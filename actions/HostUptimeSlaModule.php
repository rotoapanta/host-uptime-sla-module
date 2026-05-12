<?php
/**
 * Host Uptime & SLA – Action Controller
 *
 * Controlador principal del módulo. Procesa los parámetros del filtro
 * (grupos, hosts, SLA, rango de tiempo), consulta la base de datos de
 * Zabbix (history_uint / trends_uint) usando el ítem icmpping, calcula
 * el porcentaje de disponibilidad de cada host y pasa los datos a la vista.
 *
 * Lógica de fuente de datos:
 *   · Período >= 1 día  → trends_uint  (datos agregados por hora)
 *   · Período <  1 día  → history_uint (datos en bruto)
 *
 * @package    Modules\HostUptimeSla\Actions
 * @author     Roberto Toapanta <rtoapanta@igepn.edu.ec>
 * @version    1.1.0
 * @since      Zabbix 7.0.4
 * @copyright  2026 IG-EPN – Instituto Geofísico · Escuela Politécnica Nacional
 */

namespace Modules\HostUptimeSla\Actions;

use CController;
use CControllerResponseData;
use API;
use CProfile;
use CRangeTimeParser;

/**
 * Class HostUptimeSlaModule
 *
 * Extiende CController siguiendo el patrón MVC de Zabbix 7.
 * El nombre de la clase debe coincidir con el valor "class" definido
 * en manifest.json bajo la acción "host.uptime.sla".
 */
class HostUptimeSlaModule extends CController {

    /**
     * Deshabilita la validación CSRF para permitir peticiones GET
     * desde el time selector nativo de Zabbix (gtlc.js).
     *
     * @return void
     */
    protected function init(): void {
        $this->disableCsrfValidation();
    }

    /**
     * Valida los parámetros de entrada del request.
     *
     * La validación detallada se realiza en doAction() usando CProfile
     * y sanitización manual. Se retorna true para delegar al framework.
     *
     * @return bool
     */
    protected function checkInput(): bool {
        return true;
    }

    /**
     * Verifica permisos de acceso al módulo.
     *
     * Actualmente permite acceso a todos los usuarios autenticados.
     * Para restringir por rol usar: $this->checkAccess(CRoleHelper::UI_*)
     *
     * @return bool
     */
    protected function checkPermissions(): bool {
        return true;
    }

    /**
     * Lógica principal del controlador.
     *
     * Flujo:
     *  1. Lee y sanitiza filtros (groupids, hostids, sla, from, to)
     *  2. Persiste filtros en el perfil de usuario (CProfile)
     *  3. Parsea el rango de tiempo relativo a timestamps Unix
     *  4. Obtiene hosts monitoreados via API de Zabbix
     *  5. Consulta itemid del ítem icmpping por host
     *  6. Consulta history_uint o trends_uint según el período
     *  7. Calcula disponibilidad, downtime y cumplimiento de SLA
     *  8. Ordena resultados de menor a mayor disponibilidad
     *  9. Calcula estadísticas globales
     * 10. Pasa datos a la vista via CControllerResponseData
     *
     * @return void
     */
    protected function doAction(): void {

        // ── 1. Filtros ────────────────────────────────────────────────────────

        $filter_groupids = array_map('intval',
            array_key_exists('filter_groupids', $_REQUEST) ? (array) $_REQUEST['filter_groupids'] : []
        );

        $filter_hostids = array_map('intval',
            array_key_exists('filter_hostids', $_REQUEST) ? (array) $_REQUEST['filter_hostids'] : []
        );

        $filter_sla_raw = $_REQUEST['filter_sla'] ?? CProfile::get('web.avail_report.filter.sla', '99.0');
        $filter_sla = (float) str_replace(',', '.', $filter_sla_raw);

        if ($filter_sla < 0 || $filter_sla > 100) {
            $filter_sla = 99.0;
        }

        CProfile::update('web.avail_report.filter.sla', (string) $filter_sla, PROFILE_TYPE_STR);

        // ── 2. Rango de tiempo ────────────────────────────────────────────────

        $from = $_REQUEST['from'] ?? CProfile::get('web.avail_report.filter.from', 'now-30d');
        $to   = $_REQUEST['to']   ?? CProfile::get('web.avail_report.filter.to', 'now');

        if ($from === '') { $from = 'now-30d'; }
        if ($to   === '') { $to   = 'now'; }

        CProfile::update('web.avail_report.filter.from', $from, PROFILE_TYPE_STR);
        CProfile::update('web.avail_report.filter.to',   $to,   PROFILE_TYPE_STR);

        // ── 3. Parseo de tiempo relativo → timestamps Unix ────────────────────

        $range_parser = new CRangeTimeParser();

        $range_parser->parse($from);
        $from_dt   = $range_parser->getDateTime(true);
        $time_from = $from_dt ? $from_dt->getTimestamp() : strtotime('-30 days');

        $range_parser->parse($to);
        $to_dt     = $range_parser->getDateTime(false);
        $time_till = $to_dt ? $to_dt->getTimestamp() : time();

        // Protección contra rangos invertidos
        if ($time_till <= $time_from) {
            $from      = 'now-30d';
            $to        = 'now';
            $time_from = strtotime('-30 days');
            $time_till = time();
        }

        // ── 4. Datos de grupos y hosts seleccionados (para el multiselect) ────

        $groups_data = [];

        if ($filter_groupids) {
            foreach (API::HostGroup()->get([
                'output'   => ['groupid', 'name'],
                'groupids' => $filter_groupids
            ]) as $group) {
                $groups_data[] = ['id' => $group['groupid'], 'name' => $group['name']];
            }
        }

        $hosts_data = [];

        if ($filter_hostids) {
            foreach (API::Host()->get([
                'output'  => ['hostid', 'name'],
                'hostids' => $filter_hostids
            ]) as $host) {
                $hosts_data[] = ['id' => $host['hostid'], 'name' => $host['name']];
            }
        }

        // ── 5. Obtención de hosts monitoreados ────────────────────────────────

        $host_options = [
            'output'           => ['hostid', 'name', 'host', 'active_available', 'maintenance_status'],
            'selectHostGroups' => ['groupid', 'name'],
            'selectInterfaces' => ['interfaceid', 'type', 'main', 'useip', 'ip', 'dns', 'port', 'available'],
            'monitored_hosts'  => true,
            'preservekeys'     => true
        ];

        if ($filter_groupids) { $host_options['groupids'] = $filter_groupids; }
        if ($filter_hostids)  { $host_options['hostids']  = $filter_hostids; }

        $hosts = API::Host()->get($host_options);

        $rows           = [];
        $source         = 'sin datos';
        $period_seconds = max(1, $time_till - $time_from);

        if ($hosts) {

            // ── 6. Mapeo host → itemid del ítem icmpping ──────────────────────

            $host_ids_str = implode(',', array_map('intval', array_keys($hosts)));

            $db_error = null;
            try {
                $res = DBselect(
                    "SELECT itemid, hostid FROM items
                     WHERE key_='icmpping'
                     AND status=0
                     AND hostid IN ($host_ids_str)"
                );
            } catch (Exception $e) {
                $db_error = 'Error icmpping: ' . $e->getMessage();
                $res = null;
            }

            $host_item_map = [];
            if ($res) {
                while ($item = DBfetch($res)) {
                    $host_item_map[$item['hostid']] = $item['itemid'];
                }
            }

            // ── 7. Consulta de disponibilidad (history o trends) ──────────────

            $istats      = [];
            $period_days = $period_seconds / 86400;
            $source      = ($period_days >= 1) ? 'trends_uint' : 'history_uint';

            if ($host_item_map) {
                $iids = implode(',', array_map('intval', array_values($host_item_map)));

                /**
                 * trends_uint: datos agregados por hora.
                 *   value_avg → promedio de pings exitosos en la hora (0.0 – 1.0)
                 *   num       → cantidad de muestras en esa hora
                 *   up_sum    → suma ponderada de disponibilidad
                 *
                 * history_uint: datos en bruto (1 = up, 0 = down por muestra).
                 */
                if ($period_days >= 1) {
                    $sql = "SELECT itemid,
                                   SUM(num) AS total,
                                   SUM(value_avg * num) AS up_sum
                            FROM trends_uint
                            WHERE itemid IN ($iids)
                              AND clock >= $time_from
                              AND clock <= $time_till
                            GROUP BY itemid";
                }
                else {
                    $sql = "SELECT itemid,
                                   COUNT(*) AS total,
                                   SUM(value) AS up_sum
                            FROM history_uint
                            WHERE itemid IN ($iids)
                              AND clock >= $time_from
                              AND clock <= $time_till
                            GROUP BY itemid";
                }

                try {
                    $rs = DBselect($sql);
                    while ($stat = DBfetch($rs)) {
                        $istats[$stat['itemid']] = [
                            'total'  => (float) $stat['total'],
                            'up_sum' => (float) $stat['up_sum']
                        ];
                    }
                } catch (Exception $e) {
                    $db_error = 'Error ' . $source . ': ' . $e->getMessage();
                }
            }
            else {
                $source = 'sin item icmpping';
            }

            // ── 8. Construcción de filas por host ─────────────────────────────

            $type_map = [1 => 'Agent', 2 => 'SNMP', 3 => 'IPMI', 4 => 'JMX'];

            foreach ($hosts as $hostid => $host) {

                // Interfaz principal (main=1) o primera disponible
                $main_interface = null;

                if (!empty($host['interfaces'])) {
                    foreach ($host['interfaces'] as $interface) {
                        if ((int) $interface['main'] === 1) {
                            $main_interface = $interface;
                            break;
                        }
                    }

                    if ($main_interface === null) {
                        $main_interface = reset($host['interfaces']);
                    }
                }

                $ip                 = '—';
                $interface_type     = '—';
                $interface_port     = '—';
                $interface_available = 0;

                if ($main_interface) {
                    $ip = ((int) $main_interface['useip'] === 1 && $main_interface['ip'] !== '')
                        ? $main_interface['ip']
                        : ($main_interface['dns'] !== '' ? $main_interface['dns'] : '—');

                    $interface_type      = $type_map[(int) $main_interface['type']] ?? 'Otro';
                    $interface_port      = $main_interface['port'] ?? '—';
                    $interface_available = (int) ($main_interface['available'] ?? 0);
                }

                // Estado del host: maint > down > up
                $status = 'up';

                if ((int) ($host['maintenance_status'] ?? 0) === 1) {
                    $status = 'maint';
                }
                elseif ((int) ($host['active_available'] ?? 0) === 2 || $interface_available === 2) {
                    $status = 'down';
                }

                // Cálculo de disponibilidad y tiempo caído
                $itemid = $host_item_map[$hostid] ?? null;

                if ($itemid && isset($istats[$itemid]) && $istats[$itemid]['total'] > 0) {
                    $total        = $istats[$itemid]['total'];
                    $up_sum       = $istats[$itemid]['up_sum'];
                    $availability = round(($up_sum / $total) * 100, 4);
                    $down_sec     = (int) round($period_seconds * (1 - ($availability / 100)));
                }
                else {
                    $availability = null;
                    $down_sec     = 0;
                }

                $rows[] = [
                    'hostid'         => $hostid,
                    'name'           => $host['name'],
                    'host'           => $host['host'],
                    'ip'             => $ip,
                    'interface_type' => $interface_type,
                    'interface_port' => $interface_port,
                    'availability'   => $availability,
                    'downtime_sec'   => $down_sec,
                    'status'         => $status,
                    'sla_ok'         => ($availability !== null && $availability >= $filter_sla)
                ];
            }
        }

        // ── 9. Ordenar de menor a mayor disponibilidad (nulls al final) ───────

        usort($rows, function ($a, $b) {
            if ($a['availability'] === null && $b['availability'] === null) return 0;
            if ($a['availability'] === null) return 1;
            if ($b['availability'] === null) return -1;
            return $a['availability'] <=> $b['availability'];
        });

        // ── 10. Estadísticas globales ─────────────────────────────────────────

        $rows_with_data = array_filter($rows, fn($row) => $row['availability'] !== null);

        $avg = $rows_with_data
            ? array_sum(array_column($rows_with_data, 'availability')) / count($rows_with_data)
            : 0;

        $sla_ok   = count(array_filter($rows_with_data, fn($row) => $row['sla_ok']));
        $sla_fail = count($rows_with_data) - $sla_ok;

        // ── Respuesta ─────────────────────────────────────────────────────────

        $this->setResponse(new CControllerResponseData([
            'title'          => _('Host Uptime & SLA'),
            'rows'           => $rows,
            'stats'          => [
                'total'       => count($rows),
                'avg'         => round($avg, 2),
                'sla_ok'      => $sla_ok,
                'sla_fail'    => $sla_fail,
                'online'      => count(array_filter($rows, fn($row) => $row['status'] === 'up')),
                'offline'     => count(array_filter($rows, fn($row) => $row['status'] === 'down')),
                'maintenance' => count(array_filter($rows, fn($row) => $row['status'] === 'maint'))
            ],
            'filter_groupids' => $filter_groupids,
            'filter_hostids'  => $filter_hostids,
            'filter_sla'      => $filter_sla,
            'from'            => $from,
            'to'              => $to,
            'groups_data'     => $groups_data,
            'hosts_data'      => $hosts_data,
            'time_from'       => $time_from,
            'time_till'       => $time_till,
            'source'          => $source,
            'db_error'        => $db_error ?? null,
            'active_tab'      => CProfile::get('web.avail_report.filter.active', 1),
            'debug'           => [
                'request_sla'  => $_REQUEST['filter_sla'] ?? 'NO_LLEGA',
                'request_from' => $_REQUEST['from']       ?? 'NO_LLEGA',
                'request_to'   => $_REQUEST['to']         ?? 'NO_LLEGA',
                'sla_final'    => $filter_sla,
                'from_final'   => $from,
                'to_final'     => $to,
                'time_from'    => date('Y-m-d H:i:s', $time_from),
                'time_till'    => date('Y-m-d H:i:s', $time_till)
            ]
        ]));
    }
}
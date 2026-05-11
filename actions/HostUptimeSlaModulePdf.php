<?php
/**
 * Host Uptime & SLA – PDF Action Controller
 *
 * Controlador para la vista PDF del módulo. Reutiliza la misma lógica
 * de datos que HostUptimeSlaModule pero usa layout.print (sin sidebar
 * ni header de Zabbix) para generar una página limpia exportable como PDF.
 *
 * URL: zabbix.php?action=host.uptime.sla.pdf&from=...&to=...&filter_sla=...
 *
 * @package    Modules\HostUptimeSla\Actions
 * @author     Roberto Toapanta <rtoapanta@igepn.edu.ec>
 * @version    1.0.0
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
 * Class HostUptimeSlaModulePdf
 *
 * El nombre debe coincidir con "class" en manifest.json bajo "host.uptime.sla.pdf".
 */
class HostUptimeSlaModulePdf extends CController {

    /** @inheritdoc */
    protected function init(): void {
        $this->disableCsrfValidation();
    }

    /** @inheritdoc */
    protected function checkInput(): bool {
        return true;
    }

    /** @inheritdoc */
    protected function checkPermissions(): bool {
        return true;
    }

    /**
     * Procesa filtros, consulta datos y pasa a la vista PDF.
     *
     * @return void
     */
    protected function doAction(): void {

        // ── Filtros ───────────────────────────────────────────────────────────

        $filter_groupids = array_map('intval',
            array_key_exists('filter_groupids', $_REQUEST) ? (array) $_REQUEST['filter_groupids'] : []
        );

        $filter_hostids = array_map('intval',
            array_key_exists('filter_hostids', $_REQUEST) ? (array) $_REQUEST['filter_hostids'] : []
        );

        $filter_sla_raw = $_REQUEST['filter_sla'] ?? CProfile::get('web.avail_report.filter.sla', '99.0');
        $filter_sla     = (float) str_replace(',', '.', $filter_sla_raw);

        if ($filter_sla < 0 || $filter_sla > 100) { $filter_sla = 99.0; }

        // ── Rango de tiempo ───────────────────────────────────────────────────

        $from = $_REQUEST['from'] ?? CProfile::get('web.avail_report.filter.from', 'now-30d');
        $to   = $_REQUEST['to']   ?? CProfile::get('web.avail_report.filter.to', 'now');

        if ($from === '') { $from = 'now-30d'; }
        if ($to   === '') { $to   = 'now'; }

        $range_parser = new CRangeTimeParser();

        $range_parser->parse($from);
        $from_dt   = $range_parser->getDateTime(true);
        $time_from = $from_dt ? $from_dt->getTimestamp() : strtotime('-30 days');

        $range_parser->parse($to);
        $to_dt     = $range_parser->getDateTime(false);
        $time_till = $to_dt ? $to_dt->getTimestamp() : time();

        if ($time_till <= $time_from) {
            $from = 'now-30d'; $to = 'now';
            $time_from = strtotime('-30 days');
            $time_till = time();
        }

        // ── Hosts ─────────────────────────────────────────────────────────────

        $host_options = [
            'output'           => ['hostid', 'name', 'host', 'active_available', 'maintenance_status'],
            'selectHostGroups' => ['groupid', 'name'],
            'selectInterfaces' => ['interfaceid', 'type', 'main', 'useip', 'ip', 'dns', 'port', 'available'],
            'monitored_hosts'  => true,
            'preservekeys'     => true
        ];

        if ($filter_groupids) { $host_options['groupids'] = $filter_groupids; }
        if ($filter_hostids)  { $host_options['hostids']  = $filter_hostids; }

        $hosts          = API::Host()->get($host_options);
        $rows           = [];
        $source         = 'sin datos';
        $period_seconds = max(1, $time_till - $time_from);

        if ($hosts) {
            $host_ids_str  = implode(',', array_map('intval', array_keys($hosts)));
            $res           = DBselect(
                "SELECT itemid, hostid FROM items
                 WHERE key_='icmpping' AND status=0 AND hostid IN ($host_ids_str)"
            );
            $host_item_map = [];
            while ($item = DBfetch($res)) {
                $host_item_map[$item['hostid']] = $item['itemid'];
            }

            $istats      = [];
            $period_days = $period_seconds / 86400;
            $source      = ($period_days >= 1) ? 'trends_uint' : 'history_uint';

            if ($host_item_map) {
                $iids = implode(',', array_map('intval', array_values($host_item_map)));
                $sql  = $period_days >= 1
                    ? "SELECT itemid, SUM(num) AS total, SUM(value_avg * num) AS up_sum
                       FROM trends_uint WHERE itemid IN ($iids)
                       AND clock >= $time_from AND clock <= $time_till GROUP BY itemid"
                    : "SELECT itemid, COUNT(*) AS total, SUM(value) AS up_sum
                       FROM history_uint WHERE itemid IN ($iids)
                       AND clock >= $time_from AND clock <= $time_till GROUP BY itemid";

                $rs = DBselect($sql);
                while ($stat = DBfetch($rs)) {
                    $istats[$stat['itemid']] = [
                        'total'  => (float) $stat['total'],
                        'up_sum' => (float) $stat['up_sum']
                    ];
                }
            } else {
                $source = 'sin item icmpping';
            }

            $type_map = [1 => 'Agent', 2 => 'SNMP', 3 => 'IPMI', 4 => 'JMX'];

            foreach ($hosts as $hostid => $host) {
                $main_interface = null;
                if (!empty($host['interfaces'])) {
                    foreach ($host['interfaces'] as $iface) {
                        if ((int) $iface['main'] === 1) { $main_interface = $iface; break; }
                    }
                    if ($main_interface === null) { $main_interface = reset($host['interfaces']); }
                }

                $ip = '—'; $interface_type = '—'; $interface_port = '—'; $interface_available = 0;

                if ($main_interface) {
                    $ip                  = ((int) $main_interface['useip'] === 1 && $main_interface['ip'] !== '')
                                         ? $main_interface['ip']
                                         : ($main_interface['dns'] !== '' ? $main_interface['dns'] : '—');
                    $interface_type      = $type_map[(int) $main_interface['type']] ?? 'Otro';
                    $interface_port      = $main_interface['port'] ?? '—';
                    $interface_available = (int) ($main_interface['available'] ?? 0);
                }

                $status = 'up';
                if ((int) ($host['maintenance_status'] ?? 0) === 1) { $status = 'maint'; }
                elseif ((int) ($host['active_available'] ?? 0) === 2 || $interface_available === 2) { $status = 'down'; }

                $itemid = $host_item_map[$hostid] ?? null;
                if ($itemid && isset($istats[$itemid]) && $istats[$itemid]['total'] > 0) {
                    $total        = $istats[$itemid]['total'];
                    $up_sum       = $istats[$itemid]['up_sum'];
                    $availability = round(($up_sum / $total) * 100, 4);
                    $down_sec     = (int) round($period_seconds * (1 - ($availability / 100)));
                } else {
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

        usort($rows, function ($a, $b) {
            if ($a['availability'] === null && $b['availability'] === null) return 0;
            if ($a['availability'] === null) return 1;
            if ($b['availability'] === null) return -1;
            return $a['availability'] <=> $b['availability'];
        });

        $rows_with_data = array_filter($rows, fn($r) => $r['availability'] !== null);
        $avg      = $rows_with_data
            ? array_sum(array_column($rows_with_data, 'availability')) / count($rows_with_data)
            : 0;
        $sla_ok   = count(array_filter($rows_with_data, fn($r) => $r['sla_ok']));
        $sla_fail = count($rows_with_data) - $sla_ok;

        $this->setResponse(new CControllerResponseData([
            'title'           => 'Host_Uptime_SLA_' . date('Y-m-d_H-i'),
            'rows'            => $rows,
            'stats'           => [
                'total'       => count($rows),
                'avg'         => round($avg, 2),
                'sla_ok'      => $sla_ok,
                'sla_fail'    => $sla_fail,
                'online'      => count(array_filter($rows, fn($r) => $r['status'] === 'up')),
                'offline'     => count(array_filter($rows, fn($r) => $r['status'] === 'down')),
                'maintenance' => count(array_filter($rows, fn($r) => $r['status'] === 'maint'))
            ],
            'filter_groupids' => $filter_groupids,
            'filter_hostids'  => $filter_hostids,
            'filter_sla'      => $filter_sla,
            'from'            => $from,
            'to'              => $to,
            'time_from'       => $time_from,
            'time_till'       => $time_till,
            'source'          => $source
        ]));
    }
}

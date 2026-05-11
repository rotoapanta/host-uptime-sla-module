<?php
/**
 * Host Uptime & SLA – Module Entry Point
 *
 * Registra el módulo en el sistema de menús de Zabbix 7 e inyecta
 * el ítem "Host Uptime & SLA" al final del menú Reports.
 *
 * @package    Modules\HostUptimeSla
 * @author     Roberto Toapanta <rtoapanta@igepn.edu.ec>
 * @version    1.0.0
 * @since      Zabbix 7.0.4
 * @copyright  2026 IG-EPN – Instituto Geofísico · Escuela Politécnica Nacional
 */

namespace Modules\HostUptimeSla;

use Zabbix\Core\CModule;
use APP;
use CMenuItem;

/**
 * Class Module
 *
 * Punto de entrada del módulo. Zabbix instancia esta clase automáticamente
 * al cargar el módulo habilitado desde Administration → Modules.
 */
class Module extends CModule {

    /**
     * Inicializa el módulo e inserta el ítem de menú en Reports.
     *
     * Se ejecuta una sola vez por request, antes de renderizar la página.
     * Utiliza insertAfter('Notifications') para colocar el ítem al final
     * de los ítems nativos del menú Reports de Zabbix.
     *
     * @return void
     */
    public function init(): void {
        $mainMenu = APP::Component()->get('menu.main');

        $parent = $mainMenu->findOrAdd(_('Reports'));

        $parent->getSubmenu()->insertAfter(
            _('Notifications'),
            (new CMenuItem(_('Host Uptime & SLA')))
                ->setAction('host.uptime.sla')
        );
    }
}
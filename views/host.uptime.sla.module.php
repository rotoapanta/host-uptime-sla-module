<?php
// ============================================================
//  Host Uptime & SLA – view
//  Version : 3.0.0
//  Autor   : rtoapanta · IG-EPN
//  Cambios : CFilter nativo + carga manual de class.tabfilter.js
//            y class.tabfilteritem.js para activar rangos rápidos
//            y calendario nativo de Zabbix 7.
// ============================================================

// JS nativos de Zabbix requeridos por el time selector:
//   · class.calendar.js     → toggleCalendar() — abre el picker de fecha
//   · gtlc.js               → rangos rápidos, botones < Zoom out >, Apply
//   · class.tabfilter.js    → componente tab filter
//   · class.tabfilteritem.js → items del tab filter
$this->addJsFile('class.calendar.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('class.tabfilter.js');
$this->addJsFile('class.tabfilteritem.js');

// ── Datos del controller ──────────────────────────────────────────────────────
$rows        = $data['rows'];
$stats       = $data['stats'];
$filter_sla  = (float) $data['filter_sla'];
$from        = $data['from'];
$to          = $data['to'];
$groups_data = $data['groups_data'];
$hosts_data  = $data['hosts_data'];
$time_from   = $data['time_from'];
$time_till   = $data['time_till'];
$source      = $data['source'] ?? '—';
$debug       = $data['debug'] ?? [];

/**
 * Flag de depuración.
 * true  → muestra la barra DEBUG con parámetros del request y tiempos.
 * false → oculta la barra (modo producción).
 */
$show_debug = false;

// ── Helpers ───────────────────────────────────────────────────────────────────

function ar_format_downtime(int $seconds): string {
    $years   = intdiv($seconds, 31536000); $seconds %= 31536000;
    $months  = intdiv($seconds, 2592000);  $seconds %= 2592000;
    $days    = intdiv($seconds, 86400);    $seconds %= 86400;
    $hours   = intdiv($seconds, 3600);     $seconds %= 3600;
    $minutes = intdiv($seconds, 60);       $seconds %= 60;

    $out = [];
    if ($years)   $out[] = $years . 'a';
    if ($months)  $out[] = $months . 'm';
    if ($days)    $out[] = $days . 'd';
    if ($hours)   $out[] = $hours . 'h';
    if ($minutes) $out[] = $minutes . 'min';
    if ($seconds || empty($out)) $out[] = $seconds . 's';

    return implode(' ', $out);
}

function ar_availability_bar($availability) {
    if ($availability === null) {
        return (new CSpan(_('Sin datos')))->addClass(ZBX_STYLE_GREY);
    }

    $percent = max(0, min(100, round((float) $availability)));
    $filled  = (int) round(($percent / 100) * 10);
    $bars    = [];

    for ($i = 0; $i < 10; $i++) {
        if ($i >= $filled)  $class = 'ar-bar-empty';
        elseif ($i < 2)     $class = 'ar-bar-red';
        elseif ($i < 5)     $class = 'ar-bar-yellow';
        else                $class = 'ar-bar-green';

        $bars[] = (new CSpan(''))->addClass('ar-bar ' . $class);
    }

    return (new CDiv([
        (new CDiv($bars))->addClass('ar-bar-box'),
        (new CSpan($percent . '%'))->addClass('ar-bar-percent')
    ]))->addClass('ar-availability');
}

function ar_status_badge(string $status) {
    if ($status === 'down')  return (new CSpan('OFFLINE'))->addClass('ar-badge ar-badge-red');
    if ($status === 'maint') return (new CSpan('MAINT'))->addClass('ar-badge ar-badge-amber');
    return (new CSpan('ONLINE'))->addClass('ar-badge ar-badge-green');
}

// ── Filter ────────────────────────────────────────────────────────────────────

$filter_column = (new CFormList())
    ->addRow(
        new CLabel(_('Host groups'), 'filter_groupids__ms'),
        (new CMultiSelect([
            'name'        => 'filter_groupids[]',
            'object_name' => 'hostGroup',
            'data'        => $groups_data,
            'popup'       => [
                'parameters' => [
                    'srctbl'  => 'host_groups',
                    'srcfld1' => 'groupid',
                    'srcfld2' => 'name',
                    'dstfrm'  => 'zbx_filter',
                    'dstfld1' => 'filter_groupids_'
                ]
            ]
        ]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
    )
    ->addRow(
        new CLabel(_('Hosts'), 'filter_hostids__ms'),
        (new CMultiSelect([
            'name'        => 'filter_hostids[]',
            'object_name' => 'hosts',
            'data'        => $hosts_data,
            'popup'       => [
                'parameters' => [
                    'srctbl'          => 'hosts',
                    'srcfld1'         => 'hostid',
                    'srcfld2'         => 'host',
                    'dstfrm'          => 'zbx_filter',
                    'dstfld1'         => 'filter_hostids_',
                    'monitored_hosts' => 1
                ]
            ]
        ]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
    )
    ->addRow(
        new CLabel(_('SLA mínimo (%)'), 'filter_sla'),
        (new CSelect('filter_sla'))
            ->setId('filter_sla')
            ->addOptions(CSelect::createOptionsFromArray([
                '99.9' => '99.9%',
                '99.5' => '99.5%',
                '99.0' => '99.0%',
                '98.0' => '98.0%',
                '95.0' => '95.0%'
            ]))
            ->setValue(number_format($filter_sla, 1))
    );

/*
 * CFilter::addTimeSelector() genera:
 *   · CDateSelector('from') → input + botón calendario
 *   · CDateSelector('to')   → input + botón calendario
 *   · Lista de rangos rápidos con data-from / data-to
 *   · Botón Apply
 *
 * El botón calendario llama a toggleCalendar() que está en
 * class.calendar.js — cargado automáticamente por el layout htmlpage.
 *
 * Los rangos rápidos los intercepta class.tabfilter.js (cargado arriba).
 */
$filter = (new CFilter())
    ->setResetUrl(
        (new CUrl('zabbix.php'))
            ->setArgument('action', 'host.uptime.sla')
            ->setArgument('from', 'now-30d')
            ->setArgument('to', 'now')
    )
    ->setProfile('web.avail_report.filter')
    ->setActiveTab($data['active_tab'] ?? 1)
    ->addVar('action', 'host.uptime.sla')
    ->addTimeSelector($from, $to, true, 'web.avail_report.filter', ZBX_DATE_TIME)
    ->addFilterTab(_('Filter'), [$filter_column]);

// ── Table ─────────────────────────────────────────────────────────────────────

$table = (new CTableInfo())->setHeader([
    _('Host'),
    _('IP / Interfaz'),
    _('Estado'),
    _('Disponibilidad'),
    _('Tiempo caído'),
    _('SLA') . ' ' . number_format($filter_sla, 1) . '%'
]);

foreach ($rows as $row) {
    $availability = $row['availability'] !== null ? (float) $row['availability'] : null;

    $table->addRow([
        (new CDiv([
            (new CSpan($row['name']))->addClass('ar-host-name'),
            (new CSpan($row['host']))->addClass('ar-host-tech')
        ]))->addClass('ar-host-box'),

        (new CDiv([
            (new CSpan($row['ip']))->addClass('ar-ip-main'),
            (new CSpan($row['interface_type'] . ' : ' . $row['interface_port']))->addClass('ar-ip-sub')
        ]))->addClass('ar-ip-box'),

        ar_status_badge($row['status']),
        ar_availability_bar($availability),

        ($availability === null)
            ? (new CSpan('—'))->addClass(ZBX_STYLE_GREY)
            : (new CSpan(ar_format_downtime((int) $row['downtime_sec'])))
                ->addClass((int) $row['downtime_sec'] > 0 ? ZBX_STYLE_RED : ZBX_STYLE_GREEN),

        ($availability === null)
            ? (new CSpan('—'))->addClass(ZBX_STYLE_GREY)
            : (
                $row['sla_ok']
                    ? (new CSpan('OK'))->addClass(ZBX_STYLE_GREEN)
                    : (new CSpan('FAIL'))->addClass(ZBX_STYLE_RED)
            )
    ]);
}

// ── Debug bar ─────────────────────────────────────────────────────────────────

$debug_text = 'DEBUG'
    . ' | request_from='  . ($debug['request_from'] ?? 'NO_DEBUG')
    . ' | request_to='    . ($debug['request_to']   ?? 'NO_DEBUG')
    . ' | final_from='    . ($debug['from_final']   ?? $from)
    . ' | final_to='      . ($debug['to_final']     ?? $to)
    . ' | time_from='     . date('Y-m-d H:i:s', $time_from)
    . ' | time_till='     . date('Y-m-d H:i:s', $time_till)
    . ' | SLA='           . number_format($filter_sla, 1) . '%';

// ── Botón PDF ─────────────────────────────────────────────────────────────────

/**
 * Botón PDF — abre la vista host.uptime.sla.pdf en nueva pestaña.
 * Usa layout.print (sin UI de Zabbix) y se auto-imprime al cargar.
 * Preserva todos los filtros activos (from, to, sla) en la URL.
 */
$pdf_base_url = (new CUrl('zabbix.php'))
    ->setArgument('action',     'host.uptime.sla.pdf')
    ->setArgument('from',       $from)
    ->setArgument('to',         $to)
    ->setArgument('filter_sla', number_format($filter_sla, 1))
    ->getUrl();

$pdf_button = (new CSimpleButton(_('Download PDF')))
    ->addClass(ZBX_STYLE_BTN)
    ->addClass(ZBX_STYLE_BTN_ALT)
    ->setAttribute('id', 'ar-pdf-btn')
    ->setAttribute('data-pdf-url', $pdf_base_url)
    ->setAttribute('onclick', 'arOpenPdf(this); return false;')
    ->setAttribute('title', _('Open PDF report in new tab'));

// ── Page content ──────────────────────────────────────────────────────────────

$page_content = new CDiv([
    $pdf_button,
    $show_debug ? (new CDiv($debug_text))->addClass('ar-debug') : null,

    (new CDiv([
        (new CDiv([(new CDiv($stats['total']))->addClass('ar-sv'),              (new CDiv(_('Total hosts')))->addClass('ar-sl')]))->addClass('ar-stat'),
        (new CDiv([(new CDiv($stats['online']))->addClass('ar-sv ar-sv-green'), (new CDiv(_('Online')))->addClass('ar-sl')]))->addClass('ar-stat'),
        (new CDiv([(new CDiv($stats['offline']))->addClass('ar-sv ar-sv-red'),  (new CDiv(_('Offline')))->addClass('ar-sl')]))->addClass('ar-stat'),
        (new CDiv([(new CDiv(number_format($stats['avg'], 1) . '%'))->addClass('ar-sv ar-sv-sm'), (new CDiv(_('Disponibilidad media')))->addClass('ar-sl')]))->addClass('ar-stat'),
        (new CDiv([(new CDiv($stats['sla_ok']))->addClass('ar-sv ar-sv-green'), (new CDiv(_('Cumplen SLA')))->addClass('ar-sl')]))->addClass('ar-stat'),
        (new CDiv([(new CDiv($stats['sla_fail']))->addClass('ar-sv ar-sv-red'), (new CDiv(_('Incumplen SLA')))->addClass('ar-sl')]))->addClass('ar-stat')
    ]))->addClass('ar-stats'),

    (new CDiv(
        _('Período') . ': ' . date('d/m/Y H:i', $time_from) .
        ' — '        . date('d/m/Y H:i', $time_till) .
        ' · Fuente: ' . $source .
        ' · SLA: '   . number_format($filter_sla, 1) . '%'
    ))->addClass('ar-info'),

    $table,

    (new CDiv('© 2026 Roberto Toapanta · IG-EPN · All rights reserved'))
        ->addClass('ar-footer')
]);

// ── Render ────────────────────────────────────────────────────────────────────

(new CHtmlPage())
    ->setTitle(_('Host Uptime & SLA'))
    ->addItem($filter)
    ->addItem($page_content)
    ->show();
?>

<style>
.ar-debug{background:#fff7e6;border:1px solid #ffcc80;color:#8a4b00;padding:8px 10px;margin:8px 0;font-size:12px}
.ar-stats{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}
.ar-stat{background:#fff;border:1px solid #d4d4d4;padding:12px;text-align:center;flex:1;min-width:105px}
.ar-sv{font-size:22px;font-weight:700;color:#1976d2}
.ar-sv-sm{font-size:18px;font-weight:700}
.ar-sv-green{color:#2e7d32}.ar-sv-red{color:#e53935}
.ar-sl{font-size:10px;color:#888}
.ar-info{font-size:11px;color:#999;margin:0 0 8px}
.ar-host-box,.ar-ip-box{display:flex;flex-direction:column;line-height:1.2}
.ar-host-name{font-weight:600;color:#1f2933}
.ar-host-tech,.ar-ip-sub{font-size:10px;color:#777}
.ar-ip-main{font-weight:600}
.ar-badge{display:inline-block;min-width:72px;text-align:center;padding:3px 8px;border-radius:3px;font-size:10px;font-weight:700}
.ar-badge-green{background:#e8f5e9;color:#1b5e20}
.ar-badge-red{background:#ffebee;color:#b71c1c}
.ar-badge-amber{background:#fff3e0;color:#e65100}
.ar-availability{display:flex;align-items:center;gap:10px}
.ar-bar-box{display:flex;gap:1px;background:#fff;padding:2px;border-radius:4px;border:1px solid #aaa}
.ar-bar{width:8px;height:20px;display:inline-block;border-radius:2px}
.ar-bar-red{background:#e53935}.ar-bar-yellow{background:#fbc02d}
.ar-bar-green{background:#43a047}.ar-bar-empty{background:#e0e0e0}
.ar-bar-percent{font-weight:700;font-size:13px;min-width:42px}
.ar-footer{text-align:center;font-size:11px;color:#999;margin-top:20px;padding-top:10px;border-top:1px solid #e0e0e0}
#ar-pdf-btn{margin-bottom:10px}
</style>

<script>
/**
 * gtlc.js actualiza inputs from/to via AJAX pero no hace submit.
 * Tras timeselector.rangeupdate navegamos directamente con los nuevos
 * valores — evitando hidden vars desactualizados en el form.
 */
function arOpenPdf(btn) {
    var base   = btn.getAttribute('data-pdf-url');
    var params = [];

    document.querySelectorAll('[name="filter_groupids[]"]').forEach(function(el) {
        if (el.value) params.push('filter_groupids[]=' + encodeURIComponent(el.value));
    });
    document.querySelectorAll('[name="filter_hostids[]"]').forEach(function(el) {
        if (el.value) params.push('filter_hostids[]=' + encodeURIComponent(el.value));
    });

    window.open(base + (params.length ? '&' + params.join('&') : ''), '_blank');
}

jQuery(function($) {
    $.subscribe('timeselector.rangeupdate', function(e, data) {
        if (!('from' in data) || !('to' in data)) return;

        var url = new Curl('zabbix.php');
        url.setArgument('action', 'host.uptime.sla');
        url.setArgument('from', data.from);
        url.setArgument('to', data.to);
        url.setArgument('filter_set', '1');

        // Preservar SLA si está seleccionado
        var sla = $('[name="filter_sla"]').val();
        if (sla) url.setArgument('filter_sla', sla);

        // Preservar grupos y hosts seleccionados
        $('[name="filter_groupids[]"]').each(function() {
            if ($(this).val()) url.setArgument('filter_groupids[]', $(this).val());
        });
        $('[name="filter_hostids[]"]').each(function() {
            if ($(this).val()) url.setArgument('filter_hostids[]', $(this).val());
        });

        window.location.href = url.getUrl();
    });
});
</script>
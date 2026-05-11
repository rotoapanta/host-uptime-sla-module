<?php
/**
 * Host Uptime & SLA – PDF Report View
 *
 * Página HTML limpia renderizada con layout.print (sin sidebar ni header
 * de Zabbix). Diseñada para exportarse como PDF desde el navegador.
 *
 * Características:
 *   · Colores corporativos IG-EPN (#8B4513, #C8860A)
 *   · Encabezado con logo, título y metadatos del período
 *   · Tarjetas de resumen estadístico
 *   · Gráfico de barras de disponibilidad (Chart.js CDN)
 *   · Tabla completa con barras de color, badges y SLA
 *   · Auto-print al cargar + nombre de archivo sugerido
 *   · @page A4 landscape para tabla completa sin cortes
 *
 * @package    Modules\HostUptimeSla
 * @author     Roberto Toapanta <rtoapanta@igepn.edu.ec>
 * @version    1.0.0
 * @since      Zabbix 7.0.4
 * @copyright  2026 IG-EPN – Instituto Geofísico · Escuela Politécnica Nacional
 */

// ── Datos del controller ──────────────────────────────────────────────────────
$rows       = $data['rows'];
$stats      = $data['stats'];
$filter_sla = (float) $data['filter_sla'];
$time_from  = $data['time_from'];
$time_till  = $data['time_till'];
$source     = $data['source'] ?? '—';
$title      = $data['title'];

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Formatea segundos en string legible en inglés.
 *
 * @param int $seconds
 * @return string  Ej: "2d 3h 15min"
 */
function pdf_format_downtime(int $seconds): string {
    $years   = intdiv($seconds, 31536000); $seconds %= 31536000;
    $months  = intdiv($seconds, 2592000);  $seconds %= 2592000;
    $days    = intdiv($seconds, 86400);    $seconds %= 86400;
    $hours   = intdiv($seconds, 3600);     $seconds %= 3600;
    $minutes = intdiv($seconds, 60);       $seconds %= 60;

    $out = [];
    if ($years)   $out[] = $years   . 'y';
    if ($months)  $out[] = $months  . 'mo';
    if ($days)    $out[] = $days    . 'd';
    if ($hours)   $out[] = $hours   . 'h';
    if ($minutes) $out[] = $minutes . 'min';
    if ($seconds || empty($out)) $out[] = $seconds . 's';

    return implode(' ', $out);
}

/**
 * Retorna color hex según porcentaje de disponibilidad.
 *
 * @param float|null $pct
 * @return string  Hex color
 */
function pdf_avail_color($pct): string {
    if ($pct === null) return '#aaaaaa';
    if ($pct >= 99)    return '#2e7d32';
    if ($pct >= 95)    return '#43a047';
    if ($pct >= 90)    return '#fbc02d';
    if ($pct >= 80)    return '#ef6c00';
    return '#c62828';
}

/**
 * Genera barras HTML inline para la columna disponibilidad.
 *
 * @param float|null $availability
 * @return string  HTML
 */
function pdf_bar($availability): string {
    if ($availability === null) return '<span style="color:#aaa">No data</span>';

    $percent = max(0, min(100, round($availability)));
    $filled  = (int) round(($percent / 100) * 10);
    $bars    = '';

    for ($i = 0; $i < 10; $i++) {
        if ($i >= $filled)  $color = '#e0e0e0';
        elseif ($i < 2)     $color = '#c62828';
        elseif ($i < 5)     $color = '#fbc02d';
        else                $color = '#43a047';

        $bars .= '<span style="display:inline-block;width:7px;height:13px;'
               . 'background:' . $color . ';border-radius:1px;margin-right:1px;"></span>';
    }

    $color = pdf_avail_color($availability);
    return '<div style="display:flex;align-items:center;gap:6px">'
         . '<div>' . $bars . '</div>'
         . '<strong style="color:' . $color . '">' . $percent . '%</strong>'
         . '</div>';
}

// Sin gráfico
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        /* ── Reset & base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            background: #fff;
            padding: 20px 24px;
        }

        /* ── Colores corporativos IG-EPN ── */
        :root {
            --igepn-brown: #8B4513;
            --igepn-gold:  #C8860A;
        }

        /* ── Header ── */
        .pdf-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 14px;
            border-bottom: 3px solid var(--igepn-gold);
            margin-bottom: 16px;
        }

        .pdf-header-left  { display: flex; align-items: center; gap: 16px; }
        .pdf-header img   { height: 60px; width: auto; }

        .pdf-header-title h1 {
            font-size: 18px;
            font-weight: 700;
            color: var(--igepn-brown);
        }

        .pdf-header-title p {
            font-size: 10px;
            color: #666;
            margin-top: 3px;
        }

        .pdf-header-meta {
            text-align: right;
            font-size: 10px;
            color: #555;
            line-height: 1.8;
        }

        .pdf-header-meta strong { color: var(--igepn-brown); }

        /* ── Section title ── */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--igepn-brown);
            border-left: 3px solid var(--igepn-gold);
            padding-left: 8px;
            margin: 14px 0 8px;
        }

        /* ── Stats cards ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin-bottom: 4px;
        }

        .stat-card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px 8px;
            text-align: center;
            background: #fafafa;
        }

        .stat-value { font-size: 20px; font-weight: 700; line-height: 1.1; }
        .stat-label { font-size: 9px; color: #888; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.5px; }

        .stat-blue  { color: #1565C0; border-top: 3px solid #1565C0; }
        .stat-green { color: #2e7d32; border-top: 3px solid #2e7d32; }
        .stat-red   { color: #c62828; border-top: 3px solid #c62828; }
        .stat-gold  { color: var(--igepn-gold); border-top: 3px solid var(--igepn-gold); }
        .stat-ok    { color: #2e7d32; border-top: 3px solid #2e7d32; }
        .stat-fail  { color: #c62828; border-top: 3px solid #c62828; }

        /* ── Table ── */
        table { width: 100%; border-collapse: collapse; font-size: 10px; }

        thead tr { background: var(--igepn-brown); color: #fff; }

        thead th {
            padding: 7px 8px;
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
        }

        tbody tr:nth-child(even) { background: #f9f9f9; }
        tbody tr:nth-child(odd)  { background: #ffffff; }

        tbody td {
            padding: 5px 8px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .host-name { font-weight: 600; }
        .host-tech { font-size: 9px; color: #888; }
        .ip-sub    { font-size: 9px; color: #888; }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 700;
            min-width: 55px;
            text-align: center;
        }

        .badge-online  { background: #e8f5e9; color: #1b5e20; }
        .badge-offline { background: #ffebee; color: #b71c1c; }
        .badge-maint   { background: #fff3e0; color: #e65100; }

        .sla-ok   { color: #2e7d32; font-weight: 700; }
        .sla-fail { color: #c62828; font-weight: 700; }
        .dt-ok    { color: #2e7d32; }
        .dt-fail  { color: #c62828; font-weight: 600; }

        /* ── Footer ── */
        .pdf-footer {
            margin-top: 16px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #999;
        }

        /* ── Print ── */
        @page { size: A4 landscape; margin: 10mm; }

        @media print {
            body { padding: 0; }
            tr { page-break-inside: avoid; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="pdf-header">
    <div class="pdf-header-left">
        <img src="/zabbix/modules/host-uptime-sla-module/assets/igepn_logo.png" alt="IG-EPN">
        <div class="pdf-header-title">
            <h1>Host Uptime &amp; SLA Report</h1>
            <p>Instituto Geofísico – Escuela Politécnica Nacional · Network Monitoring</p>
        </div>
    </div>
    <div class="pdf-header-meta">
        <div><strong>Period:</strong> <?= date('d/m/Y H:i', $time_from) ?> — <?= date('d/m/Y H:i', $time_till) ?></div>
        <div><strong>SLA Target:</strong> <?= number_format($filter_sla, 1) ?>%</div>
        <div><strong>Data Source:</strong> <?= htmlspecialchars($source) ?></div>
        <div><strong>Generated:</strong> <?= date('d/m/Y H:i') ?></div>
    </div>
</div>

<!-- Summary -->
<div class="section-title">Summary</div>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value stat-blue"><?= $stats['total'] ?></div>
        <div class="stat-label">Total Hosts</div>
    </div>
    <div class="stat-card">
        <div class="stat-value stat-green"><?= $stats['online'] ?></div>
        <div class="stat-label">Online</div>
    </div>
    <div class="stat-card">
        <div class="stat-value stat-red"><?= $stats['offline'] ?></div>
        <div class="stat-label">Offline</div>
    </div>
    <div class="stat-card">
        <div class="stat-value stat-gold"><?= number_format($stats['avg'], 1) ?>%</div>
        <div class="stat-label">Avg Availability</div>
    </div>
    <div class="stat-card">
        <div class="stat-value stat-ok"><?= $stats['sla_ok'] ?></div>
        <div class="stat-label">Meet SLA</div>
    </div>
    <div class="stat-card">
        <div class="stat-value stat-fail"><?= $stats['sla_fail'] ?></div>
        <div class="stat-label">Fail SLA</div>
    </div>
</div>


<!-- Table -->
<div class="section-title">Host Detail</div>
<table>
    <thead>
        <tr>
            <th style="width:26%">Host</th>
            <th style="width:15%">IP / Interface</th>
            <th style="width:9%">Status</th>
            <th style="width:26%">Availability</th>
            <th style="width:13%">Downtime</th>
            <th style="width:11%">SLA <?= number_format($filter_sla, 1) ?>%</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row):
        $avail       = $row['availability'] !== null ? (float) $row['availability'] : null;
        $badge_class = $row['status'] === 'down'  ? 'badge-offline'
                     : ($row['status'] === 'maint' ? 'badge-maint' : 'badge-online');
        $badge_text  = $row['status'] === 'down'  ? 'OFFLINE'
                     : ($row['status'] === 'maint' ? 'MAINT' : 'ONLINE');
    ?>
        <tr>
            <td>
                <div class="host-name"><?= htmlspecialchars($row['name']) ?></div>
                <div class="host-tech"><?= htmlspecialchars($row['host']) ?></div>
            </td>
            <td>
                <div><?= htmlspecialchars($row['ip']) ?></div>
                <div class="ip-sub"><?= htmlspecialchars($row['interface_type']) ?> : <?= htmlspecialchars($row['interface_port']) ?></div>
            </td>
            <td><span class="badge <?= $badge_class ?>"><?= $badge_text ?></span></td>
            <td><?= pdf_bar($avail) ?></td>
            <td class="<?= (int) $row['downtime_sec'] > 0 ? 'dt-fail' : 'dt-ok' ?>">
                <?= $avail !== null ? htmlspecialchars(pdf_format_downtime((int) $row['downtime_sec'])) : '—' ?>
            </td>
            <td>
                <?php if ($avail === null): ?>
                    <span style="color:#aaa">—</span>
                <?php elseif ($row['sla_ok']): ?>
                    <span class="sla-ok">✓ OK</span>
                <?php else: ?>
                    <span class="sla-fail">✗ FAIL</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Footer -->
<div class="pdf-footer">
    <span>© 2026 Roberto Toapanta · Instituto Geofísico – EPN · All rights reserved</span>
    <span>Host Uptime &amp; SLA Module v1.0 · Zabbix <?= ZABBIX_VERSION ?></span>
</div>

<script>
    document.title = '<?= addslashes($title) ?>';
    setTimeout(function() { window.print(); }, 400);
</script>

</body>
</html>
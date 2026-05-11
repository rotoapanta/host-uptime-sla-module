![License](https://img.shields.io/badge/license-MIT-blue.svg)
![Platform](https://img.shields.io/badge/platform-Zabbix%207.0-red.svg)
![PHP](https://img.shields.io/badge/PHP-8.1-777BB4?logo=php)
![Status](https://img.shields.io/badge/status-stable-brightgreen)
![Issues](https://img.shields.io/github/issues/rotoapanta/host-uptime-sla-module)
![Last Commit](https://img.shields.io/github/last-commit/rotoapanta/host-uptime-sla-module)
![Repo Size](https://img.shields.io/github/repo-size/rotoapanta/host-uptime-sla-module)

---

<p align="right"><strong>[EN]</strong> | <a href="README.es.md">[ES]</a></p>

# <p align="center">Host Uptime & SLA – Zabbix 7 Module</p>

## Description

**Host Uptime & SLA** is a custom module for Zabbix 7 that provides a detailed availability and SLA compliance report for all monitored hosts.

The module queries `icmpping` item data from `history_uint` or `trends_uint` (automatically selected based on the time period), calculates uptime percentage and downtime per host, and compares results against a configurable SLA target.

---

## Features

* Native Zabbix 7 time selector (calendar, quick ranges, zoom)
* Filter by host groups, hosts and SLA target
* Availability bar with color scale (red / yellow / green)
* Online / Offline / Maintenance status badge per host
* Downtime formatted in years, months, days, hours and minutes
* Global statistics: total hosts, online, offline, avg availability, meet/fail SLA
* Exportable PDF report with IG-EPN corporate branding
* Integrated into the **Reports** menu of Zabbix
* Debug bar (configurable via `$show_debug` flag)
* Deploy verification script (`deploy_check.sh`)

---

## Requirements

| Component | Version        |
|-----------|----------------|
| Zabbix    | 7.0.x          |
| PHP       | 8.1+           |
| Hosts     | `icmpping` item enabled and monitored |

---

## Module Structure

```
host-uptime-sla-module/
├── actions/
│   ├── HostUptimeSlaModule.php       ← Main controller
│   └── HostUptimeSlaModulePdf.php    ← PDF controller
├── assets/
│   └── igepn_logo.png                ← Corporate logo
├── views/
│   ├── host.uptime.sla.module.php    ← Main view
│   └── host.uptime.sla.pdf.php       ← PDF view (layout.print)
├── deploy_check.sh                   ← Deploy verifier (MD5 + size)
├── manifest.json                     ← Module manifest
├── Module.php                        ← Menu registration
└── README.md
```

---

## Installation

### 1. Copy module to Zabbix

```bash
# Create directory on the server
sudo mkdir -p /var/www/html/zabbix/modules/host-uptime-sla-module
sudo chown -R www-data:www-data /var/www/html/zabbix/modules/host-uptime-sla-module
sudo chmod -R 775 /var/www/html/zabbix/modules/host-uptime-sla-module

# Deploy from local machine
rsync -avz --progress \
  ~/path/to/host-uptime-sla-module/ \
  user@server:/var/www/html/zabbix/modules/host-uptime-sla-module/
```

### 2. Enable in Zabbix

Go to **Administration → Modules** and enable `host-uptime-sla-module`.

The module will appear under **Reports → Host Uptime & SLA**.

---

## Deploy Verification

```bash
chmod +x deploy_check.sh
./deploy_check.sh
```

Expected output:

```
╔══════════════════════════════════════════════════════════╗
║   Host Uptime & SLA Module – Deploy Verifier             ║
╚══════════════════════════════════════════════════════════╝

actions/HostUptimeSlaModule.php       [OK]  md5=abc123…  size=11200B
actions/HostUptimeSlaModulePdf.php    [OK]  md5=def456…  size=9800B
views/host.uptime.sla.module.php      [OK]  md5=ghi789…  size=14100B
views/host.uptime.sla.pdf.php         [OK]  md5=jkl012…  size=12300B
Module.php                            [OK]  md5=mno345…  size=1100B
manifest.json                         [OK]  md5=pqr678…  size=434B

✔  All files match. Deploy OK.
```

---

## Usage

### Filters

| Filter       | Description                              |
|--------------|------------------------------------------|
| Host groups  | Filter hosts by one or more groups       |
| Hosts        | Filter by specific hosts                 |
| SLA minimum  | Target SLA: 99.9 / 99.5 / 99.0 / 98.0 / 95.0 % |
| From / To    | Time range (native Zabbix time selector) |

### Data source logic

| Period        | Source         |
|---------------|----------------|
| ≥ 1 day       | `trends_uint`  |
| < 1 day       | `history_uint` |

### Debug mode

In `views/host.uptime.sla.module.php`, set:

```php
$show_debug = true;   // show debug bar
$show_debug = false;  // production mode (default)
```

---

## PDF Export

Click **Download PDF** to open the report in a new tab with:

* IG-EPN logo and corporate colors (`#8B4513`, `#C8860A`)
* Period, SLA target, data source and generation date
* Summary statistics cards
* Full host detail table with availability bars and SLA result
* Auto-print dialog on load
* Suggested filename: `Host_Uptime_SLA_YYYY-MM-DD_HH-mm.pdf`

---

## Screenshots

> *(Add screenshots here)*

---

## Contributing

Contributions are welcome!

1. Fork the repository
2. Create a new branch (`feature/new-feature`)
3. Make your changes
4. Submit a Pull Request

---

## License

This project is licensed under the MIT License.

---

## Author

**Roberto Toapanta**
Electrical Engineer · IG-EPN
Network Monitoring | Zabbix | PHP | Bash

---

## Support

If you find this project useful, consider giving it a star ⭐

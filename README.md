<p align="right"><a href="README.es.md">Español</a></p>

# <p align="center">Host Uptime & SLA – Zabbix 7 Module</p>

<p align="center">
    <a href="https://www.zabbix.com/"><img src="https://img.shields.io/badge/Zabbix-7.0.x-red" alt="Zabbix"></a>
    <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.1-777BB4?logo=php" alt="PHP"></a>
    <a href="https://github.com/rotoapanta/host-uptime-sla-module/issues"><img src="https://img.shields.io/github/issues/rotoapanta/host-uptime-sla-module" alt="GitHub issues"></a>
    <a href="https://github.com/rotoapanta/host-uptime-sla-module"><img src="https://img.shields.io/github/repo-size/rotoapanta/host-uptime-sla-module" alt="GitHub repo size"></a>
    <a href="https://github.com/rotoapanta/host-uptime-sla-module/commits"><img src="https://img.shields.io/github/last-commit/rotoapanta/host-uptime-sla-module" alt="GitHub last commit"></a>
    <a href="https://www.linux.org/"><img src="https://img.shields.io/badge/Platform-Linux-orange" alt="Linux"></a>
    <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/License-MIT-blue.svg" alt="License: MIT"></a>
    <a href="https://www.linkedin.com/in/roberto-carlos-toapanta-g/"><img src="https://img.shields.io/badge/Author-Roberto%20Toapanta-brightgreen" alt="Author"></a>
    <a href="#-changelog"><img src="https://img.shields.io/badge/Version-1.0.0-brightgreen" alt="Version"></a>
    <a href="https://github.com/rotoapanta/host-uptime-sla-module/fork"><img src="https://img.shields.io/github/forks/rotoapanta/host-uptime-sla-module?style=social" alt="GitHub forks"></a>
</p>

A custom Zabbix 7 module that provides a detailed availability and SLA compliance report for all monitored hosts. It queries `icmpping` data from `history_uint` or `trends_uint`, calculates uptime percentage and downtime per host, and compares results against a configurable SLA target.

---

## ✨ Features

- **Native Zabbix 7 UI:** Uses the native time selector (calendar, quick ranges, zoom out) and filter components.
- **Flexible Filtering:** Filter by host groups, specific hosts and SLA target percentage.
- **Visual Availability Bar:** Color-coded bar (red / yellow / green) with percentage per host.
- **Status Badges:** Online / Offline / Maintenance badge per host.
- **Downtime Formatting:** Downtime expressed in years, months, days, hours and minutes.
- **Global Statistics:** Total hosts, online, offline, avg availability, meet/fail SLA cards.
- **PDF Export:** Corporate-branded PDF report with IG-EPN logo, auto-print on load.
- **Integrated Menu:** Appears under **Reports → Host Uptime & SLA** in Zabbix.
- **Debug Mode:** Configurable via `$show_debug` flag in the view.
- **Deploy Verifier:** `deploy_check.sh` validates file integrity via MD5 + size.

---

## 🛠️ System Requirements

| Component | Version |
|-----------|---------|
| Zabbix    | 7.0.x   |
| PHP       | 8.1+    |
| Hosts     | `icmpping` item enabled and monitored |

---

## 🗂️ Project Structure

```
host-uptime-sla-module/
├── actions/
│   ├── HostUptimeSlaModule.php       # Main controller
│   └── HostUptimeSlaModulePdf.php    # PDF controller
├── assets/
│   ├── igepn_logo.png                # Corporate logo (PDF header)
│   └── screenshots/
│       ├── screenshot_main.png
│       └── screenshot_pdf.png
├── views/
│   ├── host.uptime.sla.module.php    # Main view
│   └── host.uptime.sla.pdf.php       # PDF view (layout.print)
├── deploy_check.sh                   # Deploy verifier (MD5 + size)
├── manifest.json                     # Module manifest
├── Module.php                        # Menu registration
├── README.md
└── README.es.md
```

---

## 🚀 Installation

### 1. Copy the module to the server

```bash
# Create directory on the server
sudo mkdir -p /var/www/html/zabbix/modules/host-uptime-sla-module
sudo chown -R rtoapanta:www-data /var/www/html/zabbix/modules/host-uptime-sla-module
sudo chmod -R 775 /var/www/html/zabbix/modules/host-uptime-sla-module

# Deploy from local machine
rsync -avz --progress \
  ~/path/to/host-uptime-sla-module/ \
  user@server:/var/www/html/zabbix/modules/host-uptime-sla-module/
```

### 2. Enable in Zabbix

Go to **Administration → Modules**, click **Scan directory**, then enable `host-uptime-sla-module`.

The module will appear under **Reports → Host Uptime & SLA**.

---

## ✅ Deploy Verification

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

## 📖 Usage

### Filters

| Filter      | Description                                  |
|-------------|----------------------------------------------|
| Host groups | Filter hosts by one or more groups           |
| Hosts       | Filter by specific hosts                     |
| SLA minimum | Target: 99.9 / 99.5 / 99.0 / 98.0 / 95.0 % |
| From / To   | Time range via native Zabbix time selector   |

### Data Source Logic

| Period  | Source         |
|---------|----------------|
| ≥ 1 day | `trends_uint`  |
| < 1 day | `history_uint` |

### Debug Mode

In `views/host.uptime.sla.module.php`:

```php
$show_debug = true;   // show debug bar (request params + timestamps)
$show_debug = false;  // production mode (default)
```

---

## 📄 PDF Export

Click **Download PDF** to open a clean report in a new tab with:

- IG-EPN logo and corporate colors (`#8B4513`, `#C8860A`)
- Period, SLA target, data source and generation timestamp
- Summary statistics cards
- Full host detail table with availability bars and SLA result
- Auto-print dialog on page load
- Suggested filename: `Host_Uptime_SLA_YYYY-MM-DD_HH-mm.pdf`

---

## 📸 Screenshots

### Main View
![Main View](assets/screenshots/screenshot_main.png)

### PDF Export
![PDF Export](assets/screenshots/screenshot_pdf.png)

---

## 💬 Feedback

For comments or suggestions: robertocarlos.toapanta@gmail.com

## 🛟 Support

For support, email robertocarlos.toapanta@gmail.com

## 📄 License

[MIT](https://opensource.org/licenses/MIT)

## 👥 Authors

- [@rotoapanta](https://github.com/rotoapanta)

---

## 📜 Changelog

This project follows [Keep a Changelog](https://keepachangelog.com/) and [Semantic Versioning](https://semver.org/).

### [Unreleased]
-

### 1.0.0 – 2026-05-11
- Initial stable release.
- Native Zabbix 7 time selector (calendar + quick ranges).
- Filter by host groups, hosts and SLA target.
- PDF export with IG-EPN corporate branding.
- Deploy verification script (`deploy_check.sh`).
- Debug mode flag (`$show_debug`).

---

## 🔗 Links

[![linkedin](https://img.shields.io/badge/linkedin-0A66C2?style=for-the-badge&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/roberto-carlos-toapanta-g/)

[![twitter](https://img.shields.io/badge/twitter-1DA1F2?style=for-the-badge&logo=twitter&logoColor=white)](https://twitter.com/rotoapanta)

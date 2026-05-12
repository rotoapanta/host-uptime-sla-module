# Contributing to Host Uptime & SLA – Zabbix 7 Module

Thank you for your interest in contributing! This document explains how to get involved.

---

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How to Contribute](#how-to-contribute)
- [Development Setup](#development-setup)
- [Project Structure](#project-structure)
- [Coding Standards](#coding-standards)
- [Submitting a Pull Request](#submitting-a-pull-request)
- [Reporting Issues](#reporting-issues)

---

## Code of Conduct

Be respectful and constructive. Contributions of all levels are welcome.

---

## How to Contribute

1. **Fork** the repository
2. **Clone** your fork locally
3. **Create a branch** for your change
4. **Make your changes** following the coding standards below
5. **Test** on a Zabbix 7.0.x instance
6. **Submit a Pull Request**

---

## Development Setup

### Requirements

| Component | Version |
|-----------|---------|
| Zabbix    | 7.0.x   |
| PHP       | 8.1+    |
| Host      | `icmpping` item enabled |

### Local workflow

```bash
# 1. Clone your fork
git clone https://github.com/YOUR_USERNAME/host-uptime-sla-module.git
cd host-uptime-sla-module

# 2. Create a feature branch
git checkout -b feature/your-feature-name

# 3. Deploy to your Zabbix test server
rsync -avz --progress ./ user@zabbix-server:/var/www/html/zabbix/modules/host-uptime-sla-module/

# 4. Verify deploy integrity
chmod +x deploy_check.sh
./deploy_check.sh

# 5. Enable in Zabbix: Administration → Modules → Scan directory
```

---

## Project Structure

```
host-uptime-sla-module/
├── actions/
│   ├── HostUptimeSlaModule.php       # Main controller — data logic
│   └── HostUptimeSlaModulePdf.php    # PDF controller — same logic, layout.print
├── assets/
│   ├── igepn_logo.png
│   └── screenshots/
├── views/
│   ├── host.uptime.sla.module.php    # Main view — filter, table, stats
│   └── host.uptime.sla.pdf.php       # PDF view — clean HTML, auto-print
├── deploy_check.sh
├── manifest.json
└── Module.php
```

---

## Coding Standards

### PHP

- Follow **PSR-12** style
- Use **docblocks** on all classes and public methods
- Wrap all DB queries in **try/catch**
- Never concatenate raw user input into SQL — use `intval()` and `implode()`
- Use `CProfile` for filter persistence, not `$_SESSION`

```php
// ✅ Correct
$host_ids_str = implode(',', array_map('intval', array_keys($hosts)));

try {
    $res = DBselect("SELECT ... WHERE hostid IN ($host_ids_str)");
} catch (Exception $e) {
    $db_error = $e->getMessage();
}

// ❌ Incorrect
$res = DBselect("SELECT ... WHERE hostid IN (" . $_REQUEST['ids'] . ")");
```

### JavaScript

- Use `jQuery` (available in Zabbix layout) for DOM and events
- Use `Curl` (Zabbix native class) to build URLs
- Comment event listeners with `@listens` tag

### CSS

- Use `.ar-` prefix for all module classes to avoid conflicts with Zabbix native styles
- Keep styles in the `<style>` block at the bottom of the view file

---

## Submitting a Pull Request

1. Make sure `./deploy_check.sh` passes on your test server
2. Update the **Changelog** section in `README.md` and `README.es.md`
3. Bump the `@version` in the docblock of modified files
4. Write a clear PR description:
   - What changed and why
   - How to test it
   - Screenshots if UI changed

---

## Reporting Issues

Open an issue at:
https://github.com/rotoapanta/host-uptime-sla-module/issues

Include:
- Zabbix version
- PHP version
- Steps to reproduce
- Expected vs actual behavior
- Relevant error messages or screenshots

---

## Author

**Roberto Toapanta** · [@rotoapanta](https://github.com/rotoapanta)
IG-EPN – Instituto Geofísico · Escuela Politécnica Nacional

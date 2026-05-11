![License](https://img.shields.io/badge/license-MIT-blue.svg)
![Platform](https://img.shields.io/badge/platform-Zabbix%207.0-red.svg)
![PHP](https://img.shields.io/badge/PHP-8.1-777BB4?logo=php)
![Status](https://img.shields.io/badge/status-stable-brightgreen)
![Issues](https://img.shields.io/github/issues/rotoapanta/host-uptime-sla-module)
![Last Commit](https://img.shields.io/github/last-commit/rotoapanta/host-uptime-sla-module)
![Repo Size](https://img.shields.io/github/repo-size/rotoapanta/host-uptime-sla-module)

---

<p align="right"><a href="README.md">[EN]</a> | <strong>[ES]</strong></p>

# <p align="center">Host Uptime & SLA – Módulo para Zabbix 7</p>

## Descripción

**Host Uptime & SLA** es un módulo personalizado para Zabbix 7 que genera un reporte detallado de disponibilidad y cumplimiento de SLA para todos los hosts monitoreados.

El módulo consulta el ítem `icmpping` desde `history_uint` o `trends_uint` (seleccionado automáticamente según el período), calcula el porcentaje de uptime y el tiempo caído por host, y compara los resultados contra un objetivo de SLA configurable.

---

## Características

* Selector de tiempo nativo de Zabbix 7 (calendario, rangos rápidos, zoom)
* Filtro por grupos de hosts, hosts y objetivo de SLA
* Barra de disponibilidad con escala de colores (rojo / amarillo / verde)
* Badge de estado Online / Offline / Mantenimiento por host
* Tiempo caído formateado en años, meses, días, horas y minutos
* Estadísticas globales: total de hosts, online, offline, disponibilidad media, cumplen/incumplen SLA
* Reporte PDF exportable con identidad corporativa IG-EPN
* Integrado en el menú **Reports** de Zabbix
* Barra de debug configurable mediante el flag `$show_debug`
* Script de verificación de despliegue (`deploy_check.sh`)

---

## Requisitos

| Componente | Versión        |
|------------|----------------|
| Zabbix     | 7.0.x          |
| PHP        | 8.1+           |
| Hosts      | Ítem `icmpping` habilitado y monitoreado |

---

## Estructura del módulo

```
host-uptime-sla-module/
├── actions/
│   ├── HostUptimeSlaModule.php       ← Controlador principal
│   └── HostUptimeSlaModulePdf.php    ← Controlador PDF
├── assets/
│   └── igepn_logo.png                ← Logo corporativo
├── views/
│   ├── host.uptime.sla.module.php    ← Vista principal
│   └── host.uptime.sla.pdf.php       ← Vista PDF (layout.print)
├── deploy_check.sh                   ← Verificador de despliegue (MD5 + tamaño)
├── manifest.json                     ← Manifiesto del módulo
├── Module.php                        ← Registro en el menú
└── README.md
```

---

## Instalación

### 1. Copiar el módulo a Zabbix

```bash
# Crear directorio en el servidor
sudo mkdir -p /var/www/html/zabbix/modules/host-uptime-sla-module
sudo chown -R www-data:www-data /var/www/html/zabbix/modules/host-uptime-sla-module
sudo chmod -R 775 /var/www/html/zabbix/modules/host-uptime-sla-module

# Desplegar desde la máquina local
rsync -avz --progress \
  ~/ruta/al/host-uptime-sla-module/ \
  usuario@servidor:/var/www/html/zabbix/modules/host-uptime-sla-module/
```

### 2. Habilitar en Zabbix

Ir a **Administration → Modules**, hacer clic en **Scan directory** y luego habilitar `host-uptime-sla-module`.

El módulo aparecerá en **Reports → Host Uptime & SLA**.

---

## Verificación del despliegue

```bash
chmod +x deploy_check.sh
./deploy_check.sh
```

Salida esperada:

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

✔  Todos los archivos coinciden. Deploy OK.
```

---

## Uso

### Filtros disponibles

| Filtro          | Descripción                                    |
|-----------------|------------------------------------------------|
| Host groups     | Filtrar hosts por uno o más grupos             |
| Hosts           | Filtrar por hosts específicos                  |
| SLA mínimo      | Objetivo SLA: 99.9 / 99.5 / 99.0 / 98.0 / 95.0 % |
| From / To       | Rango de tiempo (selector nativo de Zabbix)    |

### Lógica de fuente de datos

| Período       | Fuente         |
|---------------|----------------|
| ≥ 1 día       | `trends_uint`  |
| < 1 día       | `history_uint` |

### Modo debug

En `views/host.uptime.sla.module.php`, configurar:

```php
$show_debug = true;   // muestra la barra de debug
$show_debug = false;  // modo producción (por defecto)
```

---

## Exportación PDF

Al hacer clic en **Download PDF** se abre el reporte en una nueva pestaña con:

* Logo IG-EPN y colores corporativos (`#8B4513`, `#C8860A`)
* Período, objetivo SLA, fuente de datos y fecha de generación
* Tarjetas de estadísticas del resumen
* Tabla completa con barras de disponibilidad y resultado SLA
* Diálogo de impresión automático al cargar
* Nombre de archivo sugerido: `Host_Uptime_SLA_YYYY-MM-DD_HH-mm.pdf`

---

## Capturas de pantalla

### Vista principal
![Vista principal](assets/screenshots/screenshot_main.png)

### Exportación PDF
![Exportación PDF](assets/screenshots/screenshot_pdf.png)

---

## Contribuciones

¡Las contribuciones son bienvenidas!

1. Realiza un fork del repositorio
2. Crea una rama (`feature/nueva-funcionalidad`)
3. Realiza los cambios
4. Envía un Pull Request

---

## Licencia

Este proyecto está bajo la licencia MIT.

---

## Autor

**Roberto Toapanta**
Ingeniero Eléctrico · IG-EPN
Monitoreo de redes | Zabbix | PHP | Bash

---

## Apoyo

Si este proyecto te resulta útil, considera darle una estrella ⭐

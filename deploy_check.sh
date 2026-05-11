#!/usr/bin/env bash
# =============================================================================
#  deploy_check.sh  –  Verifica que los archivos del módulo en el servidor
#                      coincidan exactamente con los locales (MD5 + tamaño).
# =============================================================================

REMOTE_USER="rtoapanta"
REMOTE_HOST="192.168.1.143"
REMOTE_BASE="/var/www/html/zabbix/modules/host-uptime-sla-module"
LOCAL_BASE="$(cd "$(dirname "$0")" && pwd)"

FILES=(
    "actions/HostUptimeSlaModule.php"
    "actions/HostUptimeSlaModulePdf.php"
    "views/host.uptime.sla.module.php"
    "views/host.uptime.sla.pdf.php"
    "Module.php"
    "manifest.json"
)

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
NC='\033[0m'

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║   Host Uptime & SLA Module – Deploy Verifier             ║${NC}"
echo -e "${BOLD}║   Local  : ${LOCAL_BASE}${NC}"
echo -e "${BOLD}║   Remote : ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_BASE}${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""

OK=0; FAIL=0; MISSING=0

for rel in "${FILES[@]}"; do
    local_path="${LOCAL_BASE}/${rel}"
    remote_path="${REMOTE_BASE}/${rel}"

    printf "%-55s " "${rel}"

    if [[ ! -f "$local_path" ]]; then
        echo -e "${YELLOW}[LOCAL MISSING]${NC}"; (( MISSING++ )); continue
    fi

    local_md5=$(md5sum "$local_path" | awk '{print $1}')
    local_size=$(wc -c < "$local_path")

    remote_info=$(ssh -o ConnectTimeout=5 -o BatchMode=yes \
        "${REMOTE_USER}@${REMOTE_HOST}" \
        "md5sum '${remote_path}' 2>/dev/null && wc -c < '${remote_path}' 2>/dev/null" \
        2>/dev/null)

    if [[ -z "$remote_info" ]]; then
        echo -e "${YELLOW}[REMOTE MISSING]${NC}"; (( MISSING++ )); continue
    fi

    remote_md5=$(echo "$remote_info" | awk 'NR==1{print $1}')
    remote_size=$(echo "$remote_info" | awk 'NR==2{print $1}')

    if [[ "$local_md5" == "$remote_md5" ]]; then
        echo -e "${GREEN}[OK]${NC}  md5=${local_md5:0:12}…  size=${local_size}B"
        (( OK++ ))
    else
        echo -e "${RED}[MISMATCH]${NC}"
        echo -e "    local  md5=${local_md5}  (${local_size}B)"
        echo -e "    remote md5=${remote_md5}  (${remote_size}B)"
        (( FAIL++ ))
    fi
done

echo ""
echo -e "${BOLD}─────────────────────────────────────────────────────────${NC}"
echo -e "  ${GREEN}OK${NC}      : ${OK}"
echo -e "  ${RED}MISMATCH${NC}: ${FAIL}"
echo -e "  ${YELLOW}MISSING${NC} : ${MISSING}"
echo -e "${BOLD}─────────────────────────────────────────────────────────${NC}"
echo ""

if [[ $FAIL -gt 0 || $MISSING -gt 0 ]]; then
    echo -e "${RED}✖  Hay diferencias. Ejecuta el rsync y corrige permisos:${NC}"
    echo ""
    echo "  sudo chown -R ${REMOTE_USER}:www-data ${REMOTE_BASE}/"
    echo "  sudo chmod -R 775 ${REMOTE_BASE}/"
    echo "  rsync -avz --delete --progress ./ ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_BASE}/"
    echo ""
    exit 1
else
    echo -e "${GREEN}✔  Todos los archivos coinciden. Deploy OK.${NC}"
    echo ""
    exit 0
fi
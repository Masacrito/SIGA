#!/usr/bin/env bash
# SIGA — despliega API + web + MySQL con Docker en un servidor Ubuntu (pensado para EC2).
#
# Estructura esperada (la misma que en C:\REPOS\cobach):
#   siga/
#     asistencias_back/
#     asistencias_front/
#     deploy/            <- este script, compose.yaml, Caddyfile
#
# En git vive en asistencias_back/deploy; el pipeline del back lo copia a <SIGA_DIR>/deploy.
#
# Uso:  bash deploy/deploy.sh   (en Azure lo corre el pipeline del back)
# Se puede correr las veces que quieras: la primera vez instala y configura,
# las siguientes solo reconstruye y aplica migraciones nuevas.
set -euo pipefail

cd "$(dirname "$0")"
DEPLOY_DIR="$(pwd)"

info() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m[!] %s\033[0m\n' "$*"; }
fail() { printf '\033[1;31m[x] %s\033[0m\n' "$*"; exit 1; }

[ -d ../asistencias_back ]  || fail "No encuentro ../asistencias_back junto a deploy/"
[ -d ../asistencias_front ] || fail "No encuentro ../asistencias_front junto a deploy/"

SUDO=""; [ "$(id -u)" -ne 0 ] && SUDO="sudo"

# ---------------------------------------------------------------- 1. Docker
if ! command -v docker >/dev/null 2>&1 || ! $SUDO docker compose version >/dev/null 2>&1; then
  info "Instalando Docker"
  $SUDO apt-get update -y
  if ! $SUDO apt-get install -y docker.io docker-compose-v2 docker-buildx; then
    warn "Paquetes de Ubuntu no disponibles; uso el instalador oficial de Docker"
    curl -fsSL https://get.docker.com | $SUDO sh
  fi
  $SUDO systemctl enable --now docker
  [ -n "$SUDO" ] && $SUDO usermod -aG docker "$USER" || true
fi

# ---------------------------------------------------------------- 2. Swap (2 GB de RAM se quedan cortos al compilar)
if [ "$(swapon --show --noheadings | wc -l)" -eq 0 ]; then
  info "Creando 2 GB de swap"
  $SUDO fallocate -l 2G /swapfile
  $SUDO chmod 600 /swapfile
  $SUDO mkswap /swapfile >/dev/null
  $SUDO swapon /swapfile
  grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' | $SUDO tee -a /etc/fstab >/dev/null
fi

# ---------------------------------------------------------------- 3. Variables (.env)
ip_publica() {
  local t
  t=$(curl -fs -m 3 -X PUT http://169.254.169.254/latest/api/token \
        -H 'X-aws-ec2-metadata-token-ttl-seconds: 60' 2>/dev/null || true)
  curl -fs -m 3 -H "X-aws-ec2-metadata-token: $t" \
       http://169.254.169.254/latest/meta-data/public-ipv4 2>/dev/null \
    || curl -fs -m 5 https://checkip.amazonaws.com | tr -d '[:space:]'
}

IP="$(ip_publica || true)"

if [ ! -f .env ]; then
  info "Generando deploy/.env (contraseñas aleatorias)"
  [ -n "$IP" ] || fail "No pude obtener la IP pública. Crea deploy/.env a mano a partir de .env.example"
  DOMINIO="${DOMINIO:-${IP//./-}.sslip.io}"
  rnd() { openssl rand -hex "$1"; }
  cat > .env <<ENV
# Generado por deploy.sh el $(date '+%Y-%m-%d %H:%M'). No lo subas a git.
SITE_ADDRESS=${DOMINIO}
APP_URL=https://${DOMINIO}
APP_KEY=base64:$(openssl rand -base64 32)

DB_DATABASE=cobach35_asistencias
DB_USERNAME=siga
DB_PASSWORD=$(rnd 16)
DB_ROOT_PASSWORD=$(rnd 16)

ADMIN_USERNAME=superadmin
ADMIN_PASSWORD=$(rnd 8)

# 1 = siembra los datos de prueba (DemoSeeder) para la revisión del avance
DEMO=${DEMO:-1}
DEMO_PASSWORD=$(rnd 6)
ENV
  chmod 600 .env
fi

set -a; . ./.env; set +a

if [ -n "$IP" ] && [[ "$SITE_ADDRESS" == *sslip.io ]] && [ "$SITE_ADDRESS" != "${IP//./-}.sslip.io" ]; then
  warn "La IP pública ($IP) ya no coincide con SITE_ADDRESS ($SITE_ADDRESS)."
  warn "Si asignaste una IP elástica, cambia SITE_ADDRESS y APP_URL en deploy/.env y vuelve a correr el script."
fi

DC="$SUDO docker compose"
artisan() { $DC exec -T --user www-data "$@" </dev/null; }

# ---------------------------------------------------------------- 4. Construir y levantar
info "Construyendo imágenes y levantando contenedores (la primera vez tarda varios minutos)"
$DC up -d --build --remove-orphans

info "Esperando a que la API responda"
for _ in $(seq 1 30); do
  artisan app php artisan --version >/dev/null 2>&1 && break
  sleep 2
done

# ---------------------------------------------------------------- 5. Base de datos
artisan app php artisan config:clear >/dev/null
info "Migraciones"
artisan app php artisan migrate --force

ROLES=$($DC exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N -s -e "SELECT COUNT(*) FROM roles" "$MYSQL_DATABASE" 2>/dev/null' </dev/null || echo 0)
if [ "${ROLES:-0}" = "0" ]; then
  info "Catálogo base y súper admin (primera vez)"
  artisan app php artisan db:seed --force
fi

if [ "${DEMO:-0}" = "1" ]; then
  info "Datos de prueba (DemoSeeder)"
  # El DemoSeeder solo corre en "local"; se fuerza solo para este comando
  artisan -e APP_ENV=local app php artisan db:seed --class=DemoSeeder --force
  # Los usuarios demo nacen con contraseña "password": en un servidor público se cambia
  artisan -e HOME=/tmp -e DEMO_PASSWORD="$DEMO_PASSWORD" app php artisan tinker --execute='App\Models\Usuario::where("es_super_admin", false)->get()->each(fn ($u) => $u->update(["password" => getenv("DEMO_PASSWORD")]));' >/dev/null
fi

# ---------------------------------------------------------------- 6. Optimizar
info "Cacheando configuración y rutas"
artisan app php artisan optimize >/dev/null

# ---------------------------------------------------------------- 7. Resumen
URL="https://${SITE_ADDRESS}"; [[ "$SITE_ADDRESS" == :* ]] && URL="http://${IP}"
$DC ps
cat <<RES

────────────────────────────────────────────────────────────
  SIGA desplegado
  Web:        ${URL}
  API:        ${URL}/api
  Salud:      ${URL}/up

RES
# En Azure Pipelines (TF_BUILD) no se imprimen contraseñas: quedarían en el log
if [ -z "${TF_BUILD:-}" ]; then
cat <<RES
  Súper admin:  ${ADMIN_USERNAME} / ${ADMIN_PASSWORD}
RES
if [ "${DEMO:-0}" = "1" ]; then
cat <<RES
  Usuarios demo (admin, orientador.matutino, orientador.vespertino y docentes)
  contraseña:   ${DEMO_PASSWORD}
RES
fi
fi
cat <<RES

  Credenciales guardadas en ${DEPLOY_DIR}/.env
  El certificado HTTPS puede tardar ~1 min la primera vez.
────────────────────────────────────────────────────────────
RES

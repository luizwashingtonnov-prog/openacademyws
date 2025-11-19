#!/usr/bin/env bash
#
# Deploy helper for the Sistema EAD project.
# Usage:
#   ./deploy.sh [environment]
# Example:
#   ./deploy.sh staging

set -euo pipefail

ENVIRONMENT="${1:-production}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_DIR="backups"
ARCHIVE_NAME="${BACKUP_DIR}/deploy_${ENVIRONMENT}_${TIMESTAMP}.tar.gz"

GREEN="\033[0;32m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
RESET="\033[0m"

info() {
    printf "%b[INFO]%b %s\n" "${GREEN}" "${RESET}" "$*"
}

warn() {
    printf "%b[WARN]%b %s\n" "${YELLOW}" "${RESET}" "$*"
}

error() {
    printf "%b[ERRO]%b %s\n" "${RED}" "${RESET}" "$*"
    exit 1
}

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        error "Comando obrigatório não encontrado: $1"
    fi
}

info "Ambiente alvo: ${ENVIRONMENT}"
info "Timestamp atual: ${TIMESTAMP}"

if [[ ! -f "config.php" ]]; then
    error "Execute este script na raiz do projeto (config.php não encontrado)."
fi

require_command tar
require_command php

info "Gerando backup mínimo em ${ARCHIVE_NAME}"
mkdir -p "${BACKUP_DIR}"
if ! tar -czf "${ARCHIVE_NAME}" \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='backups' \
    --exclude='public/uploads' \
    .; then
    warn "Backup completo não pôde ser criado; verifique permissões/armazenamento."
else
    info "Backup pronto em ${ARCHIVE_NAME}"
fi

if [[ ! -f ".env" ]]; then
    warn "Arquivo .env não encontrado."
    if [[ -f ".env.example" ]]; then
        cp ".env.example" ".env"
        warn ".env.example copiado para .env. Ajuste as variáveis e execute novamente."
        exit 1
    else
        error "Arquivo .env.example também não existe. Impossível prosseguir."
    fi
fi

info "Verificando extensões PHP essenciais"
if ! php -m | grep -qiE 'pdo_mysql|pdo_pgsql'; then
    warn "Extensão PDO para MySQL/PostgreSQL não localizada."
fi

if ! php -m | grep -qiE 'gd|imagick'; then
    warn "Extensão para manipulação de imagens (gd ou imagick) não localizada."
fi

info "Validando conexão com banco conforme config.php"
if ! php -r "
require 'config.php';
try {
    \$db = get_db();
    echo 'Conexão com banco estabelecida.' . PHP_EOL;
} catch (Throwable \$e) {
    echo 'Falha na conexão: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"; then
    error "Configuração do banco inválida. Ajuste o .env e tente novamente."
fi

info "Garantindo estrutura das pastas de upload"
mkdir -p public/uploads/pdfs public/uploads/videos public/uploads/signatures storage || \
    warn "Não foi possível criar todas as pastas de upload/storage."

info "Ajustando permissões das pastas sensíveis (se suportado pelo SO)"
chmod -R 755 public/uploads 2>/dev/null || warn "Não foi possível ajustar permissões de public/uploads"
chmod -R 755 storage 2>/dev/null || warn "Não foi possível ajustar permissões de storage"

info "Limpando arquivos de cache temporários"
find . -type f -name '*.cache' -delete 2>/dev/null || true

cat <<'EOF'

Checklist rápido:
  1. Reveja o arquivo .env (credenciais de BD e APP_BASE_URL).
  2. Importe/atualize o banco com database/schema.sql.
  3. Publique public/ no diretório público do servidor.
  4. Teste login, uploads e emissão de certificados.

Deploy automatizado finalizado com sucesso.
EOF

#!/usr/bin/env bash
# Publica o commit ATUAL direto do computador do mantenedor, sem GitHub Actions nem Secrets.
# Usa o mesmo bin/deploy.sh do CI (release imutável, troca atômica, health check e rollback).
#
#   bash bin/deploy-local.sh
#
# Configuração FORA do repositório (nenhum segredo vai para o Git):
#   ~/.ssh/radar_deploy          chave privada de deploy (ed25519)
#   ~/.ssh/known_hosts_radar     linha de identidade do servidor, conferida
#   ~/.ssh/radar-deploy.env      DEPLOY_HOST, DEPLOY_USER, DEPLOY_PATH (e DEPLOY_PORT, opcional)
# Variáveis: RADAR_SKIP_TESTS=1 pula os testes; RADAR_DEPLOY_ENV aponta outro arquivo .env.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

KEY=${RADAR_DEPLOY_KEY:-$HOME/.ssh/radar_deploy}
KNOWN=${RADAR_DEPLOY_KNOWN_HOSTS_FILE:-$HOME/.ssh/known_hosts_radar}
ENV_FILE=${RADAR_DEPLOY_ENV:-$HOME/.ssh/radar-deploy.env}
fail() { printf '\nERRO: %s\n' "$1" >&2; exit 1; }
for file in "$KEY" "$KNOWN" "$ENV_FILE"; do [[ -s $file ]] || fail "Falta o arquivo $file (veja o cabeçalho deste script)."; done

# shellcheck disable=SC1090
set -a; source "$ENV_FILE"; set +a
for name in DEPLOY_HOST DEPLOY_USER DEPLOY_PATH; do [[ -n ${!name:-} ]] || fail "$name não definido em $ENV_FILE."; done

# O pacote sai da árvore de trabalho: só publica o que está commitado.
[[ -z $(git status --porcelain) ]] || fail 'Há alterações não commitadas. Faça o commit antes de publicar.'
COMMIT=$(git rev-parse HEAD)
git branch -r --contains "$COMMIT" 2>/dev/null | grep -q . || printf 'AVISO: o commit %s ainda não foi enviado ao GitHub.\n' "${COMMIT:0:7}" >&2

if [[ ${RADAR_SKIP_TESTS:-0} != 1 ]]; then
  printf '\n== Testes ==\n'
  php tests/run.php | tail -3
  php tests/run.php >/dev/null || fail 'Os testes falharam: nada foi publicado.'
fi

export DEPLOY_SSH_KEY DEPLOY_KNOWN_HOSTS RELEASE_ID
DEPLOY_SSH_KEY=$(cat "$KEY")
DEPLOY_KNOWN_HOSTS=$(cat "$KNOWN")
RELEASE_ID="$COMMIT-$(date +%s)-1"
printf '\n== Publicando %s em %s ==\n' "${COMMIT:0:7}" "$DEPLOY_HOST"
bash bin/deploy.sh

printf '\n== Conferência pública ==\n'
curl -s -m 20 -w '\nHTTP %{http_code}\n' https://viral.netnewsltda.online/health.php

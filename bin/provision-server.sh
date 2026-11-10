#!/usr/bin/env bash
# Prepara o servidor para o Radar Viral BR em UM comando. Rode como o USUÁRIO DO SITE
# (o mesmo do CloudPanel), num terminal de verdade; nunca como root.
#
#   bash bin/provision-server.sh "ssh-ed25519 AAAA… radar-deploy"
#
# O argumento é a chave PÚBLICA de deploy (opcional). Idempotente: pode rodar de novo.
# O script não instala nada, não usa sudo e não altera o CloudPanel nem o Nginx.
set -euo pipefail
umask 077

say() { printf '\n\033[1m%s\033[0m\n' "$1"; }
fail() { printf '\n\033[31mERRO:\033[0m %s\n' "$1" >&2; exit 1; }

[[ $(id -u) -ne 0 ]] || fail 'Não rode como root. Entre como o usuário do site (veja o nome no CloudPanel).'
REPO=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
[[ -f $REPO/bin/setup.php ]] || fail 'Rode este script de dentro de uma cópia do repositório (bin/setup.php não encontrado).'
# O Root Directory do CloudPanel é relativo a htdocs: por isso o padrão fica dentro dele.
BASE="${RADAR_BASE:-$HOME/htdocs/radar-viral-br}"
DEPLOY_PUBKEY=${1:-}

say '1/5  PHP e extensões'
PHP=''
for candidate in php8.4 php8.3 php8.2 php; do
  if command -v "$candidate" >/dev/null 2>&1 && "$candidate" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' 2>/dev/null; then
    PHP=$(command -v "$candidate")
    break
  fi
done
[[ -n $PHP ]] || fail 'Nenhum PHP 8.2+ encontrado para este usuário. No CloudPanel, confira a versão do PHP do site.'
missing=$("$PHP" -r '
  $need = ["curl", "simplexml", "fileinfo", "mbstring", "pdo_sqlite", "libxml", "json"];
  $missing = array_values(array_filter($need, static fn(string $e): bool => !extension_loaded($e)));
  if (!extension_loaded("sodium") && !extension_loaded("openssl")) { $missing[] = "sodium ou openssl"; }
  echo implode(", ", $missing);
')
[[ -z $missing ]] || fail "Faltam extensões do PHP ($PHP): $missing. Ative em CloudPanel > Site > PHP Settings e rode de novo."
for tool in git tar flock curl; do
  command -v "$tool" >/dev/null 2>&1 || fail "Falta o comando '$tool' no servidor."
done
echo "ok: $("$PHP" -r 'echo PHP_VERSION;') em $PHP"

say '2/5  Pastas privadas'
mkdir -p "$BASE/shared/storage"
chmod 700 "$BASE/shared" "$BASE/shared/storage"
echo "ok: $BASE/shared/storage (0700)"

say '3/5  Acesso do painel (sua senha, digitada só aqui)'
if [[ -s $BASE/shared/config.php ]]; then
  echo 'config.php já existe: mantido. (Para trocar a senha: php bin/setup.php --force --config=…)'
else
  [[ -t 0 ]] || fail 'Este passo pede a senha do painel: rode num terminal interativo.'
  "$PHP" "$REPO/bin/setup.php" --config="$BASE/shared/config.php"
fi
chmod 600 "$BASE/shared/config.php"

say '4/5  Chave de deploy e agendador'
if [[ -n $DEPLOY_PUBKEY ]]; then
  [[ $DEPLOY_PUBKEY =~ ^ssh-ed25519\ [A-Za-z0-9+/=]+(\ [A-Za-z0-9._@-]+)?$ ]] || fail 'A chave pública informada não parece uma chave ssh-ed25519 válida (uma linha só).'
  mkdir -p "$HOME/.ssh" && chmod 700 "$HOME/.ssh"
  touch "$HOME/.ssh/authorized_keys" && chmod 600 "$HOME/.ssh/authorized_keys"
  key_body=$(printf '%s' "$DEPLOY_PUBKEY" | awk '{print $2}')
  if grep -qF "$key_body" "$HOME/.ssh/authorized_keys"; then
    echo 'chave de deploy: já autorizada.'
  else
    # Sem encaminhamentos de agente, de porta nem de X11: a chave serve só para o deploy.
    printf 'no-agent-forwarding,no-port-forwarding,no-X11-forwarding %s\n' "$DEPLOY_PUBKEY" >> "$HOME/.ssh/authorized_keys"
    echo 'chave de deploy: autorizada (sem encaminhamentos).'
  fi
else
  echo 'nenhuma chave pública informada: pulei a autorização da chave de deploy.'
fi
CRON_LINE="*/30 * * * * /usr/bin/flock -n $BASE/.feeds.lock $PHP $BASE/current/bin/update.php >> $BASE/shared/storage/cron.log 2>&1"
if command -v crontab >/dev/null 2>&1; then
  if crontab -l 2>/dev/null | grep -qF 'radar-viral-br/current/bin/update.php'; then
    echo 'cron: já existe.'
  else
    (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab - && echo 'cron: coleta a cada 30 minutos adicionada (só funciona depois do primeiro deploy).'
  fi
else
  echo "cron: 'crontab' indisponível. Cadastre no CloudPanel: $CRON_LINE"
fi

say '5/5  Impressão digital do servidor (para conferir a identidade no deploy)'
host_pub=''
for type in ed25519 ecdsa rsa; do
  if [[ -r /etc/ssh/ssh_host_${type}_key.pub ]]; then host_pub=/etc/ssh/ssh_host_${type}_key.pub; break; fi
done
fingerprint='indisponível (leia /etc/ssh/ssh_host_*_key.pub pelo console do provedor)'
host_line=''
if [[ -n $host_pub ]]; then
  fingerprint=$(ssh-keygen -lf "$host_pub" 2>/dev/null | awk '{print $2" ("$NF")"}')
  host_line=$(awk '{print $1" "$2}' "$host_pub")
fi

if [[ $BASE == "$HOME/htdocs/"* ]]; then
  ROOT_REL="${BASE#"$HOME/htdocs/"}/current/public"
else
  ROOT_REL='(use um caminho dentro de htdocs para o CloudPanel aceitar)'
fi

cat <<RESUMO

==================== COPIE ESTE BLOCO E ENVIE AO CLAUDE ====================
(nada aqui é segredo: sem senhas e sem chaves privadas)
usuario=$(id -un)
caminho=$BASE
php=$PHP
impressao_digital=$fingerprint
linha_host=$host_line
============================================================================

Falta só uma coisa no CloudPanel (Sites > seu site > Settings):
  Root Directory = $ROOT_REL
Até o primeiro deploy, essa página fica fora do ar: é o esperado.
RESUMO

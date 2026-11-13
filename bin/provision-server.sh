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

say '1/6  PHP e extensões'
PHP=''
# Prefere as versões que o CI testa (8.3, 8.2); 8.4 só se não houver outra.
for candidate in php8.3 php8.2 php8.4 php; do
  if command -v "$candidate" >/dev/null 2>&1 && "$candidate" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' 2>/dev/null; then
    PHP=$(command -v "$candidate")
    break
  fi
done
[[ -n $PHP ]] || fail 'Nenhum PHP 8.2+ encontrado para este usuário. No CloudPanel, confira a versão do PHP do site.'
# 2>/dev/null: servidores com módulos PHP ausentes imprimem avisos de inicialização.
missing=$("$PHP" 2>/dev/null -r '
  $need = ["curl", "simplexml", "fileinfo", "mbstring", "pdo_sqlite", "libxml", "json"];
  $missing = array_values(array_filter($need, static fn(string $e): bool => !extension_loaded($e)));
  if (!extension_loaded("sodium") && !extension_loaded("openssl")) { $missing[] = "sodium ou openssl"; }
  echo implode(", ", $missing);
')
[[ -z $missing ]] || fail "Faltam extensões do PHP ($PHP): $missing. Ative em CloudPanel > Site > PHP Settings e rode de novo."
for tool in git tar flock curl; do
  command -v "$tool" >/dev/null 2>&1 || fail "Falta o comando '$tool' no servidor."
done
echo "ok: $("$PHP" -r 'echo PHP_VERSION;' 2>/dev/null) em $PHP"

say '2/6  Pastas privadas'
mkdir -p "$BASE/shared/storage"
chmod 700 "$BASE/shared" "$BASE/shared/storage"
echo "ok: $BASE/shared/storage (0700)"

say '3/6  Acesso do painel'
if [[ -s $BASE/shared/config.php ]]; then
  echo 'config.php já existe: mantido. (Para trocar a senha: php bin/setup.php --force --config=…)'
elif [[ ${RADAR_PASSWORD_STDIN:-0} == 1 ]]; then
  # Modo sem terminal: a senha chega por stdin (uma linha). O token de leitura do setup não é exibido.
  "$PHP" "$REPO/bin/setup.php" --config="$BASE/shared/config.php" --password-stdin | grep -Ev '^[a-f0-9]{64}$' || true
  [[ -s $BASE/shared/config.php ]] || fail 'O setup não criou o config.php (senha com menos de 14 caracteres?).'
  echo 'config.php criado. O token de leitura inicial não foi exibido: crie tokens novos em Integrações.'
else
  [[ -t 0 ]] || fail 'Este passo pede a senha do painel: rode num terminal interativo (ssh -t) ou use RADAR_PASSWORD_STDIN=1.'
  "$PHP" "$REPO/bin/setup.php" --config="$BASE/shared/config.php"
fi
chmod 600 "$BASE/shared/config.php"

say '4/6  Chave de deploy e agendador'
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

say '5/6  Domínio apontando para o Radar (sem mexer no CloudPanel)'
# O CloudPanel serve htdocs/<domínio>. Um link simbólico para current/public dispensa trocar o Root Directory.
DOMAIN=${RADAR_DOMAIN:-viral.netnewsltda.online}
SITE_ROOT="$HOME/htdocs/$DOMAIN"
TARGET="$BASE/current/public"
if [[ -L $SITE_ROOT ]]; then
  echo "ok: $SITE_ROOT já é um link para $(readlink "$SITE_ROOT")"
elif [[ -d $SITE_ROOT && $BASE == "$HOME/htdocs/"* ]]; then
  if [[ -e $SITE_ROOT.original ]]; then
    echo "pulei: $SITE_ROOT.original já existe (apontamento manual: Root Directory = ${BASE#"$HOME/htdocs/"}/current/public)"
  else
    # Cria o link com outro nome ANTES de mexer na pasta: se algo falhar, nada foi movido.
    if ln -s "$TARGET" "$SITE_ROOT.novo" 2>/dev/null; then
      mv "$SITE_ROOT" "$SITE_ROOT.original"
      if mv -T "$SITE_ROOT.novo" "$SITE_ROOT"; then
        echo "ok: página antiga guardada em $SITE_ROOT.original; $SITE_ROOT -> $TARGET"
      else
        mv "$SITE_ROOT.original" "$SITE_ROOT"; rm -f "$SITE_ROOT.novo"
        echo "pulei: não consegui trocar a pasta; mantive a original (Root Directory = ${BASE#"$HOME/htdocs/"}/current/public)"
      fi
    else
      echo "pulei: não foi possível criar o link simbólico; mantive a pasta original (Root Directory = ${BASE#"$HOME/htdocs/"}/current/public)"
    fi
  fi
else
  echo "pulei: $SITE_ROOT não encontrado. No CloudPanel, Root Directory = ${BASE#"$HOME/htdocs/"}/current/public"
fi

say '6/6  Impressão digital do servidor (para conferir a identidade no deploy)'
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

Se o apontamento automático foi pulado, no CloudPanel (Settings) use Root Directory = $ROOT_REL
Até o primeiro deploy, o site fica fora do ar: é o esperado.
RESUMO

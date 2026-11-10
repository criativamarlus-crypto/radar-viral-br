#!/usr/bin/env bash
# Invoked by the protected deployment job; never stores production secrets in Git.
set -euo pipefail
umask 077

fail() { printf '%s\n' "$1" >&2; exit 1; }
for name in DEPLOY_HOST DEPLOY_USER DEPLOY_PATH DEPLOY_SSH_KEY DEPLOY_KNOWN_HOSTS RELEASE_ID; do
  [[ -n ${!name:-} ]] || fail "Missing required deployment setting: $name"
done
DEPLOY_PORT=${DEPLOY_PORT:-22}
[[ $DEPLOY_PORT =~ ^[0-9]{1,5}$ ]] && (( 10#$DEPLOY_PORT >= 1 && 10#$DEPLOY_PORT <= 65535 )) || fail 'SSH port must be between 1 and 65535.'
[[ $DEPLOY_HOST =~ ^[A-Za-z0-9][A-Za-z0-9.-]*$ ]] || fail 'Invalid SSH hostname.'
[[ $DEPLOY_USER =~ ^[a-z_][a-z0-9_-]*$ ]] || fail 'Invalid SSH user.'
[[ $DEPLOY_USER != root ]] || fail 'Use a dedicated non-root deployment user.'
[[ $DEPLOY_PATH =~ ^/[A-Za-z0-9_./-]+$ && $DEPLOY_PATH != / && $DEPLOY_PATH != */ ]] || fail 'Deployment path must be an absolute site directory without spaces or trailing slash.'
[[ /$DEPLOY_PATH/ != */../* && /$DEPLOY_PATH/ != */./* ]] || fail 'Deployment path may not contain dot segments.'
[[ $RELEASE_ID =~ ^[a-f0-9]{40}-[0-9]+-[0-9]+$ ]] || fail 'Invalid immutable release identifier.'
for source in app public bin config.example.php; do
  [[ -e $source ]] || fail "Missing release source: $source"
done
[[ -z $(find app public bin -type l -print -quit) ]] || fail 'Release source must not contain symlinks.'

scratch=$(mktemp -d)
trap 'rm -rf -- "$scratch"' EXIT
printf '%s\n' "$DEPLOY_SSH_KEY" > "$scratch/key"
printf '%s\n' "$DEPLOY_KNOWN_HOSTS" > "$scratch/known_hosts"
unset DEPLOY_SSH_KEY DEPLOY_KNOWN_HOSTS
chmod 600 "$scratch/key" "$scratch/known_hosts"
ssh_options=(-i "$scratch/key" -o Port="$DEPLOY_PORT" -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile="$scratch/known_hosts" -o GlobalKnownHostsFile=/dev/null -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=2)
remote="$DEPLOY_USER@$DEPLOY_HOST"

# Never bundle runtime configuration, cache, uploads, tests or Git metadata.
tar --exclude='public/uploads' --exclude='*.log' --exclude='config.php' --exclude='.env' -czf "$scratch/release.tar.gz" app public bin config.example.php

ssh "${ssh_options[@]}" "$remote" bash -s -- "$DEPLOY_PATH" <<'PREFLIGHT'
set -euo pipefail
umask 077
base=$1
[[ -d $base && ! -L $base ]] || { echo 'Provision the deployment base directory first.' >&2; exit 1; }
[[ -s $base/shared/config.php && -d $base/shared/storage && -w $base/shared/storage ]] || { echo 'Provision private shared/config.php and writable shared/storage before deployment.' >&2; exit 1; }
[[ ! -e $base/current || -L $base/current ]] || { echo 'The current path must be a release symlink, never an existing document root.' >&2; exit 1; }
for command in php curl tar flock; do command -v "$command" >/dev/null || { echo "Missing server command: $command" >&2; exit 1; }; done
php -r 'exit(PHP_VERSION_ID >= 80200 && extension_loaded("curl") && extension_loaded("simplexml") && extension_loaded("fileinfo") && extension_loaded("pdo_sqlite") && extension_loaded("mbstring") && (extension_loaded("sodium") || extension_loaded("openssl")) ? 0 : 1);'
php -r '
  $c = require $argv[1];
  $storage = is_array($c) ? ($c["storage_path"] ?? null) : null;
  $valid = is_array($c) && ($c["secure_cookies"] ?? null) === true
    && ($c["site_url"] ?? null) === "https://viral.netnewsltda.online"
    && is_string($c["admin_user"] ?? null) && $c["admin_user"] !== ""
    && is_string($c["admin_password_hash"] ?? null)
    && (password_get_info($c["admin_password_hash"])["algoName"] ?? "unknown") !== "unknown"
    && is_string($storage) && is_dir($storage) && !is_link($storage) && is_writable($storage)
    && realpath($storage) === realpath($argv[2])
    && (fileperms($storage) & 0077) === 0 && (fileperms($argv[1]) & 0077) === 0;
  if (!$valid) { fwrite(STDERR, "Private production configuration is invalid; deploy refused.\n"); exit(1); }
' "$base/shared/config.php" "$base/shared/storage"
mkdir -p "$base/releases" "$base/incoming"
# Nginx must traverse releases and read public assets; private shared data stays 0700.
chmod 0711 "$base" "$base/releases"
chmod 0700 "$base/incoming"
PREFLIGHT

scp "${ssh_options[@]}" "$scratch/release.tar.gz" "$remote:$DEPLOY_PATH/incoming/$RELEASE_ID.tar.gz"
ssh "${ssh_options[@]}" "$remote" bash -s -- "$DEPLOY_PATH" "$RELEASE_ID" <<'ACTIVATE'
set -euo pipefail
umask 077
base=$1
release=$2
archive="$base/incoming/$release.tar.gz"
stage="$base/releases/.staging-$release"
target="$base/releases/$release"
next="$base/.current-$release"
health=$(mktemp)
previous=''
switched=false
complete=false
cleanup() {
  exit_status=$?
  if [[ $switched == true && $complete != true ]]; then
    rm -f -- "$next"
    if [[ -n $previous ]]; then
      ln -s "$previous" "$next" && mv -Tf "$next" "$base/current"
    else
      rm -f -- "$base/current"
    fi
    echo 'Deployment interrupted or health check failed. Previous current symlink restored; inspect server logs.' >&2
  fi
  rm -f -- "$archive" "$next" "$health"
  if [[ -d $stage ]]; then rm -rf -- "$stage"; fi
  exit "$exit_status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
trap 'exit 129' HUP
exec 9>"$base/.deploy.lock"
flock -w 30 9
[[ ! -e $target && ! -e $stage ]] || { echo 'This release already exists.' >&2; exit 1; }
mkdir "$stage"
tar --extract --gzip --file="$archive" --directory="$stage" --no-same-owner --no-same-permissions
chmod 0711 "$stage"
find "$stage/public" -type d -exec chmod 0755 {} +
find "$stage/public" -type f -exec chmod 0644 {} +
ln -s "$base/shared/config.php" "$stage/config.php"
ln -s "$base/shared/storage" "$stage/storage"
find "$stage/app" "$stage/public" "$stage/bin" -type f -name '*.php' -print0 | xargs -0 -r -n1 php -l >/dev/null
php -r '
  $c = require $argv[1];
  $storage = is_array($c) ? ($c["storage_path"] ?? null) : null;
  $valid = is_array($c) && ($c["secure_cookies"] ?? null) === true
    && ($c["site_url"] ?? null) === "https://viral.netnewsltda.online"
    && is_string($c["admin_user"] ?? null) && $c["admin_user"] !== ""
    && is_string($c["admin_password_hash"] ?? null)
    && (password_get_info($c["admin_password_hash"])["algoName"] ?? "unknown") !== "unknown"
    && is_string($storage) && is_dir($storage) && !is_link($storage) && is_writable($storage)
    && realpath($storage) === realpath($argv[2])
    && (fileperms($storage) & 0077) === 0 && (fileperms($argv[1]) & 0077) === 0;
  if (!$valid) { fwrite(STDERR, "Private production configuration is invalid; release refused.\n"); exit(1); }
' "$stage/config.php" "$base/shared/storage"
if [[ -L $base/current ]]; then
  previous=$(readlink "$base/current")
  case "$previous" in "$base/releases/"*|releases/*) ;; *) echo 'Unexpected current release target; refusing replacement.' >&2; exit 1 ;; esac
fi
mv "$stage" "$target"
ln -s "$target" "$next"
mv -Tf "$next" "$base/current"
switched=true
check_health() {
  curl --fail --silent --show-error --connect-timeout 5 --max-time 20 "$@" --output "$health" || return 1
  php -r '$v=json_decode(file_get_contents($argv[1]),true); exit(is_array($v) && ($v["status"] ?? null) === "ok" ? 0 : 1);' "$health"
}
# Loopback only: the origin certificate may be self-signed (CloudPanel default, Cloudflare terminates public TLS).
# The public check below still validates the certificate presented to visitors.
if ! check_health --insecure --resolve viral.netnewsltda.online:443:127.0.0.1 https://viral.netnewsltda.online/health.php || ! check_health https://viral.netnewsltda.online/health.php; then
  exit 1
fi
complete=true
echo 'Release activated; origin and public HTTPS health checks passed.'
ACTIVATE

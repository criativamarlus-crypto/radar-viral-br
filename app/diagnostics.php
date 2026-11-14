<?php
declare(strict_types=1);

/** Environment checks shown in the panel (never public) and used by /health.php. */

/** @return array<int,string> missing PHP extensions required to run */
function radar_missing_extensions(): array
{
    $missing = [];
    foreach (['curl', 'simplexml', 'fileinfo', 'pdo_sqlite', 'mbstring', 'json', 'libxml'] as $extension) {
        if (!extension_loaded($extension)) {
            $missing[] = $extension;
        }
    }
    if (!extension_loaded('sodium') && !extension_loaded('openssl')) {
        $missing[] = 'sodium ou openssl';
    }
    return $missing;
}

/** @return array<int,array{label:string,ok:?bool,detail:string}> */
function radar_diagnostics(): array
{
    $checks = [];
    $add = static function (string $label, ?bool $ok, string $detail) use (&$checks): void {
        $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    };
    $add('Versão do PHP', PHP_VERSION_ID >= 80200, PHP_VERSION . (PHP_VERSION_ID >= 80200 ? '' : ' — exige 8.2 ou superior'));
    $missing = radar_missing_extensions();
    $add('Extensões do PHP', $missing === [], $missing === [] ? 'curl, simplexml, fileinfo, pdo_sqlite, mbstring, sodium/openssl' : 'faltando: ' . implode(', ', $missing));
    $config = radar_config();
    $storage = (string)$config['storage_path'];
    $writable = is_dir($storage) && is_writable($storage);
    $add('Armazenamento privado', $writable, $writable ? 'gravável e fora da pasta pública' : 'não gravável');
    $perms = $writable ? ((int)fileperms($storage) & 0077) === 0 : false;
    $add('Permissões do storage', $writable ? $perms : null, $perms ? 'somente o usuário do site (0700)' : 'revise: outros usuários não deveriam ler o storage');
    try {
        $add('Banco de dados', true, 'SQLite, esquema versão ' . radar_db_schema_version() . ', ' . number_format((float)@filesize(radar_db_path()) / 1048576, 1, ',', '.') . ' MB');
    } catch (Throwable $error) {
        $add('Banco de dados', false, 'indisponível: ' . $error->getMessage());
    }
    $siteUrl = (string)($config['site_url'] ?? '');
    $add('Endereço do site', str_starts_with($siteUrl, 'https://'), $siteUrl !== '' ? $siteUrl : 'não configurado');
    $add('Cookies seguros', ($config['secure_cookies'] ?? true) !== false, ($config['secure_cookies'] ?? true) !== false ? 'ativados (HTTPS)' : 'desativados (somente para uso local)');
    $lastCron = radar_db_value("SELECT MAX(started_at) FROM collection_runs WHERE trigger_kind = 'cron'");
    if ($lastCron === null) {
        $add('Agendador (cron)', null, 'nenhuma coleta pelo cron ainda. Veja docs/CLOUDPANEL.md para configurar a cada 30 minutos.');
    } else {
        $age = (int)round((time() - (int)$lastCron) / 60);
        $add('Agendador (cron)', $age <= 90, 'última coleta pelo cron há ' . radar_age_label($age) . ($age > 90 ? ' — está parado?' : ''));
    }
    $ai = radar_ai_config();
    $add('IA de roteiros', $ai['provider'] !== 'none' && $ai['has_key'] ? true : null, $ai['provider'] === 'none' ? 'não configurada (modo rascunho)' : ($ai['has_key'] ? RADAR_AI_PROVIDERS[$ai['provider']] . ' · ' . $ai['model'] : 'provedor escolhido, falta a chave'));
    return $checks;
}

#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Scheduled collection (cron, every 30 minutes). Exit codes:
 *   0  collected (even if some sources failed) or another run already holds the lock
 *   1  every source failed, or the application could not start
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    radar_cleanup_private_storage();
    $started = microtime(true);
    $result = radar_collect('cron', 15);
    printf(
        "%s status=%s sources_ok=%d sources_failed=%d items_seen=%d items_new=%d seconds=%.1f\n",
        date(DATE_ATOM), $result['status'], $result['sources_ok'] ?? 0, $result['sources_failed'] ?? 0,
        $result['items_seen'] ?? 0, $result['items_new'] ?? 0, microtime(true) - $started
    );
    if (($result['sources_failed'] ?? 0) > 0) {
        foreach (radar_db_all("SELECT source_name, error FROM collection_run_sources WHERE run_id = ? AND status = 'error'", [$result['run_id']]) as $failure) {
            printf("  falha: %s — %s\n", $failure['source_name'], $failure['error']);
        }
    }
    exit($result['status'] === 'error' ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, date(DATE_ATOM) . ' falha: ' . get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}

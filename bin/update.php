<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/feeds.php';

radar_cleanup_private_storage();

$failed = false;
foreach (array_keys(radar_categories()) as $category) {
    $result = radar_fetch($category);
    $failed = $failed || $result['error'] !== null || ($result['stale'] ?? false);
    printf("%s category=%s items=%d status=%s\n", date(DATE_ATOM), $category, count($result['items']), $result['error'] !== null ? 'unavailable' : (($result['stale'] ?? false) ? 'stale' : 'ok'));
}
$trends = radar_trends();
$failed = $failed || $trends['error'] !== null || ($trends['stale'] ?? false);
printf("%s source=trends items=%d status=%s\n", date(DATE_ATOM), count($trends['items']), $trends['error'] !== null ? 'unavailable' : (($trends['stale'] ?? false) ? 'stale' : 'ok'));
exit($failed ? 1 : 0);

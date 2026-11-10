<?php
declare(strict_types=1);

/** Offline regression suite. Never reads production configuration or contacts feeds. */
$testDirectory = sys_get_temp_dir() . '/radar-tests-' . bin2hex(random_bytes(8));
mkdir($testDirectory, 0700, true);
function radar_config(): array
{
    global $testDirectory;
    return ['storage_path' => $testDirectory . '/storage', 'feed_ttl' => 300];
}
require_once dirname(__DIR__) . '/app/feeds.php';
require_once dirname(__DIR__) . '/app/scripts.php';
require_once dirname(__DIR__) . '/app/media.php';

$passed = 0;
$failed = 0;
function check(string $description, callable $assertion): void
{
    global $passed, $failed;
    try {
        if ($assertion() !== true) {
            throw new RuntimeException('Assertion did not return true');
        }
        $passed++;
        echo "PASS $description\n";
    } catch (Throwable $error) {
        $failed++;
        echo "FAIL $description: " . $error->getMessage() . "\n";
    }
}
function rejects(callable $operation, string $exception = Throwable::class): bool
{
    try {
        $operation();
    } catch (Throwable $error) {
        return $error instanceof $exception;
    }
    return false;
}
function rss(string $items, string $namespaces = ''): string
{
    return '<?xml version="1.0"?><rss version="2.0" ' . $namespaces . '><channel><title>Fixture</title>' . $items . '</channel></rss>';
}
function fixtureItem(string $title, string $url, string $extra = ''): string
{
    return '<item><title>' . htmlspecialchars($title, ENT_XML1, 'UTF-8') . '</title><link>' . htmlspecialchars($url, ENT_XML1, 'UTF-8') . '</link>' . $extra . '</item>';
}
function eraseTestDirectory(string $directory): void
{
    if (is_link($directory) || !is_dir($directory)) {
        @unlink($directory);
        return;
    }
    foreach (new FilesystemIterator($directory) as $entry) {
        eraseTestDirectory($entry->getPathname());
    }
    rmdir($directory);
}
register_shutdown_function(static fn() => eraseTestDirectory($GLOBALS['testDirectory']));

check('all original news categories remain available', static fn(): bool => array_keys(radar_categories()) === ['Geral', 'Brasil', 'Futebol', 'Política', 'Famosos', 'Economia', 'Mundo', 'Tecnologia']);
check('public HTTPS article URLs accepted', static fn(): bool => radar_feed_safe_url('https://example.com/news?id=1&lang=pt') === 'https://example.com/news?id=1&lang=pt');
foreach (['javascript:alert(1)', 'http://example.com/a', '//example.com/a', 'https://user:pass@example.com/', 'https://127.0.0.1/', 'https://[::1]/', 'https://localhost/', 'https://app.internal/', 'https://site.local/', 'https://example.com:8443/', 'https://example.com\\@evil.com/', "https://example.com/\nheader"] as $url) {
    check('reject unsafe URL ' . json_encode($url), static fn(): bool => radar_feed_safe_url($url) === null);
}
check('server fetch rejects arbitrary public and private URLs before networking', static fn(): bool => rejects(static fn() => radar_feed_download('https://example.com/feed')) && rejects(static fn() => radar_feed_download('https://127.0.0.1/feed')));

$recent = gmdate(DATE_RSS, time() - 600);
$xml = rss(
    fixtureItem('<b>Manchete real</b> & explicação', 'https://example.com/news/one', '<pubDate>' . $recent . '</pubDate><source url="https://example.com">Jornal de teste</source><media:thumbnail url="https://images.example.com/one.jpg"/>') .
    fixtureItem('Sem data e sem imagem', 'https://example.com/news/two', '<description><![CDATA[<img src="https://images.example.com/invented.jpg">]]></description>'),
    'xmlns:media="http://search.yahoo.com/mrss/"'
);
$items = radar_parse_feed($xml, 'Política');
check('RSS parser preserves category, source and article link', static fn(): bool => count($items) === 2 && $items[0]['category'] === 'Política' && $items[0]['source'] === 'Jornal de teste' && $items[0]['url'] === 'https://example.com/news/one');
check('RSS title HTML is removed and entities decoded', static fn(): bool => $items[0]['title'] === 'Manchete real & explicação');
check('article identifier is deterministic across repeated parsing', static fn(): bool => $items[0]['id'] === radar_parse_feed($xml, 'Política')[0]['id'] && $items[0]['id'] === hash('sha256', $items[0]['url']));
check('image is a supplied MediaRSS URL with provenance', static fn(): bool => $items[0]['image'] === 'https://images.example.com/one.jpg' && $items[0]['image_credit'] === 'Jornal de teste');
check('description images are not invented as verified cover images', static fn(): bool => $items[1]['image'] === null);
check('missing publication date remains null instead of current time', static fn(): bool => $items[1]['published'] === null && $items[1]['score'] === 5);
check('RSS ingestion never marks a headline as fact checked', static fn(): bool => $items[0]['verification'] === 'headline_only' && $items[1]['verification'] === 'headline_only');
check('heuristic score stays within declared bounds', static fn(): bool => $items[0]['score'] >= 5 && $items[0]['score'] <= 100 && str_contains($items[0]['rank_reason'], 'RSS'));
check('invalid and far-future dates remain unknown', static fn(): bool => radar_feed_date('yesterday') === null && radar_feed_date(gmdate(DATE_RSS, time() + 86400)) === null && radar_feed_date('not a date') === null);
check('duplicate article URLs are deduplicated', static fn(): bool => count(radar_parse_feed(rss(fixtureItem('A', 'https://example.com/a') . fixtureItem('A duplicate', 'https://example.com/a')), 'Geral')) === 1);
check('unsafe feed URLs are discarded while safe entries survive', static fn(): bool => count(radar_parse_feed(rss(fixtureItem('Bad', 'javascript:alert(1)') . fixtureItem('Good', 'https://example.com/good')), 'Geral')) === 1);
check('unsafe media and publisher links are discarded', static function (): bool {
    $entry = radar_parse_feed(rss(fixtureItem('A', 'https://example.com/a', '<source url="javascript:alert(1)">Jornal</source><enclosure type="image/png" url="http://127.0.0.1/a.png"/>')), 'Geral')[0];
    return $entry['image'] === null && $entry['source_url'] === null;
});
$many = '';
for ($i = 0; $i < 125; $i++) {
    $many .= fixtureItem('Notícia ' . $i, 'https://example.com/item/' . $i);
}
check('RSS requested item limit is respected', static fn(): bool => count(radar_parse_feed(rss($many), 'Geral', 3)) === 3);
check('RSS processing stays bounded at 100 entries', static fn(): bool => count(radar_parse_feed(rss($many), 'Geral', 10000)) === 100);
check('valid empty RSS is an empty state', static fn(): bool => radar_parse_feed(rss(''), 'Geral') === []);
check('malformed XML and non-RSS documents are rejected', static fn(): bool => rejects(static fn() => radar_parse_feed('<rss><channel>', 'Geral')) && rejects(static fn() => radar_parse_feed('<html/>', 'Geral')));
check('external entity/DTD payload cannot access local files', static fn(): bool => rejects(static fn() => radar_parse_feed('<!DOCTYPE rss [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><rss><channel><item><title>&xxe;</title><link>https://example.com/a</link></item></channel></rss>', 'Geral')));
check('billion-laughs entities are rejected without expanding', static fn(): bool => rejects(static fn() => radar_parse_feed('<!DOCTYPE rss [<!ENTITY a "a"><!ENTITY b "&a;&a;">]><rss><channel/></rss>', 'Geral')));
check('oversized XML is rejected', static fn(): bool => rejects(static fn() => radar_parse_feed(str_repeat(' ', 2_000_001), 'Geral')));
$trends = radar_parse_trends(rss(fixtureItem('Pesquisa real', 'https://trends.google.com/trending?geo=BR', '<ht:approx_traffic>20K+</ht:approx_traffic>') . fixtureItem('Sem tráfego fornecido', 'https://trends.google.com/trending'), 'xmlns:ht="https://trends.google.com/trending/rss"'));
check('Google Trends traffic is preserved only when provided', static fn(): bool => count($trends) === 2 && $trends[0]['traffic'] === '20K+' && $trends[1]['traffic'] === null);

$cacheDirectory = radar_feed_cache_dir();
if (!is_string($cacheDirectory)) {
    throw new RuntimeException('Could not create isolated test cache');
}
$cacheFile = $cacheDirectory . '/' . hash('sha256', 'news:Geral') . '.json';
file_put_contents($cacheFile, json_encode(['fetched_at' => time(), 'items' => $items], JSON_THROW_ON_ERROR));
$cached = radar_fetch('Geral');
check('fresh cache serves articles offline without a network call', static fn(): bool => $cached['error'] === null && $cached['stale'] === false && count($cached['items']) === 2);
check('ranking clearly distinguishes heuristics from official trends', static fn(): bool => str_contains($cached['ranking'], 'heurística') && str_contains($cached['ranking'], 'não é ranking oficial'));
file_put_contents($cacheFile, json_encode(['fetched_at' => time() - 600, 'items' => $items], JSON_THROW_ON_ERROR));
$stale = radar_feed_cached_fetch('news:Geral', 'https://invalid.example.com/not-allowed', static fn(string $data): array => []);
check('upstream failure serves labelled stale cache with warning', static fn(): bool => $stale['error'] === null && $stale['stale'] === true && count($stale['items']) === 2 && count($stale['warnings']) > 0);
unlink($cacheFile);
$unavailable = radar_feed_cached_fetch('news:Geral', 'https://invalid.example.com/not-allowed', static fn(string $data): array => []);
check('upstream failure without cache exposes an honest empty error', static fn(): bool => is_string($unavailable['error']) && $unavailable['items'] === [] && $unavailable['updated'] === null);

// Script generator regression checks follow the shared API contract.
$item = $items[0];
$script = radar_build_script($item, 25, 'bem-humorado', '');
check('script response includes every dashboard field', static fn(): bool => array_diff(['headline', 'script', 'prompt', 'duration', 'caution'], array_keys($script)) === []);
check('script headline stays faithful to the supplied headline', static fn(): bool => $script['headline'] === $item['title']);
check('script generation attributes its source and links to it', static fn(): bool => str_contains($script['prompt'], $item['source']) && str_contains($script['prompt'], $item['url']));
check('script uses the requested duration and fixed character description', static fn(): bool => $script['duration'] === 25 && str_contains($script['prompt'], '25') && str_contains($script['prompt'], 'negro') && str_contains($script['prompt'], 'óculos'));
check('script warns that RSS alone does not verify the story', static fn(): bool => $script['caution'] !== '' && preg_match('/chec|confirm|verific/i', $script['caution']) === 1);
check('script context is explicit context rather than fabricated facts', static function () use ($item): bool {
    $withContext = radar_build_script($item, 20, 'direto', 'Teste de contexto: o evento foi remarcado.');
    return str_contains($withContext['prompt'], 'Teste de contexto: o evento foi remarcado.') && $withContext['caution'] !== '';
});

// Minimal valid PNG. SVG/script payloads and mismatched submitted sizes must fail.
$pngPath = $testDirectory . '/image.png';
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jpXkAAAAASUVORK5CYII=', true);
file_put_contents($pngPath, $png);
$image = radar_media_validate($pngPath, strlen($png));
check('valid PNG has inspected MIME, dimensions and canonical extension', static fn(): bool => $image['mime'] === 'image/png' && $image['extension'] === 'png' && $image['width'] === 1 && $image['height'] === 1);
$svgPath = $testDirectory . '/payload.svg';
file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
check('active SVG content is not accepted as an uploaded image', static fn(): bool => rejects(static fn() => radar_media_validate($svgPath, filesize($svgPath)), InvalidArgumentException::class));
$phpPath = $testDirectory . '/payload.jpg';
file_put_contents($phpPath, '<?php echo "payload";');
check('PHP content disguised as JPEG is rejected', static fn(): bool => rejects(static fn() => radar_media_validate($phpPath, filesize($phpPath)), InvalidArgumentException::class));
check('oversized upload rejected before image decoding', static fn(): bool => rejects(static fn() => radar_media_validate($pngPath, 5 * 1024 * 1024 + 1), InvalidArgumentException::class));
check('image dimensions are capped independently from file size', static function () use ($testDirectory, $png): bool {
    $path = $testDirectory . '/too-wide.png';
    $widePng = substr_replace($png, pack('NN', 4097, 1), 16, 8);
    file_put_contents($path, $widePng);
    return rejects(static fn() => radar_media_validate($path, strlen($widePng)), InvalidArgumentException::class);
});
check('total decoded pixel count is capped independently from side length', static function () use ($testDirectory, $png): bool {
    $path = $testDirectory . '/too-many-pixels.png';
    $largePng = substr_replace($png, pack('NN', 4096, 4096), 16, 8);
    file_put_contents($path, $largePng);
    return rejects(static fn() => radar_media_validate($path, strlen($largePng)), InvalidArgumentException::class);
});
check('nonexistent upload is rejected', static fn(): bool => rejects(static fn() => radar_media_validate($testDirectory . '/missing.png', 20), InvalidArgumentException::class));
check('media IDs reject directory traversal and executable extensions', static fn(): bool => !radar_media_id_valid('../config.php') && !radar_media_id_valid(str_repeat('a', 32) . '.php') && !radar_media_id_valid(str_repeat('a', 32) . '.png/../config.php') && radar_media_id_valid(str_repeat('a', 32) . '.png'));
check('invalid script duration and tone are rejected', static fn(): bool => rejects(static fn() => radar_build_script($item, 14, 'direto', ''), InvalidArgumentException::class) && rejects(static fn() => radar_build_script($item, 36, 'direto', ''), InvalidArgumentException::class) && rejects(static fn() => radar_build_script($item, 25, 'fake-tone', ''), InvalidArgumentException::class));

require __DIR__ . '/http.php';

echo "\n$passed passed; $failed failed.\n";
exit($failed > 0 ? 1 : 0);

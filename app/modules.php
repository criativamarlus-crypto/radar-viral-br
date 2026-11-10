<?php
declare(strict_types=1);

/** Loads every application module. Requires radar_config() to exist (bootstrap.php or a test double). */
foreach (['net', 'db', 'feeds', 'sources', 'ranking', 'og', 'collector', 'news', 'media', 'character', 'ai', 'reacts', 'diagnostics'] as $module) {
    require_once __DIR__ . '/' . $module . '.php';
}

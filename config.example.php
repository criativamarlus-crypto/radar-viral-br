<?php
declare(strict_types=1);

// Gerado por `php bin/setup.php`. Nunca comite config.php nem segredos reais.
// Chaves de IA e do serviço de captura NÃO ficam aqui: são salvas criptografadas no
// banco pelo painel (ou vêm de variáveis de ambiente do PHP-FPM).
return [
    'admin_user' => 'admin',
    'admin_password_hash' => '', // password_hash(), gerado pelo setup.php
    'api_token_hash' => '', // SHA-256 do token de leitura criado pelo setup.php (tokens novos: painel > Integrações)
    'site_url' => 'https://viral.netnewsltda.online',
    'secure_cookies' => true, // false somente para desenvolvimento local em HTTP
    'storage_path' => __DIR__ . '/storage', // banco SQLite, sessões, imagens e chave mestra (0700, fora da raiz pública)
    'feed_ttl' => 300,
    // 'app_key' => '64 caracteres hexadecimais', // opcional: chave mestra dos segredos; sem ela é criada em storage/app.key
];

#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$options = getopt('', ['help', 'force', 'user:', 'site-url:', 'config:', 'local-http', 'password-stdin']);
if (isset($options['help'])) {
    echo "Radar Viral BR — configuração privada\n\n";
    echo "php bin/setup.php [--user=admin] [--site-url=https://viral.netnewsltda.online]\n";
    echo "                  [--config=/absolute/shared/config.php] [--password-stdin] [--force] [--local-http]\n\n";
    echo "Senha solicitada sem eco no terminal. Com --password-stdin, leia uma linha de STDIN.\n";
    echo "Não passe senhas em argumentos. --force substitui as credenciais existentes.\n";
    echo "--local-http só permite http://localhost ou http://127.0.0.1 para desenvolvimento.\n";
    exit(0);
}
$root = dirname(__DIR__);
$path = $options['config'] ?? ($root . '/config.php');
if (!is_string($path) || !str_starts_with($path, '/') || !is_dir(dirname($path)) || !is_writable(dirname($path))) {
    fwrite(STDERR, "--config deve ser um caminho absoluto em uma pasta existente com permissão de escrita.\n");
    exit(1);
}
$configDirectory = dirname($path);
if (is_file($path) && !isset($options['force'])) {
    fwrite(STDERR, "Configuração existente. Use --force somente para trocar as credenciais.\n");
    exit(1);
}
if (is_link($path)) {
    fwrite(STDERR, "Recusado: config.php é um link simbólico.\n");
    exit(1);
}
$user = $options['user'] ?? 'admin';
$url = $options['site-url'] ?? 'https://viral.netnewsltda.online';
$local = isset($options['local-http']);
if (!is_string($user) || !preg_match('/^[A-Za-z0-9_.@-]{3,100}$/D', $user)) {
    fwrite(STDERR, "Usuário inválido: use 3 a 100 letras, números ou _.@-.\n");
    exit(1);
}
$parsed = is_string($url) ? parse_url($url) : false;
if (!is_array($parsed) || empty($parsed['host']) || (isset($parsed['user']) || isset($parsed['pass']))
    || isset($parsed['query']) || isset($parsed['fragment'])
    || (($parsed['scheme'] ?? '') !== 'https' && !($local && ($parsed['scheme'] ?? '') === 'http' && in_array($parsed['host'], ['localhost', '127.0.0.1'], true)))) {
    fwrite(STDERR, "URL inválida: produção exige HTTPS; HTTP local exige --local-http.\n");
    exit(1);
}
if ($local && !in_array($parsed['host'], ['localhost', '127.0.0.1'], true)) {
    fwrite(STDERR, "--local-http é reservado ao ambiente local.\n");
    exit(1);
}
$stdinPassword = isset($options['password-stdin']);
$isTty = function_exists('stream_isatty') && stream_isatty(STDIN);
if (!$stdinPassword && !$isTty) {
    fwrite(STDERR, "Use --password-stdin para uma entrada não interativa, sem senha nos argumentos.\n");
    exit(1);
}
if (!$stdinPassword) {
    fwrite(STDERR, 'Senha do administrador (mínimo 14 caracteres): ');
    $state = [];
    $result = 1;
    exec('stty -g', $state, $result);
    if ($result !== 0 || !isset($state[0])) {
        fwrite(STDERR, "Não foi possível ocultar a senha. Use --password-stdin.\n");
        exit(1);
    }
    $terminalState = $state[0];
    exec('stty -echo');
    register_shutdown_function(static function () use ($terminalState): void {
        exec('stty ' . escapeshellarg($terminalState));
    });
}
$password = fgets(STDIN, 1026);
if (!$stdinPassword) {
    exec('stty ' . escapeshellarg($terminalState));
    fwrite(STDERR, "\n");
}
$password = is_string($password) ? rtrim($password, "\r\n") : '';
if (strlen($password) < 14 || strlen($password) > 72) {
    fwrite(STDERR, "A senha deve ter entre 14 e 72 bytes.\n");
    exit(1);
}
umask(0077);
$storage = $configDirectory . '/storage';
if ((!is_dir($storage) && !mkdir($storage, 0700, true)) || is_link($storage) || !chmod($storage, 0700)) {
    fwrite(STDERR, "Não foi possível criar storage privado.\n");
    exit(1);
}
$token = bin2hex(random_bytes(32));
$config = [
    'admin_user' => $user,
    'admin_password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'api_token_hash' => hash('sha256', $token),
    'site_url' => rtrim($url, '/'),
    'secure_cookies' => !$local,
    'storage_path' => $storage,
    'feed_ttl' => 300,
];
$password = '';
$content = "<?php\ndeclare(strict_types=1);\nreturn " . var_export($config, true) . ";\n";
$temp = tempnam($configDirectory, '.radar-config-');
if ($temp === false || file_put_contents($temp, $content, LOCK_EX) === false || !chmod($temp, 0600) || !rename($temp, $path)) {
    if (is_string($temp)) {
        @unlink($temp);
    }
    fwrite(STDERR, "Não foi possível gravar config.php.\n");
    exit(1);
}
echo "Configuração privada criada. Usuário: {$user}\n";
echo "Token de API/MCP somente leitura (guarde no gerenciador de segredos; mostrado uma vez):\n{$token}\n";
echo "Mantenha config.php e storage fora da raiz pública.\n";

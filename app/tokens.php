<?php
declare(strict_types=1);

/**
 * API/MCP bearer tokens. Only the SHA-256 of a token is stored; the plain token is shown
 * once at creation. Scopes: "read" (news, trends, scripts) and "react" (also prepare a react).
 */

const RADAR_TOKEN_SCOPES = ['read' => 'Somente leitura (notícias, tendências, roteiros)', 'react' => 'Leitura + preparar react (gera roteiro, usa a IA do servidor)'];

/** @return array{id:int,token:string,scopes:string} */
function radar_token_create(string $name, string $scope): array
{
    $name = mb_substr(trim(strip_tags($name)), 0, 80);
    if ($name === '' || !isset(RADAR_TOKEN_SCOPES[$scope])) {
        throw new InvalidArgumentException('Informe um nome e escolha o acesso do token.');
    }
    if ((int)radar_db_value('SELECT COUNT(*) FROM api_tokens WHERE revoked_at IS NULL') >= 20) {
        throw new InvalidArgumentException('Limite de 20 tokens ativos. Revogue algum antes de criar outro.');
    }
    $token = 'rvb_' . bin2hex(random_bytes(24));
    $scopes = $scope === 'react' ? 'read,react' : 'read';
    $id = radar_db_insert('INSERT INTO api_tokens (name, token_hash, scopes, created_at) VALUES (?, ?, ?, ?)', [$name, hash('sha256', $token), $scopes, time()]);
    return ['id' => $id, 'token' => $token, 'scopes' => $scopes];
}

/** @return array<int,string>|null scopes when valid */
function radar_token_authenticate(string $token): ?array
{
    $row = radar_db_one('SELECT id, scopes, last_used_at FROM api_tokens WHERE token_hash = ? AND revoked_at IS NULL', [hash('sha256', $token)]);
    if ($row === null) {
        return null;
    }
    // Throttle bookkeeping writes: one update per token per 10 minutes.
    if ($row['last_used_at'] === null || time() - (int)$row['last_used_at'] > 600) {
        radar_db_exec('UPDATE api_tokens SET last_used_at = ? WHERE id = ?', [time(), $row['id']]);
    }
    return explode(',', (string)$row['scopes']);
}

/** @return array<int,array<string,mixed>> */
function radar_tokens_list(): array
{
    return radar_db_all('SELECT id, name, scopes, created_at, last_used_at, revoked_at FROM api_tokens ORDER BY revoked_at IS NOT NULL, created_at DESC');
}

function radar_token_revoke(int $id): bool
{
    return radar_db_exec('UPDATE api_tokens SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL', [time(), $id]) > 0;
}

/** Scopes granted to the current request (set by radar_require_read_access / login). */
function radar_scopes(): array
{
    return $GLOBALS['radar_scopes'] ?? [];
}

function radar_has_scope(string $scope): bool
{
    return in_array($scope, radar_scopes(), true) || in_array('admin', radar_scopes(), true);
}

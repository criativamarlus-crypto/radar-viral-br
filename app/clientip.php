<?php
declare(strict_types=1);

/**
 * Visitor IP for rate limiting.
 *
 * Behind Cloudflare the TCP peer (REMOTE_ADDR) is a Cloudflare edge address shared by many
 * visitors, so per-IP limits would be shared too (one person's failed logins would lock
 * everyone else out). The CF-Connecting-IP header carries the real visitor, but a header is
 * trivially forged, so it is honoured ONLY when the peer itself is inside Cloudflare's
 * published ranges. Anyone connecting directly gets their real peer address.
 */

/** Official ranges, https://www.cloudflare.com/ips (checked 2026-11-10). Cloudflare changes them rarely. */
function radar_cloudflare_ranges(): array
{
    return [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];
}

/** Is $ip inside $cidr? Works for IPv4 and IPv6; mismatched families never match. */
function radar_ip_in_cidr(string $ip, string $cidr): bool
{
    $parts = explode('/', $cidr, 2);
    $bits = isset($parts[1]) ? (int)$parts[1] : -1;
    $a = @inet_pton($ip);
    $b = @inet_pton($parts[0]);
    if ($a === false || $b === false || strlen($a) !== strlen($b) || $bits < 0 || $bits > strlen($a) * 8) {
        return false;
    }
    $bytes = intdiv($bits, 8);
    $rest = $bits % 8;
    if ($bytes > 0 && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
        return false;
    }
    if ($rest === 0) {
        return true;
    }
    $mask = (0xFF << (8 - $rest)) & 0xFF;
    return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
}

/** The visitor's IP, or the peer address, or 'unknown'. */
function radar_client_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($remote) || filter_var($remote, FILTER_VALIDATE_IP) === false) {
        return 'unknown';
    }
    $forwarded = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if (is_string($forwarded) && filter_var($forwarded, FILTER_VALIDATE_IP) !== false) {
        foreach (radar_cloudflare_ranges() as $range) {
            if (radar_ip_in_cidr($remote, $range)) {
                return $forwarded;
            }
        }
    }
    return $remote;
}

/** Rate-limit bucket key: one bucket per action and visitor. */
function radar_client_key(string $prefix): string
{
    return $prefix . ':' . radar_client_ip();
}

<?php
declare(strict_types=1);

/**
 * Configure and start the PHP session with cookies that work both on localhost
 * and when the project is exposed via tunnelling services (e.g. ngrok).
 *
 * This ensures:
 *  - cookie path is rooted at the project (`/`)
 *  - cookie domain matches the current request host (no hard-coded localhost)
 *  - Secure flag follows the current scheme (https → true)
 *  - HttpOnly + SameSite=Lax for better security while keeping first-party fetches working
 */
function start_project_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');

    if (!$isHttps) {
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if ($forwardedProto !== '') {
            $isHttps = array_reduce(
                array_map('trim', explode(',', strtolower($forwardedProto))),
                static fn (bool $carry, string $value) => $carry || $value === 'https',
                false
            );
        }
    }

    if (!$isHttps) {
        $forwardedSsl = strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
        $isHttps = $forwardedSsl === 'on' || $forwardedSsl === '1';
    }

    if (!$isHttps) {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '' && str_contains($host, 'ngrok-')) {
            $isHttps = true;
        }
    }

    $sameSite = $isHttps ? 'None' : 'Lax';

    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => $sameSite,
    ];

    session_set_cookie_params($cookieParams);
    session_start();
}

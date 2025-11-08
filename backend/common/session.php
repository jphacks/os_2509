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

    // スマホ対応: HTTPでもLaxを使用（Noneは不安定）
    $sameSite = 'Lax';

    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', $sameSite);
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }

    $cookieParams = [
        'lifetime' => 86400,  // 24時間（スマホ対応のため0から変更）
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => $sameSite,
    ];

    // デバッグログ（本番環境では削除可能）
    error_log("Session config: " . json_encode([
        'lifetime' => $cookieParams['lifetime'],
        'path' => $cookieParams['path'],
        'secure' => $cookieParams['secure'],
        'samesite' => $cookieParams['samesite'],
        'https' => $isHttps,
        'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    ]));

    session_set_cookie_params($cookieParams);
    
    // ガベージコレクションの設定も延長
    ini_set('session.gc_maxlifetime', '86400');
    
    session_start();
    
    // セッション開始をログに記録
    error_log("Session started: ID=" . session_id() . ", Data=" . json_encode($_SESSION));
}



<?php
declare(strict_types=1);

/**
 * Configure and start the PHP session with cookies that work both on localhost
 * and when the project is exposed via tunnelling services (e.g. ngrok).
 *
 * This ensures:
 * - cookie path is rooted at the project (`/`)
 * - cookie domain matches the current request host (no hard-coded localhost)
 * - Secure flag follows the current scheme (https → true)
 * - HttpOnly + SameSite=Lax for better security while keeping first-party fetches working
 */

// ★★★ 必須: ロガーの読み込み ★★★
require_once __DIR__ . '/logger.php'; 

function start_project_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        app_log("Session already active", ['session_id' => session_id()]);
        return;
    }

    // ----------------------------------------------------
    // 1. HTTPS 判定ロジック
    // ----------------------------------------------------
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
    
    // ★★★ 強制HTTPS判定の追加 (必要に応じて解除) ★★★
    $currentHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if (!$isHttps && str_contains($currentHost, 'tunagaridiary.com')) {
        $isHttps = true;
    }

    // スマホ対応: HTTPでもLaxを使用（Noneは不安定）
    $sameSite = 'Lax';

    // ----------------------------------------------------
    // 2. セッション設定の適用
    // ----------------------------------------------------
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', $sameSite);
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }

    $cookieParams = [
        'lifetime' => 86400, 	// 24時間
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => $sameSite,
    ];

    session_set_cookie_params($cookieParams);
    
    // ガベージコレクションの設定も延長
    ini_set('session.gc_maxlifetime', '86400');
    
    // ★★★ セッション開始前のデバッグログ ★★★
    app_log("Before session_start()", [
        'cookie_sent' => $_COOKIE[session_name()] ?? 'NOT SENT',
        'cookie_name' => session_name(),
    ]);
    
    // ----------------------------------------------------
    // 3. セッション開始とデバッグロギング
    // ----------------------------------------------------
    session_start();

    // ★★★ セッション開始後の詳細デバッグログ ★★★
    app_log("After session_start()", [
        'is_https_detected' => $isHttps, 
        'secure_cookie_set' => $isHttps,
        'samesite_policy' => $sameSite,
        'session_id' => session_id(),
        'cookie_sent_by_browser' => $_COOKIE[session_name()] ?? 'NOT SENT',
        'session_data_keys' => array_keys($_SESSION),
        'session_save_path' => session_save_path(),
        'account_id_exists' => isset($_SESSION['account_id']),
        'account_name_exists' => isset($_SESSION['account_name']),
        'account_id_value' => $_SESSION['account_id'] ?? 'NOT SET',
        'account_name_value' => $_SESSION['account_name'] ?? 'NOT SET',
    ]);
}
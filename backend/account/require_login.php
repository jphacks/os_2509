<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['account_id'], $_SESSION['account_name'])) {
    $responseType = defined('AUTH_GUARD_RESPONSE_TYPE') ? AUTH_GUARD_RESPONSE_TYPE : 'json';
    http_response_code(401);
    if ($responseType === 'text') {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'unauthorized';
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

return [
    'id' => (int)$_SESSION['account_id'],
    'name' => (string)$_SESSION['account_name'],
];

<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

if (isset($_SESSION['account_id'], $_SESSION['account_name'])) {
    echo json_encode([
        'authenticated' => true,
        'account' => [
            'id' => (int)$_SESSION['account_id'],
            'name' => (string)$_SESSION['account_name'],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(401);
echo json_encode(['authenticated' => false], JSON_UNESCAPED_UNICODE);

<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/common/session.php';
start_project_session();

$loggedIn = isset($_SESSION['account_id'], $_SESSION['account_name']);

if ($loggedIn) {
    header('Location: /frontend/top/select/select_page.html', true, 302);
    exit;
}

require __DIR__ . '/frontend/top/account/login.html';
exit;

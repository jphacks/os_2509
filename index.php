<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/common/session.php';
start_project_session();

// ログイン済みならセレクト画面へ
if (isset($_SESSION['account_id'], $_SESSION['account_name'])) {
    header('Location: ./frontend/top/select/select_page.html', true, 302);
    exit;
}

// 未ログインならログイン画面へ（login.html をそのまま表示させる）
header('Location: ./frontend/top/account/login.html', true, 302);
exit;

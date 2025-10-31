<?php
declare(strict_types=1);

session_start();

$target = (isset($_SESSION['account_id'], $_SESSION['account_name']))
    ? 'frontend/top/select/select_page.html'
    : 'frontend/account/login.html';

header('Location: ' . $target);
exit;

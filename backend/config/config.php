<?php
// データベース設定
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'backhold');
define('DB_PASSWORD', 'backhold');
define('DB_NAME', 'back_db1');

// データベース接続関数
function getDbConnection() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    if ($conn->connect_error) {
        die("接続失敗: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>
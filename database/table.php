<?php

require_once '../config/config.php';

$servername = "localhost";
$username = "backhold";
$password = "backhold";
$dbname = "back_db1";

// データベース接続を作成
$conn = new mysqli($servername, $username, $password, $dbname);

// 接続を確認
if ($conn->connect_error) {
    die("接続失敗: " . $conn->connect_error);
}

// db1テーブル作成 (音声テキスト用)
$sql1 = "CREATE TABLE IF NOT EXISTS db1 (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATETIME NOT NULL,
    soundtext TEXT NULL,
    location VARCHAR(255) NOT NULL
)";

// db1_1テーブル作成 (追加テーブル)
$sql1_1 = "CREATE TABLE IF NOT EXISTS db1_1 (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATETIME NOT NULL,
    location VARCHAR(255) NOT NULL
)";

// db2テーブル作成
$sql2 = "CREATE TABLE IF NOT EXISTS db2 (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATETIME NOT NULL,
    soundsum TEXT NOT NULL,
    place VARCHAR(255) NOT NULL
)";

// db3テーブル作成
$sql3 = "CREATE TABLE IF NOT EXISTS db3 (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATETIME NOT NULL,
    sentence TEXT NOT NULL,
    place VARCHAR(255) NULL,
    image MEDIUMBLOB NULL
)";

// 各テーブルを作成
if ($conn->query($sql1) === TRUE) {
    echo "db1テーブルが正常に作成されました<br>";
} else {
    echo "db1エラー: " . $conn->error . "<br>";
}

if ($conn->query($sql1_1) === TRUE) {
    echo "db1_1テーブルが正常に作成されました<br>";
} else {
    echo "db1_1エラー: " . $conn->error . "<br>";
}

if ($conn->query($sql2) === TRUE) {
    echo "db2テーブルが正常に作成されました<br>";
} else {
    echo "db2エラー: " . $conn->error . "<br>";
}

if ($conn->query($sql3) === TRUE) {
    echo "db3テーブルが正常に作成されました<br>";
} else {
    echo "db3エラー: " . $conn->error . "<br>";
}

// 接続を閉じる
$conn->close();
?>
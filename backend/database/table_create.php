<?php
/**
 * データベース完全セットアップスクリプト
 * データベースとテーブルをゼロから作成します
 */

$servername = "localhost";
$username = "backhold";
$password = "backhold";
$dbname = "back_db1";

echo "<!DOCTYPE html>";
echo "<html lang='ja'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>データベースセットアップ</title>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
    h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
    h2 { color: #666; margin-top: 30px; }
    .success { color: #4CAF50; font-weight: bold; }
    .error { color: #f44336; font-weight: bold; }
    .info { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th { background: #4CAF50; color: white; padding: 10px; text-align: left; }
    td { border: 1px solid #ddd; padding: 8px; }
    tr:nth-child(even) { background: #f9f9f9; }
    .step { background: #fff3cd; padding: 10px; margin: 10px 0; border-left: 4px solid #ffc107; }
</style>";
echo "</head>";
echo "<body>";

echo "<h1>🚀 データベース完全セットアップ</h1>";

// ステップ1: MySQL接続（データベース選択なし）
echo "<div class='step'><strong>ステップ1:</strong> MySQLサーバーに接続中...</div>";
$conn = new mysqli($servername, $username, $password);

if ($conn->connect_error) {
    die("<p class='error'>❌ 接続失敗: " . $conn->connect_error . "</p></body></html>");
}
echo "<p class='success'>✓ MySQLサーバーに接続成功</p>";

// ステップ2: データベースの作成
echo "<div class='step'><strong>ステップ2:</strong> データベース '$dbname' を作成中...</div>";
$sql = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✓ データベース '$dbname' を作成しました</p>";
} else {
    echo "<p class='error'>❌ エラー: " . $conn->error . "</p>";
}

// データベースを選択
$conn->select_db($dbname);
echo "<p class='success'>✓ データベース '$dbname' を選択しました</p>";

// ステップ3: 既存テーブルの削除（クリーンスタート）
echo "<div class='step'><strong>ステップ3:</strong> 既存のテーブルを削除中...</div>";
$tables = ['db0', 'db1', 'db1_1', 'db2', 'db3'];
foreach ($tables as $table) {
    $sql = "DROP TABLE IF EXISTS $table";
    if ($conn->query($sql) === TRUE) {
        echo "<p class='success'>✓ テーブル '$table' を削除しました（存在した場合）</p>";
    }
}

// ステップ4: db0テーブルの作成（緯度経度用）
echo "<div class='step'><strong>ステップ4:</strong> db0テーブル（緯度経度用）を作成中...</div>";
$sql_db0 = "CREATE TABLE db0 (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATETIME NOT NULL COMMENT '記録日時',
    latitude DECIMAL(10, 8) NULL COMMENT '緯度',
    longitude DECIMAL(11, 8) NULL COMMENT '経度',
    INDEX idx_date (date),
    INDEX idx_coords (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='位置情報（緯度経度）テーブル'";

if ($conn->query($sql_db0) === TRUE) {
    echo "<p class='success'>✓ db0テーブル（緯度経度用）を作成しました</p>";
} else {
    echo "<p class='error'>❌ db0エラー: " . $conn->error . "</p>";
}

// ステップ5: db1テーブルの作成（音声テキスト用）
echo "<div class='step'><strong>ステップ5:</strong> db1テーブル（音声テキスト用）を作成中...</div>";
$sql_db1 = "CREATE TABLE db1 (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATETIME NOT NULL COMMENT '記録日時',
    soundtext TEXT NULL COMMENT '音声認識テキスト',
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='音声テキストテーブル'";

if ($conn->query($sql_db1) === TRUE) {
    echo "<p class='success'>✓ db1テーブル（音声テキスト用）を作成しました</p>";
} else {
    echo "<p class='error'>❌ db1エラー: " . $conn->error . "</p>";
}

// ステップ6: db1_1テーブルの作成（予備用）
echo "<div class='step'><strong>ステップ6:</strong> db1_1テーブル（予備用）を作成中...</div>";
$sql_db1_1 = "CREATE TABLE db1_1 (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATETIME NOT NULL,
    location TEXT NULL COMMENT '予備フィールド',
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='予備テーブル'";

if ($conn->query($sql_db1_1) === TRUE) {
    echo "<p class='success'>✓ db1_1テーブル（予備用）を作成しました</p>";
} else {
    echo "<p class='error'>❌ db1_1エラー: " . $conn->error . "</p>";
}

// ステップ7: db2テーブルの作成（予備用）
echo "<div class='step'><strong>ステップ7:</strong> db2テーブル（予備用）を作成中...</div>";
$sql_db2 = "CREATE TABLE db2 (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATETIME NOT NULL,
    soundsum TEXT NULL,
    place VARCHAR(255) NULL,
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='予備テーブル'";

if ($conn->query($sql_db2) === TRUE) {
    echo "<p class='success'>✓ db2テーブル（予備用）を作成しました</p>";
} else {
    echo "<p class='error'>❌ db2エラー: " . $conn->error . "</p>";
}

// ステップ8: db3テーブルの作成（予備用）
echo "<div class='step'><strong>ステップ8:</strong> db3テーブル（予備用）を作成中...</div>";
$sql_db3 = "CREATE TABLE db3 (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATETIME NOT NULL,
    sentence TEXT NULL,
    place VARCHAR(255) NULL,
    image MEDIUMBLOB NULL,
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='予備テーブル'";

if ($conn->query($sql_db3) === TRUE) {
    echo "<p class='success'>✓ db3テーブル（予備用）を作成しました</p>";
} else {
    echo "<p class='error'>❌ db3エラー: " . $conn->error . "</p>";
}

// テーブル一覧の表示
echo "<h2>📊 作成されたテーブル一覧</h2>";
$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "<ul>";
    while ($row = $result->fetch_array()) {
        echo "<li class='success'>" . $row[0] . "</li>";
    }
    echo "</ul>";
}

// db0テーブルの構造表示
echo "<h2>📋 db0テーブル構造（緯度経度用）</h2>";
$result = $conn->query("DESCRIBE db0");
if ($result) {
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th><th>Comment</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Comment'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// db1テーブルの構造表示
echo "<h2>📋 db1テーブル構造（音声テキスト用）</h2>";
$result = $conn->query("DESCRIBE db1");
if ($result) {
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th><th>Comment</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Comment'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();

// 完了メッセージ
echo "<div class='info'>";
echo "<h2>✅ セットアップ完了！</h2>";
echo "<p><strong>作成されたもの:</strong></p>";
echo "<ul>";
echo "<li>データベース: <code>$dbname</code></li>";
echo "<li>db0テーブル: 緯度経度を保存（<code>latitude</code>, <code>longitude</code>）</li>";
echo "<li>db1テーブル: 音声テキストを保存（<code>soundtext</code>）</li>";
echo "<li>db1_1, db2, db3テーブル: 予備テーブル</li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
?>
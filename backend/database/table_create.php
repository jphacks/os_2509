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

// ステップ9: db0にサンプルデータ（歩行経路）を挿入
echo "<div class='step'><strong>ステップ9:</strong> db0テーブルにサンプルデータを挿入中...</div>";

try {
    // 場所の定義（ダミーの緯度経度）
    $locations = [
        'home'   => ['lat' => 35.68000000, 'lon' => 139.76000000], // 家
        'school' => ['lat' => 35.68200000, 'lon' => 139.76300000], // 小学校
        'park'   => ['lat' => 35.68400000, 'lon' => 139.76100000]  // 公園
    ];

    // データポイントを格納する配列
    $dataPoints = [];
    
    // 現在時刻を基準にし、キリの良い時間（例: 9:00:00）からスタート
    $currentTime = new DateTime();
    $currentTime->setTime((int)$currentTime->format('H'), 0, 0); 
    
    $moveInterval = new DateInterval('PT2M'); // 2分の移動間隔
    $moveSteps = 5; // 移動を5ステップ（計10分）で表現

    // --- 1. 家（スタート） ---
    $dataPoints[] = sprintf("('%s', %f, %f)", $currentTime->format('Y-m-d H:i:s'), $locations['home']['lat'], $locations['home']['lon']);

    // --- 2. 家 → 小学校 (10分かけて移動) ---
    $latStep = ($locations['school']['lat'] - $locations['home']['lat']) / $moveSteps;
    $lonStep = ($locations['school']['lon'] - $locations['home']['lon']) / $moveSteps;
    for ($i = 1; $i <= $moveSteps; $i++) {
        $currentTime->add($moveInterval);
        $lat = $locations['home']['lat'] + $latStep * $i;
        $lon = $locations['home']['lon'] + $lonStep * $i;
        $dataPoints[] = sprintf("('%s', %f, %f)", $currentTime->format('Y-m-d H:i:s'), $lat, $lon);
    }

    // --- 3. 小学校（1時間滞在） ---
    // 滞在中のデータ（15分ごと）
    $stayDuration = 60; // 60分
    $stayIntervalMinutes = 15; // 15分間隔
    for ($i = 0; $i < $stayDuration; $i += $stayIntervalMinutes) {
        $stayTime = clone $currentTime; // 到着時刻をコピー
        $stayTime->add(new DateInterval('PT' . $i . 'M'));
        // 滞在中も少し動く（GPSの揺らぎノイズ）
        $latNoise = (rand(-5, 5) * 0.00001);
        $lonNoise = (rand(-5, 5) * 0.00001);
        $dataPoints[] = sprintf("('%s', %f, %f)", $stayTime->format('Y-m-d H:i:s'), $locations['school']['lat'] + $latNoise, $locations['school']['lon'] + $lonNoise);
    }
    // 1時間経過させる
    $currentTime->add(new DateInterval('PT' . $stayDuration . 'M'));
    // 小学校出発時刻のデータ
    $dataPoints[] = sprintf("('%s', %f, %f)", $currentTime->format('Y-m-d H:i:s'), $locations['school']['lat'], $locations['school']['lon']);

    // --- 4. 小学校 → 公園 (10分かけて移動) ---
    $latStep = ($locations['park']['lat'] - $locations['school']['lat']) / $moveSteps;
    $lonStep = ($locations['park']['lon'] - $locations['school']['lon']) / $moveSteps;
    for ($i = 1; $i <= $moveSteps; $i++) {
        $currentTime->add($moveInterval);
        $lat = $locations['school']['lat'] + $latStep * $i;
        $lon = $locations['school']['lon'] + $lonStep * $i;
        $dataPoints[] = sprintf("('%s', %f, %f)", $currentTime->format('Y-m-d H:i:s'), $lat, $lon);
    }

    // --- 5. 公園（1時間滞在） ---
    for ($i = 0; $i < $stayDuration; $i += $stayIntervalMinutes) {
        $stayTime = clone $currentTime;
        $stayTime->add(new DateInterval('PT' . $i . 'M'));
        $latNoise = (rand(-5, 5) * 0.00001);
        $lonNoise = (rand(-5, 5) * 0.00001);
        $dataPoints[] = sprintf("('%s', %f, %f)", $stayTime->format('Y-m-d H:i:s'), $locations['park']['lat'] + $latNoise, $locations['park']['lon'] + $lonNoise);
    }
    // 1時間経過させる
    $currentTime->add(new DateInterval('PT' . $stayDuration . 'M'));
    // 公園出発時刻のデータ
    $dataPoints[] = sprintf("('%s', %f, %f)", $currentTime->format('Y-m-d H:i:s'), $locations['park']['lat'], $locations['park']['lon']);

    // --- 6. 公園 → 家 (10分かけて移動) ---
    $latStep = ($locations['home']['lat'] - $locations['park']['lat']) / $moveSteps;
    $lonStep = ($locations['home']['lon'] - $locations['park']['lon']) / $moveSteps;
    for ($i = 1; $i <= $moveSteps; $i++) {
        $currentTime->add($moveInterval);
        $lat = $locations['park']['lat'] + $latStep * $i;
        $lon = $locations['park']['lon'] + $lonStep * $i;
        $dataPoints[] = sprintf("('%s', %f, %f)", $currentTime->format('Y-m-d H:i:s'), $lat, $lon);
    }

    // --- 7. 家（1時間滞在） ---
    for ($i = 0; $i < $stayDuration; $i += $stayIntervalMinutes) {
        $stayTime = clone $currentTime;
        $stayTime->add(new DateInterval('PT' . $i . 'M'));
        $latNoise = (rand(-5, 5) * 0.00001);
        $lonNoise = (rand(-5, 5) * 0.00001);
        $dataPoints[] = sprintf("('%s', %f, %f)", $stayTime->format('Y-m-d H:i:s'), $locations['home']['lat'] + $latNoise, $locations['home']['lon'] + $lonNoise);
    }
    // 1時間経過
    $currentTime->add(new DateInterval('PT' . $stayDuration . 'M'));
    // 滞在終了時刻のデータ
    $dataPoints[] = sprintf("('%s', %f, %f)", $currentTime->format('Y-m-d H:i:s'), $locations['home']['lat'], $locations['home']['lon']);

    // --- SQLを構築して実行 ---
    $sql_insert_data = "INSERT INTO db0 (date, latitude, longitude) VALUES \n" . implode(",\n", $dataPoints);

    if ($conn->query($sql_insert_data) === TRUE) {
        echo "<p class='success'>✓ db0テーブルにサンプルデータ（歩行経路）を挿入しました (" . count($dataPoints) . "件)</p>";
    } else {
        echo "<p class='error'>❌ db0データ挿入エラー: " . $conn->error . "</p>";
    }

    // 挿入したデータを簡易表示
    echo "<h2>📊 db0 サンプルデータ（先頭10件）</h2>";
    $result = $conn->query("SELECT * FROM db0 ORDER BY date ASC LIMIT 10");
    if ($result) {
        echo "<table>";
        echo "<tr><th>id</th><th>date</th><th>latitude</th><th>longitude</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['date']) . "</td>";
            echo "<td>" . htmlspecialchars($row['latitude']) . "</td>";
            echo "<td>" . htmlspecialchars($row['longitude']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<p class='error'>❌ サンプルデータ作成中にエラーが発生しました: " . $e->getMessage() . "</p>";
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
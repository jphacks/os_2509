<?php
/**
 * データベース初期化スクリプト
 * テスト環境向けに必要なテーブルを再作成します。
 * UTF-8 (BOMなし)
 */

$servername = "localhost";
$username   = "backhold";
$password   = "backhold";
$dbname     = "back_db1";

echo "<!DOCTYPE html>";
echo "<html lang='ja'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>データベース初期化</title>";
echo "<style>
    :root { color-scheme: light; }
    body {
        font-family: 'Hiragino Sans', 'Noto Sans JP', 'Yu Gothic UI', Meiryo, sans-serif;
        background: #f4f6fb;
        margin: 0;
        padding: 40px 20px 60px;
        color: #223146;
    }
    h1 {
        margin-top: 0;
        font-size: clamp(24px, 4vw, 32px);
        border-bottom: 4px solid #5e8df7;
        padding-bottom: 12px;
        letter-spacing: .05em;
    }
    h2 {
        margin-top: 40px;
        font-size: clamp(18px, 3vw, 24px);
        color: #33445f;
    }
    .steps {
        display: grid;
        gap: 16px;
        margin: 30px 0 40px;
    }
    .step {
        background: #ffffff;
        border-radius: 12px;
        padding: 18px 20px;
        box-shadow: 0 10px 28px rgba(24, 54, 104, 0.12);
        border-inline-start: 6px solid #5e8df7;
    }
    .step strong {
        display: block;
        font-size: 16px;
        margin-bottom: 8px;
    }
    .success {
        color: #1c7430;
        font-weight: 600;
        margin: 6px 0 0;
    }
    .error {
        color: #b91c1c;
        font-weight: 600;
        margin: 6px 0 0;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 18px 0 0;
        background: #ffffff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 12px 24px rgba(24, 54, 104, 0.08);
    }
    th, td {
        border-bottom: 1px solid rgba(34, 49, 70, .08);
        padding: 10px 12px;
        text-align: left;
        font-size: 14px;
    }
    th {
        background: #eef3ff;
        font-weight: 700;
        color: #1f2d47;
    }
    ul {
        margin: 10px 0 0;
        padding-left: 22px;
    }
    code {
        background: rgba(34, 49, 70, .06);
        padding: 3px 6px;
        border-radius: 6px;
        font-size: 90%;
    }
    footer {
        margin-top: 60px;
        text-align: center;
        color: rgba(34, 49, 70, .6);
        font-size: 12px;
        letter-spacing: .08em;
    }
</style>";
echo "</head>";
echo "<body>";
echo "<h1>データベース初期化レポート</h1>";

echo "<div class='steps'>";

$conn = @new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    echo "<div class='step'><strong>ステップ1: MySQLサーバー接続</strong>";
    echo "<p class='error'>接続に失敗しました: " . htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8') . "</p></div>";
    echo "</div></body></html>";
    exit;
}
echo "<div class='step'><strong>ステップ1: MySQLサーバー接続</strong>";
echo "<p class='success'>接続が完了しました。</p></div>";

$sqlCreateDb = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sqlCreateDb) === TRUE) {
    echo "<div class='step'><strong>ステップ2: データベース作成</strong>";
    echo "<p class='success'>データベース <code>$dbname</code> を利用可能にしました。</p></div>";
} else {
    echo "<div class='step'><strong>ステップ2: データベース作成</strong>";
    echo "<p class='error'>エラー: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . "</p></div>";
    echo "</div></body></html>";
    $conn->close();
    exit;
}

if ($conn->select_db($dbname)) {
    $conn->set_charset("utf8mb4");
    echo "<div class='step'><strong>ステップ3: データベース選択</strong>";
    echo "<p class='success'>データベース <code>$dbname</code> を使用します。</p></div>";
} else {
    echo "<div class='step'><strong>ステップ3: データベース選択</strong>";
    echo "<p class='error'>エラー: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . "</p></div>";
    echo "</div></body></html>";
    $conn->close();
    exit;
}

$tablesToDrop = ['db3', 'db2', 'db1_1', 'db1', 'db0', 'db4', 'accounts'];
echo "<div class='step'><strong>ステップ4: 既存テーブルの整理</strong>";
$dropErrors = [];
foreach ($tablesToDrop as $table) {
    if ($conn->query("DROP TABLE IF EXISTS $table") !== TRUE) {
        $dropErrors[] = "$table: " . $conn->error;
    }
}
if (empty($dropErrors)) {
    echo "<p class='success'>旧テーブルを安全にクリアしました。</p>";
} else {
    echo "<p class='error'>一部のテーブルを削除できませんでした:<br>" .
         htmlspecialchars(implode('<br>', $dropErrors), ENT_QUOTES, 'UTF-8') . "</p>";
}
echo "</div>";

$tableDefinitions = [
    'db4' => [
        'title' => 'ステップ5: db4テーブル（アカウント管理）',
        'sql'   => "CREATE TABLE db4 (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL UNIQUE COMMENT '表示名',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
            last_login_at DATETIME NULL COMMENT '最終ログイン日時',
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='アカウント管理テーブル'"
    ],
    'db0' => [
        'title' => 'ステップ6: db0テーブル（位置情報）',
        'sql'   => "CREATE TABLE db0 (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) UNSIGNED NULL COMMENT '利用者ID',
            date DATETIME NOT NULL COMMENT '記録日時',
            latitude DECIMAL(10, 8) NULL COMMENT '緯度',
            longitude DECIMAL(11, 8) NULL COMMENT '経度',
            INDEX idx_user_date (user_id, date),
            INDEX idx_coords (latitude, longitude),
            CONSTRAINT fk_db0_user FOREIGN KEY (user_id) REFERENCES db4(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='位置情報テーブル'"
    ],
    'db1' => [
        'title' => 'ステップ7: db1テーブル（音声テキスト）',
        'sql'   => "CREATE TABLE db1 (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) UNSIGNED NULL COMMENT '利用者ID',
            date DATETIME NOT NULL COMMENT '記録日時',
            soundtext TEXT NULL COMMENT '音声テキスト',
            INDEX idx_user_date (user_id, date),
            CONSTRAINT fk_db1_user FOREIGN KEY (user_id) REFERENCES db4(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='音声テキストテーブル'"
    ],
    'db1_1' => [
        'title' => 'ステップ8: db1_1テーブル（場所メモ）',
        'sql'   => "CREATE TABLE db1_1 (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) UNSIGNED NULL COMMENT '利用者ID',
            date DATETIME NOT NULL COMMENT '記録日時',
            location TEXT NULL COMMENT '場所メモ',
            INDEX idx_user_date (user_id, date),
            CONSTRAINT fk_db11_user FOREIGN KEY (user_id) REFERENCES db4(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='場所テーブル'"
    ],
    'db2' => [
        'title' => 'ステップ9: db2テーブル（要約テキスト）',
        'sql'   => "CREATE TABLE db2 (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) UNSIGNED NULL COMMENT '利用者ID',
            date DATETIME NOT NULL COMMENT '記録日時',
            soundsum TEXT NULL COMMENT '要約テキスト',
            place VARCHAR(255) NULL COMMENT '場所',
            INDEX idx_user_date (user_id, date),
            CONSTRAINT fk_db2_user FOREIGN KEY (user_id) REFERENCES db4(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='要約テーブル'"
    ],
    'db3' => [
        'title' => 'ステップ10: db3テーブル（生成コンテンツ）',
        'sql'   => "CREATE TABLE db3 (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) UNSIGNED NULL COMMENT '利用者ID',
            date DATETIME NOT NULL COMMENT '記録日時',
            sentence TEXT NULL COMMENT '本文',
            place VARCHAR(255) NULL COMMENT '場所',
            image TEXT NULL COMMENT '生成画像URL',
            INDEX idx_user_date (user_id, date),
            CONSTRAINT fk_db3_user FOREIGN KEY (user_id) REFERENCES db4(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='生成コンテンツテーブル'"
    ],
];

foreach ($tableDefinitions as $tableName => $definition) {
    echo "<div class='step'><strong>" . htmlspecialchars($definition['title'], ENT_QUOTES, 'UTF-8') . "</strong>";
    if ($conn->query($definition['sql']) === TRUE) {
        echo "<p class='success'>$tableName テーブルを作成しました。</p>";
    } else {
        echo "<p class='error'>$tableName の作成に失敗しました: " .
             htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . "</p>";
    }
    echo "</div>";
}

echo "</div>";

$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "<h2>作成されたテーブル一覧</h2>";
    echo "<ul>";
    while ($row = $result->fetch_array()) {
        echo "<li>" . htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8') . "</li>";
    }
    echo "</ul>";
}

$tablesToDescribe = [
    'db0' => 'db0テーブル構造（位置情報）',
    'db1' => 'db1テーブル構造（音声テキスト）',
    'db4' => 'db4テーブル構造（アカウント）',
];

foreach ($tablesToDescribe as $table => $heading) {
    $describe = $conn->query("DESCRIBE $table");
    if ($describe) {
        echo "<h2>" . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . "</h2>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th><th>Comment</th></tr>";
        while ($row = $describe->fetch_assoc()) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($row['Field'], ENT_QUOTES, 'UTF-8') . "</strong></td>";
            echo "<td>" . htmlspecialchars($row['Type'], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['Null'], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['Key'], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['Extra'], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['Comment'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

$conn->close();

echo "<div class='step info'>";
echo "<strong>初期化完了メモ</strong>";
echo "<ul>";
echo "<li>データベース: <code>$dbname</code></li>";
echo "<li>アカウント情報は <code>db4</code> に保存されます。</li>";
echo "<li>各データテーブルに <code>user_id</code> を追加し、利用者と紐付けられるようになりました。</li>";
echo "</ul>";
echo "</div>";

echo "<footer>&copy; " . date('Y') . " Connection Diary Setup</footer>";
echo "</body></html>";
?>

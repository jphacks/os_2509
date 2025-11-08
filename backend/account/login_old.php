<?php
declare(strict_types=1);

// エラーをJSON形式で返す設定
error_reporting(E_ALL);
ini_set('display_errors', '0');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'PHPエラーが発生しました',
        'debug' => [
            'error' => $errstr,
            'file' => $errfile,
            'line' => $errline
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => 'Fatal error',
            'debug' => $error
        ], JSON_UNESCAPED_UNICODE);
    }
});

try {
    require_once __DIR__ . '/../common/session.php';
    start_project_session();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'セッション開始エラー',
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POSTメソッドで送信してください。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawName = $_POST['name'] ?? '';
$name = trim((string)$rawName);

if ($name === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => '名前を入力してください。'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($name, 'UTF-8') > 120) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => '名前は120文字以内で入力してください。'], JSON_UNESCAPED_UNICODE);
    exit;
}

// $configPath = __DIR__ . '/home/xs413160/tunagaridiary.com/private/config/config.php';
$configPath = '/home/xs413160/tunagaridiary.com/private/config/config.php';

if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'config.phpが見つかりません',
        'debug' => ['path' => $configPath]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once $configPath;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'config.php読み込みエラー',
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = null;

try {
    $conn = getDbConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'データベース接続エラー',
        'debug' => [
            'error' => $e->getMessage()
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$closeWithError = static function (?mysqli $conn, int $statusCode, string $message): void {
    http_response_code($statusCode);
    echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
    if ($conn instanceof mysqli) {
        $conn->close();
    }
    exit;
};

try {
    $createTableSql = <<<SQL
CREATE TABLE IF NOT EXISTS db4 (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE COMMENT '表示名',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    last_login_at DATETIME NULL COMMENT '最終ログイン日時',
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='アカウント管理テーブル'
SQL;
    $conn->query($createTableSql);

    // 登録済みか確認
    $stmt = $conn->prepare('SELECT id FROM db4 WHERE name = ? LIMIT 1');
    if (!$stmt) {
        $closeWithError($conn, 500, 'アカウント情報の読み込みに失敗しました。');
    }
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $stmt->bind_result($userId);

    if ($stmt->fetch()) {
        $stmt->close();
    } else {
        $stmt->close();
        // 新規作成
        $insert = $conn->prepare('INSERT INTO db4 (name, created_at, updated_at, last_login_at) VALUES (?, NOW(), NOW(), NOW())');
        if (!$insert) {
            $closeWithError($conn, 500, 'アカウントの作成に失敗しました。');
        }
        $insert->bind_param('s', $name);
        if (!$insert->execute()) {
            $insert->close();
            $closeWithError($conn, 500, 'アカウントの作成に失敗しました。');
        }
        $userId = $insert->insert_id;
        $insert->close();
    }

    // 最終ログイン更新
    $update = $conn->prepare('UPDATE db4 SET last_login_at = NOW(), updated_at = NOW() WHERE id = ?');
    if (!$update) {
        $closeWithError($conn, 500, 'ログイン情報の更新に失敗しました。');
    }
    $update->bind_param('i', $userId);
    if (!$update->execute()) {
        $update->close();
        $closeWithError($conn, 500, 'ログイン情報の更新に失敗しました。');
    }
    $update->close();
} catch (mysqli_sql_exception $e) {
    $closeWithError($conn, 500, 'データベース処理でエラーが発生しました: ' . $e->getMessage());
}

$conn->close();

$_SESSION['account_id'] = $userId;
$_SESSION['account_name'] = $name;

echo json_encode([
    'status' => 'success',
    'message' => 'ログインしました。',
    'data' => [
        'id' => $userId,
        'name' => $name,
    ],
], JSON_UNESCAPED_UNICODE);

// $_SESSION['account_id'] = $userId;
// $_SESSION['account_name'] = $name;

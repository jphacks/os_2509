<?php
define('AUTH_GUARD_RESPONSE_TYPE', 'json');
$account = require __DIR__ . '/../../../backend/account/require_login.php';
// データベース接続情報
$servername = "localhost";
$username = "backhold";
$password = "backhold";
$dbname = "back_db1";

// POSTデータの検証
if (!isset($_POST['sound_text']) || empty($_POST['sound_text'])) {
    http_response_code(400);
    die("エラー: sound_textが送信されていません。");
}

$sound_text = $_POST['sound_text'];

// データベース接続
$conn = new mysqli($servername, $username, $password, $dbname);

// 接続チェック
if ($conn->connect_error) {
    http_response_code(500);
    die("エラー: データベース接続失敗: " . $conn->connect_error);
}

// プリペアドステートメントを使用してSQLインジェクションを防止
$sql = "INSERT INTO db1 (date, soundtext) VALUES (NOW(), ?)";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    http_response_code(500);
    die("エラー: SQLステートメントの準備に失敗しました: " . $conn->error);
}

// パラメータをバインド
// 's' は string (文字列) を意味します
$stmt->bind_param("s", $sound_text);

// 実行
if ($stmt->execute()) {
    echo "成功: 音声テキストをdb1に保存しました。ID: " . $stmt->insert_id;
} else {
    http_response_code(500);
    echo "エラー: レコードの挿入に失敗しました: " . $stmt->error;
}

// 接続を閉じる
$stmt->close();
$conn->close();

?>


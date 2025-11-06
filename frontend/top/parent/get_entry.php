<?php
define('AUTH_GUARD_RESPONSE_TYPE', 'json');
$account = require __DIR__ . '/../../../backend/account/require_login.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$servername = "localhost";
$username = "backhold";
$password = "backhold";
$dbname = "back_db1";

// 出力用配列
$response = ['ok' => false, 'message' => '', 'entry' => null];

// パラメータ取得
$entry_id = $_GET['id'] ?? null;
$entry_date = $_GET['date'] ?? null;

if (empty($entry_id) && empty($entry_date)) {
    $response['message'] = 'IDまたは日付が指定されていません。';
    echo json_encode($response);
    exit;
}

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("データベース接続失敗: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    $sql = "";
    $stmt = null;

    if (!empty($entry_id)) {
        // IDで検索
        $sql = "SELECT id, date, sentence, place, image FROM db3 WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $entry_id);

    } else if (!empty($entry_date)) {
        // 日付で検索 (その日の最新のものを1件取得)
        $sql = "SELECT id, date, sentence, place, image FROM db3 WHERE DATE(date) = ? ORDER BY date DESC, id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $entry_date);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $entry = $result->fetch_assoc();
        $response['ok'] = true;
        $response['entry'] = $entry;
    } else {
        $response['message'] = '該当する絵日記が見つかりませんでした。';
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    // エラー時はok:falseとエラーメッセージを返す
    $response['message'] = "サーバーエラー: " . $e->getMessage();
}

// JSONを出力
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>


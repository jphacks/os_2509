<?php
// ヘッダーをJSONに設定
header('Content-Type: application/json');

// エラーレポートを有効にする（デバッグ用）
ini_set('display_errors', 1);
error_reporting(E_ALL);

// データベース接続情報（あなたのスクリプトから拝借）
$servername = "localhost";
$username = "backhold";
$password = "backhold";
$dbname = "back_db1";

// レスポンス用の連想配列
$response = ['status' => 'error', 'message' => '不明なエラー'];

try {
    // 1. データベースに接続
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // 接続チェック
    if ($conn->connect_error) {
        throw new Exception("接続失敗: " . $conn->connect_error);
    }
    
    // 文字コードをutf8mb4に設定
    $conn->set_charset("utf8mb4");

    // 2. POSTデータ（'soundtext'）が送られてきたかチェック
    if (isset($_POST['soundtext']) && !empty($_POST['soundtext'])) {
        
        $soundtext = $_POST['soundtext'];
        $date = date("Y-m-d H:i:s"); // 現在の日時をサーバー側で取得

        // 3. SQLインジェクション対策（プリペアドステートメント）
        // db1テーブルに (date, soundtext) を挿入する準備
        $sql = "INSERT INTO db1 (date, soundtext) VALUES (?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
             throw new Exception("SQL準備エラー: " . $conn->error);
        }

        // 's'はstring（文字列）、2つの変数をバインド
        $stmt->bind_param("ss", $date, $soundtext);

        // 4. SQLの実行
        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'データが正常に保存されました。';
            $response['inserted_id'] = $conn->insert_id; // 保存されたID
        } else {
            throw new Exception("SQL実行エラー: " . $stmt->error);
        }

        // 5. ステートメントを閉じる
        $stmt->close();
        
    } else {
        $response['message'] = 'soundtextデータが送信されていません。';
    }

    // 6. 接続を閉じる
    $conn->close();

} catch (Exception $e) {
    // エラーハンドリング
    http_response_code(500); // サーバーエラー
    $response['message'] = $e->getMessage();
}

// 7. 結果をJSON形式でフロントエンド（JavaScript）に返す
echo json_encode($response);

?>
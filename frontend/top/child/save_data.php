<?php
/**
 * save_data.php
 * 音声テキスト (db1) と位置情報 (db0) をデータベースに保存する
 */

// データベース接続情報（db_setup.php と同じ設定）
$servername = "localhost";
$username = "backhold";
$password = "backhold";
$dbname = "back_db1";

// タイムゾーン設定 (JST: 日本標準時)
date_default_timezone_set('Asia/Tokyo');
$current_datetime = date('Y-m-d H:i:s');

// レスポンスをJSON形式で返す設定
header('Content-Type: application/json; charset=utf-8');

// POSTデータの受け取り
// isset()で確認し、存在しない場合は null を設定
$sound_text = $_POST['sound_text'] ?? null;
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;

// デバッグや応答用の連想配列
$response = [
    'status' => 'error',
    'message' => '初期エラー',
    'received' => [
        'text_received' => !is_null($sound_text),
        'latitude' => $latitude,
        'longitude' => $longitude
    ],
    'saved' => [
        'db0_location' => false,
        'db1_text' => false
    ]
];

try {
    // データベース接続
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("DB接続失敗: " . $conn->connect_error);
    }
    // 文字コード設定
    $conn->set_charset("utf8mb4");

    // トランザクション開始 (両方の保存を保証するため)
    $conn->begin_transaction();
    
    $inserted_db0 = false;
    $inserted_db1 = false;

    // 1. 位置情報を db0 に保存 (緯度・経度が両方送信された場合のみ)
    if ($latitude !== null && $longitude !== null) {
        // プリペアドステートメント
        $stmt0 = $conn->prepare("INSERT INTO db0 (date, latitude, longitude) VALUES (?, ?, ?)");
        if (!$stmt0) {
             throw new Exception("db0 SQL準備エラー: " . $conn->error);
        }
        // "sdd" = String (date), Double (latitude), Double (longitude)
        $stmt0->bind_param("sdd", $current_datetime, $latitude, $longitude);
        
        if ($stmt0->execute()) {
            $inserted_db0 = true;
        } else {
            throw new Exception("db0 実行エラー: " . $stmt0->error);
        }
        $stmt0->close();
    }

    // 2. 音声テキストを db1 に保存 (テキストが送信され、空でない場合)
    if ($sound_text !== null && trim($sound_text) !== '') {
        $stmt1 = $conn->prepare("INSERT INTO db1 (date, soundtext) VALUES (?, ?)");
         if (!$stmt1) {
             throw new Exception("db1 SQL準備エラー: " . $conn->error);
        }
        // "ss" = String (date), String (soundtext)
        $stmt1->bind_param("ss", $current_datetime, $sound_text);
        
        if ($stmt1->execute()) {
            $inserted_db1 = true;
        } else {
            throw new Exception("db1 実行エラー: " . $stmt1->error);
        }
        $stmt1->close();
    }

    // 3. トランザクション完了 (コミット)
    if ($conn->commit()) {
        $response['status'] = 'success';
        $response['message'] = 'データが正常に保存されました。';
        $response['saved']['db0_location'] = $inserted_db0;
        $response['saved']['db1_text'] = $inserted_db1;
    } else {
         throw new Exception("コミットに失敗しました。");
    }

} catch (Exception $e) {
    // エラー発生時はロールバック
    if (isset($conn) && $conn->ping()) {
        $conn->rollback(); 
    }
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();

} finally {
    // 接続を閉じる
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}

// 最終的な応答をJSONで出力
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>
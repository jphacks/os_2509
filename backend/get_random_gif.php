<?php
/**
 * ランダム動物GIF取得API
 * db5テーブルからランダムに1件のGIF URLを返す
 * UTF-8 (BOMなし)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$servername = "localhost";
$username   = "backhold";
$password   = "backhold";
$dbname     = "back_db1";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("データベース接続エラー: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // db5からランダムに1件取得（gif_urlが存在するもののみ）
    $sql = "SELECT id, animal_name, description, gif_url 
            FROM db5 
            WHERE gif_url IS NOT NULL AND gif_url != '' 
            ORDER BY RAND() 
            LIMIT 1";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $row['id'],
                'animal_name' => $row['animal_name'],
                'description' => $row['description'],
                'gif_url' => $row['gif_url']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // データが存在しない場合はデフォルトのヒヨコを返す
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => 0,
                'animal_name' => 'ひよこ',
                'description' => 'たまごから うまれたよ！',
                'gif_url' => null  // nullの場合は絵文字を使用
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
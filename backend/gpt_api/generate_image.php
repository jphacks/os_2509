<?php

// 1. ライブラリと環境変数の読み込み
// --------------------------------------------------
require __DIR__ . '\vendor\autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/env');
$dotenv->load();


// 2. データベース接続情報
// --------------------------------------------------
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "back_db1"; // あなたのデータベース名


// 3. データベースからプロンプトと関連データを取得
// --------------------------------------------------
$prompt = "";
$sourceId = 0;
$sourceDate = '';
$sourceLocation = '';

// データベースに接続
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("データベース接続エラー: " . $conn->connect_error . "\n");
}
echo "データベースに正常に接続しました。\n";

// ---- ステップA: db2から最新のプロンプト(soundsum)と関連情報を取得 ----
$sql_db2 = "SELECT id, date, soundsum FROM db2 ORDER BY id DESC LIMIT 1";
$result_db2 = $conn->query($sql_db2);

if ($result_db2 && $result_db2->num_rows > 0) {
    $row_db2 = $result_db2->fetch_assoc();
    $sourceId = $row_db2["id"];
    $sourceDate = $row_db2["date"];
    $prompt = $row_db2["soundsum"];
    echo "db2テーブルからプロンプトを取得しました (ID: {$sourceId})。\n";
} else {
    $conn->close();
    die("エラー: db2テーブルにデータが見つかりませんでした。\n");
}

// ---- ステップB: 取得したIDを元に、db1から場所(location)を取得 ----
$sql_db1 = "SELECT location FROM db1 WHERE id = ?";
$stmt_db1 = $conn->prepare($sql_db1);
$stmt_db1->bind_param("i", $sourceId);
$stmt_db1->execute();
$result_db1 = $stmt_db1->get_result();
if ($result_db1 && $result_db1->num_rows > 0) {
    $row_db1 = $result_db1->fetch_assoc();
    $sourceLocation = $row_db1['location'];
    echo "db1テーブルから場所を取得しました: {$sourceLocation}\n";
} else {
    $conn->close();
    die("エラー: db1テーブルで対応する場所が見つかりませんでした (ID: {$sourceId})。\n");
}
$stmt_db1->close();


// 4. APIキーの準備とOpenAIクライアントの初期化
// --------------------------------------------------
$apiKey = $_ENV['OPENAI_API_KEY'];
if (empty($apiKey) || $apiKey === "sk-...") {
    $conn->close();
    die("エラー: .envファイルに有効なOPENAI_API_KEYが設定されていません。\n");
}
$client = OpenAI::client($apiKey);


// 5. 画像生成APIの実行
// --------------------------------------------------
$imageData = null; // 画像データ用の変数を初期化
echo "以下のプロンプトで画像を生成します...\n";
echo "====================\n";
echo $prompt . "\n";
echo "====================\n";

try {
    $response = $client->images()->create([
        'model' => 'dall-e-3', 'prompt' => $prompt, 'n' => 1,
        'size' => '960x1280', 
        'quality' => 'standard',
    ]);
    $imageUrl = $response->data[0]->url;
    echo "画像が生成されました！ URL: " . $imageUrl . "\n";

    // 画像データをURLからダウンロード
    $imageData = file_get_contents($imageUrl);
    if ($imageData === false) {
        throw new Exception("画像のダウンロードに失敗しました。");
    }
    echo "画像のダウンロードが完了しました。\n";

} catch (Exception $e) {
    $conn->close();
    die("APIエラーが発生しました: " . $e->getMessage() . "\n");
}


// 6. db3テーブルに画像と関連データを保存
// --------------------------------------------------
if ($imageData !== null) {
    echo "db3テーブルに画像データを保存します...\n";

    // 同じIDが既に存在するか確認
    $check_sql = "SELECT id FROM db3 WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $sourceId);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        echo "ID: {$sourceId} は既にdb3に存在するため、処理をスキップしました。\n";
    } else {
        // プリペアドステートメントでデータを挿入
        $insert_sql = "INSERT INTO db3 (id, date, sentence, place, image) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($insert_sql);
        // bind_paramの最後の"b"はBLOBデータを送ることを意味します
        $null = NULL; // send_long_data用
        $stmt_insert->bind_param("isssb", $sourceId, $sourceDate, $prompt, $sourceLocation, $null);
        // 大きなデータを安全に送信
        $stmt_insert->send_long_data(4, $imageData);
        
        if ($stmt_insert->execute()) {
            echo "db3テーブルへのデータ保存が成功しました！\n";
        } else {
            echo "エラー (db3): " . $stmt_insert->error . "\n";
        }
        $stmt_insert->close();
    }
    $check_stmt->close();
}


// 7. データベース接続を閉じる
// --------------------------------------------------
$conn->close();
echo "全ての処理が完了しました。\n";

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


// 3. db2テーブルから最新のIDとプロンプトを取得
// --------------------------------------------------
$longText = ""; // プロンプト用の変数
$sourceId = 0;  // 更新対象のIDを保存する変数

// データベースに接続
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("データベース接続エラー: " . $conn->connect_error . "\n");
}
echo "データベースに正常に接続しました。\n";

// ---- db2から最新のIDとsoundsumを1件取得 ----
$sql = "SELECT id, soundsum FROM db2 ORDER BY id DESC LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $sourceId = $row["id"];       // IDを取得
    $longText = $row["soundsum"]; // soundsumを$longText変数に格納
    echo "db2テーブルからプロンプトを取得しました！ (ID: {$sourceId})\n";
} else {
    $conn->close();
    die("エラー: db2テーブルにデータが見つかりませんでした。\n");
}
// この時点では接続を閉じない


// 4. APIキーの準備とOpenAIクライアントの初期化
// --------------------------------------------------
$apiKey = $_ENV['OPENAI_API_KEY'];
if (empty($apiKey) || $apiKey === "sk-...") {
    $conn->close();
    die("エラー: .envファイルに有効なOPENAI_API_KEYが設定されていません。\n");
}
$client = OpenAI::client($apiKey);


// 5. AIへの指示の設定
// --------------------------------------------------
$systemInstruction = <<<EOT
あなたは日本の小学生・幼稚園生向けの「絵日記」のアシスタントです。
ユーザーから与えられるのは以下の情報です：
1. 画像生成用のプロンプト（絵の内容）
2. 日付
3. 土地情報

次の手順で文章を作成してください：

1. 画像に描かれた情景を、**幼稚園生が書くような短くやさしい文章**で説明する。
2. 一文は短く、読みやすくする。
3. 言葉遣いは**子どもが話すような表現**にする。
4. 文章は絵日記風に自然につながるようにする。
5. 出力は文章のみ、箇条書きやJSON、コードブロックは使わない。
6. 文章の最後に「たのしかった！」など**ポジティブな感情**を必ず一言添える。
7. 文字数はpythonでカウントしてください。
8. 100字以内厳守で生成する。
9. 日付はいりません．
10. 箇条書きに絶対にしない。
EOT;


// 6. APIの実行と結果の取得
// --------------------------------------------------
$summary = ''; // 生成された文章を格納する変数
echo "絵日記の文章を生成します...\n";

try {
    $response = $client->chat()->create([
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'system', 'content' => $systemInstruction],
            ['role' => 'user', 'content' => $longText],
        ],
    ]);
    $summary = $response->choices[0]->message->content;
    echo "文章が生成されました！\n";
    echo "====================\n";
    echo $summary . "\n";
    echo "====================\n";

} catch (Exception $e) {
    $conn->close();
    die("APIエラーが発生しました: " . $e->getMessage() . "\n");
}


// 7. db3テーブルのsentenceコラムを更新
// --------------------------------------------------
echo "db3テーブルを更新します (対象ID: {$sourceId})...\n";

// プリペアドステートメントで安全にデータを更新
$update_sql = "UPDATE db3 SET sentence = ? WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
// 型を指定: s = string (sentence), i = integer (id)
$update_stmt->bind_param("si", $summary, $sourceId);

if ($update_stmt->execute()) {
    // 実際に更新された行があるか確認
    if ($update_stmt->affected_rows > 0) {
        echo "db3テーブルのsentenceコラムの更新が成功しました！\n";
    } else {
        echo "更新対象のID: {$sourceId} がdb3に見つかりませんでした。\n";
    }
} else {
    echo "エラー (db3 update): " . $update_stmt->error . "\n";
}
$update_stmt->close();


// 8. データベース接続を閉じる
// --------------------------------------------------
$conn->close();
echo "全ての処理が完了しました。\n";
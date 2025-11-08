<?php

// 1. ライブラリと環境変数の読み込み
// --------------------------------------------------
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/env');
$dotenv->load();


// 2. データベース接続情報
// --------------------------------------------------
$servername = "localhost";
$username   = "backhold";
$password   = "backhold";
$dbname     = "back_db1"; // 正しいデータベース名


// 3. データベースから元データを取得
// --------------------------------------------------
$sourceId = 0;
$sourceDate = '';
$sourceSoundtext = '';
$sourceLocation = ''; // locationを格納する変数を追加

// データベースに接続
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("データベース接続エラー: " . $conn->connect_error . "\n");
}
echo "データベースに正常に接続しました。\n";

// ---- ステップA: db1から最新のid, date, soundtextを取得 ----
$sql_db1 = "SELECT id, date, soundtext FROM db1 ORDER BY id DESC LIMIT 1";
$result_db1 = $conn->query($sql_db1);

if ($result_db1 && $result_db1->num_rows > 0) {
    $row_db1 = $result_db1->fetch_assoc();
    $sourceId        = $row_db1["id"];
    $sourceDate      = $row_db1["date"];
    $sourceSoundtext = $row_db1["soundtext"];
    echo "db1テーブルから元データを取得しました (ID: {$sourceId})。\n";
} else {
    $conn->close();
    die("エラー: db1テーブルにデータが見つかりませんでした。\n");
}

// // ---- ステップB: 取得したIDを元に、db1_1から場所(location)を取得 ----
// $sql_db1_1 = "SELECT location FROM db1_1 WHERE id = ?";
// $stmt_db1_1 = $conn->prepare($sql_db1_1);
// $stmt_db1_1->bind_param("i", $sourceId);
// $stmt_db1_1->execute();
// $result_db1_1 = $stmt_db1_1->get_result();
// if ($result_db1_1 && $result_db1_1->num_rows > 0) {
//     $row_db1_1 = $result_db1_1->fetch_assoc();
//     $sourceLocation = $row_db1_1['location'];
//     echo "db1_1テーブルから場所を取得しました: {$sourceLocation}\n";
// } else {
//     $sourceLocation = "不明な場所";
//     echo "警告: db1_1テーブルで対応する場所が見つかりませんでした (ID: {$sourceId})。デフォルト値を設定します。\n";
// }
// $stmt_db1_1->close();

// ---- ステップB: 取得した日付を元に、db1_1から直近の場所(location)を取得 ----
// $sourceDate (例: '2025-10-19 10:30:00') を元に、
// db1_1テーブルからその時刻以前の最新の場所ログを探します。
$sql_db1_1 = "SELECT location FROM db1_1 WHERE date <= ? ORDER BY date DESC LIMIT 1";
$stmt_db1_1 = $conn->prepare($sql_db1_1);
// バインドする変数を $sourceId から $sourceDate に変更
// 型を "i" (integer) から "s" (string) に変更
$stmt_db1_1->bind_param("s", $sourceDate); 
$stmt_db1_1->execute();
$result_db1_1 = $stmt_db1_1->get_result();
if ($result_db1_1 && $result_db1_1->num_rows > 0) {
    $row_db1_1 = $result_db1_1->fetch_assoc();
    $sourceLocation = $row_db1_1['location'];
    echo "db1_1テーブルから場所を取得しました: {$sourceLocation}\n";
} else {
    $sourceLocation = "不明な場所";
    echo "警告: db1_1テーブルで対応する場所が見つかりませんでした (ID: {$sourceId})。デフォルト値を設定します。\n";
}
$stmt_db1_1->close();

// ---- ステップC: APIに渡すためのプロンプト文字列を作成 ----
$userPromptForAI = "日付：" . $sourceDate . "\n" .
                   "場所：" . $sourceLocation . "\n" .
                   "プロンプト：" . $sourceSoundtext;

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
あなたは、小学生の絵日記のイラスト案を考えるアシスタントです。

# 指示
ユーザーが提供する情報（日付、場所、プロンプト）を元に、以下の条件に従ってイラスト生成用のプロンプトを作成してください。

# ユーザーからの情報フォーマット
- 日付：YYYY-MM-DD HH:MM:SS
- 場所：テキスト
- プロンプト：1日の出来事をまとめたテキスト

# 条件
1.  ユーザー提供の「プロンプト」テキストを読み、その日の中で最も絵になり、かつ印象的なイベントを**場所を問わず4つだけ**厳選してください。
2.  選んだイベントを描く際は、その出来事が実際に起こった場所が正確にわかるように背景を描写してください。ユーザー提供の「場所」情報も参考にしてください。
3.  選んだ4つのイベントは、それぞれを1つの区画に描く構成で、必ず**4区画に分割された1枚の画像**としてください。
4.  イラストのタッチは、明るくやさしい色合いの**水彩画風**で、**子どもの絵日記**のような雰囲気でお願いします。
5.  出力形式は、まず選定した4つのイベントを箇条書きで端的に説明し、最後にスタイルを指定する以下の文章を**必ずそのまま**追加してください：
    「明るくやさしい色合い、水彩画風、日本の子どもの絵日記のようなタッチで，それぞれのイベントを1つの区画に描く構成で、必ず4区画の1枚の画像として集約する。」
EOT;


// 6. APIの実行と結果の取得
// --------------------------------------------------
$summaryText = '';
echo "OpenAI APIへのリクエストを開始します...\n";
echo "====================\n";
echo "AIに渡す情報:\n" . $userPromptForAI . "\n";
echo "====================\n";

try {
    $response = $client->chat()->create([
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'system', 'content' => $systemInstruction],
            ['role' => 'user', 'content' => $userPromptForAI],
        ],
    ]);
    $summaryText = $response->choices[0]->message->content;
    echo "プロンプトの生成が完了しました！\n";
    echo "====================\n";
    echo $summaryText . "\n";
    echo "====================\n";

} catch (Exception $e) {
    $conn->close();
    die("APIエラーが発生しました: " . $e->getMessage() . "\n");
}


// 7. db2テーブルに結果を保存
// --------------------------------------------------
echo "db2テーブルに処理結果を保存します...\n";

// ---- ステップA: db2テーブルで次に使用可能な一番若いIDを探す ----
$nextId = 1;

$find_id_sql = "
    SELECT MIN(t1.id) + 1 AS next_available_id
    FROM db2 AS t1
    LEFT JOIN db2 AS t2 ON t1.id + 1 = t2.id
    WHERE t2.id IS NULL
";
$result_id = $conn->query($find_id_sql);
$row_id = $result_id->fetch_assoc();

if ($row_id && $row_id['next_available_id'] !== null) {
    $nextId = $row_id['next_available_id'];
}

echo "次に利用可能なIDは {$nextId} です。\n";

// ---- ステップB: 新しく見つけたIDでデータを挿入 ----
$insert_sql = "INSERT INTO db2 (id, date, soundsum) VALUES (?, ?, ?)";
$insert_stmt = $conn->prepare($insert_sql);

$insert_stmt->bind_param("iss", $nextId, $sourceDate, $summaryText);

if ($insert_stmt->execute()) {
    echo "db2テーブルへのデータ保存が成功しました！ (新しいID: {$nextId})\n";
} else {
    echo "エラー (db2): " . $insert_stmt->error . "\n";
}
$insert_stmt->close();


// 8. データベース接続を閉じる
// --------------------------------------------------
$conn->close();
echo "全ての処理が完了し、データベース接続を閉じました。\n";
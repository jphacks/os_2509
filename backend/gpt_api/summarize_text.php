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
$dbname     = "back_db1"; // 正しいデータベース名


// 3. データベースから元データを取得
// --------------------------------------------------
$sourceId = 0;
$sourceDate = '';
$sourceSoundtext = '';
$sourceLocation = '';

// データベースに接続
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("データベース接続エラー: " . $conn->connect_error . "\n");
}
echo "データベースに正常に接続しました。\n";

// ---- db1からid, date, soundtext, locationをまとめて取得 ----
$sql_db1 = "SELECT id, date, soundtext, location FROM db1 ORDER BY id DESC LIMIT 1";
$result_db1 = $conn->query($sql_db1);

if ($result_db1 && $result_db1->num_rows > 0) {
    $row_db1 = $result_db1->fetch_assoc();
    $sourceId        = $row_db1["id"];
    $sourceDate      = $row_db1["date"];
    $sourceSoundtext = $row_db1["soundtext"];
    $sourceLocation  = $row_db1["location"];
    echo "db1テーブルから元データを取得しました (ID: {$sourceId})。\n";
} else {
    $conn->close();
    die("エラー: db1テーブルにデータが見つかりませんでした。\n");
}


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
ユーザーが提供する1日の出来事をまとめたテキストから、以下の条件に従ってイラスト生成用のプロンプトを作成してください。

# 条件
1.  **イベントの選定**:
    テキスト全体を読み、その日の中で最も絵になり、かつ印象的なイベントを**場所を問わず4つだけ**厳選してください。例えば、「公園で遊んだこと」と「水族館に行ったこと」が混ざっていても、全体の中からベストな4シーンを選びます。

2.  **背景の描写**:
    選んだイベントを描く際は、その出来事が実際に起こった場所（例：公園、水族館、おうち）が正確にわかるように背景を描写してください。

3.  **イラストの構成とスタイル**:
    - 選んだ4つのイベントは、それぞれを1つの区画に描く構成で、必ず**4区画に分割された1枚の画像**としてください。
    - イラストのタッチは、明るくやさしい色合いの**水彩画風**で、**子どもの絵日記**のような雰囲気でお願いします。

4.  **出力形式**:
    - まず、選定した4つのイベントを箇条書きで端的に説明してください。
    - JSONやコードブロックは使用しないでください。
    - 最後に、スタイルを指定する以下の文章を**必ずそのまま**追加してください：
      「明るくやさしい色合い、水彩画風、子どもの絵日記のようなタッチで，それぞれのイベントを1つの区画に描く構成で、必ず4区画の1枚の画像として集約する。」
EOT;


// 6. APIの実行と結果の取得
// --------------------------------------------------
$summaryText = '';
echo "OpenAI APIへのリクエストを開始します...\n";

try {
    $response = $client->chat()->create([
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'system', 'content' => $systemInstruction],
            ['role' => 'user', 'content' => $sourceSoundtext],
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
$nextId = 1; // デフォルト値を1に設定

// このSQLは、連番になっているID (例: 1, 2, 3) の次の番号 (4) か、
// 途中に空きがある場合 (例: 1, 3, 4) のその番号 (2) を見つけます。
$find_id_sql = "
    SELECT MIN(t1.id) + 1 AS next_available_id
    FROM db2 AS t1
    LEFT JOIN db2 AS t2 ON t1.id + 1 = t2.id
    WHERE t2.id IS NULL
";
$result_id = $conn->query($find_id_sql);
$row_id = $result_id->fetch_assoc();

// クエリ結果がNULLでなければ (データが1件以上あれば)、そのIDを使用する
if ($row_id && $row_id['next_available_id'] !== null) {
    $nextId = $row_id['next_available_id'];
}
// クエリ結果がNULL (テーブルが空) の場合は、デフォルト値の1が使われる

echo "次に利用可能なIDは {$nextId} です。\n";

// ---- ステップB: 新しく見つけたIDでデータを挿入 ----
// プリペアドステートメントで安全にデータを挿入
$insert_sql = "INSERT INTO db2 (id, date, soundsum) VALUES (?, ?, ?)";
$insert_stmt = $conn->prepare($insert_sql);

// 型を指定: i = integer, s = string
$insert_stmt->bind_param("iss", $nextId, $sourceDate, $summaryText);

if ($insert_stmt->execute()) {
    echo "db2テーブルへのデータ保存が成功しました！ (新しいID: {$nextId})\n";
} else {
    // 主キー重複などのエラーメッセージを表示
    echo "エラー (db2): " . $insert_stmt->error . "\n";
}
$insert_stmt->close();


// 8. データベース接続を閉じる
// --------------------------------------------------
$conn->close();
echo "全ての処理が完了し、データベース接続を閉じました。\n";
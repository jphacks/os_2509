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


// 3. データベースから最新日付の全データを取得し、テキストを結合
// --------------------------------------------------
$sourceId = 0;
$sourceDate = '';
$sourceSoundtext = '';
$sourceLocation = '';
$targetDate = '';

// データベースに接続
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("データベース接続エラー: " . $conn->connect_error . "\n");
}
echo "データベースに正常に接続しました。\n";

// ---- ステップ1: 最新のレコードの日付を取得 ----
$latest_date_sql = "SELECT date FROM db1 ORDER BY id DESC LIMIT 1";
$result_date = $conn->query($latest_date_sql);

if ($result_date && $result_date->num_rows > 0) {
    $latest_date_row = $result_date->fetch_assoc();
    // 日付部分だけを抽出 (例: '2025-10-18')
    $targetDate = substr($latest_date_row['date'], 0, 10);
    echo "処理対象の日付: {$targetDate}\n";
} else {
    $conn->close();
    die("エラー: db1テーブルにデータが見つかりませんでした。\n");
}

// ---- ステップ2: 取得した日付に合致する全てのレコードを取得 ----
$sql_db1 = "SELECT id, date, soundtext, location FROM db1 WHERE DATE(date) = ? ORDER BY id ASC";
$stmt = $conn->prepare($sql_db1);
$stmt->bind_param("s", $targetDate);
$stmt->execute();
$result_db1 = $stmt->get_result();
echo "{$targetDate}付のデータを{$result_db1->num_rows}件見つけました。\n";

$allSoundtexts = []; // 複数のテキストを格納する配列
$isFirstRow = true;  // ループの最初の行を判定するフラグ

while ($row_db1 = $result_db1->fetch_assoc()) {
    // 取得したレコードの中で最新のID、日付、場所を代表として保存
    // (ORDER BY id ASCなので、最後のループの値が最新になる)
    $sourceId       = $row_db1["id"];
    $sourceDate     = $row_db1["date"];
    $sourceLocation = $row_db1["location"];
    
    // soundtextを配列に追加
    $allSoundtexts[] = $row_db1["soundtext"];
}
$stmt->close();

// ---- ステップ3: 取得した全てのsoundtextを結合 ----
if (!empty($allSoundtexts)) {
    // 配列の要素を改行2つでつなげて、1つのテキストにする
    $sourceSoundtext = implode("\n\n", $allSoundtexts);
    echo "テキストを結合しました (代表ID: {$sourceId})。\n";
} else {
    $conn->close();
    die("エラー: 対象日のデータからテキストを取得できませんでした。\n");
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
    - 各イベントで絵にするのは1つのことだけです．
    - イラストのタッチは、明るくやさしい色合いの**水彩画風**で、**子どもの絵日記**のような雰囲気でお願いします。

4.  **出力形式**:
    - まず、選定した4つのイベントを箇条書きで端的に説明してください。
    - JSONやコードブロックは使用しないでください。
    - 最後に、スタイルを指定する以下の文章を**必ずそのまま**追加してください：
      「明るくやさしい色合い、水彩画風、子どもの絵日記のようなタッチで，それぞれのイベントを1つの区画に描く構成で、必ず4区画の1枚の画像として集約する。」
EOT;


// 6. APIの実行と結果の取得
// --------------------------------------------------
$summaryText = ''; // 要約文用の変数を初期化
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

// 同じIDが既に存在するか確認 (重複挿入を防ぐため)
$check_sql = "SELECT id FROM db2 WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $sourceId);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo "ID: {$sourceId} は既にdb2に存在するため、処理をスキップしました。\n";
} else {
    // プリペアドステートメントで安全にデータを挿入
    $insert_sql = "INSERT INTO db2 (id, date, soundsum, place) VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    // 型を指定: i = integer, s = string
    $insert_stmt->bind_param("isss", $sourceId, $sourceDate, $summaryText, $sourceLocation);

    if ($insert_stmt->execute()) {
        echo "db2テーブルへのデータ保存が成功しました！\n";
    } else {
        echo "エラー (db2): " . $insert_stmt->error . "\n";
    }
    $insert_stmt->close();
}
$check_stmt->close();


// 8. データベース接続を閉じる
// --------------------------------------------------
$conn->close();
echo "全ての処理が完了し、データベース接続を閉じました。\n";
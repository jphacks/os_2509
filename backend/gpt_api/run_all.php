<?php

// =================================================================
// STEP 0: 初期設定 (ライブラリ、DB接続、API準備)
// =================================================================
echo "--- 初期設定を開始します ---\n";

// ライブラリと環境変数の読み込み
require __DIR__ . '\vendor\autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/env');
$dotenv->load();

// データベース接続情報
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "back_db1";

// データベースに接続
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("データベース接続エラー: " . $conn->connect_error . "\n"); }
echo "データベースに正常に接続しました。\n";

// APIキーの準備とOpenAIクライアントの初期化
$apiKey = $_ENV['OPENAI_API_KEY'];
if (empty($apiKey) || $apiKey === "sk-...") { $conn->close(); die("エラー: .envファイルに有効なOPENAI_API_KEYが設定されていません。\n"); }
$client = OpenAI::client($apiKey);
echo "OpenAIクライアントの準備が完了しました。\n";


// =================================================================
// STEP 1: イラストの指示文(プロンプト)を生成する
// =================================================================
echo "\n--- STEP 1: イラスト指示文の生成を開始します ---\n";

$sourceId = 0;
$sourceDate = '';
$userPromptForAI = '';
$targetDate = '';
$sourceLocationForDB3 = '';

// 最新の日付を特定
$latest_date_sql = "SELECT date FROM db1 ORDER BY id DESC LIMIT 1";
$result_date = $conn->query($latest_date_sql);
if ($result_date && $result_date->num_rows > 0) {
    $targetDate = substr($result_date->fetch_assoc()['date'], 0, 10);
    echo "処理対象の日付: {$targetDate}\n";
} else {
    $conn->close(); die("エラー: db1にデータがありません。\n");
}

// ---- ステップA: 該当日の全データを取得 ----
$sql = "SELECT db1.id, db1.date, db1.soundtext, db1_1.location
        FROM db1
        LEFT JOIN db1_1 ON db1.date = db1_1.date
        WHERE DATE(db1.date) = ? AND db1.soundtext IS NOT NULL AND db1.soundtext != ''
        ORDER BY db1.id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $targetDate);
$stmt->execute();
$result = $stmt->get_result();
echo "{$targetDate}付の有効なデータを{$result->num_rows}件見つけました。\n";

// ---- ステップB: locationでデータをグループ化 ----
$groupedData = [];
$currentLocation = null;
$groupIndex = -1;

while ($row = $result->fetch_assoc()) {
    $location = $row['location'] ?? '不明な場所';

    if ($location !== $currentLocation) {
        $currentLocation = $location;
        $groupIndex++;
        $groupedData[$groupIndex] = [
            'location' => $currentLocation,
            'soundtexts' => []
        ];
    }
    $groupedData[$groupIndex]['soundtexts'][] = $row['soundtext'];

    $sourceId   = $row["id"];
    $sourceDate = $row["date"];
    $sourceLocationForDB3 = $currentLocation;
}
$stmt->close();

// ---- ステップC: グループ化したデータからAIに渡すプロンプトを作成 ----
$promptParts = [];
foreach ($groupedData as $group) {
    $combinedText = implode(" ", $group['soundtexts']);
    $promptParts[] = "場所：" . $group['location'] . "\nプロンプト：" . $combinedText;
}

if (!empty($promptParts)) {
    $userPromptForAI = implode("\n\n", $promptParts);
    echo "AIに渡すテキストを作成しました (代表ID: {$sourceId})。\n";

    // ★★★ 追加箇所: 結合した文章をコンソールに出力 ★★★
    echo "==================== AIに渡す結合後テキスト ====================\n";
    echo $userPromptForAI . "\n";
    echo "=============================================================\n";

} else {
    $conn->close(); die("エラー: 有効なデータが見つかりませんでした。\n");
}

// ---- ステップD: AIに指示文の生成を依頼 ----
$systemInstructionForPrompt = <<<EOT
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
EOT;;

$imagePrompt = '';
try {
    $response = $client->chat()->create([ 'model' => 'gpt-4', 'messages' => [
            ['role' => 'system', 'content' => $systemInstructionForPrompt],
            ['role' => 'user', 'content' => $userPromptForAI]
    ]]);
    $imagePrompt = $response->choices[0]->message->content;
    echo "イラスト指示文の生成が完了しました。\n";
    echo "==================== 生成された指示文 ====================\n";
    echo $imagePrompt . "\n";
    echo "========================================================\n";
} catch (Exception $e) {
    $conn->close(); die("APIエラー (STEP 1): " . $e->getMessage() . "\n");
}

// ---- ステップE: 生成された指示文をdb2に保存 ----
$nextId_db2 = 1;
$find_id_sql_db2 = "SELECT MAX(id) as max_id FROM db2";
$result_id_db2 = $conn->query($find_id_sql_db2);
if ($result_id_db2) { $nextId_db2 = ($result_id_db2->fetch_assoc()['max_id'] ?? 0) + 1; }

$insert_sql_db2 = "INSERT INTO db2 (id, date, soundsum) VALUES (?, ?, ?)";
$insert_stmt_db2 = $conn->prepare($insert_sql_db2);
$insert_stmt_db2->bind_param("iss", $nextId_db2, $sourceDate, $imagePrompt);
if ($insert_stmt_db2->execute()) {
    echo "db2テーブルへの保存が成功しました (ID: {$nextId_db2})。\n";
}
$insert_stmt_db2->close();


// =================================================================
// STEP 2: 画像を生成する
// =================================================================
echo "\n--- STEP 2: 画像の生成を開始します ---\n";
$imageData = null;
try {
    $response = $client->images()->create([
        'model' => 'dall-e-3', 'prompt' => $imagePrompt, 'n' => 1,
        'size' => '1024x1024', 'quality' => 'standard',
    ]);
    $imageUrl = $response->data[0]->url;
    echo "画像が生成されました！\n";
    echo "画像URL: " . $imageUrl . "\n";
    
    $imageData = file_get_contents($imageUrl);
    if ($imageData === false) { throw new Exception("画像のダウンロードに失敗。"); }
    echo "画像のダウンロードが完了しました。\n";
} catch (Exception $e) {
    $conn->close(); die("APIエラー (STEP 2): " . $e->getMessage() . "\n");
}


// =================================================================
// STEP 3: 絵日記の文章を生成する
// =================================================================
echo "\n--- STEP 3: 絵日記の文章生成を開始します ---\n";
$diarySentence = '';
$systemInstructionForSentence = <<<EOT
あなたは日本の小学生・幼稚園生向けの「絵日記」のアシスタントです。
ユーザーから与えられるのは以下の情報です：
1. 画像生成用のプロンプト（絵の内容）
2. 日付
3. 土地情報

次の条件で文章を作成してください：
条件は必ず守るようにしてください

1. 画像に描かれた情景を、**幼稚園生が書くような短くやさしい文章**で説明する。
2. 一文は短く、読みやすくする。
3. 言葉遣いは**子どもが話すような表現**にする。
4. 文章は絵日記風に自然につながるようにする。
5. 出力は文章のみ、箇条書きやJSON、コードブロックは使わない。
6. 文章の最後に「たのしかった！」など**ポジティブな感情**を必ず一言添える。
7. 最後にpythonでカウントした文字数を記載ください。
8. 120字以内厳守で生成する。
9. 日付はいりません．
10. 箇条書きに絶対にしない。
11. 句読点や改行も文字数に含める
12. 改行は絶対にしないでください
EOT;

try {
    $response = $client->chat()->create([
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'system', 'content' => $systemInstructionForSentence],
            ['role' => 'user', 'content' => $imagePrompt],
        ],
    ]);
    $diarySentence = $response->choices[0]->message->content;
    echo "文章が生成されました！\n";
    echo "==================== 生成された文章 ====================\n";
    echo $diarySentence . "\n";
    echo "======================================================\n";
} catch (Exception $e) {
    $conn->close(); die("APIエラー (STEP 3): " . $e->getMessage() . "\n");
}


// =================================================================
// STEP 4: 最終結果をdb3に保存する
// =================================================================
if ($imageData !== null && $diarySentence !== '') {
    echo "\n--- STEP 4: 最終結果をdb3に保存します ---\n";

    $check_sql_db3 = "SELECT id FROM db3 WHERE id = ?";
    $check_stmt_db3 = $conn->prepare($check_sql_db3);
    $check_stmt_db3->bind_param("i", $sourceId);
    $check_stmt_db3->execute();
    if ($check_stmt_db3->get_result()->num_rows > 0) {
        echo "ID: {$sourceId} は既にdb3に存在するため、スキップしました。\n";
    } else {
        $insert_sql_db3 = "INSERT INTO db3 (id, date, sentence, place, image) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert_db3 = $conn->prepare($insert_sql_db3);
        $null = NULL;
        $stmt_insert_db3->bind_param("isssb", $sourceId, $sourceDate, $diarySentence, $sourceLocationForDB3, $null);
        $stmt_insert_db3->send_long_data(4, $imageData);
        if ($stmt_insert_db3->execute()) {
            echo "db3テーブルへのデータ保存が成功しました！ (ID: {$sourceId})\n";
        } else {
            echo "エラー (db3): " . $stmt_insert_db3->error . "\n";
        }
        $stmt_insert_db3->close();
    }
    $check_stmt_db3->close();
}


// =================================================================
// FINAL STEP: 終了処理
// =================================================================
$conn->close();
echo "\n全ての処理が完了しました。\n";
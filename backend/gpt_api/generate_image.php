<?php

// Composerでインストールしたライブラリを読み込む
require __DIR__ . '/vendor/autoload.php';

// .envファイルから環境変数を読み込む
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// .envファイルからAPIキーを取得
$apiKey = $_ENV['OPENAI_API_KEY'];
if (empty($apiKey) || $apiKey === "sk-...") {
    die("エラー: .envファイルに有効なOPENAI_API_KEYが設定されていません。\n");
}

// OpenAIクライアントを初期化
$client = OpenAI::client($apiKey);

// ★★★ ここに生成したい画像の説明を自由に入力してください ★★★
$prompt = "1. 朝、縦列で並びみんなに「おはよう！」と言った場面
2. 運動場で先生とおにごっこをして遊んでいる場面
3. お昼、ふかろからおにぎりを出して食べたり友達と分け合った場面
4. 飛行機の絵を書いて先生からほめられた場面と、帰り道でみんなと「さようなら！」と挨拶を交わす場面

背景には、幼稚園の風景を入れます。それぞれの子供たちはかわいらしい制服を着ています。朝と帰りの風景では、幼稚園の建物や緑豊かな運動場、遊具などを描きましょう。遊んでいる場面では、活発に動き回る子供たちや笑顔の先生を、そしてお昼は楽しくお弁当を食べる子供たちとおにぎり、その周りにお弁当の中身を描きます。絵を描いている場面では、子供たちが楽しそうに色とりどりのクレヨンで絵を描く様子と、黄色の飛行機の絵、そして先生が微笑みながらほめる様子を描きます。

明るくやさしい色合い、水彩画風、子どもの絵日記のようなタッチで，それぞれのイベントを**1つの区画に描く**構成で、必ず**4区画の1枚の画像**として集約する。また，画像内に文字はいりません．";

echo "「{$prompt}」の画像を生成します...\n";

try {
    // 画像生成APIを呼び出す
    $response = $client->images()->create([
        'model' => 'dall-e-3',
        'prompt' => $prompt,
        'n' => 1,
        'size' => '1024x1024',
        'quality' => 'standard',
    ]);

    // 生成された画像のURLを取得
    $imageUrl = $response->data[0]->url;
    echo "画像が生成されました！\n";
    echo "URL: " . $imageUrl . "\n";

    // 画像データをURLから取得
    $imageData = file_get_contents($imageUrl);
    if ($imageData === false) {
        throw new Exception("画像のダウンロードに失敗しました。");
    }

    // ファイル名を生成 (例: image_20251018_033800.png)
    $timestamp = date("Ymd_His");
    $fileName = "generated_image_{$timestamp}.png";

    // ファイルに保存
    file_put_contents($fileName, $imageData);
    echo "画像を {$fileName} として保存しました。\n";

} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
}
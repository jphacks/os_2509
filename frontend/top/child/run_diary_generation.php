<?php
/**
 * run_diary_generation.php
 *
 * ブラウザからのリクエストを即座に返し（タイムアウト防止）、
 * サーバー側で2つの重い処理 (get_place, generate_diary) を順番に実行する。
 */

// (1) ブラウザに「処理を開始しました」と即座に応答
// これにより、ブラウザはタイムアウトせず、ユーザーを待たせません。
ob_start();
echo json_encode(['status' => 'processing_started']);
header('Connection: close');
header('Content-Length: ' . ob_get_length());
ob_end_flush();
ob_flush();
flush();

// (2) ここから下の処理は、ブラウザが応答を受け取った *後* に
// サーバー側で実行されます。

// --- 実行設定 ---
$baseDir = __DIR__;
$logFile = $baseDir . '/_process_diary.log'; // 実行ログを残すファイル
$getPlaceScript = $baseDir . '../../../backend/get_place/get_place.php';
$generateDiaryScript = $baseDir . '../../../backend/gpt_api/run_all.php';

// ログの初期化
file_put_contents($logFile, "--- [START] 日記生成プロセス開始: " . date('Y-m-d H:i:s') . " ---\n");

// --- 処理 1: get_place.php を実行 ---
// shell_exec は、スクリプトが完了するまで *待ちます*。
// '2>&1' は、エラー出力(stderr)もログに含めるためのおまじないです。
file_put_contents($logFile, "[INFO] 実行中: get_place.php\n", FILE_APPEND);
$output1 = shell_exec("php {$getPlaceScript} 2>&1");
file_put_contents($logFile, "[OUTPUT] get_place.php:\n" . $output1 . "\n", FILE_APPEND);

// --- 処理 2: generate_diary_openai.php を実行 ---
// 上の get_place.php が完了した後、こちらが実行されます。
file_put_contents($logFile, "[INFO] 実行中: generate_diary_openai.php\n", FILE_APPEND);
$output2 = shell_exec("php {$generateDiaryScript} 2>&1");
file_put_contents($logFile, "[OUTPUT] generate_diary_openai.php:\n" . $output2 . "\n", FILE_APPEND);

file_put_contents($logFile, "--- [END] 全てのプロセスが完了: " . date('Y-m-d H:i:s') . " ---\n", FILE_APPEND);

?>






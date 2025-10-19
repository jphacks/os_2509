<?php
/**
 * run_diary_generation.php
 *
 * 各スクリプトの *自身のディレクトリ* に 'cd' してから、
 * スクリプト本体を実行する。
 */

// (1) ブラウザに「処理を開始しました」と即座に応答
ob_start();
echo json_encode(['status' => 'processing_started']);
header('Connection: close');
header('Content-Length: '.ob_get_length());
ob_end_flush();
ob_flush();
flush();

// (2) ここから下の処理は、サーバー側で実行されます。


// --- 実行設定 ---
$php_executable_path = 'C:\xampp\php\php.exe'; 
$projectRoot = dirname(__DIR__, 3); 
$logFile = __DIR__ . '/_process_diary.log'; 

// ★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★
// ↓↓↓↓↓↓ 修正箇所 ↓↓↓↓↓↓
// ★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★

// 実行したいスクリプトの「親ディレクトリ」と「ファイル名」
$getPlaceDir   = $projectRoot . '/backend/get_place';
$getPlaceFile  = 'get_place.php';

$generateDiaryDir  = $projectRoot . '/backend/gpt_api';
$generateDiaryFile = 'run_all.php'; // 以前のログから 'run_all.php' と推測

// ★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★
// ↑↑↑↑↑↑ 修正箇所 ↑↑↑↑↑↑
// ★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★


// ログの初期化
file_put_contents($logFile, "--- [START] 日記生成プロセス開始: " . date('Y-m-d H:i:s') . " ---\n");
file_put_contents($logFile, "[INFO] プロジェクトルート: {$projectRoot}\n", FILE_APPEND);


// --- 処理 1: get_place.php を実行 ---
// "cd /d ... (スクリプトの親フォルダ)" && php (スクリプトファイル名)
$command1 = "cd /d \"{$getPlaceDir}\" && {$php_executable_path} {$getPlaceFile} 2>&1";
file_put_contents($logFile, "[INFO] 実行中 (CMD1): {$command1}\n", FILE_APPEND);
$output1 = shell_exec($command1);
file_put_contents($logFile, "[OUTPUT] get_place.php:\n" . $output1 . "\n", FILE_APPEND);


// --- 処理 2: generate_diary_openai.php を実行 ---
$command2 = "cd /d \"{$generateDiaryDir}\" && {$php_executable_path} {$generateDiaryFile} 2>&1";
file_put_contents($logFile, "[INFO] 実行中 (CMD2): {$command2}\n", FILE_APPEND);
$output2 = shell_exec($command2);
file_put_contents($logFile, "[OUTPUT] generate_diary_openai.php:\n" . $output2 . "\n", FILE_APPEND);


file_put_contents($logFile, "--- [END] 全てのプロセスが完了: " . date('Y-m-d H:i:s') . " ---\n", FILE_APPEND);

?>
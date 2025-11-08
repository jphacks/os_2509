<?php
declare(strict_types=1);

define('AUTH_GUARD_RESPONSE_TYPE', 'json');
$account = require __DIR__ . '/../../../backend/account/require_login.php';

mb_internal_encoding('UTF-8');

const DIARY_PROCESS_LOG_FILE = __DIR__ . '/_process_diary.log';

function initDiaryLog(): void
{
    $header = sprintf('=== 実行開始 %s ===', date('Y-m-d H:i:s'));
    $bytes = file_put_contents(
        DIARY_PROCESS_LOG_FILE,
        $header . PHP_EOL,
        LOCK_EX
    );
    if ($bytes === false) {
        throw new RuntimeException('ログファイルを初期化できません: ' . DIARY_PROCESS_LOG_FILE);
    }
}

function diaryLog(string $level, string $message, array $context = []): void
{
    $timestamp = date('Y-m-d H:i:s');
    $line = sprintf('[%s][%s] %s', $timestamp, $level, $message);
    if (!empty($context)) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $bytes = file_put_contents(
        DIARY_PROCESS_LOG_FILE,
        $line . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
    if ($bytes === false) {
        throw new RuntimeException('ログファイルに書き込めません: ' . DIARY_PROCESS_LOG_FILE);
    }
}

function summarizeCommandOutput(array $lines, int $maxLength = 4000): string
{
    if (empty($lines)) {
        return '';
    }
    $text = implode(PHP_EOL, $lines);
    if (mb_strlen($text, 'UTF-8') > $maxLength) {
        return mb_substr($text, 0, $maxLength, 'UTF-8') . ' ...(truncated)';
    }
    return $text;
}

function runDiaryCommand(string $label, string $command, ?string $debugId = null): void
{
    diaryLog('INFO', "{$label} を実行します", [
        'command' => $command,
        'debug_id' => $debugId,
    ]);

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    $summary = summarizeCommandOutput($output);
    if ($summary !== '') {
        diaryLog('DEBUG', "{$label} の出力概要", [
            'output' => $summary,
            'debug_id' => $debugId,
        ]);
    } else {
        diaryLog('DEBUG', "{$label} の出力は空でした", [
            'debug_id' => $debugId,
        ]);
    }

    diaryLog('INFO', "{$label} の実行終了", [
        'exit_code' => $exitCode,
        'debug_id' => $debugId,
    ]);

    if ($exitCode !== 0) {
        throw new RuntimeException("{$label} が異常終了しました (exit_code={$exitCode})");
    }
}

function parseTriggerPayload(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $rawBody = file_get_contents('php://input') ?: '';
    $payload = $_POST;

    if ($rawBody !== '' && stripos($contentType, 'application/json') !== false) {
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            $payload = array_merge($payload, $decoded);
        }
    }

    return [$payload, $rawBody, $contentType];
}

[$triggerPayload, $rawBody, $contentType] = parseTriggerPayload();
$debugId = $triggerPayload['debug_id'] ?? null;

try {
    initDiaryLog();
} catch (Throwable $e) {
    // ログ初期化に失敗した場合でも処理だけは継続しつつ通知
    error_log('run_diary_generation.php: ログ初期化に失敗しました: ' . $e->getMessage());
}

diaryLog('INFO', '絵日記生成トリガーを受信しました', [
    'user_id' => (int)$account['id'],
    'content_type' => $contentType,
    'raw_length' => strlen($rawBody),
    'payload' => $triggerPayload,
    'debug_id' => $debugId,
]);

ob_start();
echo json_encode(['status' => 'processing_started']);
header('Connection: close');
header('Content-Length: ' . (string)ob_get_length());
ob_end_flush();
ob_flush();
flush();

// Xserver (Linux) 用の設定
$phpExecutablePath = '/usr/bin/php8.3';  // Linux用のPHPパス
$projectRoot = dirname(__DIR__, 3);

$getPlaceDir   = $projectRoot . '/backend/get_place';
$getPlaceFile  = 'get_place.php';

$generateDiaryDir  = $projectRoot . '/backend/gpt_api';
$generateDiaryFile = 'run_all.php';

try {
    diaryLog('INFO', 'バックグラウンド処理を開始します', [
        'php_executable' => $phpExecutablePath,
        'project_root' => $projectRoot,
        'debug_id' => $debugId,
    ]);

    if (!is_file($phpExecutablePath)) {
        throw new RuntimeException('PHP実行ファイルが見つかりません: ' . $phpExecutablePath);
    }
    if (!is_dir($getPlaceDir) || !is_file($getPlaceDir . '/' . $getPlaceFile)) {
        throw new RuntimeException('get_place.php が見つかりません: ' . $getPlaceDir);
    }
    if (!is_dir($generateDiaryDir) || !is_file($generateDiaryDir . '/' . $generateDiaryFile)) {
        throw new RuntimeException('run_all.php が見つかりません: ' . $generateDiaryDir);
    }

    // Linux用のコマンド（cd /d は不要）
    $command1 = 'cd "' . $getPlaceDir . '" && "' . $phpExecutablePath . '" "' . $getPlaceFile . '" 2>&1';
    runDiaryCommand('get_place.php', $command1, $debugId);

    $command2 = 'cd "' . $generateDiaryDir . '" && "' . $phpExecutablePath . '" "' . $generateDiaryFile . '" 2>&1';
    runDiaryCommand('run_all.php', $command2, $debugId);

    diaryLog('INFO', '全てのプロセスが正常に完了しました', [
        'debug_id' => $debugId,
    ]);
} catch (Throwable $e) {
    $context = [
        'message' => $e->getMessage(),
        'file' => $e->getFile() . ':' . $e->getLine(),
        'debug_id' => $debugId,
    ];

    try {
        diaryLog('ERROR', 'バックグラウンド処理中に例外が発生しました', $context);
    } catch (Throwable $logFailure) {
        error_log('run_diary_generation.php: ログ出力に失敗しました: ' . $logFailure->getMessage());
    }
}
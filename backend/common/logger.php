<?php
declare(strict_types=1);

/**
 * ログを記録する関数
 */
function app_log(string $message, array $data = []): void
{
    $logDir = '/home/xs413160/tunagaridiary.com/private/logs';
    
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/login_' . date('Y-m-d') . '.log';
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $sessionId = session_id() ?: 'no-session';
    
    $isMobile = preg_match('/(iPhone|iPad|Android|Mobile)/i', $userAgent) ? '[MOBILE]' : '[DESKTOP]';
    
    $dataStr = !empty($data) ? ' ' . json_encode($data, JSON_UNESCAPED_UNICODE) : '';
    
    $logLine = sprintf(
        "%s %s [%s] [IP:%s] [SID:%s] %s%s\n",
        $timestamp,
        $isMobile,
        $_SERVER['REQUEST_URI'] ?? '/',
        $ip,
        substr($sessionId, 0, 8),
        $message,
        $dataStr
    );
    
    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}
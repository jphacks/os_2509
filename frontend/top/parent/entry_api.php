<?php
/**
 * entry_api.php
 *
 * - JSON: id/date で絞ったエントリー情報を返却
 * - mode=proxy&id=... : 画像キャッシュを介して日記の画像を返却
 *
 * 出力:
 * - UTF-8
 * - 余計な空白は echo しない
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
define('AUTH_GUARD_RESPONSE_TYPE', 'json');
$account = require __DIR__ . '/../../../backend/account/require_login.php';
$userId = (int)($account['id'] ?? 0);
if ($userId <= 0) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'ユーザー情報を取得できませんでした'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mode = $_GET['mode'] ?? null;

if ($mode === 'proxy') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'id required';
        exit;
    }

    $mysqli = new mysqli('localhost', 'backhold', 'backhold', 'back_db1');
    if ($mysqli->connect_error) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'DB接続失敗: ' . $mysqli->connect_error;
        exit;
    }
    $mysqli->set_charset('utf8mb4');

    $stmt = $mysqli->prepare('SELECT image FROM db3 WHERE id = ? AND user_id = ? LIMIT 1');
    if (!$stmt) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ステートメント準備に失敗しました: ' . $mysqli->error;
        $mysqli->close();
        exit;
    }

    $stmt->bind_param('ii', $id, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $mysqli->close();

    $imageUrl = $row['image'] ?? null;
    if (!$imageUrl) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'image url not found';
        exit;
    }

    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    $metaFile = sprintf('%s/img_%d.json', $cacheDir, $id);
    $binFile  = sprintf('%s/img_%d.bin', $cacheDir, $id);

    $mime = 'image/jpeg';
    $cacheValid = false;
    if (is_file($binFile) && is_file($metaFile)) {
        $meta = json_decode(@file_get_contents($metaFile), true) ?: [];
        if (($meta['user_id'] ?? null) === $userId && ($meta['from'] ?? null) === $imageUrl) {
            $mime = $meta['mime'] ?? $mime;
            $cacheValid = true;
        } else {
            @unlink($binFile);
            @unlink($metaFile);
        }
    }

    if ($cacheValid) {
        $mtime = filemtime($binFile);
        $etag = '"' . sha1($mtime . filesize($binFile)) . '"';
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('Cache-Control: public, max-age=31536000, immutable');

        $ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
        $ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
        if ($ifNoneMatch === $etag || ($ifModifiedSince && $ifModifiedSince >= $mtime)) {
            header('HTTP/1.1 304 Not Modified');
            exit;
        }

        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($binFile));
        readfile($binFile);
        exit;
    }

    $bin = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($imageUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'DiaryImageProxy/1.1'
        ]);
        $bin = curl_exec($ch);
        $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        if ($ct) {
            $mime = $ct;
        }
        curl_close($ch);
    }

    if (!$bin) {
        $ctx = stream_context_create([
            'http'  => ['timeout' => 10],
            'https' => ['timeout' => 10],
        ]);
        $bin = @file_get_contents($imageUrl, false, $ctx);
    }

    if (!$bin) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'image fetch failed';
        exit;
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected = finfo_buffer($finfo, $bin);
        finfo_close($finfo);
        if ($detected) {
            $mime = $detected;
        }
    }

    @file_put_contents($binFile, $bin);
    @file_put_contents($metaFile, json_encode([
        'mime'     => $mime,
        'from'     => $imageUrl,
        'user_id'  => $userId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: ' . strlen($bin));
    echo $bin;
    exit;
}

while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$mysqli = new mysqli('localhost', 'backhold', 'backhold', 'back_db1');
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB接続失敗: ' . $mysqli->connect_error], JSON_UNESCAPED_UNICODE);
    exit;
}
$mysqli->set_charset('utf8mb4');

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$date = isset($_GET['date']) ? trim($_GET['date']) : '';
if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = '';
}

if ($id > 0) {
    $stmt = $mysqli->prepare('SELECT id, DATE_FORMAT(`date`, "%Y-%m-%d") AS day, sentence, place, image FROM db3 WHERE id = ? AND user_id = ? LIMIT 1');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'ステートメント準備に失敗しました: ' . $mysqli->error], JSON_UNESCAPED_UNICODE);
        $mysqli->close();
        exit;
    }
    $stmt->bind_param('ii', $id, $userId);
} elseif ($date !== '') {
    $stmt = $mysqli->prepare('SELECT id, DATE_FORMAT(`date`, "%Y-%m-%d") AS day, sentence, place, image FROM db3 WHERE user_id = ? AND DATE(`date`) = ? ORDER BY id ASC LIMIT 1');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'ステートメント準備に失敗しました: ' . $mysqli->error], JSON_UNESCAPED_UNICODE);
        $mysqli->close();
        exit;
    }
    $stmt->bind_param('is', $userId, $date);
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id または date を指定してください'], JSON_UNESCAPED_UNICODE);
    $mysqli->close();
    exit;
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SQL実行失敗: ' . $stmt->error], JSON_UNESCAPED_UNICODE);
    $stmt->close();
    $mysqli->close();
    exit;
}

$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();
$mysqli->close();

if (!$row) {
    echo json_encode([
        'ok' => true,
        'id' => null,
        'date' => ($date !== '' ? $date : null),
        'sentence' => null,
        'image_url' => null,
        'image_proxy' => null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$imageUrl = (is_string($row['image']) && $row['image'] !== '') ? $row['image'] : null;
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$imageProxy = ($imageUrl && isset($row['id']))
    ? sprintf('%s%s%s/entry_api.php?mode=proxy&id=%d', $scheme, $host, $dir, (int)$row['id'])
    : null;

echo json_encode([
    'ok' => true,
    'id' => (int)$row['id'],
    'date' => $row['day'] ?? null,
    'sentence' => $row['sentence'] ?? null,
    'image_url' => $imageUrl,
    'image_proxy' => $imageProxy,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;

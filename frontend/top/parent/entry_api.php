<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
/**
 * entry_api.php
 * - JSON: id/date で日記1件を返す
 * - mode=proxy&id=... : ローカルにキャッシュした画像を返す（強キャッシュ）
 *   ※初回だけimageのURL（DBのimage列に格納されたURL）から取り寄せ→/cache/ に保存
 *     以後はローカルから高速配信（SAS期限切れでもOK）
 *
 * 置き場所:
 *   C:\xampp\htdocs\os_2509\frontend\top\parent\entry_api.php
 *
 * 注意:
 *   このファイルはBOMなしUTF-8で保存。先頭に空行/空白を入れないこと。
 */

/* ========== 画像プロキシ: 先に処理（ヘッダ競合回避） ========== */
$mode = isset($_GET['mode']) ? $_GET['mode'] : null;
if ($mode === 'proxy') {
  // 必要情報のみで動くよう、ここではDB接続は後で行う
  $id = isset($_GET['id']) ? intval($_GET['id']) : null;
  if ($id === null) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "id required";
    exit;
  }

  $cacheDir = __DIR__ . '/cache';
  if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
  $metaFile = $cacheDir . "/img_{$id}.json";
  $binFile  = $cacheDir . "/img_{$id}.bin";

  // 既存キャッシュなら即返す
  if (is_file($binFile) && is_file($metaFile)) {
    $meta = json_decode(@file_get_contents($metaFile), true) ?: [];
    $mime = isset($meta['mime']) ? $meta['mime'] : 'image/jpeg';
    $mtime = filemtime($binFile);

    $etag = '"' . sha1($mtime . filesize($binFile)) . '"';
    header('ETag: '.$etag);
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT');

    if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) ||
        (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime)) {
      header('HTTP/1.1 304 Not Modified');
      exit;
    }

    header('Content-Type: '.$mime);
    header('Cache-Control: public, max-age=31536000, immutable'); // 1年
    header('Content-Length: '.filesize($binFile));
    readfile($binFile);
    exit;
  }

  // DB接続してURLを取得
  $mysqli = new mysqli("localhost", "backhold", "backhold", "back_db1");
  if ($mysqli->connect_error) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'DB接続失敗: '.$mysqli->connect_error;
    exit;
  }

  $stmt = $mysqli->prepare("SELECT image FROM db3 WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $id);
  if (!$stmt->execute()) {
    $stmt->close(); $mysqli->close();
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "SQL error";
    exit;
  }
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();
  $mysqli->close();

  $image_url = null;
  if ($row && isset($row['image']) && $row['image'] !== null) {
    if (is_string($row['image'])) $image_url = $row['image'];
  }
  if (!$image_url) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "image url not found";
    exit;
  }

  // 取り寄せ（curl優先→file_get_contents フォールバック）
  $bin = null; $mime = 'image/png';
  if (function_exists('curl_init')) {
    $ch = curl_init($image_url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT => 10,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_USERAGENT => 'DiaryImageProxy/1.0'
    ]);
    $bin = curl_exec($ch);
    $ct  = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    if ($ct) $mime = $ct;
    curl_close($ch);
  }
  if ($bin === false || $bin === null) {
    $ctx = stream_context_create(['http'=>['timeout'=>10], 'https'=>['timeout'=>10]]);
    $bin = @file_get_contents($image_url, false, $ctx);
  }
  if ($bin === false || $bin === null) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    echo "image fetch failed";
    exit;
  }

  // MIME推定
  if (function_exists('finfo_open')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $m = $finfo->buffer($bin);
    if ($m) $mime = $m;
  }

  // 保存
  @file_put_contents($binFile, $bin);
  @file_put_contents($metaFile, json_encode(['mime'=>$mime, 'from'=>$image_url], JSON_UNESCAPED_UNICODE));

  header('Content-Type: '.$mime);
  header('Cache-Control: public, max-age=31536000, immutable');
  header('Content-Length: '.strlen($bin));
  echo $bin;
  exit;
}

/* ========== JSON API（id/date 指定） ========== */
header('Content-Type: application/json; charset=utf-8');

$mysqli = new mysqli("localhost", "backhold", "backhold", "back_db1");
if ($mysqli->connect_error) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'DB接続失敗: '.$mysqli->connect_error], JSON_UNESCAPED_UNICODE);
  exit;
}

$id   = isset($_GET['id'])   ? intval($_GET['id']) : null;
$date = isset($_GET['date']) ? trim($_GET['date']) : null;
if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = null; }

if ($id) {
  $stmt = $mysqli->prepare("SELECT id, DATE_FORMAT(`date`, '%Y-%m-%d') AS day, sentence, place, image FROM db3 WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $id);
} elseif ($date) {
  $stmt = $mysqli->prepare("SELECT id, DATE_FORMAT(`date`, '%Y-%m-%d') AS day, sentence, place, image FROM db3 WHERE DATE(`date`) = ? ORDER BY id ASC LIMIT 1");
  $stmt->bind_param("s", $date);
} else {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>'id または date を指定してください'], JSON_UNESCAPED_UNICODE);
  $mysqli->close();
  exit;
}

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'SQL実行失敗: '.$mysqli->error], JSON_UNESCAPED_UNICODE);
  $stmt->close(); $mysqli->close();
  exit;
}
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
$mysqli->close();

if (!$row) {
  echo json_encode(['ok'=>true, 'id'=>null, 'date'=>($date ?: null), 'sentence'=>null, 'image_url'=>null, 'image_data'=>null, 'image_proxy'=>null], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

// image: URL 文字列（SAS期限付きでもOK。初回成功時にローカル保存）
$image_url = null;
if (!is_null($row['image']) && $row['image'] !== '') {
  if (is_string($row['image'])) $image_url = $row['image'];
}

// プロキシURLを安全に生成（このスクリプトのディレクトリを基準にする）
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir    = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/'); // 例: /os_2509/frontend/top/parent
$image_proxy = ($image_url && isset($row['id']))
  ? $scheme . $host . $dir . '/entry_api.php?mode=proxy&id=' . intval($row['id'])
  : null;

echo json_encode([
  'ok'         => true,
  'id'         => intval($row['id']),
  'date'       => $row['day'],
  'sentence'   => $row['sentence'] ?? null,
  'image_url'  => $image_url,
  'image_data' => null,
  'image_proxy'=> $image_proxy
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

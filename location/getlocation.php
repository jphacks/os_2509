<?php
// CSV追記：logs/positions.csv
header('Content-Type: application/json; charset=utf-8');

try {
    // 入力をJSONとして受け取る
    $raw = file_get_contents('php://input');
    if ($raw === false) throw new Exception('no input');
    $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

    // 必須項目の存在チェック
    $required = ['timestamp','latitude','longitude'];
    foreach ($required as $k) {
        if (!isset($data[$k])) throw new Exception("missing: $k");
    }

    // 型と値の軽い検証
    $ts  = $data['timestamp'];
    $lat = floatval($data['latitude']);
    $lon = floatval($data['longitude']);
    if ($lat < -90 || $lat > 90)   throw new Exception('invalid lat');
    if ($lon < -180 || $lon > 180) throw new Exception('invalid lon');

    // 任意フィールド
    $acc   = isset($data['accuracy']) ? floatval($data['accuracy']) : null;
    $alt   = isset($data['altitude']) ? floatval($data['altitude']) : null;
    $altac = isset($data['altitude_accuracy']) ? floatval($data['altitude_accuracy']) : null;
    $head  = isset($data['heading']) ? floatval($data['heading']) : null;
    $speed = isset($data['speed']) ? floatval($data['speed']) : null;
    $src   = isset($data['source']) ? (string)$data['source'] : '';

    // 保存先（フォルダなければ作る）
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new Exception('failed to create logs dir');
        }
    }
    $path = $dir . '/positions.csv';
    $is_new = !file_exists($path);

    // 追記
    $fp = fopen($path, 'a');
    if ($fp === false) throw new Exception('failed to open csv');

    // 排他ロック
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        throw new Exception('failed to lock');
    }

    // 新規作成ならヘッダ出力
    if ($is_new) {
        fputcsv($fp, [
            'timestamp','latitude','longitude','accuracy','altitude',
            'altitude_accuracy','heading','speed','ip','user_agent','source'
        ]);
    }

    // 付加情報
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // レコード出力
    fputcsv($fp, [
        $ts, $lat, $lon, $acc, $alt, $altac, $head, $speed, $ip, $ua, $src
    ]);

    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    echo json_encode(['ok' => true, 'path' => 'logs/positions.csv'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

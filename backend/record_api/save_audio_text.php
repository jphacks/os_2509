<?php
// 音声テキストと位置情報をDBに保存
header('Content-Type: application/json; charset=utf-8');

// データベース接続設定（環境に合わせて変更してください）
$db_config = [
    'host' => 'localhost',
    'dbname' => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4'
];

try {
    // 入力をJSONとして受け取る
    $raw = file_get_contents('php://input');
    if ($raw === false) throw new Exception('no input');
    $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

    // 必須項目の存在チェック
    if (!isset($data['soundtext']) || !isset($data['location'])) {
        throw new Exception('missing required fields: soundtext or location');
    }

    $soundtext = trim($data['soundtext']);
    $location = trim($data['location']);
    
    if (empty($soundtext)) {
        throw new Exception('soundtext is empty');
    }
    if (empty($location)) {
        throw new Exception('location is empty');
    }

    // 位置情報（任意）
    $position = isset($data['position']) ? $data['position'] : null;
    $latitude = null;
    $longitude = null;
    $accuracy = null;
    $altitude = null;
    
    if ($position && is_array($position)) {
        $latitude = isset($position['latitude']) ? floatval($position['latitude']) : null;
        $longitude = isset($position['longitude']) ? floatval($position['longitude']) : null;
        $accuracy = isset($position['accuracy']) ? floatval($position['accuracy']) : null;
        $altitude = isset($position['altitude']) ? floatval($position['altitude']) : null;
    }

    // データベース接続
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $db_config['host'],
        $db_config['dbname'],
        $db_config['charset']
    );
    
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // テーブルが存在しない場合は作成（初回実行時）
    $create_table_sql = "
    CREATE TABLE IF NOT EXISTS audio_texts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        soundtext TEXT NOT NULL,
        location VARCHAR(255) NOT NULL,
        latitude DECIMAL(10, 8) NULL,
        longitude DECIMAL(11, 8) NULL,
        accuracy DECIMAL(10, 2) NULL,
        altitude DECIMAL(10, 2) NULL,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_location (location),
        INDEX idx_recorded_at (recorded_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($create_table_sql);

    // データ挿入
    $stmt = $pdo->prepare("
        INSERT INTO audio_texts 
        (soundtext, location, latitude, longitude, accuracy, altitude) 
        VALUES 
        (:soundtext, :location, :latitude, :longitude, :accuracy, :altitude)
    ");
    
    $stmt->execute([
        ':soundtext' => $soundtext,
        ':location' => $location,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':accuracy' => $accuracy,
        ':altitude' => $altitude,
    ]);

    $insert_id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'id' => $insert_id,
        'message' => 'Data saved successfully'
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
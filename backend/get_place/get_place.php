<?php
/**
 * get_place.php
 * - db0テーブルを入力として読み込み
 * - 滞在検出＋Google Places API（v1）で施設名を取得
 * - 結果を db1_1 に出力
 *
 * DB接続: backend/config/config.php（mysqli）
 * Google APIキー: 同ディレクトリ内の config.php
 */

ini_set('memory_limit', '1024M');
mb_internal_encoding('UTF-8');

// ====== DB接続設定読み込み ======
// require_once __DIR__ . '/config/config.php';  // mysqli版
require_once dirname(__DIR__) . '/config/config.php';  // <- 1階層上の /config/config.php
$conn = getDbConnection();

// ====== Google APIキー読み込み ======
$api_cfg_path = __DIR__ . '/config.php';
if (!is_readable($api_cfg_path)) {
    die("❌ Google APIキー設定ファイル（config.php）が見つかりません\n");
}
$cfg = include $api_cfg_path;
$GOOGLE_API_KEY = $cfg['GOOGLE_API_KEY'] ?? null;
if (!$GOOGLE_API_KEY) {
    die("❌ config.php に GOOGLE_API_KEY が設定されていません\n");
}

// ====== パラメータ ======
const W_SEC          = 60;   // 平均速度算出の時間窓
const S_DWELL        = 0.4;  // 滞在速度上限 (m/s)
const S_WANDER_MAX   = 1.0;  // うろうろ速度上限 (m/s)
const R_DWELL        = 8.0;  // 滞在半径 (m)
const R_WANDER       = 15.0; // うろうろ半径 (m)
const MIN_DWELL_SEC  = 20;
const MIN_MOVE_SEC   = 10;
const LONG_DWELL_SEC = 60;

const PLACES_RADIUS_M   = 600;
const PLACES_RADIUS_MAX = 1500;
const PLACES_LANG       = 'ja';

// ====== ユーティリティ ======
function dbg($msg) { fwrite(STDERR, "[DEBUG] $msg\n"); }

function haversine_m($a_lat, $a_lon, $b_lat, $b_lon) {
    $R = 6371000.0;
    $toRad = M_PI / 180.0;
    $dLat = ($b_lat - $a_lat) * $toRad;
    $dLon = ($b_lon - $a_lon) * $toRad;
    $la1  = $a_lat * $toRad;
    $la2  = $b_lat * $toRad;
    $h = sin($dLat/2)**2 + cos($la1)*cos($la2)*sin($dLon/2)**2;
    return 2*$R*asin(min(1.0, sqrt($h)));
}

function classify_once($avg_v, $radius) {
    if ($avg_v < S_DWELL && $radius !== null && $radius < R_DWELL) return 'dwell';
    if ($avg_v < S_WANDER_MAX && $radius !== null && $radius < R_WANDER) return 'wander_dwell';
    return 'moving';
}

// ====== Places API呼び出し ======
function places_v1_post($endpoint, $payload, $fieldMask, $api_key) {
    $url = "https://places.googleapis.com/v1/{$endpoint}";
    $headers = [
        "Content-Type: application/json; charset=utf-8",
        "X-Goog-Api-Key: {$api_key}",
        "X-Goog-FieldMask: {$fieldMask}",
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($res, true);
    return [$json, $status];
}

function google_find_nearby_spot($lat, $lon, &$cache, $api_key) {
    $key = sprintf("%.5f,%.5f", $lat, $lon);
    if (isset($cache[$key])) return $cache[$key];

    $radius = PLACES_RADIUS_M;
    $FIELD_MASK = "places.id,places.displayName,places.location,places.formattedAddress";
    $best = null; $bestDist = INF;

    while ($radius <= PLACES_RADIUS_MAX) {
        $payload = [
            "languageCode"    => PLACES_LANG,
            "maxResultCount"  => 10,
            "includedTypes"   => ["park"],
            "locationRestriction" => [
                "circle" => [
                    "center" => ["latitude" => $lat, "longitude" => $lon],
                    "radius" => $radius
                ]
            ]
        ];
        [$data, $status] = places_v1_post("places:searchNearby", $payload, $FIELD_MASK, $api_key);
        usleep(200000);

        if (!empty($data['places'])) {
            foreach ($data['places'] as $p) {
                $name = $p['displayName']['text'] ?? $p['formattedAddress'] ?? null;
                if (!$name) continue;
                $plat = $p['location']['latitude'] ?? null;
                $plon = $p['location']['longitude'] ?? null;
                if (!$plat || !$plon) continue;
                $dist = haversine_m($lat, $lon, $plat, $plon);
                if ($dist < $bestDist) {
                    $bestDist = $dist;
                    $best = ['name' => $name, 'distance_m' => $dist];
                }
            }
            if ($best) {
                $cache[$key] = $best;
                return $best;
            }
        }
        $radius *= 1.8;
    }
    $cache[$key] = null;
    return null;
}

// ====== Trackerクラス ======
class Tracker {
    public $track = [];
    public $state = 'moving';
    public $current_spot = null;
    private $cache;
    private $api_key;

    public function __construct(&$cache, $api_key) {
        $this->cache = &$cache;
        $this->api_key = $api_key;
    }

    public function update($lat, $lon, $ts) {
        $this->track[] = [$lat, $lon, $ts];
        if (count($this->track) > 7200) array_splice($this->track, 0, 3600);

        $win = array_filter($this->track, fn($p) => $p[2] >= $ts - W_SEC);
        $avg_v = $this->avg_speed($win);
        [$center, $rg] = $this->center_and_radius($win);

        $instant = classify_once($avg_v, $rg);
        $this->state = $instant;

        if (($instant === 'dwell' || $instant === 'wander_dwell') && !$this->current_spot) {
            $spot = google_find_nearby_spot($center['lat'], $center['lon'], $this->cache, $this->api_key);
            if ($spot) $this->current_spot = $spot;
        } elseif ($instant === 'moving') {
            $this->current_spot = null;
        }

        return [
            'state' => $instant,
            'place' => $this->current_spot['name'] ?? '移動中'
        ];
    }

    private function avg_speed($pts) {
        $n = count($pts);
        if ($n < 2) return 0;
        $dist = 0;
        for ($i=1; $i<$n; $i++)
            $dist += haversine_m($pts[$i-1][0], $pts[$i-1][1], $pts[$i][0], $pts[$i][1]);
        $dt = max(1, $pts[$n-1][2] - $pts[0][2]);
        return $dist / $dt;
    }

    private function center_and_radius($pts) {
        if (empty($pts)) return [null, null];
        $lat = array_sum(array_column($pts, 0)) / count($pts);
        $lon = array_sum(array_column($pts, 1)) / count($pts);
        $sum = 0;
        foreach ($pts as $p) $sum += haversine_m($lat, $lon, $p[0], $p[1])**2;
        $rg = sqrt($sum / max(1, count($pts)));
        return [['lat'=>$lat,'lon'=>$lon], $rg];
    }
}

// ====== メイン処理 ======
$res = $conn->query("SELECT date, latitude, longitude FROM db0 WHERE latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY date ASC");
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$spot_cache = [];
$trk = new Tracker($spot_cache, $GOOGLE_API_KEY);
$stmt = $conn->prepare("INSERT INTO db1_1 (date, location) VALUES (?, ?)");

foreach ($rows as $r) {
    $ts = strtotime($r['date']);
    $lat = (float)$r['latitude'];
    $lon = (float)$r['longitude'];

    $result = $trk->update($lat, $lon, $ts);
    $place = $result['place'];
    $stmt->bind_param("ss", $r['date'], $place);
    $stmt->execute();

    dbg("{$r['date']} => {$place}");
}

$stmt->close();
$conn->close();

echo "✅ 完了: db1_1 に保存しました。\n";
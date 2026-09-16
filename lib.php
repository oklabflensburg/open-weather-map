<?php
declare(strict_types=1);

/**
 * Geteilte Bibliothek – Interpolation, WW-Codes, DB-Zugriff.
 * Wird von api.php, import_cities.php und optional pipeline.php genutzt.
 */

// ─── Konstanten (überschreibbar durch pipeline.php) ──────────────────────────
defined('DB_PATH')           || define('DB_PATH',           __DIR__ . '/mosmix.sqlite3');
defined('SEARCH_RADIUS_DEG') || define('SEARCH_RADIUS_DEG', 1.0);
defined('IDW_NEIGHBORS')     || define('IDW_NEIGHBORS',      4);
defined('ELEV_PENALTY_KM')   || define('ELEV_PENALTY_KM',    0.1);
defined('RAIN_ACCUM_HOURS')  || define('RAIN_ACCUM_HOURS',   6);
defined('TZ_LOCAL')          || define('TZ_LOCAL',           'Europe/Berlin');

// ─── Datenbank ────────────────────────────────────────────────────────────────

function openDatabase(string $path = DB_PATH): SQLite3
{
    $db = new SQLite3($path);
    $db->exec('PRAGMA synchronous  = OFF');
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA cache_size   = -32768');  // 32 MB
    $db->exec('PRAGMA foreign_keys = ON');
    return $db;
}

// ─── Geometrie ────────────────────────────────────────────────────────────────

function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    static $R = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a    = sin($dLat / 2) ** 2
          + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $R * 2.0 * asin(min(1.0, sqrt($a)));
}

function pseudoDistKm(
    float $tLat, float $tLon, float $tElev,
    float $sLat, float $sLon, float $sElev
): float {
    return haversineKm($tLat, $tLon, $sLat, $sLon)
         + abs($tElev - $sElev) / 100.0 * ELEV_PENALTY_KM;
}

// ─── Stationssuche ───────────────────────────────────────────────────────────

/**
 * Findet Kandidaten-Stationen im SEARCH_RADIUS_DEG-Umkreis,
 * sortiert nach Pseudo-Distanz (horizontal + Höhenkorrektur).
 */
function findCandidateStations(SQLite3 $db, float $lat, float $lon, float $elev): array
{
    $stmt = $db->prepare(<<<SQL
        SELECT id, lat, lon, elevation
        FROM   stations
        WHERE  lat BETWEEN :la AND :lb
          AND  lon BETWEEN :lo AND :lp
    SQL);
    $stmt->bindValue(':la', $lat - SEARCH_RADIUS_DEG, SQLITE3_FLOAT);
    $stmt->bindValue(':lb', $lat + SEARCH_RADIUS_DEG, SQLITE3_FLOAT);
    $stmt->bindValue(':lo', $lon - SEARCH_RADIUS_DEG, SQLITE3_FLOAT);
    $stmt->bindValue(':lp', $lon + SEARCH_RADIUS_DEG, SQLITE3_FLOAT);

    $res        = $stmt->execute();
    $candidates = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $candidates[] = [
            'id'        => $row['id'],
            'lat'       => (float)$row['lat'],
            'lon'       => (float)$row['lon'],
            'elevation' => (float)$row['elevation'],
            'dist_km'   => pseudoDistKm($lat, $lon, $elev, $row['lat'], $row['lon'], $row['elevation']),
        ];
    }
    usort($candidates, static fn($a, $b) => $a['dist_km'] <=> $b['dist_km']);
    return $candidates;
}

// ─── Inverse Distance Weighting ──────────────────────────────────────────────

/**
 * @param array<array{0:float|null, 1:float}> $valDist  [[value, dist_km], ...]
 */
function idw(array $valDist): ?float
{
    $ws = $wv = 0.0;
    foreach ($valDist as [$v, $d]) {
        if ($v === null) continue;
        $w   = 1.0 / max($d, 0.001) ** 2;
        $wv += $v * $w;
        $ws += $w;
    }
    return $ws > 0.0 ? $wv / $ws : null;
}

// ─── Forecast-Daten laden ────────────────────────────────────────────────────

/**
 * Lädt Forecast-Rows für die angegebenen Stationen und optionalen Zeitbereich.
 * Rückgabe: [valid_time => [station_id => row]]
 */
function loadForecastIndexRange(
    SQLite3 $db,
    array   $stationIds,
    ?int    $fromTime = null,
    ?int    $toTime   = null
): array {
    if (empty($stationIds)) return [];

    $ph = implode(',', array_fill(0, count($stationIds), '?'));
    $tc = '';
    if ($fromTime !== null) $tc .= ' AND valid_time >= ?';
    if ($toTime   !== null) $tc .= ' AND valid_time <= ?';

    $stmt = $db->prepare(<<<SQL
        SELECT station_id, valid_time,
               TTT, PPPP, RH, R101, Dur,
               Neff_cl, Neff_cm, Neff_ch, N_total,
               wind_u, wind_v, FX1, WW
        FROM   forecasts
        WHERE  station_id IN ({$ph}){$tc}
        ORDER  BY valid_time ASC
    SQL);

    $i = 1;
    foreach ($stationIds as $sid) $stmt->bindValue($i++, $sid, SQLITE3_TEXT);
    if ($fromTime !== null) $stmt->bindValue($i++, $fromTime, SQLITE3_INTEGER);
    if ($toTime   !== null) $stmt->bindValue($i++, $toTime,   SQLITE3_INTEGER);

    $res = $stmt->execute();
    $idx = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $idx[(int)$row['valid_time']][$row['station_id']] = $row;
    }
    return $idx;
}

// ─── Kern-Interpolation ───────────────────────────────────────────────────────

/**
 * Berechnet stündliche Wettervorhersage für beliebige Koordinaten on-demand.
 *
 * Rückgabe:
 *   [
 *     'meta' => ['idw_stations'=>[], 'nearest_station'=>'', 'candidate_count'=>N, 'error'=>?],
 *     'rows' => [['valid_time'=>N, 'temp_c'=>, ...], ...]
 *   ]
 */
function interpolateForLocation(
    SQLite3 $db,
    float $lat,
    float $lon,
    float $elev,
    int   $fromTime,
    int   $toTime,
    int   $rainLookbackHours = RAIN_ACCUM_HOURS
): array {
    $candidates = findCandidateStations($db, $lat, $lon, $elev);
    if (empty($candidates)) {
        return ['meta' => ['error' => 'Keine DWD-Stationen im Umkreis gefunden.'], 'rows' => []];
    }

    $idwSet    = array_slice($candidates, 0, IDW_NEIGHBORS);
    $idwIds    = array_column($idwSet, 'id');
    $idwDists  = array_column($idwSet, 'dist_km');
    $nearestId = $candidates[0]['id'];

    $allIds   = array_unique(array_merge($idwIds, [$nearestId]));
    $loadFrom = $fromTime - ($rainLookbackHours * 3600);
    $fcIndex  = loadForecastIndexRange($db, $allIds, $loadFrom, $toTime);

    $allTimes = array_keys($fcIndex);
    sort($allTimes);

    $rainBuf = [];
    $rows    = [];

    foreach ($allTimes as $vt) {
        $data = $fcIndex[$vt];

        // Hilfsclosure: Wertepaare [value, dist_km] für IDW aufbauen
        $vd = static function (string $col) use ($data, $idwIds, $idwDists): array {
            $out = [];
            foreach ($idwIds as $i => $sid) {
                $v   = (isset($data[$sid][$col]) && $data[$sid][$col] !== null)
                     ? (float)$data[$sid][$col] : null;
                $out[] = [$v, $idwDists[$i]];
            }
            return $out;
        };

        // A) IDW für stetige Parameter
        $TTT_K   = idw($vd('TTT'));
        $PPPP_Pa = idw($vd('PPPP'));
        $RH      = idw($vd('RH'));
        $R101    = idw($vd('R101'));
        $Dur_s   = idw($vd('Dur'));
        $cl      = idw($vd('Neff_cl'));
        $cm      = idw($vd('Neff_cm'));
        $ch      = idw($vd('Neff_ch'));
        $ntotal  = idw($vd('N_total'));

        // B) Wind-Vektoren interpolieren, dann Rückrechnung
        $wind_u = idw($vd('wind_u'));
        $wind_v = idw($vd('wind_v'));
        $gust   = idw($vd('FX1'));
        $wspd   = null;
        $wdir   = null;
        if ($wind_u !== null && $wind_v !== null) {
            $wspd = sqrt($wind_u ** 2 + $wind_v ** 2);
            $wdir = fmod(rad2deg(atan2(-$wind_u, -$wind_v)) + 360.0, 360.0);
        }

        // C) WW: Nearest-Neighbor, nie mitteln
        $ww = null;
        if (isset($data[$nearestId]['WW']) && $data[$nearestId]['WW'] !== null) {
            $ww = (int)(float)$data[$nearestId]['WW'];
        }
        if ($ww === null) {
            foreach ($candidates as $c) {
                if (isset($fcIndex[$vt][$c['id']]['WW']) && $fcIndex[$vt][$c['id']]['WW'] !== null) {
                    $ww = (int)(float)$fcIndex[$vt][$c['id']]['WW'];
                    break;
                }
            }
        }

        // Rollende 6h-Regensumme
        $rainBuf[] = $R101 ?? 0.0;
        if (count($rainBuf) > $rainLookbackHours) array_shift($rainBuf);
        $rain6h = round(array_sum($rainBuf), 2);

        if ($vt < $fromTime) continue;  // Lookback überspringen

        $rows[] = [
            'valid_time'    => $vt,
            'temp_c'        => $TTT_K   !== null ? round($TTT_K - 273.15, 1)      : null,
            'pressure_hpa'  => $PPPP_Pa !== null ? round($PPPP_Pa / 100.0, 1)     : null,
            'humidity'      => $RH      !== null ? (int)round($RH)                 : null,
            'rain_1h'       => $R101    !== null ? round($R101, 2)                 : null,
            'sunshine_pct'  => $Dur_s   !== null ? round(min(100.0, $Dur_s/36.0), 1): null,
            'cloud_low'     => $cl      !== null ? (int)round($cl)                 : null,
            'cloud_mid'     => $cm      !== null ? (int)round($cm)                 : null,
            'cloud_high'    => $ch      !== null ? (int)round($ch)                 : null,
            'cloud_total'   => $ntotal  !== null ? (int)round($ntotal)             : null,
            'wind_u'        => $wind_u  !== null ? round($wind_u, 2)               : null,
            'wind_v'        => $wind_v  !== null ? round($wind_v, 2)               : null,
            'wind_speed'    => $wspd    !== null ? round($wspd, 1)                 : null,
            'wind_dir'      => $wdir    !== null ? (int)round($wdir)               : null,
            'wind_gust'     => $gust    !== null ? round($gust, 1)                 : null,
            'weather_code'  => $ww,
            'rain_accum_6h' => $rain6h,
        ];
    }

    // In der stations-Tabelle enthält 'id' den lesbaren Stationsnamen (aus kml:description),
    // 'name' enthält den numerischen DWD-Code (aus kml:name). Daher direkt $sid verwenden.
    $idwNames    = array_map(
        static fn($s) => ucwords(strtolower($s)),
        $idwIds
    );
    $nearestName = ucwords(strtolower($nearestId));

    return [
        'meta' => [
            'idw_stations'    => $idwNames,
            'idw_dists'       => array_map(static fn($d) => round($d, 1), $idwDists),
            'idw_lats'        => array_column($idwSet, 'lat'),
            'idw_lons'        => array_column($idwSet, 'lon'),
            'nearest_station' => $nearestName ? ucwords(strtolower((string)$nearestName)) : $nearestId,
            'nearest_dist'    => round($candidates[0]['dist_km'], 1),
            'nearest_lat'     => $candidates[0]['lat'],
            'nearest_lon'     => $candidates[0]['lon'],
            'candidate_count' => count($candidates),
        ],
        'rows' => $rows,
    ];
}

// ─── WW-Code-Mapping ─────────────────────────────────────────────────────────

function wwInfo(?int $ww): array
{
    if ($ww === null) return ['label' => '—', 'icon' => '—', 'class' => 'ww-na'];
    return match (true) {
        $ww === 0          => ['label' => 'Sonnig',              'icon' => '☀️',   'class' => 'ww-sunny'],
        $ww <= 2           => ['label' => 'Heiter',              'icon' => '🌤️',  'class' => 'ww-fair'],
        $ww === 3          => ['label' => 'Bewölkt',             'icon' => '☁️',   'class' => 'ww-cloudy'],
        $ww <= 9           => ['label' => 'Dunst',               'icon' => '🌫️',  'class' => 'ww-haze'],
        $ww <= 12          => ['label' => 'Nebel',               'icon' => '🌫️',  'class' => 'ww-fog'],
        $ww === 13         => ['label' => 'Wetterleuchten',      'icon' => '🌩️',  'class' => 'ww-lightning'],
        $ww <= 19          => ['label' => 'Besondere Witterung', 'icon' => '🌡️',  'class' => 'ww-special'],
        $ww <= 22          => ['label' => 'Leichter Regen',      'icon' => '🌦️',  'class' => 'ww-light-rain'],
        $ww === 23         => ['label' => 'Schneeregen',         'icon' => '🌨️',  'class' => 'ww-sleet'],
        $ww <= 25          => ['label' => 'Gefrierender Regen',  'icon' => '🌧️',  'class' => 'ww-freezing'],
        $ww <= 29          => ['label' => 'Regen/Schnee',        'icon' => '🌨️',  'class' => 'ww-mix'],
        $ww <= 39          => ['label' => 'Staubsturm',          'icon' => '🌪️',  'class' => 'ww-dust'],
        $ww <= 49          => ['label' => 'Nebel',               'icon' => '🌫️',  'class' => 'ww-fog'],
        $ww <= 55          => ['label' => 'Nieselregen',         'icon' => '🌦️',  'class' => 'ww-drizzle'],
        $ww <= 59          => ['label' => 'Nieselregen+Regen',   'icon' => '🌧️',  'class' => 'ww-drizzle-rain'],
        $ww <= 65          => ['label' => 'Regen',               'icon' => '🌧️',  'class' => 'ww-rain'],
        $ww <= 67          => ['label' => 'Gefrierender Regen',  'icon' => '🌧️',  'class' => 'ww-freezing'],
        $ww <= 69          => ['label' => 'Schneeregen',         'icon' => '🌨️',  'class' => 'ww-sleet'],
        $ww <= 75          => ['label' => 'Schnee',              'icon' => '❄️',   'class' => 'ww-snow'],
        $ww <= 79          => ['label' => 'Eiskörnchen',         'icon' => '🌨️',  'class' => 'ww-ice'],
        $ww <= 82          => ['label' => 'Regenschauer',        'icon' => '🌦️',  'class' => 'ww-shower'],
        $ww <= 84          => ['label' => 'Schneeregenschauer',  'icon' => '🌨️',  'class' => 'ww-sleet'],
        $ww <= 87          => ['label' => 'Schneeschauer',       'icon' => '❄️',   'class' => 'ww-snow'],
        $ww <= 90          => ['label' => 'Hagelschauer',        'icon' => '⛈️',   'class' => 'ww-hail'],
        $ww <= 94          => ['label' => 'Gewitter (leicht)',   'icon' => '⛈️',   'class' => 'ww-thunder'],
        default            => ['label' => 'Gewitter',            'icon' => '⛈️',   'class' => 'ww-thunder'],
    };
}

function windDirLabel(int $deg): string
{
    $d = ['N','NNO','NO','ONO','O','OSO','SO','SSO','S','SSW','SW','WSW','W','WNW','NW','NNW'];
    return $d[(int)round($deg / 22.5) % 16];
}

// ─── Suchtext normalisieren (Umlaute→ASCII für GeoNames-Suche) ───────────────

function normalizeSearch(string $q): string
{
    $q = mb_strtolower(trim($q), 'UTF-8');
    return strtr($q, ['ä'=>'a','ö'=>'o','ü'=>'u','ß'=>'ss','é'=>'e','è'=>'e','ê'=>'e','à'=>'a','â'=>'a']);
}

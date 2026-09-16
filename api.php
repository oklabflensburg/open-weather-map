<?php
declare(strict_types=1);

/**
 * JSON-API für das MOSMIX-Frontend.
 *
 * GET ?action=search&q=Berlin                      → Ortssuche (Autocomplete)
 * GET ?action=forecast&lat=N&lon=E&elev=M&hours=49  → Interpolierte Vorhersage (−1h bis +48h)
 * GET ?action=locations                             → Liste gespeicherter Orte
 * GET ?action=precomputed&id=N[&hours=49]           → Vorberechnete Vorhersage
 */

require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Einfacher Output-Buffer für saubere JSON-Ausgabe auch bei Warnungen
ob_start();

try {
    $db     = openDatabase(DB_PATH);
    $action = $_GET['action'] ?? '';

    match ($action) {
        'search'      => handleSearch($db),
        'forecast'    => handleForecast($db),
        'locations'   => handleLocations($db),
        'precomputed' => handlePrecomputed($db),
        'meta'        => handleMeta($db),
        default       => jsonError('Unbekannte Aktion.', 400),
    };
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

ob_end_flush();

// ─── Suche ────────────────────────────────────────────────────────────────────

function handleSearch(SQLite3 $db): void
{
    $q = trim($_GET['q'] ?? '');
    if (mb_strlen($q, 'UTF-8') < 2) {
        echo json_encode([]);
        return;
    }

    $limit = min((int)($_GET['limit'] ?? 10), 20);

    $qNorm = normalizeSearch($q);
    $qLow  = mb_strtolower($q, 'UTF-8');

    // SQL-Ausdruck: Umlaut-Vereinfachung direkt in SQLite
    $deNorm = "REPLACE(REPLACE(REPLACE(REPLACE(LOWER(name),'ü','u'),'ö','o'),'ä','a'),'ß','ss')";

    // Ranking: 0 = Präfix-Treffer, 1 = reiner Substring-Treffer
    // Präfix: erste 4 Zeichen des normalisierten Suchstrings
    $prefix4 = mb_substr($qNorm, 0, 4, 'UTF-8') . '%';
    $sub     = '%' . $qNorm . '%';
    $subLow  = '%' . $qLow  . '%';

    // LIMIT als Integer eingebettet (Named-Parameter in LIMIT unzuverlässig)
    $stmt = $db->prepare(
        "SELECT id, name, name_ascii, lat, lon, elevation, country, admin1, population,
                CASE WHEN name_ascii LIKE :pf4 THEN 0
                     WHEN {$deNorm}  LIKE :pf4 THEN 0
                     ELSE 1 END AS rank
         FROM   cities
         WHERE  name_ascii  LIKE :sub
            OR  LOWER(name) LIKE :subLow
            OR  {$deNorm}   LIKE :sub
         ORDER  BY rank ASC, population DESC
         LIMIT  {$limit}"
    );
    if ($stmt === false) {
        jsonError('Datenbankfehler: ' . $db->lastErrorMsg(), 500);
        return;
    }
    $stmt->bindValue(':pf4',    $prefix4, SQLITE3_TEXT);
    $stmt->bindValue(':sub',    $sub,     SQLITE3_TEXT);
    $stmt->bindValue(':subLow', $subLow,  SQLITE3_TEXT);

    $res     = $stmt->execute();
    $results = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $results[] = [
            'id'         => (int)$row['id'],
            'name'       => $row['name'],
            'admin1'     => $row['admin1'],
            'country'    => $row['country'],
            'lat'        => (float)$row['lat'],
            'lon'        => (float)$row['lon'],
            'elevation'  => (int)$row['elevation'],
            'population' => (int)$row['population'],
        ];
    }

    ob_end_clean();
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Vorhersage ───────────────────────────────────────────────────────────────

function handleForecast(SQLite3 $db): void
{
    $latRaw = $_GET['lat'] ?? null;
    $lonRaw = $_GET['lon'] ?? null;
    if ($latRaw === null || $lonRaw === null || !is_numeric($latRaw) || !is_numeric($lonRaw)) {
        jsonError('lat und lon sind Pflichtparameter.', 400);
        return;
    }
    $lat   = (float)$latRaw;
    $lon   = (float)$lonRaw;
    $elev  = (float)($_GET['elev'] ?? 0.0);
    $hours = min((int)($_GET['hours'] ?? 12), 240);

    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        jsonError('Koordinaten außerhalb des gültigen Bereichs.', 400);
        return;
    }

    $now = time();

    // Startzeit: vorheriger lokaler 3h-Block (z.B. jetzt 08:15 → Start 03:00 Lokalzeit)
    $tz       = new DateTimeZone(TZ_LOCAL);
    $dtNow    = new DateTime('now', $tz);
    $blockH   = (int)floor((int)$dtNow->format('H') / 3) * 3;
    $dtNow->setTime($blockH, 0, 0);
    $fromTime = $dtNow->getTimestamp() - 3 * 3600;

    // Sicherstellen dass Daten vorhanden sind
    $firstAvail = (int)$db->querySingle(
        "SELECT MIN(valid_time) FROM forecasts WHERE valid_time >= " . ($fromTime - 3600)
    );
    if (!$firstAvail) {
        jsonError('Keine aktuellen Forecast-Daten in der Datenbank. Pipeline ausführen.', 503);
        return;
    }

    $toTime = $fromTime + ($hours * 3600);

    $result = interpolateForLocation($db, $lat, $lon, $elev, $fromTime, $toTime);

    if (!empty($result['meta']['error'])) {
        jsonError($result['meta']['error'], 404);
        return;
    }

    // WW-Info und Windrichtung ergänzen
    foreach ($result['rows'] as &$row) {
        $ww = $row['weather_code'];
        $info = wwInfo($ww);
        $row['weather_label'] = $info['label'];
        $row['weather_icon']  = $info['icon'];
        $row['weather_class'] = $info['class'];
        $row['wind_dir_label']= $row['wind_dir'] !== null
            ? windDirLabel($row['wind_dir']) : '—';
        $row['valid_time_iso'] = gmdate('Y-m-d\TH:i:s\Z', $row['valid_time']);
    }
    unset($row);

    // Datenbankstand
    $dbAge = $now - (int)$db->querySingle("SELECT MIN(valid_time) FROM forecasts");

    $metaRow = $db->query("SELECT key, value FROM meta");
    $metaDb  = [];
    if ($metaRow) {
        while ($r = $metaRow->fetchArray(SQLITE3_ASSOC)) $metaDb[$r['key']] = $r['value'];
    }
    $importTs       = isset($metaDb['import_time'])    ? (int)strtotime($metaDb['import_time'])    : null;
    $issueTimeUtc   = $metaDb['issue_time_utc']   ?? null;
    $modelRunUtc    = $metaDb['model_run_utc']    ?? null;
    $kmlValidFrom   = $metaDb['valid_from_utc']   ?? null;
    $kmlValidTo     = $metaDb['valid_to_utc']     ?? null;

    $payload = [
        'location' => ['lat' => $lat, 'lon' => $lon, 'elevation' => $elev],
        'meta'     => array_merge($result['meta'], [
            'hours'           => $hours,
            'from_utc'        => gmdate('Y-m-d\TH:i:s\Z', $fromTime),
            'to_utc'          => gmdate('Y-m-d\TH:i:s\Z', $toTime),
            'db_age_hours'    => round($dbAge / 3600, 1),
            'generated_utc'   => gmdate('Y-m-d\TH:i:s\Z', $now),
            'import_ts'       => $importTs,
            'issue_time_utc'  => $issueTimeUtc,
            'model_run_utc'   => $modelRunUtc,
            'kml_valid_from'  => $kmlValidFrom,
            'kml_valid_to'    => $kmlValidTo,
        ]),
        'forecast' => $result['rows'],
    ];

    ob_end_clean();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ─── Gespeicherte Orte ────────────────────────────────────────────────────────

function handleLocations(SQLite3 $db): void
{
    $res  = $db->query("SELECT id, label, lat, lon, elevation FROM my_locations ORDER BY id");
    $list = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $list[] = [
            'id'        => (int)$row['id'],
            'label'     => $row['label'],
            'lat'       => (float)$row['lat'],
            'lon'       => (float)$row['lon'],
            'elevation' => (int)$row['elevation'],
        ];
    }
    ob_end_clean();
    echo json_encode($list, JSON_UNESCAPED_UNICODE);
    exit;
}

function handlePrecomputed(SQLite3 $db): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonError('id ist ein Pflichtparameter.', 400);
        return;
    }

    $loc = $db->querySingle(
        "SELECT id, label, lat, lon, elevation FROM my_locations WHERE id = $id",
        true
    );
    if (!$loc) {
        jsonError('Gespeicherter Ort nicht gefunden.', 404);
        return;
    }

    $now   = time();
    $hours = min((int)($_GET['hours'] ?? 12), 240);

    // Startzeit: vorheriger lokaler 3h-Block
    $tz       = new DateTimeZone(TZ_LOCAL);
    $dtNow    = new DateTime('now', $tz);
    $blockH   = (int)floor((int)$dtNow->format('H') / 3) * 3;
    $dtNow->setTime($blockH, 0, 0);
    $fromTime = $dtNow->getTimestamp() - 3 * 3600;

    $firstAvail = (int)$db->querySingle(
        "SELECT MIN(valid_time) FROM my_forecasts WHERE location_id = $id AND valid_time >= " . ($fromTime - 3600)
    );
    if (!$firstAvail) {
        jsonError('Keine Vorhersage-Daten für diesen Ort. Pipeline erneut ausführen.', 503);
        return;
    }

    $toTime = $fromTime + ($hours * 3600);
    $res    = $db->query(
        "SELECT * FROM my_forecasts
          WHERE location_id = $id
            AND valid_time >= $fromTime AND valid_time <= $toTime
          ORDER BY valid_time ASC"
    );

    $rows = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $ww   = $row['weather_code'] !== null ? (int)$row['weather_code'] : null;
        $info = wwInfo($ww);
        $rows[] = [
            'valid_time'     => (int)$row['valid_time'],
            'valid_time_iso' => gmdate('Y-m-d\TH:i:s\Z', (int)$row['valid_time']),
            'temp_c'         => $row['temp_c']        !== null ? round((float)$row['temp_c'],        1) : null,
            'pressure_hpa'   => $row['pressure_hpa']  !== null ? round((float)$row['pressure_hpa'],  1) : null,
            'humidity'       => $row['humidity']       !== null ? round((float)$row['humidity'])         : null,
            'rain_1h'        => $row['rain_1h']        !== null ? round((float)$row['rain_1h'],       2) : null,
            'rain_accum_6h'  => $row['rain_accum_6h'] !== null ? round((float)$row['rain_accum_6h'], 2) : null,
            'sunshine_pct'   => $row['sunshine_pct']  !== null ? round((float)$row['sunshine_pct'])     : null,
            'cloud_low'      => $row['cloud_low']      !== null ? round((float)$row['cloud_low'])        : null,
            'cloud_mid'      => $row['cloud_mid']      !== null ? round((float)$row['cloud_mid'])        : null,
            'cloud_high'     => $row['cloud_high']     !== null ? round((float)$row['cloud_high'])       : null,
            'cloud_total'    => $row['cloud_total']    !== null ? round((float)$row['cloud_total'])      : null,
            'wind_speed'     => $row['wind_speed']     !== null ? round((float)$row['wind_speed'],   1) : null,
            'wind_dir'       => $row['wind_dir']       !== null ? round((float)$row['wind_dir'])         : null,
            'wind_gust'      => $row['wind_gust']      !== null ? round((float)$row['wind_gust'],    1) : null,
            'weather_code'   => $ww,
            'weather_label'  => $info['label'],
            'weather_icon'   => $info['icon'],
            'weather_class'  => $info['class'],
            'wind_dir_label' => $row['wind_dir'] !== null ? windDirLabel((int)round((float)$row['wind_dir'])) : '—',
        ];
    }

    $dbAge = $now - (int)$db->querySingle("SELECT MIN(valid_time) FROM my_forecasts WHERE location_id = $id");

    $metaRow2 = $db->query("SELECT key, value FROM meta");
    $metaDb2  = [];
    if ($metaRow2) {
        while ($r = $metaRow2->fetchArray(SQLITE3_ASSOC)) $metaDb2[$r['key']] = $r['value'];
    }

    $payload = [
        'location' => [
            'id'        => (int)$loc['id'],
            'label'     => $loc['label'],
            'lat'       => (float)$loc['lat'],
            'lon'       => (float)$loc['lon'],
            'elevation' => (int)$loc['elevation'],
        ],
        'meta' => [
            'precomputed'   => true,
            'hours'         => $hours,
            'from_utc'      => gmdate('Y-m-d\TH:i:s\Z', $fromTime),
            'to_utc'        => gmdate('Y-m-d\TH:i:s\Z', $toTime),
            'db_age_hours'  => round($dbAge / 3600, 1),
            'generated_utc' => gmdate('Y-m-d\TH:i:s\Z', $now),
            'import_ts'       => isset($metaDb2['import_time']) ? (int)strtotime($metaDb2['import_time']) : null,
            'issue_time_utc'  => $metaDb2['issue_time_utc']  ?? null,
            'model_run_utc'   => $metaDb2['model_run_utc']   ?? null,
            'kml_valid_from'  => $metaDb2['valid_from_utc']  ?? null,
            'kml_valid_to'    => $metaDb2['valid_to_utc']    ?? null,
        ],
        'forecast' => $rows,
    ];

    ob_end_clean();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ─── Datenbasis-Info ─────────────────────────────────────────────────────────

function handleMeta(SQLite3 $db): void
{
    $res  = $db->query("SELECT key, value FROM meta");
    $data = [];
    if ($res) {
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $data[$row['key']] = $row['value'];
        }
    }
    $now = time();
    $data['now_utc'] = gmdate('Y-m-d\TH:i:s\Z', $now);
    if (!empty($data['import_time'])) {
        $importTs = strtotime($data['import_time']);
        $data['db_age_hours'] = round(($now - $importTs) / 3600, 1);
    }
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Hilfsfunktionen ─────────────────────────────────────────────────────────

function jsonError(string $msg, int $code = 400): void
{
    ob_end_clean();
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

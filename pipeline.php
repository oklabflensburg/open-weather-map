<?php
declare(strict_types=1);

/**
 * DWD MOSMIX-L Weather Data Pipeline
 * ====================================
 * PHP 8.x | SQLite3 | cURL | XMLReader + SimpleXML (streaming)
 *
 * CRONJOB (alle 6 Stunden, +30 min Versatz für DWD-Upload-Lag):
 *   30 0,6,12,18 * * * /usr/bin/php /var/www/html/mosmix/pipeline.php >> /var/log/mosmix.log 2>&1
 *
 * VOR DEM ERSTEN LAUF – eigene Standorte eintragen:
 *   sqlite3 mosmix.sqlite3 \
 *     "INSERT INTO my_locations (label,lat,lon,elevation) VALUES ('München',48.137,11.576,519);"
 */

// ─────────────────────────────────────────────────────────────────────────────
// KONFIGURATION
// ─────────────────────────────────────────────────────────────────────────────

const DB_PATH   = __DIR__ . '/mosmix.sqlite3';
const DATA_DIR  = __DIR__ . '/data';
const KMZ_LOCAL = DATA_DIR . '/MOSMIX_L_LATEST.kmz';

// DWD-OpenData-URLs
const URL_MOSMIX_KMZ = 'https://opendata.dwd.de/weather/local_forecasts/mos/MOSMIX_L/all_stations/kml/MOSMIX_L_LATEST.kmz';

// Stationsliste: Fixed-Width-Format, Koordinaten in Grad.Minuten
// Direkte Download-URL zur DWD-Stationsliste (TXT-Datei im ZIP)
const URL_STATION_CFG = 'https://opendata.dwd.de/climate_environment/CDC/observations_germany/climate/hourly/air_temperature/recent/TU_Stundenwerte_Beschreibung_Stationen.txt';

// Interpolationsparameter
const SEARCH_RADIUS_DEG  = 1.0;   // Suchradius in Grad (~111 km)
const IDW_NEIGHBORS      = 4;     // Anzahl nächster Stationen für IDW
const ELEV_PENALTY_KM    = 0.1;   // km-Malus pro 100 m Höhendifferenz
const RAIN_ACCUM_HOURS   = 6;     // Stunden für rollende Regensumme
const BATCH_SIZE         = 500;   // Stationen pro SQL-Transaktion

// KML-Namespaces (beide DWD-Varianten werden erkannt)
const NS_KML  = 'http://www.opengis.net/kml/2.2';
const NS_DWD1 = 'https://opendata.dwd.de/weather/lib/pointforecast_dwd_extension_V1_0.xsd';
const NS_DWD2 = 'http://www.dwd.de/namespaces/pointforecast/dwd_extension_V1_0';

// ─────────────────────────────────────────────────────────────────────────────
// BOOTSTRAP
// ─────────────────────────────────────────────────────────────────────────────

ini_set('memory_limit', '1G');
set_time_limit(0);
date_default_timezone_set('UTC');

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// ─────────────────────────────────────────────────────────────────────────────
// DATENBANK – Initialisierung & Schema
// ─────────────────────────────────────────────────────────────────────────────

function initDatabase(string $path): SQLite3
{
    $db = new SQLite3($path);
    // Performance-PRAGMAs
    $db->exec('PRAGMA synchronous  = OFF');
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA cache_size   = -65536');  // 64 MB
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA temp_store   = MEMORY');

    $db->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS stations (
            id        TEXT  PRIMARY KEY,
            name      TEXT  NOT NULL,
            lat       REAL  NOT NULL,
            lon       REAL  NOT NULL,
            elevation REAL  NOT NULL DEFAULT 0
        ) STRICT;
    SQL);

    $db->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS forecasts (
            station_id TEXT    NOT NULL,
            valid_time INTEGER NOT NULL,   -- Unix-Timestamp UTC
            TTT        REAL,               -- Temperatur [K]
            PPPP       REAL,               -- Luftdruck [Pa]
            RH         REAL,               -- Relative Feuchte [%]
            R101       REAL,               -- Niederschlag 1h [mm]
            Dur        REAL,               -- Sonnenscheindauer [s/h]
            Neff_cl    REAL,               -- Bedeckung Tief [%]
            Neff_cm    REAL,               -- Bedeckung Mittel [%]
            Neff_ch    REAL,               -- Bedeckung Hoch [%]
            N_total    REAL,               -- Gesamtbedeckung [%] → /12.5 = Okta
            wind_u     REAL,               -- Windvektor U [m/s]
            wind_v     REAL,               -- Windvektor V [m/s]
            FX1        REAL,               -- Böengeschwindigkeit [m/s]
            WW         REAL,               -- Signifikantes Wetter-Code
            PRIMARY KEY (station_id, valid_time),
            FOREIGN KEY (station_id) REFERENCES stations(id)
        ) STRICT;
    SQL);

    $db->exec('CREATE INDEX IF NOT EXISTS idx_fc_time    ON forecasts (valid_time)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_fc_station ON forecasts (station_id)');
    // Migration: Spalte nachrüsten falls DB älter
    try { $db->exec('ALTER TABLE forecasts ADD COLUMN N_total REAL'); } catch (\Exception) {}

    $db->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS meta (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL
        ) STRICT;
    SQL);

    $db->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS my_locations (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            label     TEXT    NOT NULL,
            lat       REAL    NOT NULL,
            lon       REAL    NOT NULL,
            elevation REAL    NOT NULL DEFAULT 0
        ) STRICT;
    SQL);

    $db->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS my_forecasts (
            location_id   INTEGER NOT NULL,
            valid_time    INTEGER NOT NULL,   -- Unix-Timestamp UTC
            temp_c        REAL,               -- Temperatur [°C]
            pressure_hpa  REAL,               -- Luftdruck [hPa]
            humidity      REAL,               -- Rel. Feuchte [%]
            rain_1h       REAL,               -- Niederschlag [mm/h]
            sunshine_pct  REAL,               -- Sonnenschein [% der Stunde]
            cloud_low     REAL,               -- Bedeckung Tief [%]
            cloud_mid     REAL,               -- Bedeckung Mittel [%]
            cloud_high    REAL,               -- Bedeckung Hoch [%]
            cloud_total   REAL,               -- Gesamtbedeckung [%] → /12.5 = Okta
            wind_u        REAL,               -- Wind U-Vektor [m/s]
            wind_v        REAL,               -- Wind V-Vektor [m/s]
            wind_speed    REAL,               -- Windgeschwindigkeit [m/s]
            wind_dir      REAL,               -- Windrichtung [°]
            wind_gust     REAL,               -- Böengeschwindigkeit [m/s]
            weather_code  INTEGER,            -- WW-Code (Nearest-Neighbor)
            rain_accum_6h REAL,               -- Rollende 6h-Summe [mm]
            PRIMARY KEY (location_id, valid_time),
            FOREIGN KEY (location_id) REFERENCES my_locations(id)
        ) STRICT;
    SQL);

    // Schema-Migration für bestehende Datenbanken (Fehler = Spalte existiert bereits, ignorieren)
    @$db->exec("ALTER TABLE forecasts    ADD COLUMN FX1         REAL");
    @$db->exec("ALTER TABLE my_forecasts ADD COLUMN wind_gust   REAL");
    @$db->exec("ALTER TABLE forecasts    ADD COLUMN N_total      REAL");
    @$db->exec("ALTER TABLE my_forecasts ADD COLUMN cloud_total  REAL");

    return $db;
}

// ─────────────────────────────────────────────────────────────────────────────
// STATIONSLISTE – Import Fixed-Width-Format (Grad.Minuten)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Konvertiert DWD-Format Grad.Minuten → Dezimalgrad.
 * Beispiel: 5230.5 → 52°30.5' → 52 + 30.5/60 = 52.5083°
 */
function gradMinToDecimal(float $gradMin): float
{
    $degrees = (int)($gradMin / 100);
    $minutes = $gradMin - ($degrees * 100);
    return $degrees + $minutes / 60.0;
}

function importStationList(SQLite3 $db): void
{
    logMsg("Lade DWD-Stationsliste von " . URL_STATION_CFG . " ...");

    $content = httpGet(URL_STATION_CFG);
    if ($content === null) {
        logMsg("WARNUNG: Stationsliste nicht abrufbar – wird übersprungen.");
        logMsg("Stationen werden später aus dem KMZ-Inhalt extrahiert.");
        return;
    }

    // Encoding: DWD-Dateien oft in ISO-8859-1
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
    }

    $lines = explode("\n", $content);
    $stmt  = $db->prepare(
        'INSERT OR REPLACE INTO stations (id,name,lat,lon,elevation) VALUES (:id,:name,:lat,:lon,:elev)'
    );

    $db->exec('BEGIN TRANSACTION');
    $count  = 0;
    $header = true;

    foreach ($lines as $line) {
        $line = rtrim($line);

        // Kopfzeilen und Trennlinien überspringen
        if ($header) {
            if (preg_match('/^\s*\d{5}/', $line)) {
                $header = false;
            } else {
                continue;
            }
        }

        if (strlen($line) < 20) {
            continue;
        }

        // Spalten per Whitespace aufteilen (robuster als fixe Positionen)
        $parts = preg_split('/\s+/', ltrim($line), 7);
        if (count($parts) < 5) {
            continue;
        }

        // Format: ID  ElvStart  ElvEnd  Breite  Laenge  Bundesland  Name
        // oder:   ID  Hoehe     Breite  Laenge  Name ...
        $id   = str_pad((string)(int)$parts[0], 5, '0', STR_PAD_LEFT);
        $elev = (float)$parts[1];
        $lat  = (float)$parts[2];
        $lon  = (float)$parts[3];
        $name = isset($parts[5]) ? trim($parts[5] . ' ' . ($parts[6] ?? '')) : trim($parts[4]);

        // Grad.Minuten erkennen: lat > 90 → muss kodiertes Format sein
        if (abs($lat) > 90.0) {
            $lat = gradMinToDecimal($lat);
        }
        if (abs($lon) > 180.0) {
            $lon = gradMinToDecimal($lon);
        }

        // Plausibilitätsprüfung für Deutschland
        if ($lat < 45.0 || $lat > 56.0 || $lon < 5.0 || $lon > 16.0) {
            // Andere Länder zulassen (MOSMIX ist nicht auf DE beschränkt)
        }

        bindAll($stmt, [
            ':id'   => [$id,   SQLITE3_TEXT],
            ':name' => [$name, SQLITE3_TEXT],
            ':lat'  => [$lat,  SQLITE3_FLOAT],
            ':lon'  => [$lon,  SQLITE3_FLOAT],
            ':elev' => [$elev, SQLITE3_FLOAT],
        ]);
        $stmt->execute();
        $stmt->reset();
        $count++;
    }

    $db->exec('COMMIT');
    logMsg("Stationsliste: {$count} Stationen importiert.");
}

// ─────────────────────────────────────────────────────────────────────────────
// KMZ DOWNLOAD (cURL-Stream)
// ─────────────────────────────────────────────────────────────────────────────

function downloadKmz(): void
{
    logMsg("Lade MOSMIX KMZ: " . URL_MOSMIX_KMZ);
    logMsg("Zieldatei: " . KMZ_LOCAL);

    $fp = fopen(KMZ_LOCAL, 'wb');
    if (!$fp) {
        throw new RuntimeException("Kann " . KMZ_LOCAL . " nicht zum Schreiben öffnen.");
    }

    $ch = curl_init(URL_MOSMIX_KMZ);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 1800,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_USERAGENT      => 'MOSMIX-Pipeline/1.0 (PHP)',
        CURLOPT_NOPROGRESS     => false,
    ]);

    $ok   = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if (!$ok || $code !== 200) {
        throw new RuntimeException("KMZ-Download fehlgeschlagen (HTTP {$code}): {$err}");
    }

    $mb = round(filesize(KMZ_LOCAL) / 1048576, 1);
    logMsg("Download abgeschlossen ({$mb} MB).");
}

// ─────────────────────────────────────────────────────────────────────────────
// MOSMIX KML PARSING – Streaming via XMLReader + SimpleXML
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Ermittelt den KML-Dateinamen innerhalb des KMZ-ZIP-Archivs.
 */
function findKmlEntry(string $kmzPath): string
{
    $zip = new ZipArchive();
    if ($zip->open($kmzPath) !== true) {
        throw new RuntimeException("ZIP konnte nicht geöffnet werden: {$kmzPath}");
    }

    $kmlEntry = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_ends_with(strtolower($name), '.kml')) {
            $kmlEntry = $name;
            break;
        }
    }
    $zip->close();

    if ($kmlEntry === null) {
        throw new RuntimeException("Kein .kml-Eintrag im KMZ-Archiv gefunden.");
    }

    logMsg("KML-Eintrag im KMZ: {$kmlEntry}");
    return $kmlEntry;
}

/**
 * Ermittelt den aktiven DWD-Namespace aus dem KML-Dokument.
 * Gibt den Namespace-URI für 'dwd' zurück.
 */
function detectDwdNamespace(string $kmzPath, string $kmlEntry): string
{
    $kmlPath = "zip://{$kmzPath}#{$kmlEntry}";
    $reader  = new XMLReader();
    $reader->open($kmlPath);

    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT) {
            $ns = $reader->namespaceURI;
            // Suche nach einem bekannten DWD-Element
            if ($reader->localName === 'ForecastTimeSteps') {
                $reader->close();
                return $ns ?: NS_DWD1;
            }
            // Attribute nach DWD-Namespace durchsuchen
            if ($reader->moveToFirstAttribute()) {
                do {
                    $val = $reader->value;
                    if ($val === NS_DWD1 || $val === NS_DWD2) {
                        $reader->close();
                        return $val;
                    }
                } while ($reader->moveToNextAttribute());
                $reader->moveToElement();
            }
        }
    }
    $reader->close();
    return NS_DWD1;
}

/**
 * Haupt-Parsing-Funktion:
 * – Liest Zeitschritte aus dem Dokument-Header
 * – Iteriert Placemarks per XMLReader (Streaming – geringer RAM-Bedarf)
 * – Parst je Placemark mit SimpleXML
 * – Zerlegt FF/DD sofort in wind_u/wind_v
 * – Schreibt blockweise via BEGIN/COMMIT
 */
function importForecasts(SQLite3 $db, string $kmzPath): void
{
    $kmlEntry = findKmlEntry($kmzPath);
    $nsDwd    = detectDwdNamespace($kmzPath, $kmlEntry);
    $kmlPath  = "zip://{$kmzPath}#{$kmlEntry}";

    logMsg("Erkannter DWD-Namespace: {$nsDwd}");

    // ── Schritt 1: Zeitschritte extrahieren ──────────────────────────────────
    $timeSteps  = extractTimeSteps($kmlPath, $nsDwd);
    $stepCount  = count($timeSteps);
    if ($stepCount === 0) {
        throw new RuntimeException("Keine Zeitschritte im KML gefunden.");
    }
    logMsg("Zeitschritte: {$stepCount} (von " . gmdate('Y-m-d H:i', $timeSteps[0])
        . " bis " . gmdate('Y-m-d H:i', end($timeSteps)) . " UTC)");

    // ── Schritt 2: Alte Forecasts löschen ────────────────────────────────────
    $db->exec("DELETE FROM forecasts");
    logMsg("Alte Forecast-Daten gelöscht.");

    // ── Schritt 3: Prepared Statements ───────────────────────────────────────
    $stmtStation = $db->prepare(
        'INSERT OR REPLACE INTO stations (id,name,lat,lon,elevation) VALUES (:id,:name,:lat,:lon,:elev)'
    );
    $stmtFc = $db->prepare(<<<SQL
        INSERT OR REPLACE INTO forecasts
            (station_id,valid_time,TTT,PPPP,RH,R101,Dur,Neff_cl,Neff_cm,Neff_ch,N_total,wind_u,wind_v,FX1,WW)
        VALUES
            (:sid,:vt,:TTT,:PPPP,:RH,:R101,:Dur,:Neff_cl,:Neff_cm,:Neff_ch,:N_total,:wind_u,:wind_v,:FX1,:WW)
    SQL);

    // ── Schritt 4: Placemarks streaming per XMLReader ─────────────────────────
    $reader = new XMLReader();
    $reader->open($kmlPath);

    $db->exec('BEGIN TRANSACTION');
    $stationCount = 0;
    $paramMap = getParamMap();

    while ($reader->read()) {
        // Nur kml:Placemark-Elemente verarbeiten
        if ($reader->nodeType !== XMLReader::ELEMENT
            || $reader->localName !== 'Placemark'
            || $reader->namespaceURI !== NS_KML) {
            continue;
        }

        // Gesamten Placemark-XML-String für SimpleXML extrahieren
        $pmXml = $reader->readOuterXml();
        if (empty($pmXml)) {
            continue;
        }

        // Namespaces für SimpleXML-Parsing voranstellen
        $pmXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<root xmlns:kml="' . NS_KML . '" xmlns:dwd="' . $nsDwd . '">'
            . $pmXml
            . '</root>';

        $pm = @simplexml_load_string($pmXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($pm === false) {
            continue;
        }

        // Station-Metadaten aus kml:Placemark lesen
        $pmKml       = $pm->children(NS_KML)->Placemark;
        $stationId   = trim((string)$pmKml->description);
        $stationName = trim((string)$pmKml->name);

        // Koordinaten: lon,lat,elevation (DWD-Format: Dezimalgrad)
        $coordStr = trim((string)$pmKml->Point->coordinates);
        $coords   = explode(',', $coordStr);
        $lon      = (float)($coords[0] ?? 0.0);
        $lat      = (float)($coords[1] ?? 0.0);
        $elev     = (float)($coords[2] ?? 0.0);

        if ($stationId === '' || $lat === 0.0) {
            continue;
        }

        // Station upsert
        bindAll($stmtStation, [
            ':id'   => [$stationId,   SQLITE3_TEXT],
            ':name' => [$stationName, SQLITE3_TEXT],
            ':lat'  => [$lat,         SQLITE3_FLOAT],
            ':lon'  => [$lon,         SQLITE3_FLOAT],
            ':elev' => [$elev,        SQLITE3_FLOAT],
        ]);
        $stmtStation->execute();
        $stmtStation->reset();

        // Forecast-Parameter extrahieren (KML-Elementnamen)
        $raw = parsePlacemarkParams($pmKml, $nsDwd, $paramMap);

        // KML-Alias → DB-Spaltenname umbiegen
        $raw['R101']    = $raw['RR1c']  ?? [];
        $raw['Dur']     = $raw['SunD1'] ?? [];
        $raw['Neff_cl'] = $raw['Nl']    ?? [];
        $raw['Neff_cm'] = $raw['Nm']    ?? [];
        $raw['Neff_ch'] = $raw['Nh']    ?? [];
        $raw['N_total'] = $raw['N']     ?? [];
        $raw['WW']      = $raw['ww']    ?? [];

        // Relative Feuchte aus Taupunkt berechnen (Magnus-Formel)
        // RH = 100 * exp(17.625*Td_C/(243.04+Td_C)) / exp(17.625*T_C/(243.04+T_C))
        $rhArray = [];
        for ($j = 0; $j < $stepCount; $j++) {
            $T_K  = $raw['TTT'][$j] ?? null;
            $Td_K = $raw['Td'][$j]  ?? null;
            if ($T_K !== null && $Td_K !== null) {
                $Tc  = $T_K  - 273.15;
                $Tdc = $Td_K - 273.15;
                $rh  = 100.0 * exp(17.625 * $Tdc / (243.04 + $Tdc))
                             / exp(17.625 * $Tc  / (243.04 + $Tc));
                $rhArray[] = min(100.0, max(0.0, round($rh, 1)));
            } else {
                $rhArray[] = null;
            }
        }
        $raw['RH'] = $rhArray;

        // Einen DB-Datensatz pro Zeitschritt schreiben
        for ($i = 0; $i < $stepCount; $i++) {
            $vt = $timeSteps[$i];

            // ── Windvektoren sofort aus FF/DD berechnen ───────────────────
            $FF     = $raw['FF'][$i]  ?? null;
            $DD     = $raw['DD'][$i]  ?? null;
            $windU  = null;
            $windV  = null;
            if ($FF !== null && $DD !== null) {
                $rad   = deg2rad($DD);
                $windU = -1.0 * $FF * sin($rad);
                $windV = -1.0 * $FF * cos($rad);
            }

            bindAll($stmtFc, [
                ':sid'    => [$stationId,              SQLITE3_TEXT],
                ':vt'     => [$vt,                     SQLITE3_INTEGER],
                ':TTT'    => [$raw['TTT'][$i]    ?? null, SQLITE3_FLOAT],
                ':PPPP'   => [$raw['PPPP'][$i]   ?? null, SQLITE3_FLOAT],
                ':RH'     => [$raw['RH'][$i]     ?? null, SQLITE3_FLOAT],
                ':R101'   => [$raw['R101'][$i]   ?? null, SQLITE3_FLOAT],
                ':Dur'    => [$raw['Dur'][$i]    ?? null, SQLITE3_FLOAT],
                ':Neff_cl'=> [$raw['Neff_cl'][$i]?? null, SQLITE3_FLOAT],
                ':Neff_cm'=> [$raw['Neff_cm'][$i]?? null, SQLITE3_FLOAT],
                ':Neff_ch'=> [$raw['Neff_ch'][$i]?? null, SQLITE3_FLOAT],
                ':N_total'=> [$raw['N_total'][$i]?? null, SQLITE3_FLOAT],
                ':wind_u' => [$windU,                  SQLITE3_FLOAT],
                ':wind_v' => [$windV,                  SQLITE3_FLOAT],
                ':FX1'    => [$raw['FX1'][$i]    ?? null, SQLITE3_FLOAT],
                ':WW'     => [$raw['WW'][$i]     ?? null, SQLITE3_FLOAT],
            ]);
            $stmtFc->execute();
            $stmtFc->reset();
        }

        $stationCount++;
        if ($stationCount % BATCH_SIZE === 0) {
            $db->exec('COMMIT');
            $db->exec('BEGIN TRANSACTION');
            logMsg("  {$stationCount} Stationen verarbeitet...");
        }
    }

    $db->exec('COMMIT');
    $reader->close();
    logMsg("Forecast-Import abgeschlossen: {$stationCount} Stationen.");

    // IssueTime und Model-referenceTime direkt aus dem KML-Header lesen
    [$issueTimeUtc, $referenceTimeUtc] = extractKmlHeaderTimes($kmlPath, $nsDwd);

    $stmtMeta = $db->prepare("INSERT OR REPLACE INTO meta (key,value) VALUES (:k,:v)");
    foreach ([
        'source_file'      => $kmlEntry,
        'issue_time_utc'   => $issueTimeUtc   ?? '',   // MOSMIX-Ausgabezeitpunkt (IssueTime)
        'model_run_utc'    => $referenceTimeUtc ?? '',  // NWP-Modelllauf (ICON referenceTime)
        'import_time'      => gmdate('Y-m-d\TH:i:s\Z'),
        'station_count'    => (string)$stationCount,
        'time_steps'       => (string)$stepCount,
        'valid_from_utc'   => gmdate('Y-m-d\TH:i:s\Z', $timeSteps[0]),
        'valid_to_utc'     => gmdate('Y-m-d\TH:i:s\Z', $timeSteps[$stepCount - 1]),
    ] as $k => $v) {
        $stmtMeta->bindValue(':k', $k, SQLITE3_TEXT);
        $stmtMeta->bindValue(':v', $v, SQLITE3_TEXT);
        $stmtMeta->execute();
        $stmtMeta->reset();
    }
}

/**
 * Extrahiert die Zeitschritte aus dem KML-Dokument-Header.
 * Gibt Array von Unix-Timestamps zurück.
 */
function extractTimeSteps(string $kmlPath, string $nsDwd): array
{
    $reader = new XMLReader();
    $reader->open($kmlPath);
    $timeSteps = [];
    $inForecastTimeSteps = false;

    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT
            && $reader->localName === 'ForecastTimeSteps') {
            $inForecastTimeSteps = true;
            continue;
        }

        if ($inForecastTimeSteps) {
            if ($reader->nodeType === XMLReader::ELEMENT
                && $reader->localName === 'TimeStep') {
                $reader->read();
                if ($reader->nodeType === XMLReader::TEXT) {
                    $ts = strtotime(trim($reader->value));
                    if ($ts !== false) {
                        $timeSteps[] = $ts;
                    }
                }
            }
            if ($reader->nodeType === XMLReader::END_ELEMENT
                && $reader->localName === 'ForecastTimeSteps') {
                break;
            }
        }
    }

    $reader->close();
    return $timeSteps;
}

/**
 * Liest IssueTime und erste Model-referenceTime aus dem KML-Header.
 * Gibt [issueTimeUtc, referenceTimeUtc] zurück (beide als ISO-8601-UTC-String oder null).
 */
function extractKmlHeaderTimes(string $kmlPath, string $nsDwd): array
{
    $reader        = new XMLReader();
    $reader->open($kmlPath);
    $issueTime     = null;
    $referenceTime = null;

    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT) continue;

        if ($reader->namespaceURI === $nsDwd && $reader->localName === 'IssueTime') {
            $reader->read();
            if ($reader->nodeType === XMLReader::TEXT) {
                $ts = strtotime(trim($reader->value));
                if ($ts !== false) $issueTime = gmdate('Y-m-d\TH:i:s\Z', $ts);
            }
        }

        if ($reader->namespaceURI === $nsDwd && $reader->localName === 'Model'
            && $referenceTime === null) {
            $ref = $reader->getAttribute('dwd:referenceTime');
            if ($ref) {
                $ts = strtotime($ref);
                if ($ts !== false) $referenceTime = gmdate('Y-m-d\TH:i:s\Z', $ts);
            }
        }

        // Abbruch sobald beide gefunden oder ForecastTimeSteps beginnt
        if ($issueTime && $referenceTime) break;
        if ($reader->localName === 'ForecastTimeSteps') break;
    }

    $reader->close();
    return [$issueTime, $referenceTime];
}

/**
 * Parst die Forecast-Parameter eines einzelnen Placemarks.
 * Gibt [elementName => float?[]] zurück.
 */
function parsePlacemarkParams(
    SimpleXMLElement $pmKml,
    string $nsDwd,
    array $paramMap
): array {
    $raw = [];
    $extData = $pmKml->ExtendedData;
    if ($extData === null) {
        return $raw;
    }

    foreach ($extData->children($nsDwd)->Forecast as $fc) {
        $attr = $fc->attributes($nsDwd);
        $elemName = (string)($attr['elementName'] ?? '');

        if (!isset($paramMap[$elemName])) {
            continue;
        }

        $valueStr = trim((string)$fc->children($nsDwd)->value);
        if ($valueStr === '') {
            continue;
        }

        $raw[$elemName] = array_map(
            static function (string $v): ?float {
                $v = trim($v);
                return ($v === '-' || $v === '') ? null : (float)$v;
            },
            preg_split('/\s+/', $valueStr) ?: []
        );
    }

    return $raw;
}

/**
 * Welche KML-Elemente werden importiert?
 * FF und DD → werden in wind_u/wind_v umgerechnet, nie direkt gespeichert.
 */
/**
 * Tatsächliche KML-Elementnamen laut DWD-MOSMIX-Spezifikation.
 * Abweichungen von den naiven Annahmen:
 *   ww   (klein) statt WW
 *   Nl/Nm/Nh     statt Neff_cl/Neff_cm/Neff_ch
 *   SunD1        statt Dur
 *   RR1c         statt R101  (tatsächliche Niederschlagsmenge mm/h)
 *   Td           = Taupunkt [K] → wird zu RH umgerechnet (Magnus-Formel)
 */
function getParamMap(): array
{
    return [
        'TTT'   => true,   // Temperatur 2m [K]
        'PPPP'  => true,   // Luftdruck [Pa]
        'Td'    => true,   // Taupunkt [K] → RH wird berechnet
        'RR1c'  => true,   // Niederschlag letzte Stunde [mm]
        'SunD1' => true,   // Sonnenscheindauer [s/h]
        'Nl'    => true,   // Bedeckung tief [%]
        'Nm'    => true,   // Bedeckung mittel [%]
        'Nh'    => true,   // Bedeckung hoch [%]
        'FF'    => true,   // Windgeschwindigkeit → Vektorzerlegung
        'DD'    => true,   // Windrichtung → Vektorzerlegung
        'FX1'   => true,   // Max. Böengeschwindigkeit letzte Stunde [m/s]
        'ww'    => true,   // Signifikantes Wetter (SYNOP-Code)
        'N'     => true,   // Gesamtbedeckung [%]
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// INTERPOLATIONS-ENGINE
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Haversine-Entfernung in Kilometern.
 */
function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    static $R = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a    = sin($dLat / 2) ** 2
          + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $R * 2.0 * asin(min(1.0, sqrt($a)));
}

/**
 * Pseudo-Distanz: Haversine + Höhenkorrektur.
 * 100 m Elevation-Delta → ELEV_PENALTY_KM Malus.
 */
function pseudoDistKm(
    float $targetLat, float $targetLon, float $targetElev,
    float $sLat,      float $sLon,      float $sElev
): float {
    $horiz   = haversineKm($targetLat, $targetLon, $sLat, $sLon);
    $vertPen = abs($targetElev - $sElev) / 100.0 * ELEV_PENALTY_KM;
    return $horiz + $vertPen;
}

/**
 * Sucht Kandidaten-Stationen im Umkreis SEARCH_RADIUS_DEG.
 * Gibt sortiertes Array [id, lat, lon, elevation, dist_km] zurück.
 */
function findCandidateStations(
    SQLite3 $db,
    float $lat,
    float $lon,
    float $elev
): array {
    $stmt = $db->prepare(<<<SQL
        SELECT id, lat, lon, elevation
        FROM   stations
        WHERE  lat BETWEEN :minLat AND :maxLat
          AND  lon BETWEEN :minLon AND :maxLon
    SQL);
    bindAll($stmt, [
        ':minLat' => [$lat - SEARCH_RADIUS_DEG, SQLITE3_FLOAT],
        ':maxLat' => [$lat + SEARCH_RADIUS_DEG, SQLITE3_FLOAT],
        ':minLon' => [$lon - SEARCH_RADIUS_DEG, SQLITE3_FLOAT],
        ':maxLon' => [$lon + SEARCH_RADIUS_DEG, SQLITE3_FLOAT],
    ]);

    $res        = $stmt->execute();
    $candidates = [];

    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $dist = pseudoDistKm($lat, $lon, $elev, $row['lat'], $row['lon'], $row['elevation']);
        $candidates[] = [
            'id'        => $row['id'],
            'lat'       => (float)$row['lat'],
            'lon'       => (float)$row['lon'],
            'elevation' => (float)$row['elevation'],
            'dist_km'   => $dist,
        ];
    }

    usort($candidates, static fn($a, $b) => $a['dist_km'] <=> $b['dist_km']);
    return $candidates;
}

/**
 * Inverse Distance Weighting (IDW) mit quadrierter Distanz als Gewicht.
 * Null-Werte werden übersprungen.
 *
 * @param array<array{0: float|null, 1: float}> $valDist  [[value, dist_km], ...]
 */
function idw(array $valDist): ?float
{
    $weightedSum = 0.0;
    $totalWeight = 0.0;

    foreach ($valDist as [$val, $dist]) {
        if ($val === null) {
            continue;
        }
        $w            = 1.0 / max($dist, 0.001) ** 2;
        $weightedSum += $val * $w;
        $totalWeight += $w;
    }

    return $totalWeight > 0.0 ? $weightedSum / $totalWeight : null;
}

/**
 * Lädt Forecast-Daten für eine Menge von Stations-IDs in einen Speicher-Index.
 * Rückgabe: [valid_time => [station_id => row]]
 */
function loadForecastIndex(SQLite3 $db, array $stationIds): array
{
    if (empty($stationIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($stationIds), '?'));
    $stmt = $db->prepare(<<<SQL
        SELECT station_id, valid_time,
               TTT, PPPP, RH, R101, Dur,
               Neff_cl, Neff_cm, Neff_ch, N_total,
               wind_u, wind_v, FX1, WW
        FROM   forecasts
        WHERE  station_id IN ({$placeholders})
        ORDER  BY valid_time ASC
    SQL);

    foreach ($stationIds as $idx => $sid) {
        $stmt->bindValue($idx + 1, $sid, SQLITE3_TEXT);
    }

    $res   = $stmt->execute();
    $index = [];

    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $index[(int)$row['valid_time']][$row['station_id']] = $row;
    }

    return $index;
}

// ─────────────────────────────────────────────────────────────────────────────
// MY_LOCATIONS AUS DATEI SYNCHRONISIEREN
// ─────────────────────────────────────────────────────────────────────────────

function syncMyLocations(SQLite3 $db): void
{
    $file = __DIR__ . '/locations.txt';
    if (!file_exists($file)) {
        logMsg("locations.txt nicht gefunden – my_locations unverändert.");
        return;
    }

    $lines   = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $entries = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Koordinaten-Zeile: "lat,lon" oder "lat,lon,elev"
        if (preg_match('/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)(?:\s*,\s*(-?\d+(?:\.\d+)?))?$/', $line, $m)) {
            $lat   = (float)$m[1];
            $lon   = (float)$m[2];
            $elev  = isset($m[3]) ? (float)$m[3] : 0.0;
            $label = "{$lat},{$lon}";
            $entries[] = compact('label', 'lat', 'lon', 'elev');
            continue;
        }

        // Stadtname → in cities-Tabelle nachschlagen
        $nameNorm = strtolower(trim($line));
        $stmt2 = $db->prepare(
            "SELECT name, lat, lon, elevation FROM cities
              WHERE LOWER(name) = :n OR name_ascii = :n
              ORDER BY population DESC LIMIT 1"
        );
        $stmt2->bindValue(':n', $nameNorm, SQLITE3_TEXT);
        $res2 = $stmt2->execute();
        $row  = $res2 ? $res2->fetchArray(SQLITE3_ASSOC) : false;
        if (!$row) {
            logMsg("  WARNUNG: Ort '{$line}' nicht in cities-DB gefunden – übersprungen.");
            continue;
        }
        $label = $row['name'];
        $lat   = (float)$row['lat'];
        $lon   = (float)$row['lon'];
        $elev  = (float)$row['elevation'];
        $entries[] = compact('label', 'lat', 'lon', 'elev');
    }

    // Tabelle leeren und neu befüllen (my_forecasts wird ebenfalls geleert,
    // da computeMyForecasts() alles neu berechnet)
    $db->exec("DELETE FROM my_forecasts");
    $db->exec("DELETE FROM my_locations");

    $stmt = $db->prepare(
        "INSERT INTO my_locations (label, lat, lon, elevation) VALUES (:label, :lat, :lon, :elev)"
    );
    foreach ($entries as $e) {
        $stmt->bindValue(':label', $e['label'], SQLITE3_TEXT);
        $stmt->bindValue(':lat',   $e['lat'],   SQLITE3_FLOAT);
        $stmt->bindValue(':lon',   $e['lon'],   SQLITE3_FLOAT);
        $stmt->bindValue(':elev',  $e['elev'],  SQLITE3_FLOAT);
        $stmt->execute();
        logMsg("  Ort: {$e['label']} ({$e['lat']}, {$e['lon']}, {$e['elev']} m)");
    }

    logMsg("  " . count($entries) . " Ort(e) in my_locations geladen.");
}

// ─────────────────────────────────────────────────────────────────────────────
// MY_FORECASTS BERECHNUNG
// ─────────────────────────────────────────────────────────────────────────────

function computeMyForecasts(SQLite3 $db): void
{
    // Alle Ziel-Standorte laden
    $locations = [];
    $res = $db->query("SELECT id, label, lat, lon, elevation FROM my_locations ORDER BY id");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $locations[] = $row;
    }

    if (empty($locations)) {
        logMsg("Keine my_locations definiert. Zeile(n) in my_locations eintragen und erneut ausführen.");
        return;
    }

    // Alle verfügbaren Zeitschritte laden
    $vtRes      = $db->query("SELECT DISTINCT valid_time FROM forecasts ORDER BY valid_time ASC");
    $validTimes = [];
    while ($vtRow = $vtRes->fetchArray(SQLITE3_NUM)) {
        $validTimes[] = (int)$vtRow[0];
    }

    if (empty($validTimes)) {
        logMsg("Keine Forecast-Daten in der DB. KMZ-Import prüfen.");
        return;
    }

    $stmtInsert = $db->prepare(<<<SQL
        INSERT OR REPLACE INTO my_forecasts
            (location_id, valid_time, temp_c, pressure_hpa, humidity,
             rain_1h, sunshine_pct, cloud_low, cloud_mid, cloud_high, cloud_total,
             wind_u, wind_v, wind_speed, wind_dir, wind_gust, weather_code, rain_accum_6h)
        VALUES
            (:loc, :vt, :temp_c, :phpa, :hum,
             :rain, :sun, :cl, :cm, :ch, :ctotal,
             :wu, :wv, :wspd, :wdir, :wgust, :wcode, :rain6h)
    SQL);

    foreach ($locations as $loc) {
        logMsg("  Berechne Standort: {$loc['label']} ({$loc['lat']}, {$loc['lon']}, {$loc['elevation']}m)");

        $candidates = findCandidateStations($db, (float)$loc['lat'], (float)$loc['lon'], (float)$loc['elevation']);

        if (empty($candidates)) {
            logMsg("  WARNUNG: Keine Stationen im Umkreis von " . SEARCH_RADIUS_DEG . "° für '{$loc['label']}'. Überspringe.");
            continue;
        }

        // IDW-Stationen: bis zu IDW_NEIGHBORS nächste
        $idwSet   = array_slice($candidates, 0, IDW_NEIGHBORS);
        $idwIds   = array_column($idwSet, 'id');
        $idwDists = array_column($idwSet, 'dist_km');

        // Nächste Station für Nearest-Neighbor WW (geografisch/orografisch)
        $nearestId = $candidates[0]['id'];

        // Alle benötigten IDs in einem Rutsch laden
        $allIds   = array_unique(array_merge($idwIds, [$nearestId]));
        $fcIndex  = loadForecastIndex($db, $allIds);

        logMsg("    IDW-Stationen: " . implode(', ', $idwIds)
            . " | NN-WW: {$nearestId}");

        $rainBuffer = [];   // Rollender 6h-Puffer für Regensumme
        $rowCount   = 0;
        $db->exec('BEGIN TRANSACTION');

        foreach ($validTimes as $vt) {
            $rows = $fcIndex[$vt] ?? [];

            // ── Hilfsclosure: Wertepaare [value, dist_km] für IDW aufbauen ─
            $valDist = static function (string $col) use ($rows, $idwIds, $idwDists): array {
                $out = [];
                foreach ($idwIds as $i => $sid) {
                    $val = isset($rows[$sid][$col]) && $rows[$sid][$col] !== null
                        ? (float)$rows[$sid][$col]
                        : null;
                    $out[] = [$val, $idwDists[$i]];
                }
                return $out;
            };

            // ── A) Lineare Parameter – IDW ───────────────────────────────
            $TTT_K   = idw($valDist('TTT'));
            $PPPP_Pa = idw($valDist('PPPP'));
            $RH      = idw($valDist('RH'));
            $R101    = idw($valDist('R101'));
            $Dur_s   = idw($valDist('Dur'));
            $cl      = idw($valDist('Neff_cl'));
            $cm      = idw($valDist('Neff_cm'));
            $ch      = idw($valDist('Neff_ch'));
            $ntotal  = idw($valDist('N_total'));

            // ── B) Wind – U/V-Vektoren + Böen interpolieren ──────────────
            // Die Vektoren wurden beim Import aus FF/DD berechnet; hier IDW.
            $wind_u = idw($valDist('wind_u'));
            $wind_v = idw($valDist('wind_v'));
            $gust   = idw($valDist('FX1'));

            // Rückrechnung: Geschwindigkeit und Richtung aus interpolierten Vektoren
            $windSpeed = null;
            $windDir   = null;
            if ($wind_u !== null && $wind_v !== null) {
                $windSpeed = sqrt($wind_u ** 2 + $wind_v ** 2);
                // atan2(-u, -v) → meteorologische Windrichtung (woher der Wind kommt)
                $windDir = fmod(rad2deg(atan2(-$wind_u, -$wind_v)) + 360.0, 360.0);
            }

            // ── C) WW – Kategoriales Nearest-Neighbor ────────────────────
            // NIEMALS mitteln: Code 50 (Regen) ∩ Code 10 (Nebel) = 30 (kein Sinn)
            $WW = null;
            // Nächste Station zuerst
            if (isset($rows[$nearestId]['WW']) && $rows[$nearestId]['WW'] !== null) {
                $WW = (int)(float)$rows[$nearestId]['WW'];
            }
            // Fallback: nächste Station mit gültigem WW
            if ($WW === null) {
                foreach ($candidates as $c) {
                    $sid = $c['id'];
                    if (isset($rows[$sid]['WW']) && $rows[$sid]['WW'] !== null) {
                        $WW = (int)(float)$rows[$sid]['WW'];
                        break;
                    }
                }
            }

            // ── Einheitenumrechnung ───────────────────────────────────────
            $temp_c      = $TTT_K   !== null ? round($TTT_K - 273.15, 2)          : null;
            $pressure_hpa= $PPPP_Pa !== null ? round($PPPP_Pa / 100.0, 2)         : null;
            // Sonnenscheindauer: Sekunden → Prozent der Stunde (0–100 %)
            $sunshine_pct= $Dur_s   !== null ? round(min(100.0, $Dur_s / 36.0), 1): null;

            // ── Rollende 6h-Regensumme ────────────────────────────────────
            $rainBuffer[] = $R101 ?? 0.0;
            if (count($rainBuffer) > RAIN_ACCUM_HOURS) {
                array_shift($rainBuffer);
            }
            $rain6h = round(array_sum($rainBuffer), 3);

            // ── Persistieren ──────────────────────────────────────────────
            bindAll($stmtInsert, [
                ':loc'   => [(int)$loc['id'],    SQLITE3_INTEGER],
                ':vt'    => [$vt,                SQLITE3_INTEGER],
                ':temp_c'=> [$temp_c,            SQLITE3_FLOAT],
                ':phpa'  => [$pressure_hpa,      SQLITE3_FLOAT],
                ':hum'   => [$RH,                SQLITE3_FLOAT],
                ':rain'  => [$R101,              SQLITE3_FLOAT],
                ':sun'   => [$sunshine_pct,      SQLITE3_FLOAT],
                ':cl'    => [$cl,                SQLITE3_FLOAT],
                ':cm'    => [$cm,                SQLITE3_FLOAT],
                ':ch'    => [$ch,                SQLITE3_FLOAT],
                ':ctotal'=> [$ntotal,            SQLITE3_FLOAT],
                ':wu'    => [$wind_u,            SQLITE3_FLOAT],
                ':wv'    => [$wind_v,            SQLITE3_FLOAT],
                ':wspd'  => [$windSpeed,         SQLITE3_FLOAT],
                ':wdir'  => [$windDir,           SQLITE3_FLOAT],
                ':wgust' => [$gust,              SQLITE3_FLOAT],
                ':wcode' => [$WW,                SQLITE3_INTEGER],
                ':rain6h'=> [$rain6h,            SQLITE3_FLOAT],
            ]);
            $stmtInsert->execute();
            $stmtInsert->reset();

            $rowCount++;
            if ($rowCount % 5000 === 0) {
                $db->exec('COMMIT');
                $db->exec('BEGIN TRANSACTION');
            }
        }

        $db->exec('COMMIT');
        logMsg("  {$rowCount} Zeilen für '{$loc['label']}' geschrieben.");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HILFSFUNKTIONEN
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Bindet alle Parameter eines assoziativen Arrays an ein Prepared Statement.
 * Erkennt null-Werte und setzt automatisch SQLITE3_NULL.
 *
 * @param array<string, array{0: mixed, 1: int}> $params  [':key' => [value, type]]
 */
function bindAll(SQLite3Stmt $stmt, array $params): void
{
    foreach ($params as $key => [$value, $type]) {
        if ($value === null) {
            $stmt->bindValue($key, null, SQLITE3_NULL);
        } else {
            $stmt->bindValue($key, $value, $type);
        }
    }
}

function stationsEmpty(SQLite3 $db): bool
{
    return (int)$db->querySingle("SELECT COUNT(*) FROM stations") === 0;
}

function httpGet(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'MOSMIX-Pipeline/1.0 (PHP)',
        CURLOPT_ENCODING       => '',   // Transparente Dekompression
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($body !== false && $code === 200) ? $body : null;
}

function logMsg(string $msg): void
{
    echo '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $msg . PHP_EOL;
    flush();
}

// ─────────────────────────────────────────────────────────────────────────────
// ENTRY POINT
// ─────────────────────────────────────────────────────────────────────────────

// Modus: "locations" überspringt Download + Import und berechnet nur my_locations neu.
// Aufruf: php pipeline.php locations
$modeLocationsOnly = in_array('locations', $argv ?? [], true);

try {
    $wallStart = microtime(true);
    $db = initDatabase(DB_PATH);

    if ($modeLocationsOnly) {
        logMsg("=== Nur Orte neu berechnen (kein Download) ===");
        if ((int)$db->querySingle("SELECT COUNT(*) FROM forecasts") === 0) {
            logMsg("FEHLER: Keine Forecast-Daten in DB. Erst vollständige Pipeline ausführen.");
            exit(1);
        }
        syncMyLocations($db);
        computeMyForecasts($db);
    } else {
        logMsg("=== MOSMIX-L Pipeline gestartet ===");

        // Schritt 1: KMZ herunterladen
        logMsg("[1/4] KMZ herunterladen...");
        downloadKmz();

        // Schritt 2: KMZ parsen und Forecasts importieren
        logMsg("[2/4] Forecasts importieren...");
        importForecasts($db, KMZ_LOCAL);

        // Schritt 3: Veraltete Stations-Einträge ohne Forecast-Daten bereinigen
        $db->exec("DELETE FROM stations WHERE id NOT IN (SELECT DISTINCT station_id FROM forecasts)");
        $n = (int)$db->querySingle("SELECT COUNT(*) FROM stations");
        logMsg("[3/4] Stations-Cleanup: {$n} aktive Stationen in DB.");

        // Schritt 4: Interpolation für my_locations
        logMsg("[4/4] Orte aus locations.txt synchronisieren und Vorhersage berechnen...");
        syncMyLocations($db);
        computeMyForecasts($db);
    }

    $elapsed = round(microtime(true) - $wallStart, 1);
    logMsg("=== Fertig in {$elapsed}s ===");

} catch (Throwable $e) {
    logMsg("FEHLER: " . $e->getMessage());
    logMsg("Datei: " . $e->getFile() . ":" . $e->getLine());
    exit(1);
}

<?php
declare(strict_types=1);

/**
 * Importiert Städte aus Wikidata (SPARQL) für DE, AT, CH.
 * Ausführung: php import_cities.php
 *
 * Quelle: Wikidata (CC0)  https://query.wikidata.org/
 *
 * Strategie Deutschland: 16 Einzelqueries (je Bundesland) statt einer
 * transitivem Gesamtabfrage – verhindert den SPARQL-Timeout.
 * Österreich + Schweiz: eine Query je Land (geringerer Umfang).
 */

require_once __DIR__ . '/lib.php';

ini_set('memory_limit', '512M');
set_time_limit(600);
date_default_timezone_set('UTC');

const SPARQL_URL = 'https://query.wikidata.org/sparql';
const MIN_POP    = 5000;

// ─── Hilfsfunktionen ─────────────────────────────────────────────────────────

function log_ci(string $msg): void
{
    echo '[' . gmdate('H:i:s') . '] ' . $msg . PHP_EOL;
    flush();
}

function toAscii(string $s): string
{
    static $map = [
        'ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'ae','Ö'=>'oe','Ü'=>'ue','ß'=>'ss',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','á'=>'a','ã'=>'a',
        'î'=>'i','ï'=>'i','í'=>'i','ì'=>'i','ô'=>'o','ó'=>'o','ò'=>'o','õ'=>'o',
        'û'=>'u','ú'=>'u','ù'=>'u','ç'=>'c','ñ'=>'n','ý'=>'y',
        'ž'=>'z','š'=>'s','č'=>'c','ř'=>'r','ł'=>'l','ø'=>'o','å'=>'a','æ'=>'ae',
    ];
    return strtolower(strtr($s, $map));
}

function sparqlQuery(string $sparql, int $timeout = 90): ?array
{
    $ch = curl_init(SPARQL_URL . '?' . http_build_query(['query' => $sparql]));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'MOSMIX-Pipeline/1.0 (local weather tool)',
        CURLOPT_HTTPHEADER     => ['Accept: application/sparql-results+json'],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$body) {
        log_ci("    SPARQL HTTP {$code}");
        return null;
    }
    $data = json_decode($body, true);
    return $data['results']['bindings'] ?? null;
}

// Extrahiert QID-Integer aus Wikidata-URI "http://www.wikidata.org/entity/Q12345"
function qidInt(string $uri): int
{
    return preg_match('/Q(\d+)$/', $uri, $m) ? (int)$m[1] : 0;
}

// ─── Datenbank vorbereiten ────────────────────────────────────────────────────

$db = openDatabase(DB_PATH);

$db->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS cities (
        id         INTEGER PRIMARY KEY,
        name       TEXT    NOT NULL,
        name_ascii TEXT    NOT NULL,
        lat        REAL    NOT NULL,
        lon        REAL    NOT NULL,
        elevation  INTEGER NOT NULL DEFAULT 0,
        country    TEXT    NOT NULL DEFAULT 'DE',
        admin1     TEXT    NOT NULL DEFAULT '',
        population INTEGER NOT NULL DEFAULT 0
    ) STRICT
SQL);
$db->exec('CREATE INDEX IF NOT EXISTS idx_cities_ascii ON cities (name_ascii COLLATE NOCASE)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_cities_name  ON cities (name  COLLATE NOCASE)');

$existing = (int)$db->querySingle("SELECT COUNT(*) FROM cities");
if ($existing > 0) {
    log_ci("Lösche {$existing} bestehende Einträge...");
    $db->exec("DELETE FROM cities");
}

$stmt = $db->prepare(<<<SQL
    INSERT OR REPLACE INTO cities
        (id, name, name_ascii, lat, lon, elevation, country, admin1, population)
    VALUES (:id, :name, :ascii, :lat, :lon, :elev, :country, :admin1, :pop)
SQL);

// ─── Zeilen in DB schreiben ───────────────────────────────────────────────────

function insertRows(SQLite3 $db, SQLite3Stmt $stmt, array $bindings,
                    string $cc, string $admin1, array &$seen): int
{
    $db->exec('BEGIN TRANSACTION');
    $count = 0;
    foreach ($bindings as $b) {
        $id = qidInt($b['item']['value'] ?? '');
        if (!$id || isset($seen[$id])) continue;
        $seen[$id] = true;

        $name = $b['itemLabel']['value'] ?? '';
        if (!$name || preg_match('/^Q\d+$/', $name)) continue;

        $lat  = (float)($b['lat']['value']  ?? 0.0);
        $lon  = (float)($b['lon']['value']  ?? 0.0);
        $pop  = (int)  ($b['pop']['value']  ?? 0);
        $elev = (int)  ($b['elev']['value'] ?? 0);

        $stmt->bindValue(':id',      $id,            SQLITE3_INTEGER);
        $stmt->bindValue(':name',    $name,          SQLITE3_TEXT);
        $stmt->bindValue(':ascii',   toAscii($name), SQLITE3_TEXT);
        $stmt->bindValue(':lat',     $lat,           SQLITE3_FLOAT);
        $stmt->bindValue(':lon',     $lon,           SQLITE3_FLOAT);
        $stmt->bindValue(':elev',    $elev,          SQLITE3_INTEGER);
        $stmt->bindValue(':country', $cc,            SQLITE3_TEXT);
        $stmt->bindValue(':admin1',  $admin1,        SQLITE3_TEXT);
        $stmt->bindValue(':pop',     $pop,           SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->reset();
        $count++;
    }
    $db->exec('COMMIT');
    return $count;
}

// ─── Deutschland: je Bundesland eine Query ───────────────────────────────────

$minPop = MIN_POP;

log_ci('=== Deutschland ===');

// Schritt 1: alle Bundesland-QIDs holen
$stateBindings = sparqlQuery(<<<SPARQL
SELECT ?state ?stateLabel WHERE {
  ?state wdt:P31 wd:Q1221156 .
  SERVICE wikibase:label { bd:serviceParam wikibase:language "de" }
}
ORDER BY ?stateLabel
SPARQL);

if (!$stateBindings) {
    log_ci('FEHLER: Bundesland-Liste konnte nicht geladen werden.');
} else {
    $seen = [];
    $deTotal = 0;
    log_ci(count($stateBindings) . ' Bundesländer gefunden.');

    // Schritt 2: pro Bundesland Städte abfragen
    foreach ($stateBindings as $sb) {
        $stateUri  = $sb['state']['value']      ?? '';
        $stateName = $sb['stateLabel']['value'] ?? '';
        $stateQid  = 'Q' . qidInt($stateUri);
        if (!qidInt($stateUri)) continue;

        $bindings = sparqlQuery(<<<SPARQL
SELECT ?item ?itemLabel ?lat ?lon ?pop ?elev WHERE {
  ?item wdt:P17  wd:Q183 ;
        wdt:P1082 ?pop ;
        wdt:P625  ?coord ;
        wdt:P131+ wd:{$stateQid} .
  FILTER (?pop >= {$minPop})
  OPTIONAL { ?item wdt:P2044 ?elev }
  BIND(geof:latitude(?coord)  AS ?lat)
  BIND(geof:longitude(?coord) AS ?lon)
  SERVICE wikibase:label { bd:serviceParam wikibase:language "de,en" }
}
SPARQL, timeout: 60);

        if ($bindings === null) {
            log_ci("  {$stateName}: Timeout/Fehler – übersprungen.");
            continue;
        }
        $n = insertRows($db, $stmt, $bindings, 'DE', $stateName, $seen);
        $deTotal += $n;
        log_ci("  {$stateName}: {$n} Orte ({$stateQid})");
    }
    log_ci("DE gesamt: {$deTotal} Orte.");
}

// ─── Österreich + Schweiz: eine Query je Land ────────────────────────────────

$singleCountries = [
    ['Q40',  'Q261543', 'AT', 'Österreich'],
    ['Q39',  'Q23058',  'CH', 'Schweiz'],
];

foreach ($singleCountries as [$cQid, $stateTypeQid, $cc, $label]) {
    log_ci("=== {$label} ===");
    $seen = [];

    $bindings = sparqlQuery(<<<SPARQL
SELECT DISTINCT ?item ?itemLabel ?lat ?lon ?pop ?stateLabel ?elev WHERE {
  ?item wdt:P17  wd:{$cQid} ;
        wdt:P1082 ?pop ;
        wdt:P625  ?coord .
  FILTER (?pop >= {$minPop})
  ?item wdt:P131+ ?state .
  ?state wdt:P31 wd:{$stateTypeQid} .
  OPTIONAL { ?item wdt:P2044 ?elev }
  BIND(geof:latitude(?coord)  AS ?lat)
  BIND(geof:longitude(?coord) AS ?lon)
  SERVICE wikibase:label { bd:serviceParam wikibase:language "de,en" }
}
ORDER BY DESC(?pop)
SPARQL);

    if ($bindings === null) {
        log_ci("  FEHLER: Query fehlgeschlagen.");
        continue;
    }
    log_ci('  ' . count($bindings) . ' Ergebnisse.');

    // Admin1 kommt hier aus stateLabel pro Zeile
    $db->exec('BEGIN TRANSACTION');
    $count = 0;
    foreach ($bindings as $b) {
        $id = qidInt($b['item']['value'] ?? '');
        if (!$id || isset($seen[$id])) continue;
        $seen[$id] = true;

        $name  = $b['itemLabel']['value']  ?? '';
        if (!$name || preg_match('/^Q\d+$/', $name)) continue;

        $lat   = (float)($b['lat']['value']        ?? 0.0);
        $lon   = (float)($b['lon']['value']        ?? 0.0);
        $pop   = (int)  ($b['pop']['value']        ?? 0);
        $elev  = (int)  ($b['elev']['value']       ?? 0);
        $admin = $b['stateLabel']['value']          ?? '';

        $stmt->bindValue(':id',      $id,            SQLITE3_INTEGER);
        $stmt->bindValue(':name',    $name,          SQLITE3_TEXT);
        $stmt->bindValue(':ascii',   toAscii($name), SQLITE3_TEXT);
        $stmt->bindValue(':lat',     $lat,           SQLITE3_FLOAT);
        $stmt->bindValue(':lon',     $lon,           SQLITE3_FLOAT);
        $stmt->bindValue(':elev',    $elev,          SQLITE3_INTEGER);
        $stmt->bindValue(':country', $cc,            SQLITE3_TEXT);
        $stmt->bindValue(':admin1',  $admin,         SQLITE3_TEXT);
        $stmt->bindValue(':pop',     $pop,           SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->reset();
        $count++;
    }
    $db->exec('COMMIT');
    log_ci("{$cc}: {$count} Orte importiert.");
}

// ─── Abschluss ────────────────────────────────────────────────────────────────

$total = (int)$db->querySingle("SELECT COUNT(*) FROM cities");
log_ci("=== Fertig: {$total} Orte in der Datenbank. ===");

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DWD MOSMIX · Wettervorhersage</title>
<script src="assets/chart.umd.min.js"></script>
<link rel="stylesheet" href="assets/leaflet.css">
<script src="assets/leaflet.js"></script>
<style>
/* ── Reset & Basis ──────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:       #f0f4f8;
  --card:     #ffffff;
  --border:   #dde1ea;
  --accent:   #2563eb;
  --accent2:  #7c3aed;
  --text:     #1e2535;
  --muted:    #64748b;
  --rain:     #1d4ed8;
  --snow:     #93c5fd;
  --storm:    #d97706;
  --warm:     #dc2626;
  --cold:     #1d4ed8;
  --radius:   10px;
  --shadow:   0 2px 12px rgba(0,0,0,.08);
}
html { font-size: 15px; }
body {
  font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  padding: 0 0 3rem;
}

/* ── Header ─────────────────────────────────────────────────────────── */
header {
  background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
  border-bottom: 1px solid #1d4ed8;
  padding: 1.25rem 1.5rem;
  display: flex;
  align-items: center;
  gap: .75rem;
}
header h1 { font-size: 1.25rem; font-weight: 600; letter-spacing: -.01em; color: #fff; }
header .sub { font-size: .8rem; color: rgba(255,255,255,.75); margin-top: .1rem; }
.logo { font-size: 2rem; line-height: 1; }

/* ── Haupt-Layout ───────────────────────────────────────────────────── */
main { max-width: 1100px; margin: 0 auto; padding: 1.5rem 1rem 0; }

/* ── Suchkarte ──────────────────────────────────────────────────────── */
.search-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 1.25rem 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: var(--shadow);
}
.search-card h2 {
  font-size: .85rem;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--muted);
  margin-bottom: 1rem;
}
.search-row {
  display: flex;
  gap: .75rem;
  flex-wrap: wrap;
}
.search-wrap {
  flex: 1 1 280px;
}
.search-wrap input {
  width: 100%;
  background: #f8fafc;
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text);
  font-size: .95rem;
  padding: .65rem 2.2rem .65rem 1rem;
  outline: none;
  transition: border-color .15s;
}
.search-wrap input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
.search-wrap input::placeholder { color: var(--muted); }
.search-clear {
  position: absolute;
  right: .6rem;
  top: 50%;
  transform: translateY(-50%);
  cursor: pointer;
  color: var(--muted);
  font-size: 1.1rem;
  line-height: 1;
  display: none;
  user-select: none;
}
.search-clear:hover { color: var(--text); }
.autocomplete-list {
  position: absolute;
  top: calc(100% + 4px);
  left: 0; right: 0;
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 8px;
  max-height: 260px;
  overflow-y: auto;
  z-index: 100;
  box-shadow: 0 8px 24px rgba(0,0,0,.12);
  display: none;
}
.autocomplete-list.open { display: block; }
.ac-item {
  padding: .6rem 1rem;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--border);
  transition: background .1s;
}
.ac-item:last-child { border-bottom: none; }
.ac-item:hover, .ac-item.active { background: #eff6ff; }
.ac-name { font-weight: 500; }
.ac-detail { font-size: .8rem; color: var(--muted); }
.ac-pop { font-size: .75rem; color: var(--muted); white-space: nowrap; }

/* Koordinaten-Felder */
.coords-row {
  display: flex;
  gap: .5rem;
  flex: 0 0 auto;
  align-items: stretch;
}
.coords-row input {
  width: 72px;
  background: #f8fafc;
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text);
  font-size: .82rem;
  padding: .5rem .4rem;
  outline: none;
  transition: border-color .15s;
  text-align: center;
}
.coords-row input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
.coords-row label { font-size: .72rem; color: var(--muted); display: block; margin-bottom: .2rem; }
.coords-group { display: flex; flex-direction: column; }
#btnGeolocate {
  padding: .5rem .6rem;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #f8fafc;
  color: var(--text);
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
  align-self: flex-end;
}

.btn-primary {
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: .65rem 1.4rem;
  font-size: .95rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  align-self: flex-end;
  transition: opacity .15s;
}
.btn-primary:hover { opacity: .88; }
.btn-primary:disabled { opacity: .45; cursor: default; }

/* ── Gespeicherte Orte ──────────────────────────────────────────────── */
.bookmarks-section {
  margin-bottom: 1.5rem;
  display: none;
}
.bookmarks-label {
  font-size: .75rem;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--muted);
  margin-bottom: .6rem;
}
.bookmarks-list { display: flex; flex-wrap: wrap; gap: .5rem; }
.btn-bookmark {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 20px;
  color: var(--text);
  font-size: .85rem;
  padding: .35rem .9rem;
  cursor: pointer;
  transition: border-color .15s, background .15s;
  white-space: nowrap;
}
.btn-bookmark:hover { border-color: var(--accent); background: #eff6ff; }
.btn-bookmark.active { border-color: var(--accent); background: #dbeafe; color: #1d4ed8; font-weight: 600; }

/* ── Meteogramm ─────────────────────────────────────────────────────── */
.meteogram-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: .5rem 1rem .75rem;
  margin-bottom: 1.5rem;
  box-shadow: var(--shadow);
  height: 480px;
  position: relative;
}
#meteogramScrollWrap {
  overflow-x: auto;
  overflow-y: hidden;
  height: 100%;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
  scrollbar-color: var(--border) transparent;
}
#meteogramInner {
  position: relative;
  height: 100%;
  /* min-width wird per JS aus der Datenpunktanzahl gesetzt */
}
#meteogramCanvas { display: block; width: 100%; height: 100%; }

/* ── Ergebnis-Karte ─────────────────────────────────────────────────── */
#result { display: none; }
.result-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: .5rem;
  margin-bottom: 1rem;
}
.result-title { font-size: 1.1rem; font-weight: 600; }
.result-meta { font-size: .78rem; color: var(--muted); margin-top: .25rem; }
.result-meta span { margin-right: 1rem; }
.data-age { padding: .2rem .6rem; border-radius: 20px; font-size: .75rem; font-weight: 500; }
.data-age.fresh { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.data-age.stale { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
.db-info-bar { font-size: .72rem; color: var(--muted); background: #f8fafc; border: 1px solid var(--border);
  border-radius: 6px; padding: .35rem .75rem; margin-bottom: 1rem; display: flex; flex-wrap: wrap; gap: .3rem 1.2rem; }
.db-info-bar span { white-space: nowrap; }

/* ── Accordion ──────────────────────────────────────────────────────── */
.accordion-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: .55rem .9rem; cursor: pointer; user-select: none;
  background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); font-size: .85rem; font-weight: 500;
  color: var(--text); box-shadow: var(--shadow); margin-top: .75rem;
}
.accordion-header:hover { background: #f1f5f9; }
.accordion-arrow { transition: transform .2s; font-style: normal; }
.accordion-arrow.open { transform: rotate(180deg); }
.accordion-body { overflow: hidden; max-height: 0; transition: max-height .3s ease; }
.accordion-body.open { max-height: 9999px; }

/* ── Radar-Panel ────────────────────────────────────────────────────── */
#radarMap { height: 380px; background: #e8eef4; }
.radar-controls {
  display: flex; align-items: center; gap: .6rem; flex-wrap: wrap;
  padding: .5rem .75rem; background: var(--card);
  border: 1px solid var(--border); border-top: none;
  border-radius: 0 0 var(--radius) var(--radius);
  box-shadow: var(--shadow);
}
.radar-btn {
  padding: .25rem .7rem; border: 1px solid var(--border); border-radius: 5px;
  background: var(--card); cursor: pointer; font-size: .8rem; color: var(--text);
}
.radar-btn:hover { background: #f1f5f9; }
.radar-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }
#radarTimeline {
  flex: 1; min-width: 120px; accent-color: var(--accent);
}
#radarTimestamp {
  font-size: .78rem; color: var(--muted); white-space: nowrap; min-width: 9rem;
}
.radar-speed-wrap {
  display: flex; align-items: center; gap: .35rem; margin-left: auto;
}
.radar-speed-wrap input[type=range] { width: 80px; accent-color: var(--accent); }
.radar-speed-wrap span { font-size: .72rem; color: var(--muted); min-width: 3.2rem; text-align: right; }
.radar-nowcast-badge {
  font-size: .7rem; padding: .1rem .4rem; border-radius: 3px;
  background: #dbeafe; color: #1d4ed8; font-weight: 600;
}
.radar-map-wrap { position: relative; }
.radar-map-spinner {
  position: absolute; inset: 0; display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: .75rem;
  background: rgba(232,238,244,.8); z-index: 1000;
}
.radar-spin-ring {
  width: 44px; height: 44px;
  border: 4px solid rgba(37,99,235,.2); border-top-color: #2563eb;
  border-radius: 50%; animation: spin .8s linear infinite;
}
.radar-spin-label { font-size: .85rem; color: #2563eb; font-weight: 500; }
.radar-fs-btn {
  position: absolute; top: 8px; right: 8px; z-index: 1001;
  background: rgba(255,255,255,.88); border: 1px solid var(--border);
  border-radius: 5px; padding: 3px 8px; cursor: pointer;
  font-size: 1rem; line-height: 1; backdrop-filter: blur(3px);
  color: var(--text);
}
.radar-fs-btn:hover { background: #fff; }
/* ── DWD Modal ──────────────────────────────────────────────────────── */
#dwdModal {
  position: fixed; inset: 0; z-index: 9999;
  background: rgba(0,0,0,.55);
  display: none; align-items: center; justify-content: center;
}
#dwdModalBox {
  width: 96vw; height: 94vh;
  background: var(--card); border-radius: var(--radius);
  box-shadow: 0 24px 64px rgba(0,0,0,.45);
  display: flex; flex-direction: column; overflow: hidden;
}
#dwdModalHeader {
  display: flex; align-items: center; justify-content: space-between;
  padding: .45rem 1rem; border-bottom: 1px solid var(--border);
  font-size: .88rem; font-weight: 600; flex-shrink: 0;
}
#dwdModalMap { flex: 1; min-height: 0; background: #e8eef4; }
#dwdModalBox .radar-controls { flex-shrink: 0; border-radius: 0; border-top: 1px solid var(--border); border-left: none; border-right: none; border-bottom: none; }

/* ── Forecast-Tabelle ───────────────────────────────────────────────── */
.table-wrap {
  background: var(--card);
  border: 1px solid var(--border);
  border-top: none;
  border-radius: 0 0 var(--radius) var(--radius);
  box-shadow: var(--shadow);
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
table {
  width: 100%;
  border-collapse: collapse;
  font-size: .88rem;
  white-space: nowrap;
}
thead th {
  padding: .6rem .85rem;
  background: #f8fafc;
  color: var(--muted);
  font-size: .73rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  font-weight: 600;
  border-bottom: 1px solid var(--border);
  text-align: right;
}
thead th:first-child, thead th:nth-child(2) { text-align: left; }
tbody tr {
  border-bottom: 1px solid #f1f5f9;
  transition: background .1s;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #f8fbff; }
tbody tr.current-hour { background: #eff6ff; border-left: 3px solid var(--accent); }
td {
  padding: .55rem .85rem;
  text-align: right;
  vertical-align: middle;
}
td:first-child { text-align: left; font-weight: 600; font-variant-numeric: tabular-nums; }
td:nth-child(2) { text-align: left; }

.ww-cell { display: flex; align-items: center; gap: .5rem; }
.ww-icon { font-size: 1.25rem; line-height: 1; flex-shrink: 0; }
.ww-label { font-size: .82rem; }

/* Temperatur-Farbgebung per CSS-Variable --tf */
.temp-cell {
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: color-mix(in srgb, var(--cold) calc(var(--tf) * 1%), var(--warm) calc((100 - var(--tf)) * 1%));
}

.wind-cell { font-variant-numeric: tabular-nums; }
.wind-dir  { color: var(--muted); font-size: .8rem; }

.rain-cell { color: var(--rain); font-variant-numeric: tabular-nums; }
.rain-cell.none { color: var(--muted); }

.cloud-bar {
  display: inline-flex;
  gap: 2px;
  align-items: center;
}
.cloud-seg {
  height: 10px;
  border-radius: 2px;
  background: #e2e8f0;
  position: relative;
  overflow: hidden;
}
.cloud-seg-fill {
  position: absolute;
  top: 0; left: 0; height: 100%;
  border-radius: 2px;
}
.cloud-low-fill  { background: #475569; }
.cloud-mid-fill  { background: #94a3b8; }
.cloud-high-fill { background: #cbd5e1; }

.sunshine-bar {
  display: inline-block;
  width: 48px;
  height: 8px;
  border-radius: 4px;
  background: #e2e8f0;
  vertical-align: middle;
  position: relative;
  overflow: hidden;
}
.sunshine-fill {
  position: absolute;
  top: 0; left: 0; height: 100%;
  background: linear-gradient(90deg, #fbbf24, #f59e0b);
  border-radius: 4px;
}

/* ── Spinner / Status ───────────────────────────────────────────────── */
#status {
  text-align: center;
  padding: 2rem;
  color: var(--muted);
  font-size: .9rem;
  display: none;
}
.spinner {
  display: inline-block;
  width: 24px; height: 24px;
  border: 3px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin .7s linear infinite;
  vertical-align: middle;
  margin-right: .5rem;
}
@keyframes spin { to { transform: rotate(360deg); } }

.error-box {
  background: #fee2e2;
  border: 1px solid #fca5a5;
  color: #991b1b;
  border-radius: 8px;
  padding: .85rem 1.1rem;
  font-size: .9rem;
  margin: 1rem 0;
  display: none;
}

/* ── Footer ─────────────────────────────────────────────────────────── */
footer {
  text-align: center;
  color: var(--muted);
  font-size: .75rem;
  padding-top: 2rem;
}
</style>
</head>
<body>

<header>
  <div class="logo">🌤️</div>
  <div>
    <h1>DWD MOSMIX Wettervorhersage</h1>
    <div class="sub">Interpolierte Stundenwerte · Quelle: Deutscher Wetterdienst</div>
  </div>
</header>

<main>

  <!-- Suchkarte -->
  <div class="search-card">
    <h2>Standort auswählen</h2>
    <div class="search-row">
      <!-- Ortssuche -->
      <div class="search-wrap">
        <label for="searchInput" style="font-size:.75rem;color:var(--muted);display:block;margin-bottom:.2rem">Ort</label>
        <div style="position:relative">
          <input type="text" id="searchInput" placeholder="Ort suchen, z.B. Augsburg …" autocomplete="off" spellcheck="false">
          <span class="search-clear" id="searchClear" title="Eingabe löschen">✕</span>
          <div class="autocomplete-list" id="acList"></div>
        </div>
      </div>

      <!-- Manuelle Koordinaten -->
      <div class="coords-row">
        <div class="coords-group">
          <label for="inLat">Breite</label>
          <input type="number" id="inLat" placeholder="48.137" step="0.001" min="-90"  max="90">
        </div>
        <div class="coords-group">
          <label for="inLon">Länge</label>
          <input type="number" id="inLon" placeholder="11.576" step="0.001" min="-180" max="180">
        </div>
        <div class="coords-group">
          <label for="inElev">Höhe m</label>
          <input type="number" id="inElev" placeholder="519" step="1" min="-500" max="9000">
        </div>
        <div class="coords-group">
          <label>&nbsp;</label>
          <button id="btnGeolocate" onclick="useMyLocation()" title="Aktuellen Standort verwenden">📍</button>
        </div>
      </div>

      <button class="btn-primary" id="btnLoad" onclick="loadForecast()">
        Vorhersage laden
      </button>
    </div>
  </div>

  <!-- Gespeicherte Orte (nur sichtbar wenn my_locations befüllt) -->
  <div class="db-info-bar" id="dbInfoBar" style="display:none"></div>

  <div class="bookmarks-section" id="bookmarksSection">
    <div class="bookmarks-label">📌 Gespeicherte Orte</div>
    <div class="bookmarks-list" id="bookmarksList"></div>
  </div>

  <div class="bookmarks-section" id="recentSection">
    <div class="bookmarks-label">🕐 Zuletzt gesucht</div>
    <div class="bookmarks-list" id="recentList"></div>
  </div>

  <!-- Status / Spinner -->
  <div id="status"></div>
  <div class="error-box" id="errorBox"></div>

  <!-- Ergebnis -->
  <div id="result">
    <div class="result-header">
      <div>
        <div class="result-title" id="resultTitle">—</div>
        <div class="result-meta" id="resultMeta"></div>
      </div>
      <div id="dataAge"></div>
    </div>
    <!-- Toggle + Sonneninfo + Meteogramm -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.4rem;margin-bottom:.35rem">
      <div id="sunTimesBar" style="display:none;font-size:.72rem;color:var(--muted);flex-wrap:wrap;gap:.4rem .6rem"></div>
      <button id="windUnitToggle" onclick="toggleWindUnit()"
        style="font-size:.75rem;padding:.2rem .6rem;border:1px solid var(--border);border-radius:4px;background:var(--card);color:var(--muted);cursor:pointer;margin-left:auto">
        Wind: kn → m/s
      </button>
    </div>
    <div class="meteogram-card" style="position:relative">
      <div id="meteogramScrollWrap">
        <div id="meteogramInner">
          <canvas id="meteogramCanvas"></canvas>
          <div id="iconOverlay" style="position:absolute;inset:0;pointer-events:none;overflow:hidden"></div>
        </div>
      </div>
      <div id="iconTip" style="display:none;position:fixed;pointer-events:none;
        background:#fff;border:1px solid #dde1ea;border-radius:6px;
        padding:.3rem .55rem;font-size:.72rem;color:#334155;
        box-shadow:0 2px 6px rgba(0,0,0,.1);white-space:nowrap;z-index:10"></div>
    </div>

    <!-- Accordion Radar -->
    <div class="accordion-header" id="radarHeader" onclick="toggleRadar(this)">
      <span>🌧 Niederschlagsradar</span>
      <i class="accordion-arrow" id="radarArrow">▼</i>
    </div>
    <div class="accordion-body" id="radarBody">
      <div id="radarMap"></div>
      <div class="radar-controls">
        <button class="radar-btn" id="radarPlayBtn" onclick="radarTogglePlay()">▶ Play</button>
        <input type="range" id="radarTimeline" min="0" value="0" step="1"
               oninput="radarSeek(+this.value)">
        <span id="radarTimestamp">—</span>
        <span class="radar-nowcast-badge" id="radarNowcastBadge" style="display:none">Nowcast</span>
        <span class="radar-speed-wrap">
          <input type="range" id="radarSpeedSlider" min="50" max="800" value="420" step="10"
                 oninput="radarSetSpeed(+this.value)">
          <span id="radarSpeedVal">420 ms</span>
        </span>
      </div>
    </div>

    <!-- Accordion DWD WMS Radar (animiert, RV-Produkt mit TIME-Parameter) -->
    <div class="accordion-header" onclick="toggleDwdRadar(this)">
      <span>📡 DWD Niederschlagsradar (animiert)</span>
      <i class="accordion-arrow" id="dwdRadarArrow">▼</i>
    </div>
    <div class="accordion-body" id="dwdRadarBody">
      <div class="radar-map-wrap">
        <div id="dwdRadarMap" style="height:380px;background:#e8eef4"></div>
        <div id="dwdSpinner" class="radar-map-spinner" style="display:none">
          <div class="radar-spin-ring"></div>
          <div class="radar-spin-label">Lade Zeitreihe …</div>
        </div>
        <button class="radar-fs-btn" onclick="dwdOpenModal()" title="Vollbild">⛶</button>
      </div>
      <div class="radar-controls">
        <button class="radar-btn" id="dwdPlayBtn" onclick="dwdTogglePlay()">▶ Play</button>
        <input type="range" id="dwdTimeline" min="0" value="0" step="1"
               oninput="dwdSeek(+this.value)" style="flex:1;min-width:80px">
        <span id="dwdTimestamp" style="font-size:.78rem;color:var(--muted)">—</span>
        <span class="radar-nowcast-badge" id="dwdNowcastBadge" style="display:none">Vorhersage</span>
        <button class="radar-btn" onclick="dwdRadarRefresh()" title="Neu laden">🔄</button>
        <span class="radar-speed-wrap">
          <input type="range" id="dwdSpeedSlider" min="50" max="800" value="320" step="10"
                 oninput="dwdSetSpeed(+this.value)">
          <span id="dwdSpeedVal">320 ms</span>
        </span>
        <span style="font-size:.7rem;color:var(--muted)">DWD RV · 5 min</span>
      </div>
    </div>

    <!-- Accordion Stundentabelle -->
    <div class="accordion-header" onclick="toggleTable(this)">
      <span>📋 Stundenwerte</span>
      <i class="accordion-arrow" id="tableArrow">▼</i>
    </div>
    <div class="accordion-body" id="tableBody">
    <div class="table-wrap">
      <table id="forecastTable">
        <thead>
          <tr>
            <th>Uhrzeit</th>
            <th>Wetter</th>
            <th title="Temperatur">Temp °C</th>
            <th title="Windgeschwindigkeit und -richtung" id="thWindUnit">Wind kn</th>
            <th title="Windrichtung (woher)">Richtung</th>
            <th title="Niederschlag letzte Stunde">Regen mm</th>
            <th title="Rollende 6-Stunden-Summe">∑6h mm</th>
            <th title="Relative Luftfeuchtigkeit">Feuchte %</th>
            <th title="Luftdruck auf Stationshöhe">Druck hPa</th>
            <th title="Sonnenscheindauer als Anteil der Stunde">☀️ %</th>
            <th title="Bewölkung tief/mittel/hoch">Wolken</th>
          </tr>
        </thead>
        <tbody id="forecastBody"></tbody>
      </table>
    </div>
    </div><!-- /accordion-body -->
  </div>

</main>

<footer>
  Datenquelle: <strong>Deutscher Wetterdienst</strong> · MOSMIX-L ·
  Berechnungsmodell: Inverse Distance Weighting (IDW) + Vektorinterpolation Wind
</footer>

<script>
// ─── Zustand ─────────────────────────────────────────────────────────────────
let acDebounce  = null;
let selectedCity = null;
let _acResults  = [];   // letzte Autocomplete-Treffer, Auswahl per Index

// ─── Autocomplete ─────────────────────────────────────────────────────────────
const searchInput = document.getElementById('searchInput');
const acList      = document.getElementById('acList');
const searchClear = document.getElementById('searchClear');

function updateClearBtn() {
  searchClear.style.display = searchInput.value ? 'block' : 'none';
}
searchClear.addEventListener('click', () => {
  searchInput.value = '';
  closeAc();
  updateClearBtn();
  searchInput.focus();
});

searchInput.addEventListener('input', () => {
  updateClearBtn();
  clearTimeout(acDebounce);
  const q = searchInput.value.trim();
  if (q.length < 2) { closeAc(); return; }
  acDebounce = setTimeout(() => fetchSuggestions(q), 220);
});

searchInput.addEventListener('keydown', e => {
  const items = acList.querySelectorAll('.ac-item');
  const active = acList.querySelector('.ac-item.active');
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    const next = active ? active.nextElementSibling : items[0];
    if (next) { active?.classList.remove('active'); next.classList.add('active'); }
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    const prev = active ? active.previousElementSibling : items[items.length - 1];
    if (prev) { active?.classList.remove('active'); prev.classList.add('active'); }
  } else if (e.key === 'Enter') {
    e.preventDefault();
    const sel = acList.querySelector('.ac-item.active') || acList.querySelector('.ac-item');
    if (sel) sel.click();
    else loadForecast();
  } else if (e.key === 'Escape') {
    closeAc();
  }
});

document.addEventListener('click', e => {
  if (!e.target.closest('.search-wrap') && !e.target.closest('#btnLoad')) closeAc();
});

async function fetchSuggestions(q) {
  try {
    const res  = await fetch(`api.php?action=search&q=${encodeURIComponent(q)}&limit=8`);
    const data = await res.json();
    renderAc(data);
  } catch { closeAc(); }
}

function renderAc(items) {
  if (!items.length) { closeAc(); return; }
  _acResults = items;
  acList.innerHTML = items.map((c, i) => {
    const pop    = c.population > 0 ? fmtPop(c.population) : '';
    const detail = [c.admin1, c.country !== 'DE' ? c.country : ''].filter(Boolean).join(' · ');
    return `<div class="ac-item${i === 0 ? ' active' : ''}" data-idx="${i}" onclick="selectCity(${i})">
      <div>
        <div class="ac-name">${esc(c.name)}</div>
        <div class="ac-detail">${esc(detail)}${c.elevation ? ` · ${c.elevation} m` : ''}</div>
      </div>
      ${pop ? `<div class="ac-pop">${pop} Einw.</div>` : ''}
    </div>`;
  }).join('');
  acList.classList.add('open');
}

function selectCity(idx) {
  const city = _acResults[idx];
  if (city) applyCity(city);
}

function applyCity(city) {
  selectedCity = city;
  searchInput.value = city.name + (city.admin1 ? `, ${city.admin1}` : '');
  updateClearBtn();
  document.getElementById('inLat').value  = city.lat.toFixed(4);
  document.getElementById('inLon').value  = city.lon.toFixed(4);
  document.getElementById('inElev').value = city.elevation;
  closeAc();
  loadForecast();
}

function closeAc() {
  acList.classList.remove('open');
  // Inhalt NICHT löschen — wird bei nächster Suche neu befüllt
}

// ─── Vorhersage laden ─────────────────────────────────────────────────────────
async function reverseGeocode(lat, lon) {
  try {
    const r = await fetch(
      `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json&accept-language=de`
    ).then(r => r.json());
    if (r.error) return null;
    const a = r.address ?? {};
    // Ort: Stadt/Gemeinde/Dorf
    const city = a.city ?? a.town ?? a.village ?? a.hamlet ?? a.municipality ?? a.county ?? '';
    // Stadtteil nur wenn es sich vom Ort unterscheidet
    const district = a.city ? (a.suburb ?? a.quarter ?? a.city_district ?? '') : '';
    // Region: Bundesland / Staat
    const region = a.state ?? '';
    const parts = district ? [district, city] : [city, region];
    return parts.filter(Boolean).join(', ') || null;
  } catch (_) { return null; }
}

function useMyLocation() {
  const btn = document.getElementById('btnGeolocate');
  if (!window.isSecureContext) {
    showError('GPS erfordert HTTPS – bitte die Seite über https:// aufrufen.');
    return;
  }
  if (!navigator.geolocation) {
    showError('Geolokalisierung wird von diesem Browser nicht unterstützt.');
    return;
  }
  btn.disabled = true;
  btn.textContent = '⏳';
  navigator.geolocation.getCurrentPosition(
    async pos => {
      const lat = pos.coords.latitude;
      const lon = pos.coords.longitude;
      document.getElementById('inLat').value = lat.toFixed(4);
      document.getElementById('inLon').value = lon.toFixed(4);

      const elevEl = document.getElementById('inElev');
      if (!elevEl.value) {
        if (pos.coords.altitude != null) {
          elevEl.value = Math.round(pos.coords.altitude);
        } else {
          // Höhe per API nachschlagen (SRTM 90 m, kein API-Key nötig)
          try {
            const r = await fetch(
              `https://api.opentopodata.org/v1/srtm90m?locations=${lat},${lon}`
            ).then(r => r.json());
            const elev = r?.results?.[0]?.elevation;
            if (elev != null) elevEl.value = Math.round(elev);
          } catch (_) { /* kein Elevation-Wert – Feld bleibt leer */ }
        }
      }

      // Ortsname per Rückwärtssuche ins Suchfeld
      const name = await reverseGeocode(lat, lon);
      if (name) searchInput.value = name;

      btn.disabled = false;
      btn.textContent = '📍';
      loadForecast();
    },
    err => {
      btn.disabled = false;
      btn.textContent = '📍';
      const msgs = {
        1: 'Standortzugriff verweigert – bitte in den Browser-Einstellungen erlauben.',
        2: 'Standort konnte nicht ermittelt werden.',
        3: 'Standortabfrage hat zu lange gedauert.',
      };
      showError(msgs[err.code] ?? 'Standortfehler.');
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
  );
}

async function loadForecast() {
  let lat  = parseFloat(document.getElementById('inLat').value);
  let lon  = parseFloat(document.getElementById('inLon').value);
  let elev = parseFloat(document.getElementById('inElev').value) || 0;

  // Koordinaten fehlen → Suchtext als Fallback nutzen
  if (isNaN(lat) || isNaN(lon)) {
    const q = searchInput.value.trim();
    if (q.length < 2) {
      showError('Bitte Ort auswählen oder Koordinaten eingeben.');
      return;
    }
    showStatus(`Suche "${q}" …`);
    try {
      const res  = await fetch(`api.php?action=search&q=${encodeURIComponent(q)}&limit=1`);
      const data = await res.json();
      if (!Array.isArray(data) || !data.length) {
        showError(`Kein Ort gefunden: „${q}"`);
        hideStatus();
        return;
      }
      applyCity(data[0]);   // setzt Koordinaten und ruft loadForecast() erneut
    } catch {
      showError('Suche fehlgeschlagen – bitte Koordinaten manuell eingeben.');
      hideStatus();
    }
    return;
  }

  showStatus('Berechne Vorhersage …');
  hideError();
  document.getElementById('result').style.display = 'none';
  document.getElementById('btnLoad').disabled = true;

  try {
    const url = `api.php?action=forecast&lat=${lat}&lon=${lon}&elev=${elev}&hours=49`;
    const res = await fetch(url);
    const data = await res.json();

    if (data.error) { showError(data.error); return; }
    renderForecast(data, lat, lon, elev);
    const knownLabel = searchInput.value.trim();
    addToRecent(knownLabel || `${lat.toFixed(3)}°N, ${lon.toFixed(3)}°E`, lat, lon, elev);
    // Suchfeld leer → Rückwärtssuche im Hintergrund, Titel + Recent nachträglich aktualisieren
    if (!knownLabel) {
      reverseGeocode(lat, lon).then(name => {
        if (!name) return;
        searchInput.value = name;
        addToRecent(name, lat, lon, elev);
        const titleEl = document.getElementById('resultTitle');
        if (titleEl) titleEl.textContent = titleEl.textContent.replace(/^📍 [^–]+ – /, `📍 ${name} – `);
      });
    }
  } catch (err) {
    showError('Netzwerkfehler: ' + err.message);
  } finally {
    hideStatus();
    document.getElementById('btnLoad').disabled = false;
  }
}

// ─── Datenalter + nächste Aktualisierung ─────────────────────────────────────
function renderDataAge(meta) {
  const ageBadge  = document.getElementById('dataAge');
  if (!ageBadge) return;

  const now       = Math.floor(Date.now() / 1000);
  const importTs  = meta.import_ts;
  const ageHours  = meta.db_age_hours;

  // Nächste Pipeline-Ausführung: Cronjob läuft täglich um 00:30, 06:30, 12:30, 18:30 UTC
  // Suche den nächsten Zeitpunkt nach jetzt
  const PIPELINE_MINUTES_UTC = [30, 6 * 60 + 30, 12 * 60 + 30, 18 * 60 + 30]; // Minuten seit UTC-Mitternacht
  const d       = new Date();
  const utcMin  = d.getUTCHours() * 60 + d.getUTCMinutes();
  const todayMidnightTs = Math.floor(d.setUTCHours(0, 0, 0, 0) / 1000);
  let nextTs = null;
  for (const m of PIPELINE_MINUTES_UTC) {
    const ts = todayMidnightTs + m * 60;
    if (ts > now + 60) { nextTs = ts; break; }
  }
  if (nextTs === null) nextTs = todayMidnightTs + 86400 + PIPELINE_MINUTES_UTC[0] * 60; // morgen 00:30 UTC

  const diffMin = Math.round((nextTs - now) / 60);
  const nextFmt = new Date(nextTs * 1000).toLocaleTimeString('de-DE', {
    timeZone: TZ, hour: '2-digit', minute: '2-digit'
  });
  const nextStr = diffMin <= 60
    ? `in ${diffMin} Min.`
    : `um ${nextFmt} Uhr`;

  const fmtUTC = (isoStr) => {
    if (!isoStr) return null;
    const d = new Date(isoStr);
    const dd = String(d.getUTCDate()).padStart(2, '0');
    const mm = String(d.getUTCMonth() + 1).padStart(2, '0');
    const HH = String(d.getUTCHours()).padStart(2, '0');
    const MM = String(d.getUTCMinutes()).padStart(2, '0');
    return `${dd}.${mm}. ${HH}:${MM}Z`;
  };
  const fmtLocal = (ts) => ts ? new Date(ts * 1000).toLocaleString('de-DE', {
    timeZone: TZ, day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'
  }) : null;

  const importStr   = fmtLocal(importTs);
  const issueStr    = fmtUTC(meta.issue_time_utc);
  const modelRunStr = fmtUTC(meta.model_run_utc);

  // Vorhersageintervall in UTC
  let intervalStr = null;
  if (meta.kml_valid_from && meta.kml_valid_to) {
    const f = fmtUTC(meta.kml_valid_from);
    const t = fmtUTC(meta.kml_valid_to);
    const sameDay = f.slice(0, 5) === t.slice(0, 5);
    intervalStr = sameDay ? `${f}–${t.slice(6)}` : `${f} – ${t}`;
  }

  const SEP = `<span style="opacity:.3">|</span>`;
  const fresh = ageHours < 7;
  const parts = [];
  if (importStr)   parts.push(`<span title="Letzter DWD-Import (Lokalzeit)">🕐 ${importStr}</span>`);
  if (modelRunStr) parts.push(`<span title="ICON/ECMWF-Modelllauf (UTC)">🔵 ${modelRunStr}</span>`);
  if (issueStr)    parts.push(`<span title="MOSMIX-Ausgabe / IssueTime (UTC)">🔄 ${issueStr}</span>`);
  if (intervalStr) parts.push(`<span title="Vorhersagezeitraum im Datensatz (UTC)">📆 ${intervalStr}</span>`);
  parts.push(`<span title="Nächste Pipeline-Ausführung (Cronjob 0/6/12/18 UTC, Lokalzeit)">⏱ ${nextStr}</span>`);

  ageBadge.innerHTML =
    `<span class="data-age ${fresh ? 'fresh' : 'stale'}" style="display:inline-flex;gap:.45rem;align-items:center;flex-wrap:wrap">` +
    parts.join(SEP) +
    `</span>`;
}

// ─── Tabelle rendern ──────────────────────────────────────────────────────────
function renderForecast(data, lat, lon, elev) {
  _lastForecast = data; _lastLat = lat; _lastLon = lon; _lastElev = elev;
  const meta     = data.meta;
  const forecast = data.forecast;
  const now      = Math.floor(Date.now() / 1000);

  // Titel
  let locName;
  if (data.location?.label) {
    locName = data.location.label;
  } else if (selectedCity) {
    locName = selectedCity.name + (selectedCity.admin1 ? ', ' + selectedCity.admin1 : '');
  } else {
    locName = `${lat.toFixed(3)}° N, ${lon.toFixed(3)}° E`;
  }
  const dtFmt = { timeZone: TZ, weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' };
  const fromStr = new Date(meta.from_utc).toLocaleString('de-DE', dtFmt);
  const toStr   = new Date(meta.to_utc  ).toLocaleString('de-DE', dtFmt);
  document.getElementById('resultTitle').textContent = `📍 ${locName} – ${fromStr} bis ${toStr}`;

  // Meta-Info
  let metaHtml = '';
  if (meta.precomputed) {
    metaHtml = `<span title="Vorberechnete Vorhersage">📦 Vorberechnet</span>`;
  } else {
    const idwStations = (meta.idw_stations ?? []).slice(0, 3);
    const idwDists    = meta.idw_dists  ?? [];
    const idwLats     = meta.idw_lats   ?? [];
    const idwLons     = meta.idw_lons   ?? [];
    const idwTip = idwStations.map((s, i) => {
      if (idwDists[i] == null || idwLats[i] == null) return s;
      const dir = _compassDir(_bearing(lat, lon, idwLats[i], idwLons[i]));
      return `${s} (${idwDists[i]} km ${dir})`;
    }).join('\n');

    const nTip = _stationTooltip(
      meta.nearest_station ?? '', meta.nearest_dist,
      meta.nearest_lat, meta.nearest_lon, lat, lon
    );

    metaHtml = `<span title="Inverse Distance Weighting:\n${idwTip}" style="cursor:help">🔵 ${idwStations.join(', ')}</span>` +
               `<span title="Nearest-Neighbor (Wettercode):\n${nTip}" style="cursor:help">⚫ ${meta.nearest_station ?? ''}</span>`;
  }
  if (data.location?.elevation || elev) {
    metaHtml += `<span>⛰️ ${data.location?.elevation ?? elev} m</span>`;
  }
  document.getElementById('resultMeta').innerHTML = metaHtml;

  // Datenalter + Countdown
  _importTs = meta.import_ts ?? null;
  renderDataAge(meta);
  if (_dataAgeTimer) clearInterval(_dataAgeTimer);
  _dataAgeTimer = setInterval(() => renderDataAge(meta), 30_000);

  // Temperaturgrenzen für Farbskala
  const temps = forecast.map(r => r.temp_c).filter(t => t !== null);
  const tMin  = Math.min(...temps);
  const tMax  = Math.max(...temps);
  const tRange = tMax - tMin || 1;

  // Tabelle füllen
  const tbody = document.getElementById('forecastBody');
  tbody.innerHTML = '';

  forecast.forEach(row => {
    const dt      = new Date(row.valid_time * 1000);
    const isCurr  = Math.abs(row.valid_time - now) < 1800;

    // Zeit formatieren
    const timeStr = dt.toLocaleTimeString('de-DE', {
      timeZone: TZ, hour: '2-digit', minute: '2-digit', weekday: 'short'
    });

    // Temperatur-Farbfaktor (0=kalt/blau, 100=warm/rot)
    const tf = row.temp_c !== null
      ? Math.round(((row.temp_c - tMin) / tRange) * 100) : 50;

    // Regen
    const rainMm   = row.rain_1h;
    const rainIsZero = rainMm === null || rainMm < 0.05;

    // Sonnenschein-Balken
    const sunPct = row.sunshine_pct ?? 0;
    const sunBar = `<span class="sunshine-bar" title="${sunPct}%"><span class="sunshine-fill" style="width:${sunPct}%"></span></span>`;

    // Wolken-Balken (tief/mittel/hoch je 16px breit)
    const cloudBar = ['cloud_low','cloud_mid','cloud_high'].map((k, i) => {
      const pct = row[k] ?? 0;
      const cls = ['cloud-low-fill','cloud-mid-fill','cloud-high-fill'][i];
      const labels = ['Tief','Mittel','Hoch'];
      return `<span class="cloud-seg" style="width:16px" title="${labels[i]}: ${pct}%">
                <span class="cloud-seg-fill ${cls}" style="width:${pct}%"></span>
              </span>`;
    }).join('');

    const tr = document.createElement('tr');
    if (isCurr) tr.classList.add('current-hour');
    tr.innerHTML = `
      <td>${timeStr}</td>
      <td>
        <div class="ww-cell">
          <span class="ww-icon">${row.weather_icon}</span>
          <span class="ww-label">${row.weather_label}</span>
        </div>
      </td>
      <td>
        <span class="temp-cell" style="--tf:${tf}">${fmt1(row.temp_c)}°</span>
      </td>
      <td class="wind-cell">${row.wind_speed != null ? fmtWind(row.wind_speed) : '—'}</td>
      <td class="wind-cell wind-dir">${row.wind_dir_label ?? '—'} ${row.wind_dir !== null ? row.wind_dir + '°' : ''}</td>
      <td class="rain-cell ${rainIsZero ? 'none' : ''}">${rainIsZero ? '—' : fmtRain(rainMm)}</td>
      <td class="rain-cell ${row.rain_accum_6h < 0.05 ? 'none' : ''}">${row.rain_accum_6h < 0.05 ? '—' : fmtRain(row.rain_accum_6h)}</td>
      <td>${row.humidity !== null ? row.humidity + ' %' : '—'}</td>
      <td>${fmt1(row.pressure_hpa) ?? '—'}</td>
      <td>${sunPct > 0 ? sunPct + ' %&nbsp;' : '—&nbsp;'}${sunBar}</td>
      <td><div class="cloud-bar">${cloudBar}</div></td>
    `;
    tbody.appendChild(tr);
  });

  document.getElementById('result').style.display = 'block';
  document.getElementById('result').scrollIntoView({ behavior: 'smooth', block: 'start' });
  const rlat = data.location?.lat ?? lat;
  const rlon = data.location?.lon ?? lon;
  renderMeteogram(forecast, rlat, rlon);
}

// ─── UI-Hilfsfunktionen ───────────────────────────────────────────────────────
function showStatus(msg) {
  const el = document.getElementById('status');
  el.innerHTML = `<span class="spinner"></span>${msg}`;
  el.style.display = 'block';
}
function hideStatus() { document.getElementById('status').style.display = 'none'; }
function showError(msg) {
  const el = document.getElementById('errorBox');
  el.textContent = '⚠️ ' + msg;
  el.style.display = 'block';
}
function hideError() { document.getElementById('errorBox').style.display = 'none'; }

function fmt1(v) { return v !== null && v !== undefined ? v.toFixed(1) : null; }
function fmtRain(v) { return v < 0.1 ? '<0.1' : v.toFixed(1); }
function fmtPop(n) {
  if (n >= 1e6) return (n/1e6).toFixed(1) + ' Mio';
  if (n >= 1e3) return (n/1e3).toFixed(0) + ' Tsd';
  return n.toString();
}
function esc(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─── Meteogramm ───────────────────────────────────────────────────────────────
const TZ = 'Europe/Berlin';   // Anzeigetimezone – unabhängig von Browser-Einstellung

let _meteoChart   = null;
let _windKnots    = true;
let _lastForecast = null, _lastLat = null, _lastLon = null, _lastElev = null;
let _importTs     = null;
let _dataAgeTimer = null;

function _bearing(lat1, lon1, lat2, lon2) {
  const φ1 = lat1 * Math.PI / 180, φ2 = lat2 * Math.PI / 180;
  const Δλ = (lon2 - lon1) * Math.PI / 180;
  const y = Math.sin(Δλ) * Math.cos(φ2);
  const x = Math.cos(φ1) * Math.sin(φ2) - Math.sin(φ1) * Math.cos(φ2) * Math.cos(Δλ);
  return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
}

function _compassDir(deg) {
  return ['N','NO','O','SO','S','SW','W','NW'][Math.round(deg / 45) % 8];
}

function _stationTooltip(name, distKm, stLat, stLon, fromLat, fromLon) {
  if (distKm == null || stLat == null) return name;
  const dir = _compassDir(_bearing(fromLat, fromLon, stLat, stLon));
  return `${name}\n${distKm} km ${dir}`;
}

function fmtWind(ms, unit = true) {
  if (ms == null) return '—';
  return _windKnots
    ? Math.round(ms * 1.94384) + (unit ? ' kn' : '')
    : Math.round(ms)           + (unit ? ' m/s' : '');
}

function toggleWindUnit() {
  _windKnots = !_windKnots;
  const btn = document.getElementById('windUnitToggle');
  btn.textContent = _windKnots ? 'Wind: kn → m/s' : 'Wind: m/s → kn';
  document.getElementById('thWindUnit').textContent = 'Wind ' + (_windKnots ? 'kn' : 'm/s');
  if (_lastForecast) renderForecast(_lastForecast, _lastLat, _lastLon, _lastElev);
  else if (_meteoChart) _meteoChart.update();
}

// Layout-Konstanten (px relativ zu chartArea.top, Koordinaten nach oben negativ)
// Gesamtaufteilung (von oben nach unten im Padding-Bereich):
//   2px Rand | 22px Wetter-Icon-Strip | 12px Okta-Lücke | 11px Hoch | 3px | 11px Mittel | 3px | 11px Tief | 14px Lücke
const M_PAD_TOP   = 89;   // layout.padding.top
const M_ICON_H    = 22;   // Höhe des Icon-Streifens
const M_BAND_H    = 11;   // Höhe jedes Wolkenbandes
// Starty (fillRect) der Bänder: top - M_BAND_Y[i], Höhe M_BAND_H
// Low: top-25 .. top-14  |  Mid: top-39 .. top-28  |  High: top-53 .. top-42
const M_BAND_Y    = [25, 39, 53];

// Sonnenhöhe in Grad über dem Horizont (astronomische Standardformel)
function _solarElevation(ts, lat, lon) {
  const jd  = ts / 86400 + 2440587.5;
  const n   = jd - 2451545.0;
  const L   = ((280.46    + 0.9856474 * n) % 360 + 360) % 360;
  const g   = ((357.528   + 0.9856003 * n) % 360 + 360) % 360;
  const gr  = g * Math.PI / 180;
  const λ   = L + 1.915 * Math.sin(gr) + 0.020 * Math.sin(2 * gr);
  const lr  = λ * Math.PI / 180;
  const ε   = (23.439 - 0.0000004 * n) * Math.PI / 180;
  const dec = Math.asin(Math.sin(ε) * Math.sin(lr));
  const RA  = Math.atan2(Math.cos(ε) * Math.sin(lr), Math.cos(lr));
  const eot = (L * Math.PI / 180 - RA) * 12 / Math.PI;
  const utH = (ts % 86400) / 3600;
  const ha  = ((utH + lon / 15 + eot) - 12) * 15 * Math.PI / 180;
  const la  = lat * Math.PI / 180;
  return Math.asin(Math.sin(la) * Math.sin(dec) + Math.cos(la) * Math.cos(dec) * Math.cos(ha)) * 180 / Math.PI;
}

function _sunBgColor(elev) {
  if (elev >= 6)   return 'rgba(255,220,60,0.07)';
  if (elev >= 0)   return 'rgba(255,150,40,0.10)';
  if (elev >= -6)  return 'rgba(210,100,30,0.06)';
  if (elev >= -12) return 'rgba(50,70,180,0.04)';
  return 'rgba(20,40,160,0.07)';
}

const _sunPlugin = {
  id: 'sun',
  beforeDatasetsDraw(chart) {
    const { ctx, chartArea: { top, bottom }, scales: { x } } = chart;
    if (!chart._sunData || !x) return;
    const step = x.getPixelForValue(1) - x.getPixelForValue(0);
    chart._sunData.forEach((elev, i) => {
      ctx.fillStyle = _sunBgColor(elev);
      ctx.fillRect(x.getPixelForValue(i) - step / 2, top, step, bottom - top);
    });
  }
};

// Wolkenbänder: tief / mittel / hoch als horizontale Streifen oberhalb der Diagrammfläche
const _cloudBandsPlugin = {
  id: 'cloudBands',
  afterDraw(chart) {
    const { ctx, chartArea: { top, left, right }, scales: { x } } = chart;
    if (!x) return;
    const step = x.getPixelForValue(1) - x.getPixelForValue(0);

    const levels = [
      { data: chart._cloudLow,  rgb: '100,116,139' },   // Tief:  blaugrau
      { data: chart._cloudMid,  rgb: '130,148,168' },   // Mittel: heller
      { data: chart._cloudHigh, rgb: '160,185,215' },   // Hoch:  bläulich-hell
    ];

    levels.forEach((lvl, li) => {
      if (!lvl.data) return;
      const yTop = top - M_BAND_Y[li];
      lvl.data.forEach((v, i) => {
        if (!v || v < 5) return;
        ctx.fillStyle = 'rgba(' + lvl.rgb + ',' + (v / 100 * 0.45).toFixed(2) + ')';
        ctx.fillRect(x.getPixelForValue(i) - step / 2, yTop, step, M_BAND_H);
      });
    });
  }
};

// ─── Okta-Symbol (meteorologisch korrekt: Tortensegment) ─────────────────────
function _drawOktaSymbol(ctx, cx, cy, r, okta) {
  const clr = 'rgba(80,100,130,0.8)';
  ctx.lineWidth = 0.75;
  ctx.strokeStyle = clr;

  if (okta === 0) {
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.stroke();
    return;
  }
  if (okta >= 8) {
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2);
    ctx.fillStyle = clr; ctx.fill(); ctx.stroke();
    return;
  }
  if (okta === 9) {
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.stroke();
    const s = r * 0.65;
    ctx.beginPath();
    ctx.moveTo(cx - s, cy - s); ctx.lineTo(cx + s, cy + s);
    ctx.moveTo(cx + s, cy - s); ctx.lineTo(cx - s, cy + s);
    ctx.stroke();
    return;
  }
  // Teilfüllung: okta/8 des Kreises, Tortenscheibe ab 12-Uhr-Position, Uhrzeigersinn
  const fillAngle = (okta / 8) * Math.PI * 2;
  ctx.beginPath();
  ctx.moveTo(cx, cy);
  ctx.arc(cx, cy, r, -Math.PI / 2, -Math.PI / 2 + fillAngle);
  ctx.closePath();
  ctx.fillStyle = clr; ctx.fill();
  ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.stroke();
}

const _oktaPlugin = {
  id: 'okta',
  afterDraw(chart) {
    const { ctx, chartArea: { top }, scales: { x } } = chart;
    if (!chart._cloudTotal || !x) return;
    ctx.save();
    const cy = top - 59;  // Mitte des 12px-Spalts zwischen Icon-Streifen (top-65) und High-Band (top-53)
    chart._cloudTotal.forEach((pct, i) => {
      if (pct === null) return;
      _drawOktaSymbol(ctx, x.getPixelForValue(i), cy, 4, Math.min(8, Math.round(pct / 12.5)));
    });
    ctx.restore();
  }
};

function deriveWeatherIcon(ww, sunPct, cl, cm, ch, solarElev) {
  const isNight  = solarElev !== null && solarElev < -1;
  const clv      = cl ?? 0;
  const cmv      = cm ?? 0;
  const chv      = ch ?? 0;
  const sun      = sunPct ?? (isNight ? 0 : 50);
  // Zeigt solare Helligkeit: Sonne scheint durch (nur tagsüber relevant)
  const sunny    = !isNight && !heavyOvercast() && sun > 20;
  function heavyOvercast() { return clv > 65; }

  if (ww !== null) {
    // ── Gewitter ──────────────────────────────────────────────────────────
    if (ww >= 91) return '⛈️';                        // Gewitter mit Regen/Schnee/Hagel
    if (ww >= 88) return '⛈️';                        // Hagelschauer
    if (ww === 13) return '🌩️';                       // Wetterleuchten, kein Donner

    // ── Schneeschauer ─────────────────────────────────────────────────────
    if (ww >= 85) return sunny ? '🌨️' : '❄️';        // Schneeschauer (Sonne→🌨, bedeckt→❄)

    // ── Gemischte Schauer ─────────────────────────────────────────────────
    if (ww >= 83) return '🌨️';                        // Schneeregenschauer

    // ── Regenschauer ──────────────────────────────────────────────────────
    if (ww >= 80) return sunny ? '🌦️' : '🌧️';        // Schauer: Sonne→🌦, bedeckt→🌧

    // ── Eiskörnchen / Graupel ─────────────────────────────────────────────
    if (ww >= 76) return '🌨️';

    // ── Schneefall ────────────────────────────────────────────────────────
    if (ww >= 70) return '❄️';

    // ── Schneeregen ───────────────────────────────────────────────────────
    if (ww >= 68) return '🌨️';

    // ── Gefrierender Regen ────────────────────────────────────────────────
    if (ww >= 66) return '🌧️';

    // ── Regen mäßig / stark ───────────────────────────────────────────────
    if (ww >= 63) return '🌧️';

    // ── Regen leicht ──────────────────────────────────────────────────────
    if (ww >= 60) return sunny ? '🌦️' : '🌧️';

    // ── Gefrierender Nieselregen / Niesel+Regen ───────────────────────────
    if (ww >= 56) return '🌧️';

    // ── Nieselregen ───────────────────────────────────────────────────────
    if (ww >= 50) return '🌦️';

    // ── Nebel ─────────────────────────────────────────────────────────────
    if (ww >= 40) return '🌫️';

    // ── Staubsturm / Sandsturm ────────────────────────────────────────────
    if (ww >= 30) return '🌪️';

    // ── Vergangenes Wetter (Phänomen in letzter Stunde abgeklungen) ───────
    if (ww >= 27) return '⛈️';                        // vergangener Hagel/Gewitter
    if (ww >= 26) return '🌩️';                        // vergangenes Wetterleuchten
    if (ww >= 23) return '🌧️';                        // vergangener Regen/Schneeregen
    if (ww >= 20) return '🌦️';                        // vergangener Niesel/Leichtregen

    // ── Dunst / Schleier (4–9) ────────────────────────────────────────────
    if (ww >= 4) return '🌫️';

    // ── ww 3: bedeckt laut Meldung ────────────────────────────────────────
    if (ww === 3) return isNight ? '☁️' : (sun > 10 ? '🌥️' : '☁️');

    // ── ww 0–2: klar/heiter – Cloud-Fallback unten ────────────────────────
  }

  // ── Fallback: Icon aus Bedeckung + Sonnenschein ───────────────────────────
  if (isNight) {
    if (clv > 75 || cmv > 85) return '☁️';
    return '🌙';
  }
  if (clv > 85)                    return '☁️';
  if (clv > 60)                    return sun > 10  ? '🌥️' : '☁️';
  if (clv > 35)                    return sun > 25  ? '⛅'  : '🌥️';
  if (clv > 15 || cmv > 45)       return sun > 45  ? '🌤️' : '⛅';
  if (cmv > 20  || chv > 40)      return sun > 55  ? '🌤️' : '⛅';
  return sun > 55 ? '☀️' : '🌤️';
}

// ─── HTML-Overlay für Wetter-Emoji (besseres Browser-Rendering als Canvas-Text) ─
function _updateIconOverlay(chart) {
  const overlay = document.getElementById('iconOverlay');
  if (!overlay || !chart._iconData || !chart.scales.x) return;
  const canvas = document.getElementById('meteogramCanvas');
  // Canvas liegt innerhalb der gepaddeten Karte; Overlay hat inset:0 → Offset korrigieren
  const offX = canvas ? canvas.offsetLeft : 0;
  const offY = canvas ? canvas.offsetTop  : 0;
  const { chartArea: { top } } = chart;
  const sMid = top - M_PAD_TOP + 2 + M_ICON_H / 2;

  const parts = [];
  chart._iconData.forEach((fallback, i) => {
    const icon = deriveWeatherIcon(
      chart._wwCodes     ? chart._wwCodes[i]     : null,
      chart._sunshinePct ? chart._sunshinePct[i] : null,
      chart._cloudLow    ? chart._cloudLow[i]    : null,
      chart._cloudMid    ? chart._cloudMid[i]    : null,
      chart._cloudHigh   ? chart._cloudHigh[i]   : null,
      chart._sunData     ? chart._sunData[i]     : null
    ) || fallback || '';
    if (!icon) return;
    const xPx = chart.scales.x.getPixelForValue(i) + offX;
    parts.push(
      `<span style="position:absolute;left:${xPx.toFixed(1)}px;top:${(sMid + offY).toFixed(1)}px;` +
      `transform:translate(-50%,-50%);font-size:15px;line-height:1;user-select:none">${icon}</span>`
    );
  });
  overlay.innerHTML = parts.join('');
}

const _iconPlugin = {
  id: 'icons',
  afterDraw(chart) {
    const { ctx, chartArea: { top, left }, scales: { x } } = chart;
    if (!x) return;

    // Beschriftung "Wetter" weiterhin in Canvas
    const sMid = top - M_PAD_TOP + 2 + M_ICON_H / 2;
    ctx.save();
    ctx.font = 'bold 8px system-ui';
    ctx.fillStyle = '#94a3b8';
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.fillText('Wetter', left + 3, sMid);
    ctx.restore();

    // Emoji im HTML-Overlay rendern (schärfer als Canvas-fillText)
    _updateIconOverlay(chart);
  }
};

const _windPlugin = {
  id: 'wind',
  afterDraw(chart) {
    const { ctx, chartArea: { bottom }, scales: { x } } = chart;
    if (!chart._windData) return;
    chart._windData.forEach((w, i) => {
      if (w.speed == null) return;
      const px = x.getPixelForValue(i);
      const arrowY = bottom + WIND_CHART_TOP + WIND_CHART_HEIGHT + 10;
      _drawWindArrow(ctx, px, arrowY, w.dir, w.speed);
      ctx.save();
      ctx.font = '9px system-ui';
      ctx.fillStyle = '#64748b';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'alphabetic';
      ctx.fillText(fmtWind(w.speed, false), px, bottom + WIND_CHART_TOP + WIND_CHART_HEIGHT + 26);
      ctx.restore();
    });
  }
};

function _drawWindArrow(ctx, x, y, dir, speed) {
  const rot = (((dir ?? 0) + 180) % 360) * Math.PI / 180;
  const len = 10 + Math.min(speed * 1.1, 16);
  ctx.save();
  ctx.translate(x, y);
  ctx.rotate(rot);
  ctx.strokeStyle = '#1d4ed8';
  ctx.fillStyle   = '#1d4ed8';
  ctx.lineWidth   = 1.5;
  ctx.beginPath(); ctx.moveTo(0, len / 2); ctx.lineTo(0, -len / 2 + 5); ctx.stroke();
  ctx.beginPath();
  ctx.moveTo(0, -len / 2); ctx.lineTo(-3.5, -len / 2 + 7); ctx.lineTo(3.5, -len / 2 + 7);
  ctx.closePath(); ctx.fill();
  ctx.restore();
}

const WIND_CHART_TOP    = 28;   // px unterhalb chartArea.bottom
const WIND_CHART_HEIGHT = 45;   // Höhe des Mini-Diagramms

const _windMiniChartPlugin = {
  id: 'windMiniChart',
  afterDraw(chart) {
    const wd = chart._windData;
    const gd = chart._gustData;
    if (!wd) return;

    const { ctx, chartArea: { left, right, bottom }, scales: { x } } = chart;
    const cTop = bottom + WIND_CHART_TOP;
    const cBot = cTop + WIND_CHART_HEIGHT;
    const cH   = WIND_CHART_HEIGHT;
    const n     = wd.length;

    // Maximalwert (Knoten) für Y-Skalierung
    const toMs = v => v;  // m/s bleibt m/s; Anzeige via fmtWind
    const scale = _windKnots ? 1.94384 : 1.0;
    const step5 = _windKnots ? 5 : 2;
    let maxVal = _windKnots ? 5 : 3;
    for (let i = 0; i < n; i++) {
      if (wd[i]?.speed != null) maxVal = Math.max(maxVal, wd[i].speed * scale);
      if (gd?.[i]     != null) maxVal = Math.max(maxVal, gd[i]       * scale);
    }
    const yMax = Math.ceil(maxVal / step5) * step5;

    const toY = v => cBot - (v / yMax) * cH;

    ctx.save();

    // Hintergrund
    ctx.fillStyle = '#f8fafc';
    ctx.fillRect(left, cTop, right - left, cH);
    ctx.strokeStyle = '#dde1ea';
    ctx.lineWidth = 1;
    ctx.strokeRect(left, cTop, right - left, cH);

    // Horizontale Gitterlinien
    ctx.setLineDash([2, 3]);
    ctx.strokeStyle = '#e2e8f0';
    for (let v = step5; v < yMax; v += step5) {
      const y = toY(v);
      ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(right, y); ctx.stroke();
    }
    ctx.setLineDash([]);

    // Y-Achsen-Labels
    ctx.font = '8px system-ui';
    ctx.fillStyle = '#94a3b8';
    ctx.textAlign = 'right';
    ctx.textBaseline = 'middle';
    for (let v = 0; v <= yMax; v += step5) {
      ctx.fillText(v, left - 3, toY(v));
    }
    ctx.fillStyle = '#64748b';
    ctx.textAlign = 'left';
    ctx.textBaseline = 'top';
    ctx.fillText(_windKnots ? 'kn' : 'm/s', left + 2, cTop + 2);

    const step = n > 1 ? (x.getPixelForValue(1) - x.getPixelForValue(0)) : 1;

    // Windgeschwindigkeit – gefüllte Fläche + Linie (schwarz)
    ctx.beginPath();
    let started = false;
    for (let i = 0; i < n; i++) {
      const spd = wd[i]?.speed;
      if (spd == null) { started = false; continue; }
      const px = x.getPixelForValue(i);
      const py = toY(spd * scale);
      if (!started) { ctx.moveTo(px, cBot); ctx.lineTo(px, py); started = true; }
      else ctx.lineTo(px, py);
    }
    for (let i = n - 1; i >= 0; i--) {
      if (wd[i]?.speed != null) { ctx.lineTo(x.getPixelForValue(i), cBot); break; }
    }
    ctx.closePath();
    ctx.fillStyle = 'rgba(30,37,53,0.12)';
    ctx.fill();

    // Linie Windgeschwindigkeit
    ctx.beginPath();
    started = false;
    for (let i = 0; i < n; i++) {
      const spd = wd[i]?.speed;
      if (spd == null) { started = false; continue; }
      const px = x.getPixelForValue(i);
      if (!started) { ctx.moveTo(px, toY(spd * scale)); started = true; }
      else ctx.lineTo(px, toY(spd * scale));
    }
    ctx.strokeStyle = '#1e2535';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    // Böen – Linie (orange)
    const hasGust = gd && gd.some(v => v != null);
    if (hasGust) {
      ctx.beginPath();
      started = false;
      for (let i = 0; i < n; i++) {
        const g = gd[i];
        if (g == null) { started = false; continue; }
        const px = x.getPixelForValue(i);
        if (!started) { ctx.moveTo(px, toY(g * scale)); started = true; }
        else ctx.lineTo(px, toY(g * scale));
      }
      ctx.strokeStyle = '#ea580c';
      ctx.lineWidth = 1.5;
      ctx.stroke();
    }

    ctx.restore();
  }
};


const isNewDay = lbl => /^\d{2}\.\d{2}\./.test(lbl);  // "11.09." → Datumslabel ohne Wochentag

// Manuelle Gitterlinien – exakt an denselben x-Positionen wie die 3h-Labels
const _gridPlugin = {
  id: 'customGrid',
  beforeDatasetsDraw(chart) {
    const { ctx, chartArea: { top, bottom }, scales: { x } } = chart;
    if (!x) return;
    ctx.save();
    chart.data.labels.forEach((lbl, i) => {
      if (i % 3 !== 0) return;
      const xPx = x.getPixelForValue(i);
      ctx.strokeStyle = isNewDay(lbl) ? 'rgba(37,99,235,0.25)' : 'rgba(220,225,234,1)';
      ctx.lineWidth   = isNewDay(lbl) ? 2 : 1;
      ctx.beginPath();
      ctx.moveTo(xPx, top);
      ctx.lineTo(xPx, bottom);
      ctx.stroke();
    });
    ctx.restore();
  }
};

const _dateBoundaryPlugin = {
  id: 'dateBoundary',
  afterDraw(chart) {
    const { ctx, chartArea: { top, bottom }, scales: { x } } = chart;
    const triH = 8, triW = 6;
    ctx.save();

    chart.data.labels.forEach((lbl, i) => {
      if (!isNewDay(lbl)) return;
      const xPx = x.getPixelForValue(i);

      // Dreieck-Marker
      ctx.fillStyle = '#2563eb';
      ctx.beginPath();
      ctx.moveTo(xPx, top);
      ctx.lineTo(xPx - triW, top - triH);
      ctx.lineTo(xPx + triW, top - triH);
      ctx.closePath();
      ctx.fill();

      // Wochentag aus Timestamp (Langform) – horizontal in der 25px-Lücke oberhalb
      const ts = chart._timestamps ? chart._timestamps[i] : null;
      const wday = ts
        ? new Date(ts * 1000).toLocaleDateString('de-DE', { timeZone: TZ, weekday: 'long' })
        : '';
      if (wday) {
        ctx.save();
        ctx.font = 'bold 11px system-ui';
        ctx.fillStyle = 'rgba(37,99,235,0.8)';
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        ctx.fillText(wday, xPx + 10, top - 7);
        ctx.restore();
      }
    });

    ctx.restore();
  }
};

const _nowLinePlugin = {
  id: 'nowLine',
  afterDraw(chart) {
    if (!chart._timestamps) return;
    const nowSec = Date.now() / 1000;
    const ts = chart._timestamps;
    // find the two surrounding data points
    let idx = ts.findIndex(t => t >= nowSec);
    if (idx < 0) idx = ts.length - 1;
    if (idx === 0 && nowSec < ts[0]) return; // before first point

    const { ctx, chartArea: { top, bottom }, scales: { x } } = chart;

    let xPx;
    if (idx === 0) {
      xPx = x.getPixelForValue(0);
    } else {
      // linear interpolation between idx-1 and idx
      const t0 = ts[idx - 1], t1 = ts[idx];
      const frac = (nowSec - t0) / (t1 - t0);
      const x0 = x.getPixelForValue(idx - 1);
      const x1 = x.getPixelForValue(idx);
      xPx = x0 + frac * (x1 - x0);
    }

    ctx.save();
    // filled triangle marker at top
    const triH = 8, triW = 6;
    ctx.beginPath();
    ctx.moveTo(xPx, top);
    ctx.lineTo(xPx - triW, top - triH);
    ctx.lineTo(xPx + triW, top - triH);
    ctx.closePath();
    ctx.fillStyle = '#ef4444';
    ctx.fill();

    // dashed vertical line
    ctx.beginPath();
    ctx.moveTo(xPx, top);
    ctx.lineTo(xPx, bottom);
    ctx.strokeStyle = '#ef4444';
    ctx.lineWidth = 1.5;
    ctx.setLineDash([4, 3]);
    ctx.stroke();
    ctx.restore();
  }
};

function renderSunTimes(sunData, timestamps, sunshinePctData, lat, lon) {
  const bar = document.getElementById('sunTimesBar');
  if (!bar || !sunData.length) return;

  const timeFmt = ts => new Date(ts * 1000).toLocaleTimeString('de-DE', {
    timeZone: TZ, hour: '2-digit', minute: '2-digit'
  });
  const dayFmt = ts => new Date(ts * 1000).toLocaleDateString('de-DE', {
    timeZone: TZ, weekday: 'short', day: '2-digit', month: '2-digit'
  });

  // Bisektion für exakten Nulldurchgang der Sonnenhöhe
  const bisectCrossing = (t0, t1) => {
    for (let i = 0; i < 40; i++) {
      const tm = Math.round((t0 + t1) / 2);
      if (_solarElevation(tm, lat, lon) < 0) t0 = tm; else t1 = tm;
    }
    return Math.round((t0 + t1) / 2);
  };

  // Nulldurchgänge interpolieren → Auf-/Untergang
  const days = {};
  for (let i = 1; i < sunData.length; i++) {
    const e0 = sunData[i - 1], e1 = sunData[i];
    if (e0 === null || e1 === null) continue;
    const t0 = timestamps[i - 1], t1 = timestamps[i];
    if (e0 < 0 && e1 >= 0) {
      const ts = Math.round(t0 + (-e0 / (e1 - e0)) * (t1 - t0));
      const key = dayFmt(ts);
      if (!days[key]) days[key] = { key, ts };
      days[key].rise = ts;
    }
    if (e0 >= 0 && e1 < 0) {
      const ts = Math.round(t0 + (e0 / (e0 - e1)) * (t1 - t0));
      const key = dayFmt(ts);
      if (!days[key]) days[key] = { key, ts };
      days[key].set = ts;
    }
  }

  // Randfall: Erster Datenpunkt liegt schon im Tag → Aufgang lag vor dem Datenfenster
  if (lat != null && lon != null && sunData[0] !== null && sunData[0] >= 0) {
    let tNight = null;
    for (let h = 1; h <= 24; h++) {
      const tc = timestamps[0] - h * 3600;
      if (_solarElevation(tc, lat, lon) < 0) { tNight = tc; break; }
    }
    if (tNight !== null) {
      const ts = bisectCrossing(tNight, timestamps[0]);
      const key = dayFmt(ts);
      if (!days[key]) days[key] = { key, ts };
      if (!days[key].rise) days[key].rise = ts;
    }
  }

  // Randfall: Letzter Datenpunkt liegt noch im Tag → Untergang liegt nach dem Datenfenster
  const last = sunData.length - 1;
  if (lat != null && lon != null && sunData[last] !== null && sunData[last] >= 0) {
    let tNight = null;
    for (let h = 1; h <= 24; h++) {
      const tc = timestamps[last] + h * 3600;
      if (_solarElevation(tc, lat, lon) < 0) { tNight = tc; break; }
    }
    if (tNight !== null) {
      const ts = bisectCrossing(tNight, timestamps[last]);
      const key = dayFmt(ts);
      if (!days[key]) days[key] = { key, ts };
      if (!days[key].set) days[key].set = ts;
    }
  }

  // Tageslänge und effektive Sonnenstunden pro Tag
  const dayStats = key => {
    let daySecs = 0, sunHours = 0;
    const d = days[key];
    if (!d) return { dayLen: null, sunHours: null };
    if (d.rise && d.set) daySecs = d.set - d.rise;

    // Effektive Sonnenstunden aus sunshine_pct der Tagstunden
    for (let i = 0; i < timestamps.length; i++) {
      if (sunData[i] !== null && sunData[i] > 0 && dayFmt(timestamps[i]) === key) {
        sunHours += (sunshinePctData?.[i] ?? 0) / 100;
      }
    }
    return {
      dayLen: daySecs > 0 ? daySecs / 3600 : null,
      sunHours: sunHours > 0 ? sunHours : 0,
    };
  };

  const fmtH = h => h != null ? h.toFixed(1) + ' h' : '—';

  const entries = Object.values(days).sort((a, b) => a.ts - b.ts);
  bar.innerHTML = entries.map(d => {
    const { dayLen, sunHours } = dayStats(d.key);
    const sunStr = dayLen != null
      ? `🌤 ${fmtH(sunHours)} / ${fmtH(dayLen)}`
      : '';
    return `<span style="display:inline-flex;align-items:center;gap:.5rem;padding:.25rem .55rem;` +
      `border:1px solid rgba(148,163,184,0.35);border-radius:.4rem;background:rgba(248,250,252,0.7)">` +
      `📅 <b>${d.key}</b>` +
      `<span style="color:rgba(0,0,0,.25)">|</span>` +
      `🌅 ${d.rise ? timeFmt(d.rise) : '—'}` +
      `<span style="color:rgba(0,0,0,.25)">|</span>` +
      `🌇 ${d.set  ? timeFmt(d.set)  : '—'}` +
      `${sunStr ? `<span style="color:rgba(0,0,0,.25)">|</span>${sunStr}` : ''}` +
      `</span>`;
  }).join('');
  bar.style.display = 'flex';
}

function renderMeteogram(forecast, lat, lon) {
  const canvas = document.getElementById('meteogramCanvas');
  if (!canvas) return;
  if (_meteoChart) { _meteoChart.destroy(); _meteoChart = null; }

  // Koordinaten für Radar-Panels merken und ggf. Karten neu zentrieren
  _radarSetLocation(lat, lon);

  const tz = TZ;
  const labels       = [];
  const tempData     = [];
  const pressData    = [];
  const rainData     = [];
  const humidData    = [];
  const cloudLow     = [];
  const cloudMid     = [];
  const cloudHigh    = [];
  const cloudTotal   = [];
  const iconData     = [];
  const wwCodes      = [];
  const sunshinePctData = [];
  const windData     = [];
  const gustData     = [];
  const sunData      = [];
  const timestamps   = [];
  let prevDay = null;

  forecast.forEach(row => {
    const dt      = new Date(row.valid_time * 1000);
    const dayStr  = dt.toLocaleDateString('de-DE', { timeZone: tz, day: '2-digit', month: '2-digit' });
    const timeStr = dt.toLocaleTimeString('de-DE', { timeZone: tz, hour: '2-digit', minute: '2-digit' });
    labels.push(prevDay !== null && dayStr !== prevDay ? dayStr : timeStr);
    prevDay = dayStr;

    timestamps.push(row.valid_time);
    tempData.push(row.temp_c);
    pressData.push(row.pressure_hpa);
    rainData.push(row.rain_1h ?? 0);
    humidData.push(row.humidity);
    cloudLow.push(row.cloud_low ?? 0);
    cloudMid.push(row.cloud_mid ?? 0);
    cloudHigh.push(row.cloud_high ?? 0);
    cloudTotal.push(row.cloud_total ?? null);
    iconData.push(row.weather_icon);
    wwCodes.push(row.weather_code ?? null);
    sunshinePctData.push(row.sunshine_pct ?? null);
    windData.push({ dir: row.wind_dir, speed: row.wind_speed, dirLabel: row.wind_dir_label ?? '' });
    gustData.push(row.wind_gust ?? null);
    sunData.push(lat != null && lon != null ? _solarElevation(row.valid_time, lat, lon) : null);
  });

  const validTemps = tempData.filter(v => v != null);
  const tMax = validTemps.length ? Math.max(...validTemps) : 25;
  const tMin = validTemps.length ? Math.min(...validTemps) : 0;
  const yTSuggestedMax = Math.ceil(tMax / 5) * 5;
  const yTSuggestedMin = Math.min(0, Math.floor(tMin / 5) * 5);

  // Dynamische Integer-Grenzen für Regenachse
  const rainMax = Math.max(...rainData.filter(v => v != null), 0);
  const _rainNice = [1, 2, 3, 5, 10, 15, 20, 30, 50, 100];
  const _rainStep = [1, 1, 1, 1,  2,  3,  5,  5, 10,  20];
  const _ri = _rainNice.findIndex(b => b >= rainMax);
  const _yRStep = _ri >= 0 ? _rainStep[_ri] : Math.max(1, Math.ceil(rainMax / 5));
  const yRStep = _yRStep;                              // Ticks bleiben beim errechneten Intervall
  const _yRMax = _ri >= 0 ? _rainNice[_ri] : Math.ceil(rainMax / 10) * 10;
  const yRMax  = Math.max(_yRMax, _yRStep * 2);       // Achsen-Maximum verdoppelt

  // Luftdruck-Achse: auto-range mit Puffer, keine Skala anzeigen
  const validPress = pressData.filter(v => v != null);
  const pMin = validPress.length ? Math.min(...validPress) : 980;
  const pMax = validPress.length ? Math.max(...validPress) : 1020;
  const pPad = Math.max(4, Math.ceil((pMax - pMin) * 0.25));
  const yPMin = Math.floor((pMin - pPad) / 5) * 5;
  const yPMax = Math.ceil((pMax + pPad) / 5) * 5;

  // Scrollbares Diagramm: min-width so dass jeder Datenpunkt >= 18 px breit ist
  const inner = document.getElementById('meteogramInner');
  if (inner) {
    const minW = Math.max(labels.length * 18, 600);
    inner.style.minWidth = minW + 'px';
  }

  const ctxEl = canvas.getContext('2d');
  let chart;


  chart = new Chart(ctxEl, {
    data: {
      labels,
      datasets: [
        {
          type: 'line', label: 'Temperatur', data: tempData,
          borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,0.05)',
          yAxisID: 'yT', tension: 0.35, pointRadius: 0, pointHoverRadius: 4,
          borderWidth: 2, order: 1,
        },
        {
          type: 'bar', label: 'Regen', data: rainData,
          backgroundColor: 'rgba(59,130,246,0.55)', borderColor: 'rgba(59,130,246,0.8)',
          borderWidth: 1, borderRadius: 2, yAxisID: 'yR', order: 3,
        },
        {
          type: 'line', label: 'Luftfeuchtigkeit', data: humidData,
          borderColor: '#059669', backgroundColor: 'transparent',
          yAxisID: 'yH', tension: 0.35, pointRadius: 0, pointHoverRadius: 4,
          borderWidth: 1.5, borderDash: [3, 3], order: 2,
        },
        {
          type: 'line', label: 'Sonnenhöhe', data: sunData,
          borderColor: 'rgba(251,191,36,0.6)', backgroundColor: 'transparent',
          yAxisID: 'yS', tension: 0.4, pointRadius: 0, pointHoverRadius: 0,
          borderWidth: 1.5, borderDash: [2, 3], order: 5,
        },
        {
          type: 'line', label: 'Luftdruck', data: pressData,
          borderColor: 'rgba(124,58,237,0.65)', backgroundColor: 'transparent',
          yAxisID: 'yP', tension: 0.4, pointRadius: 0, pointHoverRadius: 4,
          borderWidth: 1.5, borderDash: [4, 2], order: 4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      layout: { padding: { top: M_PAD_TOP, bottom: 108 } },
      interaction: { mode: 'index', intersect: false },
      animation: { duration: 300 },
      scales: {
        x: {
          grid: { display: false },   // Manuelle Gitterlinien via _gridPlugin
          afterBuildTicks(axis) {
            axis.ticks = axis.ticks.filter((_, i) => i % 3 === 0);
          },
          ticks: {
            color: '#64748b',
            font: ctx => isNewDay(ctx.tick?.label ?? '') ? { size: 11, weight: 'bold' } : { size: 11 },
            maxRotation: 0,
          },
          border: { color: 'rgba(220,225,234,1)' },
        },
        yT: {
          type: 'linear', position: 'left',
          suggestedMin: yTSuggestedMin, suggestedMax: yTSuggestedMax,
          grid: { color: 'rgba(220,225,234,1)' },
          border: { color: 'rgba(220,225,234,1)' },
          ticks: { color: '#dc2626', font: { size: 11 }, stepSize: 5, callback: v => v + '°' },
        },
        yR: {
          type: 'linear', position: 'right',
          min: 0, max: yRMax,
          grid: { drawOnChartArea: false },
          border: { color: 'rgba(220,225,234,1)' },
          ticks: {
            color: '#3b82f6', font: { size: 10 }, stepSize: yRStep,
            callback: v => v === 0 ? '0' : v + ' mm',
          },
        },
        yH: {
          type: 'linear', position: 'right',
          min: 0, max: 100, display: false,
          grid: { drawOnChartArea: false },
          ticks: { stepSize: 10 },
        },
        yS: {
          type: 'linear', position: 'right',
          min: -90, max: 90, display: false,
          grid: { drawOnChartArea: false },
        },
        yP: {
          type: 'linear', position: 'right',
          min: yPMin, max: yPMax, display: false,
          grid: { drawOnChartArea: false },
        },
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#ffffff', borderColor: '#dde1ea', borderWidth: 1,
          titleColor: '#1e2535', bodyColor: '#64748b', padding: 10,
          displayColors: false,
          callbacks: {
            label: item => {
              if (item.datasetIndex === 0) return ' 🌡 ' + (item.parsed.y?.toFixed(1) ?? '—') + ' °C';
              if (item.datasetIndex === 2) return ' 💧 ' + (item.parsed.y?.toFixed(0) ?? '—') + ' %';
              if (item.datasetIndex === 3) return ' ☀ ' + (item.parsed.y?.toFixed(1) ?? '—') + '°';
              if (item.datasetIndex === 4) return item.parsed.y != null ? ' 🔵 ' + item.parsed.y.toFixed(0) + ' hPa' : null;
              return item.parsed.y >= 0.05 ? ' 🌧 ' + item.parsed.y.toFixed(1) + ' mm' : null;
            },
            afterBody: items => {
              if (!items.length) return [];
              const i  = items[0].dataIndex;
              const w  = chart._windData?.[i];
              const g  = chart._gustData?.[i];
              const cl = chart._cloudLow?.[i];
              const cm = chart._cloudMid?.[i];
              const ch = chart._cloudHigh?.[i];
              const out = [];
              if (cl != null) out.push(' ☁ Tief ' + cl + '% · Mittel ' + cm + '% · Hoch ' + ch + '%');
              if (w?.speed != null) {
                out.push(' 💨 Wind: ' + fmtWind(w.speed) + '  aus ' + (w.dirLabel || '—') + (w.dir != null ? ' (' + Math.round(w.dir) + '°)' : ''));
              }
              if (g != null) {
                out.push(' 🌬 Böen: ' + fmtWind(g));
              }
              return out;
            },
          },
        },
      },
    },
    plugins: [_sunPlugin, _gridPlugin, _cloudBandsPlugin, _oktaPlugin, _iconPlugin, _windPlugin, _windMiniChartPlugin, _dateBoundaryPlugin, _nowLinePlugin],
  });

  chart._sunData    = sunData;
  chart._cloudLow   = cloudLow;
  chart._cloudMid   = cloudMid;
  chart._cloudHigh  = cloudHigh;
  chart._cloudTotal = cloudTotal;
  chart._iconData   = iconData;
  chart._wwCodes    = wwCodes;
  chart._sunshinePct = sunshinePctData;
  chart._windData   = windData;
  chart._gustData   = gustData;
  chart._humidData  = humidData;
  chart._timestamps = timestamps;
  _meteoChart = chart;

  renderSunTimes(sunData, timestamps, sunshinePctData, lat, lon);

  // ── Icon-Strip Tooltip ────────────────────────────────────────────────────
  const iconTip = document.getElementById('iconTip');
  canvas.addEventListener('mousemove', e => {
    const ch = _meteoChart;
    if (!ch || !iconTip) return;
    const { chartArea, scales: { x } } = ch;
    if (!chartArea || !x) return;

    const rect = canvas.getBoundingClientRect();
    const cx   = e.clientX - rect.left;   // CSS-Pixel, passend zu Chart.js-Koordinaten
    const cy   = e.clientY - rect.top;

    const sTop = chartArea.top - M_PAD_TOP + 2;
    const sBot = sTop + M_ICON_H;
    if (cy < sTop || cy > sBot) { iconTip.style.display = 'none'; return; }

    // Nächsten Datenpunkt bestimmen
    const step = x.getPixelForValue(1) - x.getPixelForValue(0);
    const i    = Math.round((cx - x.getPixelForValue(0)) / step);
    if (i < 0 || i >= (ch._cloudLow?.length ?? 0)) { iconTip.style.display = 'none'; return; }

    const cl  = ch._cloudLow?.[i]    ?? null;
    const cm  = ch._cloudMid?.[i]    ?? null;
    const chi = ch._cloudHigh?.[i]   ?? null;
    const sun = ch._sunshinePct?.[i] ?? null;

    const fmtPct = v => v != null ? v + '%' : '—';
    iconTip.innerHTML =
      `☁ Tief&nbsp;<b>${fmtPct(cl)}</b>&nbsp;&nbsp;` +
      `Mittel&nbsp;<b>${fmtPct(cm)}</b>&nbsp;&nbsp;` +
      `Hoch&nbsp;<b>${fmtPct(chi)}</b>` +
      (sun != null ? `&nbsp;&nbsp;☀&nbsp;<b>${sun}%</b>` : '');

    // position:fixed: erst rechts vom Cursor platzieren, dann nachmessen und ggf. links spiegeln
    iconTip.style.left = (e.clientX + 10) + 'px';
    iconTip.style.top  = (e.clientY  + 14) + 'px';
    iconTip.style.display = 'block';
    const r = iconTip.getBoundingClientRect();
    if (r.right > window.innerWidth - 8) {
      iconTip.style.left = Math.max(8, e.clientX - r.width - 10) + 'px';
    }
  });
  canvas.addEventListener('mouseleave', () => { iconTip.style.display = 'none'; });
}

// ─── Gespeicherte Orte ────────────────────────────────────────────────────────
let activeBookmarkId = null;
const _bookmarkMap = {};   // id → location-Objekt, vermeidet Quoting-Probleme in onclick

async function loadBookmarks() {
  try {
    const res  = await fetch('api.php?action=locations');
    const list = await res.json();
    if (!Array.isArray(list) || list.length === 0) return;

    const section   = document.getElementById('bookmarksSection');
    const container = document.getElementById('bookmarksList');
    section.style.display = 'block';

    list.forEach(loc => { _bookmarkMap[loc.id] = loc; });

    container.innerHTML = list.map(loc =>
      `<button class="btn-bookmark" data-id="${loc.id}" onclick="loadPrecomputed(${loc.id})">${esc(loc.label)}</button>`
    ).join('');
  } catch { /* Lesezeichen optional */ }
}

async function loadPrecomputed(id) {
  const loc = _bookmarkMap[id];
  if (!loc) return;
  const { label, lat, lon, elevation: elev } = loc;
  // Aktiven Button markieren
  document.querySelectorAll('.btn-bookmark').forEach(b => b.classList.remove('active'));
  const btn = document.querySelector(`.btn-bookmark[data-id="${id}"]`);
  if (btn) btn.classList.add('active');
  activeBookmarkId = id;

  // Koordinatenfelder befüllen (informativ)
  document.getElementById('inLat').value  = lat.toFixed(4);
  document.getElementById('inLon').value  = lon.toFixed(4);
  document.getElementById('inElev').value = elev;
  searchInput.value = label;
  updateClearBtn();
  selectedCity = null;

  showStatus(`Lade „${label}" …`);
  hideError();
  document.getElementById('result').style.display = 'none';

  try {
    const res  = await fetch(`api.php?action=precomputed&id=${id}&hours=49`);
    const data = await res.json();
    if (data.error) { showError(data.error); return; }
    renderForecast(data, lat, lon, elev);
    addToRecent(label, lat, lon, elev);
  } catch (err) {
    showError('Netzwerkfehler: ' + err.message);
  } finally {
    hideStatus();
  }
}

// ─── Zuletzt gesucht ─────────────────────────────────────────────────────────
const RECENT_KEY = 'mosmix_recent';
const RECENT_MAX = 5;

function _recentLoad() {
  try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); } catch { return []; }
}
function _recentSave(list) {
  try { localStorage.setItem(RECENT_KEY, JSON.stringify(list)); } catch {}
}

const _recentSearches = _recentLoad();

function addToRecent(label, lat, lon, elev) {
  const key = `${lat.toFixed(3)},${lon.toFixed(3)}`;
  const idx  = _recentSearches.findIndex(r => `${r.lat.toFixed(3)},${r.lon.toFixed(3)}` === key);
  if (idx !== -1) _recentSearches.splice(idx, 1);
  _recentSearches.unshift({ label, lat, lon, elev });
  if (_recentSearches.length > RECENT_MAX) _recentSearches.pop();
  _recentSave(_recentSearches);
  renderRecent();
}

function renderRecent() {
  const section   = document.getElementById('recentSection');
  const container = document.getElementById('recentList');
  if (!_recentSearches.length) { section.style.display = 'none'; return; }
  section.style.display = 'block';
  container.innerHTML = _recentSearches.map((r, i) =>
    `<button class="btn-bookmark" onclick="_loadRecent(${i})">${esc(r.label)}</button>`
  ).join('');
}

function _loadRecent(i) {
  const r = _recentSearches[i];
  if (!r) return;
  document.getElementById('inLat').value  = r.lat.toFixed(4);
  document.getElementById('inLon').value  = r.lon.toFixed(4);
  document.getElementById('inElev').value = r.elev;
  searchInput.value = r.label;
  updateClearBtn();
  selectedCity = null;
  activeBookmarkId = null;
  document.querySelectorAll('.btn-bookmark').forEach(b => b.classList.remove('active'));
  loadForecast();
}

// Lesezeichen und Datenbasis-Info beim Seitenstart laden
loadBookmarks();
loadDbInfo();
renderRecent();

function toggleTable(header) {
  const body  = document.getElementById('tableBody');
  const arrow = document.getElementById('tableArrow');
  const open  = body.classList.toggle('open');
  arrow.classList.toggle('open', open);
}

// ─── Radar (RainViewer + Leaflet) ────────────────────────────────────────────
let _radarMap       = null;
let _radarFrames    = [];   // {time, path, isNowcast}
let _radarLayers    = {};   // path → L.TileLayer
let _radarCurrent   = 0;
let _radarPlaying   = false;
let _radarTimer     = null;
let _radarLat       = null;
let _radarLon       = null;
let _radarActiveLayers = []; // alle derzeit auf der Karte liegenden Radar-Layer
let _mapSyncing        = false; // Endlosschleifen-Schutz bei Zoom-Sync

const RAINVIEWER_API  = 'https://api.rainviewer.com/public/weather-maps.json';
const RAINVIEWER_TILE = 'https://tilecache.rainviewer.com';
const RADAR_COLOR     = 6;   // The Weather Channel colour scheme
const RADAR_SMOOTH    = 1;
const RADAR_SNOW      = 1;
let _radarInterval = 420; // ms zwischen Frames (RainViewer)
let _radarFadeMs   = 400; // CSS-Transition-Dauer (RainViewer)

function radarSetSpeed(ms) {
  _radarInterval = ms;
  _radarFadeMs   = Math.max(40, ms - 30);
  const t = `opacity ${_radarFadeMs}ms linear`;
  _radarActiveLayers.forEach(l => { if (l._container) l._container.style.transition = t; });
  document.getElementById('radarSpeedVal').textContent = ms + ' ms';
}

function toggleRadar(header) {
  const body  = document.getElementById('radarBody');
  const arrow = document.getElementById('radarArrow');
  const open  = body.classList.toggle('open');
  arrow.classList.toggle('open', open);
  if (open && !_radarMap) _radarInit();
}

function _radarInit() {
  if (!_radarLat || !_radarLon) return;

  // Leaflet-Marker auf lokale Assets umleiten
  delete L.Icon.Default.prototype._getIconUrl;
  L.Icon.Default.mergeOptions({
    iconUrl:       'assets/images/marker-icon.png',
    iconRetinaUrl: 'assets/images/marker-icon-2x.png',
    shadowUrl:     'assets/images/marker-shadow.png',
  });

  _radarMap = L.map('radarMap', { zoomControl: true, attributionControl: true, minZoom: 4 })
               .setView([_radarLat, _radarLon], 6);

  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
    maxZoom: 18,
  }).addTo(_radarMap);

  // Stationsmarker
  L.circleMarker([_radarLat, _radarLon], {
    radius: 6, color: '#2563eb', fillColor: '#2563eb', fillOpacity: 0.9, weight: 2,
  }).addTo(_radarMap).bindTooltip('Station', { permanent: false });

  _radarLoad();
  _attachMapSync();
}

async function _radarLoad() {
  try {
    // Alte Layer von der Karte entfernen
    _radarActiveLayers.forEach(l => { try { _radarMap.removeLayer(l); } catch(_) {} });
    _radarActiveLayers = [];
    _radarFrames = [];
    _radarLayers = {};

    const data = await (await fetch(RAINVIEWER_API)).json();
    (data.radar?.past    || []).forEach(f => _radarFrames.push({ ...f, isNowcast: false }));
    (data.radar?.nowcast || []).forEach(f => _radarFrames.push({ ...f, isNowcast: true  }));

    if (!_radarFrames.length) return;

    const slider = document.getElementById('radarTimeline');
    slider.max   = _radarFrames.length - 1;
    slider.value = _radarFrames.length - 1;

    // Nur Layer-Objekte registrieren – kein addTo; Tiles werden on-demand geladen
    _radarFrames.forEach(f => {
      _radarLayers[f.path] = L.tileLayer(
        RAINVIEWER_TILE + f.path + `/256/{z}/{x}/{y}/${RADAR_COLOR}/${RADAR_SMOOTH}_${RADAR_SNOW}.png`,
        { opacity: 0, zIndex: 10, attribution: '© <a href="https://rainviewer.com">RainViewer</a>' }
      );
    });

    _radarCurrent = 0;
    _radarSeekTo(_radarFrames.length - 1);
  } catch(_) {
    document.getElementById('radarTimestamp').textContent = 'Fehler beim Laden der Radardaten';
  }
}

function _radarSeekTo(idx) {
  if (!_radarFrames.length) return;
  idx = Math.max(0, Math.min(_radarFrames.length - 1, idx));

  const prevLayer = _radarLayers[_radarFrames[_radarCurrent].path];
  _radarCurrent = idx;
  const nextFrame = _radarFrames[idx];
  const nextLayer = _radarLayers[nextFrame.path];
  if (!nextLayer) return;

  // Lazy-add: erst beim ersten Anzeigen zur Karte hinzufügen → Tiles laden
  if (!_radarMap.hasLayer(nextLayer)) {
    nextLayer.addTo(_radarMap);
    if (nextLayer._container)
      nextLayer._container.style.transition = `opacity ${_radarFadeMs}ms linear`;
    _radarActiveLayers.push(nextLayer);
  }

  if (prevLayer && prevLayer !== nextLayer) prevLayer.setOpacity(0);
  nextLayer.setOpacity(0.65);

  // UI-Aktualisierung
  const dt = new Date(nextFrame.time * 1000);
  const ts = dt.toLocaleString('de-DE', {
    timeZone: TZ, day: '2-digit', month: '2-digit',
    hour: '2-digit', minute: '2-digit',
  });
  document.getElementById('radarTimestamp').textContent = ts + ' Uhr';
  document.getElementById('radarNowcastBadge').style.display = nextFrame.isNowcast ? '' : 'none';
  document.getElementById('radarTimeline').value = idx;
}

function radarSeek(idx) { _radarSeekTo(idx); }

function radarTogglePlay() {
  _radarPlaying = !_radarPlaying;
  document.getElementById('radarPlayBtn').textContent = _radarPlaying ? '⏸ Pause' : '▶ Play';
  if (_radarPlaying) _radarStep();
}

function _radarStep() {
  if (!_radarPlaying) return;
  const next = (_radarCurrent + 1) % _radarFrames.length;
  _radarSeekTo(next);
  _radarTimer = setTimeout(_radarStep, _radarInterval);
}

// Gemeinsame Koordinaten-Aktualisierung für beide Panels
function _radarSetLocation(lat, lon) {
  _radarLat = lat;
  _radarLon = lon;
  if (_radarMap) { _radarMap.setView([lat, lon], 6); _radarLoad(); }
  if (_dwdRadarMap) {
    _dwdRadarMap.setView([lat, lon], 6);
    if (_dwdStationMarker) _dwdStationMarker.setLatLng([lat, lon]);
  }
}

// ─── DWD WMS Radar (animiert, RV-Produkt + TIME-Dimension) ───────────────────
let _dwdRadarMap      = null;
let _dwdStationMarker = null;
let _dwdFrames        = [];   // { time, iso, isForecast }
let _dwdLayers        = {};   // iso → L.TileLayer.WMS
let _dwdCurrent       = 0;
let _dwdPlaying       = false;
let _dwdTimer         = null;
let _dwdActiveLayers  = [];
let _dwdLoadedCount   = 0;   // Anzahl vollständig geladener Frames
// Modal-Zustand
let _dwdModalMap     = null;
let _dwdModalMarker  = null;
let _dwdModalLayers  = {};
let _dwdModalPrevIso = null;

const DWD_WMS_URL   = 'https://maps.dwd.de/geoserver/dwd/wms';
const DWD_WMS_LAYER = 'dwd:Niederschlagsradar';
let _dwdInterval = 320; // ms zwischen Frames (DWD)
let _dwdFadeMs   = 300; // CSS-Transition-Dauer (DWD)

function dwdSetSpeed(ms) {
  _dwdInterval = ms;
  _dwdFadeMs   = Math.max(40, ms - 30);
  const t = `opacity ${_dwdFadeMs}ms linear`;
  _dwdActiveLayers.forEach(l => { if (l._container) l._container.style.transition = t; });
  Object.values(_dwdModalLayers).forEach(l => { if (l._container) l._container.style.transition = t; });
  document.getElementById('dwdSpeedVal').textContent = ms + ' ms';
  const mv = document.getElementById('dwdModalSpeedVal');
  if (mv) mv.textContent = ms + ' ms';
  const ms2 = document.getElementById('dwdModalSpeedSlider');
  if (ms2) ms2.value = ms;
}

function toggleDwdRadar(header) {
  const body  = document.getElementById('dwdRadarBody');
  const arrow = document.getElementById('dwdRadarArrow');
  const open  = body.classList.toggle('open');
  arrow.classList.toggle('open', open);
  if (open && !_dwdRadarMap) _dwdRadarInit();
  else if (open) _dwdRadarMap.invalidateSize();
}

function _dwdRadarInit() {
  if (!_radarLat || !_radarLon) return;

  _dwdRadarMap = L.map('dwdRadarMap', { zoomControl: true, minZoom: 4 })
                  .setView([_radarLat, _radarLon], 6);

  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
    maxZoom: 18,
  }).addTo(_dwdRadarMap);

  _dwdStationMarker = L.circleMarker([_radarLat, _radarLon], {
    radius: 6, color: '#2563eb', fillColor: '#2563eb', fillOpacity: 0.9, weight: 2,
  }).addTo(_dwdRadarMap);

  _dwdRadarLoadFrames();
  _attachMapSync();
}

function _dwdSpinner(on) {
  const el = document.getElementById('dwdSpinner');
  if (el) el.style.display = on ? 'flex' : 'none';
}

function _dwdSliderColor() {
  const s = document.getElementById('dwdTimeline');
  if (!s) return;
  s.style.accentColor = _dwdLoadedCount >= _dwdFrames.length ? '#16a34a' : '#dc2626';
}

async function _dwdRadarLoadFrames() {
  _dwdSpinner(true);
  document.getElementById('dwdTimestamp').textContent = '—';
  try {
    // Alte Layer entfernen
    _dwdActiveLayers.forEach(l => { try { _dwdRadarMap.removeLayer(l); } catch(_) {} });
    _dwdActiveLayers = [];
    _dwdFrames       = [];
    _dwdLayers       = {};
    _dwdLoadedCount  = 0;

    // TIME-Dimension aus GetCapabilities lesen
    const cap = await fetch(
      DWD_WMS_URL + '?SERVICE=WMS&VERSION=1.3.0&REQUEST=GetCapabilities'
    ).then(r => r.text());

    const dimMatch = cap.match(
      /Niederschlagsradar[\s\S]*?<Dimension[^>]*name="time"[^>]*>([^<]+)<\/Dimension>/i
    );

    const stepMs = 5 * 60 * 1000;
    const nowMs  = Date.now();
    let   endMs  = nowMs + 2 * 3600 * 1000; // Fallback

    if (dimMatch) {
      const [, endRaw] = dimMatch[1].trim().split('/');
      const parsed = new Date(endRaw).getTime();
      if (!isNaN(parsed)) endMs = parsed;
    }

    // Fenster: 2h Vergangenheit bis Ende der verfügbaren Daten (max. 2h Zukunft)
    const fromMs = Math.ceil((nowMs - 2 * 3600 * 1000) / stepMs) * stepMs;
    const toMs   = Math.min(endMs, nowMs + 2 * 3600 * 1000);

    for (let t = fromMs; t <= toMs; t += stepMs) {
      _dwdFrames.push({ time: t / 1000, iso: new Date(t).toISOString(), isForecast: t > nowMs });
    }

    if (!_dwdFrames.length) {
      document.getElementById('dwdTimestamp').textContent = 'Keine Daten verfügbar';
      _dwdSpinner(false);
      return;
    }

    // Slider konfigurieren – rot bis alle Frames geladen
    const slider = document.getElementById('dwdTimeline');
    slider.max   = _dwdFrames.length - 1;
    _dwdSliderColor(); // initial rot

    // Nur Layer-Objekte registrieren – kein addTo; Tiles werden on-demand geladen
    _dwdFrames.forEach(f => {
      _dwdLayers[f.iso] = L.tileLayer.wms(DWD_WMS_URL, {
        layers:      DWD_WMS_LAYER,
        format:      'image/png',
        transparent: true,
        opacity:     0,
        version:     '1.3.0',
        TIME:        f.iso,
        attribution: '© <a href="https://www.dwd.de">DWD</a>',
      });
    });

    // Aktuellen Zeitpunkt anzeigen (letzter Vergangenheits-Frame)
    const nowIdx = Math.max(0, _dwdFrames.findLastIndex(f => !f.isForecast));
    _dwdCurrent  = 0;
    _dwdSeekTo(nowIdx);
    _dwdSpinner(false);

  } catch(e) {
    _dwdSpinner(false);
    document.getElementById('dwdTimestamp').textContent = 'Fehler: ' + e.message;
  }
}

function _dwdSeekTo(idx) {
  if (!_dwdFrames.length) return;
  idx = Math.max(0, Math.min(_dwdFrames.length - 1, idx));

  const prevLayer = _dwdLayers[_dwdFrames[_dwdCurrent].iso];
  _dwdCurrent = idx;
  const nextFrame = _dwdFrames[idx];
  const nextLayer = _dwdLayers[nextFrame.iso];
  if (!nextLayer) return;

  // Lazy-add: erst beim ersten Anzeigen zur Karte hinzufügen → Tiles laden
  if (!_dwdRadarMap.hasLayer(nextLayer)) {
    nextLayer.addTo(_dwdRadarMap);
    if (nextLayer._container)
      nextLayer._container.style.transition = `opacity ${_dwdFadeMs}ms linear`;
    _dwdActiveLayers.push(nextLayer);
    // Nach jedem Tile prüfen ob dieser Frame komplett geladen ist
    const _onTile = () => {
      if (nextLayer._loading === 0) {
        nextLayer.off('tileload',  _onTile);
        nextLayer.off('tileerror', _onTile);
        _dwdLoadedCount++;
        _dwdSliderColor();
      }
    };
    nextLayer.on('tileload',  _onTile);
    nextLayer.on('tileerror', _onTile);
  }

  if (prevLayer && prevLayer !== nextLayer) prevLayer.setOpacity(0);
  nextLayer.setOpacity(0.75);

  const dt = new Date(nextFrame.time * 1000);
  const ts = dt.toLocaleString('de-DE', {
    timeZone: TZ, day: '2-digit', month: '2-digit',
    hour: '2-digit', minute: '2-digit',
  });
  document.getElementById('dwdTimestamp').textContent = ts + ' Uhr';
  document.getElementById('dwdNowcastBadge').style.display = nextFrame.isForecast ? '' : 'none';
  document.getElementById('dwdTimeline').value = idx;

  // Modal synchronisieren (falls offen)
  if (document.getElementById('dwdModal').style.display !== 'none') {
    _dwdModalShowFrame(prevLayer, nextFrame, idx);
  }
}

function dwdSeek(idx) { _dwdSeekTo(idx); }

function dwdTogglePlay() {
  _dwdPlaying = !_dwdPlaying;
  const label = _dwdPlaying ? '⏸ Pause' : '▶ Play';
  document.getElementById('dwdPlayBtn').textContent = label;
  const mb = document.getElementById('dwdModalPlayBtn');
  if (mb) mb.textContent = label;
  if (_dwdPlaying) _dwdStep();
}

function dwdModalTogglePlay() { dwdTogglePlay(); }

function _dwdStep() {
  if (!_dwdPlaying) return;
  _dwdSeekTo((_dwdCurrent + 1) % _dwdFrames.length);
  _dwdTimer = setTimeout(_dwdStep, _dwdInterval);
}

function dwdRadarRefresh() {
  _dwdPlaying = false;
  if (document.getElementById('dwdPlayBtn'))
    document.getElementById('dwdPlayBtn').textContent = '▶ Play';
  if (_dwdTimer) { clearTimeout(_dwdTimer); _dwdTimer = null; }
  if (_dwdRadarMap) _dwdRadarLoadFrames();
}

function _attachMapSync() {
  if (!_radarMap || !_dwdRadarMap) return;
  _radarMap.on('zoomend moveend', () => {
    if (_mapSyncing) return;
    _mapSyncing = true;
    _dwdRadarMap.setView(_radarMap.getCenter(), _radarMap.getZoom(), { animate: false });
    _mapSyncing = false;
  });
  _dwdRadarMap.on('zoomend moveend', () => {
    if (_mapSyncing) return;
    _mapSyncing = true;
    _radarMap.setView(_dwdRadarMap.getCenter(), _dwdRadarMap.getZoom(), { animate: false });
    _mapSyncing = false;
  });
}

// ─── DWD Vollbild-Modal (eigene Leaflet-Instanz, lazy-cached) ────────────────

function dwdOpenModal() {
  if (!_dwdRadarMap) return;
  const modal = document.getElementById('dwdModal');
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';

  if (!_dwdModalMap) {
    _dwdModalMap = L.map('dwdModalMap', { zoomControl: true, minZoom: 4 })
                    .setView(_dwdRadarMap.getCenter(), _dwdRadarMap.getZoom());
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
      maxZoom: 18,
    }).addTo(_dwdModalMap);
    if (_radarLat) {
      _dwdModalMarker = L.circleMarker([_radarLat, _radarLon], {
        radius: 6, color: '#2563eb', fillColor: '#2563eb', fillOpacity: 0.9, weight: 2,
      }).addTo(_dwdModalMap);
    }
    // Registrierte Frames als leere Layer anlegen
    _dwdFrames.forEach(f => {
      _dwdModalLayers[f.iso] = L.tileLayer.wms(DWD_WMS_URL, {
        layers: DWD_WMS_LAYER, format: 'image/png',
        transparent: true, opacity: 0, version: '1.3.0',
        TIME: f.iso, attribution: '© DWD',
      });
    });
    // Modal-Slider initialisieren
    const sl = document.getElementById('dwdModalTimeline');
    if (sl) { sl.max = Math.max(0, _dwdFrames.length - 1); sl.value = _dwdCurrent; }
    const spd = document.getElementById('dwdModalSpeedSlider');
    if (spd) spd.value = _dwdInterval;
    const spv = document.getElementById('dwdModalSpeedVal');
    if (spv) spv.textContent = _dwdInterval + ' ms';
  } else {
    _dwdModalMap.setView(_dwdRadarMap.getCenter(), _dwdRadarMap.getZoom());
    if (_dwdModalMarker && _radarLat) _dwdModalMarker.setLatLng([_radarLat, _radarLon]);
  }

  // Play-Button-State synchronisieren
  const mb = document.getElementById('dwdModalPlayBtn');
  if (mb) mb.textContent = _dwdPlaying ? '⏸ Pause' : '▶ Play';

  _dwdModalShowFrame(null, _dwdFrames[_dwdCurrent], _dwdCurrent);
  setTimeout(() => _dwdModalMap.invalidateSize(), 80);
}

// prevLayer = vorheriger Layer (für Crossfade); kann null sein
function _dwdModalShowFrame(prevMainLayer, nextFrame, idx) {
  if (!_dwdModalMap || !nextFrame) return;

  const prevIso  = _dwdModalPrevIso;
  const prevML   = prevIso ? _dwdModalLayers[prevIso] : null;
  const nextML   = _dwdModalLayers[nextFrame.iso];
  if (!nextML) return;

  // Lazy-add: erst beim ersten Anzeigen zur Modal-Karte hinzufügen
  if (!_dwdModalMap.hasLayer(nextML)) {
    nextML.addTo(_dwdModalMap);
    if (nextML._container)
      nextML._container.style.transition = `opacity ${_dwdFadeMs}ms linear`;
  }

  if (prevML && prevML !== nextML) prevML.setOpacity(0);
  nextML.setOpacity(0.75);
  _dwdModalPrevIso = nextFrame.iso;

  // UI-Controls synchronisieren
  const dt = new Date(nextFrame.time * 1000);
  const ts = dt.toLocaleString('de-DE', {
    timeZone: TZ, day: '2-digit', month: '2-digit',
    hour: '2-digit', minute: '2-digit',
  });
  const el = document.getElementById('dwdModalTimestamp');
  if (el) el.textContent = ts + ' Uhr';
  const badge = document.getElementById('dwdModalNowcastBadge');
  if (badge) badge.style.display = nextFrame.isForecast ? '' : 'none';
  const sl = document.getElementById('dwdModalTimeline');
  if (sl) sl.value = idx;
}

function dwdCloseModal() {
  document.getElementById('dwdModal').style.display = 'none';
  document.body.style.overflow = '';
}

// Backdrop-Klick schließt Modal
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('dwdModal')?.addEventListener('click', e => {
    if (e.target.id === 'dwdModal') dwdCloseModal();
  });
});

async function loadDbInfo() {
  try {
    const d = await (await fetch('api.php?action=meta')).json();
    if (!d.import_time) return;
    const fmtDt = iso => new Date(iso).toLocaleString('de-DE', {
      timeZone: TZ,
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
    const ageH  = d.db_age_hours ?? '?';
    const ageCls = ageH < 7 ? 'color:#166534' : 'color:#92400e';
    const bar = document.getElementById('dbInfoBar');
    bar.innerHTML = [
      `<span>📡 <b>Quelle:</b> DWD MOSMIX-L</span>`,
      d.model_run_utc ? `<span>🕐 <b>Modell-Lauf:</b> ${fmtDt(d.model_run_utc)} UTC</span>` : '',
      `<span>📥 <b>Import:</b> ${fmtDt(d.import_time)} UTC</span>`,
      `<span style="${ageCls}">⏱ <b>Datenalter:</b> ${ageH} h</span>`,
      d.valid_from_utc ? `<span>📅 <b>Gültig:</b> ${fmtDt(d.valid_from_utc)} – ${fmtDt(d.valid_to_utc)} UTC</span>` : '',
      d.station_count  ? `<span>📍 <b>Stationen:</b> ${parseInt(d.station_count).toLocaleString('de-DE')}</span>` : '',
    ].filter(Boolean).join('');
    bar.style.display = 'flex';
  } catch(e) {}
}
</script>

<!-- DWD Vollbild-Modal (eigene Leaflet-Instanz, eigene Controls) -->
<div id="dwdModal">
  <div id="dwdModalBox">
    <div id="dwdModalHeader">
      <span>📡 DWD Niederschlagsradar – Vollbild</span>
      <button class="radar-btn" onclick="dwdCloseModal()">✕ Schließen</button>
    </div>
    <div id="dwdModalMap"></div>
    <div class="radar-controls">
      <button class="radar-btn" id="dwdModalPlayBtn" onclick="dwdModalTogglePlay()">▶ Play</button>
      <input type="range" id="dwdModalTimeline" min="0" value="0" step="1"
             style="flex:1;min-width:80px;accent-color:var(--accent)"
             oninput="dwdSeek(+this.value)">
      <span id="dwdModalTimestamp" style="font-size:.78rem;color:var(--muted)">—</span>
      <span class="radar-nowcast-badge" id="dwdModalNowcastBadge" style="display:none">Vorhersage</span>
      <span class="radar-speed-wrap">
        <input type="range" id="dwdModalSpeedSlider" min="50" max="800" value="320" step="10"
               oninput="dwdSetSpeed(+this.value);document.getElementById('dwdSpeedSlider').value=this.value">
        <span id="dwdModalSpeedVal">320 ms</span>
      </span>
    </div>
  </div>
</div>

</body>
</html>

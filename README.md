# MOSMIX Weather Pipeline

A self-hosted PHP pipeline that downloads DWD MOSMIX-L weather forecast data, stores it in a local SQLite database, and serves it via a JSON API with an interactive web frontend.

## Features

- Downloads and parses the full DWD MOSMIX-L KMZ file (~200 MB, ~5,000+ stations worldwide) every 6 hours
- Streams the KML using `XMLReader` + `SimpleXML` for low memory usage (~1 GB limit sufficient)
- Spatial interpolation (IDW – Inverse Distance Weighting) with elevation correction for arbitrary coordinates
- Pre-computed forecasts for fixed locations defined in `locations.txt`
- City search with autocomplete (GeoNames DE/AT/CH, ~100k places)
- JSON API for on-demand forecasts and saved locations
- Interactive frontend with temperature/precipitation/wind charts (Chart.js) and two animated radar panels (RainViewer + DWD WMS)
- Radar panels are zoom-synchronized; animation speed is adjustable via slider

## Requirements

- PHP 8.1+ (tested on 8.3) with extensions: `sqlite3`, `curl`, `zip`, `xml` (`SimpleXMLElement` + `XMLReader`)
- A web server (Apache, Nginx) with PHP-FPM or mod_php
- ~500 MB free disk space for the database and KMZ cache

### Install PHP extensions (Debian/Ubuntu)

```bash
sudo apt install php8.3-sqlite3 php8.3-curl php8.3-zip php8.3-xml
```

## Installation

```bash
git clone https://github.com/YOUR_USERNAME/mosmix.git /var/www/html/mosmix
cd /var/www/html/mosmix
```

The web root must be served by your web server. No Composer dependencies — all JS/CSS assets are included under `assets/`.

### First run

#### 1. Import the cities database (one-time)

This populates the `cities` table used for location search and `locations.txt` name resolution:

```bash
php import_cities.php
```

Takes ~2–5 minutes (queries Wikidata SPARQL for DE, AT, CH cities with population ≥ 5,000).

#### 2. Define your locations

Edit `locations.txt` — one location per line:

```
# City name (resolved via cities DB)
Berlin
München

# Coordinates: lat,lon  or  lat,lon,elevation_m
48.137,11.576,519
54.75,9.5,33
```

Lines starting with `#` and blank lines are ignored.

#### 3. Run the pipeline

```bash
php pipeline.php
```

This downloads the MOSMIX-L KMZ (~200 MB), parses all stations, and interpolates forecasts for your locations. Takes about 5–15 minutes depending on hardware.

## Cron job

Run every 6 hours, offset by 30 minutes to account for DWD upload lag:

```
30 0,6,12,18 * * * /usr/bin/php /var/www/html/mosmix/pipeline.php >> /var/log/mosmix.log 2>&1
```

### Recompute locations only (no download)

If you change `locations.txt` and don't want to re-download the KMZ:

```bash
php pipeline.php locations
```

## File structure

```
mosmix/
├── pipeline.php        # Download, parse, interpolate – the main cron script
├── api.php             # JSON API (search, forecast, locations, precomputed, meta)
├── lib.php             # Shared library: IDW, WW codes, DB helpers
├── import_cities.php   # One-time city import from Wikidata (DE/AT/CH)
├── index.php           # Web frontend (charts + radar panels)
├── locations.txt       # Your saved locations (edit this)
├── mosmix.sqlite3      # SQLite database (created on first run, not in repo)
├── data/
│   └── MOSMIX_L_LATEST.kmz   # Downloaded KMZ cache (not in repo)
└── assets/
    ├── chart.umd.min.js       # Chart.js (local)
    ├── leaflet.js             # Leaflet 1.9.4 (local)
    ├── leaflet.css
    └── images/                # Leaflet marker icons
```

## Database schema

| Table | Purpose |
|---|---|
| `stations` | All DWD MOSMIX stations (id, name, lat, lon, elevation) |
| `forecasts` | Raw per-station forecast data (hourly, ~240 h horizon) |
| `my_locations` | Your saved locations from `locations.txt` |
| `my_forecasts` | Pre-interpolated forecasts for saved locations |
| `cities` | GeoNames city data for search (populated by `import_cities.php`) |
| `meta` | Pipeline run metadata (import time, model run, data range) |

## API endpoints

All endpoints are `GET` requests to `api.php`.

| Action | Parameters | Description |
|---|---|---|
| `search` | `q` (string, min 2 chars), `limit` (int, default 10) | City autocomplete |
| `forecast` | `lat`, `lon`, `elev` (optional), `hours` (default 12, max 240) | On-demand IDW interpolation |
| `precomputed` | `id` (location id), `hours` (default 12, max 240) | Pre-computed forecast for saved location |
| `locations` | — | List all saved locations |
| `meta` | — | Database and pipeline metadata |

Example:

```bash
curl "http://localhost/mosmix/api.php?action=forecast&lat=48.137&lon=11.576&elev=519&hours=48"
```

## Configuration

Constants in `pipeline.php` and `lib.php` (defaults shown):

| Constant | Default | Description |
|---|---|---|
| `SEARCH_RADIUS_DEG` | `1.0` | Station search radius in degrees (~111 km) |
| `IDW_NEIGHBORS` | `4` | Number of nearest stations for interpolation |
| `ELEV_PENALTY_KM` | `0.1` | km penalty per 100 m elevation difference |
| `RAIN_ACCUM_HOURS` | `6` | Rolling rain accumulation window |
| `TZ_LOCAL` | `Europe/Berlin` | Local timezone for time block alignment |

## .gitignore

Add the following to avoid committing the database and downloaded data:

```
mosmix.sqlite3
mosmix.db
data/MOSMIX_L_LATEST.kmz
data/MOSMIX_L_LATEST_240.kmz
locations.txt~
```

## Data sources

- **DWD MOSMIX-L**: [opendata.dwd.de](https://opendata.dwd.de/weather/local_forecasts/mos/MOSMIX_L/all_stations/kml/) — Open Data, [DWD licence](https://www.dwd.de/DE/service/copyright/copyright_node.html)
- **Radar (RainViewer)**: [rainviewer.com API](https://www.rainviewer.com/api.html)
- **Radar (DWD WMS)**: [maps.dwd.de](https://maps.dwd.de/geoserver/dwd/wms) — layer `dwd:Niederschlagsradar`
- **Cities (Wikidata)**: CC0 — queried once during setup via `import_cities.php`

## Licence

MIT

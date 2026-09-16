<!DOCTYPE html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>GPS Test</title>
<style>
  body { font-family: system-ui; padding: 2rem; max-width: 480px; margin: auto; }
  pre  { background: #f1f5f9; padding: 1rem; border-radius: 8px; font-size: .9rem; white-space: pre-wrap; }
  h3   { margin: 1.5rem 0 .5rem; }
  button { padding: .6rem 1.2rem; font-size: 1rem; cursor: pointer; }
</style>

<h2>📍 Standort-Test</h2>

<h3>🌐 IP-Geolokalisierung (client-seitig)</h3>
<pre id="ip-out">⏳ Ermittle …</pre>

<h3>📡 GPS (nur HTTPS)</h3>
<pre id="gps-out">→ Button klicken</pre>
<button onclick="gpsGo()">GPS ermitteln</button>

<script>
// IP-Lookup direkt vom Browser → ip-api.com sieht die öffentliche IP des Users
fetch('http://ip-api.com/json/?fields=status,message,query,lat,lon,city,regionName,country')
  .then(r => r.json())
  .then(d => {
    const o = document.getElementById('ip-out');
    if (d.status !== 'success') { o.textContent = 'Fehler: ' + (d.message ?? 'unbekannt'); return; }
    o.textContent = [
      'Öffentliche IP: ' + d.query,
      'Breite:         ' + d.lat.toFixed(4),
      'Länge:          ' + d.lon.toFixed(4),
      'Ort:            ' + d.city + ', ' + d.regionName + ', ' + d.country,
      'Genauigkeit:    ~1–20 km (stadtgenau)',
    ].join('\n');
  })
  .catch(e => { document.getElementById('ip-out').textContent = 'Fehler: ' + e.message; });

function gpsGo() {
  const o = document.getElementById('gps-out');
  if (!window.isSecureContext) { o.textContent = '⚠ Kein HTTPS – GPS vom Browser gesperrt.'; return; }
  if (!navigator.geolocation)  { o.textContent = 'Geolocation nicht verfügbar.'; return; }
  o.textContent = '⏳ Ermittle …';
  navigator.geolocation.getCurrentPosition(
    p => { const c = p.coords; o.textContent = [
      'Breite:       ' + c.latitude.toFixed(6),
      'Länge:        ' + c.longitude.toFixed(6),
      'Höhe:         ' + (c.altitude != null ? c.altitude.toFixed(1) + ' m' : '—'),
      'Genauigkeit:  ' + c.accuracy.toFixed(0) + ' m',
      'Zeitstempel:  ' + new Date(p.timestamp).toLocaleString('de-DE'),
    ].join('\n'); },
    e => { o.textContent = 'Fehler ' + e.code + ': ' + e.message; },
    { enableHighAccuracy: true, timeout: 15000 }
  );
}
</script>

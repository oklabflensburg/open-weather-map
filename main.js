fetch('http://localhost:8000/stations', {
// fetch('https://api.open-weather-map.de/stations', {
  method: 'GET'
})
.then((response) => {
  return response.json()
})
.then((data) => {
  marker(data);
})
.catch(function (error) {
  console.log(error);
});


const map = L.map('map').setView([50.7836, 9.4321], 10);

L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
}).addTo(map);

let geocoder = L.Control.Geocoder.nominatim();

if (typeof URLSearchParams !== 'undefined' && location.search) {
    // parse /?geocoder=nominatim from URL
    let params = new URLSearchParams(location.search);
    let geocoderString = params.get('geocoder');

    if (geocoderString && L.Control.Geocoder[geocoderString]) {
        console.log('Using geocoder', geocoderString);
        geocoder = L.Control.Geocoder[geocoderString]();
    } else if (geocoderString) {
        console.warn('Unsupported geocoder', geocoderString);
    }
}

const osmGeocoder = new L.Control.geocoder({
    query: 'Flensburg',
    position: 'topright',
    placeholder: 'Adresse oder Ort',
    defaultMarkGeocode: false
}).addTo(map);

osmGeocoder.on('markgeocode', e => {
    const bounds = L.latLngBounds(e.geocode.bbox._southWest, e.geocode.bbox._northEast);
    map.fitBounds(bounds);
});


function marker(data) {
    let markers = L.markerClusterGroup({
        zoomToBoundsOnClick: true,
        disableClusteringAtZoom: 13
    });

    const geojsonGroup = L.geoJSON(data, {
        onEachFeature: function (feature, layer) {
            layer.on('click', function (e) {
                document.getElementById('filter').scrollTo({
                    top: 0,
                    left: 0
                });

                map.setView(e.latlng, 13)

                /*
                let uart = e.target.feature.properties.uart
                let ukategorie = e.target.feature.properties.ukategorie
                let ulichtverh = e.target.feature.properties.ulichtverh
                let ustrzustan = e.target.feature.properties.ustrzustan
                let istrad = e.target.feature.properties.istrad
                let istpkw = e.target.feature.properties.istpkw
                let istgkfz = e.target.feature.properties.istgkfz
                let istkrad = e.target.feature.properties.istkrad
                let istsonstig = e.target.feature.properties.istsonstig
                let istfuss = e.target.feature.properties.istfuss
                let utyp1 = e.target.feature.properties.utyp1

                document.getElementById('details').classList.remove('hidden');
                document.getElementById('uart').innerHTML = uart || '---';
                document.getElementById('utyp1').innerHTML = utyp1 || '---';
                document.getElementById('ukategorie').innerHTML = ukategorie || '---';
                document.getElementById('ulichtverh').innerHTML = ulichtverh || '---';
                document.getElementById('ustrzustan').innerHTML = ustrzustan || '---';
                document.getElementById('istrad').innerHTML = istrad || '---';
                document.getElementById('istpkw').innerHTML = istpkw || '---';
                document.getElementById('istgkfz').innerHTML = istgkfz || '---';
                document.getElementById('istkrad').innerHTML = istkrad || '---';
                document.getElementById('istfuss').innerHTML = istfuss || '---';
                document.getElementById('istsonstig').innerHTML = istsonstig || '---';
                */
            })
        },
        pointToLayer: function (feature, latlng) {
            const label = String(feature.properties.city_name);

            const customIcon = L.icon({
                iconUrl: '/assets/marker-icon-blue.png',
                shadowUrl: '/assets/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                tooltipAnchor: [0, -38],
                shadowSize: [41, 41]
            });

            return L.marker(latlng, {icon: customIcon}).bindTooltip(label, {
                permanent: false,
                direction: 'top'
            }).openTooltip();
        }
    });

    markers.addLayer(geojsonGroup);
    map.addLayer(markers);
}

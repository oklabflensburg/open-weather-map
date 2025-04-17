// This file ensures Leaflet CSS is only imported on the client side
export default defineNuxtPlugin(() => {
    import('leaflet/dist/leaflet.css')
    import('leaflet.markercluster/dist/MarkerCluster.css')
    import('leaflet.markercluster/dist/MarkerCluster.Default.css')
})

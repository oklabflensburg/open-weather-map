<template>
    <div id="map" ref="mapContainer" class="w-full h-full flex-grow"></div>
</template>

<script>
// Use defineComponent with SSR: false to make this component client-only
export default {
  name: 'WeatherMap',
  ssr: false
}
</script>

<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'

const emit = defineEmits(['marker-click', 'map-click'])

// Refs
const mapContainer = ref(null)
const map = ref(null)
const markersLayer = ref(null)

// State
const currentSelectedMarker = ref(null)
const selectedMarkerLatLng = ref(null)
const isZoomingToMarker = ref(false)
const currentZoomLevel = ref(9)

// Leaflet icon references - initialized later to avoid SSR issues
let defaultIcon = null
let highlightedIcon = null
let searchIcon = null
let L = null  // Store Leaflet reference

// Import Leaflet dynamically (client-side only)
onMounted(() => {
  // Ensure we're on the client side before trying to use Leaflet
  if (process.client) {
    // Use a function to initialize everything - helps with error handling
    initLeaflet()
  }
})

async function initLeaflet() {
  try {
    // Dynamically import Leaflet
    const leaflet = await import('leaflet')
    L = leaflet.default
    
    // Wait for MarkerCluster to load
    await import('leaflet.markercluster')

    // Wait for next tick to ensure DOM is ready
    await nextTick()
    
    if (!mapContainer.value) {
      console.error('Map container reference is not available')
      return
    }

    // Setup icons
    defaultIcon = L.icon({
      iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
      iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34],
      shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
      shadowSize: [41, 41]
    })

    highlightedIcon = L.icon({
      iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
      iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34],
      shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
      shadowSize: [41, 41]
    })

    searchIcon = L.icon({
      iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
      iconSize: [25, 41], iconAnchor: [12, 41],
      shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
      shadowSize: [41, 41]
    })

    // Initialize map
    map.value = L.map(mapContainer.value, {
      zoomControl: false
    }).setView([54.5, 10], currentZoomLevel.value)

    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map.value)

    // Create marker cluster group
    markersLayer.value = L.markerClusterGroup({
      disableClusteringAtZoom: 12,
      spiderfyOnMaxZoom: false,
      chunkedLoading: true,
      maxClusterRadius: 50,
      iconCreateFunction: function(cluster) {
        const count = cluster.getChildCount()
        let size, className

        if (count < 10) {
          size = 'small'
          className = 'bg-blue-500'
        } else if (count < 100) {
          size = 'medium'
          className = 'bg-blue-600'
        } else {
          size = 'large'
          className = 'bg-blue-700'
        }

        return L.divIcon({
          html: `<div class="flex items-center justify-center rounded-full ${className} text-white font-bold" style="width: 100%; height: 100%">${count}</div>`,
          className: `marker-cluster marker-cluster-${size}`,
          iconSize: [40, 40]
        })
      }
    }).addTo(map.value)

    // Map event listeners
    map.value.on('moveend', handleMapMoveEnd)
    map.value.on('click', handleMapClick)
    map.value.on('zoomstart', handleZoomStart)

    // Initial load of markers
    updateMarkers()
  } catch (error) {
    console.error('Failed to initialize Leaflet map:', error)
  }
}

onBeforeUnmount(() => {
  // Clean up Leaflet map on component unmount
  if (map.value) {
    map.value.remove()
    map.value = null
  }
})

// Event handlers
function handleMapMoveEnd() {
  if (!map.value) return
  
  const newZoom = map.value.getZoom()
  const isZoomingOut = newZoom < currentZoomLevel.value
  currentZoomLevel.value = newZoom

  if (isZoomingOut && currentSelectedMarker.value) {
    resetSelectedMarker()
    emit('map-click')
  }

  if (!isZoomingToMarker.value) {
    debouncedUpdateMarkers()
  } else {
    setTimeout(() => { isZoomingToMarker.value = false }, 500)
  }
}

function handleMapClick(e) {
  // Check if the click was directly on the map (not on marker or control)
  if (e.originalEvent && 
     (e.originalEvent.target === mapContainer.value || 
      e.originalEvent.target.classList.contains('leaflet-tile'))) {
    emit('map-click')
    resetSelectedMarker()
  }
}

function handleZoomStart() {
  if (!map.value) return
  // Only reset zoom flag if actually zooming out
  isZoomingToMarker.value = map.value.getZoom() < currentZoomLevel.value 
    ? false 
    : isZoomingToMarker.value
}

// Helper functions
let debounceTimeout
function debouncedUpdateMarkers() {
  clearTimeout(debounceTimeout)
  debounceTimeout = setTimeout(updateMarkers, 300)
}

// Marker handling methods
async function fetchMarkersByBounds() {
  if (!map.value) return []
  
  const bounds = map.value.getBounds()
  const url = `https://api.oklabflensburg.de/climate/v1/mosmix/bounds?xmin=${bounds.getWest().toFixed(6)}&ymin=${bounds.getSouth().toFixed(6)}&xmax=${bounds.getEast().toFixed(6)}&ymax=${bounds.getNorth().toFixed(6)}`
  
  try {
    const response = await fetch(url)
    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`)
    const data = await response.json()
    return data.features || []
  } catch (error) {
    console.error("Error fetching markers:", error)
    return []
  }
}

async function updateMarkers() {
  if (!markersLayer.value || !map.value || !L) return
  
  try {
    const prevSelectedLatLng = selectedMarkerLatLng.value ? [...selectedMarkerLatLng.value] : null
    const markerFeatures = await fetchMarkersByBounds()

    markersLayer.value.clearLayers()
    let foundSelectedMarker = false

    markerFeatures.forEach(feature => {
      if (feature.geometry?.coordinates) {
        const [longitude, latitude] = feature.geometry.coordinates
        const properties = feature.properties || {}
        const title = properties.station_name || "Wetterstation"
        const stationId = feature.id || ''

        const mapMarker = L.marker([latitude, longitude], {
          icon: defaultIcon,
          title: title
        })

        // Store the stationId directly on the marker for access in click handler
        mapMarker.stationId = stationId

        // Check if this was the previously selected marker
        if (prevSelectedLatLng &&
            Math.abs(prevSelectedLatLng[0] - latitude) < 0.0001 &&
            Math.abs(prevSelectedLatLng[1] - longitude) < 0.0001) {
          mapMarker.setIcon(highlightedIcon)
          currentSelectedMarker.value = mapMarker
          foundSelectedMarker = true
        }

        // Set up click handler
        mapMarker.on('click', (e) => {
          if (L) L.DomEvent.stopPropagation(e)
          handleMarkerClick(mapMarker, title, stationId, latitude, longitude)
        })

        // Add to marker cluster
        markersLayer.value.addLayer(mapMarker)
      }
    })

    // If the previously selected marker wasn't found in the new bounds
    if (prevSelectedLatLng && !foundSelectedMarker) {
      resetSelectedMarker()
    }
  } catch (error) {
    console.error("Error updating markers:", error)
  }
}

function handleMarkerClick(marker, title, stationId, lat, lon) {
  // Reset previously selected marker
  resetSelectedMarker()
  
  // Make sure Leaflet is available
  if (!L) return
  
  // Highlight the clicked marker
  marker.setIcon(highlightedIcon)
  currentSelectedMarker.value = marker
  selectedMarkerLatLng.value = [lat, lon]
  
  // Set flag and zoom to marker
  isZoomingToMarker.value = true
  map.value.flyTo([lat, lon], 15, { animate: true, duration: 0.5 })
    
  // Emit marker click event with all necessary data
  emit('marker-click', { 
    title, 
    stationId,
    latitude: lat,
    longitude: lon
  })
}

// Exposed methods
function resetSelectedMarker() {
  if (!currentSelectedMarker.value || !defaultIcon || !L) return
  
  try {
    if (map.value && map.value.hasLayer(currentSelectedMarker.value)) {
      currentSelectedMarker.value.setIcon(defaultIcon)
    }
  } catch (error) {
    console.warn("Could not reset marker icon:", error)
  }
  currentSelectedMarker.value = null
  selectedMarkerLatLng.value = null
}

function searchLocation(query) {
  if (!query || query.trim().length < 3 || !map.value || !L) return false
  
  return new Promise(async (resolve) => {
    try {
      const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`
      const response = await fetch(url, {
        headers: { 'User-Agent': 'WeatherMapApplication/1.0' }
      })
      
      if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`)
      const data = await response.json()
      
      if (data && data.length > 0) {
        const result = data[0]
        flyToLocation(result.lat, result.lon, 12)
        addSearchMarker(result)
        resolve(true)
      } else {
        resolve(false)
      }
    } catch (error) {
      console.error("Error searching location:", error)
      resolve(false)
    }
  })
}

function flyToLocation(lat, lon, zoom) {
  if (!map.value) return
  
  map.value.flyTo([parseFloat(lat), parseFloat(lon)], zoom, {
    animate: true,
    duration: 1
  })
}

function addSearchMarker(location) {
  if (!map.value || !searchIcon || !L) return
  
  const marker = L.marker([parseFloat(location.lat), parseFloat(location.lon)], {
    icon: searchIcon,
    title: location.display_name
  }).addTo(map.value)
  
  setTimeout(() => {
    if (map.value && marker) {
      map.value.removeLayer(marker)
    }
  }, 8000)
}

// Expose methods to parent components
defineExpose({
  searchLocation,
  flyToLocation,
  addSearchMarker,
  resetSelectedMarker
})
</script>

<style>
/* These styles need to be global or scoped properly */
.marker-cluster-small,
.marker-cluster-medium,
.marker-cluster-large {
  background-clip: padding-box;
  border-radius: 50%;
}
.marker-cluster div {
  width: 30px;
  height: 30px;
  margin-left: 5px;
  margin-top: 5px;
  text-align: center;
  border-radius: 50%;
  font-size: 12px;
  line-height: 30px;
}
</style>

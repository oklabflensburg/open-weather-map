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
import { ref, onMounted, onBeforeUnmount, nextTick, watch, computed } from 'vue'

// Import the search state composable
import { useSearchState } from '~/composables/useSearchState'
import { useSearch } from '~/composables/useSearch'

// Get runtime config for API URLs
const config = useRuntimeConfig()
const weatherApiBaseUrl = computed(() => config.public.weatherApiBaseUrl)

const searchState = useSearchState()
const { getLocationDetails } = useSearch()

const emit = defineEmits(['marker-click', 'map-click', 'mouse-move'])

// Refs
const mapContainer = ref(null)
const map = ref(null)
const markersLayer = ref(null)
const searchOverlayLayer = ref(null)

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

    // Create a layer group for search overlays (polygons, etc)
    searchOverlayLayer.value = L.layerGroup().addTo(map.value)

    // Map event listeners
    map.value.on('moveend', handleMapMoveEnd)
    map.value.on('click', handleMapClick)
    map.value.on('zoomstart', handleZoomStart)
    map.value.on('mousemove', handleMouseMove)

    // Initial load of markers
    updateMarkers()
  } catch (error) {
    console.error('Failed to initialize Leaflet map:', error)
  }
}

onBeforeUnmount(() => {
  // Clean up Leaflet map on component unmount
  if (map.value) {
    map.value.off('mousemove', handleMouseMove)
    map.value.remove()
    map.value = null
  }

  // Clear any search overlays
  if (searchOverlayLayer.value) {
    searchOverlayLayer.value.clearLayers()
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

function handleMouseMove(e) {
  if (!map.value) return
  
  // Get the latitude and longitude from the mouse event
  const { lat, lng } = e.latlng
  
  // Format to 6 decimal places
  const formattedLat = lat.toFixed(6)
  const formattedLng = lng.toFixed(6)
  
  // Emit the coordinates to parent components
  emit('mouse-move', { lat: formattedLat, lng: formattedLng })
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
  const url = `${weatherApiBaseUrl.value}/climate/v1/mosmix/bounds?xmin=${bounds.getWest().toFixed(6)}&ymin=${bounds.getSouth().toFixed(6)}&xmax=${bounds.getEast().toFixed(6)}&ymax=${bounds.getNorth().toFixed(6)}`
  
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

function flyToLocation(lat, lon, zoom) {
  if (!map.value) return
  
  map.value.flyTo([parseFloat(lat), parseFloat(lon)], zoom, {
    animate: true,
    duration: 1
  })
}

function addGeoJsonToMap(geojson, name) {
  if (!map.value || !L || !searchOverlayLayer.value) return
  
  try {
    // Add the GeoJSON to the map with styling
    const layer = L.geoJSON(geojson, {
      style: {
        color: '#3388ff',
        weight: 4,
        opacity: 0.7,
        fillColor: '#3388ff',
        fillOpacity: 0.1
      },
      onEachFeature: (feature, layer) => {
        // Add a popup with the name
        if (name) {
          layer.bindPopup(name)
        }
      }
    }).addTo(searchOverlayLayer.value)
    
    // Fit the map to the bounds of the layer
    if (layer.getBounds) {
      map.value.fitBounds(layer.getBounds(), {
        padding: [50, 50] // Add some padding around the bounds
      })
    }
    
    // Remove after some time (optional)
    setTimeout(() => {
      if (map.value && searchOverlayLayer.value) {
        searchOverlayLayer.value.removeLayer(layer)
      }
    }, 15000) // 15 seconds
  } catch (error) {
    console.error('Error adding GeoJSON to map:', error)
  }
}

// Watch for search state changes
watch(
  () => searchState.isSearching.value,
  async (isSearching) => {
    if (isSearching) {
      if (searchState.selectedLocation.value) {
        // Handle location selection with polygon support
        const location = searchState.selectedLocation.value
        
        // Clear previous overlays
        if (searchOverlayLayer.value) {
          searchOverlayLayer.value.clearLayers()
        }
        
        // Show the location on the map (center and zoom)
        flyToLocation(location.lat, location.lon, 12)
        
        // Handle polygon if available or try to fetch it
        if (location.geojson) {
          addGeoJsonToMap(location.geojson, location.display_name)
        } else if (location.osm_type && location.osm_id) {
          // Fetch detailed geometry if basic geojson is not included
          const details = await getLocationDetails(location.osm_type, location.osm_id)
          if (details && details.geometry) {
            addGeoJsonToMap(details.geometry, location.display_name)
          }
        }
        
        searchState.markSearchHandled()
      } else if (searchState.searchQuery.value) {
        // Handle search query
        await searchLocation(searchState.searchQuery.value)
        searchState.markSearchHandled()
      }
    }
  }
)

// Expose methods to parent components
defineExpose({
  flyToLocation,
  resetSelectedMarker
})
</script>
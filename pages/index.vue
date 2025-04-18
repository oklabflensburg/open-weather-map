<template>
  <div class="w-full h-full flex-grow flex flex-col relative" id="main-content">
    <!-- Map Component with ClientOnly wrapper -->
    <ClientOnly>
      <WeatherMap 
        ref="mapRef" 
        @marker-click="handleMarkerClick" 
        @map-click="hideDetailsPanel"
        @mouse-move="handleMouseCoordinates"
      />
      <template #fallback>
        <div class="w-full h-full flex-grow flex items-center justify-center bg-gray-100">
          <div class="text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500 mb-2"></div>
            <p>Loading map...</p>
          </div>
        </div>
      </template>
    </ClientOnly>
    
    <ClientOnly>
      <DetailsPanel 
        ref="detailsRef"
        :title="markerTitle" 
        :content="markerContent" 
        :is-visible="detailsPanelVisible"
        @close="hideDetailsPanel"
      />
      <template #fallback>
        <!-- Fallback content for SSR -->
        <div class="hidden">Loading details panel...</div>
      </template>
    </ClientOnly>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useWeatherData } from '~/composables/useWeatherData'
import { useMapCoordinates } from '~/composables/useMapCoordinates'

// Component refs
const mapRef = ref(null)
const detailsRef = ref(null)
const mobileMenuOpen = ref(false)

function toggleMenu() {
  mobileMenuOpen.value = !mobileMenuOpen.value
}
// Details panel state
const detailsPanelVisible = ref(false)
const markerTitle = ref('')
const markerContent = ref('')

// Weather data composable
const { fetchForecastData, formatForecastData } = useWeatherData()

async function handleMarkerClick(marker) {
  // Set initial state with loading indicator
  markerTitle.value = marker.title
  markerContent.value = `Stations-ID: ${marker.stationId || 'N/V'}<div class="mt-4 text-center"><div class="inline-block animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-blue-500"></div> Vorhersagedaten werden geladen...</div>`
  
  // Show panel immediately
  detailsPanelVisible.value = true
  
  // Fetch and format forecast data
  if (marker.stationId) {
    const forecastData = await fetchForecastData(marker.stationId)
    if (forecastData) {
      const content = `Stations-ID: ${marker.stationId}${formatForecastData(forecastData)}`
      markerContent.value = content
    } else {
      markerContent.value = `Stations-ID: ${marker.stationId}<p class="mt-4 text-red-500">Vorhersagedaten konnten nicht geladen werden.</p>`
    }
  } else {
    markerContent.value = `Stations-ID: N/V<p class="mt-4">Keine Vorhersagedaten für diese Station verfügbar.</p>`
  }
}

function hideDetailsPanel() {
  detailsPanelVisible.value = false
  if (mapRef.value) {
    mapRef.value.resetSelectedMarker()
  }
}

// Handle map mouse coordinates and forward to layout
function handleMouseCoordinates(coords) {
  useMapCoordinates().setCoordinates(coords)
}

// New functions to handle events from TheNavigation component
function handleNavigationSearch(query) {
  if (mapRef.value) {
    mapRef.value.searchLocation(query)
  }
}

function handleNavigationLocationSelect(location) {
  if (mapRef.value) {
    mapRef.value.flyToLocation(location.lat, location.lon, 12)
    mapRef.value.addSearchMarker(location)
  }
}

// Initialize and clean up
onMounted(() => {
  // Any additional initialization if needed
})

// Expose methods for the navigation component to call
defineExpose({
  handleSearch: handleNavigationSearch,
  handleLocationSelect: handleNavigationLocationSelect
})
</script>

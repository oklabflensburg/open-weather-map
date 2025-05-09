import { ref } from 'vue'

// Create a shared state for map coordinates that can be accessed from any component
const coordinates = ref({ lat: '', lng: '' })

export const useMapCoordinates = () => {
  // Set coordinates from the map component
  function setCoordinates(coords) {
    coordinates.value = coords
  }

  // Get formatted coordinates for display
  function getFormattedCoordinates() {
    if (!coordinates.value.lat || !coordinates.value.lng) {
      return 'Lat: -- | Lng: --'
    }
    return `Lat: ${coordinates.value.lat.toFixed(6)} | Lng: ${coordinates.value.lng.toFixed(6)}`
  }

  return {
    coordinates,
    setCoordinates,
    getFormattedCoordinates
  }
}

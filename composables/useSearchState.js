import { ref } from 'vue'

// Create shared state
const selectedLocation = ref(null)
const searchQuery = ref('')
const isSearching = ref(false)

export function useSearchState() {
  // Set location from SearchBar
  function setSelectedLocation(location) {
    selectedLocation.value = location
    isSearching.value = true
  }
  
  // Set search query
  function setSearchQuery(query) {
    searchQuery.value = query
    isSearching.value = true
  }
  
  // Mark search as handled
  function markSearchHandled() {
    isSearching.value = false
  }
  
  // Reset the state
  function resetSearch() {
    selectedLocation.value = null
    searchQuery.value = ''
    isSearching.value = false
  }
  
  return {
    selectedLocation,
    searchQuery,
    isSearching,
    setSelectedLocation,
    setSearchQuery,
    markSearchHandled,
    resetSearch
  }
}

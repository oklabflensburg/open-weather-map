<template>
  <div class="relative flex-1">
    <input
      ref="searchInput"
      v-model="searchQuery"
      @input="handleInput"
      @keydown.enter="handleEnter"
      @focus="showDropdown = true"
      type="text"
      :placeholder="mode === 'mobile' ? 'Ort' : 'Ort suchen'"
      :class="mobileMenuOpen ? 'border border-gray-400' : 'border-0'"
      class="w-full px-4 py-2 text-gray-900 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-600"
    />

    <div
      v-if="suggestions.length && showDropdown"
      class="absolute left-0 right-0 top-full mt-2 bg-white rounded-lg shadow-lg z-[1100] max-h-60 overflow-y-auto"
    >
      <div
        v-for="suggestion in suggestions"
        :key="suggestion.osm_id"
        @mousedown.prevent="selectSuggestion(suggestion)"
        class="px-4 py-3 hover:bg-blue-100 cursor-pointer text-gray-800 text-sm"
      >
        {{ suggestion.display_name }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useSearch } from '~/composables/useSearch'
import { useSearchState } from '~/composables/useSearchState'

const props = defineProps({
    mode: {
        type: String,
        default: 'desktop',
        validator: (value) => ['desktop', 'mobile'].includes(value)
    },
    mobileMenuOpen: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['search', 'select-location'])

// State
const searchQuery = ref('')
const suggestions = ref([])
const showDropdown = ref(false)
const searchInput = ref(null)

// Import search functionality
const { getSuggestions } = useSearch()
const searchState = useSearchState()

// Methods
let debounceTimeout

function handleInput() {
  clearTimeout(debounceTimeout)
  
  if (searchQuery.value.length >= 3) {
    debounceTimeout = setTimeout(async () => {
      try {
        suggestions.value = await getSuggestions(searchQuery.value)
        showDropdown.value = true
      } catch (error) {
        console.error('Error getting suggestions:', error)
        suggestions.value = []
      }
    }, 300)
  } else {
    suggestions.value = []
  }
}

function handleEnter() {
  // Update the shared state instead of just emitting
  searchState.setSearchQuery(searchQuery.value)
  
  // Still emit for backward compatibility
  emit('search', searchQuery.value)
  
  searchInput.value?.blur()
  showDropdown.value = false
}

function selectSuggestion(suggestion) {
  searchQuery.value = suggestion.display_name
  
  // Update the shared state with all the polygon information
  searchState.setSelectedLocation({
    display_name: suggestion.display_name,
    lat: suggestion.lat,
    lon: suggestion.lon,
    geojson: suggestion.geojson,
    osm_type: suggestion.osm_type,
    osm_id: suggestion.osm_id
  })
  
  // Still emit for backward compatibility
  emit('select-location', {
    display_name: suggestion.display_name,
    lat: suggestion.lat,
    lon: suggestion.lon,
    // Include other properties for components that directly use this event
    geojson: suggestion.geojson,
    osm_type: suggestion.osm_type,
    osm_id: suggestion.osm_id
  })
  
  showDropdown.value = false
  searchInput.value?.blur()
}

// Close dropdown when clicking outside
if (process.client) {
  const handleClickOutside = (event) => {
    if (searchInput.value && !searchInput.value.contains(event.target)) {
      showDropdown.value = false
    }
  }
  
  onMounted(() => {
    document.addEventListener('click', handleClickOutside)
  })
  
  onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
  })
}
</script>

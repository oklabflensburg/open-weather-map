<template>
  <footer class="bg-gray-800 text-white py-2">
    <div class="container-fluid mx-auto px-4">
      <div class="flex flex-col md:flex-row justify-between items-center">
        <!-- Coordinates display on the left - only show on index page -->
        <div v-if="isIndexPage" class="text-xs text-gray-400 mb-2 md:mb-0">
          {{ mapCoordinates.getFormattedCoordinates() }}
        </div>
        <div v-else class="flex-grow"></div>
        
        <!-- Links on the right -->
        <div class="flex space-x-4">
          <NuxtLink to="/terms" class="text-sm hover:text-blue-300">Impressum</NuxtLink>
          <NuxtLink to="/privacy" class="text-sm hover:text-blue-300">Datenschutz</NuxtLink>
          <NuxtLink to="/contact" class="text-sm hover:text-blue-300">Kontakt</NuxtLink>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup>
// Import the map coordinates composable
import { useMapCoordinates } from '~/composables/useMapCoordinates'

// Get current route to check if we're on the index page
const route = useRoute()

// Check if current page is index
const isIndexPage = computed(() => {
  return route.path === '/' || route.name === 'index'
})

// Access the shared coordinates state
const mapCoordinates = useMapCoordinates()
</script>

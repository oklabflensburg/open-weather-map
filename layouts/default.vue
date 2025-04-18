<template>
  <div class="flex flex-col min-h-screen bg-gray-100">
    <!-- Navigation with event handlers -->
    <TheNavigation 
      @search="handleSearch" 
      @location-select="handleLocationSelect" 
    />
    
    <!-- Main Content -->
    <main class="flex-grow flex flex-col w-full" @mouse-move="handleMapMouseMove">
      <slot />
    </main>

    <!-- Use footer component with mouse position -->
    <TheFooter />
  </div>
</template>

<script setup>
import { computed } from 'vue'

// Get reference to current page component
const route = useRoute()
const page = computed(() => route.meta.pageComponent)

// Forward navigation events to the index page component if on index route
function handleSearch(query) {
  if (route.name === 'index' && page.value?.handleSearch) {
    page.value.handleSearch(query)
  }
}

function handleLocationSelect(location) {
  if (route.name === 'index' && page.value?.handleLocationSelect) {
    page.value.handleLocationSelect(location)
  }
}
</script>

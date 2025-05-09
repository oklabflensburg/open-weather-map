<template>
    <nav class="w-full top-0 z-[1000] md:hidden"
        :class="{'bg-gray-700 shadow-lg': mobileMenuOpen, 'absolute': isIndexPage}"> 
        <div class="flex flex-grow mx-auto p-3 space-x-2 items-center"
            :class="{'justify-end bg-gray-700 border-b border-gray-100': !isIndexPage}">
            <div class="flex-grow text-white text-xl px-2 font-semibold">
                <NuxtLink to="/" class="hover:text-blue-200">Wetterkarte</NuxtLink>
            </div>
            <div class="md:hidden flex items-center justify-center rounded-full bg-white h-10 w-10"
                :class="{'bg-white shadow-lg': mobileMenuOpen}">
                <!-- Mobile Menu Button -->
                <button v-if="!mobileMenuOpen" @click="toggleMenu" class="focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <button v-else @click="toggleMenu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu with Transition -->
        <transition
            enter-active-class="transition-all duration-300 ease-out"
            leave-active-class="transition-all duration-200 ease-in"
            enter-from-class="opacity-0 max-h-0"
            enter-to-class="opacity-100 max-h-64"
            leave-from-class="opacity-100 max-h-64"
            leave-to-class="opacity-0 max-h-0"
        >
            <div v-show="mobileMenuOpen" 
                class="text-gray-100 md:hidden pl-5 pr-3 overflow-hidden"
                :class="{'absolute z-[1000] w-full bg-gray-700': !isIndexPage}">
                <NuxtLink to="/" 
                    :class="{'text-blue-300 font-medium': isActive('/')}" 
                    class="block py-2 hover:text-blue-200"
                    @click="closeMobileMenu">
                    Home
                </NuxtLink>
                <NuxtLink to="/forecast" 
                    :class="{'text-blue-300 font-medium': isActive('/forecast')}" 
                    class="block py-2 hover:text-blue-200"
                    @click="closeMobileMenu">
                    Forecast
                </NuxtLink>
                <NuxtLink to="/radar" 
                    :class="{'text-blue-300 font-medium': isActive('/radar')}" 
                    class="block py-2 hover:text-blue-200"
                    @click="closeMobileMenu">
                    Radar
                </NuxtLink>
                <NuxtLink to="/about" 
                    :class="{'text-blue-300 font-medium': isActive('/about')}" 
                    class="block py-2 hover:text-blue-200"
                    @click="closeMobileMenu">
                    About
                </NuxtLink>
            </div>
        </transition>
    </nav>

    <!-- Desktop Navigation -->
    <nav class="hidden md:block bg-gray-700 text-white shadow-lg">
        <div class="container-fluid mx-auto px-4">
            <div class="flex justify-between items-center py-3">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold">Wetterkarte</h1>
                </div>
                
                <div class="hidden md:flex items-center space-x-6">
                    <div class="flex space-x-4">
                        <NuxtLink to="/" 
                            :class="{'text-blue-300 border-b-2 border-blue-300': isActive('/')}" 
                            class="hover:text-blue-200 pb-1">
                            Home
                        </NuxtLink>
                        <NuxtLink to="/forecast" 
                            :class="{'text-blue-300 border-b-2 border-blue-300': isActive('/forecast')}" 
                            class="hover:text-blue-200 pb-1">
                            Forecast
                        </NuxtLink>
                        <NuxtLink to="/radar" 
                            :class="{'text-blue-300 border-b-2 border-blue-300': isActive('/radar')}" 
                            class="hover:text-blue-200 pb-1">
                            Radar
                        </NuxtLink>
                        <NuxtLink to="/about" 
                            :class="{'text-blue-300 border-b-2 border-blue-300': isActive('/about')}" 
                            class="hover:text-blue-200 pb-1">
                            About
                        </NuxtLink>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</template>

<script setup>
const mobileMenuOpen = ref(false)
const route = useRoute()

// Check if current page is index
const isIndexPage = computed(() => {
  return route.path === '/' || route.name === 'index'
})

// Helper method to check if route is active
function isActive(path) {
  return route.path === path
}

function toggleMenu() {
  mobileMenuOpen.value = !mobileMenuOpen.value
}

function closeMobileMenu() {
  mobileMenuOpen.value = false
}

// Handler functions - emit events to the parent component instead of directly accessing mapRef
function handleSearch(query) {
  emit('search', query)
}

function handleLocationSelect(location) {
  emit('location-select', location)
}

// Define emits
const emit = defineEmits(['search', 'location-select'])
</script>

<style scoped>
/* Ensure transition works properly for height animation */
.transition-all {
  transition-property: all;
}
</style>

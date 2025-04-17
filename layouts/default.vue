<template>
  <div class="flex flex-col min-h-screen bg-gray-100">
    <!-- Use the navigation component -->
    <TheNavigation />
    
    <!-- Main Content -->
    <main class="flex-grow flex flex-col w-full">
      <slot />
    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-2">
      <div class="container-fluid mx-auto px-4">
        <div class="flex flex-col md:flex-row justify-center md:justify-end items-center">
          <div class="flex space-x-4">
            <NuxtLink to="/terms" class="hover:text-blue-300">Impressum</NuxtLink>
            <NuxtLink to="/privacy" class="hover:text-blue-300">Datenschutz</NuxtLink>
            <NuxtLink to="/contact" class="hover:text-blue-300">Kontakt</NuxtLink>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
// Navigation moved to its own component
</script>

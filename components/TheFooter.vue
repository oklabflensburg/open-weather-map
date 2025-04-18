<template>
  <footer class="bg-gray-800 text-white py-2">
    <div class="container-fluid mx-auto px-4">
      <div class="flex flex-col md:flex-row justify-center md:justify-end items-center">
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
// Footer component setup
</script>

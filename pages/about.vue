<template>
  <div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">About Weather Map</h1>
    <p class="mb-4">
      This application provides real-time weather data and forecasts using 
      data from the Open Weather Map API and other meteorological services.
    </p>
    <h2 class="text-2xl font-semibold mt-6 mb-3">Features</h2>
    <ul class="list-disc pl-6 mb-6">
      <li>Interactive weather map with station markers</li>
      <li>Detailed weather forecasts</li>
      <li>Location search</li>
      <li>Responsive design for desktop and mobile</li>
    </ul>
    <h2 class="text-2xl font-semibold mt-6 mb-3">Credits</h2>
    <p class="mb-4">
      Weather data provided by OpenWeatherMap and Deutsche Wetterdienst.
      Built with Nuxt.js, Vue.js, Leaflet, and Tailwind CSS.
    </p>
    <NuxtLink to="/" class="text-blue-600 hover:text-blue-800 underline">
      Return to Map
    </NuxtLink>
  </div>
</template>

<script setup>
// About page setup
</script>

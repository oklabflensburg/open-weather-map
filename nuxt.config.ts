export default defineNuxtConfig({
  // Enable Vue's new features
  devtools: { enabled: true },

  // Modules
  modules: [
    '@nuxtjs/tailwindcss',
  ],

  // Runtime configuration - exposed to the client
  runtimeConfig: {
    // Private keys are only available on the server
    // apiSecret: process.env.NUXT_API_SECRET,

    // Public keys that are available on client and server
    public: {
      // Default values will be overwritten by env vars with prefix NUXT_PUBLIC_
      nominatimBaseUrl: process.env.NUXT_PUBLIC_NOMINATIM_BASE_URL,

      // Company information
      company: {
        name: process.env.NUXT_PUBLIC_COMPANY_NAME,
        street: process.env.NUXT_PUBLIC_COMPANY_STREET,
        zipCode: process.env.NUXT_PUBLIC_COMPANY_ZIP,
        city: process.env.NUXT_PUBLIC_COMPANY_CITY,
        email: process.env.NUXT_PUBLIC_COMPANY_EMAIL,
        phone: process.env.NUXT_PUBLIC_COMPANY_PHONE,
        responsiblePerson: process.env.NUXT_PUBLIC_COMPANY_RESPONSIBLE,
        domain: process.env.NUXT_PUBLIC_COMPANY_DOMAIN
      }
    }
  },

  // App configuration
  app: {
    head: {
      title: 'Wetterkarte',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'Wetterkarte' }
      ],
      link: [
        // Leaflet CSS will be imported in the component itself
      ]
    }
  },

  // Build configuration
  build: {
    transpile: []
  },

  // Handle client-side only libraries
  ssr: true,

  // Improve hydration settings
  vue: {
    compilerOptions: {
      // Treat specific elements with suppressHydrationWarning
      directiveTransforms: {
        // Example if needed
      }
    }
  },

  // For client-side only imports
  vite: {
    optimizeDeps: {
      include: ['leaflet', 'leaflet.markercluster']
    }
  },

  // Disable warnings for specific issues (optional)
  typescript: {
    shim: false
  },

  compatibilityDate: '2025-04-17'
})
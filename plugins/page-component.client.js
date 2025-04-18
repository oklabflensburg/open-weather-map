export default defineNuxtPlugin((nuxtApp) => {
  // Make page components accessible via route meta
  nuxtApp.hook('page:start', (pageComponent) => {
    // Set current page component in the route meta
    if (pageComponent.route?.meta) {
      pageComponent.route.meta.pageComponent = pageComponent.instance
    }
  })
})

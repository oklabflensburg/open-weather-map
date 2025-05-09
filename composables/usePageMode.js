import { ref } from 'vue'

// Create a shared state using a simple module pattern
const pageMode = ref('default')

export function usePageMode() {
  // Function to set the page mode
  function setPageMode(mode) {
    pageMode.value = mode
  }

  // Function to get the current page mode
  function currentPageMode() {
    return pageMode.value
  }

  return {
    pageMode,
    setPageMode,
    currentPageMode
  }
}

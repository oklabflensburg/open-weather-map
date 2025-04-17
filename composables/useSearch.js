export const useSearch = () => {
  /**
   * Get location suggestions from Nominatim API
   */
  async function getSuggestions(query) {
    if (!query || query.trim().length < 3) {
      return []
    }

    try {
      const config = useRuntimeConfig()
      // Use the configured URL with fallback
      const nominatimBaseUrl = config.public.nominatimBaseUrl || 'https://nominatim.openstreetmap.org'
      const url = `${nominatimBaseUrl}/search?format=json&q=${encodeURIComponent(query)}&limit=5`
      
      const response = await fetch(url, {
        headers: {
          'User-Agent': 'WeatherMapApplication/1.0'
        }
      })

      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`)
      }

      const data = await response.json()
      return data.map(item => ({
        display_name: item.display_name,
        lat: item.lat,
        lon: item.lon
      }))
    } catch (error) {
      console.error("Error fetching suggestions:", error)
      return []
    }
  }

  return {
    getSuggestions
  }
}

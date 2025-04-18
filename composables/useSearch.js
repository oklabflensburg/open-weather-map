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
      const url = `${nominatimBaseUrl}/search?format=json&q=${encodeURIComponent(query)}&limit=5&polygon_geojson=1`
      
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
        lon: item.lon,
        geojson: item.geojson || null,
        osm_type: item.osm_type,
        osm_id: item.osm_id
      }))
    } catch (error) {
      console.error("Error fetching suggestions:", error)
      return []
    }
  }

  /**
   * Get detailed location info including polygon
   */
  async function getLocationDetails(osmType, osmId) {
    try {
      const config = useRuntimeConfig()
      const nominatimBaseUrl = config.public.nominatimBaseUrl || 'https://nominatim.openstreetmap.org'
      const url = `${nominatimBaseUrl}/details?format=json&osmtype=${osmType}&osmid=${osmId}&polygon_geojson=1`
      
      const response = await fetch(url, {
        headers: {
          'User-Agent': 'WeatherMapApplication/1.0'
        }
      })

      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`)
      }

      return await response.json()
    } catch (error) {
      console.error("Error fetching location details:", error)
      return null
    }
  }

  return {
    getSuggestions,
    getLocationDetails
  }
}

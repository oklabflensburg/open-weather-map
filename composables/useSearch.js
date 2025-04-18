export const useSearch = () => {
  async function getSuggestions(query) {
    if (!query || query.trim().length < 3) {
      return []
    }

    try {
      const config = useRuntimeConfig()
      const nominatimBaseUrl = config.public.nominatimBaseUrl
      const url = `${nominatimBaseUrl}/search?format=json&q=${encodeURIComponent(query)}&limit=5&polygon_geojson=1`
      
      const response = await fetch(url)

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

  return {
    getSuggestions
  }
}

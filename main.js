// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('menu-toggle').addEventListener('click', function () {
        document.getElementById('mobile-menu').classList.toggle('hidden')
    })
})

// Initialize Leaflet map
const map = L.map('map', {
    zoomControl: false
}).setView([54.5, 10], 9) // Centered more on Flensburg area

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(map)

// Replace simple layer group with marker cluster group
const markersLayer = L.markerClusterGroup({
    disableClusteringAtZoom: 12, // Don't cluster at high zoom levels
    spiderfyOnMaxZoom: false,    // Don't spiderfy clusters on max zoom
    chunkedLoading: true,        // Load markers in chunks for better performance
    maxClusterRadius: 50,        // Maximum radius in pixels for clusters
    iconCreateFunction: function (cluster) {
        const count = cluster.getChildCount()
        let size, className

        // Size based on number of markers
        if (count < 10) {
            size = 'small'
            className = 'bg-blue-500'
        } else if (count < 100) {
            size = 'medium'
            className = 'bg-blue-600'
        } else {
            size = 'large'
            className = 'bg-blue-700'
        }

        // Create a custom cluster icon with Tailwind classes
        return L.divIcon({
            html: '<div class="flex items-center justify-center rounded-full ' + className + ' text-white font-bold" style="width: 100%; height: 100%">' + count + '</div>',
            className: 'marker-cluster marker-cluster-' + size,
            iconSize: [40, 40]
        })
    }
}).addTo(map)

// Track current selected marker
let currentSelectedMarker = null
let selectedMarkerLatLng = null // Store the selected marker coordinates
let isZoomingToMarker = false // Flag to indicate if we're zooming to a marker

// Create default and highlighted marker icons
const defaultIcon = L.icon({
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    shadowSize: [41, 41]
})

const highlightedIcon = L.icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    shadowSize: [41, 41]
})

// Function to search locations using Nominatim
async function searchLocation(query) {
    if (!query || query.trim().length < 3) {
        return // Don't search for very short queries
    }

    try {
        // Show loading indicator if needed
        console.log(`Searching for: ${query}`)

        // Build Nominatim API URL - respecting usage policy
        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`

        // Add required User-Agent header for Nominatim
        const response = await fetch(url, {
            headers: {
                'User-Agent': 'WeatherMapApplication/1.0'
            }
        })

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`)
        }

        const data = await response.json()

        // Process results
        if (data && data.length > 0) {
            const result = data[0]
            const lat = parseFloat(result.lat)
            const lon = parseFloat(result.lon)

            console.log(`Found location: ${result.display_name} at ${lat}, ${lon}`)

            // Create a custom search result marker
            const searchMarker = L.marker([lat, lon], {
                icon: L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                    shadowSize: [41, 41]
                }),
                title: result.display_name
            }).addTo(map)

            // Set view to found location
            map.setView([lat, lon], 12)

            // Remove marker after some time to clean up the map
            setTimeout(() => {
                map.removeLayer(searchMarker)
            }, 8000)

            return true
        } else {
            console.log('No results found')
            return false
        }
    } catch (error) {
        console.error("Error searching location:", error)
        return false
    }
}

// Function to get location suggestions
async function getSuggestions(query) {
    if (!query || query.trim().length < 3) {
        return [] // Don't search for very short queries
    }

    try {
        console.log(`Getting suggestions for: ${query}`)

        // Build Nominatim API URL - respecting usage policy
        const url = `https://nominatim.oklabflensburg.de/search?format=json&q=${encodeURIComponent(query)}&limit=5`

        // Add required User-Agent header for Nominatim
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

// Function to set cursor style for an input
function setCursorStyle(input, style) {
    if (input) input.style.cursor = style
}

// Create suggestion dropdown
function createSuggestionDropdown(inputElement) {
    // Ensure parent has relative positioning for proper dropdown placement
    const parent = inputElement.parentNode
    parent.style.position = 'relative'

    // Create dropdown container with fixed positioning below input
    const dropdown = document.createElement('div')
    dropdown.className = 'absolute left-0 right-0 top-full mt-1 bg-white rounded-md shadow-lg z-[1100] max-h-60 overflow-y-auto hidden'
    parent.appendChild(dropdown)

    // Add explicit text cursor style to input
    inputElement.style.cursor = 'text'

    return dropdown
}

// Display suggestions in dropdown
function showSuggestions(suggestions, dropdown, inputElement) {
    // Always restore text cursor when showing suggestions is complete
    setCursorStyle(inputElement, 'text')

    // Clear previous suggestions
    dropdown.innerHTML = ''

    if (suggestions.length === 0) {
        dropdown.classList.add('hidden')
        return
    }

    // Create suggestion items
    suggestions.forEach(suggestion => {
        const item = document.createElement('div')
        item.className = 'px-4 py-2 hover:bg-blue-100 cursor-pointer text-gray-800 text-sm'
        item.textContent = suggestion.display_name

        // Handle suggestion click
        item.addEventListener('click', () => {
            inputElement.value = suggestion.display_name
            dropdown.classList.add('hidden')

            // Search for the selected location
            map.setView([parseFloat(suggestion.lat), parseFloat(suggestion.lon)], 12)

            // Create marker for the selected location
            const searchMarker = L.marker([parseFloat(suggestion.lat), parseFloat(suggestion.lon)], {
                icon: L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                    shadowSize: [41, 41]
                }),
                title: suggestion.display_name
            }).addTo(map)

            // Remove marker after some time
            setTimeout(() => {
                map.removeLayer(searchMarker)
            }, 8000)

            // Remove focus from input
            inputElement.blur()
        })

        dropdown.appendChild(item)
    })

    dropdown.classList.remove('hidden')
}

// Connect search bar inputs to Nominatim search
document.addEventListener('DOMContentLoaded', function () {
    // Desktop search
    const desktopSearchInput = document.querySelector('.md\\:flex .relative input')
    if (desktopSearchInput) {
        // Ensure parent has proper positioning context
        const desktopSearchContainer = desktopSearchInput.closest('.relative')
        if (desktopSearchContainer) {
            desktopSearchContainer.style.position = 'relative'
        }

        // Explicitly set cursor style
        desktopSearchInput.style.cursor = 'text'

        const desktopDropdown = createSuggestionDropdown(desktopSearchInput)

        // Handle input for suggestions
        let debounceTimeout
        desktopSearchInput.addEventListener('input', function () {
            // Ensure cursor stays as text type when typing
            setCursorStyle(this, 'text')

            clearTimeout(debounceTimeout)
            debounceTimeout = setTimeout(async () => {
                if (this.value.length >= 3) {
                    const suggestions = await getSuggestions(this.value)
                    showSuggestions(suggestions, desktopDropdown, desktopSearchInput)
                } else {
                    desktopDropdown.classList.add('hidden')
                }
            }, 300)
        })

        // Handle Enter key
        desktopSearchInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                searchLocation(this.value)
                this.blur()
                desktopDropdown.classList.add('hidden')
            }
        })

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!desktopSearchInput.contains(e.target) && !desktopDropdown.contains(e.target)) {
                desktopDropdown.classList.add('hidden')
            }
        })
    }

    // Mobile search
    const mobileSearchInput = document.querySelector('.md\\:hidden input')
    if (mobileSearchInput) {
        // Ensure parent container has proper positioning
        const mobileSearchContainer = mobileSearchInput.parentNode
        mobileSearchContainer.style.position = 'relative'

        // Explicitly set cursor style
        mobileSearchInput.style.cursor = 'text'

        const mobileDropdown = createSuggestionDropdown(mobileSearchInput)

        // Handle input for suggestions
        let mobileDebounceTimeout
        mobileSearchInput.addEventListener('input', function () {
            // Ensure cursor stays as text type when typing
            setCursorStyle(this, 'text')

            clearTimeout(mobileDebounceTimeout)
            mobileDebounceTimeout = setTimeout(async () => {
                if (this.value.length >= 3) {
                    const suggestions = await getSuggestions(this.value)
                    showSuggestions(suggestions, mobileDropdown, mobileSearchInput)
                } else {
                    mobileDropdown.classList.add('hidden')
                }
            }, 300)
        })

        // Handle Enter key
        mobileSearchInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                searchLocation(this.value)
                this.blur()
                mobileDropdown.classList.add('hidden')
            }
        })

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!mobileSearchInput.contains(e.target) && !mobileDropdown.contains(e.target)) {
                mobileDropdown.classList.add('hidden')
            }
        })
    }
})

// Function to fetch markers based on map bounds
async function fetchMarkersByBounds() {
    const bounds = map.getBounds()
    const xmin = bounds.getWest().toFixed(6)
    const ymin = bounds.getSouth().toFixed(6)
    const xmax = bounds.getEast().toFixed(6)
    const ymax = bounds.getNorth().toFixed(6)

    const url = `https://api.oklabflensburg.de/climate/v1/mosmix/bounds?xmin=${xmin}&ymin=${ymin}&xmax=${xmax}&ymax=${ymax}`

    try {
        console.log(`Fetching markers from: ${url}`)
        const response = await fetch(url)
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`)
        }
        const data = await response.json()
        return data
    } catch (error) {
        console.error("Error fetching markers:", error)
        return []
    }
}

// Function to fetch forecast data for a specific station
async function fetchForecastData(stationId) {
    try {
        const url = `https://api.oklabflensburg.de/climate/v1/mosmix/forecast/${stationId}`
        console.log(`Fetching forecast data from: ${url}`)

        const response = await fetch(url)
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`)
        }

        return await response.json()
    } catch (error) {
        console.error(`Error fetching forecast data for station ${stationId}:`, error)
        return null
    }
}

// Function to format forecast data into HTML
function formatForecastData(forecastData) {
    if (!forecastData || !forecastData.timeSteps || forecastData.timeSteps.length === 0) {
        return '<p>Keine Vorhersagedaten verfügbar</p>'
    }

    let html = '<div class="forecast-container">'

    // Extract station data
    const stationData = forecastData.station || {}

    // Convert the parameter arrays into forecast objects
    const forecasts = []
    const timeSteps = forecastData.timeSteps || []

    // Get data arrays from station data
    const tempData = stationData.TTT || [] // Temperature in Kelvin
    const windData = stationData.FF || []  // Wind speed in m/s
    const pressureData = stationData.PPPP || [] // Pressure in Pa
    const precipData = stationData.RR1c || [] // Precipitation in mm
    const cloudData = stationData.Neff || [] // Cloud cover in %
    const windDirData = stationData.DD || [] // Wind direction in degrees
    const sunData = stationData.SunD1 || [] // Sunshine duration in minutes

    // Get only the next 24 hours (24 entries), limit to 8 entries for display
    const displayCount = 8
    const limit = Math.min(displayCount, timeSteps.length)

    // Add forecast summary information first
    html += '<div class="mb-4 p-3 bg-blue-50 rounded-md">'
    html += '<h4 class="font-semibold">Zusammenfassung</h4>'

    // Calculate averages and extract relevant info for the summary
    const avgTemp = calculateAverage(tempData.slice(0, 24)).toFixed(1)
    const maxTemp = Math.max(...tempData.slice(0, 24)).toFixed(1)
    const minTemp = Math.min(...tempData.slice(0, 24)).toFixed(1)
    const totalPrecip = calculateSum(precipData.slice(0, 24)).toFixed(1)

    html += `<p>Ø Temperatur nächste 24h: ${(avgTemp - 273.15).toFixed(1)}°C</p>`
    html += `<p>Maximum: ${(maxTemp - 273.15).toFixed(1)}°C / Minimum: ${(minTemp - 273.15).toFixed(1)}°C</p>`
    html += `<p>Niederschlag nächste 24h: ${totalPrecip} mm</p>`
    html += '</div>'

    // Create a table for the forecast data
    html += '<div class="overflow-x-auto"><table class="min-w-full text-sm">'
    html += '<thead><tr class="border-b">'
    html += '<th class="text-left py-2 px-3">Zeit</th>'
    html += '<th class="text-left py-2 px-3">Temp</th>'
    html += '<th class="text-left py-2 px-4">Wind</th>'
    html += '<th class="text-left py-2 px-3">Richtung</th>'
    html += '<th class="text-left py-2 px-3">Luftdruck</th>'
    html += '<th class="text-left py-2 px-3">Niederschlag</th>'
    html += '<th class="text-left py-2 px-3">Wolken</th>'
    html += '<th class="text-left py-2 px-3">Sonne</th>'
    html += '</tr></thead><tbody>'

    // Group data by date
    const dateGroups = {}
    for (let i = 0;i < limit;i++) {
        const timestamp = timeSteps[i]
        const date = new Date(timestamp)

        // Format date for grouping and display - use German locale
        const dateKey = date.toISOString().split('T')[0] // YYYY-MM-DD format for grouping
        const formattedDate = date.toLocaleDateString('de-DE')
        const formattedTime = date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })

        // Get weather parameters
        const tempKelvin = tempData[i]
        const tempCelsius = tempKelvin ? (tempKelvin - 273.15).toFixed(1) : null
        const windSpeed = windData[i] ? windData[i].toFixed(1) : null
        const pressure = pressureData[i] ? (pressureData[i] / 100).toFixed(0) : null
        const precip = precipData[i]
        const clouds = cloudData[i]
        const windDirection = windDirData[i]
        const windDirectionText = getWindDirection(windDirection)
        const windDirectionIcon = getWindDirectionIcon(windDirection)

        // Format sunshine duration (convert minutes to hours and minutes)
        const sunMinutes = sunData[i]
        const sunDuration = formatSunDuration(sunMinutes)

        // Create or add to date group
        if (!dateGroups[dateKey]) {
            dateGroups[dateKey] = {
                displayDate: formattedDate,
                entries: []
            }
        }

        // Add forecast entry to this date group
        dateGroups[dateKey].entries.push({
            time: formattedTime,
            temp: tempCelsius !== null ? tempCelsius + '°C' : 'N/V',
            wind: windSpeed !== null ? windSpeed + ' m/s' : 'N/V',
            direction: windDirectionText || 'N/V',
            directionIcon: windDirectionIcon,
            pressure: pressure !== null ? pressure + ' hPa' : 'N/V',
            precip: precip !== undefined ? precip + ' mm' : 'N/V',
            clouds: clouds !== undefined ? clouds + '%' : 'N/V',
            sun: sunDuration
        })
    }

    // Create table with date headers
    for (const dateKey in dateGroups) {
        const group = dateGroups[dateKey]

        // Date header row
        html += `<tr class="bg-gray-100">
            <th colspan="8" class="text-left py-2 px-1 font-medium">${group.displayDate}</th>
        </tr>`

        // Forecast entries for this date
        group.entries.forEach(entry => {
            html += '<tr class="border-b hover:bg-gray-50">'
            html += `<td class="py-2">${entry.time}</td>`
            html += `<td class="py-2">${entry.temp}</td>`
            html += `<td class="py-2">${entry.wind}</td>`
            html += `<td class="py-2">${entry.directionIcon} ${entry.direction}</td>`
            html += `<td class="py-2">${entry.pressure}</td>`
            html += `<td class="py-2">${entry.precip}</td>`
            html += `<td class="py-2">${entry.clouds}</td>`
            html += `<td class="py-2">${entry.sun}</td>`
            html += '</tr>'
        })
    }

    html += '</tbody></table></div>'
    html += '</div>'

    return html
}

// Helper function to calculate average of an array
function calculateAverage(arr) {
    if (!arr || arr.length === 0) return 0
    return arr.reduce((sum, val) => sum + val, 0) / arr.length
}

// Helper function to calculate sum of an array
function calculateSum(arr) {
    if (!arr || arr.length === 0) return 0
    return arr.reduce((sum, val) => sum + val, 0)
}

// Helper function to convert degrees to compass direction in German
function getWindDirection(degrees) {
    if (degrees === undefined) return null

    // German wind directions
    const directions = ['N', 'NNO', 'NO', 'ONO', 'O', 'OSO', 'SO', 'SSO',
        'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW']
    const index = Math.round(degrees / 22.5) % 16
    return directions[index]
}

// Helper function to get wind direction icon
function getWindDirectionIcon(degrees) {
    if (degrees === undefined) return ''

    // Map wind direction to an arrow symbol based on where the wind is coming FROM
    // Note: Meteorological convention is to report wind direction as the direction FROM which it originates
    const arrowDirection = (degrees + 180) % 360 // Convert to where the arrow points TO

    // Map the direction to one of 8 arrows
    const arrowIndex = Math.round(arrowDirection / 45) % 8
    const arrows = ['↓', '↙', '←', '↖', '↑', '↗', '→', '↘']

    return `<span class="wind-arrow" aria-hidden="true">${arrows[arrowIndex]}</span>`
}

// Helper function to format sun duration in German
function formatSunDuration(minutes) {
    // Handle all invalid cases: undefined, null, NaN, 0, or negative values
    if (minutes === undefined || minutes === null || isNaN(minutes) || minutes <= 0) {
        return 'Keine'
    }

    return Math.round(parseFloat(minutes) / 60) + ' Min'
}

// Function to update markers on the map
async function updateMarkers() {
    try {
        // Remember selected marker coordinates
        const prevSelectedLatLng = selectedMarkerLatLng

        // Show loading indicator or message here if needed

        // Fetch markers within current bounds
        const markerData = await fetchMarkersByBounds()

        // Clear existing markers
        markersLayer.clearLayers()
        if (currentSelectedMarker) {
            currentSelectedMarker = null
        }

        // Process and add new markers
        if (markerData && markerData.features) {
            markerData.features.forEach(feature => {
                if (feature.geometry && feature.geometry.coordinates) {
                    const longitude = feature.geometry.coordinates[0]
                    const latitude = feature.geometry.coordinates[1]

                    // Extract properties for marker info
                    const properties = feature.properties || {}
                    const title = properties.station_name || "Wetterstation"
                    const stationId = feature.id || ''
                    let content = `Stations-ID: ${stationId || 'N/V'}`

                    // Add weather data if available
                    if (properties.temperature) {
                        content += `<br>Temperatur: ${properties.temperature}°C`
                    }
                    if (properties.precipitation) {
                        content += `<br>Niederschlag: ${properties.precipitation} mm`
                    }
                    if (properties.wind_speed) {
                        content += `<br>Windgeschwindigkeit: ${properties.wind_speed} km/h`
                    }

                    // Create and add marker
                    const mapMarker = L.marker([latitude, longitude], {
                        icon: defaultIcon,
                        title: title
                    })

                    // If this is our previously selected marker, highlight it
                    if (prevSelectedLatLng &&
                        Math.abs(prevSelectedLatLng[0] - latitude) < 0.0001 &&
                        Math.abs(prevSelectedLatLng[1] - longitude) < 0.0001) {
                        mapMarker.setIcon(highlightedIcon)
                        currentSelectedMarker = mapMarker
                    }

                    // Set up click handler
                    mapMarker.on('click', async function (e) {
                        // Stop event propagation
                        if (e.originalEvent) {
                            e.originalEvent.stopPropagation()
                        }

                        // Reset previous selected marker if exists
                        if (currentSelectedMarker && currentSelectedMarker !== mapMarker) {
                            currentSelectedMarker.setIcon(defaultIcon)
                        }

                        // Highlight current marker (ensure it's done even if clicked again)
                        mapMarker.setIcon(highlightedIcon)
                        currentSelectedMarker = mapMarker

                        // Store selected marker location to restore highlighting after map updates
                        selectedMarkerLatLng = [latitude, longitude]

                        // Set flag to indicate we're zooming to a marker
                        isZoomingToMarker = true

                        // Zoom and center map on the clicked marker
                        map.setView([latitude, longitude], 15, {
                            animate: true,
                            duration: 0.5
                        })

                        // Show initial details with loading indicator
                        showDetails(title, content + '<div class="mt-4 text-center"><div class="inline-block animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-blue-500"></div> Vorhersagedaten werden geladen...</div>')

                        // Fetch forecast data if we have a stationId
                        if (stationId) {
                            const forecastData = await fetchForecastData(stationId)
                            if (forecastData) {
                                // Format the forecast data and update the details panel
                                const forecastHtml = formatForecastData(forecastData)
                                showDetails(title, content + forecastHtml)
                            } else {
                                // Show error message if forecast data couldn't be fetched
                                showDetails(title, content + '<p class="mt-4 text-red-500">Vorhersagedaten konnten nicht geladen werden.</p>')
                            }
                        } else {
                            // No station ID available
                            showDetails(title, content + '<p class="mt-4">Keine Vorhersagedaten für diese Station verfügbar.</p>')
                        }
                    })

                    // Add to marker cluster instead of regular layer
                    markersLayer.addLayer(mapMarker)
                }
            })
        }

        // Reset the zooming flag
        isZoomingToMarker = false

        // If no markers were added, you might want to show a message
        if (markersLayer.getLayers().length === 0) {
            console.log("Keine Marker im aktuellen Ansichtsbereich gefunden.")
        }
    } catch (error) {
        console.error("Fehler beim Aktualisieren der Marker:", error)
    }
}

// Add debounce function to avoid too many API calls
function debounce(func, wait) {
    let timeout
    return function (...args) {
        const later = () => {
            clearTimeout(timeout)
            func(...args)
        }
        clearTimeout(timeout)
        timeout = setTimeout(later, wait)
    }
}

// Add event listener to map for when bounds change - modified to avoid refreshing during zooms to markers
map.on('moveend', function () {
    // Only update markers if we're not zooming to a specific marker
    if (!isZoomingToMarker) {
        debounce(updateMarkers, 300)()
    } else {
        // Reset the flag after a short delay to ensure it doesn't get stuck
        setTimeout(() => {
            isZoomingToMarker = false
        }, 500)
    }
})

// Initial load of markers
updateMarkers()

// Bottom sheet functionality
const detailsPanel = document.getElementById('details-panel')
const dragHandle = document.getElementById('drag-handle')
const mainContent = document.getElementById('main-content')
let startY, startTranslate, currentTranslate = 0

// Direct click handler on the map container
document.addEventListener('DOMContentLoaded', function () {
    const mapContainer = document.getElementById('map')

    mapContainer.addEventListener('click', function (e) {
        // Check if we clicked directly on the map (not on a marker)
        if (e.target.classList.contains('leaflet-tile') ||
            e.target.classList.contains('leaflet-container') ||
            e.target.id === 'map') {

            // Force hide the panel
            if (detailsPanel.classList.contains('visible')) {
                hideDetails()

                // Force apply transform immediately
                if (window.innerWidth >= 768) {
                    detailsPanel.style.transform = 'translateX(-100%)'
                } else {
                    detailsPanel.style.transform = 'translateY(100%)'
                }
            }
        }
    })
})

function showDetails(title, content) {
    document.getElementById('marker-title').textContent = title
    document.getElementById('marker-content').innerHTML = content // Changed from textContent to innerHTML to support HTML content

    // Reset transform state
    currentTranslate = 0

    // First remove all transforms to start fresh
    detailsPanel.style.removeProperty('transform')

    // Apply correct transform based on viewport size
    if (window.innerWidth >= 768) {
        // For desktop (sidebar)
        detailsPanel.classList.remove('translate-y-full', 'translate-y-0')
        detailsPanel.classList.remove('-translate-x-full')
        detailsPanel.classList.add('translate-x-0')
        // Force the transform to be applied immediately
        detailsPanel.style.transform = 'translateX(0)'
        // Don't adjust the container width as it's now full width
    } else {
        detailsPanel.classList.remove('-translate-x-full', 'translate-x-0')
        detailsPanel.classList.remove('translate-y-full')
        detailsPanel.classList.add('translate-y-0')
        // Force the transform to be applied immediately
        detailsPanel.style.transform = 'translateY(0)'
    }

    // Ensure visibility
    detailsPanel.classList.add('visible')

    // Force reflow to ensure transition applies correctly
    void detailsPanel.offsetWidth
}

function hideDetails() {
    // Reset currently selected marker if exists
    if (currentSelectedMarker) {
        currentSelectedMarker.setIcon(defaultIcon)
        currentSelectedMarker = null
    }

    if (window.innerWidth >= 768) {
        detailsPanel.classList.add('-translate-x-full')
        detailsPanel.classList.remove('translate-x-0')
        // Don't adjust container as it's now full width
    } else {
        detailsPanel.classList.add('translate-y-full')
        detailsPanel.classList.remove('translate-y-0')
        currentTranslate = 100
    }

    detailsPanel.classList.remove('visible')
}

// Swipe functionality for mobile
document.addEventListener('DOMContentLoaded', function () {
    dragHandle.addEventListener('mousedown', dragStart)
    dragHandle.addEventListener('touchstart', dragStart, { passive: true })
    window.addEventListener('mouseup', dragEnd)
    window.addEventListener('touchend', dragEnd)
})

function dragStart(e) {
    if (window.innerWidth >= 768) return // Only for mobile

    startY = e.type === 'touchstart' ? e.touches[0].clientY : e.clientY
    startTranslate = currentTranslate
    window.addEventListener('mousemove', drag)
    window.addEventListener('touchmove', drag, { passive: false })
}

function drag(e) {
    e.preventDefault()
    const currentY = e.type === 'touchmove' ? e.touches[0].clientY : e.clientY
    const diffY = currentY - startY

    if (diffY < 0) {
        // Swiping up - expand to full
        currentTranslate = Math.max(0, startTranslate + diffY)
    } else {
        // Swiping down - collapse
        currentTranslate = Math.min(100, startTranslate + diffY)
    }

    detailsPanel.style.transform = `translateY(${currentTranslate}%)`
}

function dragEnd() {
    window.removeEventListener('mousemove', drag)
    window.removeEventListener('touchmove', drag)

    // Snap to positions
    if (currentTranslate > 50) {
        // Close if pulled down more than 50%
        hideDetails()
    } else {
        // Open fully if pulled down less than 50%
        currentTranslate = 0
        detailsPanel.style.transform = 'translateY(0%)'
    }
}

// Handle window resize for responsive behavior
window.addEventListener('resize', function () {
    if (detailsPanel.classList.contains('visible')) {
        if (window.innerWidth >= 768) {
            detailsPanel.classList.add('translate-x-0')
            detailsPanel.classList.remove('translate-y-0', 'translate-y-full')
            detailsPanel.style.transform = ''
            // Don't adjust container margins/padding
        } else {
            detailsPanel.classList.add('translate-y-0')
            detailsPanel.classList.remove('translate-x-0', '-translate-x-full')
            detailsPanel.style.transform = ''
        }
    } else {
        if (window.innerWidth >= 768) {
            detailsPanel.classList.add('-translate-x-full')
            detailsPanel.classList.remove('translate-y-full')
            detailsPanel.style.transform = ''
        } else {
            detailsPanel.classList.add('translate-y-full')
            detailsPanel.classList.remove('-translate-x-full')
            detailsPanel.style.transform = ''
        }
    }
})

// Ensure all marker icons have proper z-index to be clickable
map.on('layeradd', function (e) {
    if (e.layer instanceof L.Marker) {
        setTimeout(() => {
            const markerIcon = e.layer.getElement()
            if (markerIcon) {
                markerIcon.style.zIndex = "1001"
            }
        }, 100)
    }
})

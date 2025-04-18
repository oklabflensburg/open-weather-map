export const useWeatherData = () => {
    const config = useRuntimeConfig()

    // Get API base URL from config
    const apiBaseUrl = config.public.apiBaseUrl

    /**
     * Fetch forecast data for a specific station
     */
    async function fetchForecastData(stationId) {
        try {
            const url = `${apiBaseUrl}/climate/v1/mosmix/forecast/${stationId}`
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

    /**
     * Format forecast data into HTML
     */
    function formatForecastData(forecastData) {
        if (!forecastData || !forecastData.timeSteps || forecastData.timeSteps.length === 0) {
            return '<p>Keine Vorhersagedaten verfügbar</p>'
        }

        let html = '<div class="forecast-container">'

        // Extract station data
        const stationData = forecastData.station || {}
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
        const avgTemp = calculateAverage(tempData.slice(0, 24))
        const maxTemp = Math.max(...tempData.slice(0, 24))
        const minTemp = Math.min(...tempData.slice(0, 24))
        const totalPrecip = calculateSum(precipData.slice(0, 24))

        html += `<p>⌀ Temperatur nächste 24h: ${(avgTemp - 273.15).toFixed(1)}&#8239;°C</p>`
        html += `<p>Maximum: ${(maxTemp - 273.15).toFixed(1)}&#8239;°C / Minimum: ${(minTemp - 273.15).toFixed(1)}&#8239;°C</p>`
        html += `<p>Niederschlag nächste 24h: ${totalPrecip.toFixed(1)} mm</p>`
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

            // Format sunshine duration
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
                temp: tempCelsius !== null ? tempCelsius + '&#8239;°C' : 'N/V',
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

    // Helper functions
    function calculateAverage(arr) {
        if (!arr || arr.length === 0) return 0
        return arr.reduce((sum, val) => sum + val, 0) / arr.length
    }

    function calculateSum(arr) {
        if (!arr || arr.length === 0) return 0
        return arr.reduce((sum, val) => sum + val, 0)
    }

    function getWindDirection(degrees) {
        if (degrees === undefined) return null

        // German wind directions
        const directions = ['N', 'NNO', 'NO', 'ONO', 'O', 'OSO', 'SO', 'SSO',
            'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW']
        const index = Math.round(degrees / 22.5) % 16
        return directions[index]
    }

    function getWindDirectionIcon(degrees) {
        if (degrees === undefined) return ''

        // Map wind direction to an arrow symbol
        const arrowDirection = (degrees + 180) % 360
        const arrowIndex = Math.round(arrowDirection / 45) % 8
        const arrows = ['↓', '↙', '←', '↖', '↑', '↗', '→', '↘']

        return `<span class="wind-arrow" aria-hidden="true">${arrows[arrowIndex]}</span>`
    }

    function formatSunDuration(minutes) {
        if (minutes === undefined || minutes === null || isNaN(minutes) || minutes <= 0) {
            return 'Keine'
        }
        return Math.round(parseFloat(minutes) / 60) + ' Min'
    }

    return {
        fetchForecastData,
        formatForecastData
    }
}

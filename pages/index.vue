<template>
  <div class="w-full h-full flex-grow flex flex-col relative">
    <LazyGenericMap
      ref="mapRef"
      v-model:selectedLayer="selectedBaseLayer"
      v-model:zoom="mapZoom"
      :center="mapCenter"
      @update:center="updateCenter"
      @map-ready="handleMapReady"
    />
  </div>
</template>

<script setup>
import { useMapCoordinates } from '@/composables/useMapCoordinates'
import { useLocalStorage } from '@vueuse/core';
import { ref, computed, onMounted, shallowRef, nextTick } from 'vue';


// Use shallowRef for non-reactive objects to avoid recursion
const currentSelectedMarker = shallowRef(null);
const selectedMarkerLatLng = ref(null);
const map = shallowRef(null);
const markersSource = shallowRef(null);
const maplibregl = shallowRef(null);

// Prevent update loops
let isUpdatingCenter = false;
let isProcessingMarkers = false;
let updatePending = false;

// Get runtime config for API URLs
const config = useRuntimeConfig();
const weatherApiBaseUrl = computed(() => config.public.weatherApiBaseUrl);

// Reference to the GenericMap component
const mapRef = ref(null);

// Initialize the map coordinates composable
const { setCoordinates } = useMapCoordinates();

// Use localStorage to persist the selected layer across refreshes
const selectedBaseLayer = useLocalStorage('index-page-base-layer', 'OpenStreetMap');

// Use localStorage to persist zoom level across refreshes
const mapZoom = useLocalStorage('index-page-zoom', 13);

// Store the map center position (optional but useful alongside zoom)
const mapCenter = useLocalStorage('index-page-center', [51.505, -0.09]);

// Safe center update to prevent recursion
function updateCenter(newCenter) {
  if (isUpdatingCenter) return;
  isUpdatingCenter = true;
  nextTick(() => {
    mapCenter.value = newCenter;
    isUpdatingCenter = false;
  });
}

function resetSelectedMarker() {
  if (!currentSelectedMarker.value || !map.value) return;
  
  try {
    // Reset the color of the selected marker by updating its feature state
    const marker = currentSelectedMarker.value;
    if (marker.id && map.value) {
      map.value.setFeatureState(
        { source: 'markers', id: marker.id },
        { selected: false }
      );
    }
  } catch (error) {
    console.warn("Could not reset marker state:", error);
  }
  
  currentSelectedMarker.value = null;
  selectedMarkerLatLng.value = null;
}

function handleMarkerClick(feature) {
  if (!map.value || !feature.id) return;
  
  // Reset previous marker if exists
  resetSelectedMarker();
  
  // Extract properties from the feature
  const properties = feature.properties || {};
  const title = properties.station_name || "Wetterstation";
  const stationId = feature.id || '';
  const [longitude, latitude] = feature.geometry.coordinates;
  
  // Store references without creating Vue reactivity
  currentSelectedMarker.value = feature;
  selectedMarkerLatLng.value = [latitude, longitude];
  
  // Highlight the selected marker
  map.value.setFeatureState(
    { source: 'markers', id: feature.id },
    { selected: true }
  );
}

// Safely update markers without triggering reactivity loops
async function updateMarkers(mapInstance) { 
  console.log('updateMarkers called', { 
    hasMap: !!mapInstance, 
    hasSource: !!markersSource.value,
    isProcessing: isProcessingMarkers,
    isPending: updatePending
  });
  
  // Prevent concurrent updates and recursion
  if (!mapInstance || !markersSource.value) {
    console.warn('Missing map or source, skipping update');
    return;
  }
  
  if (isProcessingMarkers) {
    console.warn('Already processing markers, setting pending flag');
    updatePending = true;
    return;
  }
  
  try {
    isProcessingMarkers = true;
    updatePending = false;
    
    // Get the current map bounds
    const bounds = mapInstance.getBounds();
    const prevLatLng = selectedMarkerLatLng.value ? [...selectedMarkerLatLng.value] : null;
    
    // Create simple wrapper to avoid reactive dependencies
    const boundsWrapper = {
      getBounds: () => ({
        getWest: () => bounds.getWest(),
        getSouth: () => bounds.getSouth(),
        getEast: () => bounds.getEast(),
        getNorth: () => bounds.getNorth()
      })
    };
    
    // Fetch markers
    const markerFeatures = await fetchMarkersByBounds(boundsWrapper);
    
    // Check if source still exists
    if (!markersSource.value) {
      isProcessingMarkers = false;
      return;
    }
    
    // Process features and add IDs
    const processedFeatures = markerFeatures.map(feature => {
      // Create a plain object copy
      const plainFeature = JSON.parse(JSON.stringify(feature));
      if (!plainFeature.id) {
        plainFeature.id = `marker-${Math.random().toString(36).substr(2, 9)}`;
      }
      return plainFeature;
    });
    
    // Create GeoJSON data
    const geojsonData = {
      type: 'FeatureCollection',
      features: processedFeatures
    };
    
    // Update the source data
    markersSource.value.setData(geojsonData);
    
    // Check if we need to re-highlight the previously selected marker
    if (prevLatLng && currentSelectedMarker.value) {
      // Find the marker in the new data
      const selectedFeature = processedFeatures.find(feature => {
        if (feature.geometry?.coordinates) {
          const [longitude, latitude] = feature.geometry.coordinates;
          return (
            Math.abs(prevLatLng[0] - latitude) < 0.0001 &&
            Math.abs(prevLatLng[1] - longitude) < 0.0001
          );
        }
        return false;
      });
      
      if (selectedFeature) {
        // Update the reference without triggering reactivity
        currentSelectedMarker.value = selectedFeature;
        
        // Set the feature state
        mapInstance.setFeatureState(
          { source: 'markers', id: selectedFeature.id },
          { selected: true }
        );
      } else {
        // If not found, reset
        resetSelectedMarker();
      }
    }
  } catch (error) {
    console.error("Error updating markers:", error);
  } finally {
    // Always reset processing flag and check for pending updates
    isProcessingMarkers = false;
    
    // Process any pending updates
    if (updatePending && mapInstance) {
      console.log('Processing pending marker update');
      setTimeout(() => updateMarkers(mapInstance), 100);
    }
  }
}

async function fetchMarkersByBounds(mapOrBounds) {
  const bounds = mapOrBounds.getBounds();
  const url = `${weatherApiBaseUrl.value}/climate/v1/mosmix/bounds?xmin=${bounds.getWest().toFixed(6)}&ymin=${bounds.getSouth().toFixed(6)}&xmax=${bounds.getEast().toFixed(6)}&ymax=${bounds.getNorth().toFixed(6)}`;
  
  try {
    const response = await fetch(url);
    if (!response.ok) {
      // For development, return mock data
      if (process.env.NODE_ENV === 'development') {
        console.warn(`Using mock data (${response.status} from API)`);
        return getMockFeatures(bounds);
      }
      throw new Error(`HTTP error! Status: ${response.status}`);
    }
    const data = await response.json();
    return data.features || [];
  } catch (error) {
    console.error("Error fetching markers:", error);
    // Return mock data in development
    if (process.env.NODE_ENV === 'development') {
      return getMockFeatures(bounds);
    }
    return [];
  }
}

// Generate mock data for development
function getMockFeatures(bounds) {
  const features = [];
  const count = 20;
  
  for (let i = 0; i < count; i++) {
    const lng = bounds.getWest() + (bounds.getEast() - bounds.getWest()) * Math.random();
    const lat = bounds.getSouth() + (bounds.getNorth() - bounds.getSouth()) * Math.random();
    
    features.push({
      type: "Feature",
      id: `mock-${i}`,
      geometry: {
        type: "Point",
        coordinates: [lng, lat]
      },
      properties: {
        station_name: `Station ${i}`,
        temperature: Math.round(Math.random() * 30)
      }
    });
  }
  
  return features;
}

// Handle map ready event
async function handleMapReady(mapInstance) {
  if (!process.client) return;
  
  // Store map instance
  map.value = mapInstance;
  
  try {
    // Import MapLibre
    const mlgl = await import('maplibre-gl');
    maplibregl.value = mlgl.default || mlgl;
    
    if (mapInstance) {
      console.log("Creating marker icons");
      
      // Create SVG marker icons directly to avoid file loading issues
      const blueMarkerSVG = `
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
          <path fill="#3b82f6" d="M12 0C7.6 0 4 3.6 4 8c0 3.1 4 9.6 7 14.2.4.5 1.2.5 1.6 0 3-4.6 7-11.1 7-14.2 0-4.4-3.6-8-8-8zm0 11.5c-1.9 0-3.5-1.6-3.5-3.5S10.1 4.5 12 4.5s3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5z"/>
        </svg>
      `;
      
      const redMarkerSVG = `
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
          <path fill="#ef4444" d="M12 0C7.6 0 4 3.6 4 8c0 3.1 4 9.6 7 14.2.4.5 1.2.5 1.6 0 3-4.6 7-11.1 7-14.2 0-4.4-3.6-8-8-8zm0 11.5c-1.9 0-3.5-1.6-3.5-3.5S10.1 4.5 12 4.5s3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5z"/>
        </svg>
      `;
      
      // Convert SVGs to data URLs
      const blueSvgUrl = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(blueMarkerSVG.trim());
      const redSvgUrl = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(redMarkerSVG.trim());
      
      // Use promises for cleaner async handling of icon loading
      const loadDefaultIcon = new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
          mapInstance.addImage('marker-default', img);
          console.log('Default marker icon loaded');
          resolve();
        };
        img.onerror = (e) => {
          console.error('Error loading default marker:', e);
          reject(e);
        };
        img.src = blueSvgUrl;
      });
      
      const loadSelectedIcon = new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
          mapInstance.addImage('marker-selected', img);
          console.log('Selected marker icon loaded');
          resolve();
        };
        img.onerror = (e) => {
          console.error('Error loading selected marker:', e);
          reject(e);
        };
        img.src = redSvgUrl;
      });
      
      // Wait for both icons to load, then continue setup
      Promise.all([loadDefaultIcon, loadSelectedIcon])
        .then(() => {
          console.log('All marker icons loaded successfully');
          setupMapSources(mapInstance);
        })
        .catch(error => {
          console.error('Failed to load marker icons, falling back to circles:', error);
          // If icon loading fails, setup with circle markers instead
          setupMapSourcesWithCircles(mapInstance);
        });
    }
  } catch (error) {
    console.error("Error initializing MapLibre GL:", error);
  }
}

// Alternative setup that uses circles instead of icons
function setupMapSourcesWithCircles(mapInstance) {
  console.log('Setting up map with circle markers as fallback');
  
  // Add source for markers
  mapInstance.addSource('markers', {
    type: 'geojson',
    data: {
      type: 'FeatureCollection',
      features: []
    },
    cluster: true,
    clusterMaxZoom: 12,
    clusterRadius: 50
  });
  
  // Store source reference
  markersSource.value = mapInstance.getSource('markers');
  
  // Add cluster layer
  mapInstance.addLayer({
    id: 'clusters',
    type: 'circle',
    source: 'markers',
    filter: ['has', 'point_count'],
    paint: {
      'circle-color': [
        'step',
        ['get', 'point_count'],
        '#51bbd6',  // color for 1-9 points
        10,
        '#f1f075',  // color for 10-49 points
        50,
        '#f28cb1'   // color for 50+ points
      ],
      'circle-radius': [
        'step',
        ['get', 'point_count'],
        20,  // radius for 1-9 points
        10,
        30,  // radius for 10-49 points
        50,
        40   // radius for 50+ points
      ]
    }
  });

  // Add cluster count layer with fixed font
  mapInstance.addLayer({
    id: 'cluster-count',
    type: 'symbol',
    source: 'markers',
    filter: ['has', 'point_count'],
    layout: {
      'text-field': '{point_count_abbreviated}',
      'text-font': ['superfont'],
      'text-size': 12
    },
    paint: {
      'text-color': '#ffffff'
    }
  });

  // Use circle markers instead of icons
  mapInstance.addLayer({
    id: 'unclustered-point',
    type: 'circle',
    source: 'markers',
    filter: ['!', ['has', 'point_count']],
    paint: {
      'circle-color': [
        'case',
        ['boolean', ['feature-state', 'selected'], false],
        '#ef4444',  // Selected color (red)
        '#3b82f6'   // Default color (blue)
      ],
      'circle-radius': [
        'case',
        ['boolean', ['feature-state', 'selected'], false],
        10,  // Selected size
        7    // Default size
      ],
      'circle-stroke-width': 2,
      'circle-stroke-color': '#ffffff'
    }
  });

  // After all setup is complete, load initial markers
  if (mapInstance.loaded()) {
    console.log('Map already loaded, calling updateMarkers immediately');
    updateMarkers(mapInstance);
  } else {
    console.log('Waiting for map load to call updateMarkers');
    mapInstance.once('load', () => {
      console.log('Map load event fired, calling updateMarkers');
      updateMarkers(mapInstance);
    });
  }
  
  // Setup event handlers
  setupMapEventHandlers(mapInstance);
}

// New function to setup map sources and layers after images are loaded
function setupMapSources(mapInstance) {
  console.log('Setting up map with icon markers');
  
  // Add source for markers
  mapInstance.addSource('markers', {
    type: 'geojson',
    data: {
      type: 'FeatureCollection',
      features: []
    },
    cluster: true,
    clusterMaxZoom: 12,
    clusterRadius: 50
  });
  
  // Store source reference
  markersSource.value = mapInstance.getSource('markers');
  
  // Add cluster layer
  mapInstance.addLayer({
    id: 'clusters',
    type: 'circle',
    source: 'markers',
    filter: ['has', 'point_count'],
    paint: {
      'circle-color': [
        'step',
        ['get', 'point_count'],
        '#51bbd6',  // color for 1-9 points
        10,
        '#f1f075',  // color for 10-49 points
        50,
        '#f28cb1'   // color for 50+ points
      ],
      'circle-radius': [
        'step',
        ['get', 'point_count'],
        20,  // radius for 1-9 points
        10,
        30,  // radius for 10-49 points
        50,
        40   // radius for 50+ points
      ]
    }
  });

  // Add cluster count layer with fixed font
  mapInstance.addLayer({
    id: 'cluster-count',
    type: 'symbol',
    source: 'markers',
    filter: ['has', 'point_count'],
    layout: {
      'text-field': '{point_count_abbreviated}',
      'text-font': ['superfont'],
      'text-size': 12
    },
    paint: {
      'text-color': '#ffffff'
    }
  });

  // Replace circle markers with symbol markers (icon-based)
  mapInstance.addLayer({
    id: 'unclustered-point',
    type: 'symbol',
    source: 'markers',
    filter: ['!', ['has', 'point_count']],
    layout: {
      'icon-image': [
        'case',
        ['boolean', ['feature-state', 'selected'], false],
        'marker-selected',  // Selected marker image
        'marker-default'    // Default marker image
      ],
      'icon-size': 0.8,
      'icon-anchor': 'bottom',
      'icon-allow-overlap': true
    }
  });
  
  // Click handler for clusters
  // ...existing code...

  // After all setup is complete, load initial markers
  if (mapInstance.loaded()) {
    console.log('Map already loaded, calling updateMarkers immediately');
    updateMarkers(mapInstance);
  } else {
    console.log('Waiting for map load to call updateMarkers');
    mapInstance.once('load', () => {
      console.log('Map load event fired, calling updateMarkers');
      updateMarkers(mapInstance);
    });
  }
  
  // Backup timer in case other methods fail
  setTimeout(() => {
    console.log('Backup timer: checking if markers need loading');
    if (!isProcessingMarkers) {
      console.log('Loading markers via backup timer');
      updateMarkers(mapInstance);
    }
  }, 1000);
  
  // Setup event handlers
  setupMapEventHandlers(mapInstance);
}

// New function to setup all map event handlers
function setupMapEventHandlers(mapInstance) {
  // Click handler for clusters
  mapInstance.on('click', 'clusters', (e) => {
    const features = mapInstance.queryRenderedFeatures(e.point, {
      layers: ['clusters']
    });
    
    if (features.length > 0 && features[0].properties.cluster_id) {
      const clusterId = features[0].properties.cluster_id;
      const clickedPoint = features[0].geometry.coordinates;
      
      // Visual feedback - flash effect on clicked cluster
      const clusterElement = e.originalEvent.target;
      const originalFill = clusterElement.style.fill || '';
      
      // Apply highlight
      clusterElement.style.fill = '#ff9900';
      
      markersSource.value.getClusterExpansionZoom(
        clusterId,
        (err, zoom) => {
          if (err) {
            console.error("Error expanding cluster:", err);
            // Restore original style even on error
            clusterElement.style.fill = originalFill;
            return;
          }
          
          // Smooth animation to the expanded cluster
          mapInstance.flyTo({
            center: clickedPoint,
            zoom: zoom,
            speed: 0.5, // Slower speed for smoother animation
            curve: 1,   // Default ease function
            essential: true // This animation is considered essential
          });
          
          // Restore original style after animation starts
          setTimeout(() => {
            clusterElement.style.fill = originalFill;
          }, 300);
        }
      );
    }
  });

  // Click handler for markers
  mapInstance.on('click', 'unclustered-point', (e) => {
    if (e.features && e.features.length > 0) {
      handleMarkerClick(e.features[0]);
    }
  });
  
  // Cursor handlers
  mapInstance.on('mouseenter', 'clusters', () => {
    mapInstance.getCanvas().style.cursor = 'pointer';
  });
  
  mapInstance.on('mouseleave', 'clusters', () => {
    mapInstance.getCanvas().style.cursor = '';
  });
  
  mapInstance.on('mouseenter', 'unclustered-point', () => {
    mapInstance.getCanvas().style.cursor = 'pointer';
  });
  
  mapInstance.on('mouseleave', 'unclustered-point', () => {
    mapInstance.getCanvas().style.cursor = '';
  });
  
  // Heavily throttled update handler
  let updateTimeout = null;
  mapInstance.on('moveend', () => {
    console.log('Map moveend event triggered');
    
    // Clear any existing timeout
    if (updateTimeout) {
      clearTimeout(updateTimeout);
    }
    
    // Always schedule an update
    updateTimeout = setTimeout(() => {
      updateTimeout = null;
      console.log('Calling updateMarkers from moveend handler');
      updateMarkers(mapInstance);
    }, 300);
  });
}

// Ensure everything is loaded only on client-side
onMounted(() => {
  if (!process.client) return;
});
</script>

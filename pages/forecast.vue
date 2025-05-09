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


// Use localStorage to persist the selected layer across refreshes
const selectedBaseLayer = useLocalStorage('forecast-page-base-layer', 'OpenStreetMap');

// Use localStorage to persist zoom level across refreshes
const mapZoom = useLocalStorage('forecast-page-zoom', 13);

// Store the map center position (optional but useful alongside zoom)
const mapCenter = useLocalStorage('forecast-page-center', [51.505, -0.09]);

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
  // zoom to the marker
  if (map.value && feature.geometry) {
    const [longitude, latitude] = feature.geometry.coordinates;
    map.value.flyTo({
      center: [longitude, latitude],
      zoom: 15,
      speed: 1
    });
  }
  
  // Reset previous marker if exists
  resetSelectedMarker();
  
  // Extract properties from the feature
  const properties = feature.properties || {};
  const title = properties.station_name || "Wetterstation";
  const stationId = feature.id || '';
  const [longitude, latitude] = feature.geometry.coordinates;
  
  // Show details in the panel
  if (mapRef.value) {
    // Create content for the details panel
    const content = `
      <div>
        <p>Station ID: ${stationId}</p>
        <p>Location: ${latitude.toFixed(4)}, ${longitude.toFixed(4)}</p>
        ${properties.temperature ? `<p>Temperature: ${properties.temperature}°C</p>` : ''}
      </div>
    `;

    // Call the exposed method
    mapRef.value.showDetailsPanel(title, content);
  }
  
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

      // Add unclustered points layer
      mapInstance.addLayer({
        id: 'unclustered-point',
        type: 'circle',
        source: 'markers',
        filter: ['!', ['has', 'point_count']],
        paint: {
          'circle-color': [
            'case',
            ['boolean', ['feature-state', 'selected'], false],
            '#ff0000',  // Selected color (red)
            '#3887be'   // Default color (blue)
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
      
      // Click handler for clusters
      mapInstance.on('click', 'clusters', (e) => {
        const features = mapInstance.queryRenderedFeatures(e.point, {
          layers: ['clusters']
        });
        
        if (features.length > 0 && features[0].properties.cluster_id) {
          const clusterId = features[0].properties.cluster_id;
          markersSource.value.getClusterExpansionZoom(
            clusterId,
            (err, zoom) => {
              if (err) return;
              
              mapInstance.easeTo({
                center: features[0].geometry.coordinates,
                zoom: zoom
              });
            }
          );
        }
      });
      
      // Click handler for markers
      mapInstance.on('click', 'unclustered-point', (e) => {
        if (e.features && e.features.length > 0) {
          handleMarkerClick(e.features[0]);
          console.log('Marker clicked:', e.features[0].id);
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
      
      // Initial markers load - more reliable approach
      console.log('Setting up initial markers load');
      
      // Make sure map is fully initialized before loading markers
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
    }
  } catch (error) {
    console.error("Error initializing MapLibre GL:", error);
  }
}

// Ensure everything is loaded only on client-side
onMounted(() => {
  if (!process.client) return;
});
</script>

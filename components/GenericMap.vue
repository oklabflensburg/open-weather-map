<template>
  <div id="map" ref="mapContainer" class="w-full h-full flex-grow"></div>
  <ClientOnly>
    <DetailsPanel ref="detailsRef" :title="markerTitle" :content="markerContent" :is-visible="detailsPanelVisible"
      @close="hideDetailsPanel" />
    <template #fallback>
      <!-- Fallback content for SSR -->
      <div class="hidden">Loading details panel...</div>
    </template>
  </ClientOnly>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, shallowRef, nextTick } from 'vue'
import { useMapCoordinates } from '~/composables/useMapCoordinates'
import { usePageMode } from '~/composables/usePageMode'

// Initialize the map coordinates composable
const { setCoordinates } = useMapCoordinates();
const detailsPanelVisible = ref(false)
const markerTitle = ref('')
const markerContent = ref('')

// Get page mode functions from composable
const { setPageMode, currentPageMode } = usePageMode()

// Props to allow customization of the map
const props = defineProps({
  center: {
    type: Array,
    default: () => [51.505, -0.09] // Default to London coordinates
  },
  zoom: {
    type: Number,
    default: 13
  },
  maxZoom: {
    type: Number,
    default: 19
  },
  minZoom: {
    type: Number,
    default: 3
  },
  baseLayers: {
    type: Array,
    default: () => [
      {
        name: 'OpenStreetMap',
        url: 'https://a.tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        selected: true
      },
      {
        name: 'OpenTopoMap',
        url: 'https://a.tile.opentopomap.org/{z}/{x}/{y}.png',
        attribution: '&copy; <a href="https://opentopomap.org">OpenTopoMap</a> contributors',
        selected: false
      },
      {
        name: 'Esri WorldImagery',
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        attribution: '&copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
        selected: false
      }
    ]
  },
  selectedLayer: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['update:selectedLayer', 'update:zoom', 'update:center', 'map-ready'])

// Create ref for the map container
const mapContainer = ref(null)
const detailsRef = ref(null)

// Use shallowRef to prevent recursive reactivity
const map = shallowRef(null)
const baseLayers = shallowRef({})

// Guards to prevent recursive updates
let updatingZoom = false
let updatingCenter = false
let updatingLayer = false

// Initialize map when component is mounted
onMounted(async () => {
  if (!process.client) return

  try {
    // Dynamically import MapLibre GL JS
    const maplibregl = await import('maplibre-gl')
    await import('maplibre-gl/dist/maplibre-gl.css')

    // Get the initially selected layer
    let initialLayerName = props.selectedLayer
    if (!initialLayerName) {
      const defaultLayer = props.baseLayers.find(layer => layer.selected)
      initialLayerName = defaultLayer ? defaultLayer.name : props.baseLayers[0].name
    }

    // Find initial base layer configuration
    const initialLayer = props.baseLayers.find(layer => layer.name === initialLayerName) || props.baseLayers[0]

    // Convert our tile layer format to MapLibre's raster source style
    function createRasterSource(layerConfig) {
      // Convert URL template from {s} format to {a-c} format if needed
      let url = layerConfig.url
      if (url.includes('{s}')) {
        url = url.replace('{s}', '{a-c}')
      }

      return {
        'type': 'raster',
        'tiles': [url],
        'tileSize': 256,
        'attribution': layerConfig.attribution
      }
    }

    // Create map with initial style
    const mapInstance = new maplibregl.Map({
      container: mapContainer.value,
      style: {
        version: 8,
        sources: {
          [initialLayer.name]: createRasterSource(initialLayer)
        },
        layers: [{
          id: initialLayer.name,
          type: 'raster',
          source: initialLayer.name,
          minzoom: props.minZoom,
          maxzoom: props.maxZoom
        }],
        // Use a more reliable glyphs URL
        glyphs: "http://localhost:7000/{fontstack}/{range}.pbf"
      },
      center: [props.center[1], props.center[0]], // MapLibre uses [lng, lat] format
      zoom: props.zoom,
      maxZoom: props.maxZoom,
      minZoom: props.minZoom
    })

    // Store in shallowRef to avoid deep reactivity
    map.value = mapInstance
    setPageMode('map')

    // Wait for map to load before setting up controls and events
    mapInstance.on('load', () => {
      // Create sources and layers for all base layers and store them
      const baseLayersObj = {}
      props.baseLayers.forEach(layerConfig => {
        if (layerConfig.name !== initialLayer.name) {
          // Add the source for this layer
          mapInstance.addSource(layerConfig.name, createRasterSource(layerConfig))

          // Add the layer but set visibility to none if not selected
          mapInstance.addLayer({
            id: layerConfig.name,
            type: 'raster',
            source: layerConfig.name,
            layout: {
              visibility: 'none'
            },
            minzoom: props.minZoom,
            maxzoom: props.maxZoom
          })
        }

        // Track the layer
        baseLayersObj[layerConfig.name] = layerConfig
      })

      mapInstance.on('mousemove', (e) => {
        setCoordinates(e.lngLat)
      })

      // Store using shallowRef to avoid deep reactivity
      baseLayers.value = baseLayersObj

      // Add navigation control (zoom in/out)
      const nav = new maplibregl.NavigationControl()
      mapInstance.addControl(nav, 'top-right')

      // Create a custom layer switcher control
      class LayerSwitcherControl {
        onAdd(map) {
          this._map = map
          this._container = document.createElement('div')
          this._container.className = 'maplibregl-ctrl maplibregl-ctrl-group'

          // Create dropdown for layer selection
          const dropdown = document.createElement('select')
          dropdown.className = 'maplibregl-layer-switcher'
          dropdown.style.margin = '10px'
          dropdown.style.padding = '5px'

          // Add options for each base layer
          Object.entries(baseLayersObj).forEach(([name]) => {
            const option = document.createElement('option')
            option.value = name
            option.text = name
            option.selected = name === initialLayerName
            dropdown.appendChild(option)
          })

          // Handle layer change with debounce
          dropdown.addEventListener('change', (e) => {
            if (updatingLayer) return
            updatingLayer = true

            const selectedName = e.target.value

            // Hide all layers
            Object.keys(baseLayersObj).forEach(name => {
              map.setLayoutProperty(name, 'visibility', 'none')
            })

            // Show selected layer
            map.setLayoutProperty(selectedName, 'visibility', 'visible')

            // Emit the layer change after a short delay
            setTimeout(() => {
              emit('update:selectedLayer', selectedName)
              updatingLayer = false
            }, 10)
          })

          this._container.appendChild(dropdown)
          return this._container
        }

        onRemove() {
          this._container.parentNode.removeChild(this._container)
          this._map = undefined
        }
      }

      // Add layer switcher control
      mapInstance.addControl(new LayerSwitcherControl(), 'top-left')

      // Throttled event handlers to prevent recursive updates
      let zoomTimeout = null
      let moveTimeout = null

      // Listen for map view changes with throttling
      mapInstance.on('zoomend', () => {
        if (updatingZoom) return

        clearTimeout(zoomTimeout)
        zoomTimeout = setTimeout(() => {
          emit('update:zoom', mapInstance.getZoom())
        }, 100)
      })

      mapInstance.on('moveend', () => {
        if (updatingCenter) return

        clearTimeout(moveTimeout)
        moveTimeout = setTimeout(() => {
          const center = mapInstance.getCenter()
          // Convert [lng, lat] back to [lat, lng] for consistency with props
          emit('update:center', [center.lat, center.lng])
        }, 100)
      })

      // Emit the map-ready event with the map instance
      nextTick(() => {
        emit('map-ready', mapInstance)
      })
    })

    // Watch for prop changes to sync with the map using safe update methods
    watch(() => props.center, (newCenter) => {
      if (!mapInstance || updatingCenter || !newCenter) return

      updatingCenter = true
      mapInstance.setCenter([newCenter[1], newCenter[0]])

      setTimeout(() => {
        updatingCenter = false
      }, 150)
    }, { deep: true })

    watch(() => props.zoom, (newZoom) => {
      if (!mapInstance || updatingZoom || !newZoom) return

      updatingZoom = true
      mapInstance.setZoom(newZoom)

      setTimeout(() => {
        updatingZoom = false
      }, 150)
    })

    watch(() => props.selectedLayer, (newLayerName) => {
      if (!mapInstance || !mapInstance.loaded() || updatingLayer || !newLayerName || !baseLayers.value[newLayerName]) return

      updatingLayer = true

      // Hide all layers
      Object.keys(baseLayers.value).forEach(name => {
        mapInstance.setLayoutProperty(name, 'visibility', 'none')
      })

      // Show selected layer
      mapInstance.setLayoutProperty(newLayerName, 'visibility', 'visible')

      setTimeout(() => {
        updatingLayer = false
      }, 150)
    })

  } catch (error) {
    console.error("Error initializing MapLibre GL map:", error)
  }
})

function hideDetailsPanel() {
  detailsPanelVisible.value = false
  // Remove reference to undefined mapRef
  // If you need to reset a selected marker, implement it using the map.value instead
}

// function to show the details panel
function showDetailsPanel(title, content) {
  detailsPanelVisible.value = true
  markerTitle.value = title
  markerContent.value = content
}

// Clean up map instance when component is unmounted
onUnmounted(() => {
  if (map.value) {
    map.value.remove()
    map.value = null
  }
})

// Expose methods that need to be called from parent components
defineExpose({
  showDetailsPanel,
  hideDetailsPanel
})
</script>

<style>
.maplibregl-layer-switcher {
  background-color: white;
  border-radius: 4px;
  border: 1px solid rgba(0, 0, 0, 0.1);
}
</style>
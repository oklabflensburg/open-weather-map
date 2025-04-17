<template>
  <div
    id="details-panel"
    ref="panel"
    :class="[
      'fixed bg-white shadow-lg z-[1002] overflow-hidden flex flex-col transition-transform duration-300 ease-in-out',
      { 'invisible': !isVisible },
      isMobile ? mobileClasses : desktopClasses
    ]"
    :style="panelStyle"
  >
    <!-- Drag Handle (Mobile) -->
    <div
      v-if="isMobile"
      id="drag-handle"
      ref="dragHandle"
      @mousedown="dragStart"
      @touchstart.passive="dragStart"
      class="w-full mt-3 cursor-grab active:cursor-grabbing flex justify-center items-center"
    >
      <div class="w-10 h-1 bg-gray-400 rounded-full"></div>
    </div>
    
    <!-- Close Button (Desktop) -->
    <button 
      v-if="!isMobile" 
      @click="$emit('close')"
      class="absolute top-2 right-2 text-gray-500 hover:text-gray-800 z-10"
    >
      ✕
    </button>
    
    <!-- Content Area -->
    <div class="p-4 overflow-y-auto flex-grow">
      <h3 id="marker-title" class="text-lg font-semibold mb-2">{{ title }}</h3>
      <div id="marker-content" v-html="content"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  title: {
    type: String,
    default: 'Location Details'
  },
  content: {
    type: String,
    default: 'Select a marker to view details.'
  },
  isVisible: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close'])

// Refs
const panel = ref(null)
const dragHandle = ref(null)

// Drag state
const startY = ref(0)
const startTranslate = ref(0) 
const currentTranslate = ref(100) // Start hidden (100% down)
const isDragging = ref(false)

// Responsive state
const isMobile = ref(process.client ? window.innerWidth < 768 : true)

// Classes and styles
const mobileClasses = 'bottom-0 left-0 right-0 h-3/4 max-h-[80vh] rounded-t-xl'
const desktopClasses = 'top-0 left-0 bottom-0 w-[350px] max-w-sm border-r border-gray-200'

const panelStyle = computed(() => {
  if (isMobile.value) {
    // Mobile: translate Y
    return { 
      transform: `translateY(${props.isVisible ? currentTranslate.value : 100}%)`
    }
  } else {
    // Desktop: translate X
    return { 
      transform: props.isVisible ? 'translateX(0)' : 'translateX(-100%)' 
    }
  }
})

// Watch for visibility changes
watch(() => props.isVisible, (newValue) => {
  if (newValue && isMobile.value) {
    // Reset translate position when showing on mobile
    currentTranslate.value = 0
    if (panel.value) {
      panel.value.style.transform = 'translateY(0%)'
    }
  }
})

// Drag handlers
function dragStart(e) {
  if (!isMobile.value || !props.isVisible) return
  
  isDragging.value = true
  startY.value = e.type === 'touchstart' ? e.touches[0].clientY : e.clientY
  startTranslate.value = currentTranslate.value
  
  if (panel.value) {
    panel.value.style.transition = 'none'
  }
}

function drag(e) {
  if (!isDragging.value || !isMobile.value) return
  
  // Prevent default to stop page scroll during drag
  if (e.cancelable && e.type === 'touchmove') {
    e.preventDefault()
  }
  
  const currentY = e.type === 'touchmove' ? e.touches[0].clientY : e.clientY
  const diffY = currentY - startY.value
  const panelHeight = panel.value?.offsetHeight || window.innerHeight * 0.75
  
  // Calculate new translate percentage
  const diffPercent = (diffY / panelHeight) * 100
  let newTranslate = startTranslate.value + diffPercent
  
  // Clamp between 0% (fully visible) and 100% (completely hidden)
  currentTranslate.value = Math.max(0, Math.min(100, newTranslate))
  
  // Apply transform
  if (panel.value) {
    panel.value.style.transform = `translateY(${currentTranslate.value}%)`
  }
}

function dragEnd() {
  if (!isDragging.value || !isMobile.value) return
  
  isDragging.value = false
  if (panel.value) {
    panel.value.style.transition = ''
  }
  
  // Close if dragged down past threshold, otherwise snap back open
  if (currentTranslate.value > 40) {
    emit('close')
    currentTranslate.value = 100
  } else {
    currentTranslate.value = 0
    if (panel.value) {
      panel.value.style.transform = 'translateY(0%)'
    }
  }
}

// Handle window resize
function handleResize() {
  isMobile.value = window.innerWidth < 768
  
  // Adjust position based on new screen size
  if (props.isVisible && panel.value) {
    if (isMobile.value) {
      panel.value.style.transform = `translateY(${currentTranslate.value}%)`
    } else {
      panel.value.style.transform = 'translateX(0)'
    }
  }
}

// Lifecycle hooks
onMounted(() => {
  if (process.client) {
    window.addEventListener('mouseup', dragEnd)
    window.addEventListener('touchend', dragEnd)
    window.addEventListener('mousemove', drag)
    window.addEventListener('touchmove', drag, { passive: false })
    window.addEventListener('resize', handleResize)
  }
})

onBeforeUnmount(() => {
  if (process.client) {
    window.removeEventListener('mouseup', dragEnd)
    window.removeEventListener('touchend', dragEnd)
    window.removeEventListener('mousemove', drag)
    window.removeEventListener('touchmove', drag)
    window.removeEventListener('resize', handleResize)
  }
})
</script>

<style scoped>
/* Scrollbar styles */
div.overflow-y-auto {
  scrollbar-width: thin; 
  scrollbar-color: #9ca3af #e5e7eb;
}
div.overflow-y-auto::-webkit-scrollbar {
  width: 6px;
}
div.overflow-y-auto::-webkit-scrollbar-track {
  background: #e5e7eb;
  border-radius: 3px;
}
div.overflow-y-auto::-webkit-scrollbar-thumb {
  background-color: #9ca3af;
  border-radius: 3px;
  border: 1px solid #e5e7eb;
}
</style>

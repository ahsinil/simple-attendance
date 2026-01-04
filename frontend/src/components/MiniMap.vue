<script setup>
import { ref, onMounted, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({
  latitude: { type: [Number, String], required: true },
  longitude: { type: [Number, String], required: true },
  name: { type: String, default: 'Location' },
  height: { type: String, default: '120px' },
  zoom: { type: Number, default: 15 }
})

const mapContainer = ref(null)
let map = null
let marker = null

// Fix for Leaflet marker icon issue
const defaultIcon = L.icon({
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41]
})

onMounted(() => {
  const lat = parseFloat(props.latitude)
  const lng = parseFloat(props.longitude)

  if (isNaN(lat) || isNaN(lng)) return

  map = L.map(mapContainer.value, {
    zoomControl: false,
    attributionControl: false,
    dragging: false,
    scrollWheelZoom: false,
    doubleClickZoom: false,
    touchZoom: false
  }).setView([lat, lng], props.zoom)

  // Use OpenStreetMap tiles (FREE, no API key)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19
  }).addTo(map)

  marker = L.marker([lat, lng], { icon: defaultIcon }).addTo(map)
  marker.bindPopup(props.name)
})

watch(() => [props.latitude, props.longitude], ([newLat, newLng]) => {
  const lat = parseFloat(newLat)
  const lng = parseFloat(newLng)
  if (map && !isNaN(lat) && !isNaN(lng)) {
    map.setView([lat, lng], props.zoom)
    if (marker) {
      marker.setLatLng([lat, lng])
    }
  }
})

function openInGoogleMaps() {
  const lat = parseFloat(props.latitude)
  const lng = parseFloat(props.longitude)
  window.open(`https://www.google.com/maps?q=${lat},${lng}`, '_blank')
}
</script>

<template>
  <div class="relative rounded-lg overflow-hidden border border-gray-200 dark:border-dark-border">
    <div 
      ref="mapContainer" 
      :style="{ height }" 
      class="w-full bg-gray-100 dark:bg-dark-surface"
    ></div>
    <!-- Click overlay to open in Google Maps -->
    <div 
      @click="openInGoogleMaps"
      class="absolute inset-0 cursor-pointer group"
    >
      <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
      <div class="absolute bottom-1 right-1 bg-white/90 dark:bg-dark-surface/90 px-2 py-0.5 rounded text-xs flex items-center gap-1 opacity-70 group-hover:opacity-100 transition-opacity">
        <span class="material-symbols-outlined text-xs">open_in_new</span>
        <span>Open in Maps</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Ensure Leaflet tiles have proper z-index */
:deep(.leaflet-container) {
  z-index: 0;
}
</style>

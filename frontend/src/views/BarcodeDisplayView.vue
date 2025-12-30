<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { barcodeApi, attendanceApi } from '@/services/api'
import QRCode from 'qrcode'

const locations = ref([])
const selectedLocation = ref(null)
const barcode = ref(null)
const loading = ref(true)
const countdown = ref(0)
const qrCodeUrl = ref('')

let refreshInterval = null
let countdownInterval = null

onMounted(async () => {
  await fetchLocations()
  startAutoRefresh()
})

onUnmounted(() => {
  stopAutoRefresh()
})

async function fetchLocations() {
  try {
    const response = await attendanceApi.locations()
    locations.value = response.data.data
    if (locations.value.length > 0 && !selectedLocation.value) {
      selectedLocation.value = locations.value[0]
      await fetchBarcode()
    }
  } catch (error) {
    console.error('Failed to fetch locations:', error)
  }
  loading.value = false
}

async function fetchBarcode() {
  if (!selectedLocation.value) return

  try {
    const response = await barcodeApi.location(selectedLocation.value.id)
    barcode.value = response.data.data
    countdown.value = barcode.value.expires_in_seconds

    // Generate QR code
    if (barcode.value.barcode_data) {
      qrCodeUrl.value = await QRCode.toDataURL(barcode.value.barcode_data, {
        width: 400,
        margin: 2,
        color: {
          dark: '#1a2632',
          light: '#ffffff',
        },
      })
    }
  } catch (error) {
    console.error('Failed to fetch barcode:', error)
  }
}

function startAutoRefresh() {
  // Countdown every second
  countdownInterval = setInterval(() => {
    if (countdown.value > 0) {
      countdown.value--
    }
    if (countdown.value <= 0) {
      fetchBarcode()
    }
  }, 1000)
}

function stopAutoRefresh() {
  if (refreshInterval) clearInterval(refreshInterval)
  if (countdownInterval) clearInterval(countdownInterval)
}

async function selectLocation(location) {
  selectedLocation.value = location
  await fetchBarcode()
}

const countdownFormatted = computed(() => {
  const min = Math.floor(countdown.value / 60)
  const sec = countdown.value % 60
  return `${min}:${sec.toString().padStart(2, '0')}`
})
</script>

<template>
  <div class="min-h-screen bg-dark-bg text-white p-6">
    <!-- Header -->
    <div class="max-w-2xl mx-auto">
      <div class="flex items-center justify-between mb-8">
        <RouterLink to="/" class="flex items-center gap-2 text-gray-400 hover:text-white">
          <span class="material-symbols-outlined">arrow_back</span>
          Back
        </RouterLink>
        <div class="text-right">
          <p class="text-sm text-gray-400">Current Time</p>
          <p class="text-xl font-mono">{{ new Date().toLocaleTimeString() }}</p>
        </div>
      </div>

      <!-- Location Selector -->
      <div class="mb-8">
        <p class="text-sm text-gray-400 mb-2">Select Location:</p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="loc in locations"
            :key="loc.id"
            @click="selectLocation(loc)"
            class="px-4 py-2 rounded-lg transition-colors"
            :class="selectedLocation?.id === loc.id 
              ? 'bg-primary text-white' 
              : 'bg-dark-surface text-gray-400 hover:bg-dark-border'"
          >
            {{ loc.name }}
          </button>
        </div>
      </div>

      <!-- QR Code Display -->
      <div v-if="loading" class="text-center py-20">
        <span class="material-symbols-outlined text-6xl text-gray-600 animate-pulse">qr_code</span>
        <p class="mt-4 text-gray-400">Loading barcode...</p>
      </div>

      <div v-else-if="barcode" class="text-center">
        <div class="bg-white rounded-2xl p-8 inline-block shadow-2xl">
          <img 
            v-if="qrCodeUrl" 
            :src="qrCodeUrl" 
            alt="QR Code" 
            class="w-64 h-64 md:w-80 md:h-80"
          />
          <div v-else class="w-64 h-64 md:w-80 md:h-80 flex items-center justify-center bg-gray-100">
            <span class="material-symbols-outlined text-6xl text-gray-400">qr_code</span>
          </div>
        </div>

        <!-- Location Info -->
        <div class="mt-6">
          <h2 class="text-2xl font-bold">{{ barcode.location_name }}</h2>
          <p class="text-gray-400">{{ barcode.location_code }}</p>
        </div>

        <!-- Countdown -->
        <div class="mt-6 bg-dark-surface rounded-xl p-4 inline-block">
          <p class="text-sm text-gray-400">Refreshes in</p>
          <p class="text-3xl font-mono font-bold text-primary">{{ countdownFormatted }}</p>
        </div>

        <!-- Instructions -->
        <div class="mt-8 text-gray-400 text-sm">
          <p>Scan this QR code with your phone to check in/out</p>
        </div>
      </div>
    </div>
  </div>
</template>

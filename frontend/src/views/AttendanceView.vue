<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useAttendanceStore } from '@/stores/attendance'

const attendanceStore = useAttendanceStore()

const scanning = ref(false)
const scannedCode = ref('')
const gpsLocation = ref(null)
const gpsError = ref('')
const gpsAccuracy = ref(null)
const result = ref(null)

let watchId = null

onMounted(() => {
  attendanceStore.fetchLocations()
  startGpsTracking()
})

onUnmounted(() => {
  stopGpsTracking()
})

function startGpsTracking() {
  if (!navigator.geolocation) {
    gpsError.value = 'Geolocation not supported'
    return
  }

  watchId = navigator.geolocation.watchPosition(
    (position) => {
      gpsLocation.value = {
        lat: position.coords.latitude,
        lng: position.coords.longitude,
      }
      gpsAccuracy.value = position.coords.accuracy
      gpsError.value = ''
    },
    (error) => {
      gpsError.value = error.message
    },
    {
      enableHighAccuracy: true,
      maximumAge: 0,
      timeout: 10000,
    }
  )
}

function stopGpsTracking() {
  if (watchId) {
    navigator.geolocation.clearWatch(watchId)
  }
}

async function handleScan() {
  if (!scannedCode.value) {
    result.value = { success: false, error: 'Please enter barcode data' }
    return
  }

  if (!gpsLocation.value) {
    result.value = { success: false, error: 'GPS location not available' }
    return
  }

  scanning.value = true
  result.value = null

  const response = await attendanceStore.scan({
    barcode: scannedCode.value,
    gps_lat: gpsLocation.value.lat,
    gps_lng: gpsLocation.value.lng,
    gps_accuracy: gpsAccuracy.value,
  })

  result.value = response.success 
    ? { success: true, message: response.data.message, attendance: response.data.attendance }
    : { success: false, error: response.error }

  scanning.value = false
  scannedCode.value = ''
}

// Simulate barcode scan for demo
function simulateScan() {
  // In production, this would be replaced with actual barcode scanning
  // For now, we'll show how to integrate with a barcode scanner
  scannedCode.value = 'demo-barcode-' + Date.now()
}
</script>

<template>
  <div class="max-w-lg mx-auto space-y-6">
    <!-- GPS Status -->
    <div class="card p-4">
      <div class="flex items-center gap-3">
        <div 
          class="w-10 h-10 rounded-full flex items-center justify-center"
          :class="gpsLocation ? 'bg-green-100 dark:bg-green-900/20' : 'bg-amber-100 dark:bg-amber-900/20'"
        >
          <span 
            class="material-symbols-outlined"
            :class="gpsLocation ? 'text-green-500' : 'text-amber-500'"
          >
            {{ gpsLocation ? 'location_on' : 'location_searching' }}
          </span>
        </div>
        <div>
          <p class="font-medium text-gray-900 dark:text-white">
            {{ gpsLocation ? 'GPS Ready' : 'Acquiring GPS...' }}
          </p>
          <p v-if="gpsLocation" class="text-xs text-gray-500">
            Accuracy: {{ Math.round(gpsAccuracy) }}m
          </p>
          <p v-if="gpsError" class="text-xs text-red-500">{{ gpsError }}</p>
        </div>
      </div>
    </div>

    <!-- Scan Area -->
    <div class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 text-center">
        Scan Barcode
      </h3>

      <div class="aspect-square bg-gray-100 dark:bg-dark-bg rounded-lg flex items-center justify-center mb-4">
        <div class="text-center">
          <span class="material-symbols-outlined text-6xl text-gray-400">qr_code_scanner</span>
          <p class="mt-2 text-gray-500">Point camera at barcode</p>
        </div>
      </div>

      <!-- Manual Entry -->
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Or enter barcode manually:
          </label>
          <input
            v-model="scannedCode"
            type="text"
            class="input"
            placeholder="Enter barcode data..."
            @keyup.enter="handleScan"
          />
        </div>

        <button 
          @click="handleScan"
          class="btn btn-primary w-full py-3"
          :disabled="scanning || !gpsLocation"
        >
          <span v-if="scanning" class="flex items-center justify-center gap-2">
            <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            Processing...
          </span>
          <span v-else class="flex items-center justify-center gap-2">
            <span class="material-symbols-outlined">fingerprint</span>
            Submit Attendance
          </span>
        </button>
      </div>
    </div>

    <!-- Result -->
    <div v-if="result" class="card p-4">
      <div 
        class="flex items-center gap-3"
        :class="result.success ? 'text-green-600' : 'text-red-600'"
      >
        <span class="material-symbols-outlined text-2xl">
          {{ result.success ? 'check_circle' : 'error' }}
        </span>
        <div>
          <p class="font-medium">
            {{ result.success ? result.message : result.error }}
          </p>
          <p v-if="result.attendance" class="text-sm opacity-75">
            {{ result.attendance.check_type }} at {{ new Date(result.attendance.scan_time).toLocaleTimeString() }}
          </p>
        </div>
      </div>
    </div>

    <!-- Note -->
    <p class="text-center text-sm text-gray-500">
      Make sure you're within the office area before scanning
    </p>
  </div>
</template>

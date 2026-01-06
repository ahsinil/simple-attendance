<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useAttendanceStore } from '@/stores/attendance'
import { QrcodeStream } from 'vue-qrcode-reader'

const attendanceStore = useAttendanceStore()

const scanning = ref(false)
const scannedCode = ref('')
const gpsLocation = ref(null)
const gpsError = ref('')
const gpsAccuracy = ref(null)
const result = ref(null)
const cameraError = ref('')
const cameraLoading = ref(true)

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

// QR Code Scanner handlers
function onCameraReady() {
  cameraLoading.value = false
  cameraError.value = ''
}

function onCameraError(error) {
  cameraLoading.value = false
  if (error.name === 'NotAllowedError') {
    cameraError.value = 'Camera access denied. Please allow camera permission.'
  } else if (error.name === 'NotFoundError') {
    cameraError.value = 'No camera found on this device.'
  } else if (error.name === 'NotSupportedError') {
    cameraError.value = 'Camera not supported. Try using HTTPS.'
  } else if (error.name === 'NotReadableError') {
    cameraError.value = 'Camera is already in use by another application.'
  } else if (error.name === 'OverconstrainedError') {
    cameraError.value = 'Camera constraints not satisfiable.'
  } else if (error.name === 'StreamApiNotSupportedError') {
    cameraError.value = 'Stream API not supported in this browser.'
  } else {
    cameraError.value = `Camera error: ${error.message}`
  }
}

async function onDetect(detectedCodes) {
  if (scanning.value) return // Prevent multiple scans
  if (!detectedCodes || detectedCodes.length === 0) return
  
  // vue-qrcode-reader v5.x returns an array of detected codes
  const firstCode = detectedCodes[0]
  console.log('=== QR SCAN DEBUG ===')
  console.log('Detected codes:', detectedCodes)
  console.log('First code object:', firstCode)
  console.log('Raw value:', firstCode.rawValue)
  scannedCode.value = firstCode.rawValue
  await handleScan()
}

async function handleScan() {
  if (!scannedCode.value) {
    result.value = { success: false, error: 'Please scan or enter barcode data' }
    return
  }

  if (!gpsLocation.value) {
    result.value = { success: false, error: 'GPS location not available. Please enable location services.' }
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

function dismissResult() {
  result.value = null
}
</script>

<template>
  <div class="max-w-lg mx-auto space-y-6">
    <!-- Success Popup Modal -->
    <Teleport to="body">
      <Transition name="popup">
        <div v-if="result && result.success" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <!-- Backdrop -->
          <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="dismissResult"></div>
          
          <!-- Modal Content -->
          <div class="relative bg-white dark:bg-dark-card rounded-2xl shadow-2xl p-8 max-w-sm w-full transform animate-bounce-in">
            <!-- Success Icon -->
            <div class="flex justify-center mb-4">
              <div class="w-20 h-20 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center animate-pulse">
                <span class="material-symbols-outlined text-5xl text-green-500">check_circle</span>
              </div>
            </div>
            
            <!-- Content -->
            <div class="text-center">
              <h3 class="text-xl font-bold text-green-600 dark:text-green-400 mb-2">
                {{ result.message }}
              </h3>
              <p v-if="result.attendance" class="text-gray-600 dark:text-gray-300">
                {{ result.attendance.check_type }} at {{ new Date(result.attendance.scan_time).toLocaleTimeString() }}
              </p>
            </div>
            
            <!-- Close Button -->
            <button 
              @click="dismissResult"
              class="mt-6 w-full btn btn-primary py-3"
            >
              OK
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Error Alert (inline, stays visible) -->
    <div v-if="result && !result.success" class="card p-4 border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20">
      <div class="flex items-center gap-3 text-red-600 dark:text-red-400">
        <span class="material-symbols-outlined text-2xl">error</span>
        <div>
          <p class="font-medium">{{ result.error }}</p>
        </div>
        <button @click="dismissResult" class="ml-auto">
          <span class="material-symbols-outlined text-gray-400 hover:text-gray-600">close</span>
        </button>
      </div>
    </div>

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

    <!-- QR Scanner -->
    <div class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 text-center">
        Scan QR Code
      </h3>

      <div class="aspect-square bg-gray-100 dark:bg-dark-bg rounded-lg overflow-hidden mb-4 relative">
        <!-- Camera Loading -->
        <div v-if="cameraLoading" class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-dark-bg z-10">
          <div class="text-center">
            <svg class="animate-spin h-10 w-10 text-primary mx-auto mb-2" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p class="text-gray-500">Starting camera...</p>
          </div>
        </div>

        <!-- Camera Error -->
        <div v-if="cameraError" class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-dark-bg z-10">
          <div class="text-center p-4">
            <span class="material-symbols-outlined text-5xl text-red-400 mb-2">videocam_off</span>
            <p class="text-red-500 text-sm">{{ cameraError }}</p>
          </div>
        </div>

        <!-- QR Code Stream -->
        <QrcodeStream 
          @detect="onDetect"
          @camera-on="onCameraReady"
          @error="onCameraError"
          class="w-full h-full"
        />

        <!-- Scan Overlay -->
        <div class="absolute inset-0 pointer-events-none">
          <div class="absolute inset-8 border-2 border-primary rounded-lg"></div>
          <div class="absolute top-8 left-8 w-6 h-6 border-t-4 border-l-4 border-primary rounded-tl-lg"></div>
          <div class="absolute top-8 right-8 w-6 h-6 border-t-4 border-r-4 border-primary rounded-tr-lg"></div>
          <div class="absolute bottom-8 left-8 w-6 h-6 border-b-4 border-l-4 border-primary rounded-bl-lg"></div>
          <div class="absolute bottom-8 right-8 w-6 h-6 border-b-4 border-r-4 border-primary rounded-br-lg"></div>
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

    <!-- Note -->
    <p class="text-center text-sm text-gray-500">
      Make sure you're within the office area before scanning
    </p>
  </div>
</template>

<style scoped>
/* Popup transition */
.popup-enter-active,
.popup-leave-active {
  transition: all 0.3s ease;
}

.popup-enter-from,
.popup-leave-to {
  opacity: 0;
}

.popup-enter-from .relative,
.popup-leave-to .relative {
  transform: scale(0.9);
}

/* Bounce-in animation for the modal */
@keyframes bounce-in {
  0% {
    transform: scale(0.5);
    opacity: 0;
  }
  60% {
    transform: scale(1.05);
    opacity: 1;
  }
  100% {
    transform: scale(1);
  }
}

.animate-bounce-in {
  animation: bounce-in 0.4s ease-out;
}
</style>

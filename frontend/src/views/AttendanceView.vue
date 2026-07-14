<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useAttendanceStore } from '@/stores/attendance'
import { QrcodeStream } from 'vue-qrcode-reader'
import { getDeviceFingerprint, getDeviceData } from '@/utils/deviceFingerprint'

const attendanceStore = useAttendanceStore()
const deviceFingerprint = ref(null)
const deviceData = ref({})

// Audio context for beep sound
let audioContext = null

function playBeep() {
  try {
    if (!audioContext) {
      audioContext = new (window.AudioContext || window.webkitAudioContext)()
    }
    
    const oscillator = audioContext.createOscillator()
    const gainNode = audioContext.createGain()
    
    oscillator.connect(gainNode)
    gainNode.connect(audioContext.destination)
    
    oscillator.frequency.value = 1000 // 1000 Hz beep
    oscillator.type = 'sine'
    
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime)
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.15)
    
    oscillator.start(audioContext.currentTime)
    oscillator.stop(audioContext.currentTime + 0.15)
  } catch (e) {
    console.warn('Could not play beep sound:', e)
  }
}

const scanning = ref(false)
const scannedCode = ref('')
const gpsLocation = ref(null)
const gpsError = ref('')
const gpsAccuracy = ref(null)
const result = ref(null)
const cameraError = ref('')
const cameraLoading = ref(true)

const showOvertimePrompt = ref(false)
const isOvertime = ref(false)
const overtimeReason = ref('')
const submittingOvertime = ref(false)

let watchId = null

onMounted(async () => {
  attendanceStore.fetchLocations()
  startGpsTracking()
  // Generate device fingerprint for attendance scans
  try {
    deviceFingerprint.value = await getDeviceFingerprint()
    deviceData.value = getDeviceData()
  } catch (e) {
    console.warn('Could not generate device fingerprint:', e)
  }
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
  
  // Play beep sound on successful detection
  playBeep()
  
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
    device_fingerprint: deviceFingerprint.value,
    ...deviceData.value,
  })

  if (response.success) {
    if (response.data.attendance.check_type === 'OUT' && response.data.attendance.overtime_min > 60) {
      result.value = { success: true, message: response.data.message, attendance: response.data.attendance }
      showOvertimePrompt.value = true
    } else {
      result.value = { success: true, message: response.data.message, attendance: response.data.attendance }
    }
  } else {
    result.value = { success: false, error: response.error }
  }

  scanning.value = false
  scannedCode.value = ''
}

async function submitOvertimeReason() {
  if (!isOvertime.value || !overtimeReason.value.trim()) {
    showOvertimePrompt.value = false
    return
  }
  
  submittingOvertime.value = true
  const res = await attendanceStore.submitOvertimeReason(result.value.attendance.id, overtimeReason.value)
  submittingOvertime.value = false
  
  showOvertimePrompt.value = false
}

function dismissOvertimePrompt() {
  showOvertimePrompt.value = false
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
        <div v-if="result && result.success && !showOvertimePrompt" class="fixed inset-0 z-50 flex items-center justify-center p-4">
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
                {{ $t('app.attendanceView.checkType', { type: result.attendance.check_type, time: new Date(result.attendance.scan_time).toLocaleTimeString() }) }}
              </p>
            </div>
            
            <!-- Close Button -->
            <button 
              @click="dismissResult"
              class="mt-6 w-full btn btn-primary py-3"
            >
              {{ $t('app.attendanceView.ok') }}
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Overtime Prompt Modal -->
    <Teleport to="body">
      <Transition name="popup">
        <div v-if="showOvertimePrompt" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="dismissOvertimePrompt"></div>
          
          <div class="relative bg-white dark:bg-dark-card rounded-2xl shadow-2xl p-8 max-w-sm w-full transform animate-bounce-in">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4 text-center">
              Apakah kamu lembur hari ini?
            </h3>
            
            <div class="flex items-center justify-between mb-4">
              <span class="text-gray-700 dark:text-gray-300 font-medium">Ya, saya lembur</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="isOvertime" class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
              </label>
            </div>
            
            <div v-if="isOvertime" class="mb-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Alasan Lembur
              </label>
              <textarea 
                v-model="overtimeReason"
                class="input w-full h-24 resize-none"
                placeholder="Tuliskan alasan lembur..."
              ></textarea>
            </div>
            
            <div class="flex gap-3 mt-6">
              <button 
                @click="dismissOvertimePrompt"
                class="flex-1 btn bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300 py-3"
              >
                Lewati
              </button>
              <button 
                @click="submitOvertimeReason"
                class="flex-1 btn btn-primary py-3"
                :disabled="isOvertime && !overtimeReason.trim() || submittingOvertime"
              >
                <span v-if="submittingOvertime" class="flex items-center justify-center gap-2">
                  <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                </span>
                <span v-else>Simpan</span>
              </button>
            </div>
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
            {{ gpsLocation ? $t('app.attendanceView.gpsReady') : $t('app.attendanceView.acquiringGps') }}
          </p>
          <p v-if="gpsLocation" class="text-xs text-gray-500">
            {{ $t('app.attendanceView.accuracy', { acc: Math.round(gpsAccuracy) }) }}
          </p>
          <p v-if="gpsError" class="text-xs text-red-500">{{ gpsError }}</p>
        </div>
      </div>
    </div>

    <!-- QR Scanner -->
    <div class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 text-center">
        {{ $t('app.attendanceView.scanQrCode') }}
      </h3>

      <div class="aspect-square bg-gray-100 dark:bg-dark-bg rounded-lg overflow-hidden mb-4 relative">
        <!-- Camera Loading -->
        <div v-if="cameraLoading" class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-dark-bg z-10">
          <div class="text-center">
            <svg class="animate-spin h-10 w-10 text-primary mx-auto mb-2" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p class="text-gray-500">{{ $t('app.attendanceView.startingCamera') }}</p>
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
            {{ $t('app.attendanceView.orEnterBarcode') }}
          </label>
          <input
            v-model="scannedCode"
            type="text"
            class="input"
            :placeholder="$t('app.attendanceView.enterBarcodeData')"
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
            {{ $t('app.attendanceView.processing') }}
          </span>
          <span v-else class="flex items-center justify-center gap-2">
            <span class="material-symbols-outlined">fingerprint</span>
            {{ $t('app.attendanceView.submitAttendance') }}
          </span>
        </button>
      </div>
    </div>

    <!-- Note -->
    <p class="text-center text-sm text-gray-500">
      {{ $t('app.attendanceView.makeSureWithinOffice') }}
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

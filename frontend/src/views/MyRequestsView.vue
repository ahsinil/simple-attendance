<script setup>
import { ref, onMounted, computed } from 'vue'
import { useAttendanceStore } from '@/stores/attendance'

const attendanceStore = useAttendanceStore()

const showForm = ref(false)
const form = ref({
  location_id: '',
  check_type: 'IN',
  request_time: '',
  reason: '',
  photo: null,
})
const photoPreview = ref(null)
const fileInput = ref(null)
const submitting = ref(false)
const submitError = ref('')
const submitSuccess = ref(false)
const showPhotoModal = ref(false)
const selectedPhoto = ref('')

// Base URL for photo storage
const storageUrl = import.meta.env.VITE_API_URL?.replace('/api', '') ?? 'http://localhost:8000'

function getPhotoUrl(path) {
  if (!path) return null
  return `${storageUrl}/storage/${path}`
}

function openPhotoModal(photoPath) {
  selectedPhoto.value = getPhotoUrl(photoPath)
  showPhotoModal.value = true
}

// Format date to local datetime-local input format (YYYY-MM-DDTHH:mm)
function formatToLocalDatetime(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day}T${hours}:${minutes}`
}

onMounted(() => {
  attendanceStore.fetchMyRequests()
  attendanceStore.fetchLocations()
  
  // Set default time to now (using device's local timezone)
  const now = new Date()
  form.value.request_time = formatToLocalDatetime(now)
})

function handlePhotoSelect(event) {
  const file = event.target.files[0]
  if (file) {
    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      submitError.value = 'Photo size must be less than 5MB'
      return
    }
    
    // Validate file type
    if (!file.type.startsWith('image/')) {
      submitError.value = 'Please select an image file'
      return
    }
    
    form.value.photo = file
    
    // Create preview
    const reader = new FileReader()
    reader.onload = (e) => {
      photoPreview.value = e.target.result
    }
    reader.readAsDataURL(file)
    submitError.value = ''
  }
}

function removePhoto() {
  form.value.photo = null
  photoPreview.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function triggerFileInput() {
  fileInput.value?.click()
}

async function handleSubmit() {
  if (!form.value.location_id || !form.value.reason) {
    submitError.value = 'Please fill all required fields'
    return
  }

  submitting.value = true
  submitError.value = ''

  const result = await attendanceStore.submitManualRequest(form.value)

  submitting.value = false

  if (result.success) {
    submitSuccess.value = true
    showForm.value = false
    // Reset form
    form.value.photo = null
    photoPreview.value = null
    attendanceStore.fetchMyRequests()
    setTimeout(() => submitSuccess.value = false, 3000)
  } else {
    submitError.value = result.error
  }
}

function statusBadgeClass(status) {
  const classes = {
    PENDING: 'bg-amber-100 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400',
    APPROVED: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    REJECTED: 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400',
  }
  return classes[status] || 'bg-gray-100 text-gray-700'
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('en-GB', { 
    day: 'numeric',
    month: 'long', 
    year: 'numeric'
  })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Success Alert -->
    <div v-if="submitSuccess" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-600 px-4 py-3 rounded-lg flex items-center gap-2">
      <span class="material-symbols-outlined">check_circle</span>
      {{ $t('app.myRequestsView.requestSubmitted') }}
    </div>

    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $t('app.myRequestsView.myRequests') }}</h2>
      <button @click="showForm = !showForm" class="btn btn-primary">
        <span class="material-symbols-outlined text-sm">add</span>
        {{ $t('app.myRequestsView.newRequest') }}
      </button>
    </div>

    <!-- Request Form -->
    <div v-if="showForm" class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ $t('app.myRequestsView.submitManualRequest') }}</h3>

      <div v-if="submitError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 px-4 py-3 rounded-lg mb-4">
        {{ submitError }}
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('app.myRequestsView.location') }}</label>
            <select v-model="form.location_id" class="input">
              <option value="">{{ $t('app.myRequestsView.selectLocation') }}</option>
              <option v-for="loc in attendanceStore.locations" :key="loc.id" :value="loc.id">
                {{ loc.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('app.myRequestsView.type') }}</label>
            <select v-model="form.check_type" class="input">
              <option value="IN">{{ $t('app.historyView.checkIn') }}</option>
              <option value="OUT">{{ $t('app.historyView.checkOut') }}</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('app.myRequestsView.dateTime') }}</label>
          <input v-model="form.request_time" type="datetime-local" class="input" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('app.myRequestsView.reason') }}</label>
          <textarea 
            v-model="form.reason" 
            class="input min-h-[100px]" 
            :placeholder="$t('app.myRequestsView.reasonPlaceholder')"
          />
        </div>

        <!-- Photo Upload -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('app.myRequestsView.photo') }}</label>
          <input 
            ref="fileInput"
            type="file" 
            accept="image/*"
            capture="environment"
            @change="handlePhotoSelect"
            class="hidden"
          />
          
          <div v-if="photoPreview" class="relative inline-block">
            <img :src="photoPreview" alt="Preview" class="w-32 h-32 object-cover rounded-lg border border-gray-200 dark:border-dark-border" />
            <button 
              type="button"
              @click="removePhoto"
              class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600"
            >
              <span class="material-symbols-outlined text-sm">close</span>
            </button>
          </div>
          
          <button 
            v-else
            type="button"
            @click="triggerFileInput"
            class="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 dark:border-dark-border rounded-lg hover:border-primary hover:bg-primary/5 transition-colors"
          >
            <span class="material-symbols-outlined text-gray-400">add_a_photo</span>
            <span class="text-gray-600 dark:text-gray-400">{{ $t('app.myRequestsView.addPhoto') }}</span>
          </button>
          <p class="text-xs text-gray-500 mt-1">{{ $t('app.myRequestsView.photoMax') }}</p>
        </div>

        <div class="flex gap-3">
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ submitting ? $t('app.myRequestsView.submitting') : $t('app.myRequestsView.submitRequest') }}
          </button>
          <button type="button" @click="showForm = false" class="btn btn-secondary">
            {{ $t('app.myRequestsView.cancel') }}
          </button>
        </div>
      </form>
    </div>

    <!-- Requests List -->
    <div class="card overflow-hidden">
      <div v-if="attendanceStore.loading" class="p-8 text-center text-gray-500">
        {{ $t('app.historyView.loading') }}
      </div>

      <div v-else-if="attendanceStore.myRequests?.data?.length" class="divide-y divide-gray-200 dark:divide-dark-border">
        <div 
          v-for="request in attendanceStore.myRequests.data" 
          :key="request.id"
          class="p-4"
        >
          <div class="flex items-start md:items-center gap-4">
            <!-- Photo thumbnail (left column) -->
            <div v-if="request.photo_path" class="flex-shrink-0">
              <img 
                :src="getPhotoUrl(request.photo_path)" 
                alt="Attached photo" 
                class="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-dark-border cursor-pointer hover:opacity-80"
                @click="openPhotoModal(request.photo_path)"
              />
            </div>
            <div v-else class="flex-shrink-0 w-16 h-16 rounded-lg bg-gray-100 dark:bg-dark-border flex items-center justify-center">
              <span class="material-symbols-outlined text-gray-400">image</span>
            </div>
            
            <!-- Text content (right column) -->
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2 mb-1">
                <span class="font-medium text-gray-900 dark:text-white">
                  {{ request.check_type === 'IN' ? $t('app.myRequestsView.checkInRequest') : $t('app.myRequestsView.checkOutRequest') }}
                </span>
                <span :class="statusBadgeClass(request.status)" class="text-xs px-2 py-0.5 rounded-full">
                  {{ request.status }}
                </span>
              </div>
              <p class="text-sm text-gray-500">{{ formatDate(request.request_time) }} • {{ request.location?.name }}</p>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 break-words">{{ request.reason }}</p>
              <p v-if="request.admin_note" class="text-sm text-primary mt-1 break-words">
                {{ $t('app.myRequestsView.note', { note: request.admin_note }) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="p-8 text-center text-gray-500">
        {{ $t('app.myRequestsView.noRequests') }}
      </div>
    </div>

    <!-- Photo Modal -->
    <div v-if="showPhotoModal" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4" @click="showPhotoModal = false">
      <div class="relative max-w-4xl max-h-[90vh]" @click.stop>
        <button 
          @click="showPhotoModal = false" 
          class="absolute -top-10 right-0 text-white hover:text-gray-300"
        >
          <span class="material-symbols-outlined text-3xl">close</span>
        </button>
        <img 
          :src="selectedPhoto" 
          alt="Full size photo" 
          class="max-w-full max-h-[85vh] rounded-lg shadow-2xl"
        />
      </div>
    </div>
  </div>
</template>

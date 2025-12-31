<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const requests = ref([])
const loading = ref(true)
const statusFilter = ref('PENDING')
const processingId = ref(null)
const adminNote = ref('')
const adjustedTime = ref('')
const showApproveModal = ref(false)
const showRejectModal = ref(false)
const showPhotoModal = ref(false)
const selectedRequest = ref(null)
const selectedPhoto = ref('')

// Base URL for photo storage
const storageUrl = import.meta.env.VITE_API_URL?.replace('/api', '') || 'http://localhost:8000'

function getPhotoUrl(path) {
  if (!path) return null
  return `${storageUrl}/storage/${path}`
}

function openPhotoModal(photoPath) {
  selectedPhoto.value = getPhotoUrl(photoPath)
  showPhotoModal.value = true
}

onMounted(() => fetchRequests())

async function fetchRequests() {
  loading.value = true
  try {
    const response = await adminApi.getRequests({ status: statusFilter.value })
    requests.value = response.data.data?.data || []
  } catch (error) {
    console.error('Failed to fetch requests:', error)
    toast.error('Failed to load requests')
  }
  loading.value = false
}

// Format date to local datetime-local input format (YYYY-MM-DDTHH:mm)
function formatToLocalDatetime(date) {
  const d = new Date(date)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day}T${hours}:${minutes}`
}

function openApproveModal(request) {
  selectedRequest.value = request
  adminNote.value = ''
  adjustedTime.value = formatToLocalDatetime(request.request_time)
  showApproveModal.value = true
}

async function confirmApprove() {
  processingId.value = selectedRequest.value.id
  try {
    await adminApi.approveRequest(selectedRequest.value.id, { 
      admin_note: adminNote.value,
      adjusted_time: adjustedTime.value
    })
    showApproveModal.value = false
    toast.success('Request approved successfully')
    fetchRequests()
  } catch (error) {
    const message = error.response?.data?.message || error.response?.data?.error || 'Failed to approve request'
    toast.error(message)
  }
  processingId.value = null
}

function openRejectModal(request) {
  selectedRequest.value = request
  adminNote.value = ''
  showRejectModal.value = true
}

async function confirmReject() {
  if (!adminNote.value.trim()) {
    toast.warning('Please provide a reason for rejection')
    return
  }

  processingId.value = selectedRequest.value.id
  try {
    await adminApi.rejectRequest(selectedRequest.value.id, { admin_note: adminNote.value })
    showRejectModal.value = false
    toast.success('Request rejected')
    fetchRequests()
  } catch (error) {
    const message = error.response?.data?.message || error.response?.data?.error || 'Failed to reject request'
    toast.error(message)
  }
  processingId.value = null
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('en-GB', { 
    day: 'numeric',
    month: 'long', 
    year: 'numeric'
  })
}

function formatDateTime(iso) {
  return new Date(iso).toLocaleString('en-GB', { 
    day: 'numeric',
    month: 'long', 
    year: 'numeric',
    hour: '2-digit', 
    minute: '2-digit'
  })
}

function formatTime(iso) {
  return new Date(iso).toLocaleTimeString('en-GB', { 
    hour: '2-digit', 
    minute: '2-digit'
  })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Filters -->
    <div class="flex gap-2">
      <button 
        v-for="status in ['PENDING', 'APPROVED', 'REJECTED']"
        :key="status"
        @click="statusFilter = status; fetchRequests()"
        class="px-4 py-2 rounded-lg transition-colors"
        :class="statusFilter === status 
          ? 'bg-primary text-white' 
          : 'bg-white dark:bg-dark-surface text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-border'"
      >
        {{ status }}
      </button>
    </div>

    <!-- Requests List -->
    <div class="card overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-gray-500">Loading...</div>

      <div v-else-if="requests.length" class="divide-y divide-gray-200 dark:divide-dark-border">
        <div v-for="request in requests" :key="request.id" class="p-4">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="font-medium text-gray-900 dark:text-white">{{ request.user?.name }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-dark-border text-gray-600 dark:text-gray-400">
                  {{ request.check_type }}
                </span>
              </div>
              <p class="text-sm text-gray-500">
                Requested for: {{ formatDateTime(request.request_time) }} • {{ request.location?.name }}
              </p>
              <p class="text-xs text-gray-400 mt-0.5">
                Submitted: {{ formatDateTime(request.created_at) }}
              </p>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ request.reason }}</p>
              
              <!-- Photo thumbnail -->
              <div v-if="request.photo_path" class="mt-2">
                <img 
                  :src="getPhotoUrl(request.photo_path)" 
                  alt="Attached photo" 
                  class="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-dark-border cursor-pointer hover:opacity-80 transition-opacity"
                  @click="openPhotoModal(request.photo_path)"
                />
              </div>
            </div>

            <div v-if="statusFilter === 'PENDING'" class="flex gap-2">
              <button 
                @click="openApproveModal(request)"
                :disabled="processingId === request.id"
                class="btn btn-primary"
              >
                <span class="material-symbols-outlined text-sm">check</span>
                Approve
              </button>
              <button 
                @click="openRejectModal(request)"
                :disabled="processingId === request.id"
                class="btn btn-danger"
              >
                <span class="material-symbols-outlined text-sm">close</span>
                Reject
              </button>
            </div>
            <div v-else class="text-right">
              <p class="text-sm text-gray-500">Reviewed by {{ request.reviewer?.name }}</p>
              <p v-if="request.admin_note" class="text-sm text-primary">{{ request.admin_note }}</p>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="p-8 text-center text-gray-500">No {{ statusFilter.toLowerCase() }} requests</div>
    </div>

    <!-- Approve Modal -->
    <div v-if="showApproveModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Approve Request</h3>
        
        <div class="space-y-4">
          <div>
            <p class="text-sm text-gray-500 mb-2">
              <span class="font-medium text-gray-900 dark:text-white">{{ selectedRequest?.user?.name }}</span>
              is requesting {{ selectedRequest?.check_type === 'IN' ? 'Check In' : 'Check Out' }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Adjust Time (if needed)
            </label>
            <input 
              v-model="adjustedTime" 
              type="datetime-local" 
              class="input"
            />
            <p class="text-xs text-gray-400 mt-1">
              Original: {{ formatDateTime(selectedRequest?.request_time) }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Note (optional)
            </label>
            <textarea 
              v-model="adminNote"
              class="input min-h-[80px]"
              placeholder="Add a note..."
            />
          </div>
        </div>

        <div class="flex gap-3 mt-4">
          <button @click="confirmApprove" class="btn btn-primary flex-1" :disabled="processingId">
            {{ processingId ? 'Approving...' : 'Approve' }}
          </button>
          <button @click="showApproveModal = false" class="btn btn-secondary flex-1">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="showRejectModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Reject Request</h3>
        <textarea 
          v-model="adminNote"
          class="input min-h-[100px]"
          placeholder="Provide a reason for rejection..."
        />
        <div class="flex gap-3 mt-4">
          <button @click="confirmReject" class="btn btn-danger flex-1" :disabled="processingId">
            {{ processingId ? 'Rejecting...' : 'Reject' }}
          </button>
          <button @click="showRejectModal = false" class="btn btn-secondary flex-1">Cancel</button>
        </div>
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

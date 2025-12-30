<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'

const requests = ref([])
const loading = ref(true)
const statusFilter = ref('PENDING')
const processingId = ref(null)
const adminNote = ref('')
const showNoteModal = ref(false)
const selectedRequest = ref(null)
const isRejecting = ref(false)

onMounted(() => fetchRequests())

async function fetchRequests() {
  loading.value = true
  try {
    const response = await adminApi.getRequests({ status: statusFilter.value })
    requests.value = response.data.data?.data || []
  } catch (error) {
    console.error('Failed to fetch requests:', error)
  }
  loading.value = false
}

async function approve(request) {
  processingId.value = request.id
  try {
    await adminApi.approveRequest(request.id, { admin_note: '' })
    fetchRequests()
  } catch (error) {
    alert(error.response?.data?.error || 'Failed to approve')
  }
  processingId.value = null
}

function openRejectModal(request) {
  selectedRequest.value = request
  isRejecting.value = true
  adminNote.value = ''
  showNoteModal.value = true
}

async function confirmReject() {
  if (!adminNote.value.trim()) {
    alert('Please provide a reason for rejection')
    return
  }

  processingId.value = selectedRequest.value.id
  try {
    await adminApi.rejectRequest(selectedRequest.value.id, { admin_note: adminNote.value })
    showNoteModal.value = false
    fetchRequests()
  } catch (error) {
    alert(error.response?.data?.error || 'Failed to reject')
  }
  processingId.value = null
}

function formatDate(iso) {
  return new Date(iso).toLocaleString('en-US', { 
    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' 
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
                {{ formatDate(request.request_time) }} • {{ request.location?.name }}
              </p>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ request.reason }}</p>
            </div>

            <div v-if="statusFilter === 'PENDING'" class="flex gap-2">
              <button 
                @click="approve(request)"
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

    <!-- Reject Modal -->
    <div v-if="showNoteModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
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
          <button @click="showNoteModal = false" class="btn btn-secondary flex-1">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

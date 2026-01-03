<script setup>
import { ref, onMounted, computed } from 'vue'
import { adminApi, leaveApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

// Permission checks
const canApprove = computed(() => authStore.hasPermission('admin.leaves.approve'))
const canReject = computed(() => authStore.hasPermission('admin.leaves.reject'))

// Data
const requests = ref({ data: [] })
const stats = ref({})
const leaveTypes = ref([])
const loading = ref(false)

// Filters
const filters = ref({
  status: 'PENDING',
  leave_type_id: '',
})

// Modal
const showModal = ref(false)
const selectedRequest = ref(null)
const modalAction = ref('')
const adminNote = ref('')
const processing = ref(false)

// Format dates
function formatDate(date) {
  return new Date(date).toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

function formatDateRange(start, end) {
  const startDate = formatDate(start)
  const endDate = formatDate(end)
  return startDate === endDate ? startDate : `${startDate} - ${endDate}`
}

// Status badge class
function statusBadgeClass(status) {
  const classes = {
    PENDING: 'bg-amber-100 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400',
    APPROVED: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    REJECTED: 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400',
    CANCELLED: 'bg-gray-100 text-gray-500 dark:bg-gray-900/20 dark:text-gray-400',
  }
  return classes[status] || 'bg-gray-100 text-gray-700'
}

// Fetch data
async function fetchData() {
  loading.value = true
  try {
    const [requestsRes, statsRes, typesRes] = await Promise.all([
      adminApi.getLeaveRequests(filters.value),
      adminApi.getLeaveRequestStats(),
      leaveApi.getTypes(),
    ])
    requests.value = requestsRes.data.data
    stats.value = statsRes.data.data
    leaveTypes.value = typesRes.data.data
  } catch (error) {
    console.error('Failed to fetch leave requests:', error)
  } finally {
    loading.value = false
  }
}

async function fetchRequests() {
  try {
    const res = await adminApi.getLeaveRequests(filters.value)
    requests.value = res.data.data
  } catch (error) {
    console.error('Failed to fetch requests:', error)
  }
}

// Open approve/reject modal
function openModal(request, action) {
  selectedRequest.value = request
  modalAction.value = action
  adminNote.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  selectedRequest.value = null
  adminNote.value = ''
}

// Process approve/reject
async function processRequest() {
  if (modalAction.value === 'reject' && adminNote.value.length < 5) {
    alert('Please provide a reason (at least 5 characters) for rejection')
    return
  }

  processing.value = true

  try {
    if (modalAction.value === 'approve') {
      await adminApi.approveLeaveRequest(selectedRequest.value.id, { admin_note: adminNote.value })
    } else {
      await adminApi.rejectLeaveRequest(selectedRequest.value.id, { admin_note: adminNote.value })
    }
    closeModal()
    fetchData()
  } catch (error) {
    alert(error.response?.data?.error || 'Failed to process request')
  } finally {
    processing.value = false
  }
}

// Quick approve
async function quickApprove(request) {
  if (!confirm(`Approve leave request from ${request.user.name}?`)) return

  try {
    await adminApi.approveLeaveRequest(request.id, {})
    fetchData()
  } catch (error) {
    alert(error.response?.data?.error || 'Failed to approve')
  }
}

onMounted(() => {
  fetchData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold text-gray-900 dark:text-white">Leave Requests</h2>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="card p-4">
        <div class="text-sm text-gray-500 mb-1">Pending</div>
        <div class="text-2xl font-bold text-amber-600">{{ stats.pending || 0 }}</div>
      </div>
      <div class="card p-4">
        <div class="text-sm text-gray-500 mb-1">Approved Today</div>
        <div class="text-2xl font-bold text-green-600">{{ stats.approved_today || 0 }}</div>
      </div>
      <div class="card p-4">
        <div class="text-sm text-gray-500 mb-1">Rejected Today</div>
        <div class="text-2xl font-bold text-red-600">{{ stats.rejected_today || 0 }}</div>
      </div>
      <div class="card p-4">
        <div class="text-sm text-gray-500 mb-1">This Month</div>
        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_this_month || 0 }}</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-4 flex-wrap">
      <div>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mr-2">Status:</label>
        <select v-model="filters.status" @change="fetchRequests" class="input w-auto">
          <option value="">All</option>
          <option value="PENDING">Pending</option>
          <option value="APPROVED">Approved</option>
          <option value="REJECTED">Rejected</option>
          <option value="CANCELLED">Cancelled</option>
        </select>
      </div>
      <div>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mr-2">Type:</label>
        <select v-model="filters.leave_type_id" @change="fetchRequests" class="input w-auto">
          <option value="">All Types</option>
          <option v-for="type in leaveTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
        </select>
      </div>
    </div>

    <!-- Requests Table -->
    <div class="card overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-dark-surface">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Leave Type</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dates</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Days</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-dark-border">
          <tr v-if="loading">
            <td colspan="7" class="px-4 py-8 text-center text-gray-500">Loading...</td>
          </tr>
          <tr v-else-if="!requests.data?.length">
            <td colspan="7" class="px-4 py-8 text-center text-gray-500">No leave requests found</td>
          </tr>
          <tr v-else v-for="request in requests.data" :key="request.id" class="hover:bg-gray-50 dark:hover:bg-dark-surface/50">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900 dark:text-white">{{ request.user?.name }}</div>
              <div class="text-xs text-gray-500">{{ request.user?.email }}</div>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: request.leave_type?.color }"></div>
                <span class="text-gray-900 dark:text-white">{{ request.leave_type?.name }}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
              {{ formatDateRange(request.start_date, request.end_date) }}
            </td>
            <td class="px-4 py-3 text-center font-medium text-gray-900 dark:text-white">
              {{ request.days_requested }}
            </td>
            <td class="px-4 py-3">
              <div class="text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate" :title="request.reason">
                {{ request.reason }}
              </div>
              <div v-if="request.admin_note" class="text-xs text-primary mt-1">
                Note: {{ request.admin_note }}
              </div>
            </td>
            <td class="px-4 py-3 text-center">
              <span :class="statusBadgeClass(request.status)" class="text-xs px-2 py-1 rounded-full">
                {{ request.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div v-if="request.status === 'PENDING' && (canApprove || canReject)" class="flex items-center justify-end gap-2">
                <button
                  v-if="canApprove"
                  @click="quickApprove(request)"
                  class="text-green-600 hover:text-green-700 flex items-center gap-1"
                  title="Approve"
                >
                  <span class="material-symbols-outlined text-sm">check_circle</span>
                </button>
                <button
                  v-if="canReject"
                  @click="openModal(request, 'reject')"
                  class="text-red-500 hover:text-red-700 flex items-center gap-1"
                  title="Reject"
                >
                  <span class="material-symbols-outlined text-sm">cancel</span>
                </button>
              </div>
              <div v-else class="text-xs text-gray-400">
                <span v-if="request.reviewer">by {{ request.reviewer.name }}</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Approve/Reject Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white dark:bg-dark-surface rounded-xl shadow-xl max-w-md w-full p-6" @click.stop>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          {{ modalAction === 'approve' ? 'Approve' : 'Reject' }} Leave Request
        </h3>

        <div class="mb-4 p-4 bg-gray-50 dark:bg-dark-surface/50 rounded-lg">
          <div class="font-medium text-gray-900 dark:text-white">{{ selectedRequest?.user?.name }}</div>
          <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            {{ selectedRequest?.leave_type?.name }} • {{ selectedRequest?.days_requested }} days
          </div>
          <div class="text-sm text-gray-500 mt-1">
            {{ formatDateRange(selectedRequest?.start_date, selectedRequest?.end_date) }}
          </div>
          <div class="text-sm text-gray-600 dark:text-gray-400 mt-2">
            {{ selectedRequest?.reason }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Note {{ modalAction === 'reject' ? '*' : '(optional)' }}
          </label>
          <textarea
            v-model="adminNote"
            class="input min-h-[80px]"
            :placeholder="modalAction === 'reject' ? 'Please provide a reason for rejection...' : 'Add a note (optional)'"
          />
        </div>

        <div class="flex gap-3 mt-6">
          <button
            @click="processRequest"
            :class="modalAction === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
            class="flex-1 text-white px-4 py-2 rounded-lg font-medium transition-colors"
            :disabled="processing"
          >
            {{ processing ? 'Processing...' : (modalAction === 'approve' ? 'Approve' : 'Reject') }}
          </button>
          <button @click="closeModal" class="btn btn-secondary">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

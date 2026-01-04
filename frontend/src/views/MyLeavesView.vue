<script setup>
import { ref, onMounted, computed } from 'vue'
import { leaveApi } from '@/services/api'

// Data
const leaveTypes = ref([])
const balances = ref([])
const requests = ref({ data: [] })
const loading = ref(false)
const showForm = ref(false)

// Form
const form = ref({
  leave_type_id: '',
  start_date: '',
  end_date: '',
  reason: '',
})
const submitting = ref(false)
const submitError = ref('')
const submitSuccess = ref(false)

// Filter
const statusFilter = ref('')

// Confirmation modal
const showConfirmModal = ref(false)
const confirmRequestId = ref(null)
const cancelling = ref(false)

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

// Get today's date in YYYY-MM-DD format
function getTodayDate() {
  const now = new Date()
  return now.toISOString().split('T')[0]
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
    const [typesRes, balancesRes, requestsRes] = await Promise.all([
      leaveApi.getTypes(),
      leaveApi.getBalances(),
      leaveApi.getMyRequests({ status: statusFilter.value || undefined }),
    ])
    leaveTypes.value = typesRes.data.data
    balances.value = balancesRes.data.data
    requests.value = requestsRes.data.data
  } catch (error) {
    console.error('Failed to fetch leave data:', error)
  } finally {
    loading.value = false
  }
}

async function fetchRequests() {
  try {
    const res = await leaveApi.getMyRequests({ status: statusFilter.value || undefined })
    requests.value = res.data.data
  } catch (error) {
    console.error('Failed to fetch requests:', error)
  }
}

// Submit leave request
async function handleSubmit() {
  if (!form.value.leave_type_id || !form.value.start_date || !form.value.end_date || !form.value.reason) {
    submitError.value = 'Please fill all required fields'
    return
  }

  if (form.value.reason.length < 10) {
    submitError.value = 'Reason must be at least 10 characters'
    return
  }

  submitting.value = true
  submitError.value = ''

  try {
    await leaveApi.submitRequest(form.value)
    submitSuccess.value = true
    showForm.value = false
    resetForm()
    fetchData()
    setTimeout(() => submitSuccess.value = false, 3000)
  } catch (error) {
    submitError.value = error.response?.data?.error || 'Failed to submit request'
  } finally {
    submitting.value = false
  }
}

// Cancel request - show confirmation modal
function openCancelModal(id) {
  confirmRequestId.value = id
  showConfirmModal.value = true
}

function closeCancelModal() {
  showConfirmModal.value = false
  confirmRequestId.value = null
}

async function confirmCancel() {
  if (!confirmRequestId.value) return

  cancelling.value = true
  try {
    await leaveApi.cancelRequest(confirmRequestId.value)
    closeCancelModal()
    fetchData()
  } catch (error) {
    alert(error.response?.data?.error || 'Failed to cancel request')
  } finally {
    cancelling.value = false
  }
}

function resetForm() {
  form.value = {
    leave_type_id: '',
    start_date: '',
    end_date: '',
    reason: '',
  }
}

function openForm() {
  showForm.value = true
  // Set default start date to today
  form.value.start_date = getTodayDate()
  form.value.end_date = getTodayDate()
}

// Get leave type color
function getLeaveTypeColor(typeId) {
  const type = leaveTypes.value.find(t => t.id === typeId)
  return type?.color || '#4CAF50'
}

function getLeaveTypeName(typeId) {
  const type = leaveTypes.value.find(t => t.id === typeId)
  return type?.name || 'Unknown'
}

onMounted(() => {
  fetchData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Success Alert -->
    <div v-if="submitSuccess" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-600 px-4 py-3 rounded-lg flex items-center gap-2">
      <span class="material-symbols-outlined">check_circle</span>
      Leave request submitted successfully!
    </div>

    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold text-gray-900 dark:text-white">My Leaves</h2>
      <button @click="openForm" class="btn btn-primary">
        <span class="material-symbols-outlined text-sm">add</span>
        Request Leave
      </button>
    </div>

    <!-- Leave Balances -->
    <div v-if="balances.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
      <div
        v-for="item in balances"
        :key="item.leave_type.id"
        class="card p-4"
      >
        <div class="flex items-center gap-2 mb-2">
          <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: item.leave_type.color }"></div>
          <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ item.leave_type.name }}</span>
        </div>
        <div class="text-2xl font-bold text-gray-900 dark:text-white">
          {{ item.remaining }}
        </div>
        <div class="text-xs text-gray-500">
          of {{ item.balance.allocated_days }} days
        </div>
        <div v-if="item.balance.pending_days > 0" class="text-xs text-amber-600 mt-1">
          {{ item.balance.pending_days }} pending
        </div>
      </div>
    </div>

    <!-- Request Form -->
    <div v-if="showForm" class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Request Leave</h3>

      <div v-if="submitError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 px-4 py-3 rounded-lg mb-4">
        {{ submitError }}
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Leave Type *</label>
            <select v-model="form.leave_type_id" class="input">
              <option value="">Select type</option>
              <option v-for="type in leaveTypes" :key="type.id" :value="type.id">
                {{ type.name }} {{ !type.is_paid ? '(Unpaid)' : '' }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date *</label>
            <input v-model="form.start_date" type="date" class="input" :min="getTodayDate()" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date *</label>
            <input v-model="form.end_date" type="date" class="input" :min="form.start_date || getTodayDate()" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason *</label>
          <textarea
            v-model="form.reason"
            class="input min-h-[100px]"
            placeholder="Please provide a reason for your leave request (minimum 10 characters)..."
          />
        </div>

        <div class="flex gap-3">
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ submitting ? 'Submitting...' : 'Submit Request' }}
          </button>
          <button type="button" @click="showForm = false; resetForm()" class="btn btn-secondary">
            Cancel
          </button>
        </div>
      </form>
    </div>

    <!-- Status Filter -->
    <div class="flex items-center gap-4">
      <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Filter:</label>
      <select v-model="statusFilter" @change="fetchRequests" class="input w-auto">
        <option value="">All Requests</option>
        <option value="PENDING">Pending</option>
        <option value="APPROVED">Approved</option>
        <option value="REJECTED">Rejected</option>
        <option value="CANCELLED">Cancelled</option>
      </select>
    </div>

    <!-- Requests List -->
    <div class="card overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-gray-500">
        Loading...
      </div>

      <div v-else-if="requests.data?.length" class="divide-y divide-gray-200 dark:divide-dark-border">
        <div
          v-for="request in requests.data"
          :key="request.id"
          class="p-4 hover:bg-gray-50 dark:hover:bg-dark-surface/50"
        >
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-3 mb-1">
                <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: request.leave_type?.color }"></div>
                <span class="font-medium text-gray-900 dark:text-white">
                  {{ request.leave_type?.name }}
                </span>
                <span :class="statusBadgeClass(request.status)" class="text-xs px-2 py-0.5 rounded-full">
                  {{ request.status }}
                </span>
                <span class="text-sm text-gray-500">
                  {{ request.days_requested }} day{{ request.days_requested > 1 ? 's' : '' }}
                </span>
              </div>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ formatDateRange(request.start_date, request.end_date) }}
              </p>
              <p class="text-sm text-gray-500 mt-1">{{ request.reason }}</p>
              <p v-if="request.admin_note" class="text-sm text-primary mt-1">
                Note: {{ request.admin_note }}
              </p>
            </div>
            <div v-if="request.status === 'PENDING'" class="flex-shrink-0">
              <button
                @click="openCancelModal(request.id)"
                class="text-red-500 hover:text-red-700 text-sm"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="p-8 text-center text-gray-500">
        <span class="material-symbols-outlined text-4xl mb-2">beach_access</span>
        <p>No leave requests yet</p>
      </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div v-if="showConfirmModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white dark:bg-dark-surface rounded-xl shadow-xl max-w-sm w-full p-6" @click.stop>
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center">
            <span class="material-symbols-outlined text-red-500">warning</span>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Cancel Request</h3>
        </div>

        <p class="text-gray-600 dark:text-gray-400 mb-6">
          Are you sure you want to cancel this leave request? This action cannot be undone.
        </p>

        <div class="flex gap-3">
          <button
            @click="confirmCancel"
            :disabled="cancelling"
            class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors disabled:opacity-50"
          >
            {{ cancelling ? 'Cancelling...' : 'Yes, Cancel' }}
          </button>
          <button
            @click="closeCancelModal"
            class="flex-1 btn btn-secondary"
          >
            No, Keep It
          </button>
        </div>
      </div>
    </div>
  </div>
</template>


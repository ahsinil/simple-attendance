<script setup>
import { ref, onMounted } from 'vue'
import { useAttendanceStore } from '@/stores/attendance'

const attendanceStore = useAttendanceStore()

const showForm = ref(false)
const form = ref({
  location_id: '',
  check_type: 'IN',
  request_time: '',
  reason: '',
})
const submitting = ref(false)
const submitError = ref('')
const submitSuccess = ref(false)

onMounted(() => {
  attendanceStore.fetchMyRequests()
  attendanceStore.fetchLocations()
  
  // Set default time to now
  const now = new Date()
  form.value.request_time = now.toISOString().slice(0, 16)
})

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
  return new Date(iso).toLocaleDateString('en-US', { 
    month: 'short', 
    day: 'numeric',
    year: 'numeric'
  })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Success Alert -->
    <div v-if="submitSuccess" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-600 px-4 py-3 rounded-lg flex items-center gap-2">
      <span class="material-symbols-outlined">check_circle</span>
      Request submitted successfully!
    </div>

    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold text-gray-900 dark:text-white">My Requests</h2>
      <button @click="showForm = !showForm" class="btn btn-primary">
        <span class="material-symbols-outlined text-sm">add</span>
        New Request
      </button>
    </div>

    <!-- Request Form -->
    <div v-if="showForm" class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Submit Manual Request</h3>

      <div v-if="submitError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 px-4 py-3 rounded-lg mb-4">
        {{ submitError }}
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Location *</label>
            <select v-model="form.location_id" class="input">
              <option value="">Select location</option>
              <option v-for="loc in attendanceStore.locations" :key="loc.id" :value="loc.id">
                {{ loc.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type *</label>
            <select v-model="form.check_type" class="input">
              <option value="IN">Check In</option>
              <option value="OUT">Check Out</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date & Time *</label>
          <input v-model="form.request_time" type="datetime-local" class="input" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason *</label>
          <textarea 
            v-model="form.reason" 
            class="input min-h-[100px]" 
            placeholder="Explain why you need this manual attendance..."
          />
        </div>

        <div class="flex gap-3">
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ submitting ? 'Submitting...' : 'Submit Request' }}
          </button>
          <button type="button" @click="showForm = false" class="btn btn-secondary">
            Cancel
          </button>
        </div>
      </form>
    </div>

    <!-- Requests List -->
    <div class="card overflow-hidden">
      <div v-if="attendanceStore.loading" class="p-8 text-center text-gray-500">
        Loading...
      </div>

      <div v-else-if="attendanceStore.myRequests?.data?.length" class="divide-y divide-gray-200 dark:divide-dark-border">
        <div 
          v-for="request in attendanceStore.myRequests.data" 
          :key="request.id"
          class="p-4"
        >
          <div class="flex items-start justify-between">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="font-medium text-gray-900 dark:text-white">
                  {{ request.check_type === 'IN' ? 'Check In' : 'Check Out' }} Request
                </span>
                <span :class="statusBadgeClass(request.status)" class="text-xs px-2 py-0.5 rounded-full">
                  {{ request.status }}
                </span>
              </div>
              <p class="text-sm text-gray-500">{{ formatDate(request.request_time) }} • {{ request.location?.name }}</p>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ request.reason }}</p>
              <p v-if="request.admin_note" class="text-sm text-primary mt-1">
                Note: {{ request.admin_note }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="p-8 text-center text-gray-500">
        No requests yet
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAttendanceStore } from '@/stores/attendance'

const attendanceStore = useAttendanceStore()

const startDate = ref('')
const endDate = ref('')

// Format date to YYYY-MM-DD without UTC conversion
function formatDateLocal(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

onMounted(() => {
  // Set default date range to current month
  const now = new Date()
  const firstDay = new Date(now.getFullYear(), now.getMonth(), 1)
  startDate.value = formatDateLocal(firstDay)
  endDate.value = formatDateLocal(now)
  
  fetchHistory()
})

function fetchHistory() {
  attendanceStore.fetchHistory({
    start_date: startDate.value,
    end_date: endDate.value,
    per_page: 50,
  })
}

function formatDate(iso) {
  const date = new Date(iso)
  return date.toLocaleDateString('en-GB', { 
    weekday: 'short', 
    day: 'numeric',
    month: 'short'
  })
}

function formatTime(iso) {
  const date = new Date(iso)
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${hours}:${minutes} WIB`
}

function statusBadgeClass(status) {
  const classes = {
    ON_TIME: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    LATE: 'bg-amber-100 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400',
    EARLY: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    ABSENT: 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400',
  }
  return classes[status] || 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
}
</script>

<template>
  <div class="space-y-6">
    <!-- Filters -->
    <div class="card p-4">
      <div class="flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
          <input v-model="startDate" type="date" class="input" />
        </div>
        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
          <input v-model="endDate" type="date" class="input" />
        </div>
        <button @click="fetchHistory" class="btn btn-primary">
          <span class="material-symbols-outlined text-sm">search</span>
          Search
        </button>
      </div>
    </div>

    <!-- History List -->
    <div class="card overflow-hidden">
      <div class="p-4 border-b border-gray-200 dark:border-dark-border">
        <h3 class="font-semibold text-gray-900 dark:text-white">Attendance History</h3>
      </div>

      <div v-if="attendanceStore.loading" class="p-8 text-center text-gray-500">
        Loading...
      </div>

      <div v-else-if="attendanceStore.history?.data?.length" class="divide-y divide-gray-200 dark:divide-dark-border">
        <div 
          v-for="record in attendanceStore.history.data" 
          :key="record.id"
          class="p-4 hover:bg-gray-50 dark:hover:bg-dark-border/50"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
              <div 
                class="w-10 h-10 rounded-full flex items-center justify-center"
                :class="record.check_type === 'IN' ? 'bg-green-100 dark:bg-green-900/20' : 'bg-blue-100 dark:bg-blue-900/20'"
              >
                <span 
                  class="material-symbols-outlined"
                  :class="record.check_type === 'IN' ? 'text-green-500' : 'text-blue-500'"
                >
                  {{ record.check_type === 'IN' ? 'login' : 'logout' }}
                </span>
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="font-medium text-gray-900 dark:text-white">
                    {{ record.check_type === 'IN' ? 'Check In' : 'Check Out' }}
                  </span>
                  <span 
                    class="text-xs px-2 py-0.5 rounded-full"
                    :class="statusBadgeClass(record.status)"
                  >
                    {{ record.status }}
                  </span>
                </div>
                <p class="text-sm text-gray-500">
                  {{ formatDate(record.scan_time) }} • {{ record.location?.name || 'Unknown' }}
                </p>
              </div>
            </div>
            <div class="text-right">
              <p class="font-semibold text-gray-900 dark:text-white">{{ formatTime(record.scan_time) }}</p>
              <p v-if="record.late_min > 0" class="text-sm text-amber-500">{{ record.late_min }}m late</p>
              <p v-if="record.work_minutes" class="text-sm text-gray-500">{{ Math.floor(record.work_minutes / 60) }}h {{ record.work_minutes % 60 }}m</p>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="p-8 text-center text-gray-500">
        No attendance records found
      </div>
    </div>
  </div>
</template>

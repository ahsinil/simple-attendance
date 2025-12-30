<script setup>
import { ref, onMounted } from 'vue'
import { useAttendanceStore } from '@/stores/attendance'

const attendanceStore = useAttendanceStore()

const startDate = ref('')
const endDate = ref('')

onMounted(() => {
  // Set default date range to current month
  const now = new Date()
  startDate.value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
  endDate.value = now.toISOString().split('T')[0]
  
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
  return new Date(iso).toLocaleDateString('en-US', { 
    weekday: 'short', 
    month: 'short', 
    day: 'numeric' 
  })
}

function formatTime(iso) {
  return new Date(iso).toLocaleTimeString('en-US', { 
    hour: '2-digit', 
    minute: '2-digit' 
  })
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

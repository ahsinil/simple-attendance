<script setup>
import { onMounted, computed } from 'vue'
import { useAttendanceStore } from '@/stores/attendance'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const attendanceStore = useAttendanceStore()

onMounted(() => {
  attendanceStore.fetchTodaySummary()
  attendanceStore.fetchMonthlySummary(new Date().getMonth() + 1, new Date().getFullYear())
})

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good morning'
  if (hour < 17) return 'Good afternoon'
  return 'Good evening'
})

const statusColor = computed(() => {
  const status = attendanceStore.todaySummary?.status
  if (status === 'ON_TIME') return 'text-green-500'
  if (status === 'LATE') return 'text-amber-500'
  if (status === 'ABSENT') return 'text-red-500'
  return 'text-gray-400'
})

function formatTime(iso) {
  if (!iso) return '-'
  return new Date(iso).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

function formatMinutes(min) {
  if (!min) return '0h 0m'
  const hours = Math.floor(min / 60)
  const minutes = min % 60
  return `${hours}h ${minutes}m`
}
</script>

<template>
  <div class="space-y-6">
    <!-- Welcome Card -->
    <div class="card p-6 bg-gradient-to-r from-primary to-primary-600 text-white">
      <p class="text-primary-100">{{ greeting }},</p>
      <h2 class="text-2xl font-bold">{{ authStore.user?.name }}</h2>
      <p class="text-sm text-primary-200 mt-1">{{ authStore.user?.position || 'Employee' }}</p>
    </div>

    <!-- Today's Status -->
    <div class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Today's Attendance</h3>
      
      <div v-if="attendanceStore.loading" class="text-center py-8 text-gray-500">
        Loading...
      </div>

      <div v-else-if="attendanceStore.todaySummary" class="space-y-4">
        <!-- Status Badge -->
        <div class="flex items-center gap-3">
          <div 
            class="w-12 h-12 rounded-full flex items-center justify-center"
            :class="attendanceStore.todaySummary.has_checked_in ? 'bg-green-100 dark:bg-green-900/20' : 'bg-gray-100 dark:bg-dark-border'"
          >
            <span 
              class="material-symbols-outlined"
              :class="attendanceStore.todaySummary.has_checked_in ? 'text-green-500' : 'text-gray-400'"
            >
              {{ attendanceStore.todaySummary.has_checked_in ? 'check_circle' : 'schedule' }}
            </span>
          </div>
          <div>
            <p class="font-semibold" :class="statusColor">
              {{ attendanceStore.todaySummary.status || 'Not Checked In' }}
            </p>
            <p class="text-sm text-gray-500">{{ attendanceStore.todaySummary.shift || 'No shift assigned' }}</p>
          </div>
        </div>

        <!-- Time Details -->
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-gray-50 dark:bg-dark-bg rounded-lg p-4">
            <p class="text-sm text-gray-500 mb-1">Check In</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">
              {{ formatTime(attendanceStore.todaySummary.check_in_time) }}
            </p>
          </div>
          <div class="bg-gray-50 dark:bg-dark-bg rounded-lg p-4">
            <p class="text-sm text-gray-500 mb-1">Check Out</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">
              {{ formatTime(attendanceStore.todaySummary.check_out_time) }}
            </p>
          </div>
        </div>

        <!-- Work Hours -->
        <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-dark-border">
          <span class="text-gray-500">Work Duration</span>
          <span class="font-semibold text-gray-900 dark:text-white">
            {{ formatMinutes(attendanceStore.todaySummary.work_minutes) }}
          </span>
        </div>

        <div v-if="attendanceStore.todaySummary.late_min > 0" class="flex items-center justify-between text-amber-600">
          <span>Late</span>
          <span class="font-semibold">{{ attendanceStore.todaySummary.late_min }} min</span>
        </div>
      </div>

      <div v-else class="text-center py-8 text-gray-500">
        No attendance data for today
      </div>
    </div>

    <!-- Monthly Summary -->
    <div class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">This Month</h3>

      <div v-if="attendanceStore.monthlySummary" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="text-center p-4">
          <p class="text-3xl font-bold text-green-500">{{ attendanceStore.monthlySummary.present_days }}</p>
          <p class="text-sm text-gray-500">Present</p>
        </div>
        <div class="text-center p-4">
          <p class="text-3xl font-bold text-red-500">{{ attendanceStore.monthlySummary.absent_days }}</p>
          <p class="text-sm text-gray-500">Absent</p>
        </div>
        <div class="text-center p-4">
          <p class="text-3xl font-bold text-amber-500">{{ attendanceStore.monthlySummary.late_days }}</p>
          <p class="text-sm text-gray-500">Late</p>
        </div>
        <div class="text-center p-4">
          <p class="text-3xl font-bold text-primary">{{ formatMinutes(attendanceStore.monthlySummary.total_work_minutes) }}</p>
          <p class="text-sm text-gray-500">Total Hours</p>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 gap-4">
      <RouterLink to="/attendance" class="card p-6 hover:border-primary transition-colors group">
        <span class="material-symbols-outlined text-3xl text-primary group-hover:scale-110 transition-transform">qr_code_scanner</span>
        <p class="mt-2 font-medium text-gray-900 dark:text-white">Scan Attendance</p>
        <p class="text-sm text-gray-500">Check in or out</p>
      </RouterLink>
      <RouterLink to="/requests" class="card p-6 hover:border-primary transition-colors group">
        <span class="material-symbols-outlined text-3xl text-primary group-hover:scale-110 transition-transform">edit_note</span>
        <p class="mt-2 font-medium text-gray-900 dark:text-white">Manual Request</p>
        <p class="text-sm text-gray-500">Submit correction</p>
      </RouterLink>
    </div>
  </div>
</template>

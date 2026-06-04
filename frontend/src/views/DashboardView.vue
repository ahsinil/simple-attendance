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
  if (hour < 12) return 'app.dashboardView.goodMorning'
  if (hour < 17) return 'app.dashboardView.goodAfternoon'
  return 'app.dashboardView.goodEvening'
})

const statusColor = computed(() => {
  const status = attendanceStore.todaySummary?.status
  if (status === 'ON_TIME') return 'text-green-500'
  if (status === 'LATE') return 'text-amber-500'
  if (status === 'ABSENT') return 'text-red-500'
  return 'text-gray-400'
})

// Get timezone abbreviation for Indonesian timezones
function getTimezoneAbbr(timezone) {
  const tzMap = {
    'Asia/Jakarta': 'WIB',
    'Asia/Makassar': 'WITA',
    'Asia/Jayapura': 'WIT',
    'Asia/Pontianak': 'WIB',
    'Asia/Ujung_Pandang': 'WITA'
  }
  return tzMap[timezone] || 'WIB'
}

function formatTime(iso) {
  if (!iso) return '-'
  const date = new Date(iso)
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const tz = getTimezoneAbbr(attendanceStore.todaySummary?.timezone || 'Asia/Jakarta')
  return `${hours}:${minutes} ${tz}`
}

// Calculate work duration from check-in and check-out times
const workDuration = computed(() => {
  const summary = attendanceStore.todaySummary
  if (!summary?.check_in_time || !summary?.check_out_time) {
    return '0h 0m'
  }
  
  const checkIn = new Date(summary.check_in_time)
  const checkOut = new Date(summary.check_out_time)
  const diffMs = checkOut - checkIn
  
  if (diffMs <= 0) return '0h 0m'
  
  const totalMinutes = Math.floor(diffMs / 60000)
  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60
  return `${hours}h ${minutes}m`
})

function formatMinutes(min) {
  if (!min) return '0h 0m'
  const totalMinutes = Math.round(min)
  const hours = Math.floor(Math.abs(totalMinutes) / 60)
  const minutes = Math.abs(totalMinutes) % 60
  const sign = totalMinutes < 0 ? '-' : ''
  return `${sign}${hours}h ${minutes}m`
}
</script>

<template>
  <div class="space-y-6">
    <!-- Welcome Card -->
    <div class="card p-6 bg-gradient-to-r from-primary to-primary-600 text-white">
      <p class="text-primary-100">{{ $t(greeting) }},</p>
      <h2 class="text-2xl font-bold">{{ authStore.user?.name }}</h2>
      <p class="text-sm text-primary-200 mt-1">{{ authStore.user?.position || $t('app.dashboardView.employee') }}</p>
    </div>

    <!-- Today's Status -->
    <div class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ $t('app.dashboardView.todaysAttendance') }}</h3>
      
      <div v-if="attendanceStore.loading" class="text-center py-8 text-gray-500">
        {{ $t('app.dashboardView.loading') }}
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
              {{ attendanceStore.todaySummary.status === 'ON_TIME' ? $t('admin.dashboardView.onTime') : attendanceStore.todaySummary.status === 'LATE' ? $t('app.dashboardView.late') : attendanceStore.todaySummary.status === 'ABSENT' ? $t('app.dashboardView.absent') : (attendanceStore.todaySummary.status || $t('app.dashboardView.notCheckedIn')) }}
            </p>
            <p class="text-sm text-gray-500">{{ attendanceStore.todaySummary.shift || $t('app.dashboardView.noShift') }}</p>
          </div>
        </div>

        <!-- Time Details -->
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-gray-50 dark:bg-dark-bg rounded-lg p-4">
            <p class="text-sm text-gray-500 mb-1">{{ $t('app.dashboardView.checkIn') }}</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">
              {{ formatTime(attendanceStore.todaySummary.check_in_time) }}
            </p>
          </div>
          <div class="bg-gray-50 dark:bg-dark-bg rounded-lg p-4">
            <p class="text-sm text-gray-500 mb-1">{{ $t('app.dashboardView.checkOut') }}</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">
              {{ formatTime(attendanceStore.todaySummary.check_out_time) }}
            </p>
          </div>
        </div>

        <!-- Work Hours -->
        <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-dark-border">
          <span class="text-gray-500">{{ $t('app.dashboardView.workDuration') }}</span>
          <span class="font-semibold text-gray-900 dark:text-white">
            {{ workDuration }}
          </span>
        </div>

        <div v-if="attendanceStore.todaySummary.late_min > 0" class="flex items-center justify-between text-amber-600">
          <span>{{ $t('app.dashboardView.late') }}</span>
          <span class="font-semibold">{{ attendanceStore.todaySummary.late_min }} {{ $t('app.dashboardView.min') }}</span>
        </div>
      </div>

      <div v-else class="text-center py-8 text-gray-500">
        {{ $t('app.dashboardView.noAttendanceData') }}
      </div>
    </div>

    <!-- Monthly Summary -->
    <div class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ $t('app.dashboardView.thisMonth') }}</h3>

      <div v-if="attendanceStore.monthlySummary" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="text-center p-4">
          <p class="text-3xl font-bold text-green-500">{{ attendanceStore.monthlySummary.present_days }}</p>
          <p class="text-sm text-gray-500">{{ $t('app.dashboardView.present') }}</p>
        </div>
        <div class="text-center p-4">
          <p class="text-3xl font-bold text-red-500">{{ attendanceStore.monthlySummary.absent_days }}</p>
          <p class="text-sm text-gray-500">{{ $t('app.dashboardView.absent') }}</p>
        </div>
        <div class="text-center p-4">
          <p class="text-3xl font-bold text-amber-500">{{ attendanceStore.monthlySummary.late_days }}</p>
          <p class="text-sm text-gray-500">{{ $t('app.dashboardView.late') }}</p>
        </div>
        <div class="text-center p-4">
          <p class="text-3xl font-bold text-primary">{{ formatMinutes(attendanceStore.monthlySummary.total_work_minutes) }}</p>
          <p class="text-sm text-gray-500">{{ $t('app.dashboardView.totalHours') }}</p>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 gap-4">
      <RouterLink to="/attendance" class="card p-6 hover:border-primary transition-colors group">
        <span class="material-symbols-outlined text-3xl text-primary group-hover:scale-110 transition-transform">qr_code_scanner</span>
        <p class="mt-2 font-medium text-gray-900 dark:text-white">{{ $t('app.dashboardView.scanAttendance') }}</p>
        <p class="text-sm text-gray-500">{{ $t('app.dashboardView.checkInOut') }}</p>
      </RouterLink>
      <RouterLink to="/requests" class="card p-6 hover:border-primary transition-colors group">
        <span class="material-symbols-outlined text-3xl text-primary group-hover:scale-110 transition-transform">edit_note</span>
        <p class="mt-2 font-medium text-gray-900 dark:text-white">{{ $t('app.dashboardView.manualRequest') }}</p>
        <p class="text-sm text-gray-500">{{ $t('app.dashboardView.submitCorrection') }}</p>
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '@/services/api'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const data = ref(null)
const loading = ref(true)
const processingId = ref(null)

onMounted(fetchDashboard)

async function fetchDashboard() {
  loading.value = true
  try {
    const response = await adminApi.getDashboard()
    data.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch dashboard:', error)
    toast.error('Failed to load dashboard data')
  }
  loading.value = false
}

// Quick approve/reject
async function quickApprove(requestId) {
  processingId.value = requestId
  try {
    await adminApi.approveRequest(requestId, {})
    toast.success('Request approved')
    fetchDashboard()
  } catch (error) {
    toast.error('Failed to approve request')
  }
  processingId.value = null
}

async function quickReject(requestId) {
  processingId.value = requestId
  try {
    await adminApi.rejectRequest(requestId, { admin_note: 'Rejected from dashboard' })
    toast.success('Request rejected')
    fetchDashboard()
  } catch (error) {
    toast.error('Failed to reject request')
  }
  processingId.value = null
}

// Format helpers
function formatTime(iso) {
  if (!iso) return '-'
  const date = new Date(iso)
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${hours}:${minutes}`
}

function formatDateTime(iso) {
  if (!iso) return '-'
  const date = new Date(iso)
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${day}/${month} ${hours}:${minutes}`
}

function formatMinutes(min) {
  if (!min) return '0h 0m'
  const hours = Math.floor(min / 60)
  const minutes = min % 60
  return `${hours}h ${minutes}m`
}

function timeAgo(iso) {
  if (!iso) return ''
  const date = new Date(iso)
  const now = new Date()
  const diffMs = now - date
  const diffMin = Math.floor(diffMs / 60000)
  
  if (diffMin < 1) return 'Just now'
  if (diffMin < 60) return `${diffMin}m ago`
  const diffHours = Math.floor(diffMin / 60)
  if (diffHours < 24) return `${diffHours}h ago`
  const diffDays = Math.floor(diffHours / 24)
  return `${diffDays}d ago`
}

// Chart data for attendance rate
const chartData = computed(() => {
  if (!data.value?.monthly?.chart_data) return []
  return data.value.monthly.chart_data
})

const maxChartValue = computed(() => {
  if (!chartData.value.length) return 10
  return Math.max(...chartData.value.map(d => d.present), 10)
})
</script>

<template>
  <div class="space-y-6">
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin w-8 h-8 border-4 border-primary border-t-transparent rounded-full"></div>
    </div>

    <template v-else-if="data">
      <!-- Real-time Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">Present Today</p>
              <p class="text-3xl font-bold text-green-500">{{ data.realtime.present_today }}</p>
              <p class="text-xs text-gray-400">of {{ data.realtime.total_employees }} employees</p>
            </div>
            <div class="w-12 h-12 bg-green-100 dark:bg-green-900/20 rounded-full flex items-center justify-center">
              <span class="material-symbols-outlined text-green-500">groups</span>
            </div>
          </div>
        </div>

        <div class="card p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">Late Today</p>
              <p class="text-3xl font-bold text-amber-500">{{ data.realtime.late_today }}</p>
              <p class="text-xs text-gray-400">avg {{ data.realtime.avg_late_minutes }}min late</p>
            </div>
            <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/20 rounded-full flex items-center justify-center">
              <span class="material-symbols-outlined text-amber-500">schedule</span>
            </div>
          </div>
        </div>

        <div class="card p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">On Time</p>
              <p class="text-3xl font-bold text-blue-500">{{ data.realtime.on_time_today }}</p>
              <p class="text-xs text-gray-400">{{ data.realtime.attendance_rate }}% rate</p>
            </div>
            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/20 rounded-full flex items-center justify-center">
              <span class="material-symbols-outlined text-blue-500">verified</span>
            </div>
          </div>
        </div>

        <div class="card p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">Not Checked In</p>
              <p class="text-3xl font-bold text-gray-500">{{ data.realtime.not_checked_in }}</p>
              <p class="text-xs text-gray-400">pending</p>
            </div>
            <div class="w-12 h-12 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center">
              <span class="material-symbols-outlined text-gray-500">person_off</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Attendance Rate Chart -->
        <div class="card p-6 lg:col-span-2">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Attendance This Month</h3>
          <div class="h-48 flex items-end gap-1" v-if="chartData.length">
            <div 
              v-for="day in chartData" 
              :key="day.date"
              class="flex-1 flex flex-col items-center gap-1"
            >
              <div class="w-full flex flex-col gap-0.5">
                <div 
                  class="w-full bg-green-400 rounded-t"
                  :style="{ height: `${(day.on_time / maxChartValue) * 160}px` }"
                  :title="`On Time: ${day.on_time}`"
                ></div>
                <div 
                  class="w-full bg-amber-400 rounded-b"
                  :style="{ height: `${(day.late / maxChartValue) * 160}px` }"
                  :title="`Late: ${day.late}`"
                ></div>
              </div>
              <span class="text-[10px] text-gray-400">{{ day.date.split('-')[2] }}</span>
            </div>
          </div>
          <div v-else class="h-48 flex items-center justify-center text-gray-500">
            No data available
          </div>
          <div class="flex items-center gap-4 mt-4 text-xs text-gray-500">
            <span class="flex items-center gap-1">
              <span class="w-3 h-3 bg-green-400 rounded"></span> On Time
            </span>
            <span class="flex items-center gap-1">
              <span class="w-3 h-3 bg-amber-400 rounded"></span> Late
            </span>
          </div>
        </div>

        <!-- Late vs On-Time Ratio -->
        <div class="card p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Late vs On-Time</h3>
          <div class="flex items-center justify-center h-32">
            <div class="relative w-32 h-32">
              <svg class="w-32 h-32 transform -rotate-90" viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                <circle 
                  cx="18" cy="18" r="15.9" fill="none" 
                  stroke="#22c55e" stroke-width="3"
                  :stroke-dasharray="`${100 - (data.monthly.late_vs_ontime.late_percentage || 0)} 100`"
                  stroke-linecap="round"
                />
              </svg>
              <div class="absolute inset-0 flex items-center justify-center flex-col">
                <span class="text-2xl font-bold text-gray-900 dark:text-white">
                  {{ 100 - (data.monthly.late_vs_ontime.late_percentage || 0) }}%
                </span>
                <span class="text-xs text-gray-500">On Time</span>
              </div>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4 mt-4 text-center">
            <div>
              <p class="text-2xl font-bold text-green-500">{{ data.monthly.late_vs_ontime.on_time }}</p>
              <p class="text-xs text-gray-500">On Time</p>
            </div>
            <div>
              <p class="text-2xl font-bold text-amber-500">{{ data.monthly.late_vs_ontime.late }}</p>
              <p class="text-xs text-gray-500">Late</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Average Work Duration -->
      <div class="card p-6">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Average Work Duration</h3>
            <p class="text-sm text-gray-500">This month</p>
          </div>
          <div class="text-right">
            <p class="text-3xl font-bold text-primary">{{ data.monthly.avg_work_duration.hours }}h</p>
            <p class="text-sm text-gray-500">{{ formatMinutes(data.monthly.avg_work_duration.minutes) }}</p>
          </div>
        </div>
      </div>

      <!-- Employee Insights & Pending Requests -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Employee Insights -->
        <div class="space-y-4">
          <!-- Top Late Employees -->
          <div class="card p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
              <span class="material-symbols-outlined text-amber-500">trending_down</span>
              Top Late Employees
            </h3>
            <div v-if="data.employee_insights.top_late.length" class="space-y-3">
              <div v-for="(emp, idx) in data.employee_insights.top_late" :key="emp.user?.id" class="flex items-center gap-3">
                <span class="w-6 h-6 rounded-full bg-amber-100 dark:bg-amber-900/20 text-amber-600 text-xs flex items-center justify-center font-bold">
                  {{ idx + 1 }}
                </span>
                <div class="flex-1">
                  <p class="font-medium text-gray-900 dark:text-white">{{ emp.user?.name }}</p>
                  <p class="text-xs text-gray-500">{{ emp.user?.employee_id }}</p>
                </div>
                <div class="text-right">
                  <p class="font-semibold text-amber-500">{{ emp.late_count }}x late</p>
                  <p class="text-xs text-gray-400">{{ emp.total_late_minutes }}min total</p>
                </div>
              </div>
            </div>
            <p v-else class="text-gray-500 text-sm">No late employees this month 🎉</p>
          </div>

          <!-- Perfect Attendance -->
          <div class="card p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
              <span class="material-symbols-outlined text-green-500">emoji_events</span>
              Perfect Attendance
            </h3>
            <div v-if="data.employee_insights.perfect_attendance.length" class="space-y-3">
              <div v-for="emp in data.employee_insights.perfect_attendance" :key="emp.user?.id" class="flex items-center gap-3">
                <span class="w-6 h-6 rounded-full bg-green-100 dark:bg-green-900/20 text-green-600 flex items-center justify-center">
                  <span class="material-symbols-outlined text-sm">star</span>
                </span>
                <div class="flex-1">
                  <p class="font-medium text-gray-900 dark:text-white">{{ emp.user?.name }}</p>
                  <p class="text-xs text-gray-500">{{ emp.user?.employee_id }}</p>
                </div>
                <span class="text-sm text-green-500 font-medium">{{ emp.days_present }} days</span>
              </div>
            </div>
            <p v-else class="text-gray-500 text-sm">No perfect attendance yet</p>
          </div>

          <!-- Missing Checkout -->
          <div class="card p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
              <span class="material-symbols-outlined text-red-500">logout</span>
              Missing Checkout Today
            </h3>
            <div v-if="data.employee_insights.missing_checkout.length" class="space-y-2">
              <div v-for="emp in data.employee_insights.missing_checkout" :key="emp.id" class="flex items-center gap-2 text-sm">
                <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                <span class="text-gray-900 dark:text-white">{{ emp.name }}</span>
                <span class="text-gray-400">{{ emp.employee_id }}</span>
              </div>
            </div>
            <p v-else class="text-gray-500 text-sm">All checked out ✓</p>
          </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-4">
          <!-- Pending Requests -->
          <div class="card p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center justify-between">
              <span class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">pending_actions</span>
                Pending Requests
              </span>
              <RouterLink to="/admin/requests" class="text-sm text-primary hover:underline">View all</RouterLink>
            </h3>
            <div v-if="data.pending_requests.length" class="space-y-3">
              <div v-for="req in data.pending_requests" :key="req.id" class="p-3 bg-gray-50 dark:bg-dark-border rounded-lg">
                <div class="flex items-start justify-between gap-2 mb-2">
                  <div>
                    <p class="font-medium text-gray-900 dark:text-white">{{ req.user?.name }}</p>
                    <p class="text-xs text-gray-500">{{ req.check_type }} • {{ req.location }}</p>
                  </div>
                  <span class="text-xs text-gray-400">{{ timeAgo(req.created_at) }}</span>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2 line-clamp-2">{{ req.reason }}</p>
                <div class="flex gap-2">
                  <button 
                    @click="quickApprove(req.id)"
                    :disabled="processingId === req.id"
                    class="flex-1 px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                  >
                    Approve
                  </button>
                  <button 
                    @click="quickReject(req.id)"
                    :disabled="processingId === req.id"
                    class="flex-1 px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                  >
                    Reject
                  </button>
                </div>
              </div>
            </div>
            <p v-else class="text-gray-500 text-sm text-center py-4">No pending requests</p>
          </div>

          <!-- Recent Activity -->
          <div class="card p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
              <span class="material-symbols-outlined text-blue-500">history</span>
              Recent Activity
            </h3>
            <div v-if="data.recent_activity.length" class="space-y-3 max-h-80 overflow-y-auto">
              <div v-for="activity in data.recent_activity" :key="activity.id" class="flex items-center gap-3">
                <div 
                  class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                  :class="activity.check_type === 'IN' ? 'bg-green-100 dark:bg-green-900/20' : 'bg-blue-100 dark:bg-blue-900/20'"
                >
                  <span 
                    class="material-symbols-outlined text-sm"
                    :class="activity.check_type === 'IN' ? 'text-green-500' : 'text-blue-500'"
                  >
                    {{ activity.check_type === 'IN' ? 'login' : 'logout' }}
                  </span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-gray-900 dark:text-white truncate">{{ activity.user?.name }}</p>
                  <p class="text-xs text-gray-500">{{ activity.location }}</p>
                </div>
                <div class="text-right flex-shrink-0">
                  <p class="text-sm font-medium text-gray-900 dark:text-white">{{ formatTime(activity.scan_time) }}</p>
                  <p 
                    class="text-xs"
                    :class="activity.status === 'LATE' ? 'text-amber-500' : 'text-green-500'"
                  >
                    {{ activity.status }}
                  </p>
                </div>
              </div>
            </div>
            <p v-else class="text-gray-500 text-sm text-center py-4">No recent activity</p>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

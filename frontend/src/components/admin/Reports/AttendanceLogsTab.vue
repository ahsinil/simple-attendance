<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'
import { toDateInputValue, toStartOfMonthInputValue } from '@/utils/date'

// Define exactly what this component needs to emit context upwards
const emit = defineEmits(['export-start', 'export-end'])

const loading = ref(false)
const exporting = ref(false)

const attendances = ref([])
const summary = ref({
  present_count: 0,
  late_count: 0,
  manual_overrides: 0,
  avg_late_minutes: 0,
  unique_employees: 0,
  total_records: 0,
})
const locations = ref([])
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
})

const filters = ref({
  start_date: toStartOfMonthInputValue(),
  end_date: toDateInputValue(),
  location_id: '',
  status: '',
})

onMounted(() => {
  fetchLocations()
  fetchData()
})

async function fetchLocations() {
  try {
    const response = await adminApi.getReportsLocations()
    if (response.data.success) {
      locations.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load locations', error)
  }
}

async function fetchData() {
  loading.value = true
  try {
    const params = { ...filters.value, page: pagination.value.current_page }
    
    const [reportsRes, summaryRes] = await Promise.all([
      adminApi.getReports(params),
      adminApi.getReportsSummary(params),
    ])

    if (reportsRes.data.success) {
      attendances.value = reportsRes.data.data.data
      pagination.value = {
        current_page: reportsRes.data.data.current_page,
        last_page: reportsRes.data.data.last_page,
        per_page: reportsRes.data.data.per_page,
        total: reportsRes.data.data.total,
      }
    }

    if (summaryRes.data.success) {
      summary.value = summaryRes.data.data
    }
  } catch (error) {
    console.error('Failed to load reports', error)
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  pagination.value.current_page = 1
  fetchData()
}

function resetFilters() {
  filters.value = {
    start_date: toStartOfMonthInputValue(),
    end_date: toDateInputValue(),
    location_id: '',
    status: '',
  }
  applyFilters()
}

function changePage(page) {
  if (page >= 1 && page <= pagination.value.last_page) {
    pagination.value.current_page = page
    fetchData()
  }
}

async function exportToExcel() {
  exporting.value = true
  emit('export-start')
  try {
    const response = await adminApi.exportReports(filters.value)
    downloadBlob(response.data, `attendance_report_${filters.value.start_date}_to_${filters.value.end_date}.xlsx`)
  } catch (error) {
    console.error('Export failed', error)
  } finally {
    exporting.value = false
    emit('export-end')
  }
}

// Expose export excel to parent so parent views can trigger it from their own fixed headers
defineExpose({
  exportToExcel,
  exporting
})

// ==================== HELPERS ====================
function downloadBlob(data, filename) {
  const url = window.URL.createObjectURL(new Blob([data]))
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', filename)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  })
}

function formatTime(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleTimeString('en-US', { 
    hour: '2-digit', 
    minute: '2-digit' 
  })
}

function getStatusClass(status) {
  const classes = {
    'ON_TIME': 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
    'LATE': 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
    'EARLY': 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
    'ABSENT': 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
    'EXCUSED': 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400',
  }
  return classes[status] || 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-400'
}

import { useToast } from '@/composables/useToast'
const toast = useToast()

async function toggleAllowancePaid(att) {
  try {
    const res = await adminApi.toggleAllowancePaid(att.id)
    if (res.data.success) {
      att.variable_allowance_paid = res.data.data.variable_allowance_paid
      toast.success(att.variable_allowance_paid ? 'Ditandai sudah dicairkan' : 'Status pencairan dibatalkan')
    }
  } catch (error) {
    toast.error('Gagal memperbarui status tunjangan')
    console.error(error)
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Filters -->
    <div class="bg-white dark:bg-dark-surface rounded-xl shadow-sm border border-gray-100 dark:border-dark-border p-5">
      <div class="flex flex-col gap-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 tracking-wider">{{ $t('admin.payrollView.startDate') }}</span>
            <input
              v-model="filters.start_date"
              type="date"
              class="w-full bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none text-sm"
            />
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 tracking-wider">{{ $t('admin.payrollView.endDate') }}</span>
            <input
              v-model="filters.end_date"
              type="date"
              class="w-full bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none text-sm"
            />
          </label>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <select
            v-model="filters.location_id"
            class="w-full px-4 py-2.5 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg text-gray-700 dark:text-gray-300 text-sm font-medium"
          >
            <option value="">{{ $t('admin.reportsView.allLocations') }}</option>
            <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
          </select>

          <select
            v-model="filters.status"
            class="w-full px-4 py-2.5 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg text-gray-700 dark:text-gray-300 text-sm font-medium"
          >
            <option value="">{{ $t('admin.reportsView.allStatuses') }}</option>
            <option value="ON_TIME">On Time</option>
            <option value="LATE">Late</option>
            <option value="EARLY">Early</option>
            <option value="ABSENT">Absent</option>
            <option value="EXCUSED">Excused</option>
          </select>
        </div>

        <div class="flex items-center gap-3 justify-end">
          <button @click="resetFilters" class="text-sm font-medium text-gray-500 hover:text-primary transition-colors px-3">{{ $t('admin.payrollView.reset') }}</button>
          <button @click="applyFilters" class="bg-gray-900 dark:bg-white text-white dark:text-gray-900 px-5 py-2.5 rounded-lg text-sm font-bold hover:opacity-90 transition-opacity">
            {{ $t('admin.payrollView.applyFilters') }}
          </button>
        </div>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">{{ $t('admin.reportsView.present') }}</span>
          <span class="p-1.5 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-md">
            <span class="material-symbols-outlined text-lg">check_circle</span>
          </span>
        </div>
        <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ summary.present_count }}</span>
        <span class="text-xs text-gray-400">{{ $t('admin.reportsView.uniqueEmployees', { count: summary.unique_employees }) }}</span>
      </div>

      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">{{ $t('admin.reportsView.lateCheckins') }}</span>
          <span class="p-1.5 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-md">
            <span class="material-symbols-outlined text-lg">schedule</span>
          </span>
        </div>
        <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ summary.late_count }}</span>
        <span class="text-xs text-gray-400">{{ $t('admin.reportsView.avgDelay', { mins: summary.avg_late_minutes }) }}</span>
      </div>

      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">{{ $t('admin.reportsView.manualOverrides') }}</span>
          <span class="p-1.5 bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-md">
            <span class="material-symbols-outlined text-lg">edit_note</span>
          </span>
        </div>
        <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ summary.manual_overrides }}</span>
        <span class="text-xs text-gray-400">{{ $t('admin.reportsView.adminApproved') }}</span>
      </div>

      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">{{ $t('admin.reportsView.totalRecords') }}</span>
          <span class="p-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-md">
            <span class="material-symbols-outlined text-lg">analytics</span>
          </span>
        </div>
        <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ summary.total_records }}</span>
        <span class="text-xs text-gray-400">{{ $t('admin.reportsView.inSelectedPeriod') }}</span>
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white dark:bg-dark-surface rounded-xl border border-gray-100 dark:border-dark-border shadow-sm overflow-hidden">
      <div class="p-5 border-b border-gray-100 dark:border-dark-border flex items-center justify-between">
        <h3 class="font-bold text-gray-900 dark:text-white">{{ $t('admin.reportsView.detailedLogs') }}</h3>
      </div>

      <div v-if="loading" class="flex justify-center py-12">
        <span class="material-symbols-outlined animate-spin text-4xl text-gray-400">progress_activity</span>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 dark:bg-dark-bg text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">
              <th class="px-6 py-4">{{ $t('admin.dashboardView.employee') }}</th>
              <th class="px-6 py-4">{{ $t('admin.reportsView.dateTime') }}</th>
              <th class="px-6 py-4">{{ $t('app.myRequestsView.location') }}</th>
              <th class="px-6 py-4">{{ $t('admin.reportsView.checkType') }}</th>
              <th class="px-6 py-4">{{ $t('app.schedulesView.status') }}</th>
              <th class="px-6 py-4">{{ $t('admin.reportsView.method') }}</th>
              <th class="px-6 py-4 text-center">{{ $t('admin.reportsView.allowance') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-dark-border text-sm">
            <tr v-if="attendances.length === 0">
              <td colspan="6" class="px-6 py-12 text-center text-gray-500">{{ $t('admin.reportsView.noLogsData') }}</td>
            </tr>
            <tr v-for="att in attendances" :key="att.id" class="group hover:bg-gray-50 dark:hover:bg-dark-bg transition-colors">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="size-9 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold">
                    {{ att.user?.name?.charAt(0) || '?' }}
                  </div>
                  <div>
                    <p class="font-bold text-gray-900 dark:text-white">{{ att.user?.name || 'Unknown' }}</p>
                    <p class="text-xs text-gray-500">ID: {{ att.user?.employee_id || 'N/A' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-col">
                  <span class="text-gray-900 dark:text-white font-medium">{{ formatDate(att.scan_time) }}</span>
                  <span class="text-gray-500 text-xs">{{ formatTime(att.scan_time) }}</span>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-300">
                  <span class="material-symbols-outlined text-[18px] text-green-500">location_on</span>
                  <span>{{ att.location?.name || 'Unknown' }}</span>
                </div>
              </td>
              <td class="px-6 py-4">
                <span :class="att.check_type === 'IN' ? 'text-green-600' : 'text-red-600'" class="font-medium">
                  {{ att.check_type }}
                </span>
              </td>
              <td class="px-6 py-4">
                <span :class="getStatusClass(att.status)" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium">
                  {{ att.status?.replace('_', ' ') }}
                  <template v-if="att.status === 'LATE' && att.late_min"> ({{ att.late_min }}m)</template>
                </span>
              </td>
              <td class="px-6 py-4">
                <span class="text-xs text-gray-500 uppercase">{{ att.method }}</span>
              </td>
              <td class="px-6 py-4 text-center">
                <button
                  v-if="att.check_type === 'IN'"
                  @click="toggleAllowancePaid(att)"
                  class="p-1.5 rounded-lg transition-colors border text-xs font-medium inline-flex items-center gap-1"
                  :class="att.variable_allowance_paid 
                    ? 'bg-amber-50 dark:bg-amber-900/30 border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/50' 
                    : 'bg-white dark:bg-dark-surface border-gray-200 dark:border-dark-line text-gray-500 hover:bg-gray-50 dark:hover:bg-dark-bg'"
                  :title="att.variable_allowance_paid ? 'Tunjangan sudah dicairkan. Klik untuk membatalkan.' : 'Tandai tunjangan sudah dicairkan untuk hari ini.'"
                >
                  <span class="material-symbols-outlined text-[16px]">
                    {{ att.variable_allowance_paid ? 'payments' : 'money_off' }}
                  </span>
                  <span>{{ att.variable_allowance_paid ? $t('admin.reportsView.paid') : $t('admin.reportsView.claim') }}</span>
                </button>
                <span v-else class="text-xs text-gray-400">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 border-t border-gray-100 dark:border-dark-border flex items-center justify-between">
        <span class="text-sm text-gray-500 dark:text-gray-400">
          {{ $t('admin.reportsView.showingRecords', { count: attendances.length, total: pagination.total }) }}
        </span>
        <div class="flex gap-2">
          <button
            @click="changePage(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1"
            class="px-3 py-1 text-sm border border-gray-200 dark:border-dark-line rounded hover:bg-gray-50 dark:hover:bg-dark-bg disabled:opacity-50 text-gray-600 dark:text-gray-300"
          >
            {{ $t('admin.reportsView.previous') }}
          </button>
          <button
            @click="changePage(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page"
            class="px-3 py-1 text-sm border border-gray-200 dark:border-dark-line rounded hover:bg-gray-50 dark:hover:bg-dark-bg disabled:opacity-50 text-gray-600 dark:text-gray-300"
          >
            {{ $t('admin.reportsView.next') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

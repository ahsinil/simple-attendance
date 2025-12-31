<script setup>
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '@/services/api'

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

// Filters
const filters = ref({
  start_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
  end_date: new Date().toISOString().split('T')[0],
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
    start_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    end_date: new Date().toISOString().split('T')[0],
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
  try {
    const response = await adminApi.exportReports(filters.value)
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `attendance_report_${filters.value.start_date}_to_${filters.value.end_date}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Export failed', error)
  } finally {
    exporting.value = false
  }
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
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Attendance Reports</h1>
        <p class="text-gray-500 dark:text-gray-400">View and export detailed attendance data.</p>
      </div>
      <button
        @click="exportToExcel"
        :disabled="exporting"
        class="flex items-center gap-2 bg-primary hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg shadow-md transition-all font-semibold text-sm disabled:opacity-50"
      >
        <span v-if="exporting" class="material-symbols-outlined animate-spin text-sm">progress_activity</span>
        <span v-else class="material-symbols-outlined text-sm">download</span>
        {{ exporting ? 'Exporting...' : 'Export Report' }}
      </button>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-dark-surface rounded-xl shadow-sm border border-gray-100 dark:border-dark-border p-5">
      <div class="flex flex-col xl:flex-row gap-5 items-start xl:items-end">
        <!-- Date Range -->
        <div class="flex gap-4 w-full xl:w-auto flex-1 min-w-[300px]">
          <label class="flex flex-col gap-1.5 flex-1">
            <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 tracking-wider">Start Date</span>
            <input
              v-model="filters.start_date"
              type="date"
              class="w-full bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none text-sm"
            />
          </label>
          <label class="flex flex-col gap-1.5 flex-1">
            <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 tracking-wider">End Date</span>
            <input
              v-model="filters.end_date"
              type="date"
              class="w-full bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none text-sm"
            />
          </label>
        </div>

        <!-- Dropdowns -->
        <div class="flex flex-wrap gap-3 flex-[2]">
          <select
            v-model="filters.location_id"
            class="px-4 py-2.5 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg text-gray-700 dark:text-gray-300 text-sm font-medium"
          >
            <option value="">All Locations</option>
            <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
          </select>

          <select
            v-model="filters.status"
            class="px-4 py-2.5 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg text-gray-700 dark:text-gray-300 text-sm font-medium"
          >
            <option value="">All Statuses</option>
            <option value="ON_TIME">On Time</option>
            <option value="LATE">Late</option>
            <option value="EARLY">Early</option>
            <option value="ABSENT">Absent</option>
            <option value="EXCUSED">Excused</option>
          </select>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3 w-full xl:w-auto justify-end">
          <button @click="resetFilters" class="text-sm font-medium text-gray-500 hover:text-primary transition-colors px-3">Reset</button>
          <button @click="applyFilters" class="bg-gray-900 dark:bg-white text-white dark:text-gray-900 px-5 py-2.5 rounded-lg text-sm font-bold hover:opacity-90 transition-opacity">
            Apply Filters
          </button>
        </div>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Present</span>
          <span class="p-1.5 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-md">
            <span class="material-symbols-outlined text-lg">check_circle</span>
          </span>
        </div>
        <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ summary.present_count }}</span>
        <span class="text-xs text-gray-400">{{ summary.unique_employees }} unique employees</span>
      </div>

      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Late Check-ins</span>
          <span class="p-1.5 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-md">
            <span class="material-symbols-outlined text-lg">schedule</span>
          </span>
        </div>
        <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ summary.late_count }}</span>
        <span class="text-xs text-gray-400">Avg delay: {{ summary.avg_late_minutes }} mins</span>
      </div>

      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Manual Overrides</span>
          <span class="p-1.5 bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-md">
            <span class="material-symbols-outlined text-lg">edit_note</span>
          </span>
        </div>
        <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ summary.manual_overrides }}</span>
        <span class="text-xs text-gray-400">Admin approved</span>
      </div>

      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Total Records</span>
          <span class="p-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-md">
            <span class="material-symbols-outlined text-lg">analytics</span>
          </span>
        </div>
        <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ summary.total_records }}</span>
        <span class="text-xs text-gray-400">In selected period</span>
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white dark:bg-dark-surface rounded-xl border border-gray-100 dark:border-dark-border shadow-sm overflow-hidden">
      <div class="p-5 border-b border-gray-100 dark:border-dark-border flex items-center justify-between">
        <h3 class="font-bold text-gray-900 dark:text-white">Detailed Attendance Logs</h3>
      </div>

      <div v-if="loading" class="flex justify-center py-12">
        <span class="material-symbols-outlined animate-spin text-4xl text-gray-400">progress_activity</span>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 dark:bg-dark-bg text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">
              <th class="px-6 py-4">Employee</th>
              <th class="px-6 py-4">Date & Time</th>
              <th class="px-6 py-4">Location</th>
              <th class="px-6 py-4">Check Type</th>
              <th class="px-6 py-4">Status</th>
              <th class="px-6 py-4">Method</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-dark-border text-sm">
            <tr v-if="attendances.length === 0">
              <td colspan="6" class="px-6 py-12 text-center text-gray-500">No attendance records found for the selected criteria.</td>
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
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 border-t border-gray-100 dark:border-dark-border flex items-center justify-between">
        <span class="text-sm text-gray-500 dark:text-gray-400">
          Showing {{ attendances.length }} of {{ pagination.total }} records
        </span>
        <div class="flex gap-2">
          <button
            @click="changePage(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1"
            class="px-3 py-1 text-sm border border-gray-200 dark:border-dark-line rounded hover:bg-gray-50 dark:hover:bg-dark-bg disabled:opacity-50 text-gray-600 dark:text-gray-300"
          >
            Previous
          </button>
          <button
            @click="changePage(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page"
            class="px-3 py-1 text-sm border border-gray-200 dark:border-dark-line rounded hover:bg-gray-50 dark:hover:bg-dark-bg disabled:opacity-50 text-gray-600 dark:text-gray-300"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

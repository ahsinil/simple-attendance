<script setup>
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '@/services/api'

// Tab management
const activeTab = ref('logs') // 'logs' | 'employee'

const loading = ref(false)
const exporting = ref(false)

// ==================== ATTENDANCE LOGS TAB ====================
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
  start_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
  end_date: new Date().toISOString().split('T')[0],
  location_id: '',
  status: '',
})

// ==================== EMPLOYEE REPORT TAB ====================
const empLoading = ref(false)
const empExporting = ref(false)
const employees = ref([])
const employeeList = ref([])
const departments = ref([])
const empKpis = ref({
  total_employees: 0,
  total_late: 0,
  total_overtime_hours: 0,
  avg_attendance_rate: 0,
})

const empFilters = ref({
  start_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
  end_date: new Date().toISOString().split('T')[0],
  location_id: '',
  user_id: '',
  department: '',
})

// ==================== LIFECYCLE ====================
onMounted(() => {
  fetchLocations()
  fetchData()
})

function switchTab(tab) {
  activeTab.value = tab
  if (tab === 'employee' && employees.value.length === 0) {
    fetchDropdowns()
    fetchEmployeeReport()
  }
}

// ==================== ATTENDANCE LOGS METHODS ====================
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
    downloadBlob(response.data, `attendance_report_${filters.value.start_date}_to_${filters.value.end_date}.xlsx`)
  } catch (error) {
    console.error('Export failed', error)
  } finally {
    exporting.value = false
  }
}

// ==================== EMPLOYEE REPORT METHODS ====================
async function fetchDropdowns() {
  try {
    const [deptRes, empRes] = await Promise.all([
      adminApi.getReportsDepartments(),
      adminApi.getReportsEmployees(),
    ])
    if (deptRes.data.success) departments.value = deptRes.data.data
    if (empRes.data.success) employeeList.value = empRes.data.data
  } catch (error) {
    console.error('Failed to load dropdowns', error)
  }
}

async function fetchEmployeeReport() {
  empLoading.value = true
  try {
    const response = await adminApi.getEmployeeReport(empFilters.value)
    if (response.data.success) {
      employees.value = response.data.data.employees
      empKpis.value = response.data.data.kpis
    }
  } catch (error) {
    console.error('Failed to load employee report', error)
  } finally {
    empLoading.value = false
  }
}

function applyEmpFilters() {
  fetchEmployeeReport()
}

function resetEmpFilters() {
  empFilters.value = {
    start_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    end_date: new Date().toISOString().split('T')[0],
    location_id: '',
    user_id: '',
    department: '',
  }
  fetchEmployeeReport()
}

async function exportEmployeeReport() {
  empExporting.value = true
  try {
    const response = await adminApi.exportEmployeeReport(empFilters.value)
    downloadBlob(response.data, `employee_report_${empFilters.value.start_date}_to_${empFilters.value.end_date}.xlsx`)
  } catch (error) {
    console.error('Export failed', error)
  } finally {
    empExporting.value = false
  }
}

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
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Attendance Reports</h1>
        <p class="text-gray-500 dark:text-gray-400">View and export detailed attendance data.</p>
      </div>
      <!-- Export button (context-aware) -->
      <button
        v-if="activeTab === 'logs'"
        @click="exportToExcel"
        :disabled="exporting"
        class="flex items-center gap-2 bg-primary hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg shadow-md transition-all font-semibold text-sm disabled:opacity-50"
      >
        <span v-if="exporting" class="material-symbols-outlined animate-spin text-sm">progress_activity</span>
        <span v-else class="material-symbols-outlined text-sm">download</span>
        {{ exporting ? 'Exporting...' : 'Export Report' }}
      </button>
      <button
        v-else
        @click="exportEmployeeReport"
        :disabled="empExporting"
        class="flex items-center gap-2 bg-primary hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg shadow-md transition-all font-semibold text-sm disabled:opacity-50"
      >
        <span v-if="empExporting" class="material-symbols-outlined animate-spin text-sm">progress_activity</span>
        <span v-else class="material-symbols-outlined text-sm">download</span>
        {{ empExporting ? 'Exporting...' : 'Export Employee Report' }}
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 bg-gray-100 dark:bg-dark-bg p-1 rounded-xl w-fit">
      <button
        @click="switchTab('logs')"
        :class="activeTab === 'logs'
          ? 'bg-white dark:bg-dark-surface text-gray-900 dark:text-white shadow-sm'
          : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
        class="px-4 py-2 rounded-lg text-sm font-semibold transition-all"
      >
        <span class="material-symbols-outlined text-[18px] align-middle mr-1">list_alt</span>
        Attendance Logs
      </button>
      <button
        @click="switchTab('employee')"
        :class="activeTab === 'employee'
          ? 'bg-white dark:bg-dark-surface text-gray-900 dark:text-white shadow-sm'
          : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
        class="px-4 py-2 rounded-lg text-sm font-semibold transition-all"
      >
        <span class="material-symbols-outlined text-[18px] align-middle mr-1">person_search</span>
        By Employee
      </button>
    </div>

    <!-- ==================== ATTENDANCE LOGS TAB ==================== -->
    <template v-if="activeTab === 'logs'">
      <!-- Filters -->
      <div class="bg-white dark:bg-dark-surface rounded-xl shadow-sm border border-gray-100 dark:border-dark-border p-5">
        <div class="flex flex-col gap-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label class="flex flex-col gap-1.5">
              <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 tracking-wider">Start Date</span>
              <input
                v-model="filters.start_date"
                type="date"
                class="w-full bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none text-sm"
              />
            </label>
            <label class="flex flex-col gap-1.5">
              <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 tracking-wider">End Date</span>
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
              <option value="">All Locations</option>
              <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
            </select>

            <select
              v-model="filters.status"
              class="w-full px-4 py-2.5 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg text-gray-700 dark:text-gray-300 text-sm font-medium"
            >
              <option value="">All Statuses</option>
              <option value="ON_TIME">On Time</option>
              <option value="LATE">Late</option>
              <option value="EARLY">Early</option>
              <option value="ABSENT">Absent</option>
              <option value="EXCUSED">Excused</option>
            </select>
          </div>

          <div class="flex items-center gap-3 justify-end">
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
    </template>

    <!-- ==================== BY EMPLOYEE TAB ==================== -->
    <template v-if="activeTab === 'employee'">
      <!-- Filters -->
      <div class="bg-white dark:bg-dark-surface rounded-xl shadow-sm border border-gray-100 dark:border-dark-border p-5">
        <div class="flex flex-col gap-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label class="flex flex-col gap-1.5">
              <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 tracking-wider">Start Date</span>
              <input
                v-model="empFilters.start_date"
                type="date"
                class="w-full bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none text-sm"
              />
            </label>
            <label class="flex flex-col gap-1.5">
              <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 tracking-wider">End Date</span>
              <input
                v-model="empFilters.end_date"
                type="date"
                class="w-full bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none text-sm"
              />
            </label>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <select
              v-model="empFilters.location_id"
              class="w-full px-4 py-2.5 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg text-gray-700 dark:text-gray-300 text-sm font-medium"
            >
              <option value="">All Locations</option>
              <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
            </select>

            <select
              v-model="empFilters.department"
              class="w-full px-4 py-2.5 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg text-gray-700 dark:text-gray-300 text-sm font-medium"
            >
              <option value="">All Departments</option>
              <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
            </select>

            <select
              v-model="empFilters.user_id"
              class="w-full px-4 py-2.5 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg text-gray-700 dark:text-gray-300 text-sm font-medium"
            >
              <option value="">All Employees</option>
              <option v-for="emp in employeeList" :key="emp.id" :value="emp.id">
                {{ emp.name }} ({{ emp.employee_id || emp.id }})
              </option>
            </select>
          </div>

          <div class="flex items-center gap-3 justify-end">
            <button @click="resetEmpFilters" class="text-sm font-medium text-gray-500 hover:text-primary transition-colors px-3">Reset</button>
            <button @click="applyEmpFilters" class="bg-gray-900 dark:bg-white text-white dark:text-gray-900 px-5 py-2.5 rounded-lg text-sm font-bold hover:opacity-90 transition-opacity">
              Apply Filters
            </button>
          </div>
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Employees</span>
            <span class="p-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-md">
              <span class="material-symbols-outlined text-lg">groups</span>
            </span>
          </div>
          <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ empKpis.total_employees }}</span>
          <span class="text-xs text-gray-400">With attendance data</span>
        </div>

        <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Avg Attendance</span>
            <span class="p-1.5 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-md">
              <span class="material-symbols-outlined text-lg">trending_up</span>
            </span>
          </div>
          <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ empKpis.avg_attendance_rate }}%</span>
          <span class="text-xs text-gray-400">Average attendance rate</span>
        </div>

        <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Total Late</span>
            <span class="p-1.5 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-md">
              <span class="material-symbols-outlined text-lg">schedule</span>
            </span>
          </div>
          <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ empKpis.total_late }}</span>
          <span class="text-xs text-gray-400">Total late check-ins</span>
        </div>

        <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Overtime Hours</span>
            <span class="p-1.5 bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-md">
              <span class="material-symbols-outlined text-lg">more_time</span>
            </span>
          </div>
          <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ empKpis.total_overtime_hours }}h</span>
          <span class="text-xs text-gray-400">Combined OT hours</span>
        </div>
      </div>

      <!-- Employee Data Table -->
      <div class="bg-white dark:bg-dark-surface rounded-xl border border-gray-100 dark:border-dark-border shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-100 dark:border-dark-border">
          <h3 class="font-bold text-gray-900 dark:text-white">Employee Attendance Summary</h3>
        </div>

        <div v-if="empLoading" class="flex justify-center py-12">
          <span class="material-symbols-outlined animate-spin text-4xl text-gray-400">progress_activity</span>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-gray-50 dark:bg-dark-bg text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">
                <th class="px-5 py-4">Employee</th>
                <th class="px-5 py-4">Department</th>
                <th class="px-5 py-4 text-center">Days Present</th>
                <th class="px-5 py-4 text-center">On Time</th>
                <th class="px-5 py-4 text-center">Late</th>
                <th class="px-5 py-4 text-center">Absent</th>
                <th class="px-5 py-4 text-center">Leave</th>
                <th class="px-5 py-4 text-center">Work Hours</th>
                <th class="px-5 py-4 text-center">OT Hours</th>
                <th class="px-5 py-4 text-center">Rate</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-dark-border text-sm">
              <tr v-if="employees.length === 0">
                <td colspan="10" class="px-6 py-12 text-center text-gray-500">No employee data found for the selected criteria.</td>
              </tr>
              <tr v-for="emp in employees" :key="emp.user_id" class="group hover:bg-gray-50 dark:hover:bg-dark-bg transition-colors">
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div class="size-9 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-sm">
                      {{ emp.name?.charAt(0) || '?' }}
                    </div>
                    <div>
                      <p class="font-bold text-gray-900 dark:text-white">{{ emp.name }}</p>
                      <p class="text-xs text-gray-500">{{ emp.employee_id || '-' }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                  <span>{{ emp.department || '-' }}</span>
                  <p v-if="emp.position" class="text-xs text-gray-400">{{ emp.position }}</p>
                </td>
                <td class="px-5 py-4 text-center font-semibold text-gray-900 dark:text-white">{{ emp.days_present }}</td>
                <td class="px-5 py-4 text-center">
                  <span class="text-green-600 dark:text-green-400 font-medium">{{ emp.on_time_count }}</span>
                </td>
                <td class="px-5 py-4 text-center">
                  <div class="flex flex-col items-center">
                    <span class="text-amber-600 dark:text-amber-400 font-medium">{{ emp.late_count }}</span>
                    <span v-if="emp.total_late_minutes > 0" class="text-xs text-gray-400">{{ emp.total_late_minutes }}m</span>
                  </div>
                </td>
                <td class="px-5 py-4 text-center">
                  <span :class="emp.absent_days > 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-400'">{{ emp.absent_days }}</span>
                </td>
                <td class="px-5 py-4 text-center">
                  <span :class="emp.leave_days > 0 ? 'text-blue-600 dark:text-blue-400 font-medium' : 'text-gray-400'">{{ emp.leave_days }}</span>
                </td>
                <td class="px-5 py-4 text-center text-gray-900 dark:text-white font-medium">{{ emp.total_work_hours }}h</td>
                <td class="px-5 py-4 text-center">
                  <span :class="emp.overtime_hours > 0 ? 'text-purple-600 dark:text-purple-400 font-medium' : 'text-gray-400'">{{ emp.overtime_hours }}h</span>
                </td>
                <td class="px-5 py-4 text-center">
                  <span
                    :class="[
                      'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium',
                      emp.attendance_rate >= 90 ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' :
                      emp.attendance_rate >= 70 ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' :
                      'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
                    ]"
                  >
                    {{ emp.attendance_rate }}%
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

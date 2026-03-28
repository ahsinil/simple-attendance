<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'
import { toDateInputValue, toStartOfMonthInputValue } from '@/utils/date'

// Define exactly what this component needs to emit context upwards
const emit = defineEmits(['export-start', 'export-end'])

const loading = ref(false)
const exporting = ref(false)

const employees = ref([])
const employeeList = ref([])
const departments = ref([])
const locations = ref([])
const kpis = ref({
  total_employees: 0,
  total_late: 0,
  total_overtime_hours: 0,
  avg_attendance_rate: 0,
})

const filters = ref({
  start_date: toStartOfMonthInputValue(),
  end_date: toDateInputValue(),
  location_id: '',
  user_id: '',
  department: '',
})

onMounted(() => {
  fetchDropdowns()
  fetchData()
})

async function fetchDropdowns() {
  try {
    const [deptRes, empRes, locRes] = await Promise.all([
      adminApi.getReportsDepartments(),
      adminApi.getReportsEmployees(),
      adminApi.getReportsLocations()
    ])
    if (deptRes.data.success) departments.value = deptRes.data.data
    if (empRes.data.success) employeeList.value = empRes.data.data
    if (locRes.data.success) locations.value = locRes.data.data
  } catch (error) {
    console.error('Failed to load dropdowns', error)
  }
}

async function fetchData() {
  loading.value = true
  try {
    const response = await adminApi.getEmployeeReport(filters.value)
    if (response.data.success) {
      employees.value = response.data.data.employees
      kpis.value = response.data.data.kpis
    }
  } catch (error) {
    console.error('Failed to load employee report', error)
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  fetchData()
}

function resetFilters() {
  filters.value = {
    start_date: toStartOfMonthInputValue(),
    end_date: toDateInputValue(),
    location_id: '',
    user_id: '',
    department: '',
  }
  fetchData()
}

async function exportToExcel() {
  exporting.value = true
  emit('export-start')
  try {
    const response = await adminApi.exportEmployeeReport(filters.value)
    downloadBlob(response.data, `employee_report_${filters.value.start_date}_to_${filters.value.end_date}.xlsx`)
  } catch (error) {
    console.error('Export failed', error)
  } finally {
    exporting.value = false
    emit('export-end')
  }
}

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
</script>

<template>
  <div class="space-y-6">
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

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <select
            v-model="filters.location_id"
            class="w-full px-4 py-2.5 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg text-gray-700 dark:text-gray-300 text-sm font-medium"
          >
            <option value="">All Locations</option>
            <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
          </select>

          <select
            v-model="filters.department"
            class="w-full px-4 py-2.5 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg text-gray-700 dark:text-gray-300 text-sm font-medium"
          >
            <option value="">All Departments</option>
            <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
          </select>

          <select
            v-model="filters.user_id"
            class="w-full px-4 py-2.5 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg text-gray-700 dark:text-gray-300 text-sm font-medium"
          >
            <option value="">All Employees</option>
            <option v-for="emp in employeeList" :key="emp.id" :value="emp.id">
              {{ emp.name }} ({{ emp.employee_id || emp.id }})
            </option>
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
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Employees</span>
          <span class="p-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-md">
            <span class="material-symbols-outlined text-lg">groups</span>
          </span>
        </div>
        <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ kpis.total_employees }}</span>
        <span class="text-xs text-gray-400">With attendance data</span>
      </div>

      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Avg Attendance</span>
          <span class="p-1.5 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-md">
            <span class="material-symbols-outlined text-lg">trending_up</span>
          </span>
        </div>
        <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ kpis.avg_attendance_rate }}%</span>
        <span class="text-xs text-gray-400">Average attendance rate</span>
      </div>

      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Total Late</span>
          <span class="p-1.5 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-md">
            <span class="material-symbols-outlined text-lg">schedule</span>
          </span>
        </div>
        <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ kpis.total_late }}</span>
        <span class="text-xs text-gray-400">Total late check-ins</span>
      </div>

      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Overtime Hours</span>
          <span class="p-1.5 bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-md">
            <span class="material-symbols-outlined text-lg">more_time</span>
          </span>
        </div>
        <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ kpis.total_overtime_hours }}h</span>
        <span class="text-xs text-gray-400">Combined OT hours</span>
      </div>
    </div>

    <!-- Employee Data Table -->
    <div class="bg-white dark:bg-dark-surface rounded-xl border border-gray-100 dark:border-dark-border shadow-sm overflow-hidden">
      <div class="p-5 border-b border-gray-100 dark:border-dark-border">
        <h3 class="font-bold text-gray-900 dark:text-white">Employee Attendance Summary</h3>
      </div>

      <div v-if="loading" class="flex justify-center py-12">
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
  </div>
</template>

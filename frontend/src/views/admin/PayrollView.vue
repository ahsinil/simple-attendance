<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'
import { useToast } from '@/composables/useToast'
import { toDateInputValue, toStartOfMonthInputValue } from '@/utils/date'

const toast = useToast()

const loading = ref(false)
const exporting = ref(false)
const payrollData = ref([])
const kpis = ref({
  total_employees: 0,
  total_overtime_hours: 0,
  total_overtime_pay: 0,
  total_variable_deductions: 0,
  total_estimated_payroll: 0,
  avg_overtime_per_employee: 0,
})
const departments = ref([])

// Filters
const filters = ref({
  start_date: toStartOfMonthInputValue(),
  end_date: toDateInputValue(),
  department: '',
})

onMounted(() => {
  fetchDepartments()
  fetchData()
})

async function fetchDepartments() {
  try {
    const response = await adminApi.getPayrollDepartments()
    if (response.data.success) {
      departments.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load departments', error)
  }
}

async function fetchData() {
  loading.value = true
  try {
    const params = { ...filters.value }
    const response = await adminApi.getPayrollSummary(params)
    if (response.data.success) {
      payrollData.value = response.data.data.summary
      kpis.value = response.data.data.kpis
    }
  } catch (error) {
    console.error('Failed to load payroll', error)
    toast.error('Failed to load payroll data')
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
    department: '',
  }
  fetchData()
}

async function exportToExcel() {
  exporting.value = true
  try {
    const response = await adminApi.exportPayroll(filters.value)
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `payroll_summary_${filters.value.start_date}_to_${filters.value.end_date}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
    toast.success('Payroll exported successfully')
  } catch (error) {
    console.error('Export failed', error)
    toast.error('Export failed')
  } finally {
    exporting.value = false
  }
}

function formatCurrency(amount) {
  if (amount == null || amount === 0) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount)
}

function formatNumber(num, decimals = 1) {
  if (num == null || num === 0) return '0'
  return Number(num).toFixed(decimals)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Payroll Summary</h1>
        <p class="text-gray-500 dark:text-gray-400">Overtime tracking, salary allowances, and estimated payroll.</p>
      </div>
      <button
        @click="exportToExcel"
        :disabled="exporting || payrollData.length === 0"
        class="flex items-center gap-2 bg-primary hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg shadow-md transition-all font-semibold text-sm disabled:opacity-50"
      >
        <span v-if="exporting" class="material-symbols-outlined animate-spin text-sm">progress_activity</span>
        <span v-else class="material-symbols-outlined text-sm">download</span>
        {{ exporting ? 'Exporting...' : 'Export Excel' }}
      </button>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-dark-surface rounded-xl shadow-sm border border-gray-100 dark:border-dark-border p-5">
      <div class="flex flex-col gap-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
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
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 tracking-wider">Department</span>
            <select
              v-model="filters.department"
              class="w-full px-3 py-2.5 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg text-gray-700 dark:text-gray-300 text-sm font-medium"
            >
              <option value="">All Departments</option>
              <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
            </select>
          </label>
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
            <span class="material-symbols-outlined text-lg">group</span>
          </span>
        </div>
        <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ kpis.total_employees }}</span>
        <span class="text-xs text-gray-400">With payroll data</span>
      </div>

      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Total OT Hours</span>
          <span class="p-1.5 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-md">
            <span class="material-symbols-outlined text-lg">schedule</span>
          </span>
        </div>
        <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatNumber(kpis.total_overtime_hours) }}</span>
        <span class="text-xs text-gray-400">Avg {{ formatNumber(kpis.avg_overtime_per_employee) }}h / employee</span>
      </div>

      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Overtime Pay</span>
          <span class="p-1.5 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-md">
            <span class="material-symbols-outlined text-lg">payments</span>
          </span>
        </div>
        <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(kpis.total_overtime_pay) }}</span>
        <span class="text-xs text-gray-400">Estimated</span>
      </div>

      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">Est. Total Payroll</span>
          <span class="p-1.5 bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-md">
            <span class="material-symbols-outlined text-lg">account_balance</span>
          </span>
        </div>
        <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(kpis.total_estimated_payroll) }}</span>
        <span class="text-xs text-gray-400">Deductions: {{ formatCurrency(kpis.total_variable_deductions) }}</span>
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white dark:bg-dark-surface rounded-xl border border-gray-100 dark:border-dark-border shadow-sm overflow-hidden">
      <div class="p-5 border-b border-gray-100 dark:border-dark-border">
        <h3 class="font-bold text-gray-900 dark:text-white">Per-Employee Payroll Breakdown</h3>
      </div>

      <div v-if="loading" class="flex justify-center py-12">
        <span class="material-symbols-outlined animate-spin text-4xl text-gray-400">progress_activity</span>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 dark:bg-dark-bg text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">
              <th class="px-4 py-3 sticky left-0 bg-gray-50 dark:bg-dark-bg z-10">Employee</th>
              <th class="px-4 py-3 text-center">Days</th>
              <th class="px-4 py-3 text-right">Regular Hrs</th>
              <th class="px-4 py-3 text-right">OT Hrs</th>
              <th class="px-4 py-3 text-right">Base Salary</th>
              <th class="px-4 py-3 text-right">Fixed Allow.</th>
              <th class="px-4 py-3 text-right" title="Dibayar hanya untuk hari hadir">Var. Allow. <span class="text-gray-400 font-normal">(Hadir)</span></th>
              <th class="px-4 py-3 text-right">Total Salary</th>
              <th class="px-4 py-3 text-right">OT Pay</th>
              <th class="px-4 py-3 text-right">Deductions</th>
              <th class="px-4 py-3 text-right">Est. Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-dark-border text-sm">
            <tr v-if="payrollData.length === 0">
              <td colspan="11" class="px-4 py-12 text-center text-gray-500">No payroll data found for the selected period.</td>
            </tr>
            <tr v-for="row in payrollData" :key="row.user_id" class="group hover:bg-gray-50 dark:hover:bg-dark-bg transition-colors">
              <td class="px-4 py-3 sticky left-0 bg-white dark:bg-dark-surface group-hover:bg-gray-50 dark:group-hover:bg-dark-bg z-10">
                <div class="flex items-center gap-3">
                  <div class="size-8 rounded-full bg-primary/10 flex items-center justify-center text-primary text-xs font-bold flex-shrink-0">
                    {{ row.name?.charAt(0) || '?' }}
                  </div>
                  <div class="min-w-0">
                    <p class="font-bold text-gray-900 dark:text-white truncate">{{ row.name }}</p>
                    <p class="text-xs text-gray-500">{{ row.department || '-' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex flex-col items-center">
                  <span class="font-medium text-gray-900 dark:text-white">{{ row.work_days_present }}</span>
                  <span v-if="row.absent_days > 0" class="text-xs text-red-500">{{ row.absent_days }} absent</span>
                  <span v-if="row.leave_days > 0" class="text-xs text-blue-500">{{ row.leave_days }} leave</span>
                </div>
              </td>
              <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">{{ formatNumber(row.regular_hours) }}</td>
              <td class="px-4 py-3 text-right">
                <div class="flex flex-col items-end">
                  <span class="font-bold" :class="row.overtime_hours > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500'">
                    {{ formatNumber(row.overtime_hours) }}
                  </span>
                  <span v-if="row.holiday_ot_hours > 0" class="text-xs text-red-500">{{ formatNumber(row.holiday_ot_hours) }} hol</span>
                  <span v-if="row.weekend_ot_hours > 0" class="text-xs text-orange-500">{{ formatNumber(row.weekend_ot_hours) }} wknd</span>
                </div>
              </td>
              <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(row.base_salary) }}</td>
              <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(row.fixed_allowances) }}</td>
              <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                <div class="flex flex-col items-end">
                  <span>{{ formatCurrency(row.variable_allowances) }}</span>
                  <span v-if="row.variable_allowances_full > row.variable_allowances" class="text-xs text-gray-400">
                    dari {{ formatCurrency(row.variable_allowances_full) }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(row.base_salary + row.fixed_allowances + row.variable_allowances) }}</td>
              <td class="px-4 py-3 text-right font-medium text-green-600 dark:text-green-400">{{ formatCurrency(row.overtime_pay) }}</td>
              <td class="px-4 py-3 text-right font-medium text-red-600 dark:text-red-400">
                <div class="flex flex-col items-end">
                  <span>{{ row.variable_deduction > 0 ? '-' + formatCurrency(row.variable_deduction) : '-' }}</span>
                  <span v-if="row.variable_deduction > 0" class="text-xs text-gray-400">{{ row.absent_days + row.leave_days }} hari tidak hadir</span>
                </div>
              </td>
              <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ formatCurrency(row.estimated_total) }}</td>
            </tr>
          </tbody>
          <!-- Totals Row -->
          <tfoot v-if="payrollData.length > 0">
            <tr class="bg-gray-50 dark:bg-dark-bg font-bold text-sm">
              <td class="px-4 py-3 sticky left-0 bg-gray-50 dark:bg-dark-bg z-10 text-gray-900 dark:text-white">TOTAL</td>
              <td class="px-4 py-3"></td>
              <td class="px-4 py-3"></td>
              <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">{{ formatNumber(kpis.total_overtime_hours) }}</td>
              <td class="px-4 py-3"></td>
              <td class="px-4 py-3"></td>
              <td class="px-4 py-3"></td>
              <td class="px-4 py-3"></td>
              <td class="px-4 py-3 text-right text-green-600 dark:text-green-400">{{ formatCurrency(kpis.total_overtime_pay) }}</td>
              <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">-{{ formatCurrency(kpis.total_variable_deductions) }}</td>
              <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ formatCurrency(kpis.total_estimated_payroll) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>

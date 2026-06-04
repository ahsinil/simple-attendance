<script setup>
import { ref } from 'vue'

// Import our new split components
import AttendanceLogsTab from '@/components/admin/Reports/AttendanceLogsTab.vue'
import EmployeeReportTab from '@/components/admin/Reports/EmployeeReportTab.vue'

// Tab management
const activeTab = ref('logs') // 'logs' | 'employee'

// Refs to access child component methods (specifically export)
const logsTabRef = ref(null)
const empTabRef = ref(null)

// Local state for export button loading spinners
const isExportingLogs = ref(false)
const isExportingEmp = ref(false)

function switchTab(tab) {
  activeTab.value = tab
}

// Trigger exports on the active child component
function triggerExport() {
  if (activeTab.value === 'logs' && logsTabRef.value) {
    logsTabRef.value.exportToExcel()
  } else if (activeTab.value === 'employee' && empTabRef.value) {
    empTabRef.value.exportToExcel()
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('admin.reportsView.title') }}</h1>
        <p class="text-gray-500 dark:text-gray-400">{{ $t('admin.reportsView.subtitle') }}</p>
      </div>
      <!-- Export button (context-aware) -->
      <button
        v-if="activeTab === 'logs'"
        @click="triggerExport"
        :disabled="isExportingLogs"
        class="flex items-center gap-2 bg-primary hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg shadow-md transition-all font-semibold text-sm disabled:opacity-50"
      >
        <span v-if="isExportingLogs" class="material-symbols-outlined animate-spin text-sm">progress_activity</span>
        <span v-else class="material-symbols-outlined text-sm">download</span>
        {{ isExportingLogs ? $t('admin.reportsView.exporting') : $t('admin.reportsView.exportReport') }}
      </button>
      <button
        v-else
        @click="triggerExport"
        :disabled="isExportingEmp"
        class="flex items-center gap-2 bg-primary hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg shadow-md transition-all font-semibold text-sm disabled:opacity-50"
      >
        <span v-if="isExportingEmp" class="material-symbols-outlined animate-spin text-sm">progress_activity</span>
        <span v-else class="material-symbols-outlined text-sm">download</span>
        {{ isExportingEmp ? $t('admin.reportsView.exporting') : $t('admin.reportsView.exportEmployeeReport') }}
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
        {{ $t('admin.reportsView.attendanceLogs') }}
      </button>
      <button
        @click="switchTab('employee')"
        :class="activeTab === 'employee'
          ? 'bg-white dark:bg-dark-surface text-gray-900 dark:text-white shadow-sm'
          : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
        class="px-4 py-2 rounded-lg text-sm font-semibold transition-all"
      >
        <span class="material-symbols-outlined text-[18px] align-middle mr-1">person_search</span>
        {{ $t('admin.reportsView.byEmployee') }}
      </button>
    </div>

    <!-- Tab Contents (Mounted conditionally) -->
    <AttendanceLogsTab 
      v-if="activeTab === 'logs'" 
      ref="logsTabRef"
      @export-start="isExportingLogs = true"
      @export-end="isExportingLogs = false"
    />
    
    <EmployeeReportTab 
      v-if="activeTab === 'employee'" 
      ref="empTabRef"
      @export-start="isExportingEmp = true"
      @export-end="isExportingEmp = false"
    />
  </div>
</template>

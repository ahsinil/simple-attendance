<script setup>
import { ref, computed, onMounted } from 'vue'
import { adminApi } from '@/services/api'

const loading = ref(false)
const devices = ref([])
const stats = ref(null)
const pagination = ref({})
const filters = ref({
  status: 'all',
  user_id: '',
})

const message = ref({ type: '', text: '' })
const actionLoading = ref(null)

onMounted(() => {
  fetchStats()
  fetchDevices()
})

async function fetchStats() {
  try {
    const response = await adminApi.getDeviceStats()
    if (response.data.success) {
      stats.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load device stats:', error)
  }
}

async function fetchDevices() {
  loading.value = true
  try {
    const response = await adminApi.getDevices({
      status: filters.value.status,
      user_id: filters.value.user_id || undefined,
    })
    if (response.data.success) {
      devices.value = response.data.data.data || []
      pagination.value = response.data.data
    }
  } catch (error) {
    showMessage('error', 'Failed to load devices')
  } finally {
    loading.value = false
  }
}

async function approveDevice(device) {
  actionLoading.value = device.id
  try {
    const response = await adminApi.approveDevice(device.id)
    if (response.data.success) {
      showMessage('success', 'Device approved successfully')
      fetchDevices()
      fetchStats()
    }
  } catch (error) {
    showMessage('error', error.response?.data?.error || 'Failed to approve device')
  } finally {
    actionLoading.value = null
  }
}

async function rejectDevice(device) {
  if (!confirm('Are you sure you want to reject and remove this device?')) return
  
  actionLoading.value = device.id
  try {
    const response = await adminApi.rejectDevice(device.id)
    if (response.data.success) {
      showMessage('success', 'Device rejected and removed')
      fetchDevices()
      fetchStats()
    }
  } catch (error) {
    showMessage('error', error.response?.data?.error || 'Failed to reject device')
  } finally {
    actionLoading.value = null
  }
}

async function revokeDevice(device) {
  if (!confirm(`Are you sure you want to revoke access for "${device.device_name}"?`)) return
  
  actionLoading.value = device.id
  try {
    const response = await adminApi.revokeDevice(device.id)
    if (response.data.success) {
      showMessage('success', 'Device access revoked')
      fetchDevices()
      fetchStats()
    }
  } catch (error) {
    showMessage('error', error.response?.data?.error || 'Failed to revoke device')
  } finally {
    actionLoading.value = null
  }
}

function showMessage(type, text) {
  message.value = { type, text }
  setTimeout(() => {
    message.value = { type: '', text: '' }
  }, 3000)
}

function getStatusBadge(device) {
  return device.is_approved
    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
    : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
}

function getPlatformIcon(platform) {
  const icons = {
    'Windows': 'desktop_windows',
    'macOS': 'laptop_mac',
    'Linux': 'computer',
    'iOS': 'phone_iphone',
    'Android': 'phone_android',
  }
  return icons[platform] || 'devices'
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Device Management</h1>
        <p class="text-sm text-gray-500 mt-1">Manage registered devices for employee attendance</p>
      </div>
    </div>

    <!-- Notification -->
    <div v-if="message.text" :class="`p-4 rounded-lg flex items-center gap-2 ${message.type === 'success' ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'}`">
      <span class="material-symbols-outlined">{{ message.type === 'success' ? 'check_circle' : 'error' }}</span>
      {{ message.text }}
    </div>

    <!-- Stats Cards -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white dark:bg-dark-surface p-4 rounded-xl border border-gray-100 dark:border-dark-border">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-blue-50 dark:bg-blue-900/20">
            <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">devices</span>
          </div>
          <div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</p>
            <p class="text-sm text-gray-500">Total Devices</p>
          </div>
        </div>
      </div>
      
      <div class="bg-white dark:bg-dark-surface p-4 rounded-xl border border-gray-100 dark:border-dark-border">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
            <span class="material-symbols-outlined text-yellow-600 dark:text-yellow-400">pending</span>
          </div>
          <div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.pending }}</p>
            <p class="text-sm text-gray-500">Pending Approval</p>
          </div>
        </div>
      </div>
      
      <div class="bg-white dark:bg-dark-surface p-4 rounded-xl border border-gray-100 dark:border-dark-border">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-green-50 dark:bg-green-900/20">
            <span class="material-symbols-outlined text-green-600 dark:text-green-400">verified</span>
          </div>
          <div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.approved }}</p>
            <p class="text-sm text-gray-500">Approved Devices</p>
          </div>
        </div>
      </div>
      
      <div class="bg-white dark:bg-dark-surface p-4 rounded-xl border border-gray-100 dark:border-dark-border">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-purple-50 dark:bg-purple-900/20">
            <span class="material-symbols-outlined text-purple-600 dark:text-purple-400">group</span>
          </div>
          <div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.users_with_devices }}</p>
            <p class="text-sm text-gray-500">Users with Devices</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Settings Info -->
    <div v-if="stats?.settings" class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg flex items-start gap-3">
      <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">info</span>
      <div class="text-sm text-blue-700 dark:text-blue-300">
        <p class="font-medium">Device Registration: {{ stats.settings.device_registration_enabled ? 'Enabled' : 'Disabled' }}</p>
        <p v-if="stats.settings.device_registration_enabled">
          Mode: <span class="font-medium">{{ stats.settings.device_registration_mode === 'require_approval' ? 'Require Approval' : 'Auto-Approve' }}</span>
          • Max devices: <span class="font-medium">{{ stats.settings.max_devices_per_user }}</span> per user
        </p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex gap-4 items-center">
      <select 
        v-model="filters.status"
        @change="fetchDevices"
        class="rounded-lg border-gray-300 dark:border-dark-line bg-white dark:bg-dark-surface text-gray-900 dark:text-white focus:ring-primary focus:border-primary px-4 py-2"
      >
        <option value="all">All Devices</option>
        <option value="pending">Pending Approval</option>
        <option value="approved">Approved</option>
      </select>
      
      <button 
        @click="fetchDevices" 
        class="flex items-center gap-2 px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-primary"
      >
        <span class="material-symbols-outlined text-sm">refresh</span>
        Refresh
      </button>
    </div>

    <!-- Loading Spinner -->
    <div v-if="loading" class="flex justify-center py-12">
      <span class="material-symbols-outlined animate-spin text-4xl text-gray-400">progress_activity</span>
    </div>

    <!-- Devices Table -->
    <div v-else-if="devices.length > 0" class="bg-white dark:bg-dark-surface rounded-xl border border-gray-100 dark:border-dark-border overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-dark-bg">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Used</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
          <tr v-for="device in devices" :key="device.id" class="hover:bg-gray-50 dark:hover:bg-dark-bg/50">
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-gray-100 dark:bg-dark-bg">
                  <span class="material-symbols-outlined text-gray-500">{{ getPlatformIcon(device.device_info?.platform) }}</span>
                </div>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">{{ device.device_name || 'Unknown Device' }}</p>
                  <p class="text-sm text-gray-500">{{ device.device_info?.platform || 'Unknown' }} • {{ device.device_info?.browser || 'Unknown' }}</p>
                </div>
              </div>
            </td>
            <td class="px-6 py-4">
              <div>
                <p class="font-medium text-gray-900 dark:text-white">{{ device.user?.name }}</p>
                <p class="text-sm text-gray-500">{{ device.user?.email }}</p>
              </div>
            </td>
            <td class="px-6 py-4">
              <span :class="`px-2 py-1 rounded-full text-xs font-medium ${getStatusBadge(device)}`">
                {{ device.is_approved ? 'Approved' : 'Pending' }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-500">
              {{ device.last_used_at || 'Never' }}
            </td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <template v-if="!device.is_approved">
                  <button
                    @click="approveDevice(device)"
                    :disabled="actionLoading === device.id"
                    class="p-2 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg transition-colors disabled:opacity-50"
                    title="Approve Device"
                  >
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                  </button>
                  <button
                    @click="rejectDevice(device)"
                    :disabled="actionLoading === device.id"
                    class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors disabled:opacity-50"
                    title="Reject Device"
                  >
                    <span class="material-symbols-outlined text-sm">cancel</span>
                  </button>
                </template>
                <button
                  v-else
                  @click="revokeDevice(device)"
                  :disabled="actionLoading === device.id"
                  class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors disabled:opacity-50"
                  title="Revoke Access"
                >
                  <span class="material-symbols-outlined text-sm">block</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <div v-else class="bg-white dark:bg-dark-surface rounded-xl border border-gray-100 dark:border-dark-border p-12 text-center">
      <span class="material-symbols-outlined text-6xl text-gray-300 dark:text-gray-600">devices</span>
      <p class="mt-4 text-gray-500">No devices found</p>
      <p class="text-sm text-gray-400">Devices will appear here when employees register them for attendance</p>
    </div>
  </div>
</template>

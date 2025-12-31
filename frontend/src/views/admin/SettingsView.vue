<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'

const loading = ref(false)
const saving = ref(false)
const settings = ref({
  company_name: '',
  contact_email: '',
  timezone: 'UTC',
  attendance_radius: '100',
  require_photo: 'false',
  work_days: 'Mon,Tue,Wed,Thu,Fri',
  ip_whitelist_enabled: 'false',
  ip_whitelist: '',
})

const message = ref({ type: '', text: '' })

onMounted(() => {
  fetchSettings()
})

async function fetchSettings() {
  loading.value = true
  try {
    const response = await adminApi.getSettings()
    if (response.data.success) {
      // Merge with default keys to ensure reactivity
      settings.value = { ...settings.value, ...response.data.data }
    }
  } catch (error) {
    showMessage('error', 'Failed to load settings')
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  try {
    const response = await adminApi.updateSettings({ settings: settings.value })
    if (response.data.success) {
      showMessage('success', 'Settings updated successfully')
    }
  } catch (error) {
    showMessage('error', 'Failed to save settings')
  } finally {
    saving.value = false
  }
}

function showMessage(type, text) {
  message.value = { type, text }
  setTimeout(() => {
    message.value = { type: '', text: '' }
  }, 3000)
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">System Settings</h1>
      <button
        @click="saveSettings"
        :disabled="saving"
        class="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-600 disabled:opacity-50"
      >
        <span v-if="saving" class="material-symbols-outlined animate-spin text-sm">progress_activity</span>
        <span v-else class="material-symbols-outlined text-sm">save</span>
        {{ saving ? 'Saving...' : 'Save Changes' }}
      </button>
    </div>

    <!-- Notification -->
    <div v-if="message.text" :class="`p-4 rounded-lg flex items-center gap-2 ${message.type === 'success' ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'}`">
      <span class="material-symbols-outlined">{{ message.type === 'success' ? 'check_circle' : 'error' }}</span>
      {{ message.text }}
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <span class="material-symbols-outlined animate-spin text-4xl text-gray-400">progress_activity</span>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      
      <!-- General Settings -->
      <div class="bg-white dark:bg-dark-surface p-6 rounded-xl shadow-sm border border-gray-100 dark:border-dark-border space-y-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
          <span class="material-symbols-outlined text-gray-500">domain</span>
          General Information
        </h2>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company Name</label>
          <input v-model="settings.company_name" type="text" class="w-full rounded-lg border-gray-300 dark:border-dark-line bg-white dark:bg-dark-bg text-gray-900 dark:text-white focus:ring-primary focus:border-primary px-4 py-2.5" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Email</label>
          <input v-model="settings.contact_email" type="email" class="w-full rounded-lg border-gray-300 dark:border-dark-line bg-white dark:bg-dark-bg text-gray-900 dark:text-white focus:ring-primary focus:border-primary px-4 py-2.5" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Timezone</label>
          <select v-model="settings.timezone" class="w-full rounded-lg border-gray-300 dark:border-dark-line bg-white dark:bg-dark-bg text-gray-900 dark:text-white focus:ring-primary focus:border-primary px-4 py-2.5">
            <option value="UTC">UTC</option>
            <option value="Asia/Jakarta">Asia/Jakarta</option>
            <option value="America/New_York">America/New_York</option>
            <option value="Europe/London">Europe/London</option>
          </select>
        </div>
      </div>

      <!-- Attendance Rules -->
      <div class="bg-white dark:bg-dark-surface p-6 rounded-xl shadow-sm border border-gray-100 dark:border-dark-border space-y-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
           <span class="material-symbols-outlined text-gray-500">rule</span>
           Attendance Rules
        </h2>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Allowed Radius (meters)</label>
          <input v-model="settings.attendance_radius" type="number" class="w-full rounded-lg border-gray-300 dark:border-dark-line bg-white dark:bg-dark-bg text-gray-900 dark:text-white focus:ring-primary focus:border-primary px-4 py-2.5" />
          <p class="text-xs text-gray-500 mt-1">Maximum distance allowed from office location.</p>
        </div>

        <div>
           <label class="flex items-center gap-2">
             <input type="checkbox" v-model="settings.require_photo" class="rounded border-gray-300 text-primary focus:ring-primary" true-value="true" false-value="false" />
             <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Require Photo Evidence</span>
           </label>
           <p class="text-xs text-gray-500 mt-1 ml-6">If enabled, employees must take a photo when checking in.</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Work Days</label>
          <div class="flex flex-wrap gap-3">
            <label 
              v-for="day in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" 
              :key="day"
              class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
              :class="settings.work_days.includes(day) 
                ? 'bg-primary/10 border-primary text-primary' 
                : 'bg-white dark:bg-dark-bg border-gray-300 dark:border-dark-line text-gray-700 dark:text-gray-300 hover:border-gray-400'"
            >
              <input 
                type="checkbox" 
                :value="day" 
                :checked="settings.work_days.includes(day)"
                @change="e => {
                  const days = settings.work_days ? settings.work_days.split(',').filter(d => d) : []
                  if (e.target.checked) {
                    days.push(day)
                  } else {
                    const index = days.indexOf(day)
                    if (index > -1) days.splice(index, 1)
                  }
                  // Sort days based on week order
                  const weekOrder = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
                  days.sort((a, b) => weekOrder.indexOf(a) - weekOrder.indexOf(b))
                  settings.work_days = days.join(',')
                }"
                class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
              />
              <span class="text-sm font-medium">{{ day }}</span>
            </label>
          </div>
        </div>
      </div>

      <!-- IP Security -->
      <div class="bg-white dark:bg-dark-surface p-6 rounded-xl shadow-sm border border-gray-100 dark:border-dark-border space-y-6 lg:col-span-2">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
           <span class="material-symbols-outlined text-gray-500">security</span>
           IP Security
        </h2>

        <div>
           <label class="flex items-center gap-2">
             <input type="checkbox" v-model="settings.ip_whitelist_enabled" class="rounded border-gray-300 text-primary focus:ring-primary" true-value="true" false-value="false" />
             <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Enable IP Whitelist</span>
           </label>
           <p class="text-xs text-gray-500 mt-1 ml-6">If enabled, only requests from allowed IPs can submit attendance.</p>
        </div>

        <div v-if="settings.ip_whitelist_enabled === 'true'">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Allowed IP Addresses</label>
          <textarea 
            v-model="settings.ip_whitelist"
            rows="4"
            placeholder="Enter one IP per line, e.g.&#10;192.168.1.1&#10;10.0.0.0/24" 
            class="w-full rounded-lg border-gray-300 dark:border-dark-line bg-white dark:bg-dark-bg text-gray-900 dark:text-white focus:ring-primary focus:border-primary px-4 py-2.5 text-sm font-mono"
          ></textarea>
          <p class="text-xs text-gray-500 mt-1">Supports individual IPs and CIDR notation (e.g., 192.168.1.0/24).</p>
        </div>
      </div>

    </div>
  </div>
</template>

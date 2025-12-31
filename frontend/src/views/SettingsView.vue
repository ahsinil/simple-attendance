<script setup>
import { ref, computed, onMounted } from 'vue'
import { authApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const loading = ref(false)
const devices = ref([])
const message = ref({ type: '', text: '' })

// Profile Form State
const profileForm = ref({
  firstName: '',
  lastName: '',
  phone: '',
})

// Password Form State
const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})

// 2FA Mock State
const twoFactorEnabled = ref(false)

// Initialize form data from store
function initForm() {
  if (authStore.user) {
    const names = (authStore.user.name || '').split(' ')
    profileForm.value.firstName = names[0] || ''
    profileForm.value.lastName = names.slice(1).join(' ') || ''
    profileForm.value.phone = authStore.user.phone || ''
  }
}

onMounted(async () => {
  initForm()
  await fetchDevices()
})

async function fetchDevices() {
  try {
    const response = await authApi.activeDevices()
    if (response.data.success) {
      devices.value = response.data.data
    }
  } catch (err) {
    console.error('Failed to load devices', err)
  }
}

function showMessage(type, text) {
  message.value = { type, text }
  setTimeout(() => {
    message.value = { type: '', text: '' }
  }, 3000)
}

async function updateProfile() {
  loading.value = true
  try {
    const fullName = `${profileForm.value.firstName} ${profileForm.value.lastName}`.trim()
    
    // Optimistic update
    const previousUser = { ...authStore.user }
    
    const response = await authApi.updateProfile({
      name: fullName,
      phone: profileForm.value.phone
    })

    if (response.data.success) {
      authStore.user = response.data.data
      showMessage('success', 'Profile updated successfully')
    }
  } catch (error) {
    showMessage('error', error.response?.data?.message || 'Failed to update profile')
  } finally {
    loading.value = false
  }
}

async function changePassword() {
  loading.value = true
  try {
    const response = await authApi.changePassword(passwordForm.value)
    if (response.data.success) {
      showMessage('success', 'Password changed successfully')
      passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
    }
  } catch (error) {
    const msg = error.response?.data?.message || 'Failed to change password'
    if (error.response?.data?.errors) {
      const firstError = Object.values(error.response.data.errors)[0][0]
      showMessage('error', firstError)
    } else {
      showMessage('error', msg)
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-4xl mx-auto space-y-10 pb-20">
    <!-- Page Header -->
    <div>
      <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Account Settings</h1>
      <p class="text-gray-500 mt-2">Manage your profile, security preferences, and active devices.</p>
    </div>

    <!-- Feedback Message -->
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="message.text" 
        class="fixed top-24 right-6 z-50 px-4 py-3 rounded-lg shadow-lg border"
        :class="message.type === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'"
      >
        {{ message.text }}
      </div>
    </transition>

    <!-- Profile Card -->
    <div class="bg-gray-900 text-white rounded-2xl p-6 sm:p-8 shadow-xl relative overflow-hidden">
      <!-- Background pattern -->
      <div class="absolute top-0 right-0 -mt-10 -mr-10 w-64 h-64 bg-white/5 rounded-full blur-3xl"></div>
      
      <div class="flex flex-col sm:flex-row items-center gap-6 relative z-10">
        <div class="relative">
          <div class="w-24 h-24 rounded-full bg-gray-700 border-4 border-gray-800 overflow-hidden">
            <img 
              v-if="authStore.user?.avatar" 
              :src="authStore.user.avatar" 
              class="w-full h-full object-cover"
              alt="Avatar"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-3xl font-bold text-gray-400">
              {{ authStore.user?.name?.charAt(0) }}
            </div>
          </div>
          <button class="absolute bottom-0 right-0 p-1.5 bg-primary rounded-full hover:bg-primary-600 transition-colors shadow-lg">
            <span class="material-symbols-outlined text-white text-sm">edit</span>
          </button>
        </div>

        <div class="text-center sm:text-left">
          <h2 class="text-2xl font-bold">{{ authStore.user?.name }}</h2>
          <div class="flex items-center justify-center sm:justify-start gap-2 text-gray-400 mt-1 text-sm">
            <span>{{ authStore.user?.position || 'Employee' }}</span>
            <span>•</span>
            <span>ID: {{ authStore.user?.employee_id || 'EMP-0000' }}</span>
          </div>
          
          <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 mt-4">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-emerald-500/20 text-emerald-400 text-xs font-medium">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
              On Shift
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-gray-700/50 text-gray-300 text-xs font-medium">
              <span class="material-symbols-outlined text-[14px]">location_on</span>
              {{ authStore.user?.default_location?.name || 'San Francisco HQ' }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Personal Information -->
    <div class="space-y-6">
      <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Personal Information</h3>

      </div>
      
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">First Name</label>
          <input 
            v-model="profileForm.firstName"
            type="text" 
            class="block w-full rounded-lg border-gray-300 dark:border-dark-line bg-gray-50 dark:bg-dark-bg text-gray-900 dark:text-white focus:border-primary focus:ring-primary sm:text-sm px-4 py-3"
            placeholder="First Name"
          />
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">Last Name</label>
          <input 
            v-model="profileForm.lastName"
            type="text" 
            class="block w-full rounded-lg border-gray-300 dark:border-dark-line bg-gray-50 dark:bg-dark-bg text-gray-900 dark:text-white focus:border-primary focus:ring-primary sm:text-sm px-4 py-3"
            placeholder="Last Name"
          />
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">Email Address</label>
          <input 
            :value="authStore.user?.email"
            readonly
            type="email" 
            class="block w-full rounded-lg border-gray-300 dark:border-dark-line bg-gray-100 dark:bg-dark-surface text-gray-500 cursor-not-allowed sm:text-sm px-4 py-3"
          />
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">Phone Number</label>
          <input 
            v-model="profileForm.phone"
            type="tel" 
            class="block w-full rounded-lg border-gray-300 dark:border-dark-line bg-gray-50 dark:bg-dark-bg text-gray-900 dark:text-white focus:border-primary focus:ring-primary sm:text-sm px-4 py-3"
            placeholder="+1 (555) 000-0000"
          />
        </div>
      </div>

      <div class="flex justify-end pt-4">
        <button 
          @click="updateProfile"
          :disabled="loading"
          class="px-6 py-3 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-lg font-medium hover:opacity-90 transition-opacity disabled:opacity-50 whitespace-nowrap"
        >
          {{ loading ? 'Saving...' : 'Save Changes' }}
        </button>
      </div>
    </div>

    <!-- Security -->
    <div class="space-y-6">
      <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Security</h3>
      </div>
      
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">Current Password</label>
          <input 
            v-model="passwordForm.current_password"
            type="password" 
            class="block w-full rounded-lg border-gray-300 dark:border-dark-line bg-gray-50 dark:bg-dark-bg text-gray-900 dark:text-white focus:border-primary focus:ring-primary sm:text-sm px-4 py-3 tracking-widest"
            placeholder="••••••••"
          />
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">New Password</label>
          <input 
            v-model="passwordForm.password"
            type="password" 
            class="block w-full rounded-lg border-gray-300 dark:border-dark-line bg-gray-50 dark:bg-dark-bg text-gray-900 dark:text-white focus:border-primary focus:ring-primary sm:text-sm px-4 py-3 tracking-widest"
            placeholder="••••••••"
          />
        </div>
        <div class="space-y-2 sm:col-span-2">
           <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">Confirm New Password</label>
           <div class="flex gap-4 items-start">
             <input 
                v-model="passwordForm.password_confirmation"
                type="password" 
                class="block w-full rounded-lg border-gray-300 dark:border-dark-line bg-gray-50 dark:bg-dark-bg text-gray-900 dark:text-white focus:border-primary focus:ring-primary sm:text-sm px-4 py-3 tracking-widest"
                placeholder="••••••••"
              />
              <button 
                @click="changePassword"
                :disabled="loading"
                class="px-6 py-3 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-lg font-medium hover:opacity-90 transition-opacity disabled:opacity-50 whitespace-nowrap"
              >
                Update Password
              </button>
           </div>
        </div>
      </div>

      <!-- 2FA Card -->
      <!-- <div class="mt-6 p-6 rounded-xl border border-gray-200 dark:border-dark-line bg-gray-50 dark:bg-dark-bg flex items-center justify-between">
        <div>
          <h4 class="text-base font-medium text-gray-900 dark:text-white">Two-Factor Authentication</h4>
          <p class="text-sm text-gray-500 mt-1">Add an extra layer of security to your account.</p>
        </div>
        <button 
          @click="twoFactorEnabled = !twoFactorEnabled"
          class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
          :class="twoFactorEnabled ? 'bg-primary' : 'bg-gray-200 dark:bg-gray-700'"
        >
          <span 
            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
            :class="twoFactorEnabled ? 'translate-x-5' : 'translate-x-0'"
          />
        </button>
      </div> -->
    </div>

    <!-- Registered Devices -->
    <div class="space-y-6">
      <div class="border-b border-gray-200 dark:border-gray-800 pb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Registered Devices</h3>
        <p class="text-sm text-gray-500 mt-1">Manage devices authorized for attendance submission via GPS and Barcode.</p>
      </div>

      <div class="space-y-4">
        <div 
          v-for="device in devices" 
          :key="device.id"
          class="flex items-center justify-between p-4 rounded-xl border border-gray-200 dark:border-dark-line bg-white dark:bg-dark-surface"
        >
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-dark-bg flex items-center justify-center">
              <span class="material-symbols-outlined text-gray-600 dark:text-gray-400">
                {{ device.name.toLowerCase().includes('phone') || device.name.toLowerCase().includes('android') || device.name.toLowerCase().includes('ios') ? 'smartphone' : 'laptop' }}
              </span>
            </div>
            <div>
              <h4 class="font-medium text-gray-900 dark:text-white">{{ device.name }}</h4>
              <div class="flex items-center gap-2 text-xs text-gray-500 mt-0.5">
                <span class="material-symbols-outlined text-[12px]">location_on</span>
                {{ authStore.user?.default_location?.name || 'Unknown Location' }}
                <span>•</span>
                {{ device.is_current ? 'Active now' : `Last active ${device.last_used_at}` }}
              </div>
            </div>
          </div>
          
          <span 
            v-if="device.is_current"
            class="px-3 py-1 bg-emerald-500/10 text-emerald-500 text-xs font-medium rounded-full border border-emerald-500/20"
          >
            Current Device
          </span>
          <button v-else class="text-gray-400 hover:text-red-500 transition-colors">
            <span class="material-symbols-outlined">delete</span>
          </button>
        </div>

        <div v-if="devices.length === 0" class="text-center py-8 text-gray-500">
          No devices found.
        </div>
      </div>
    </div>
  </div>
</template>

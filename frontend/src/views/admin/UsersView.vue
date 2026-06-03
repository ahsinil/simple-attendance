<script setup>
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '@/services/api'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useAuthStore } from '@/stores/auth'
import { toDateInputValue } from '@/utils/date'

// Import Extracted Modals
import UserFormModal from '@/components/admin/Users/UserFormModal.vue'
import ScheduleModal from '@/components/admin/Users/ScheduleModal.vue'
import SalaryModal from '@/components/admin/Users/SalaryModal.vue'

const toast = useToast()
const { confirmDelete } = useConfirm()
const authStore = useAuthStore()

// Permission checks
const canCreate = computed(() => authStore.hasPermission('admin.users.create'))
const canUpdate = computed(() => authStore.hasPermission('admin.users.update'))
const canDelete = computed(() => authStore.hasPermission('admin.users.delete'))

const users = ref([])
const roles = ref([])
const shifts = ref([])
const loading = ref(true)
const search = ref('')

// Modal States
const showForm = ref(false)
const showScheduleModal = ref(false)
const showSalaryModal = ref(false)

// Selected User States
const activeUser = ref(null)

onMounted(async () => {
  await Promise.all([fetchUsers(), fetchRoles(), fetchShifts()])
  loading.value = false
})

async function fetchUsers() {
  try {
    const response = await adminApi.getUsers({ search: search.value })
    users.value = response.data.data?.data || []
  } catch (error) {
    console.error('Failed to fetch users:', error)
  }
}

async function fetchRoles() {
  try {
    const response = await adminApi.getRoles()
    roles.value = response.data.data || []
  } catch (error) {
    console.error('Failed to fetch roles:', error)
  }
}

async function fetchShifts() {
  try {
    const response = await adminApi.getShifts()
    shifts.value = response.data.data || []
  } catch (error) {
    console.error('Failed to fetch shifts:', error)
  }
}

// User Form Actions
function openCreate() {
  activeUser.value = null
  showForm.value = true
}

function openEdit(user) {
  activeUser.value = user
  showForm.value = true
}

// Schedule & Salary Actions
function openScheduleModal(user) {
  activeUser.value = user
  showScheduleModal.value = true
}

function openSalaryModal(user) {
  activeUser.value = user
  showSalaryModal.value = true
}

// Delete Action
async function deleteUser(user) {
  const confirmed = await confirmDelete(user.name)
  if (!confirmed) return
  try {
    await adminApi.deleteUser(user.id)
    toast.success('User deleted successfully')
    fetchUsers()
  } catch (error) {
    toast.error(error.response?.data?.message || error.response?.data?.error || 'Failed to delete user')
  }
}

// Table formatting helpers
function getCurrentShift(user) {
  if (!user.schedules || user.schedules.length === 0) return 'No Shift'
  
  const today = toDateInputValue()
  
  const activeSchedule = user.schedules.find(s => {
    const startDate = s.start_date.split('T')[0]
    const endDate = s.end_date ? s.end_date.split('T')[0] : null
    
    return startDate <= today && (!endDate || endDate >= today)
  })
  
  if (activeSchedule && activeSchedule.shift) {
    return activeSchedule.shift.name
  }
  
  return 'No Shift'
}

/**
 * Hitung total tunjangan tetap (FIXED) yang diterima user.
 */
function getFixedAllowanceSummary(user) {
  if (!user.salary_components || user.salary_components.length === 0) {
    return null
  }

  const fixedComponents = user.salary_components.filter(
    c => c.salary_component?.type === 'FIXED' && c.salary_component?.is_active
  )

  if (fixedComponents.length === 0) return null

  const total = fixedComponents.reduce((sum, c) => sum + parseFloat(c.amount || 0), 0)
  return { count: fixedComponents.length, total }
}

function formatCurrencyShort(amount) {
  if (!amount || amount === 0) return '-'
  if (amount >= 1_000_000) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount / 1_000_000).replace(/[.,]\d+/, '') + ' jt'
  }
  if (amount >= 1_000) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount / 1_000).replace(/[.,]\d+/, '') + ' rb'
  }
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-between">
      <div class="flex gap-2">
        <input v-model="search" @keyup.enter="fetchUsers" class="input flex-1 sm:w-64" placeholder="Search users..." />
        <button @click="fetchUsers" class="btn btn-secondary whitespace-nowrap">Search</button>
      </div>
      <button v-if="canCreate" @click="openCreate" class="btn btn-primary w-full sm:w-auto">
        <span class="material-symbols-outlined text-sm">add</span>
        Add User
      </button>
    </div>

    <!-- Users Table -->
    <div class="card overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-gray-500">Loading...</div>

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[700px]">
          <thead class="bg-gray-50 dark:bg-dark-border">
            <tr>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Name</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Email</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Role</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Shift</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Tunjangan Tetap</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Status</th>
              <th v-if="canUpdate || canDelete" class="px-4 py-3 text-right text-sm font-medium text-gray-500">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-dark-border">
            <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-dark-border/50">
              <td class="px-4 py-3">
                <p class="font-medium text-gray-900 dark:text-white">{{ user.name }}</p>
                <p class="text-sm text-gray-500">{{ user.employee_id }}</p>
              </td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ user.email }}</td>
              <td class="px-4 py-3">
                <span class="px-2 py-1 text-xs rounded-full bg-primary/10 text-primary">
                  {{ user.roles?.[0]?.name || 'N/A' }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                {{ getCurrentShift(user) }}
              </td>
              <!-- Tunjangan Tetap Badge -->
              <td class="px-4 py-3">
                <template v-if="getFixedAllowanceSummary(user)">
                  <button
                    v-if="canUpdate"
                    @click="openSalaryModal(user)"
                    class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 text-xs font-medium hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors"
                    :title="`${getFixedAllowanceSummary(user).count} komponen tunjangan tetap`"
                  >
                    <span class="material-symbols-outlined text-xs">lock</span>
                    {{ formatCurrencyShort(getFixedAllowanceSummary(user).total) }}
                  </button>
                  <span v-else class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 text-xs font-medium">
                    <span class="material-symbols-outlined text-xs">lock</span>
                    {{ formatCurrencyShort(getFixedAllowanceSummary(user).total) }}
                  </span>
                </template>
                <span v-else class="text-xs text-gray-400">—</span>
              </td>
              <td class="px-4 py-3">
                <span :class="user.status === 'active' ? 'text-green-500' : 'text-gray-400'">
                  {{ user.status }}
                </span>
              </td>
              <td v-if="canUpdate || canDelete" class="px-4 py-3 text-right">
                <button v-if="canUpdate" @click="openSalaryModal(user)" class="p-1 hover:bg-green-100 dark:hover:bg-green-900/20 rounded text-green-500" title="Kelola Gaji & Tunjangan">
                  <span class="material-symbols-outlined text-sm">payments</span>
                </button>
                <button v-if="canUpdate" @click="openScheduleModal(user)" class="p-1 hover:bg-blue-100 dark:hover:bg-blue-900/20 rounded text-blue-500" title="Manage Shifts">
                  <span class="material-symbols-outlined text-sm">schedule</span>
                </button>
                <button v-if="canUpdate" @click="openEdit(user)" class="p-1 hover:bg-gray-100 dark:hover:bg-dark-border rounded" title="Edit User">
                  <span class="material-symbols-outlined text-sm">edit</span>
                </button>
                <button v-if="canDelete" @click="deleteUser(user)" class="p-1 hover:bg-red-100 dark:hover:bg-red-900/20 rounded text-red-500" title="Delete User">
                  <span class="material-symbols-outlined text-sm">delete</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Extracted Modals -->
    <UserFormModal
      :show="showForm"
      :user="activeUser"
      :roles="roles"
      @close="showForm = false"
      @saved="fetchUsers"
    />

    <ScheduleModal
      :show="showScheduleModal"
      :user="activeUser"
      :shifts="shifts"
      @close="showScheduleModal = false"
      @changed="fetchUsers"
    />

    <SalaryModal
      :show="showSalaryModal"
      :user="activeUser"
      @close="showSalaryModal = false"
      @saved="fetchUsers"
    />
  </div>
</template>

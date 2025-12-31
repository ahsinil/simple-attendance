<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'

const toast = useToast()
const { confirmDelete, confirmAction } = useConfirm()

const users = ref([])
const roles = ref([])
const shifts = ref([])
const loading = ref(true)
const showForm = ref(false)
const editingUser = ref(null)
const search = ref('')

// Schedule management
const showScheduleModal = ref(false)
const scheduleUser = ref(null)
const userSchedules = ref([])
const loadingSchedules = ref(false)
const scheduleForm = ref({
  shift_id: '',
  start_date: '',
  end_date: '',
})

const form = ref({
  name: '',
  email: '',
  password: '',
  employee_id: '',
  department: '',
  position: '',
  role: 'employee',
})

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

function openCreate() {
  editingUser.value = null
  form.value = { name: '', email: '', password: '', employee_id: '', department: '', position: '', role: 'employee' }
  showForm.value = true
}

function openEdit(user) {
  editingUser.value = user
  form.value = {
    name: user.name,
    email: user.email,
    password: '',
    employee_id: user.employee_id || '',
    department: user.department || '',
    position: user.position || '',
    role: user.roles?.[0]?.name || 'employee',
  }
  showForm.value = true
}

async function handleSubmit() {
  try {
    if (editingUser.value) {
      const data = { ...form.value }
      if (!data.password) delete data.password
      await adminApi.updateUser(editingUser.value.id, data)
    } else {
      await adminApi.createUser(form.value)
    }
    showForm.value = false
    toast.success(editingUser.value ? 'User updated successfully' : 'User created successfully')
    fetchUsers()
  } catch (error) {
    toast.error(error.response?.data?.message || error.response?.data?.error || 'Failed to save user')
  }
}

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

// Schedule management functions
async function openScheduleModal(user) {
  scheduleUser.value = user
  showScheduleModal.value = true
  scheduleForm.value = { shift_id: '', start_date: '', end_date: '' }
  await fetchUserSchedules(user.id)
}

async function fetchUserSchedules(userId) {
  loadingSchedules.value = true
  try {
    const response = await adminApi.getUserSchedules(userId)
    userSchedules.value = response.data.data || []
  } catch (error) {
    console.error('Failed to fetch schedules:', error)
  } finally {
    loadingSchedules.value = false
  }
}

async function assignSchedule() {
  if (!scheduleForm.value.shift_id || !scheduleForm.value.start_date) {
    toast.warning('Please select a shift and start date')
    return
  }
  try {
    await adminApi.assignSchedule(scheduleUser.value.id, {
      shift_id: scheduleForm.value.shift_id,
      start_date: scheduleForm.value.start_date,
      end_date: scheduleForm.value.end_date || null,
    })
    scheduleForm.value = { shift_id: '', start_date: '', end_date: '' }
    toast.success('Schedule assigned successfully')
    await fetchUserSchedules(scheduleUser.value.id)
  } catch (error) {
    toast.error(error.response?.data?.message || error.response?.data?.error || 'Failed to assign schedule')
  }
}

async function removeSchedule(schedule) {
  const confirmed = await confirmAction('Remove this schedule?', 'Remove Schedule')
  if (!confirmed) return
  try {
    await adminApi.removeSchedule(scheduleUser.value.id, schedule.id)
    toast.success('Schedule removed')
    await fetchUserSchedules(scheduleUser.value.id)
  } catch (error) {
    toast.error(error.response?.data?.message || error.response?.data?.error || 'Failed to remove schedule')
  }
}

function formatDate(date) {
  if (!date) return 'Ongoing'
  return new Date(date).toLocaleDateString()
}

function formatTime(time) {
  if (!time) return ''
  return time.substring(0, 5)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex gap-2">
        <input v-model="search" @keyup.enter="fetchUsers" class="input w-64" placeholder="Search users..." />
        <button @click="fetchUsers" class="btn btn-secondary">Search</button>
      </div>
      <button @click="openCreate" class="btn btn-primary">
        <span class="material-symbols-outlined text-sm">add</span>
        Add User
      </button>
    </div>

    <!-- Users Table -->
    <div class="card overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-gray-500">Loading...</div>

      <table v-else class="w-full">
        <thead class="bg-gray-50 dark:bg-dark-border">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Name</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Email</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Role</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Status</th>
            <th class="px-4 py-3 text-right text-sm font-medium text-gray-500">Actions</th>
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
            <td class="px-4 py-3">
              <span :class="user.status === 'active' ? 'text-green-500' : 'text-gray-400'">
                {{ user.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <button @click="openScheduleModal(user)" class="p-1 hover:bg-blue-100 dark:hover:bg-blue-900/20 rounded text-blue-500" title="Manage Shifts">
                <span class="material-symbols-outlined text-sm">schedule</span>
              </button>
              <button @click="openEdit(user)" class="p-1 hover:bg-gray-100 dark:hover:bg-dark-border rounded" title="Edit User">
                <span class="material-symbols-outlined text-sm">edit</span>
              </button>
              <button @click="deleteUser(user)" class="p-1 hover:bg-red-100 dark:hover:bg-red-900/20 rounded text-red-500" title="Delete User">
                <span class="material-symbols-outlined text-sm">delete</span>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- User Form Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-md max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          {{ editingUser ? 'Edit User' : 'Add User' }}
        </h3>
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
            <input v-model="form.name" class="input" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email *</label>
            <input v-model="form.email" type="email" class="input" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Password {{ editingUser ? '(leave blank to keep)' : '*' }}
            </label>
            <input v-model="form.password" type="password" class="input" :required="!editingUser" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Employee ID</label>
              <input v-model="form.employee_id" class="input" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role *</label>
              <select v-model="form.role" class="input" required>
                <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Department</label>
              <input v-model="form.department" class="input" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Position</label>
              <input v-model="form.position" class="input" />
            </div>
          </div>
          <div class="flex gap-3 pt-4">
            <button type="submit" class="btn btn-primary flex-1">Save</button>
            <button type="button" @click="showForm = false" class="btn btn-secondary flex-1">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Schedule Modal -->
    <div v-if="showScheduleModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Manage Shifts - {{ scheduleUser?.name }}
          </h3>
          <button @click="showScheduleModal = false" class="p-1 hover:bg-gray-100 dark:hover:bg-dark-border rounded">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <!-- Add Schedule Form -->
        <div class="bg-gray-50 dark:bg-dark-border rounded-lg p-4 mb-4">
          <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Assign New Shift</h4>
          <div class="grid grid-cols-1 gap-3">
            <div>
              <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Shift *</label>
              <select v-model="scheduleForm.shift_id" class="input">
                <option value="">Select a shift...</option>
                <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
                  {{ shift.name }} ({{ formatTime(shift.start_time) }} - {{ formatTime(shift.end_time) }})
                </option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Start Date *</label>
                <input v-model="scheduleForm.start_date" type="date" class="input" />
              </div>
              <div>
                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">End Date</label>
                <input v-model="scheduleForm.end_date" type="date" class="input" placeholder="Leave empty for ongoing" />
              </div>
            </div>
            <button @click="assignSchedule" class="btn btn-primary w-full">
              <span class="material-symbols-outlined text-sm mr-1">add</span>
              Assign Shift
            </button>
          </div>
        </div>

        <!-- Current Schedules -->
        <div>
          <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Current Schedules</h4>
          <div v-if="loadingSchedules" class="text-center py-4 text-gray-500">Loading...</div>
          <div v-else-if="userSchedules.length === 0" class="text-center py-4 text-gray-500">
            No schedules assigned
          </div>
          <div v-else class="space-y-2">
            <div v-for="schedule in userSchedules" :key="schedule.id" 
                 class="flex items-center justify-between p-3 bg-gray-50 dark:bg-dark-border rounded-lg">
              <div>
                <p class="font-medium text-gray-900 dark:text-white">{{ schedule.shift?.name }}</p>
                <p class="text-sm text-gray-500">
                  {{ formatTime(schedule.shift?.start_time) }} - {{ formatTime(schedule.shift?.end_time) }}
                </p>
                <p class="text-xs text-gray-400 mt-1">
                  {{ formatDate(schedule.start_date) }} → {{ formatDate(schedule.end_date) }}
                </p>
              </div>
              <button @click="removeSchedule(schedule)" 
                      class="p-1 hover:bg-red-100 dark:hover:bg-red-900/20 rounded text-red-500" title="Remove Schedule">
                <span class="material-symbols-outlined text-sm">delete</span>
              </button>
            </div>
          </div>
        </div>

        <div class="flex justify-end mt-4 pt-4 border-t border-gray-200 dark:border-dark-border">
          <button @click="showScheduleModal = false" class="btn btn-secondary">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

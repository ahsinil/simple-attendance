<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'

const users = ref([])
const roles = ref([])
const shifts = ref([])
const loading = ref(true)
const showForm = ref(false)
const editingUser = ref(null)
const search = ref('')

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
    fetchUsers()
  } catch (error) {
    alert(error.response?.data?.error || 'Failed to save user')
  }
}

async function deleteUser(user) {
  if (!confirm(`Delete ${user.name}?`)) return
  try {
    await adminApi.deleteUser(user.id)
    fetchUsers()
  } catch (error) {
    alert(error.response?.data?.error || 'Failed to delete user')
  }
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
              <button @click="openEdit(user)" class="p-1 hover:bg-gray-100 dark:hover:bg-dark-border rounded">
                <span class="material-symbols-outlined text-sm">edit</span>
              </button>
              <button @click="deleteUser(user)" class="p-1 hover:bg-red-100 dark:hover:bg-red-900/20 rounded text-red-500">
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
  </div>
</template>

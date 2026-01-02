<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'

// Data
const leaveTypes = ref([])
const loading = ref(false)

// Modal
const showModal = ref(false)
const isEditing = ref(false)
const form = ref({
  id: null,
  name: '',
  code: '',
  default_days: 12,
  is_paid: true,
  requires_approval: true,
  color: '#4CAF50',
  is_active: true,
})
const saving = ref(false)
const formError = ref('')

// Preset colors
const presetColors = [
  '#4CAF50', '#2196F3', '#F44336', '#FF9800',
  '#9C27B0', '#E91E63', '#3F51B5', '#00BCD4',
  '#795548', '#607D8B', '#9E9E9E', '#673AB7',
]

// Fetch leave types
async function fetchLeaveTypes() {
  loading.value = true
  try {
    const res = await adminApi.getLeaveTypes()
    leaveTypes.value = res.data.data
  } catch (error) {
    console.error('Failed to fetch leave types:', error)
  } finally {
    loading.value = false
  }
}

// Open modal for create/edit
function openModal(type = null) {
  formError.value = ''
  if (type) {
    isEditing.value = true
    form.value = { ...type }
  } else {
    isEditing.value = false
    form.value = {
      id: null,
      name: '',
      code: '',
      default_days: 12,
      is_paid: true,
      requires_approval: true,
      color: '#4CAF50',
      is_active: true,
    }
  }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  formError.value = ''
}

// Generate code from name
function generateCode() {
  if (!isEditing.value && form.value.name) {
    form.value.code = form.value.name
      .toUpperCase()
      .replace(/[^A-Z0-9]/g, '_')
      .replace(/_+/g, '_')
      .substring(0, 20)
  }
}

// Save leave type
async function save() {
  if (!form.value.name || !form.value.code) {
    formError.value = 'Name and code are required'
    return
  }

  saving.value = true
  formError.value = ''

  try {
    if (isEditing.value) {
      await adminApi.updateLeaveType(form.value.id, form.value)
    } else {
      await adminApi.createLeaveType(form.value)
    }
    closeModal()
    fetchLeaveTypes()
  } catch (error) {
    formError.value = error.response?.data?.message || error.response?.data?.error || 'Failed to save'
  } finally {
    saving.value = false
  }
}

// Toggle active status
async function toggleActive(type) {
  try {
    await adminApi.updateLeaveType(type.id, { is_active: !type.is_active })
    fetchLeaveTypes()
  } catch (error) {
    alert('Failed to update status')
  }
}

// Delete leave type
async function deleteType(type) {
  if (!confirm(`Are you sure you want to delete "${type.name}"?`)) return

  try {
    await adminApi.deleteLeaveType(type.id)
    fetchLeaveTypes()
  } catch (error) {
    alert(error.response?.data?.error || 'Failed to delete. Leave type may have existing requests.')
  }
}

onMounted(() => {
  fetchLeaveTypes()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold text-gray-900 dark:text-white">Leave Types</h2>
      <button @click="openModal()" class="btn btn-primary">
        <span class="material-symbols-outlined text-sm">add</span>
        Add Leave Type
      </button>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-dark-surface">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Default Days</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Paid</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Approval</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-dark-border">
          <tr v-if="loading">
            <td colspan="7" class="px-4 py-8 text-center text-gray-500">Loading...</td>
          </tr>
          <tr v-else-if="!leaveTypes.length">
            <td colspan="7" class="px-4 py-8 text-center text-gray-500">No leave types found</td>
          </tr>
          <tr v-else v-for="type in leaveTypes" :key="type.id" class="hover:bg-gray-50 dark:hover:bg-dark-surface/50">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <div class="w-4 h-4 rounded-full" :style="{ backgroundColor: type.color }"></div>
                <span class="font-medium text-gray-900 dark:text-white">{{ type.name }}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-gray-500 text-sm">{{ type.code }}</td>
            <td class="px-4 py-3 text-center text-gray-900 dark:text-white">{{ type.default_days }}</td>
            <td class="px-4 py-3 text-center">
              <span v-if="type.is_paid" class="text-green-600">
                <span class="material-symbols-outlined text-sm">check_circle</span>
              </span>
              <span v-else class="text-gray-400">
                <span class="material-symbols-outlined text-sm">remove_circle</span>
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <span v-if="type.requires_approval" class="text-amber-600 text-xs">Required</span>
              <span v-else class="text-gray-400 text-xs">Auto</span>
            </td>
            <td class="px-4 py-3 text-center">
              <button
                @click="toggleActive(type)"
                :class="type.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                class="text-xs px-2 py-1 rounded-full"
              >
                {{ type.is_active ? 'Active' : 'Inactive' }}
              </button>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <button @click="openModal(type)" class="text-primary hover:text-primary/80">
                  <span class="material-symbols-outlined text-sm">edit</span>
                </button>
                <button @click="deleteType(type)" class="text-red-500 hover:text-red-700">
                  <span class="material-symbols-outlined text-sm">delete</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white dark:bg-dark-surface rounded-xl shadow-xl max-w-md w-full p-6" @click.stop>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          {{ isEditing ? 'Edit Leave Type' : 'Add Leave Type' }}
        </h3>

        <div v-if="formError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 px-4 py-3 rounded-lg mb-4 text-sm">
          {{ formError }}
        </div>

        <form @submit.prevent="save" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
            <input v-model="form.name" @input="generateCode" type="text" class="input" placeholder="e.g., Annual Leave" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Code *</label>
            <input v-model="form.code" type="text" class="input" placeholder="e.g., ANNUAL" :disabled="isEditing" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Days Per Year</label>
            <input v-model.number="form.default_days" type="number" class="input" min="0" max="365" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
            <div class="flex items-center gap-2 flex-wrap">
              <button
                v-for="color in presetColors"
                :key="color"
                type="button"
                @click="form.color = color"
                class="w-8 h-8 rounded-full border-2 transition-transform hover:scale-110"
                :class="form.color === color ? 'border-gray-900 dark:border-white scale-110' : 'border-transparent'"
                :style="{ backgroundColor: color }"
              ></button>
            </div>
          </div>

          <div class="flex items-center gap-6">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.is_paid" type="checkbox" class="w-4 h-4 rounded text-primary" />
              <span class="text-sm text-gray-700 dark:text-gray-300">Paid Leave</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.requires_approval" type="checkbox" class="w-4 h-4 rounded text-primary" />
              <span class="text-sm text-gray-700 dark:text-gray-300">Requires Approval</span>
            </label>
          </div>

          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded text-primary" />
            <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
          </label>

          <div class="flex gap-3 pt-4">
            <button type="submit" class="btn btn-primary flex-1" :disabled="saving">
              {{ saving ? 'Saving...' : (isEditing ? 'Update' : 'Create') }}
            </button>
            <button type="button" @click="closeModal" class="btn btn-secondary">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

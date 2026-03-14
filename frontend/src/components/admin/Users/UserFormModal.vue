<script setup>
import { ref, watch } from 'vue'
import { adminApi } from '@/services/api'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  show: {
    type: Boolean,
    required: true
  },
  user: {
    type: Object,
    default: null
  },
  roles: {
    type: Array,
    required: true
  }
})

const emit = defineEmits(['close', 'saved'])
const toast = useToast()

const form = ref({
  name: '',
  email: '',
  password: '',
  employee_id: '',
  department: '',
  position: '',
  role: 'employee',
  base_salary: '',
})

watch(() => props.show, (newVal) => {
  if (newVal) {
    if (props.user) {
      form.value = {
        name: props.user.name,
        email: props.user.email,
        password: '',
        employee_id: props.user.employee_id || '',
        department: props.user.department || '',
        position: props.user.position || '',
        role: props.user.roles?.[0]?.name || 'employee',
        base_salary: props.user.base_salary || '',
      }
    } else {
      form.value = { 
        name: '', 
        email: '', 
        password: '', 
        employee_id: '', 
        department: '', 
        position: '', 
        role: 'employee', 
        base_salary: '' 
      }
    }
  }
})

async function handleSubmit() {
  try {
    if (props.user) {
      const data = { ...form.value }
      if (!data.password) delete data.password
      await adminApi.updateUser(props.user.id, data)
    } else {
      await adminApi.createUser(form.value)
    }
    toast.success(props.user ? 'User updated successfully' : 'User created successfully')
    emit('saved')
    closeModal()
  } catch (error) {
    toast.error(error.response?.data?.message || error.response?.data?.error || 'Failed to save user')
  }
}

function closeModal() {
  emit('close')
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="card p-6 w-full max-w-md max-h-[90vh] overflow-y-auto bg-white dark:bg-dark-surface">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        {{ user ? 'Edit User' : 'Add User' }}
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
            Password {{ user ? '(leave blank to keep)' : '*' }}
          </label>
          <input v-model="form.password" type="password" class="input" :required="!user" />
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
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Base Salary (Gaji Pokok)</label>
          <input v-model.number="form.base_salary" type="number" class="input" placeholder="0" min="0" />
        </div>
        <div class="flex gap-3 pt-4">
          <button type="submit" class="btn btn-primary flex-1">Save</button>
          <button type="button" @click="closeModal" class="btn btn-secondary flex-1">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'

const toast = useToast()
const { confirmDelete } = useConfirm()

const shifts = ref([])
const loading = ref(true)
const showForm = ref(false)
const editingShift = ref(null)

const form = ref({ code: '', name: '', start_time: '09:00', end_time: '17:00', late_after_min: 15 })

onMounted(fetchShifts)

async function fetchShifts() {
  loading.value = true
  try {
    const response = await adminApi.getShifts()
    shifts.value = response.data.data || []
  } catch (error) {
    console.error('Failed to fetch shifts:', error)
  }
  loading.value = false
}

function openCreate() {
  editingShift.value = null
  form.value = { code: '', name: '', start_time: '09:00', end_time: '17:00', late_after_min: 15 }
  showForm.value = true
}

function openEdit(shift) {
  editingShift.value = shift
  form.value = { ...shift }
  showForm.value = true
}

async function handleSubmit() {
  try {
    if (editingShift.value) {
      await adminApi.updateShift(editingShift.value.id, form.value)
    } else {
      await adminApi.createShift(form.value)
    }
    showForm.value = false
    toast.success(editingShift.value ? 'Shift updated successfully' : 'Shift created successfully')
    fetchShifts()
  } catch (error) {
    toast.error(error.response?.data?.message || error.response?.data?.error || 'Failed to save shift')
  }
}

async function deleteShift(shift) {
  const confirmed = await confirmDelete(shift.name)
  if (!confirmed) return
  try {
    await adminApi.deleteShift(shift.id)
    toast.success('Shift deleted successfully')
    fetchShifts()
  } catch (error) {
    toast.error(error.response?.data?.message || error.response?.data?.error || 'Failed to delete')
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-end">
      <button @click="openCreate" class="btn btn-primary">
        <span class="material-symbols-outlined text-sm">add</span>
        Add Shift
      </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="shift in shifts" :key="shift.id" class="card p-6">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h3 class="font-semibold text-gray-900 dark:text-white">{{ shift.name }}</h3>
            <p class="text-sm text-gray-500">{{ shift.code }}</p>
          </div>
          <div class="flex gap-1">
            <button @click="openEdit(shift)" class="p-1 hover:bg-gray-100 dark:hover:bg-dark-border rounded">
              <span class="material-symbols-outlined text-sm">edit</span>
            </button>
            <button @click="deleteShift(shift)" class="p-1 hover:bg-red-100 dark:hover:bg-red-900/20 rounded text-red-500">
              <span class="material-symbols-outlined text-sm">delete</span>
            </button>
          </div>
        </div>
        <div class="text-center py-4">
          <p class="text-3xl font-bold text-primary">{{ shift.start_time }} - {{ shift.end_time }}</p>
        </div>
        <div class="text-sm text-gray-500 text-center">
          Late after {{ shift.late_after_min }} min
        </div>
      </div>
    </div>

    <!-- Form Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          {{ editingShift ? 'Edit Shift' : 'Add Shift' }}
        </h3>
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Code *</label>
              <input v-model="form.code" class="input" placeholder="SHIFT-01" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
              <input v-model="form.name" class="input" placeholder="Morning Shift" required />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Time *</label>
              <input v-model="form.start_time" type="time" class="input" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Time *</label>
              <input v-model="form.end_time" type="time" class="input" required />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Late After (min)</label>
            <input v-model.number="form.late_after_min" type="number" class="input" min="0" max="120" />
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

<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'

const { showToast } = useToast()
const { confirm } = useConfirm()

const loading = ref(false)
const components = ref([])
const showModal = ref(false)
const editingId = ref(null)

const form = ref({
  name: '',
  type: 'FIXED',
  description: '',
})

onMounted(() => {
  fetchComponents()
})

async function fetchComponents() {
  loading.value = true
  try {
    const response = await adminApi.getSalaryComponents()
    if (response.data.success) {
      components.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load salary components', error)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = { name: '', type: 'FIXED', description: '' }
  showModal.value = true
}

function openEdit(component) {
  editingId.value = component.id
  form.value = {
    name: component.name,
    type: component.type,
    description: component.description || '',
  }
  showModal.value = true
}

async function saveComponent() {
  try {
    if (editingId.value) {
      await adminApi.updateSalaryComponent(editingId.value, form.value)
      showToast('Component updated successfully', 'success')
    } else {
      await adminApi.createSalaryComponent(form.value)
      showToast('Component created successfully', 'success')
    }
    showModal.value = false
    fetchComponents()
  } catch (error) {
    const msg = error.response?.data?.message || 'Failed to save component'
    showToast(msg, 'error')
  }
}

async function toggleActive(component) {
  try {
    await adminApi.updateSalaryComponent(component.id, { is_active: !component.is_active })
    component.is_active = !component.is_active
    showToast(`Component ${component.is_active ? 'activated' : 'deactivated'}`, 'success')
  } catch (error) {
    showToast('Failed to update status', 'error')
  }
}

async function deleteComponent(component) {
  const ok = await confirm({
    title: 'Delete Component',
    message: `Are you sure you want to delete "${component.name}"? This will remove it from all employee salary assignments.`,
    confirmText: 'Delete',
    type: 'danger',
  })
  if (!ok) return

  try {
    await adminApi.deleteSalaryComponent(component.id)
    showToast('Component deleted', 'success')
    fetchComponents()
  } catch (error) {
    showToast('Failed to delete component', 'error')
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Salary Components</h1>
        <p class="text-gray-500 dark:text-gray-400">Manage allowance types (tunjangan tetap & tidak tetap).</p>
      </div>
      <button
        @click="openCreate"
        class="flex items-center gap-2 bg-primary hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg shadow-md transition-all font-semibold text-sm"
      >
        <span class="material-symbols-outlined text-sm">add</span>
        Add Component
      </button>
    </div>

    <!-- Info Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm">
        <div class="flex items-center gap-3 mb-2">
          <span class="p-2 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg">
            <span class="material-symbols-outlined">lock</span>
          </span>
          <div>
            <h3 class="font-bold text-gray-900 dark:text-white">Fixed (Tunjangan Tetap)</h3>
            <p class="text-xs text-gray-500">Always paid monthly. Included in overtime hourly rate calculation.</p>
          </div>
        </div>
        <p class="text-2xl font-bold text-gray-900 dark:text-white">
          {{ components.filter(c => c.type === 'FIXED' && c.is_active).length }}
        </p>
      </div>
      <div class="bg-white dark:bg-dark-surface p-5 rounded-xl border border-gray-100 dark:border-dark-border shadow-sm">
        <div class="flex items-center gap-3 mb-2">
          <span class="p-2 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-lg">
            <span class="material-symbols-outlined">sync_alt</span>
          </span>
          <div>
            <h3 class="font-bold text-gray-900 dark:text-white">Variable (Tunjangan Tidak Tetap)</h3>
            <p class="text-xs text-gray-500">Deducted on absent/leave days. NOT included in OT calculation.</p>
          </div>
        </div>
        <p class="text-2xl font-bold text-gray-900 dark:text-white">
          {{ components.filter(c => c.type === 'VARIABLE' && c.is_active).length }}
        </p>
      </div>
    </div>

    <!-- Components List -->
    <div class="bg-white dark:bg-dark-surface rounded-xl border border-gray-100 dark:border-dark-border shadow-sm overflow-hidden">
      <div v-if="loading" class="flex justify-center py-12">
        <span class="material-symbols-outlined animate-spin text-4xl text-gray-400">progress_activity</span>
      </div>

      <div v-else class="divide-y divide-gray-100 dark:divide-dark-border">
        <div v-if="components.length === 0" class="px-6 py-12 text-center text-gray-500">
          No salary components defined yet. Click "Add Component" to create one.
        </div>

        <div
          v-for="comp in components"
          :key="comp.id"
          class="flex items-center justify-between p-5 hover:bg-gray-50 dark:hover:bg-dark-bg transition-colors"
          :class="{ 'opacity-50': !comp.is_active }"
        >
          <div class="flex items-center gap-4">
            <span
              class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold"
              :class="comp.type === 'FIXED'
                ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'
                : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'"
            >
              {{ comp.type }}
            </span>
            <div>
              <p class="font-bold text-gray-900 dark:text-white">{{ comp.name }}</p>
              <p v-if="comp.description" class="text-sm text-gray-500">{{ comp.description }}</p>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="toggleActive(comp)"
              class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-dark-border transition-colors"
              :title="comp.is_active ? 'Deactivate' : 'Activate'"
            >
              <span class="material-symbols-outlined text-sm" :class="comp.is_active ? 'text-green-500' : 'text-gray-400'">
                {{ comp.is_active ? 'toggle_on' : 'toggle_off' }}
              </span>
            </button>
            <button
              @click="openEdit(comp)"
              class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-dark-border transition-colors"
            >
              <span class="material-symbols-outlined text-sm text-gray-500">edit</span>
            </button>
            <button
              @click="deleteComponent(comp)"
              class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
            >
              <span class="material-symbols-outlined text-sm text-red-500">delete</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="showModal = false" />
        <div class="relative bg-white dark:bg-dark-surface rounded-2xl shadow-xl w-full max-w-md p-6 space-y-5">
          <h2 class="text-lg font-bold text-gray-900 dark:text-white">
            {{ editingId ? 'Edit' : 'Add' }} Salary Component
          </h2>

          <div class="space-y-4">
            <label class="flex flex-col gap-1.5">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Name</span>
              <input
                v-model="form.name"
                type="text"
                placeholder="e.g., Transport Allowance"
                class="w-full bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary/50 outline-none text-sm"
              />
            </label>

            <label class="flex flex-col gap-1.5">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Type</span>
              <select
                v-model="form.type"
                class="w-full px-3 py-2.5 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg text-gray-700 dark:text-gray-300 text-sm"
              >
                <option value="FIXED">Fixed (Tunjangan Tetap)</option>
                <option value="VARIABLE">Variable (Tunjangan Tidak Tetap)</option>
              </select>
              <p class="text-xs text-gray-400">
                {{ form.type === 'FIXED'
                  ? 'Always paid. Used in overtime hourly rate calculation.'
                  : 'Deducted on absent/leave days. NOT used in overtime calculation.'
                }}
              </p>
            </label>

            <label class="flex flex-col gap-1.5">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Description (optional)</span>
              <textarea
                v-model="form.description"
                rows="2"
                placeholder="Brief description..."
                class="w-full bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary/50 outline-none text-sm resize-none"
              />
            </label>
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <button @click="showModal = false" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
              Cancel
            </button>
            <button
              @click="saveComponent"
              :disabled="!form.name.trim()"
              class="bg-primary hover:bg-primary-600 text-white px-5 py-2 rounded-lg text-sm font-bold disabled:opacity-50 transition-all"
            >
              {{ editingId ? 'Update' : 'Create' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

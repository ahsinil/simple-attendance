<script setup>
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '@/services/api'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'

const toast = useToast()
const { confirm } = useConfirm()

const loading = ref(false)
const advances = ref([])
const showModal = ref(false)
const editingId = ref(null)

const users = ref([])

const form = ref({
  user_id: '',
  date: new Date().toISOString().split('T')[0],
  amount: '',
  notes: '',
})

onMounted(() => {
  fetchAdvances()
  fetchUsers()
})

async function fetchUsers() {
  try {
    const res = await adminApi.getUsers({ status: 'active' })
    if (res.data.success) {
      users.value = res.data.data.data || res.data.data
    }
  } catch (error) {
    console.error('Failed to load users', error)
  }
}

async function fetchAdvances() {
  loading.value = true
  try {
    const response = await adminApi.getCashAdvances()
    if (response.data.success) {
      advances.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load cash advances', error)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = {
    user_id: '',
    date: new Date().toISOString().split('T')[0],
    amount: '',
    notes: ''
  }
  showModal.value = true
}

function openEdit(advance) {
  editingId.value = advance.id
  form.value = {
    user_id: advance.user_id,
    date: advance.date,
    amount: advance.amount,
    notes: advance.notes || '',
  }
  showModal.value = true
}

async function saveAdvance() {
  if (!form.value.user_id || !form.value.date || !form.value.amount) {
    toast.error('Please fill required fields (User, Date, Amount)')
    return
  }

  try {
    if (editingId.value) {
      await adminApi.updateCashAdvance(editingId.value, form.value)
      toast.success('Kasbon updated successfully')
    } else {
      await adminApi.createCashAdvance(form.value)
      toast.success('Kasbon created successfully')
    }
    showModal.value = false
    fetchAdvances()
  } catch (error) {
    const msg = error.response?.data?.message || 'Failed to save Kasbon'
    toast.error(msg)
  }
}

async function deleteAdvance(advance) {
  const ok = await confirm({
    title: 'Hapus Kasbon',
    message: `Are you sure you want to delete this cash advance for ${advance.user?.name}?`,
    confirmText: 'Delete',
    type: 'danger',
  })
  if (!ok) return

  try {
    await adminApi.deleteCashAdvance(advance.id)
    toast.success('Kasbon deleted')
    fetchAdvances()
  } catch (error) {
    toast.error('Failed to delete kasbon')
  }
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount || 0)
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(date)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Kasbon / Cash Advances</h1>
        <p class="text-gray-500 dark:text-gray-400">Manage employee daily/weekly cash advances</p>
      </div>
      <button @click="openCreate" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        Tambah Kasbon
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700/50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Employee</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recorded By</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="loading">
              <td colspan="6" class="px-6 py-4 text-center text-gray-500">Loading...</td>
            </tr>
            <tr v-else-if="advances.length === 0">
              <td colspan="6" class="px-6 py-4 text-center text-gray-500">No cash advances found.</td>
            </tr>
            <tr v-for="adv in advances" :key="adv.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">
                {{ formatDate(adv.date) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ adv.user?.name }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">
                {{ formatCurrency(adv.amount) }}
              </td>
              <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">
                {{ adv.notes || '-' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                {{ adv.admin?.name || '-' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button @click="openEdit(adv)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                  Edit
                </button>
                <button @click="deleteAdvance(adv)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ editingId ? 'Edit Kasbon' : 'Tambah Kasbon' }}
          </h3>
          <button @click="showModal = false" class="text-gray-400 hover:text-gray-500">
            &times;
          </button>
        </div>
        
        <div class="p-6 space-y-4">
          <div v-if="!editingId">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Employee</label>
            <select v-model="form.user_id" class="w-full bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary/50 outline-none text-sm">
              <option value="">Select Employee</option>
              <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
            </select>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
            <input type="date" v-model="form.date" class="w-full bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary/50 outline-none text-sm" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount (IDR)</label>
            <input type="number" v-model="form.amount" min="0" step="1000" class="w-full bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary/50 outline-none text-sm" placeholder="e.g. 50000" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
            <textarea v-model="form.notes" rows="2" class="w-full bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-line rounded-lg px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary/50 outline-none text-sm" placeholder="Optional description"></textarea>
          </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-3">
          <button @click="showModal = false" class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg">
            Cancel
          </button>
          <button @click="saveAdvance" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Save
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

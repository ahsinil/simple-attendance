<script setup>
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '@/services/api'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'

const toast = useToast()
const { confirmDelete, confirm } = useConfirm()

const holidays = ref([])
const loading = ref(true)
const syncing = ref(false)

// State for Year filter/sync
const currentYear = new Date().getFullYear()
const selectedYear = ref(currentYear)
const years = Array.from({ length: 11 }, (_, i) => currentYear - 5 + i) // +/- 5 years

// Modal States
const showForm = ref(false)
const showSyncModal = ref(false)
const isEditing = ref(false)
const formLoading = ref(false)

const syncYear = ref(currentYear)

const form = ref({
  id: null,
  date: '',
  name: '',
  type: 'NATIONAL',
  overtime_multiplier: 2.0
})

onMounted(() => {
  fetchHolidays()
})

async function fetchHolidays() {
  loading.value = true
  try {
    const response = await adminApi.getHolidays({ year: selectedYear.value })
    if (response.data.success) {
      holidays.value = response.data.data
    }
  } catch (error) {
    toast.error('Gagal memuat data hari libur')
    console.error(error)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  form.value = {
    id: null,
    date: '',
    name: '',
    type: 'NATIONAL',
    overtime_multiplier: 2.0
  }
  isEditing.value = false
  showForm.value = true
}

function openEdit(holiday) {
  form.value = { ...holiday }
  isEditing.value = true
  showForm.value = true
}

async function saveHoliday() {
  if (!form.value.date || !form.value.name || !form.value.overtime_multiplier) {
    toast.error('Silakan lengkapi form')
    return
  }

  formLoading.value = true
  try {
    if (isEditing.value) {
      await adminApi.updateHoliday(form.value.id, form.value)
      toast.success('Hari libur berhasil diperbarui')
    } else {
      await adminApi.createHoliday(form.value)
      toast.success('Hari libur berhasil ditambahkan')
    }
    showForm.value = false
    fetchHolidays()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Gagal menyimpan data')
  } finally {
    formLoading.value = false
  }
}

async function deleteHoliday(holiday) {
  const confirmed = await confirmDelete(holiday.name)
  if (!confirmed) return

  try {
    await adminApi.deleteHoliday(holiday.id)
    toast.success('Hari libur dihapus')
    fetchHolidays()
  } catch (error) {
    toast.error('Gagal menghapus hari libur')
  }
}

async function confirmSync() {
  syncing.value = true
  try {
    const response = await adminApi.syncHolidays(syncYear.value)
    if (response.data.success) {
      toast.success(response.data.message || 'Sinkronisasi berhasil')
      if (selectedYear.value !== syncYear.value) {
        selectedYear.value = syncYear.value
      }
      fetchHolidays()
      showSyncModal.value = false
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Gagal mensinkronisasi data')
  } finally {
    syncing.value = false
  }
}

function formatDateDisplay(dateStr) {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  }).format(date)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Hari Libur (Holidays)</h1>
        <p class="text-sm text-gray-500 mt-1">Manajemen hari libur nasional dan cuti bersama.</p>
      </div>
      <div class="flex gap-2">
        <button @click="showSyncModal = true" class="btn border border-gray-300 dark:border-dark-border bg-white dark:bg-dark-surface hover:bg-gray-50 dark:hover:bg-dark-bg text-gray-700 dark:text-gray-300">
          <span class="material-symbols-outlined text-sm">sync</span>
          Sync Internet
        </button>
        <button @click="openCreate" class="btn btn-primary">
          <span class="material-symbols-outlined text-sm">add</span>
          Tambah Manual
        </button>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="flex items-center gap-3">
      <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Tahun:</label>
      <select v-model="selectedYear" @change="fetchHolidays" class="input py-1.5 w-32">
        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
      </select>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden">
      <div v-if="loading" class="p-8 flex justify-center">
        <span class="material-symbols-outlined animate-spin text-3xl text-gray-400">progress_activity</span>
      </div>

      <div v-else-if="holidays.length === 0" class="p-12 text-center flex flex-col items-center">
        <span class="material-symbols-outlined text-5xl text-gray-300 mb-3">event_busy</span>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Belum Ada Data</h3>
        <p class="text-sm text-gray-500 mt-1 mb-4">Tidak ada data hari libur untuk tahun {{ selectedYear }}.</p>
        <button @click="showSyncModal = true" class="btn btn-primary text-sm">
          Sync Otomatis Sekarang
        </button>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left">
          <thead class="bg-gray-50 dark:bg-dark-border/50 text-xs uppercase text-gray-500 font-semibold tracking-wider">
            <tr>
              <th class="px-4 py-3">Tanggal</th>
              <th class="px-4 py-3">Nama Libur</th>
              <th class="px-4 py-3">Jenis</th>
              <th class="px-4 py-3 text-center">Overtime Multiplier</th>
              <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-dark-border text-sm">
            <tr v-for="h in holidays" :key="h.id" class="hover:bg-gray-50 dark:hover:bg-dark-bg transition-colors">
              <td class="px-4 py-3 whitespace-nowrap text-gray-900 dark:text-white font-medium">
                {{ formatDateDisplay(h.date) }}
              </td>
              <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                {{ h.name }}
              </td>
              <td class="px-4 py-3">
                <span 
                  class="px-2 py-1 text-xs rounded-full font-medium"
                  :class="{
                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': h.type === 'NATIONAL',
                    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': h.type === 'COMPANY',
                    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300': h.type === 'OPTIONAL',
                  }"
                >
                  {{ h.type }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center gap-1 font-bold text-amber-600 dark:text-amber-400">
                  {{ Number(h.overtime_multiplier).toFixed(1) }}x
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <button @click="openEdit(h)" class="p-1.5 hover:bg-gray-100 dark:hover:bg-dark-border rounded text-blue-500 transition-colors" title="Edit">
                  <span class="material-symbols-outlined text-sm">edit</span>
                </button>
                <button @click="deleteHoliday(h)" class="p-1.5 hover:bg-gray-100 dark:hover:bg-dark-border rounded text-red-500 transition-colors" title="Hapus">
                  <span class="material-symbols-outlined text-sm">delete</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Manual Form Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white dark:bg-dark-surface rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-dark-border flex items-center justify-between">
          <h3 class="font-bold text-lg text-gray-900 dark:text-white">
            {{ isEditing ? 'Edit Hari Libur' : 'Tambah Hari Libur' }}
          </h3>
          <button @click="showForm = false" class="text-gray-400 hover:text-gray-600">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal</label>
            <input v-model="form.date" type="date" class="input w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Hari Libur</label>
            <input v-model="form.name" type="text" placeholder="Contoh: Tahun Baru" class="input w-full" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jenis</label>
              <select v-model="form.type" class="input w-full">
                <option value="NATIONAL">Nasional</option>
                <option value="COMPANY">Cuti Perusahaan</option>
                <option value="OPTIONAL">Opsional</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Overtime Multiplier</label>
              <div class="relative">
                <input v-model.number="form.overtime_multiplier" type="number" step="0.5" min="1" class="input w-full pr-8" />
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold">x</span>
              </div>
            </div>
          </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 dark:bg-dark-bg flex justify-end gap-3">
          <button @click="showForm = false" class="btn btn-secondary">Batal</button>
          <button @click="saveHoliday" :disabled="formLoading" class="btn btn-primary">
            {{ formLoading ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Sync Modal -->
    <div v-if="showSyncModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white dark:bg-dark-surface rounded-xl shadow-xl w-full max-w-sm overflow-hidden text-center">
        <div class="p-6">
          <div class="mx-auto size-12 bg-blue-100 dark:bg-blue-900/30 text-blue-500 rounded-full flex items-center justify-center mb-4">
            <span class="material-symbols-outlined text-2xl">sync</span>
          </div>
          <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-2">Sync dari Internet</h3>
          <p class="text-sm text-gray-500 mb-6">
            Pilih tahun untuk menarik kalender libur nasional Indonesia secara otomatis via Google Calendar.
          </p>
          
          <select v-model="syncYear" class="input w-full text-center text-lg py-3 mb-2 font-bold text-primary">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>

        <div class="px-6 py-4 bg-gray-50 dark:bg-dark-bg flex gap-3">
          <button @click="showSyncModal = false" class="btn btn-secondary flex-1">Batal</button>
          <button @click="confirmSync" :disabled="syncing" class="btn btn-primary flex-1">
            <span v-if="syncing" class="material-symbols-outlined animate-spin text-sm">progress_activity</span>
            {{ syncing ? 'Syncing...' : 'Mulai Sync' }}
          </button>
        </div>
      </div>
    </div>
    
  </div>
</template>

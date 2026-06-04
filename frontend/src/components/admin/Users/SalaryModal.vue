<script setup>
import { ref, watch, computed } from 'vue'
import { adminApi } from '@/services/api'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
  show: {
    type: Boolean,
    required: true
  },
  user: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'saved'])
const toast = useToast()
const { confirm } = useConfirm()

const loadingSalary = ref(false)
const salaryData = ref({ base_salary: 0, components: [], total_fixed: 0, total_variable: 0, hourly_rate: 0 })
const availableComponents = ref([])
const salaryForm = ref({ base_salary: 0, components: [] })

// Monthly working days for daily rate preview (approximate)
const MONTHLY_WORK_DAYS = 22

// ─── Additional (Custom) Allowances ───
const additionalAllowances = ref([])
const loadingAdditional = ref(false)
const showAddForm = ref(false)
const editingAllowance = ref(null)

// Default period = current month/year
const now = new Date()
const additionalForm = ref({
  name: '',
  amount: 0,
  description: '',
  period_year: now.getFullYear(),
  period_month: now.getMonth() + 1,
})

const MONTHS = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

watch(() => props.show, (newVal) => {
  if (newVal && props.user) {
    loadSalaryData()
    loadAdditionalAllowances()
  } else {
    resetAddForm()
  }
})

async function loadSalaryData() {
  loadingSalary.value = true
  try {
    const [salaryRes, compRes] = await Promise.all([
      adminApi.getUserSalary(props.user.id),
      adminApi.getSalaryComponents(),
    ])
    if (salaryRes.data.success) {
      salaryData.value = salaryRes.data.data
      salaryForm.value = {
        base_salary: salaryRes.data.data.base_salary || 0,
        components: (salaryRes.data.data.components || []).map(c => ({
          salary_component_id: c.salary_component_id,
          amount: c.amount,
          name: c.salary_component?.name,
          type: c.salary_component?.type,
        })),
      }
    }
    if (compRes.data.success) {
      availableComponents.value = compRes.data.data.filter(c => c.is_active)
    }
  } catch (error) {
    console.error('Failed to load salary data:', error)
  } finally {
    loadingSalary.value = false
  }
}

async function loadAdditionalAllowances() {
  if (!props.user) return
  loadingAdditional.value = true
  try {
    const res = await adminApi.getUserAdditionalAllowances(props.user.id)
    if (res.data.success) {
      additionalAllowances.value = res.data.data
    }
  } catch (error) {
    console.error('Failed to load additional allowances:', error)
  } finally {
    loadingAdditional.value = false
  }
}

function resetAddForm() {
  const d = new Date()
  additionalForm.value = {
    name: '',
    amount: 0,
    description: '',
    period_year: d.getFullYear(),
    period_month: d.getMonth() + 1,
  }
  editingAllowance.value = null
  showAddForm.value = false
}

function openEditAllowance(allowance) {
  editingAllowance.value = allowance
  additionalForm.value = {
    name: allowance.name,
    amount: parseFloat(allowance.amount),
    description: allowance.description || '',
    period_year: allowance.period_year,
    period_month: allowance.period_month,
  }
  showAddForm.value = true
}

async function saveAdditionalAllowance() {
  if (!additionalForm.value.name.trim() || additionalForm.value.amount <= 0) {
    toast.error('Nama dan nominal harus diisi')
    return
  }
  try {
    if (editingAllowance.value) {
      await adminApi.updateUserAdditionalAllowance(props.user.id, editingAllowance.value.id, additionalForm.value)
      toast.success('Tunjangan tambahan berhasil diperbarui')
    } else {
      await adminApi.addUserAdditionalAllowance(props.user.id, additionalForm.value)
      toast.success('Tunjangan tambahan berhasil ditambahkan')
    }
    resetAddForm()
    loadAdditionalAllowances()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Gagal menyimpan tunjangan tambahan')
  }
}

async function deleteAdditionalAllowance(allowance) {
  const ok = await confirm({
    title: 'Hapus Tunjangan Tambahan',
    message: `Hapus "${allowance.name}" (${formatPeriod(allowance.period_year, allowance.period_month)})?`,
    confirmText: 'Hapus',
    type: 'danger',
  })
  if (!ok) return
  try {
    await adminApi.deleteUserAdditionalAllowance(props.user.id, allowance.id)
    toast.success('Tunjangan tambahan dihapus')
    loadAdditionalAllowances()
  } catch {
    toast.error('Gagal menghapus')
  }
}

function addSalaryComponent(comp) {
  if (salaryForm.value.components.find(c => c.salary_component_id === comp.id)) return
  salaryForm.value.components.push({
    salary_component_id: comp.id,
    amount: 0,
    name: comp.name,
    type: comp.type,
  })
}

function removeSalaryComponent(index) {
  salaryForm.value.components.splice(index, 1)
}

async function saveSalary() {
  try {
    await adminApi.updateUserSalary(props.user.id, {
      base_salary: salaryForm.value.base_salary,
      components: salaryForm.value.components.map(c => ({
        salary_component_id: c.salary_component_id,
        amount: c.amount,
      })),
    })
    toast.success('Salary updated successfully')
    emit('saved')
    closeModal()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to update salary')
  }
}

function formatCurrency(amount) {
  if (amount == null || amount == 0) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount)
}

function formatPeriod(year, month) {
  return `${MONTHS[month - 1]} ${year}`
}

function closeModal() {
  resetAddForm()
  emit('close')
}

// Computed: komponen yang sudah di-assign per type
const fixedComponents = computed(() => salaryForm.value.components.filter(c => c.type === 'FIXED'))
const variableComponents = computed(() => salaryForm.value.components.filter(c => c.type === 'VARIABLE'))

// Komponen yang tersedia untuk ditambahkan, per type
const availableFixed = computed(() =>
  availableComponents.value.filter(c =>
    c.type === 'FIXED' && !salaryForm.value.components.find(sc => sc.salary_component_id === c.id)
  )
)
const availableVariable = computed(() =>
  availableComponents.value.filter(c =>
    c.type === 'VARIABLE' && !salaryForm.value.components.find(sc => sc.salary_component_id === c.id)
  )
)

// Totals
const totalFixed = computed(() => fixedComponents.value.reduce((s, c) => s + (parseFloat(c.amount) || 0), 0))
const totalVariable = computed(() => variableComponents.value.reduce((s, c) => s + (parseFloat(c.amount) || 0), 0))
const totalAdditional = computed(() => additionalAllowances.value.reduce((s, a) => s + (parseFloat(a.amount) || 0), 0))
const baseSalary = computed(() => parseFloat(salaryForm.value.base_salary) || 0)
const otRate = computed(() => (baseSalary.value + totalFixed.value) / 173)
const dailyVariableRate = computed(() => totalVariable.value / MONTHLY_WORK_DAYS)

// Index dalam components array (untuk remove by index)
function getComponentIndex(comp) {
  return salaryForm.value.components.findIndex(c => c.salary_component_id === comp.salary_component_id)
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="card w-full max-w-xl max-h-[92vh] overflow-y-auto bg-white dark:bg-dark-surface flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-dark-border flex-shrink-0">
        <div>
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">Kelola Gaji & Tunjangan</h3>
          <p class="text-sm text-gray-500 mt-0.5">{{ user?.name }}</p>
        </div>
        <button @click="closeModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-dark-border rounded-lg transition-colors">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div v-if="loadingSalary" class="text-center py-10 text-gray-500 flex-1 flex items-center justify-center">
        <div class="flex flex-col items-center gap-2">
          <span class="material-symbols-outlined animate-spin text-3xl text-gray-400">progress_activity</span>
          <span class="text-sm">Memuat data gaji...</span>
        </div>
      </div>

      <div v-else class="flex-1 overflow-y-auto">
        <div class="p-6 space-y-6">

          <!-- Base Salary -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
              Gaji Pokok (Base Salary)
            </label>
            <input
              v-model.number="salaryForm.base_salary"
              type="number"
              class="input"
              placeholder="0"
              min="0"
            />
            <p class="text-xs text-gray-400 mt-1">
              OT Rate: <span class="font-medium text-gray-600 dark:text-gray-300">{{ formatCurrency(otRate) }}/jam</span>
              <span class="text-gray-400"> (Gaji Pokok + Tunjangan Tetap) ÷ 173</span>
            </p>
          </div>

          <!-- ───── TUNJANGAN TETAP (FIXED) ───── -->
          <div class="rounded-xl border border-blue-100 dark:border-blue-900/40 overflow-hidden">
            <!-- Section header -->
            <div class="flex items-center gap-3 px-4 py-3 bg-blue-50 dark:bg-blue-900/20">
              <span class="p-1.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded-lg">
                <span class="material-symbols-outlined text-base">lock</span>
              </span>
              <div class="flex-1 min-w-0">
                <h4 class="font-bold text-blue-800 dark:text-blue-300 text-sm">Tunjangan Tetap (Fixed)</h4>
                <p class="text-xs text-blue-600 dark:text-blue-400">Dibayar penuh setiap bulan, termasuk dalam perhitungan lembur</p>
              </div>
              <span class="text-sm font-bold text-blue-700 dark:text-blue-300 whitespace-nowrap">
                {{ formatCurrency(totalFixed) }}
              </span>
            </div>

            <div class="p-4 space-y-3">
              <!-- Assigned fixed components -->
              <div v-if="fixedComponents.length === 0" class="text-sm text-gray-400 py-1">
                Belum ada tunjangan tetap yang diberikan.
              </div>
              <div
                v-for="comp in fixedComponents"
                :key="comp.salary_component_id"
                class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-dark-bg rounded-lg"
              >
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ comp.name }}</p>
                  <p class="text-xs text-gray-400">Dibayar setiap bulan</p>
                </div>
                <input
                  v-model.number="comp.amount"
                  type="number"
                  class="input w-36 text-right"
                  placeholder="Nominal"
                  min="0"
                />
                <button
                  @click="removeSalaryComponent(getComponentIndex(comp))"
                  class="p-1.5 hover:bg-red-100 dark:hover:bg-red-900/20 rounded-lg text-red-500 transition-colors flex-shrink-0"
                  title="Hapus"
                >
                  <span class="material-symbols-outlined text-sm">delete</span>
                </button>
              </div>

              <!-- Add fixed component buttons -->
              <div v-if="availableFixed.length > 0" class="flex flex-wrap gap-2 pt-1">
                <button
                  v-for="comp in availableFixed"
                  :key="comp.id"
                  @click="addSalaryComponent(comp)"
                  class="text-xs px-3 py-1.5 rounded-full border border-blue-200 dark:border-blue-800 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-blue-600 dark:text-blue-400 transition-colors font-medium"
                >
                  <span class="mr-1">+</span>{{ comp.name }}
                </button>
              </div>
              <p v-else-if="fixedComponents.length > 0" class="text-xs text-gray-400 pt-1">
                Semua komponen tetap sudah diberikan.
              </p>
            </div>
          </div>

          <!-- ───── TUNJANGAN TIDAK TETAP (VARIABLE) ───── -->
          <div class="rounded-xl border border-amber-100 dark:border-amber-900/40 overflow-hidden">
            <!-- Section header -->
            <div class="flex items-center gap-3 px-4 py-3 bg-amber-50 dark:bg-amber-900/20">
              <span class="p-1.5 bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 rounded-lg">
                <span class="material-symbols-outlined text-base">sync_alt</span>
              </span>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <h4 class="font-bold text-amber-800 dark:text-amber-300 text-sm">Tunjangan Tidak Tetap (Variable)</h4>
                  <span class="text-xs px-1.5 py-0.5 rounded-full bg-amber-200 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 font-medium">Opsional</span>
                </div>
                <p class="text-xs text-amber-600 dark:text-amber-400">Dibayar hanya pada hari karyawan hadir · Tidak termasuk dalam kalkulasi lembur</p>
              </div>
              <span class="text-sm font-bold text-amber-700 dark:text-amber-300 whitespace-nowrap">
                {{ formatCurrency(totalVariable) }}<span class="text-xs font-normal text-amber-500">/bln</span>
              </span>
            </div>

            <div class="p-4 space-y-3">
              <!-- Daily rate info -->
              <div v-if="totalVariable > 0" class="flex items-center gap-2 px-3 py-2 bg-amber-50 dark:bg-amber-900/10 rounded-lg">
                <span class="material-symbols-outlined text-sm text-amber-500">info</span>
                <p class="text-xs text-amber-700 dark:text-amber-400">
                  Rate harian: <span class="font-bold">{{ formatCurrency(dailyVariableRate) }}</span>/hari
                  <span class="text-amber-500"> (asumsi {{ MONTHLY_WORK_DAYS }} hari kerja)</span>
                </p>
              </div>

              <!-- Assigned variable components -->
              <div v-if="variableComponents.length === 0" class="text-sm text-gray-400 py-1">
                Tidak ada tunjangan tidak tetap. Karyawan ini tidak akan menerima tunjangan harian.
              </div>
              <div
                v-for="comp in variableComponents"
                :key="comp.salary_component_id"
                class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-dark-bg rounded-lg"
              >
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ comp.name }}</p>
                  <p class="text-xs text-gray-400">
                    {{ formatCurrency(comp.amount > 0 ? comp.amount / MONTHLY_WORK_DAYS : 0) }}/hari
                  </p>
                </div>
                <input
                  v-model.number="comp.amount"
                  type="number"
                  class="input w-36 text-right"
                  placeholder="Nominal/bln"
                  min="0"
                />
                <button
                  @click="removeSalaryComponent(getComponentIndex(comp))"
                  class="p-1.5 hover:bg-red-100 dark:hover:bg-red-900/20 rounded-lg text-red-500 transition-colors flex-shrink-0"
                  title="Hapus"
                >
                  <span class="material-symbols-outlined text-sm">delete</span>
                </button>
              </div>

              <!-- Add variable component buttons -->
              <div v-if="availableVariable.length > 0" class="flex flex-wrap gap-2 pt-1">
                <button
                  v-for="comp in availableVariable"
                  :key="comp.id"
                  @click="addSalaryComponent(comp)"
                  class="text-xs px-3 py-1.5 rounded-full border border-amber-200 dark:border-amber-800 hover:bg-amber-50 dark:hover:bg-amber-900/30 text-amber-600 dark:text-amber-400 transition-colors font-medium"
                >
                  <span class="mr-1">+</span>{{ comp.name }}
                </button>
              </div>
              <p v-else-if="variableComponents.length > 0" class="text-xs text-gray-400 pt-1">
                Semua komponen tidak tetap sudah diberikan.
              </p>
            </div>
          </div>

          <!-- ───── TUNJANGAN TAMBAHAN CUSTOM ───── -->
          <div class="rounded-xl border border-purple-100 dark:border-purple-900/40 overflow-hidden">
            <!-- Section header -->
            <div class="flex items-center gap-3 px-4 py-3 bg-purple-50 dark:bg-purple-900/20">
              <span class="p-1.5 bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 rounded-lg">
                <span class="material-symbols-outlined text-base">add_circle</span>
              </span>
              <div class="flex-1 min-w-0">
                <h4 class="font-bold text-purple-800 dark:text-purple-300 text-sm">Tunjangan Tambahan (Custom)</h4>
                <p class="text-xs text-purple-600 dark:text-purple-400">Ad-hoc per periode bulan, langsung dijumlahkan ke total payroll</p>
              </div>
              <span class="text-sm font-bold text-purple-700 dark:text-purple-300 whitespace-nowrap">
                {{ formatCurrency(totalAdditional) }}
              </span>
            </div>

            <div class="p-4 space-y-3">
              <!-- Loading state -->
              <div v-if="loadingAdditional" class="flex justify-center py-4">
                <span class="material-symbols-outlined animate-spin text-2xl text-purple-400">progress_activity</span>
              </div>

              <template v-else>
                <!-- List of existing additional allowances -->
                <div v-if="additionalAllowances.length === 0 && !showAddForm" class="text-sm text-gray-400 py-1">
                  Belum ada tunjangan tambahan.
                </div>

                <div
                  v-for="allowance in additionalAllowances"
                  :key="allowance.id"
                  class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-dark-bg rounded-lg"
                >
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                      <p class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ allowance.name }}</p>
                      <span class="text-xs px-1.5 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 font-medium whitespace-nowrap">
                        {{ formatPeriod(allowance.period_year, allowance.period_month) }}
                      </span>
                    </div>
                    <p v-if="allowance.description" class="text-xs text-gray-400 mt-0.5 truncate">{{ allowance.description }}</p>
                  </div>
                  <span class="font-semibold text-purple-700 dark:text-purple-300 text-sm whitespace-nowrap">
                    {{ formatCurrency(allowance.amount) }}
                  </span>
                  <div class="flex items-center gap-1 flex-shrink-0">
                    <button
                      @click="openEditAllowance(allowance)"
                      class="p-1.5 hover:bg-purple-100 dark:hover:bg-purple-900/20 rounded-lg text-purple-500 transition-colors"
                      title="Edit"
                    >
                      <span class="material-symbols-outlined text-sm">edit</span>
                    </button>
                    <button
                      @click="deleteAdditionalAllowance(allowance)"
                      class="p-1.5 hover:bg-red-100 dark:hover:bg-red-900/20 rounded-lg text-red-500 transition-colors"
                      title="Hapus"
                    >
                      <span class="material-symbols-outlined text-sm">delete</span>
                    </button>
                  </div>
                </div>

                <!-- Inline Add/Edit Form -->
                <div v-if="showAddForm" class="border border-purple-200 dark:border-purple-800 rounded-xl p-4 space-y-3 bg-purple-50/50 dark:bg-purple-900/10">
                  <h5 class="text-sm font-semibold text-purple-800 dark:text-purple-300">
                    {{ editingAllowance ? 'Edit Tunjangan Tambahan' : 'Tambah Tunjangan Baru' }}
                  </h5>
                  <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                      <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Nama Tunjangan *</label>
                      <input
                        v-model="additionalForm.name"
                        type="text"
                        placeholder="Contoh: Bonus Proyek, THR, Insentif"
                        class="input w-full text-sm"
                      />
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Nominal (Rp) *</label>
                      <input
                        v-model.number="additionalForm.amount"
                        type="number"
                        placeholder="0"
                        min="0"
                        class="input w-full text-sm text-right"
                      />
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Periode *</label>
                      <div class="flex gap-1.5">
                        <select v-model.number="additionalForm.period_month" class="input flex-1 text-sm">
                          <option v-for="(m, i) in MONTHS" :key="i" :value="i + 1">{{ m }}</option>
                        </select>
                        <input
                          v-model.number="additionalForm.period_year"
                          type="number"
                          placeholder="2026"
                          min="2020"
                          max="2100"
                          class="input w-20 text-sm"
                        />
                      </div>
                    </div>
                    <div class="col-span-2">
                      <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Keterangan (opsional)</label>
                      <input
                        v-model="additionalForm.description"
                        type="text"
                        placeholder="Keterangan tambahan..."
                        class="input w-full text-sm"
                      />
                    </div>
                  </div>
                  <div class="flex justify-end gap-2 pt-1">
                    <button @click="resetAddForm" class="text-xs px-3 py-1.5 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-dark-border transition-colors">
                      Batal
                    </button>
                    <button
                      @click="saveAdditionalAllowance"
                      :disabled="!additionalForm.name.trim() || additionalForm.amount <= 0"
                      class="text-xs px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white font-semibold disabled:opacity-50 transition-colors"
                    >
                      {{ editingAllowance ? 'Perbarui' : 'Tambahkan' }}
                    </button>
                  </div>
                </div>

                <!-- Add Button -->
                <button
                  v-if="!showAddForm"
                  @click="showAddForm = true"
                  class="w-full flex items-center justify-center gap-2 py-2 rounded-lg border border-dashed border-purple-300 dark:border-purple-700 text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 text-xs font-medium transition-colors"
                >
                  <span class="material-symbols-outlined text-sm">add</span>
                  Tambah Tunjangan Custom
                </button>
              </template>
            </div>
          </div>

        </div>
      </div>

      <!-- Footer Summary + Actions -->
      <div v-if="!loadingSalary" class="flex-shrink-0 border-t border-gray-100 dark:border-dark-border p-6 space-y-3">
        <!-- Summary rows -->
        <div class="space-y-1.5 text-sm">
          <div class="flex items-center justify-between text-gray-500">
            <span>Gaji Pokok</span>
            <span>{{ formatCurrency(baseSalary) }}</span>
          </div>
          <div class="flex items-center justify-between text-blue-600 dark:text-blue-400">
            <span>Tunjangan Tetap</span>
            <span>{{ formatCurrency(totalFixed) }}</span>
          </div>
          <div class="flex items-center justify-between text-amber-600 dark:text-amber-400">
            <span>Tunjangan Tidak Tetap <span class="text-xs">(maks/bln)</span></span>
            <span>{{ formatCurrency(totalVariable) }}</span>
          </div>
          <div v-if="totalAdditional > 0" class="flex items-center justify-between text-purple-600 dark:text-purple-400">
            <span>Tunjangan Tambahan <span class="text-xs">(semua periode)</span></span>
            <span>{{ formatCurrency(totalAdditional) }}</span>
          </div>
          <div class="flex items-center justify-between font-bold text-gray-900 dark:text-white pt-1.5 border-t border-gray-100 dark:border-dark-border">
            <span>Total Gaji Maks.</span>
            <span>{{ formatCurrency(baseSalary + totalFixed + totalVariable) }}</span>
          </div>
        </div>

        <div class="flex gap-3 justify-end pt-1">
          <button @click="closeModal" class="btn btn-secondary">Batal</button>
          <button @click="saveSalary" class="btn btn-primary">Simpan</button>
        </div>
      </div>
    </div>
  </div>
</template>

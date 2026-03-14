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
  }
})

const emit = defineEmits(['close', 'saved'])
const toast = useToast()

const loadingSalary = ref(false)
const salaryData = ref({ base_salary: 0, components: [], total_fixed: 0, total_variable: 0, hourly_rate: 0 })
const availableComponents = ref([])
const salaryForm = ref({ base_salary: 0, components: [] })

watch(() => props.show, (newVal) => {
  if (newVal && props.user) {
    loadSalaryData()
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

function closeModal() {
  emit('close')
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="card p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto bg-white dark:bg-dark-surface">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          Salary - {{ user?.name }}
        </h3>
        <button @click="closeModal" class="p-1 hover:bg-gray-100 dark:hover:bg-dark-border rounded">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div v-if="loadingSalary" class="text-center py-8 text-gray-500">Loading...</div>

      <div v-else class="space-y-5">
        <!-- Base Salary -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Base Salary (Gaji Pokok)</label>
          <input v-model.number="salaryForm.base_salary" type="number" class="input" placeholder="0" min="0" />
        </div>

        <!-- Assigned Components -->
        <div>
          <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Allowances</h4>
          <div v-if="salaryForm.components.length === 0" class="text-sm text-gray-400 py-2">No allowances assigned.</div>
          <div v-else class="space-y-2">
            <div v-for="(comp, index) in salaryForm.components" :key="index"
                 class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-dark-border rounded-lg">
              <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ comp.name }}</p>
                <span class="text-xs px-1.5 py-0.5 rounded-full"
                      :class="comp.type === 'FIXED' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-600'">
                  {{ comp.type }}
                </span>
              </div>
              <input v-model.number="comp.amount" type="number" class="input w-36" placeholder="Amount" min="0" />
              <button @click="removeSalaryComponent(index)" class="p-1 hover:bg-red-100 dark:hover:bg-red-900/20 rounded text-red-500">
                <span class="material-symbols-outlined text-sm">close</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Add Component -->
        <div>
          <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Add Allowance</h4>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="comp in availableComponents.filter(c => !salaryForm.components.find(sc => sc.salary_component_id === c.id))"
              :key="comp.id"
              @click="addSalaryComponent(comp)"
              class="text-xs px-3 py-1.5 rounded-full border border-gray-200 dark:border-dark-line hover:bg-primary/10 hover:border-primary text-gray-600 dark:text-gray-300 transition-colors"
            >
              + {{ comp.name }}
            </button>
            <span v-if="availableComponents.filter(c => !salaryForm.components.find(sc => sc.salary_component_id === c.id)).length === 0"
                  class="text-xs text-gray-400">All components assigned</span>
          </div>
        </div>

        <!-- Save -->
        <div class="flex flex-col gap-3 pt-4 border-t border-gray-200 dark:border-dark-border">
          <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500">Total Salary</span>
            <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency((parseFloat(salaryForm.base_salary) || 0) + salaryForm.components.reduce((s, c) => s + (parseFloat(c.amount) || 0), 0)) }}</span>
          </div>
          <div class="flex items-center justify-between text-xs text-gray-400">
            <span>OT Rate: {{ formatCurrency(((parseFloat(salaryForm.base_salary) || 0) + salaryForm.components.filter(c => c.type === 'FIXED').reduce((s, c) => s + (parseFloat(c.amount) || 0), 0)) / 173) }}/hr</span>
          </div>
          <div class="flex gap-3 justify-end">
            <button @click="closeModal" class="btn btn-secondary">Cancel</button>
            <button @click="saveSalary" class="btn btn-primary">Save Salary</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

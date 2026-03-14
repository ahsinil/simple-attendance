<script setup>
import { ref, watch } from 'vue'
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
  },
  shifts: {
    type: Array,
    required: true
  }
})

const emit = defineEmits(['close', 'changed'])
const toast = useToast()
const { confirmAction } = useConfirm()

const userSchedules = ref([])
const loadingSchedules = ref(false)
const scheduleForm = ref({
  shift_id: '',
  start_date: '',
  end_date: '',
})

watch(() => props.show, (newVal) => {
  if (newVal && props.user) {
    scheduleForm.value = { shift_id: '', start_date: '', end_date: '' }
    fetchUserSchedules(props.user.id)
  }
})

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
    await adminApi.assignSchedule(props.user.id, {
      shift_id: scheduleForm.value.shift_id,
      start_date: scheduleForm.value.start_date,
      end_date: scheduleForm.value.end_date || null,
    })
    scheduleForm.value = { shift_id: '', start_date: '', end_date: '' }
    toast.success('Schedule assigned successfully')
    await fetchUserSchedules(props.user.id)
    emit('changed')
  } catch (error) {
    toast.error(error.response?.data?.message || error.response?.data?.error || 'Failed to assign schedule')
  }
}

async function removeSchedule(schedule) {
  const confirmed = await confirmAction('Remove this schedule?', 'Remove Schedule')
  if (!confirmed) return
  try {
    await adminApi.removeSchedule(props.user.id, schedule.id)
    toast.success('Schedule removed')
    await fetchUserSchedules(props.user.id)
    emit('changed')
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

function closeModal() {
  emit('close')
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="card p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto bg-white dark:bg-dark-surface">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          Manage Shifts - {{ user?.name }}
        </h3>
        <button @click="closeModal" class="p-1 hover:bg-gray-100 dark:hover:bg-dark-border rounded">
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
        <button @click="closeModal" class="btn btn-secondary">Close</button>
      </div>
    </div>
  </div>
</template>

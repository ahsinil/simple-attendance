<script setup>
import { ref, computed, onMounted } from 'vue'
import { attendanceApi } from '@/services/api'

const loading = ref(true)
const error = ref(null)
const schedules = ref([])
const selectedSchedule = ref(null)
const showModal = ref(false)

// Current view and date state
const currentView = ref('month') // month, week, day
const currentDate = ref(new Date())

onMounted(async () => {
  await fetchSchedules()
})

async function fetchSchedules() {
  loading.value = true
  error.value = null
  try {
    const response = await attendanceApi.mySchedules()
    if (response.data.success) {
      schedules.value = response.data.data.schedules
    }
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to load schedules'
  } finally {
    loading.value = false
  }
}

// Calendar helpers
const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

// Dynamic view state
const activeWeekDays = computed(() => {
  if (currentView.value === 'day') {
    return [weekDays[currentDate.value.getDay()]]
  }
  return weekDays
})

const currentMonthYear = computed(() => {
  if (currentView.value === 'day') {
    return currentDate.value.toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  }
  return currentDate.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const visibleDays = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  const current = currentDate.value
  
  if (currentView.value === 'month') {
    // First day of the month
    const firstDay = new Date(year, month, 1)
    // Last day of the month
    const lastDay = new Date(year, month + 1, 0)
    
    // Start from the Sunday of the first week
    const startDate = new Date(firstDay)
    startDate.setDate(startDate.getDate() - firstDay.getDay())
    
    // End at the Saturday of the last week
    const endDate = new Date(lastDay)
    endDate.setDate(endDate.getDate() + (6 - lastDay.getDay()))
    
    const days = []
    const iter = new Date(startDate)
    
    while (iter <= endDate) {
      days.push(new Date(iter))
      iter.setDate(iter.getDate() + 1)
    }
    return days

  } else if (currentView.value === 'week') {
    const startDate = new Date(current)
    startDate.setDate(current.getDate() - current.getDay()) // Start of week (Sunday)
    
    const days = []
    for (let i = 0; i < 7; i++) {
      const d = new Date(startDate)
      d.setDate(startDate.getDate() + i)
      days.push(d)
    }
    return days

  } else { // day view
    return [new Date(current)]
  }
})

const calendarWeeks = computed(() => {
  if (currentView.value === 'day') {
    return [visibleDays.value]
  }
  
  const weeks = []
  const days = visibleDays.value
  for (let i = 0; i < days.length; i += 7) {
    weeks.push(days.slice(i, i + 7))
  }
  return weeks
})

function isToday(date) {
  const today = new Date()
  return date.getDate() === today.getDate() &&
         date.getMonth() === today.getMonth() &&
         date.getFullYear() === today.getFullYear()
}

function isCurrentMonth(date) {
  // Always highlight as 'current' in week/day view to keep background white
  if (currentView.value !== 'month') return true
  return date.getMonth() === currentDate.value.getMonth()
}

function getSchedulesForDate(date) {
  const dateStr = formatDateStr(date)
  return schedules.value.filter(s => {
    const startDate = s.start_date
    const endDate = s.end_date
    return dateStr >= startDate && (!endDate || dateStr <= endDate)
  }).sort((a, b) => {
    // Sort by start time ascending
    const timeA = a.shift?.start_time || '00:00:00'
    const timeB = b.shift?.start_time || '00:00:00'
    return timeA.localeCompare(timeB)
  })
}

function formatDateStr(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function formatTime24(time) {
  if (!time) return '-'
  return time.substring(0, 5)
}

function formatTime12(time) {
  if (!time) return '-'
  const [hours, minutes] = time.split(':')
  const hour = parseInt(hours)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const hour12 = hour % 12 || 12
  return `${String(hour12).padStart(2, '0')}:${minutes} ${ampm}`
}

function calculateDuration(startTime, endTime) {
  if (!startTime || !endTime) return '-'
  const [sh, sm] = startTime.split(':').map(Number)
  const [eh, em] = endTime.split(':').map(Number)
  let diff = (eh * 60 + em) - (sh * 60 + sm)
  if (diff < 0) diff += 24 * 60
  const hours = Math.floor(diff / 60)
  return `${hours} hours`
}

function navigateMonth(delta) {
  const newDate = new Date(currentDate.value)
  if (currentView.value === 'month') {
    newDate.setMonth(newDate.getMonth() + delta)
  } else if (currentView.value === 'week') {
    newDate.setDate(newDate.getDate() + (delta * 7))
  } else { // day
    newDate.setDate(newDate.getDate() + delta)
  }
  currentDate.value = newDate
}

function goToToday() {
  currentDate.value = new Date()
}

function openScheduleDetail(schedule, date) {
  selectedSchedule.value = { ...schedule, selectedDate: date }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  selectedSchedule.value = null
}

function getMonthAbbr(date) {
  return date.toLocaleDateString('en-US', { month: 'short' }).toUpperCase()
}

function getScheduleColor(schedule) {
  // Different colors for different shifts or statuses
  if (schedule.status === 'active') {
    return 'bg-primary/20 border-primary text-primary-100'
  }
  return 'bg-emerald-500/20 border-emerald-500 text-emerald-100'
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Schedule</h1>
        <p class="text-gray-500 mt-1">View and manage your upcoming shifts and work hours.</p>
      </div>
      
      <!-- View Toggle -->
      <div class="flex bg-gray-100 dark:bg-dark-bg rounded-lg p-1">
        <button 
          v-for="view in ['Month', 'Week', 'Day']" 
          :key="view"
          @click="currentView = view.toLowerCase()"
          class="px-4 py-2 text-sm font-medium rounded-md transition-all"
          :class="currentView === view.toLowerCase() 
            ? 'bg-primary text-white shadow-sm' 
            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
        >
          {{ view }}
        </button>
      </div>
    </div>

    <!-- Calendar Navigation -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <button 
          @click="navigateMonth(-1)"
          class="p-2 hover:bg-gray-100 dark:hover:bg-dark-border rounded-lg transition-colors"
        >
          <span class="material-symbols-outlined">chevron_left</span>
        </button>
        <button 
          @click="goToToday"
          class="px-4 py-2 text-sm font-medium bg-gray-100 dark:bg-dark-bg rounded-lg hover:bg-gray-200 dark:hover:bg-dark-border transition-colors"
        >
          Today
        </button>
        <button 
          @click="navigateMonth(1)"
          class="p-2 hover:bg-gray-100 dark:hover:bg-dark-border rounded-lg transition-colors"
        >
          <span class="material-symbols-outlined">chevron_right</span>
        </button>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white ml-4">
          {{ currentMonthYear }}
        </h2>
      </div>
      
      <!-- Legend -->
      <div class="hidden sm:flex items-center gap-4">
        <div class="flex items-center gap-2">
          <span class="w-3 h-3 rounded-full bg-primary"></span>
          <span class="text-sm text-gray-500">Scheduled</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
          <span class="text-sm text-gray-500">Completed</span>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="card p-12 text-center text-gray-500">
      <div class="inline-flex items-center gap-2">
        <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Loading schedules...
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="card p-8 text-center text-red-500">
      <span class="material-symbols-outlined text-4xl mb-2">error</span>
      <p>{{ error }}</p>
    </div>

    <!-- Calendar Grid -->
    <div v-else class="card overflow-hidden">
      <!-- Week Day Headers -->
      <div 
        class="grid border-b border-gray-200 dark:border-dark-border"
        :class="currentView === 'day' ? 'grid-cols-1' : 'grid-cols-7'"
      >
        <div 
          v-for="day in activeWeekDays" 
          :key="day"
          class="py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400"
        >
          {{ day }}
        </div>
      </div>

      <!-- Calendar Weeks -->
      <div class="divide-y divide-gray-200 dark:divide-dark-border">
        <div 
          v-for="(week, weekIndex) in calendarWeeks" 
          :key="weekIndex"
          class="grid divide-x divide-gray-200 dark:divide-dark-border"
          :class="currentView === 'day' ? 'grid-cols-1' : 'grid-cols-7'"
        >
          <div 
            v-for="day in week" 
            :key="day.toISOString()"
            class="p-2 transition-colors"
            :class="[
              isCurrentMonth(day) ? 'bg-white dark:bg-dark-surface' : 'bg-gray-50 dark:bg-dark-bg',
              currentView === 'day' ? 'min-h-[300px]' : 'min-h-[120px]'
            ]"
          >
            <!-- Day Number -->
            <div class="flex items-start justify-between mb-1">
              <span 
                class="inline-flex items-center justify-center w-7 h-7 text-sm rounded-full"
                :class="[
                  isToday(day) 
                    ? 'bg-primary text-white font-bold' 
                    : isCurrentMonth(day) 
                      ? 'text-gray-900 dark:text-white' 
                      : 'text-gray-400 dark:text-gray-600'
                ]"
              >
                {{ day.getDate() }}
              </span>
              <span 
                v-if="isToday(day)" 
                class="text-xs font-medium text-primary uppercase"
              >
                Today
              </span>
            </div>

            <!-- Schedules for this day -->
            <div class="space-y-1">
              <button
                v-for="schedule in getSchedulesForDate(day)"
                :key="schedule.id"
                @click="openScheduleDetail(schedule, day)"
                class="w-full text-left p-1.5 rounded border-l-2 text-xs transition-all hover:scale-[1.02]"
                :class="[
                  isToday(day)
                    ? 'bg-primary/30 border-primary text-white'
                    : 'bg-primary/10 border-primary/60 text-primary dark:text-primary-300'
                ]"
              >
                <div class="font-medium truncate">
                  {{ formatTime24(schedule.shift?.start_time) }} - {{ formatTime24(schedule.shift?.end_time) }}
                </div>
                <div class="truncate opacity-80 flex items-center gap-1">
                  <span class="material-symbols-outlined text-xs">location_on</span>
                  {{ schedule.shift?.name || 'Office' }}
                </div>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Schedule Detail Modal -->
    <Teleport to="body">
      <div 
        v-if="showModal" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="closeModal"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeModal"></div>
        
        <!-- Modal Content -->
        <div class="relative w-full max-w-md bg-white dark:bg-dark-surface rounded-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
          <!-- Close Button -->
          <button 
            @click="closeModal"
            class="absolute top-3 right-3 z-10 p-1 rounded-full bg-white/10 hover:bg-white/20 transition-colors"
          >
            <span class="material-symbols-outlined text-white/80">close</span>
          </button>

          <!-- Header with gradient -->
          <div class="h-32 bg-gradient-to-br from-gray-400 to-gray-600 relative">
            <!-- Date Badge -->
            <div class="absolute -bottom-6 left-6">
              <div class="bg-primary text-white rounded-xl px-4 py-2 text-center shadow-lg">
                <div class="text-xs font-medium opacity-90">
                  {{ selectedSchedule?.selectedDate ? getMonthAbbr(selectedSchedule.selectedDate) : '' }}
                </div>
                <div class="text-2xl font-bold">
                  {{ selectedSchedule?.selectedDate?.getDate().toString().padStart(2, '0') }}
                </div>
              </div>
            </div>
          </div>

          <!-- Content -->
          <div class="p-6 pt-10">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">
              {{ selectedSchedule?.shift?.name || 'Regular Shift' }}
            </h3>
            <p class="text-gray-500 mt-1">{{ selectedSchedule?.shift?.code }}</p>

            <!-- Details List -->
            <div class="mt-6 space-y-4">
              <!-- Time -->
              <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-gray-400 mt-0.5">schedule</span>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">
                    {{ formatTime12(selectedSchedule?.shift?.start_time) }} - {{ formatTime12(selectedSchedule?.shift?.end_time) }}
                  </p>
                  <p class="text-sm text-gray-500">
                    {{ calculateDuration(selectedSchedule?.shift?.start_time, selectedSchedule?.shift?.end_time) }}
                  </p>
                </div>
              </div>

              <!-- Grace Period -->
              <div v-if="selectedSchedule?.shift?.late_after_min" class="flex items-start gap-3">
                <span class="material-symbols-outlined text-gray-400 mt-0.5">timer</span>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">
                    Grace Period
                  </p>
                  <p class="text-sm text-gray-500">
                    {{ selectedSchedule.shift.late_after_min }} minutes allowed
                  </p>
                </div>
              </div>

              <!-- Schedule Period -->
              <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-gray-400 mt-0.5">date_range</span>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">
                    Shift Hours
                  </p>
                  <p class="text-sm text-gray-500">
                    {{ formatTime12(selectedSchedule?.shift?.start_time) }} — {{ formatTime12(selectedSchedule?.shift?.end_time) }}
                  </p>
                </div>
              </div>

              <!-- Status -->
              <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-gray-400 mt-0.5">info</span>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">Status</p>
                  <span 
                    class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="{
                      'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400': selectedSchedule?.status === 'active',
                      'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400': selectedSchedule?.status === 'upcoming',
                      'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400': selectedSchedule?.status === 'expired'
                    }"
                  >
                    {{ selectedSchedule?.status?.charAt(0).toUpperCase() + selectedSchedule?.status?.slice(1) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Action Button -->
            <button 
              @click="closeModal"
              class="w-full mt-6 py-3 bg-gray-100 dark:bg-dark-bg text-gray-700 dark:text-gray-300 rounded-xl font-medium hover:bg-gray-200 dark:hover:bg-dark-border transition-colors"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes zoom-in {
  from { transform: scale(0.95); }
  to { transform: scale(1); }
}

.animate-in {
  animation: fade-in 0.2s ease-out, zoom-in 0.2s ease-out;
}
</style>

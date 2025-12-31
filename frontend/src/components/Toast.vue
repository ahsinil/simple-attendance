<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  type: { type: String, default: 'info' }, // success, error, warning, info
  title: { type: String, default: '' },
  message: { type: String, default: '' },
  duration: { type: Number, default: 5000 }, // 0 for persistent
  onClose: { type: Function, default: null }
})

const emit = defineEmits(['close'])

const visible = ref(props.show)

watch(() => props.show, (val) => {
  visible.value = val
  if (val && props.duration > 0) {
    setTimeout(() => close(), props.duration)
  }
})

function close() {
  visible.value = false
  emit('close')
  if (props.onClose) props.onClose()
}

const iconClass = computed(() => {
  const icons = {
    success: 'check_circle',
    error: 'error',
    warning: 'warning',
    info: 'info'
  }
  return icons[props.type] || 'info'
})

const bgClass = computed(() => {
  const classes = {
    success: 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800',
    error: 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800',
    warning: 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800',
    info: 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'
  }
  return classes[props.type] || classes.info
})

const iconColorClass = computed(() => {
  const classes = {
    success: 'text-green-500',
    error: 'text-red-500',
    warning: 'text-amber-500',
    info: 'text-blue-500'
  }
  return classes[props.type] || classes.info
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0 translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-2"
    >
      <div
        v-if="visible"
        class="fixed top-4 right-4 z-[100] max-w-sm w-full"
      >
        <div :class="[bgClass, 'rounded-lg border shadow-lg p-4']">
          <div class="flex items-start gap-3">
            <span :class="[iconColorClass, 'material-symbols-outlined text-xl flex-shrink-0 mt-0.5']">
              {{ iconClass }}
            </span>
            <div class="flex-1 min-w-0">
              <h4 v-if="title" class="font-semibold text-gray-900 dark:text-white text-sm">
                {{ title }}
              </h4>
              <p class="text-sm text-gray-600 dark:text-gray-300" :class="{ 'mt-1': title }">
                {{ message }}
              </p>
            </div>
            <button 
              @click="close"
              class="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
            >
              <span class="material-symbols-outlined text-lg">close</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

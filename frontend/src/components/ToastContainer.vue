<script setup>
import { useToast } from '@/composables/useToast'

const { toasts, remove } = useToast()
</script>

<template>
  <Teleport to="body">
    <div class="fixed top-4 right-4 z-[100] space-y-2 max-w-sm w-full pointer-events-none">
      <TransitionGroup
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 translate-x-full"
        enter-to-class="opacity-100 translate-x-0"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-x-0"
        leave-to-class="opacity-0 translate-x-full"
      >
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="pointer-events-auto rounded-lg border shadow-lg p-4"
          :class="{
            'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800': toast.type === 'success',
            'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800': toast.type === 'error',
            'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800': toast.type === 'warning',
            'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800': toast.type === 'info'
          }"
        >
          <div class="flex items-start gap-3">
            <span 
              class="material-symbols-outlined text-xl flex-shrink-0 mt-0.5"
              :class="{
                'text-green-500': toast.type === 'success',
                'text-red-500': toast.type === 'error',
                'text-amber-500': toast.type === 'warning',
                'text-blue-500': toast.type === 'info'
              }"
            >
              {{ toast.type === 'success' ? 'check_circle' : toast.type === 'error' ? 'error' : toast.type === 'warning' ? 'warning' : 'info' }}
            </span>
            <div class="flex-1 min-w-0">
              <h4 v-if="toast.title" class="font-semibold text-gray-900 dark:text-white text-sm">
                {{ toast.title }}
              </h4>
              <p class="text-sm text-gray-600 dark:text-gray-300" :class="{ 'mt-1': toast.title }">
                {{ toast.message }}
              </p>
            </div>
            <button 
              @click="remove(toast.id)"
              class="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
            >
              <span class="material-symbols-outlined text-lg">close</span>
            </button>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

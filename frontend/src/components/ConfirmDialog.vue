<script setup>
import { useConfirm } from '@/composables/useConfirm'

const { state, handleConfirm, handleCancel } = useConfirm()

const iconClass = {
  danger: 'text-red-500',
  warning: 'text-amber-500',
  info: 'text-blue-500'
}

const iconName = {
  danger: 'delete_forever',
  warning: 'warning',
  info: 'help'
}

const buttonClass = {
  danger: 'bg-red-500 hover:bg-red-600 text-white',
  warning: 'bg-amber-500 hover:bg-amber-600 text-white',
  info: 'bg-blue-500 hover:bg-blue-600 text-white'
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="state.show"
        class="fixed inset-0 bg-black/50 z-[200] flex items-center justify-center p-4"
        @click.self="handleCancel"
      >
        <Transition
          enter-active-class="transition-all duration-200 ease-out"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition-all duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="state.show"
            class="bg-white dark:bg-dark-surface rounded-xl shadow-2xl w-full max-w-md overflow-hidden"
          >
            <!-- Header -->
            <div class="p-6 pb-4">
              <div class="flex items-start gap-4">
                <div 
                  class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0"
                  :class="{
                    'bg-red-100 dark:bg-red-900/20': state.type === 'danger',
                    'bg-amber-100 dark:bg-amber-900/20': state.type === 'warning',
                    'bg-blue-100 dark:bg-blue-900/20': state.type === 'info'
                  }"
                >
                  <span 
                    class="material-symbols-outlined text-2xl"
                    :class="iconClass[state.type]"
                  >
                    {{ iconName[state.type] }}
                  </span>
                </div>
                <div class="flex-1 min-w-0">
                  <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ state.title }}
                  </h3>
                  <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ state.message }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="px-6 py-4 bg-gray-50 dark:bg-dark-border/50 flex gap-3 justify-end">
              <button
                @click="handleCancel"
                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-dark-surface border border-gray-300 dark:border-dark-border rounded-lg hover:bg-gray-50 dark:hover:bg-dark-border transition-colors"
              >
                {{ state.cancelText }}
              </button>
              <button
                @click="handleConfirm"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                :class="buttonClass[state.type]"
              >
                {{ state.confirmText }}
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

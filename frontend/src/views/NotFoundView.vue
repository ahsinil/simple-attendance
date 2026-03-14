<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-gray-50 dark:bg-dark-bg px-4 py-8">
    <div class="text-center max-w-md w-full space-y-8">
      <!-- Illustration or Icon -->
      <div class="flex justify-center">
        <div class="relative size-32 sm:size-40 flex items-center justify-center bg-red-100 dark:bg-red-900/30 rounded-full animate-bounce-slow">
          <span class="material-symbols-outlined text-[80px] sm:text-[100px] text-red-500">
            error
          </span>
        </div>
      </div>

      <!-- Text Content -->
      <div class="space-y-3">
        <h1 class="text-7xl sm:text-8xl font-black text-gray-900 dark:text-white tracking-tight">404</h1>
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 dark:text-gray-100">Page Not Found</h2>
        <p class="text-gray-500 dark:text-gray-400 text-sm sm:text-base leading-relaxed">
          Oops! The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4 pt-4">
        <button 
          @click="router.back()" 
          class="w-full sm:w-auto px-6 py-3 rounded-xl border border-gray-200 dark:border-dark-line text-gray-700 dark:text-gray-300 font-semibold hover:bg-gray-100 dark:hover:bg-dark-surface transition-all duration-200 shadow-sm flex items-center justify-center gap-2 group"
        >
          <span class="material-symbols-outlined text-[20px] group-hover:-translate-x-1 transition-transform">arrow_back</span>
          Go Back
        </button>
        <router-link 
          to="/" 
          class="w-full sm:w-auto px-6 py-3 rounded-xl bg-primary text-white font-semibold hover:bg-primary/90 transition-all duration-200 shadow-sm shadow-primary/30 flex items-center justify-center gap-2"
        >
          <span class="material-symbols-outlined text-[20px]">home</span>
          Back to Home
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<style scoped>
.animate-bounce-slow {
  animation: bounce-slow 3s infinite ease-in-out;
}

@keyframes bounce-slow {
  0%, 100% {
    transform: translateY(-5%);
  }
  50% {
    transform: translateY(5%);
  }
}
</style>

<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'

const stats = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const response = await adminApi.getRequestStats()
    stats.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch stats:', error)
  }
  loading.value = false
})
</script>

<template>
  <div class="space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="card p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-500">Pending Requests</p>
            <p class="text-3xl font-bold text-amber-500">{{ stats?.pending || 0 }}</p>
          </div>
          <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/20 rounded-full flex items-center justify-center">
            <span class="material-symbols-outlined text-amber-500">pending</span>
          </div>
        </div>
      </div>

      <div class="card p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-500">Approved Today</p>
            <p class="text-3xl font-bold text-green-500">{{ stats?.approved_today || 0 }}</p>
          </div>
          <div class="w-12 h-12 bg-green-100 dark:bg-green-900/20 rounded-full flex items-center justify-center">
            <span class="material-symbols-outlined text-green-500">check_circle</span>
          </div>
        </div>
      </div>

      <div class="card p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-500">Rejected Today</p>
            <p class="text-3xl font-bold text-red-500">{{ stats?.rejected_today || 0 }}</p>
          </div>
          <div class="w-12 h-12 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center">
            <span class="material-symbols-outlined text-red-500">cancel</span>
          </div>
        </div>
      </div>

      <div class="card p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-500">Total Today</p>
            <p class="text-3xl font-bold text-primary">{{ stats?.total_today || 0 }}</p>
          </div>
          <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
            <span class="material-symbols-outlined text-primary">today</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <RouterLink to="/admin/requests" class="card p-6 hover:border-primary transition-colors group">
        <span class="material-symbols-outlined text-3xl text-primary group-hover:scale-110 transition-transform">pending_actions</span>
        <p class="mt-2 font-medium text-gray-900 dark:text-white">Review Requests</p>
        <p class="text-sm text-gray-500">Approve or reject attendance requests</p>
      </RouterLink>

      <RouterLink to="/admin/users" class="card p-6 hover:border-primary transition-colors group">
        <span class="material-symbols-outlined text-3xl text-primary group-hover:scale-110 transition-transform">group</span>
        <p class="mt-2 font-medium text-gray-900 dark:text-white">Manage Users</p>
        <p class="text-sm text-gray-500">Add, edit, or manage employees</p>
      </RouterLink>

      <RouterLink to="/barcode" class="card p-6 hover:border-primary transition-colors group">
        <span class="material-symbols-outlined text-3xl text-primary group-hover:scale-110 transition-transform">qr_code</span>
        <p class="mt-2 font-medium text-gray-900 dark:text-white">Display Barcode</p>
        <p class="text-sm text-gray-500">Show attendance QR code</p>
      </RouterLink>
    </div>
  </div>
</template>

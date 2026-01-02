<script setup>
import { ref } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const sidebarOpen = ref(false)

const navItems = [
  { name: 'Dashboard', icon: 'dashboard', to: '/' },
  { name: 'Attendance', icon: 'qr_code_scanner', to: '/attendance' },
  { name: 'History', icon: 'history', to: '/history' },
  { name: 'My Requests', icon: 'pending_actions', to: '/requests' },
  { name: 'My Leaves', icon: 'beach_access', to: '/leaves' },
  { name: 'My Schedules', icon: 'calendar_month', to: '/schedules' },
  { name: 'Settings', icon: 'settings', to: '/settings' },
]

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-light-bg dark:bg-dark-bg">
    <!-- Mobile Sidebar Overlay -->
    <div 
      v-if="sidebarOpen" 
      class="fixed inset-0 bg-black/50 z-40 lg:hidden"
      @click="sidebarOpen = false"
    />

    <!-- Sidebar -->
    <aside 
      class="fixed top-0 left-0 z-50 h-full w-64 bg-white dark:bg-dark-surface border-r border-gray-200 dark:border-dark-border transform transition-transform duration-300 lg:translate-x-0"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Logo -->
      <div class="p-6 border-b border-gray-200 dark:border-dark-border">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
            <span class="material-symbols-outlined text-primary">fingerprint</span>
          </div>
          <div>
            <h1 class="font-bold text-gray-900 dark:text-white">Attendance</h1>
            <p class="text-xs text-gray-500">Employee Portal</p>
          </div>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="p-4 space-y-1">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="sidebar-link"
          active-class="active"
          @click="sidebarOpen = false"
        >
          <span class="material-symbols-outlined">{{ item.icon }}</span>
          {{ item.name }}
        </RouterLink>

        <!-- Admin Link -->
        <RouterLink
          v-if="authStore.isAdmin"
          to="/admin"
          class="sidebar-link"
          active-class="active"
          @click="sidebarOpen = false"
        >
          <span class="material-symbols-outlined">admin_panel_settings</span>
          Admin Panel
        </RouterLink>
      </nav>

      <!-- User Info -->
      <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200 dark:border-dark-border">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
            <span class="text-primary font-medium">{{ authStore.user?.name?.[0] }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-medium text-gray-900 dark:text-white truncate">{{ authStore.user?.name }}</p>
            <p class="text-xs text-gray-500 truncate">{{ authStore.user?.email }}</p>
          </div>
        </div>
        <button 
          @click="handleLogout"
          class="sidebar-link w-full text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
        >
          <span class="material-symbols-outlined">logout</span>
          Logout
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="lg:ml-64 min-h-screen">
      <!-- Top Bar -->
      <header class="sticky top-0 z-30 bg-white/80 dark:bg-dark-surface/80 backdrop-blur-md border-b border-gray-200 dark:border-dark-border">
        <div class="flex items-center justify-between px-4 py-3">
          <button 
            @click="sidebarOpen = true"
            class="lg:hidden p-2 hover:bg-gray-100 dark:hover:bg-dark-border rounded-lg"
          >
            <span class="material-symbols-outlined">menu</span>
          </button>
          
          <div class="flex-1 lg:pl-0">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ $route.name }}
            </h2>
          </div>

          <div class="flex items-center gap-2">
            <RouterLink 
              to="/attendance" 
              class="p-2 hover:bg-gray-100 dark:hover:bg-dark-border rounded-lg"
              title="Scan Attendance"
            >
              <span class="material-symbols-outlined">qr_code_scanner</span>
            </RouterLink>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <div class="p-4 lg:p-6">
        <RouterView />
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const sidebarOpen = ref(false)

// Admin navigation items with required permissions
const allNavItems = [
  { name: 'Dashboard', icon: 'dashboard', to: '/admin', permission: 'admin.dashboard.view' },
  { name: 'Requests', icon: 'pending_actions', to: '/admin/requests', permission: 'admin.requests.view' },
  { name: 'Leave Requests', icon: 'event_busy', to: '/admin/leave-requests', permission: 'admin.leaves.view' },
  { name: 'Reports', icon: 'analytics', to: '/admin/reports', permission: 'admin.reports.view' },
  { name: 'Payroll', icon: 'payments', to: '/admin/payroll', permission: 'admin.payroll.view' },
  { name: 'Users', icon: 'group', to: '/admin/users', permission: 'admin.users.view' },
  {
    name: 'Settings',
    icon: 'settings_applications',
    children: [
      { name: 'General Settings', to: '/admin/settings', permission: 'admin.settings.view' },
      { name: 'Locations', to: '/admin/locations', permission: 'admin.locations.view' },
      { name: 'Shifts', to: '/admin/shifts', permission: 'admin.shifts.view' },
      { name: 'Leave Types', to: '/admin/leave-types', permission: 'admin.leave-types.view' },
      { name: 'Holidays', to: '/admin/holidays', permission: 'admin.settings.view' },
      { name: 'Salary Components', to: '/admin/salary-components', permission: 'admin.salary.view' },
      { name: 'Roles', to: '/admin/roles', permission: 'admin.roles.view' },
      { name: 'Devices', to: '/admin/devices', permission: 'admin.devices.view' },
    ]
  }
]

// Filter nav items based on user permissions
const navItems = computed(() => {
  return allNavItems.filter(item => {
    if (item.children) {
      item.children = item.children.filter(child => {
        if (!child.permission) return true
        return authStore.user?.permissions?.includes(child.permission)
      })
      return item.children.length > 0
    }
    if (!item.permission) return true
    return authStore.user?.permissions?.includes(item.permission)
  })
})

// Check if user has any employee portal permissions
const canAccessApp = computed(() => {
  const appPermissions = ['dashboard.view', 'attendance.create', 'history.view', 'requests.view', 'leaves.view', 'schedules.view']
  return appPermissions.some(p => authStore.user?.permissions?.includes(p))
})

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
      class="fixed top-0 left-0 z-50 h-full w-64 bg-white dark:bg-dark-surface border-r border-gray-200 dark:border-dark-border transform transition-transform duration-300 lg:translate-x-0 flex flex-col"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Logo -->
      <div class="p-6 border-b border-gray-200 dark:border-dark-border">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-red-500/10 rounded-lg flex items-center justify-center">
            <span class="material-symbols-outlined text-red-500">admin_panel_settings</span>
          </div>
          <div>
            <h1 class="font-bold text-gray-900 dark:text-white">Admin Panel</h1>
            <p class="text-xs text-gray-500">Attendance System</p>
          </div>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        <template v-for="item in navItems" :key="item.name">
          <!-- Normal Link -->
          <RouterLink
            v-if="!item.children"
            :to="item.to"
            class="sidebar-link"
            :class="{ 'active': item.to === '/admin' ? $route.path === '/admin' : $route.path.startsWith(item.to + '/') || $route.path === item.to }"
            @click="sidebarOpen = false"
          >
            <span class="material-symbols-outlined">{{ item.icon }}</span>
            {{ item.name }}
          </RouterLink>

          <!-- Submenu -->
          <details 
            v-else 
            class="group"
            :open="item.children.some(child => $route.path.startsWith(child.to) || $route.path === child.to)"
          >
            <summary class="sidebar-link flex items-center justify-between cursor-pointer list-none [&::-webkit-details-marker]:hidden select-none hover:bg-gray-100 dark:hover:bg-dark-border">
              <div class="flex items-center gap-3">
                <span class="material-symbols-outlined">{{ item.icon }}</span>
                {{ item.name }}
              </div>
              <span class="material-symbols-outlined transition-transform group-open:-rotate-180 text-gray-400">expand_more</span>
            </summary>
            <div class="mt-1 ml-4 pl-4 border-l-2 border-gray-100 dark:border-dark-border space-y-1">
              <RouterLink
                v-for="child in item.children"
                :key="child.to"
                :to="child.to"
                class="block px-3 py-2 text-sm rounded-lg text-gray-600 hover:text-primary hover:bg-red-50 dark:text-gray-400 dark:hover:text-red-400 dark:hover:bg-red-900/10 transition-colors"
                active-class="text-primary font-bold bg-red-50 dark:text-red-400 dark:bg-red-900/10"
                @click="sidebarOpen = false"
              >
                {{ child.name }}
              </RouterLink>
            </div>
          </details>
        </template>

        <div v-if="canAccessApp" class="my-4 border-t border-gray-200 dark:border-dark-border" />

        <RouterLink
          v-if="canAccessApp"
          to="/"
          class="sidebar-link"
          @click="sidebarOpen = false"
        >
          <span class="material-symbols-outlined">arrow_back</span>
          Back to App
        </RouterLink>
      </nav>

      <!-- User Info -->
      <div class="mt-auto p-4 border-t border-gray-200 dark:border-dark-border">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-10 h-10 bg-red-500/10 rounded-full flex items-center justify-center">
            <span class="text-red-500 font-medium">{{ authStore.user?.name?.[0] }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-medium text-gray-900 dark:text-white truncate">{{ authStore.user?.name }}</p>
            <p class="text-xs text-gray-500 truncate">Administrator</p>
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
              {{ $route.name?.replace('Admin', '') }}
            </h2>
          </div>

          <div class="flex items-center gap-2">
            <RouterLink 
              to="/barcode" 
              class="p-2 hover:bg-gray-100 dark:hover:bg-dark-border rounded-lg"
              title="Barcode Display"
            >
              <span class="material-symbols-outlined">qr_code</span>
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

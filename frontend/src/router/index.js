import { createRouter, createWebHistory } from 'vue-router'

const routes = [
    {
        path: '/login',
        name: 'Login',
        component: () => import('@/views/LoginView.vue'),
        meta: { guest: true },
    },
    {
        path: '/',
        component: () => import('@/layouts/MainLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'Dashboard',
                component: () => import('@/views/DashboardView.vue'),
            },
            {
                path: 'attendance',
                name: 'Attendance',
                component: () => import('@/views/AttendanceView.vue'),
            },
            {
                path: 'history',
                name: 'History',
                component: () => import('@/views/HistoryView.vue'),
            },
            {
                path: 'requests',
                name: 'MyRequests',
                component: () => import('@/views/MyRequestsView.vue'),
            },
            {
                path: 'schedules',
                name: 'MySchedules',
                component: () => import('@/views/SchedulesView.vue'),
            },
            {
                path: 'settings',
                name: 'Settings',
                component: () => import('@/views/SettingsView.vue'),
            },
        ],
    },
    {
        path: '/admin',
        component: () => import('@/layouts/AdminLayout.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
        children: [
            {
                path: '',
                name: 'AdminDashboard',
                component: () => import('@/views/admin/DashboardView.vue'),
            },
            {
                path: 'requests',
                name: 'AdminRequests',
                component: () => import('@/views/admin/RequestsView.vue'),
            },
            {
                path: 'users',
                name: 'AdminUsers',
                component: () => import('@/views/admin/UsersView.vue'),
            },
            {
                path: 'settings',
                name: 'AdminSettings',
                component: () => import('@/views/admin/SettingsView.vue'),
            },
            {
                path: 'shifts',
                name: 'AdminShifts',
                component: () => import('@/views/admin/ShiftsView.vue'),
            },
            {
                path: 'locations',
                name: 'AdminLocations',
                component: () => import('@/views/admin/LocationsView.vue'),
            },
            {
                path: 'reports',
                name: 'AdminReports',
                component: () => import('@/views/admin/ReportsView.vue'),
            },
        ],
    },
    {
        path: '/barcode',
        name: 'BarcodeDisplay',
        component: () => import('@/views/BarcodeDisplayView.vue'),
        meta: { requiresAuth: true },
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

// Navigation guard - uses localStorage directly to avoid Pinia initialization issues
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token')
    const userStr = localStorage.getItem('user')
    const user = userStr ? JSON.parse(userStr) : null
    const isAuthenticated = !!token
    const isAdmin = user?.roles?.includes('admin') || user?.roles?.includes('super_admin')

    if (to.meta.requiresAuth && !isAuthenticated) {
        next({ name: 'Login', query: { redirect: to.fullPath } })
        return
    }

    if (to.meta.guest && isAuthenticated) {
        next({ name: 'Dashboard' })
        return
    }

    if (to.meta.requiresAdmin && !isAdmin) {
        next({ name: 'Dashboard' })
        return
    }

    next()
})

export default router


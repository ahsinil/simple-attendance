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
                meta: { permission: 'dashboard.view' },
            },
            {
                path: 'attendance',
                name: 'Attendance',
                component: () => import('@/views/AttendanceView.vue'),
                meta: { permission: 'attendance.create' },
            },
            {
                path: 'history',
                name: 'History',
                component: () => import('@/views/HistoryView.vue'),
                meta: { permission: 'history.view' },
            },
            {
                path: 'requests',
                name: 'MyRequests',
                component: () => import('@/views/MyRequestsView.vue'),
                meta: { permission: 'requests.view' },
            },
            {
                path: 'schedules',
                name: 'MySchedules',
                component: () => import('@/views/SchedulesView.vue'),
                meta: { permission: 'schedules.view' },
            },
            {
                path: 'settings',
                name: 'Settings',
                component: () => import('@/views/SettingsView.vue'),
                // Settings is always accessible
            },
            {
                path: 'leaves',
                name: 'MyLeaves',
                component: () => import('@/views/MyLeavesView.vue'),
                meta: { permission: 'leaves.view' },
            },
        ],
    },

    {
        path: '/admin',
        component: () => import('@/layouts/AdminLayout.vue'),
        meta: { requiresAuth: true, requiresAdminPermission: true },
        children: [
            {
                path: '',
                name: 'AdminDashboard',
                component: () => import('@/views/admin/DashboardView.vue'),
                meta: { permission: 'admin.dashboard.view' },
            },
            {
                path: 'requests',
                name: 'AdminRequests',
                component: () => import('@/views/admin/RequestsView.vue'),
                meta: { permission: 'admin.requests.view' },
            },
            {
                path: 'users',
                name: 'AdminUsers',
                component: () => import('@/views/admin/UsersView.vue'),
                meta: { permission: 'admin.users.view' },
            },
            {
                path: 'roles',
                name: 'AdminRoles',
                component: () => import('@/views/admin/RolesView.vue'),
                meta: { permission: 'admin.roles.view' },
            },
            {
                path: 'settings',
                name: 'AdminSettings',
                component: () => import('@/views/admin/SettingsView.vue'),
                meta: { permission: 'admin.settings.view' },
            },
            {
                path: 'shifts',
                name: 'AdminShifts',
                component: () => import('@/views/admin/ShiftsView.vue'),
                meta: { permission: 'admin.shifts.view' },
            },
            {
                path: 'locations',
                name: 'AdminLocations',
                component: () => import('@/views/admin/LocationsView.vue'),
                meta: { permission: 'admin.locations.view' },
            },
            {
                path: 'reports',
                name: 'AdminReports',
                component: () => import('@/views/admin/ReportsView.vue'),
                meta: { permission: 'admin.reports.view' },
            },
            {
                path: 'leave-types',
                name: 'AdminLeaveTypes',
                component: () => import('@/views/admin/LeaveTypesView.vue'),
                meta: { permission: 'admin.leave-types.view' },
            },
            {
                path: 'leave-requests',
                name: 'AdminLeaveRequests',
                component: () => import('@/views/admin/LeaveRequestsView.vue'),
                meta: { permission: 'admin.leaves.view' },
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
    const isDisplayScreen = user?.roles?.includes('display_screen')
    const userPermissions = user?.permissions || []

    // Helper to check if user has a permission
    const hasPermission = (permission) => userPermissions.includes(permission)

    // Admin-level permissions that grant access to admin section
    const adminPermissions = [
        'admin.dashboard.view',
        'admin.requests.view',
        'admin.leaves.view',
        'admin.users.view',
        'admin.roles.view',
        'admin.shifts.view',
        'admin.leave-types.view',
        'admin.locations.view',
        'admin.reports.view',
        'admin.settings.view',
    ]

    // Check if user has any admin permission
    const hasAnyAdminPermission = adminPermissions.some(p => userPermissions.includes(p))

    if (to.meta.requiresAuth && !isAuthenticated) {
        next({ name: 'Login', query: { redirect: to.fullPath } })
        return
    }

    if (to.meta.guest && isAuthenticated) {
        // Redirect display_screen users to barcode page after login
        if (isDisplayScreen) {
            next({ name: 'BarcodeDisplay' })
        } else {
            // Find the first accessible route for the user
            const firstAccessible = findFirstAccessibleRoute(userPermissions)
            next({ name: firstAccessible })
        }
        return
    }

    // Restrict display_screen role to only barcode page
    if (isDisplayScreen && to.name !== 'BarcodeDisplay' && to.name !== 'Login') {
        next({ name: 'BarcodeDisplay' })
        return
    }

    // Check admin section access (requires role OR any admin permission)
    if (to.meta.requiresAdminPermission && !isAdmin && !hasAnyAdminPermission) {
        next({ name: 'Settings' })
        return
    }

    // Check route permission
    if (to.meta.permission && !hasPermission(to.meta.permission)) {
        // Find the first accessible route for the user
        const firstAccessible = findFirstAccessibleRoute(userPermissions)
        next({ name: firstAccessible })
        return
    }

    next()
})

// Helper function to find the first accessible route
function findFirstAccessibleRoute(permissions) {
    const routePermissions = [
        // Employee routes
        { name: 'Dashboard', permission: 'dashboard.view' },
        { name: 'Attendance', permission: 'attendance.create' },
        { name: 'History', permission: 'history.view' },
        { name: 'MyRequests', permission: 'requests.view' },
        { name: 'MyLeaves', permission: 'leaves.view' },
        { name: 'MySchedules', permission: 'schedules.view' },
        // Admin routes
        { name: 'AdminDashboard', permission: 'admin.dashboard.view' },
        { name: 'AdminRequests', permission: 'admin.requests.view' },
        { name: 'AdminLeaveRequests', permission: 'admin.leaves.view' },
        { name: 'AdminUsers', permission: 'admin.users.view' },
        { name: 'AdminRoles', permission: 'admin.roles.view' },
        { name: 'AdminShifts', permission: 'admin.shifts.view' },
        { name: 'AdminLeaveTypes', permission: 'admin.leave-types.view' },
        { name: 'AdminLocations', permission: 'admin.locations.view' },
        { name: 'AdminReports', permission: 'admin.reports.view' },
        { name: 'AdminSettings', permission: 'admin.settings.view' },
    ]

    for (const route of routePermissions) {
        if (permissions.includes(route.permission)) {
            return route.name
        }
    }

    // Fallback to Settings which is always accessible
    return 'Settings'
}

export default router


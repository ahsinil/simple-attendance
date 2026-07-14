import axios from 'axios'

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
})

// Request interceptor to add auth token and handle FormData
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }

    // When sending FormData, let the browser set Content-Type automatically
    // This is required for file uploads to work correctly with multipart/form-data
    if (config.data instanceof FormData) {
        delete config.headers['Content-Type']
    }

    return config
})

// Response interceptor to handle errors
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('token')
            localStorage.removeItem('user')
            window.location.href = '/login'
        }
        return Promise.reject(error)
    }
)

export default api

// Auth API
export const authApi = {
    login: (email, password, deviceData = {}) => api.post('/login', { email, password, ...deviceData }),
    logout: () => api.post('/auth/logout'),
    me: () => api.get('/auth/me'),
    updateProfile: (data) => api.put('/auth/profile', data),
    changePassword: (data) => api.put('/auth/password', data),
    activeDevices: () => api.get('/auth/devices'),

    // Registered Devices (for attendance)
    myDevices: () => api.get('/auth/my-devices'),
    registerDevice: (data) => api.post('/auth/devices/register', data),
    renameDevice: (id, data) => api.put(`/auth/devices/${id}/rename`, data),
    removeDevice: (id) => api.delete(`/auth/devices/${id}`),
}

// Attendance API
export const attendanceApi = {
    scan: (data) => api.post('/attendance/scan', data),
    today: () => api.get('/attendance/today'),
    history: (params) => api.get('/attendance/history', { params }),
    monthlySummary: (params) => api.get('/attendance/monthly-summary', { params }),
    manualRequest: (data) => api.post('/attendance/manual-request', data),
    myRequests: (params) => api.get('/attendance/my-requests', { params }),
    mySchedules: () => api.get('/attendance/my-schedules'),
    locations: () => api.get('/attendance/locations'),
    submitOvertimeReason: (id, reason) => api.post(`/attendance/${id}/overtime-reason`, { overtime_reason: reason }),
}

// Barcode API
export const barcodeApi = {
    info: () => api.get('/barcode/info'),
    locations: () => api.get('/barcode/locations'),
    location: (id) => api.get(`/barcode/location/${id}`),
}

// Leave API (Employee)
export const leaveApi = {
    getTypes: () => api.get('/leave/types'),
    getBalances: (params) => api.get('/leave/balances', { params }),
    getMyRequests: (params) => api.get('/leave/my-requests', { params }),
    submitRequest: (data) => api.post('/leave/request', data),
    cancelRequest: (id) => api.delete(`/leave/request/${id}`),
}

// Admin API
export const adminApi = {
    // Dashboard
    getDashboard: () => api.get('/admin/dashboard'),

    // Requests
    getRequests: (params) => api.get('/admin/requests', { params }),
    getRequestStats: () => api.get('/admin/requests/stats'),
    approveRequest: (id, data) => api.post(`/admin/requests/${id}/approve`, data),
    rejectRequest: (id, data) => api.post(`/admin/requests/${id}/reject`, data),

    // Shifts
    getShifts: () => api.get('/admin/shifts'),
    createShift: (data) => api.post('/admin/shifts', data),
    updateShift: (id, data) => api.put(`/admin/shifts/${id}`, data),
    deleteShift: (id) => api.delete(`/admin/shifts/${id}`),

    // Locations
    getLocations: () => api.get('/admin/locations'),
    createLocation: (data) => api.post('/admin/locations', data),
    updateLocation: (id, data) => api.put(`/admin/locations/${id}`, data),
    deleteLocation: (id) => api.delete(`/admin/locations/${id}`),

    // Users
    getRoles: () => api.get('/admin/roles'),
    getUsers: (params) => api.get('/admin/users', { params }),
    createUser: (data) => api.post('/admin/users', data),
    updateUser: (id, data) => api.put(`/admin/users/${id}`, data),
    deleteUser: (id) => api.delete(`/admin/users/${id}`),
    assignSchedule: (userId, data) => api.post(`/admin/users/${userId}/schedule`, data),
    getUserSchedules: (userId) => api.get(`/admin/users/${userId}/schedules`),

    removeSchedule: (userId, scheduleId) => api.delete(`/admin/users/${userId}/schedules/${scheduleId}`),

    // Roles & Permissions
    getPermissions: () => api.get('/admin/permissions'),
    createRole: (data) => api.post('/admin/roles', data),
    getRole: (id) => api.get(`/admin/roles/${id}`),
    updateRole: (id, data) => api.put(`/admin/roles/${id}`, data),
    deleteRole: (id) => api.delete(`/admin/roles/${id}`),

    // Settings
    getSettings: () => api.get('/admin/settings'),
    updateSettings: (data) => api.post('/admin/settings', data),

    // Reports
    getReports: (params) => api.get('/admin/reports', { params }),
    toggleAllowancePaid: (id, componentId) => api.put(`/admin/reports/attendances/${id}/toggle-allowance-paid`, { component_id: componentId }),
    getReportsSummary: (params) => api.get('/admin/reports/summary', { params }),
    getReportsLocations: () => api.get('/admin/reports/locations'),
    exportReports: (params) => api.get('/admin/reports/export', {
        params,
        responseType: 'blob'
    }),
    getReportsDepartments: () => api.get('/admin/reports/departments'),
    getReportsEmployees: () => api.get('/admin/reports/employees'),
    getEmployeeReport: (params) => api.get('/admin/reports/employee', { params }),
    exportEmployeeReport: (params) => api.get('/admin/reports/employee/export', {
        params,
        responseType: 'blob'
    }),

    // Leave Types
    getLeaveTypes: () => api.get('/admin/leave-types'),
    createLeaveType: (data) => api.post('/admin/leave-types', data),
    updateLeaveType: (id, data) => api.put(`/admin/leave-types/${id}`, data),
    deleteLeaveType: (id) => api.delete(`/admin/leave-types/${id}`),

    // Leave Requests (Admin approval)
    getLeaveRequests: (params) => api.get('/admin/leave-requests', { params }),
    getLeaveRequestStats: () => api.get('/admin/leave-requests/stats'),
    approveLeaveRequest: (id, data) => api.post(`/admin/leave-requests/${id}/approve`, data),
    rejectLeaveRequest: (id, data) => api.post(`/admin/leave-requests/${id}/reject`, data),

    // Devices
    getDevices: (params) => api.get('/admin/devices', { params }),
    getDeviceStats: () => api.get('/admin/devices/stats'),
    getUserDevices: (userId) => api.get(`/admin/devices/user/${userId}`),
    approveDevice: (id) => api.post(`/admin/devices/${id}/approve`),
    rejectDevice: (id) => api.post(`/admin/devices/${id}/reject`),
    revokeDevice: (id) => api.delete(`/admin/devices/${id}`),

    // Payroll
    getPayrollSummary: (params) => api.get('/admin/payroll/summary', { params }),
    exportPayroll: (params) => api.get('/admin/payroll/export', { params, responseType: 'blob' }),
    getPayrollDepartments: () => api.get('/admin/payroll/departments'),

    // Salary Components
    getSalaryComponents: () => api.get('/admin/salary-components'),
    createSalaryComponent: (data) => api.post('/admin/salary-components', data),
    updateSalaryComponent: (id, data) => api.put(`/admin/salary-components/${id}`, data),
    deleteSalaryComponent: (id) => api.delete(`/admin/salary-components/${id}`),

    // User Salary
    getUserSalary: (userId) => api.get(`/admin/users/${userId}/salary`),
    updateUserSalary: (userId, data) => api.post(`/admin/users/${userId}/salary`, data),

    // User Additional (Custom) Allowances
    getUserAdditionalAllowances: (userId, params) => api.get(`/admin/users/${userId}/additional-allowances`, { params }),
    addUserAdditionalAllowance: (userId, data) => api.post(`/admin/users/${userId}/additional-allowances`, data),
    updateUserAdditionalAllowance: (userId, allowanceId, data) => api.put(`/admin/users/${userId}/additional-allowances/${allowanceId}`, data),
    deleteUserAdditionalAllowance: (userId, allowanceId) => api.delete(`/admin/users/${userId}/additional-allowances/${allowanceId}`),

    // Holidays
    getHolidays: (params) => api.get('/admin/holidays', { params }),
    createHoliday: (data) => api.post('/admin/holidays', data),
    updateHoliday: (id, data) => api.put(`/admin/holidays/${id}`, data),
    deleteHoliday: (id) => api.delete(`/admin/holidays/${id}`),
    syncHolidays: (year) => api.post('/admin/holidays/sync', { year }),
}


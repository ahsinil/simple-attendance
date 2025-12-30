import axios from 'axios'

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
})

// Request interceptor to add auth token
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
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
    login: (email, password) => api.post('/login', { email, password }),
    logout: () => api.post('/auth/logout'),
    me: () => api.get('/auth/me'),
}

// Attendance API
export const attendanceApi = {
    scan: (data) => api.post('/attendance/scan', data),
    today: () => api.get('/attendance/today'),
    history: (params) => api.get('/attendance/history', { params }),
    monthlySummary: (params) => api.get('/attendance/monthly-summary', { params }),
    manualRequest: (data) => api.post('/attendance/manual-request', data),
    myRequests: (params) => api.get('/attendance/my-requests', { params }),
    locations: () => api.get('/attendance/locations'),
}

// Barcode API
export const barcodeApi = {
    info: () => api.get('/barcode/info'),
    locations: () => api.get('/barcode/locations'),
    location: (id) => api.get(`/barcode/location/${id}`),
}

// Admin API
export const adminApi = {
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
}

import { defineStore } from 'pinia'
import { attendanceApi } from '@/services/api'

export const useAttendanceStore = defineStore('attendance', {
    state: () => ({
        todaySummary: null,
        history: [],
        monthlySummary: null,
        myRequests: [],
        locations: [],
        loading: false,
        error: null,
    }),

    actions: {
        async fetchTodaySummary() {
            this.loading = true
            try {
                const response = await attendanceApi.today()
                this.todaySummary = response.data.data
            } catch (error) {
                this.error = error.response?.data?.error || 'Failed to fetch today summary'
            } finally {
                this.loading = false
            }
        },

        async fetchHistory(params = {}) {
            this.loading = true
            try {
                const response = await attendanceApi.history(params)
                this.history = response.data.data
            } catch (error) {
                this.error = error.response?.data?.error || 'Failed to fetch history'
            } finally {
                this.loading = false
            }
        },

        async fetchMonthlySummary(month, year) {
            this.loading = true
            try {
                const response = await attendanceApi.monthlySummary({ month, year })
                this.monthlySummary = response.data.data
            } catch (error) {
                this.error = error.response?.data?.error || 'Failed to fetch monthly summary'
            } finally {
                this.loading = false
            }
        },

        async fetchLocations() {
            try {
                const response = await attendanceApi.locations()
                this.locations = response.data.data
            } catch (error) {
                this.error = error.response?.data?.error || 'Failed to fetch locations'
            }
        },

        async scan(data) {
            this.loading = true
            this.error = null
            try {
                const response = await attendanceApi.scan(data)
                await this.fetchTodaySummary()
                return { success: true, data: response.data }
            } catch (error) {
                this.error = error.response?.data?.error || 'Scan failed'
                return { success: false, error: this.error }
            } finally {
                this.loading = false
            }
        },

        async submitManualRequest(data) {
            this.loading = true
            this.error = null
            try {
                // Use FormData for file uploads
                const formData = new FormData()
                formData.append('location_id', data.location_id)
                formData.append('check_type', data.check_type)
                formData.append('request_time', data.request_time)
                formData.append('reason', data.reason)

                if (data.photo) {
                    formData.append('photo', data.photo)
                }

                const response = await attendanceApi.manualRequest(formData)
                return { success: true, data: response.data }
            } catch (error) {
                // Handle Laravel validation errors (422) or other errors
                const errorData = error.response?.data
                if (errorData?.errors) {
                    // Get first validation error message
                    const firstError = Object.values(errorData.errors)[0]
                    this.error = Array.isArray(firstError) ? firstError[0] : firstError
                } else if (errorData?.message) {
                    this.error = errorData.message
                } else {
                    this.error = errorData?.error || 'Request failed'
                }
                return { success: false, error: this.error }
            } finally {
                this.loading = false
            }
        },

        async fetchMyRequests(params = {}) {
            this.loading = true
            try {
                const response = await attendanceApi.myRequests(params)
                this.myRequests = response.data.data
            } catch (error) {
                this.error = error.response?.data?.error || 'Failed to fetch requests'
            } finally {
                this.loading = false
            }
        },
    },
})

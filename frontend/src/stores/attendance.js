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
                const response = await attendanceApi.manualRequest(data)
                return { success: true, data: response.data }
            } catch (error) {
                this.error = error.response?.data?.error || 'Request failed'
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

import { defineStore } from 'pinia'
import { authApi } from '@/services/api'

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: JSON.parse(localStorage.getItem('user') || 'null'),
        token: localStorage.getItem('token') || null,
        loading: false,
        error: null,
    }),

    getters: {
        isAuthenticated: (state) => !!state.token,
        isAdmin: (state) => state.user?.roles?.includes('admin') || state.user?.roles?.includes('super_admin'),
        isSuperAdmin: (state) => state.user?.roles?.includes('super_admin'),
        hasPermission: (state) => (permission) => state.user?.permissions?.includes(permission),
    },

    actions: {
        async login(email, password) {
            this.loading = true
            this.error = null

            try {
                const response = await authApi.login(email, password)
                const { user, token } = response.data.data

                this.user = user
                this.token = token

                localStorage.setItem('user', JSON.stringify(user))
                localStorage.setItem('token', token)

                return { success: true }
            } catch (error) {
                this.error = error.response?.data?.message || 'Login failed'
                return { success: false, error: this.error }
            } finally {
                this.loading = false
            }
        },

        async logout() {
            try {
                await authApi.logout()
            } catch (error) {
                // Ignore logout errors
            }

            this.user = null
            this.token = null
            localStorage.removeItem('user')
            localStorage.removeItem('token')
        },

        async fetchUser() {
            if (!this.token) return

            try {
                const response = await authApi.me()
                this.user = response.data.data
                localStorage.setItem('user', JSON.stringify(this.user))
            } catch (error) {
                this.logout()
            }
        },
    },
})

import { defineStore } from 'pinia'
import { authApi } from '@/services/api'
import { getDeviceFingerprint, getDeviceData, getDeviceName } from '@/utils/deviceFingerprint'

export const useAuthStore = defineStore('auth', {
    state: () => {
        let user = null
        try {
            user = JSON.parse(localStorage.getItem('user') || 'null')
        } catch (e) {
            localStorage.removeItem('user')
            localStorage.removeItem('token')
        }
        return {
            user,
            token: localStorage.getItem('token') || null,
            loading: false,
            error: null,
        }
    },

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
                // Collect device fingerprint data to send with login
                let deviceData = {}
                try {
                    const fingerprint = await getDeviceFingerprint()
                    const extraData = getDeviceData()
                    deviceData = {
                        device_fingerprint: fingerprint,
                        device_name: getDeviceName(),
                        ...extraData,
                    }
                } catch (e) {
                    console.warn('Could not generate device fingerprint:', e)
                }

                const response = await authApi.login(email, password, deviceData)
                const { user, token, device } = response.data.data

                this.user = user
                this.token = token

                localStorage.setItem('user', JSON.stringify(user))
                localStorage.setItem('token', token)

                // Only fallback to background registration if it explicitly failed during login
                if (deviceData.device_fingerprint && device?.error) {
                    this._registerDeviceInBackground(deviceData)
                }

                return { success: true }
            } catch (error) {
                this.error = error.response?.data?.message || 'Login failed'
                return { success: false, error: this.error }
            } finally {
                this.loading = false
            }
        },

        /**
         * Register device in background (fire-and-forget).
         * Does not block login flow.
         */
        async _registerDeviceInBackground(deviceData) {
            try {
                await authApi.registerDevice(deviceData)
            } catch (e) {
                // Silently ignore - device registration is best-effort
                console.warn('Background device registration failed:', e)
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

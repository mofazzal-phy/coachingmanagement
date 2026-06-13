import axios from 'axios'
import { useAuthStore } from '@/stores/auth.store'

// In dev mode, use relative URL so Vite proxy handles it (avoids CORS)
// In production, use the full backend URL
const baseURL = import.meta.env.DEV
  ? '/api/v1'
  : (import.meta.env.VITE_API_BASE_URL || 'https://coaching-management-system.test/api/v1')

// Custom params serializer to ensure arrays are always sent with [] suffix
// This prevents Axios from sending single-element arrays as plain values
function paramsSerializer(params) {
  const parts = []
  for (const [key, value] of Object.entries(params)) {
    if (Array.isArray(value)) {
      value.forEach(v => {
        parts.push(`${encodeURIComponent(key)}[]=${encodeURIComponent(v)}`)
      })
    } else if (value !== null && value !== undefined && value !== '') {
      parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
    }
  }
  return parts.join('&')
}

const apiClient = axios.create({
  baseURL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  paramsSerializer,
})

// JWT token attach
apiClient.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore()
    if (authStore.token) {
      config.headers.Authorization = `Bearer ${authStore.token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

const AUTH_ENDPOINTS = ['/auth/login', '/auth/me', '/auth/logout', '/auth/refresh']

let refreshPromise = null

function isRefreshableAuthMessage(message) {
  if (typeof message !== 'string') return false
  const normalized = message.toLowerCase()
  return normalized.includes('token expired') || normalized.includes('token invalid')
}

function shouldAttemptTokenRefresh(error) {
  const status = error.response?.status
  const message = error.response?.data?.message
  const requestUrl = error.config?.url || ''

  if (status !== 401) return false
  if (AUTH_ENDPOINTS.some(endpoint => requestUrl.startsWith(endpoint))) return false
  if (typeof message === 'string' && message.toLowerCase().includes('token not provided')) return false

  return isRefreshableAuthMessage(message)
}

async function refreshAccessToken() {
  if (!refreshPromise) {
    const authStore = useAuthStore()
    refreshPromise = axios.post(`${baseURL}/auth/refresh`, null, {
      headers: {
        Authorization: `Bearer ${authStore.token}`,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
    })
      .then((response) => {
        const newToken = response.data?.access_token
        if (!newToken) {
          throw new Error('Refresh response missing access_token')
        }
        authStore.setToken(newToken)
        if (response.data?.user) {
          authStore.user = response.data.user
          localStorage.setItem('user', JSON.stringify(response.data.user))
        }
        return newToken
      })
      .finally(() => {
        refreshPromise = null
      })
  }

  return refreshPromise
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config || {}
    const status = error.response?.status
    const requestUrl = originalRequest.url || ''
    const isAuthEndpoint = AUTH_ENDPOINTS.some(endpoint => requestUrl.startsWith(endpoint))

    if (shouldAttemptTokenRefresh(error) && !originalRequest._retry) {
      originalRequest._retry = true
      try {
        const newToken = await refreshAccessToken()
        originalRequest.headers = originalRequest.headers || {}
        originalRequest.headers.Authorization = `Bearer ${newToken}`
        return apiClient(originalRequest)
      } catch {
        const authStore = useAuthStore()
        authStore.logout()
        return Promise.reject(error)
      }
    }

    if (status === 401 && isAuthEndpoint) {
      const authStore = useAuthStore()
      authStore.logout()
    }

    return Promise.reject(error)
  }
)

export default apiClient

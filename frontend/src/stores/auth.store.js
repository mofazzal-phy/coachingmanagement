import { defineStore } from 'pinia'
import authService from '@/services/auth.service'
import { roleDefaultPermissions } from '@/config/roleDefaultPermissions'

const toCanonicalRole = (role) => {
  if (!role || typeof role !== 'string') return null

  const normalized = role.toLowerCase().replace(/[\s_-]+/g, '')

  if (['superadmin', 'admin'].includes(normalized)) return 'admin'
  if (['teacher'].includes(normalized)) return 'teacher'
  if (['employee', 'staff'].includes(normalized)) return 'employee'
  if (['student'].includes(normalized)) return 'student'
  if (['guardian'].includes(normalized)) return 'guardian'

  return null
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user') || 'null'),
    token: localStorage.getItem('token') || null,
  }),

  getters: {
    isLoggedIn: (state) => !!state.token,
    userRole: (state) => {
      const rolePriority = ['admin', 'teacher', 'employee', 'student', 'guardian']

      const directRole = toCanonicalRole(state.user?.role)
      if (directRole && rolePriority.includes(directRole)) {
        return directRole
      }

      const normalizedRoles = (state.user?.roles || [])
        .map((role) => toCanonicalRole(typeof role === 'string' ? role : role?.name))
        .filter(Boolean)

      return rolePriority.find((role) => normalizedRoles.includes(role)) || null
    },
    userPermissions: (state) => {
      return state.user?.permissions || []
    },
    dashboardPath: (state) => {
      const rolePriority = ['admin', 'teacher', 'employee', 'student', 'guardian']
      const toCanonical = (role) => {
        if (!role || typeof role !== 'string') return null
        const normalized = role.toLowerCase().replace(/[\s_-]+/g, '')
        if (['superadmin', 'admin'].includes(normalized)) return 'admin'
        if (normalized === 'teacher') return 'teacher'
        if (['employee', 'staff'].includes(normalized)) return 'employee'
        if (normalized === 'student') return 'student'
        if (normalized === 'guardian') return 'guardian'
        return null
      }

      const directRole = toCanonical(state.user?.role)
      const resolvedRole = directRole && rolePriority.includes(directRole)
        ? directRole
        : rolePriority.find((role) => {
            const normalizedRoles = (state.user?.roles || [])
              .map((r) => toCanonical(typeof r === 'string' ? r : r?.name))
              .filter(Boolean)
            return normalizedRoles.includes(role)
          }) || null

      if (resolvedRole === 'student') return '/student'
      if (resolvedRole === 'teacher') return '/teacher'
      if (resolvedRole === 'guardian') return '/guardian'
      if (resolvedRole === 'employee') return '/employee'
      return '/dashboard'
    },
  },

  actions: {
    async login(credentials) {
      const response = await authService.login(credentials)
      // Backend থেকে access_token এবং user পাবো
      this.token = response.data.access_token
      this.user = response.data.user
      localStorage.setItem('token', this.token)
      localStorage.setItem('user', JSON.stringify(this.user))
      return response.data
    },

    async fetchUser() {
      const response = await authService.me()
      this.user = response.data.data
      localStorage.setItem('user', JSON.stringify(this.user))
    },

    hasPermission(permission) {
      if (!this.user) return false
      // Super-admin and admin have all permissions
      if (this.user.role === 'admin' || this.user.role === 'super-admin') return true
      // Check direct permissions array on user
      if (this.user.permissions && this.user.permissions.includes(permission)) return true
      // Check permissions nested inside roles array
      if (this.user.roles && Array.isArray(this.user.roles)) {
        for (const role of this.user.roles) {
          if (role.permissions && Array.isArray(role.permissions)) {
            if (role.permissions.some(p => (typeof p === 'string' ? p : p.name) === permission)) {
              return true
            }
          }
        }
      }
      const role = this.userRole
      if (role && roleDefaultPermissions[role]?.includes(permission)) {
        return true
      }
      return false
    },

    hasAnyPermission(permissions) {
      return permissions.some(p => this.hasPermission(p))
    },

    hasAllPermissions(permissions) {
      return permissions.every(p => this.hasPermission(p))
    },

    logout() {
      this.user = null
      this.token = null
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      window.location.href = '/login'
    },

    setToken(token) {
      this.token = token
      localStorage.setItem('token', token)
    },
  },
})

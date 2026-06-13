<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>Edit User</h1>
      </div>
      <div class="header-actions">
        <router-link to="/dashboard/users" class="btn btn-outline">← Back to Users</router-link>
      </div>
    </div>

    <div class="form-card">
      <!-- Loading -->
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Loading user data...</p>
      </div>

      <!-- Error Alert -->
      <div v-if="error" class="alert alert-danger">
        <p>⚠️ {{ error }}</p>
      </div>

      <!-- Success Alert -->
      <div v-if="success" class="alert alert-success">
        <p>✅ {{ success }}</p>
      </div>

      <form @submit.prevent="submitForm" v-if="!loading && user">
        <div class="form-grid">
          <!-- Name -->
          <div class="form-group">
            <label for="name">Full Name <span class="required">*</span></label>
            <input 
              id="name"
              v-model="form.name" 
              type="text" 
              class="form-control" 
              :class="{ 'is-invalid': errors.name }"
              placeholder="Enter full name"
              required
            />
            <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
          </div>

          <!-- Email -->
          <div class="form-group">
            <label for="email">Email <span class="required">*</span></label>
            <input 
              id="email"
              v-model="form.email" 
              type="email" 
              class="form-control" 
              :class="{ 'is-invalid': errors.email }"
              placeholder="Enter email address"
              required
            />
            <span v-if="errors.email" class="field-error">{{ errors.email }}</span>
          </div>

          <!-- Phone -->
          <div class="form-group">
            <label for="phone">Phone</label>
            <input 
              id="phone"
              v-model="form.phone" 
              type="text" 
              class="form-control" 
              :class="{ 'is-invalid': errors.phone }"
              placeholder="Enter phone number"
            />
            <span v-if="errors.phone" class="field-error">{{ errors.phone }}</span>
          </div>

          <!-- Status -->
          <div class="form-group">
            <label for="status">Status</label>
            <select 
              id="status"
              v-model="form.is_active" 
              class="form-control"
            >
              <option :value="true">Active</option>
              <option :value="false">Inactive</option>
            </select>
          </div>

          <!-- New Password (optional) -->
          <div class="form-group">
            <label for="password">New Password</label>
            <input 
              id="password"
              v-model="form.password" 
              type="password" 
              class="form-control" 
              :class="{ 'is-invalid': errors.password }"
              placeholder="Leave blank to keep current"
            />
            <span class="field-hint">Leave empty to keep current password</span>
            <span v-if="errors.password" class="field-error">{{ errors.password }}</span>
          </div>

          <!-- Confirm Password -->
          <div class="form-group">
            <label for="password_confirmation">Confirm New Password</label>
            <input 
              id="password_confirmation"
              v-model="form.password_confirmation" 
              type="password" 
              class="form-control" 
              :class="{ 'is-invalid': errors.password_confirmation }"
              placeholder="Confirm new password"
            />
            <span v-if="errors.password_confirmation" class="field-error">{{ errors.password_confirmation }}</span>
          </div>
        </div>

        <div class="form-actions">
          <router-link to="/dashboard/users" class="btn btn-outline">Cancel</router-link>
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ submitting ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import userService from '@/services/user.service'

const route = useRoute()
const router = useRouter()

const user = ref(null)
const loading = ref(true)
const submitting = ref(false)
const error = ref(null)
const success = ref(null)

const form = ref({
  name: '',
  email: '',
  phone: '',
  is_active: true,
  password: '',
  password_confirmation: '',
})

const errors = ref({})

const loadUser = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await userService.get(route.params.id)
    const userData = response.data.data || response.data
    user.value = userData
    
    // Map API 'status' field (active/inactive) to frontend 'is_active' (boolean)
    const isActive = userData.is_active !== undefined 
      ? userData.is_active 
      : (userData.status === 'active')
    
    form.value = {
      name: userData.name || '',
      email: userData.email || '',
      phone: userData.phone || '',
      is_active: isActive,
      password: '',
      password_confirmation: '',
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load user'
  } finally {
    loading.value = false
  }
}

const validateForm = () => {
  const errs = {}
  if (!form.value.name.trim()) errs.name = 'Name is required'
  if (!form.value.email.trim()) errs.email = 'Email is required'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) errs.email = 'Invalid email format'
  
  if (form.value.password) {
    if (form.value.password.length < 8) errs.password = 'Password must be at least 8 characters'
    if (form.value.password !== form.value.password_confirmation) errs.password_confirmation = 'Passwords do not match'
  }
  
  errors.value = errs
  return Object.keys(errs).length === 0
}

const submitForm = async () => {
  if (!validateForm()) return
  
  submitting.value = true
  error.value = null
  success.value = null
  
  try {
    const data = {
      name: form.value.name,
      email: form.value.email,
      phone: form.value.phone || undefined,
      is_active: form.value.is_active,
    }
    
    // Only send password if provided
    if (form.value.password) {
      data.password = form.value.password
      data.password_confirmation = form.value.password_confirmation
    }
    
    await userService.update(route.params.id, data)
    
    success.value = 'User updated successfully!'
    
    setTimeout(() => {
      router.push('/dashboard/users')
    }, 1500)
  } catch (err) {
    const response = err.response?.data
    if (response?.errors) {
      const serverErrors = {}
      Object.keys(response.errors).forEach(key => {
        serverErrors[key] = response.errors[key][0]
      })
      errors.value = serverErrors
    }
    error.value = response?.message || 'Failed to update user'
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadUser()
})
</script>

<style scoped>
.page-container {
  max-width: 800px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
}

.header-left h1 {
  font-size: 1.5rem;
  color: var(--text-primary);
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 0.5rem;
}

.form-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 2rem;
  box-shadow: var(--shadow-sm);
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}

@media (max-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}

.form-group {
  margin-bottom: 0.25rem;
}

.form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 0.4rem;
}

.required {
  color: #ef4444;
}

.form-control {
  width: 100%;
  padding: 0.6rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.85rem;
  background: var(--bg-card);
  transition: border-color 0.2s;
  box-sizing: border-box;
}

.form-control:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.form-control.is-invalid {
  border-color: #ef4444;
}

.form-control.is-invalid:focus {
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.field-error {
  display: block;
  color: #ef4444;
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.field-hint {
  display: block;
  color: var(--text-muted);
  font-size: 0.75rem;
  margin-top: 0.2rem;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1.5rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--border-color);
}

.btn {
  padding: 0.6rem 1.2rem;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.btn-primary {
  background: #4f46e5;
  color: white;
}
.btn-primary:hover { background: #4338ca; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-outline {
  background: var(--bg-card);
  color: var(--text-secondary);
  border: 1px solid var(--border-color);
}
.btn-outline:hover { background: var(--bg-accent); border-color: var(--text-muted); }

.alert {
  padding: 0.75rem 1rem;
  border-radius: 8px;
  margin-bottom: 1rem;
  font-size: 0.85rem;
}

.alert p { margin: 0; }

.alert-danger {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.alert-success {
  background: #f0fdf4;
  color: #059669;
  border: 1px solid #bbf7d0;
}

.loading-state {
  text-align: center;
  padding: 2rem;
  color: var(--text-muted);
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e5e7eb;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin { to { transform: rotate(360deg); } }
</style>

<template>
  <div class="login-container">
    <button class="login-theme-btn" type="button" @click="toggleTheme" :title="theme === 'dark' ? 'Light mode' : 'Dark mode'">
      {{ theme === 'dark' ? '☀️' : '🌙' }}
    </button>
    <div class="login-card">
      <h2>Coaching Management System</h2>
      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label>Email / Username</label>
          <input v-model="form.email" type="text" required placeholder="Enter your email or username" />
        </div>
        <div class="form-group">
          <label>Password</label>
          <input v-model="form.password" type="password" required placeholder="Enter your password" />
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <button type="submit" :disabled="loading">Sign In</button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import { useTheme } from '@/composables/useTheme'

const router = useRouter()
const authStore = useAuthStore()
const { theme, toggleTheme } = useTheme()

const form = reactive({ email: '', password: '' })
const error = ref('')
const loading = ref(false)

const handleLogin = async () => {
  error.value = ''
  loading.value = true
  try {
    await authStore.login(form)
    router.push(authStore.dashboardPath)
  } catch (err) {
    // বিস্তারিত error দেখানোর জন্য
    if (err.response?.data?.message) {
      error.value = err.response.data.message
    } else if (err.message) {
      error.value = err.message
    } else {
      error.value = 'Login failed'
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  background-color: var(--bg-page);
  background-image:
    radial-gradient(120% 120% at 0% 0%, color-mix(in srgb, #8b5cf6 9%, transparent), transparent 55%),
    radial-gradient(120% 120% at 100% 100%, color-mix(in srgb, var(--primary-color) 8%, transparent), transparent 55%);
  background-repeat: no-repeat, no-repeat;
}
.login-card {
  background: var(--bg-card);
  padding: 2rem;
  border-radius: 16px;
  width: 400px;
  max-width: calc(100vw - 2rem);
  box-shadow: var(--shadow-lg, 0 24px 60px rgba(30, 41, 99, 0.18));
  border: 1px solid var(--border-color);
  color: var(--text-dark);
  position: relative;
  z-index: 1;
}
.login-card h2 {
  color: var(--text-primary);
  margin-bottom: 1.25rem;
}
.form-group {
  margin-bottom: 1rem;
}
.form-group label {
  display: block;
  margin-bottom: 0.35rem;
  font-weight: 600;
  color: var(--text-label);
  font-size: 0.9rem;
}
input {
  width: 100%;
  padding: 0.6rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--bg-input);
  color: var(--text-dark);
}
.login-card button[type="submit"] {
  width: 100%;
  padding: 0.75rem;
  background: var(--primary-color);
  color: var(--text-on-primary);
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  margin-top: 0.5rem;
}
.login-card button[type="submit"]:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.error {
  color: var(--danger-color);
  margin-bottom: 0.5rem;
  font-weight: 600;
}
</style>
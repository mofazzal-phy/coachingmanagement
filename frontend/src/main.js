import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { initTheme } from '@/utils/theme'

// Styles
import './assets/styles/main.scss'

// Apply theme + PrimeVue stylesheet before mount (avoids flash)
initTheme()

// Font Awesome
import '@fortawesome/fontawesome-free/css/all.min.css'

// PrimeVue plugin (registers Pagination, Modal, etc.)
import PrimeVuePlugin from '@/plugins/primevue'

// Toast plugin
import ToastPlugin from '@/plugins/toast.plugin'

// Public site scroll-reveal directive
import { reveal } from '@/directives/reveal'

const app = createApp(App)

app.directive('reveal', reveal)

// Global error handler to catch component errors
app.config.errorHandler = (err, instance, info) => {
  console.error('[Vue Error Handler]', err)
  console.error('[Vue Error Handler] Component:', instance?.$options?.name || instance?.__name || 'unknown')
  console.error('[Vue Error Handler] Info:', info)
}

app.use(createPinia())
app.use(router)
app.use(PrimeVuePlugin)
app.use(ToastPlugin)

app.mount('#app')

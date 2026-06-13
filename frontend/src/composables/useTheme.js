import { ref, onMounted } from 'vue'
import { initTheme, setTheme, toggleTheme as toggleThemeUtil } from '@/utils/theme'

const theme = ref('light')

export function useTheme() {
  const syncTheme = () => {
    if (typeof document === 'undefined') return
    theme.value = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'
  }

  const toggleTheme = () => {
    setTheme(toggleThemeUtil())
    syncTheme()
  }

  const applyTheme = (value) => {
    setTheme(value)
    syncTheme()
  }

  if (typeof document !== 'undefined') {
    if (!document.documentElement.getAttribute('data-theme')) {
      initTheme()
    }
    syncTheme()
  }

  onMounted(syncTheme)

  return {
    theme,
    toggleTheme,
    applyTheme,
    syncTheme,
    isDark: () => theme.value === 'dark',
  }
}

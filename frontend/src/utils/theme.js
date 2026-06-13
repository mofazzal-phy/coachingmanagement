const STORAGE_KEY = 'theme'
const THEME_LINK_ID = 'primevue-theme-link'

const PRIME_THEME_URLS = {
  light: () => new URL('primevue/resources/themes/lara-light-blue/theme.css', import.meta.url).href,
  dark: () => new URL('primevue/resources/themes/lara-dark-blue/theme.css', import.meta.url).href,
}

export function getSystemTheme() {
  if (typeof window === 'undefined') return 'light'
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

export function getStoredTheme() {
  if (typeof localStorage === 'undefined') return null
  const stored = localStorage.getItem(STORAGE_KEY)
  return stored === 'dark' || stored === 'light' ? stored : null
}

export function resolveTheme() {
  return getStoredTheme() || getSystemTheme()
}

export function applyPrimeTheme(theme) {
  if (typeof document === 'undefined') return

  let link = document.getElementById(THEME_LINK_ID)
  if (!link) {
    link = document.createElement('link')
    link.id = THEME_LINK_ID
    link.rel = 'stylesheet'
    document.head.appendChild(link)
  }
  link.href = PRIME_THEME_URLS[theme]()
}

export function applyTheme(theme) {
  if (typeof document === 'undefined') return theme

  const resolved = theme === 'dark' ? 'dark' : 'light'
  document.documentElement.setAttribute('data-theme', resolved)
  document.documentElement.style.colorScheme = resolved
  applyPrimeTheme(resolved)
  return resolved
}

export function initTheme() {
  const theme = resolveTheme()
  applyTheme(theme)
  return theme
}

export function setTheme(theme) {
  const resolved = theme === 'dark' ? 'dark' : 'light'
  localStorage.setItem(STORAGE_KEY, resolved)
  applyTheme(resolved)
  return resolved
}

export function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'
  return setTheme(current === 'dark' ? 'light' : 'dark')
}

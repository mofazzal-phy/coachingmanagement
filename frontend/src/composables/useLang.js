import { ref } from 'vue'
import { publicDictionary } from '@/i18n/publicDictionary'

const STORAGE_KEY = 'public_lang'
const SUPPORTED = ['bn', 'en']
const DEFAULT_LANG = 'bn' // Bangla primary

const readStored = () => {
  if (typeof localStorage === 'undefined') return DEFAULT_LANG
  const stored = localStorage.getItem(STORAGE_KEY)
  return SUPPORTED.includes(stored) ? stored : DEFAULT_LANG
}

// Shared singleton state across all components
const lang = ref(readStored())

const applyLangAttr = () => {
  if (typeof document !== 'undefined') {
    document.documentElement.setAttribute('lang', lang.value)
  }
}
applyLangAttr()

export function useLang() {
  const setLang = (value) => {
    if (!SUPPORTED.includes(value)) return
    lang.value = value
    if (typeof localStorage !== 'undefined') localStorage.setItem(STORAGE_KEY, value)
    applyLangAttr()
  }

  const toggleLang = () => setLang(lang.value === 'bn' ? 'en' : 'bn')

  // Translate by dictionary key
  const t = (key, fallback = '') => {
    const entry = publicDictionary[key]
    if (!entry) return fallback || key
    return entry[lang.value] ?? entry.bn ?? fallback ?? key
  }

  // Pick the right value from an object that may hold bn/en variants,
  // e.g. tf(item, 'title') checks item.title_bn / item.title_en / item.title
  const tf = (obj, field) => {
    if (!obj) return ''
    const localized = obj[`${field}_${lang.value}`]
    if (localized) return localized
    const bn = obj[`${field}_bn`]
    const en = obj[`${field}_en`]
    return localized || (lang.value === 'bn' ? bn : en) || obj[field] || bn || en || ''
  }

  return { lang, setLang, toggleLang, t, tf, isBn: () => lang.value === 'bn' }
}

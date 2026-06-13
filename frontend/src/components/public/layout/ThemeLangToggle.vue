<template>
  <div class="tl-toggle">
    <button
      class="tl-btn tl-lang"
      type="button"
      :aria-label="lang === 'bn' ? 'Switch to English' : 'বাংলায় দেখুন'"
      @click="toggleLang"
    >
      {{ lang === 'bn' ? 'EN' : 'বাং' }}
    </button>
    <button
      class="tl-btn tl-theme"
      type="button"
      :aria-label="theme === 'dark' ? 'Light mode' : 'Dark mode'"
      @click="toggleTheme"
    >
      <svg v-if="theme === 'dark'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="5" />
        <line x1="12" y1="1" x2="12" y2="3" /><line x1="12" y1="21" x2="12" y2="23" />
        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" /><line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
        <line x1="1" y1="12" x2="3" y2="12" /><line x1="21" y1="12" x2="23" y2="12" />
        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" /><line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
      </svg>
      <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
      </svg>
    </button>
  </div>
</template>

<script setup>
import { useTheme } from '@/composables/useTheme'
import { useLang } from '@/composables/useLang'

const { theme, toggleTheme } = useTheme()
const { lang, toggleLang } = useLang()
</script>

<style scoped>
.tl-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.tl-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 38px;
  min-width: 38px;
  padding: 0 0.6rem;
  border-radius: var(--pub-radius-pill);
  border: 1.5px solid var(--pub-border);
  background: var(--pub-surface);
  color: var(--pub-text-soft);
  font-size: 0.8rem;
  font-weight: 800;
  font-family: inherit;
  cursor: pointer;
  transition: all 0.18s ease;
}

.tl-btn:hover {
  border-color: var(--pub-accent);
  color: var(--pub-accent);
  transform: translateY(-1px);
}
</style>

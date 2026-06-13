<template>
  <header class="ph" :class="{ scrolled }">
    <div class="ph-inner pub-container">
      <router-link to="/site" class="ph-brand" @click="closeAll">
        <span class="ph-logo">প</span>
        <span class="ph-brand-text">
          <strong>{{ t('brand.name') }}</strong>
          <small>{{ t('brand.tagline') }}</small>
        </span>
      </router-link>

      <nav class="ph-nav" :aria-label="t('nav.menu')">
        <router-link to="/site" class="ph-link">{{ t('nav.home') }}</router-link>
        <router-link to="/site/courses" class="ph-link">{{ t('nav.courses') }}</router-link>

        <div class="ph-dd" @mouseenter="openMenu = 'admission'" @mouseleave="openMenu = ''">
          <button class="ph-link ph-dd-btn" :class="{ active: openMenu === 'admission' }">
            {{ t('nav.admission') }}
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6" /></svg>
          </button>
          <transition name="ph-pop">
            <div v-if="openMenu === 'admission'" class="ph-menu">
              <router-link to="/site/enroll" class="ph-menu-item">
                <span class="ph-menu-ic">📝</span>
                <span><strong>{{ t('nav.apply') }}</strong></span>
              </router-link>
              <router-link to="/site/batches" class="ph-menu-item">
                <span class="ph-menu-ic">📅</span>
                <span><strong>{{ t('nav.batches') }}</strong></span>
              </router-link>
              <router-link to="/site/track" class="ph-menu-item">
                <span class="ph-menu-ic">🔍</span>
                <span><strong>{{ t('nav.trackApplication') }}</strong></span>
              </router-link>
            </div>
          </transition>
        </div>

        <div class="ph-dd" @mouseenter="openMenu = 'about'" @mouseleave="openMenu = ''">
          <button class="ph-link ph-dd-btn" :class="{ active: openMenu === 'about' }">
            {{ t('nav.about') }}
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6" /></svg>
          </button>
          <transition name="ph-pop">
            <div v-if="openMenu === 'about'" class="ph-menu">
              <router-link to="/site/about" class="ph-menu-item"><span class="ph-menu-ic">🏫</span><span><strong>{{ t('nav.about') }}</strong></span></router-link>
              <router-link to="/site/teachers" class="ph-menu-item"><span class="ph-menu-ic">👩‍🏫</span><span><strong>{{ t('nav.teachers') }}</strong></span></router-link>
              <router-link to="/site/success-stories" class="ph-menu-item"><span class="ph-menu-ic">🏆</span><span><strong>{{ t('nav.successStories') }}</strong></span></router-link>
              <router-link to="/site/gallery" class="ph-menu-item"><span class="ph-menu-ic">🖼️</span><span><strong>{{ t('nav.gallery') }}</strong></span></router-link>
            </div>
          </transition>
        </div>

        <div class="ph-dd" @mouseenter="openMenu = 'resources'" @mouseleave="openMenu = ''">
          <button class="ph-link ph-dd-btn" :class="{ active: openMenu === 'resources' }">
            {{ t('nav.resources') }}
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6" /></svg>
          </button>
          <transition name="ph-pop">
            <div v-if="openMenu === 'resources'" class="ph-menu">
              <router-link to="/site/blog" class="ph-menu-item"><span class="ph-menu-ic">✍️</span><span><strong>{{ t('nav.blog') }}</strong></span></router-link>
              <router-link to="/site/notices" class="ph-menu-item"><span class="ph-menu-ic">📢</span><span><strong>{{ t('nav.notices') }}</strong></span></router-link>
              <router-link to="/site/events" class="ph-menu-item"><span class="ph-menu-ic">🎯</span><span><strong>{{ t('nav.events') }}</strong></span></router-link>
              <router-link to="/site/downloads" class="ph-menu-item"><span class="ph-menu-ic">📥</span><span><strong>{{ t('nav.downloads') }}</strong></span></router-link>
            </div>
          </transition>
        </div>

        <router-link to="/site/contact" class="ph-link">{{ t('nav.contact') }}</router-link>
      </nav>

      <div class="ph-actions">
        <ThemeLangToggle class="ph-toggle" />
        <router-link to="/login" class="ph-login">{{ t('nav.login') }}</router-link>
        <router-link to="/site/enroll" class="pub-btn pub-btn--primary ph-cta">{{ t('nav.apply') }}</router-link>
        <button class="ph-burger" :aria-label="t('nav.menu')" @click="mobileOpen = true">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>

    <!-- Mobile drawer -->
    <transition name="ph-drawer">
      <div v-if="mobileOpen" class="ph-mobile">
        <div class="ph-mobile-backdrop" @click="mobileOpen = false"></div>
        <aside class="ph-mobile-panel">
          <div class="ph-mobile-top">
            <router-link to="/site" class="ph-brand" @click="closeAll">
              <span class="ph-logo">প</span>
              <strong>{{ t('brand.name') }}</strong>
            </router-link>
            <button class="ph-close" aria-label="Close" @click="mobileOpen = false">✕</button>
          </div>

          <nav class="ph-mobile-nav">
            <router-link to="/site" @click="closeAll">{{ t('nav.home') }}</router-link>
            <router-link to="/site/courses" @click="closeAll">{{ t('nav.courses') }}</router-link>
            <router-link to="/site/batches" @click="closeAll">{{ t('nav.batches') }}</router-link>
            <router-link to="/site/enroll" @click="closeAll">{{ t('nav.apply') }}</router-link>
            <router-link to="/site/about" @click="closeAll">{{ t('nav.about') }}</router-link>
            <router-link to="/site/teachers" @click="closeAll">{{ t('nav.teachers') }}</router-link>
            <router-link to="/site/success-stories" @click="closeAll">{{ t('nav.successStories') }}</router-link>
            <router-link to="/site/gallery" @click="closeAll">{{ t('nav.gallery') }}</router-link>
            <router-link to="/site/blog" @click="closeAll">{{ t('nav.blog') }}</router-link>
            <router-link to="/site/notices" @click="closeAll">{{ t('nav.notices') }}</router-link>
            <router-link to="/site/events" @click="closeAll">{{ t('nav.events') }}</router-link>
            <router-link to="/site/downloads" @click="closeAll">{{ t('nav.downloads') }}</router-link>
            <router-link to="/site/contact" @click="closeAll">{{ t('nav.contact') }}</router-link>
          </nav>

          <div class="ph-mobile-foot">
            <ThemeLangToggle />
            <router-link to="/login" class="pub-btn pub-btn--ghost" @click="closeAll">{{ t('nav.login') }}</router-link>
            <router-link to="/site/enroll" class="pub-btn pub-btn--primary" @click="closeAll">{{ t('nav.apply') }}</router-link>
          </div>
        </aside>
      </div>
    </transition>
  </header>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useLang } from '@/composables/useLang'
import ThemeLangToggle from './ThemeLangToggle.vue'

const { t } = useLang()
const route = useRoute()

const scrolled = ref(false)
const openMenu = ref('')
const mobileOpen = ref(false)

const onScroll = () => { scrolled.value = window.scrollY > 12 }

const closeAll = () => {
  openMenu.value = ''
  mobileOpen.value = false
}

watch(() => route.fullPath, closeAll)

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true })
  onScroll()
})
onUnmounted(() => window.removeEventListener('scroll', onScroll))
</script>

<style scoped>
.ph {
  position: sticky;
  top: 0;
  z-index: 100;
  background: var(--pub-surface-glass);
  backdrop-filter: blur(14px);
  border-bottom: 1px solid transparent;
  transition: border-color 0.25s, box-shadow 0.25s, background 0.25s;
}
.ph.scrolled {
  border-color: var(--pub-border);
  box-shadow: var(--pub-shadow-sm);
}

.ph-inner {
  height: var(--pub-header-h);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.ph-brand {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  text-decoration: none;
  color: var(--pub-text);
  flex-shrink: 0;
}
.ph-logo {
  width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  border-radius: 12px;
  background: var(--pub-hero-grad);
  color: #fff;
  font-size: 1.25rem;
  font-weight: 800;
  box-shadow: var(--pub-shadow-accent);
}
.ph-brand-text { display: flex; flex-direction: column; line-height: 1.1; }
.ph-brand-text strong { font-size: 1.1rem; font-weight: 800; }
.ph-brand-text small { font-size: 0.66rem; color: var(--pub-text-muted); }

.ph-nav {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.ph-link {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.5rem 0.8rem;
  border-radius: var(--pub-radius-pill);
  font-size: 0.92rem;
  font-weight: 600;
  color: var(--pub-text-soft);
  text-decoration: none;
  background: none;
  border: none;
  cursor: pointer;
  font-family: inherit;
  transition: color 0.18s, background 0.18s;
}
.ph-link:hover,
.ph-link.active,
.ph-link.router-link-active {
  color: var(--pub-accent);
  background: var(--pub-accent-soft);
}

.ph-dd { position: relative; }
.ph-dd-btn svg { transition: transform 0.2s; }
.ph-dd-btn.active svg { transform: rotate(180deg); }

.ph-menu {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  min-width: 230px;
  background: var(--pub-surface);
  border: 1px solid var(--pub-border);
  border-radius: var(--pub-radius);
  box-shadow: var(--pub-shadow-lg);
  padding: 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.ph-menu-item {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.6rem 0.7rem;
  border-radius: var(--pub-radius-sm);
  text-decoration: none;
  color: var(--pub-text);
  font-size: 0.9rem;
  transition: background 0.15s;
}
.ph-menu-item:hover { background: var(--pub-accent-soft); color: var(--pub-accent); }
.ph-menu-ic { font-size: 1.1rem; }

.ph-actions { display: flex; align-items: center; gap: 0.6rem; flex-shrink: 0; }
.ph-login {
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--pub-text);
  text-decoration: none;
  padding: 0.4rem 0.6rem;
}
.ph-login:hover { color: var(--pub-accent); }
.ph-cta { padding: 0.6rem 1.15rem; font-size: 0.9rem; }

.ph-burger {
  display: none;
  flex-direction: column;
  gap: 4px;
  width: 42px;
  height: 42px;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  border: 1.5px solid var(--pub-border);
  background: var(--pub-surface);
  cursor: pointer;
}
.ph-burger span { width: 18px; height: 2px; background: var(--pub-text); border-radius: 2px; }

/* dropdown transition */
.ph-pop-enter-active, .ph-pop-leave-active { transition: opacity 0.16s ease, transform 0.16s ease; }
.ph-pop-enter-from, .ph-pop-leave-to { opacity: 0; transform: translateY(-6px); }

/* Mobile drawer */
.ph-mobile { position: fixed; inset: 0; z-index: 200; }
.ph-mobile-backdrop { position: absolute; inset: 0; background: var(--pub-hero-overlay); }
.ph-mobile-panel {
  position: absolute;
  top: 0; right: 0;
  height: 100%;
  width: min(86vw, 340px);
  background: var(--pub-surface);
  border-left: 1px solid var(--pub-border);
  display: flex;
  flex-direction: column;
  padding: 1rem 1.1rem;
  overflow-y: auto;
}
.ph-mobile-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
.ph-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--pub-text-muted); }
.ph-mobile-nav { display: flex; flex-direction: column; gap: 0.15rem; }
.ph-mobile-nav a {
  padding: 0.75rem 0.6rem;
  border-radius: var(--pub-radius-sm);
  text-decoration: none;
  color: var(--pub-text);
  font-weight: 600;
  font-size: 0.95rem;
}
.ph-mobile-nav a:hover,
.ph-mobile-nav a.router-link-active { background: var(--pub-accent-soft); color: var(--pub-accent); }
.ph-mobile-foot { margin-top: auto; padding-top: 1rem; display: flex; flex-direction: column; gap: 0.6rem; }

.ph-drawer-enter-active, .ph-drawer-leave-active { transition: opacity 0.22s ease; }
.ph-drawer-enter-active .ph-mobile-panel, .ph-drawer-leave-active .ph-mobile-panel { transition: transform 0.26s cubic-bezier(0.22, 1, 0.36, 1); }
.ph-drawer-enter-from, .ph-drawer-leave-to { opacity: 0; }
.ph-drawer-enter-from .ph-mobile-panel, .ph-drawer-leave-to .ph-mobile-panel { transform: translateX(100%); }

@media (max-width: 1024px) {
  .ph-nav { display: none; }
  .ph-login, .ph-cta { display: none; }
  .ph-burger { display: flex; }
}
@media (max-width: 480px) {
  .ph-brand-text small { display: none; }
  .ph-toggle { display: none; }
}
</style>

<template>
  <section class="ph-banner">
    <div class="ph-banner-mesh"></div>
    <div class="pub-container ph-banner-inner">
      <nav class="ph-crumbs" aria-label="Breadcrumb">
        <router-link to="/site">{{ t('nav.home') }}</router-link>
        <span>/</span>
        <span class="current">{{ title }}</span>
      </nav>
      <h1 class="ph-banner-title">{{ title }}</h1>
      <p v-if="subtitle" class="ph-banner-sub">{{ subtitle }}</p>
      <slot />
    </div>
  </section>
</template>

<script setup>
import { useLang } from '@/composables/useLang'

defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
})

const { t } = useLang()
</script>

<style scoped>
.ph-banner {
  position: relative;
  background: var(--pub-hero-grad);
  color: #fff;
  overflow: hidden;
  padding: clamp(2.5rem, 7vw, 4.5rem) 0 clamp(2rem, 5vw, 3.5rem);
}
.ph-banner-mesh {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(600px circle at 85% 10%, rgba(255, 255, 255, 0.16), transparent 45%),
    radial-gradient(500px circle at 10% 90%, rgba(255, 255, 255, 0.1), transparent 45%);
  pointer-events: none;
}
.ph-banner-inner { position: relative; z-index: 1; }
.ph-crumbs { display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; opacity: 0.9; margin-bottom: 0.75rem; }
.ph-crumbs a { color: #fff; text-decoration: none; opacity: 0.85; }
.ph-crumbs a:hover { opacity: 1; }
.ph-crumbs .current { font-weight: 700; }
.ph-banner-title { font-size: clamp(1.6rem, 4.5vw, 2.6rem); font-weight: 800; margin: 0; letter-spacing: -0.01em; }
.ph-banner-sub { margin: 0.6rem 0 0; font-size: 1.02rem; opacity: 0.92; max-width: 640px; }
</style>

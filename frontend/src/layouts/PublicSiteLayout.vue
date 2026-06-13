<template>
  <div class="public-site">
    <PublicHeader />

    <main class="site-main">
      <router-view v-slot="{ Component }">
        <transition name="page-fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>

    <PublicFooter />

    <transition name="fab-fade">
      <button v-if="showTop" class="scroll-top" aria-label="Back to top" @click="scrollTop">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 15l-6-6-6 6" />
        </svg>
      </button>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import PublicHeader from '@/components/public/layout/PublicHeader.vue'
import PublicFooter from '@/components/public/layout/PublicFooter.vue'

const showTop = ref(false)
const onScroll = () => { showTop.value = window.scrollY > 600 }
const scrollTop = () => window.scrollTo({ top: 0, behavior: 'smooth' })

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true })
  onScroll()
})
onUnmounted(() => window.removeEventListener('scroll', onScroll))
</script>

<style scoped>
.site-main { flex: 1; }

.scroll-top {
  position: fixed;
  right: 1.5rem;
  bottom: 1.5rem;
  z-index: 90;
  width: 46px;
  height: 46px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  border: none;
  cursor: pointer;
  color: #fff;
  background: var(--pub-accent);
  box-shadow: var(--pub-shadow-accent);
  transition: transform 0.2s;
}
.scroll-top:hover { transform: translateY(-3px); }

.page-fade-enter-active, .page-fade-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.page-fade-enter-from { opacity: 0; transform: translateY(8px); }
.page-fade-leave-to { opacity: 0; }

.fab-fade-enter-active, .fab-fade-leave-active { transition: opacity 0.2s, transform 0.2s; }
.fab-fade-enter-from, .fab-fade-leave-to { opacity: 0; transform: scale(0.8); }
</style>

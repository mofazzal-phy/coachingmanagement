<template>
  <div>
    <PublicHero :title="t('nav.gallery')" :subtitle="isBn() ? 'আমাদের মুহূর্ত ও অর্জনসমূহ' : 'Our moments and achievements'" />
    <section class="pub-section">
      <div class="pub-container">
        <ContentState :loading="loading" :error="error" :empty="!loading && !error && !items.length" :on-retry="load" />
        <div v-if="!loading && items.length" class="gal-grid">
          <figure v-for="(g, i) in items" :key="g.id" class="gal-cell" @click="open(i)">
            <img :src="g.file_url || g.thumbnail_url || g.image_url" :alt="g.title" loading="lazy" />
            <figcaption v-if="g.title">{{ g.title }}</figcaption>
          </figure>
        </div>
      </div>
    </section>

    <transition name="lb-fade">
      <div v-if="lightbox !== null" class="lb" @click="lightbox = null">
        <button class="lb-close" @click.stop="lightbox = null">✕</button>
        <button class="lb-nav lb-prev" @click.stop="prev">‹</button>
        <img :src="current.file_url || current.thumbnail_url || current.image_url" :alt="current.title" @click.stop />
        <button class="lb-nav lb-next" @click.stop="next">›</button>
        <p v-if="current.title" class="lb-cap">{{ current.title }}</p>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import cmsPublicService from '@/services/cms-public.service'
import { useLang } from '@/composables/useLang'
import PublicHero from '@/components/public/PublicHero.vue'
import ContentState from '@/components/public/ContentState.vue'

const { t, isBn } = useLang()
const items = ref([])
const loading = ref(true)
const error = ref(false)
const lightbox = ref(null)

const current = computed(() => items.value[lightbox.value] || {})
const open = (i) => { lightbox.value = i }
const next = () => { lightbox.value = (lightbox.value + 1) % items.value.length }
const prev = () => { lightbox.value = (lightbox.value - 1 + items.value.length) % items.value.length }

const load = async () => {
  loading.value = true; error.value = false
  try { items.value = cmsPublicService.extractList(await cmsPublicService.galleries()) }
  catch { error.value = true } finally { loading.value = false }
}
onMounted(load)
</script>

<style scoped>
.gal-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.85rem; }
.gal-cell { position: relative; margin: 0; aspect-ratio: 1; border-radius: var(--pub-radius); overflow: hidden; cursor: pointer; }
.gal-cell img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s; }
.gal-cell:hover img { transform: scale(1.08); }
.gal-cell figcaption { position: absolute; inset: auto 0 0 0; padding: 0.6rem; background: linear-gradient(transparent, rgba(0,0,0,0.7)); color: #fff; font-size: 0.78rem; opacity: 0; transition: opacity 0.2s; }
.gal-cell:hover figcaption { opacity: 1; }
@media (max-width: 800px) { .gal-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 500px) { .gal-grid { grid-template-columns: repeat(2, 1fr); } }

.lb { position: fixed; inset: 0; z-index: 300; background: rgba(0,0,0,0.92); display: grid; place-items: center; }
.lb img { max-width: 90vw; max-height: 82vh; border-radius: 8px; }
.lb-close { position: absolute; top: 1.2rem; right: 1.5rem; background: none; border: none; color: #fff; font-size: 1.6rem; cursor: pointer; }
.lb-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.12); border: none; color: #fff; width: 50px; height: 50px; border-radius: 50%; font-size: 2rem; cursor: pointer; }
.lb-prev { left: 1.5rem; } .lb-next { right: 1.5rem; }
.lb-cap { position: absolute; bottom: 1.5rem; color: #fff; font-size: 0.95rem; }
.lb-fade-enter-active, .lb-fade-leave-active { transition: opacity 0.2s; }
.lb-fade-enter-from, .lb-fade-leave-to { opacity: 0; }
</style>

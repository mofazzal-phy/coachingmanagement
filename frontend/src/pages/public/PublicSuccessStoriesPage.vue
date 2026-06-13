<template>
  <div>
    <PublicHero :title="t('nav.successStories')" :subtitle="isBn() ? 'আমাদের গর্ব — শিক্ষার্থীদের সাফল্য' : 'Our pride — students’ achievements'" />
    <section class="pub-section">
      <div class="pub-container">
        <ContentState :loading="loading" :error="error" :empty="!loading && !error && !items.length" :on-retry="load" />
        <div v-if="!loading && items.length" class="ss-grid">
          <router-link v-for="s in items" :key="s.id" :to="`/site/success-stories/${s.slug}`" class="ss-card pub-card" v-reveal>
            <div class="ss-media">
              <img v-if="s.image_url || s.photo_url" :src="s.image_url || s.photo_url" :alt="s.title" loading="lazy" />
              <div v-else class="ss-ph">🎓</div>
            </div>
            <div class="ss-body">
              <span v-if="s.achievement" class="pub-chip pub-chip--gold">🏆 {{ s.achievement }}</span>
              <h3>{{ s.student_name || s.title }}</h3>
              <p v-if="s.excerpt || s.description">{{ stripHtml(s.excerpt || s.description).slice(0, 110) }}…</p>
            </div>
          </router-link>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import cmsPublicService from '@/services/cms-public.service'
import { useLang } from '@/composables/useLang'
import PublicHero from '@/components/public/PublicHero.vue'
import ContentState from '@/components/public/ContentState.vue'

const { t, isBn } = useLang()
const items = ref([])
const loading = ref(true)
const error = ref(false)
const stripHtml = (s) => (s ? String(s).replace(/<[^>]*>/g, '') : '')

const load = async () => {
  loading.value = true; error.value = false
  try { items.value = cmsPublicService.extractList(await cmsPublicService.successStories()) }
  catch { error.value = true } finally { loading.value = false }
}
onMounted(load)
</script>

<style scoped>
.ss-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
.ss-card { overflow: hidden; text-decoration: none; color: inherit; }
.ss-media { aspect-ratio: 1; overflow: hidden; }
.ss-media img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s; }
.ss-card:hover .ss-media img { transform: scale(1.05); }
.ss-ph { width: 100%; height: 100%; display: grid; place-items: center; font-size: 3rem; background: var(--pub-accent-soft); }
.ss-body { padding: 1.2rem; }
.ss-body h3 { font-size: 1.1rem; font-weight: 800; margin: 0.6rem 0 0.4rem; color: var(--pub-text); }
.ss-body p { font-size: 0.86rem; color: var(--pub-text-muted); margin: 0; line-height: 1.55; }
@media (max-width: 900px) { .ss-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 520px) { .ss-grid { grid-template-columns: 1fr; } }
</style>

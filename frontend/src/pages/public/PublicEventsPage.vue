<template>
  <div>
    <PublicHero :title="t('nav.events')" :subtitle="isBn() ? 'আমাদের আসন্ন ইভেন্ট ও কার্যক্রম' : 'Our upcoming events and activities'" />
    <section class="pub-section">
      <div class="pub-container">
        <ContentState :loading="loading" :error="error" :empty="!loading && !error && !items.length" :on-retry="load" />
        <div v-if="!loading && items.length" class="event-grid">
          <article v-for="ev in items" :key="ev.id" class="event-card pub-card" v-reveal>
            <div class="ec-media">
              <img v-if="ev.image_url || ev.banner_url" :src="ev.image_url || ev.banner_url" :alt="ev.title" loading="lazy" />
              <div v-else class="ec-media-ph">🎯</div>
              <div class="ec-date">
                <strong>{{ day(ev) }}</strong><span>{{ month(ev) }}</span>
              </div>
            </div>
            <div class="ec-body">
              <h3>{{ ev.title }}</h3>
              <p v-if="ev.location" class="ec-loc">📍 {{ ev.location }}</p>
              <p v-if="ev.description" class="ec-desc">{{ stripHtml(ev.description) }}</p>
            </div>
          </article>
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

const dateOf = (e) => e.start_date || e.event_date || e.date || e.created_at
const day = (e) => { const d = dateOf(e); return d ? new Date(d).getDate() : '•' }
const month = (e) => { const d = dateOf(e); return d ? new Date(d).toLocaleDateString(isBn() ? 'bn-BD' : 'en-US', { month: 'short' }) : '' }
const stripHtml = (s) => (s ? String(s).replace(/<[^>]*>/g, '') : '')

const load = async () => {
  loading.value = true; error.value = false
  try { items.value = cmsPublicService.extractList(await cmsPublicService.events()) }
  catch { error.value = true } finally { loading.value = false }
}
onMounted(load)
</script>

<style scoped>
.event-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
.event-card { overflow: hidden; }
.ec-media { position: relative; aspect-ratio: 16 / 9; overflow: hidden; }
.ec-media img { width: 100%; height: 100%; object-fit: cover; }
.ec-media-ph { width: 100%; height: 100%; display: grid; place-items: center; font-size: 2.5rem; background: var(--pub-accent-soft); }
.ec-date { position: absolute; top: 0.8rem; left: 0.8rem; width: 56px; height: 56px; border-radius: 12px; background: var(--pub-surface); color: var(--pub-accent); display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: var(--pub-shadow-md); }
.ec-date strong { font-size: 1.3rem; line-height: 1; }
.ec-date span { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; }
.ec-body { padding: 1.1rem 1.2rem 1.3rem; }
.ec-body h3 { font-size: 1.1rem; font-weight: 800; margin: 0 0 0.4rem; color: var(--pub-text); }
.ec-loc { font-size: 0.82rem; color: var(--pub-accent); margin: 0 0 0.5rem; font-weight: 600; }
.ec-desc { font-size: 0.86rem; color: var(--pub-text-muted); margin: 0; line-height: 1.55; }
@media (max-width: 900px) { .event-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) { .event-grid { grid-template-columns: 1fr; } }
</style>

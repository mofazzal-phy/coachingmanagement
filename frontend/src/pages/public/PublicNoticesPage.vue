<template>
  <div>
    <PublicHero :title="t('nav.notices')" :subtitle="isBn() ? 'সর্বশেষ ঘোষণা ও নোটিশসমূহ' : 'Latest announcements and notices'" />
    <section class="pub-section">
      <div class="pub-container">
        <ContentState :loading="loading" :error="error" :empty="!loading && !error && !items.length" :on-retry="load" />
        <div v-if="!loading && items.length" class="notice-feed">
          <article v-for="n in items" :key="n.id" class="notice-item pub-card" v-reveal>
            <div class="ni-date">
              <strong>{{ day(n) }}</strong><span>{{ month(n) }}</span>
            </div>
            <div class="ni-body">
              <div class="ni-head">
                <h3>{{ n.title }}</h3>
                <span v-if="n.is_important || n.priority === 'high'" class="pub-chip pub-chip--gold">{{ isBn() ? 'গুরুত্বপূর্ণ' : 'Important' }}</span>
              </div>
              <p v-if="n.content || n.description">{{ stripHtml(n.content || n.description) }}</p>
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

const dateOf = (n) => n.publish_date || n.published_at || n.created_at
const day = (n) => { const d = dateOf(n); return d ? new Date(d).getDate() : '•' }
const month = (n) => { const d = dateOf(n); return d ? new Date(d).toLocaleDateString(isBn() ? 'bn-BD' : 'en-US', { month: 'short' }) : '' }
const stripHtml = (s) => (s ? String(s).replace(/<[^>]*>/g, '') : '')

const load = async () => {
  loading.value = true; error.value = false
  try { items.value = cmsPublicService.extractList(await cmsPublicService.notices()) }
  catch { error.value = true } finally { loading.value = false }
}
onMounted(load)
</script>

<style scoped>
.notice-feed { display: flex; flex-direction: column; gap: 1rem; max-width: 820px; margin: 0 auto; }
.notice-item { display: flex; gap: 1.25rem; padding: 1.25rem; }
.ni-date { flex-shrink: 0; width: 64px; height: 64px; border-radius: 14px; background: var(--pub-accent-soft); color: var(--pub-accent); display: flex; flex-direction: column; align-items: center; justify-content: center; }
.ni-date strong { font-size: 1.5rem; line-height: 1; }
.ni-date span { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; }
.ni-head { display: flex; align-items: center; gap: 0.75rem; justify-content: space-between; }
.ni-body h3 { font-size: 1.05rem; font-weight: 800; margin: 0 0 0.35rem; color: var(--pub-text); }
.ni-body p { font-size: 0.88rem; color: var(--pub-text-muted); margin: 0; line-height: 1.55; }
</style>

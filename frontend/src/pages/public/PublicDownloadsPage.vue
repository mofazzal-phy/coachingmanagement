<template>
  <div>
    <PublicHero :title="t('section.downloads')" :subtitle="isBn() ? 'ব্রোশিওর, ফর্ম, সিলেবাস ও অন্যান্য ফ্রি রিসোর্স' : 'Brochures, forms, syllabi and other free resources'" />

    <section class="pub-section">
      <div class="pub-container">
        <ContentState :loading="loading" :error="error" :empty="!loading && !error && !items.length" :on-retry="loadItems" />

        <div v-if="!loading && items.length" class="dl-grid">
          <article v-for="item in items" :key="item.id" class="dl-card pub-card" v-reveal>
            <span class="dl-ic">📄</span>
            <div class="dl-body">
              <span class="dl-cat">{{ item.category || (isBn() ? 'রিসোর্স' : 'resource') }}</span>
              <h3>{{ item.title }}</h3>
              <p v-if="item.description">{{ item.description }}</p>
            </div>
            <button class="pub-btn pub-btn--primary dl-btn" :disabled="downloadingId === item.id" @click="handleDownload(item)">
              {{ downloadingId === item.id ? t('action.sending') : t('action.download') }}
            </button>
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
const loading = ref(true)
const error = ref(false)
const items = ref([])
const downloadingId = ref(null)

const loadItems = async () => {
  loading.value = true
  error.value = false
  try {
    const res = await cmsPublicService.downloads()
    items.value = cmsPublicService.extractList(res)
  } catch (err) {
    error.value = err.response?.data?.message || true
  } finally {
    loading.value = false
  }
}

const handleDownload = async (item) => {
  downloadingId.value = item.id
  try {
    const res = await cmsPublicService.downloadFile(item.id)
    const data = cmsPublicService.extractData(res)
    if (data?.file_url) {
      window.open(data.file_url, '_blank', 'noopener')
    }
  } catch (err) {
    error.value = err.response?.data?.message || true
  } finally {
    downloadingId.value = null
  }
}

onMounted(loadItems)
</script>

<style scoped>
.dl-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; max-width: 920px; margin: 0 auto; }
.dl-card { display: flex; align-items: center; gap: 1rem; padding: 1.1rem 1.2rem; }
.dl-ic { width: 48px; height: 48px; border-radius: 12px; display: grid; place-items: center; font-size: 1.5rem; background: var(--pub-accent-soft); flex-shrink: 0; }
.dl-body { flex: 1; min-width: 0; }
.dl-cat { font-size: 0.7rem; text-transform: uppercase; color: var(--pub-text-muted); letter-spacing: 0.04em; font-weight: 700; }
.dl-body h3 { margin: 0.2rem 0; font-size: 1rem; font-weight: 800; color: var(--pub-text); }
.dl-body p { margin: 0; color: var(--pub-text-muted); font-size: 0.84rem; }
.dl-btn { flex-shrink: 0; padding: 0.6rem 1.1rem; }
@media (max-width: 700px) { .dl-grid { grid-template-columns: 1fr; } }
@media (max-width: 460px) { .dl-card { flex-wrap: wrap; } .dl-btn { width: 100%; } }
</style>

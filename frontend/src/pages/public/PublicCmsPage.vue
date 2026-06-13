<template>
  <div>
    <ContentState v-if="loading || error" :loading="loading" :error="error" :on-retry="loadPage" />

    <template v-else-if="page">
      <PublicHero :title="page.title" :subtitle="page.meta_description || ''" />
      <section class="pub-section">
        <div class="pub-container cms-body">
          <div class="cms-content" v-html="page.content"></div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import cmsPublicService from '@/services/cms-public.service'
import PublicHero from '@/components/public/PublicHero.vue'
import ContentState from '@/components/public/ContentState.vue'

const route = useRoute()
const loading = ref(true)
const error = ref(false)
const page = ref(null)

const loadPage = async () => {
  loading.value = true
  error.value = false
  try {
    const res = await cmsPublicService.pageBySlug(route.params.slug)
    page.value = cmsPublicService.extractData(res)
  } catch (err) {
    error.value = err.response?.data?.message || true
  } finally {
    loading.value = false
  }
}

onMounted(loadPage)
watch(() => route.params.slug, loadPage)
</script>

<style scoped>
.cms-body { max-width: 820px; }
.cms-content { line-height: 1.8; color: var(--pub-text-soft); font-size: 1.02rem; }
.cms-content :deep(h2) { color: var(--pub-text); font-size: 1.4rem; margin: 1.75rem 0 0.75rem; }
.cms-content :deep(h3) { color: var(--pub-text); font-size: 1.15rem; margin: 1.5rem 0 0.5rem; }
.cms-content :deep(p) { margin-bottom: 1rem; }
.cms-content :deep(img) { max-width: 100%; border-radius: var(--pub-radius); margin: 1rem 0; }
.cms-content :deep(a) { color: var(--pub-accent); }
.cms-content :deep(ul), .cms-content :deep(ol) { padding-left: 1.5rem; margin-bottom: 1rem; }
.cms-content :deep(table) { width: 100%; border-collapse: collapse; margin: 1rem 0; }
.cms-content :deep(th), .cms-content :deep(td) { border: 1px solid var(--pub-border); padding: 0.6rem; text-align: left; }
.cms-content :deep(th) { background: var(--pub-surface-2); color: var(--pub-text); }
</style>

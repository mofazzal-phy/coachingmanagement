<template>
  <div>
    <ContentState v-if="loading || error" :loading="loading" :error="error" :on-retry="loadStory" />

    <article v-else-if="story" class="bd">
      <div class="bd-hero">
        <div class="bd-hero-mesh"></div>
        <div class="pub-container bd-hero-inner">
          <router-link to="/site/success-stories" class="bd-back">← {{ t('nav.successStories') }}</router-link>
          <span v-if="story.achievement" class="pub-chip pub-chip--gold">🏆 {{ story.achievement }}</span>
          <h1>{{ story.title }}</h1>
          <p class="bd-meta" v-if="story.student_name">{{ story.student_name }}<template v-if="story.exam_name || story.achievement_year"> · {{ story.exam_name || story.achievement_year }}</template></p>
        </div>
      </div>

      <div class="pub-container bd-body">
        <img v-if="story.featured_image_url || story.image_url" :src="story.featured_image_url || story.image_url" :alt="story.title" class="bd-cover" />
        <div class="bd-content" v-html="story.story || story.description"></div>

        <div class="bd-foot">
          <router-link to="/site/success-stories" class="pub-btn pub-btn--ghost">← {{ t('nav.successStories') }}</router-link>
          <router-link to="/site/enroll" class="pub-btn pub-btn--primary">{{ t('nav.apply') }}</router-link>
        </div>
      </div>
    </article>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import cmsPublicService from '@/services/cms-public.service'
import { useLang } from '@/composables/useLang'
import ContentState from '@/components/public/ContentState.vue'

const { t } = useLang()
const route = useRoute()
const loading = ref(true)
const error = ref(false)
const story = ref(null)

const loadStory = async () => {
  loading.value = true
  error.value = false
  try {
    const res = await cmsPublicService.successStoryBySlug(route.params.slug)
    story.value = cmsPublicService.extractData(res)
  } catch (err) {
    error.value = err.response?.data?.message || true
  } finally {
    loading.value = false
  }
}

onMounted(loadStory)
watch(() => route.params.slug, loadStory)
</script>

<style scoped>
.bd-hero { position: relative; background: var(--pub-hero-grad); color: #fff; padding: clamp(2.5rem, 7vw, 4rem) 0; overflow: hidden; }
.bd-hero-mesh { position: absolute; inset: 0; background: radial-gradient(600px circle at 85% 10%, rgba(255,255,255,0.16), transparent 45%); }
.bd-hero-inner { position: relative; z-index: 1; max-width: 820px; }
.bd-back { color: #fff; text-decoration: none; font-weight: 700; font-size: 0.9rem; opacity: 0.9; display: inline-block; margin-bottom: 0.75rem; }
.bd-back:hover { opacity: 1; }
.bd-hero .pub-chip { margin-bottom: 0.5rem; }
.bd-hero h1 { font-size: clamp(1.6rem, 4vw, 2.5rem); font-weight: 800; margin: 0.4rem 0 0.5rem; line-height: 1.2; }
.bd-meta { opacity: 0.9; font-size: 0.95rem; margin: 0; }
.bd-body { max-width: 820px; padding-top: 2rem; padding-bottom: 3rem; }
.bd-cover { width: 100%; border-radius: var(--pub-radius-lg); margin-bottom: 1.75rem; max-height: 420px; object-fit: cover; }
.bd-content { line-height: 1.8; color: var(--pub-text-soft); font-size: 1.02rem; }
.bd-content :deep(p) { margin-bottom: 1rem; }
.bd-content :deep(img) { max-width: 100%; border-radius: var(--pub-radius); margin: 1rem 0; }
.bd-content :deep(a) { color: var(--pub-accent); }
.bd-foot { display: flex; gap: 0.8rem; flex-wrap: wrap; margin-top: 2.5rem; padding-top: 1.5rem; border-top: 1px solid var(--pub-border); }
</style>

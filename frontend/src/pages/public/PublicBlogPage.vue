<template>
  <div>
    <PublicHero :title="t('nav.blog')" :subtitle="isBn() ? 'আমাদের কোচিং থেকে খবর, আপডেট ও দরকারি লেখা' : 'News, updates and insights from our coaching center'" />

    <section class="pub-section">
      <div class="pub-container">
        <ContentState :loading="loading" :error="error" :empty="!loading && !error && !posts.length" :on-retry="loadPosts" />

        <div v-if="!loading && posts.length" class="blog-grid">
          <router-link
            v-for="post in posts"
            :key="post.id"
            :to="`/site/blog/${post.slug}`"
            class="blog-card pub-card"
            v-reveal
          >
            <div class="bc-media">
              <img v-if="post.featured_image_url" :src="post.featured_image_url" :alt="post.title" loading="lazy" />
              <div v-else class="bc-media-ph">✍️</div>
            </div>
            <div class="bc-body">
              <span class="bc-meta">{{ formatDate(post.published_at || post.created_at) }}</span>
              <h2>{{ post.title }}</h2>
              <p>{{ post.excerpt || truncate(post.content, 130) }}</p>
              <span class="bc-link">{{ t('action.readMore') }} →</span>
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
const loading = ref(true)
const error = ref(false)
const posts = ref([])

const loadPosts = async () => {
  loading.value = true
  error.value = false
  try {
    const res = await cmsPublicService.blog({ per_page: 24 })
    posts.value = cmsPublicService.extractList(res)
  } catch (err) {
    error.value = err.response?.data?.message || true
  } finally {
    loading.value = false
  }
}

const formatDate = (d) => (d ? new Date(d).toLocaleDateString(isBn() ? 'bn-BD' : 'en-US', { day: 'numeric', month: 'short', year: 'numeric' }) : '')
const truncate = (text, max) => {
  const value = String(text || '').replace(/<[^>]*>/g, '')
  return value.length > max ? `${value.slice(0, max - 1)}…` : value
}

onMounted(loadPosts)
</script>

<style scoped>
.blog-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
.blog-card { overflow: hidden; text-decoration: none; color: inherit; display: flex; flex-direction: column; }
.bc-media { aspect-ratio: 16 / 9; overflow: hidden; }
.bc-media img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s; }
.blog-card:hover .bc-media img { transform: scale(1.06); }
.bc-media-ph { width: 100%; height: 100%; display: grid; place-items: center; font-size: 2.2rem; background: var(--pub-accent-soft); }
.bc-body { padding: 1.1rem 1.2rem 1.3rem; display: flex; flex-direction: column; flex: 1; }
.bc-meta { font-size: 0.76rem; color: var(--pub-text-muted); font-weight: 600; }
.bc-body h2 { font-size: 1.1rem; font-weight: 800; margin: 0.4rem 0 0.5rem; color: var(--pub-text); line-height: 1.35; }
.bc-body p { font-size: 0.86rem; color: var(--pub-text-muted); line-height: 1.55; margin: 0 0 1rem; flex: 1; }
.bc-link { font-size: 0.84rem; font-weight: 700; color: var(--pub-accent); }
@media (max-width: 900px) { .blog-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 540px) { .blog-grid { grid-template-columns: 1fr; } }
</style>

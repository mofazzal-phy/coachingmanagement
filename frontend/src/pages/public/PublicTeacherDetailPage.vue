<template>
  <div>
    <ContentState v-if="loading || error" :loading="loading" :error="error" :on-retry="load" />
    <template v-else-if="teacher">
      <PublicHero :title="teacher.name" :subtitle="teacher.designation || teacher.subject || ''" />
      <section class="pub-section">
        <div class="pub-container td-grid">
          <aside class="td-side">
            <div class="td-card pub-card">
              <img v-if="photo" :src="photo" :alt="teacher.name" class="td-img" />
              <div v-else class="td-img td-img--ph">{{ (teacher.name || '?').slice(0, 1) }}</div>
              <h2>{{ teacher.name }}</h2>
              <p class="td-role">{{ teacher.designation || teacher.subject }}</p>
              <ul class="td-facts">
                <li v-if="teacher.qualification"><span>🎓</span> {{ teacher.qualification }}</li>
                <li v-if="teacher.experience"><span>🏅</span> {{ teacher.experience }}</li>
                <li v-if="teacher.subject"><span>📚</span> {{ teacher.subject }}</li>
              </ul>
            </div>
          </aside>
          <div class="td-main">
            <div class="td-block pub-card" v-if="teacher.bio || teacher.about">
              <h3>{{ isBn() ? 'পরিচিতি' : 'About' }}</h3>
              <div class="td-rich" v-html="teacher.bio || teacher.about"></div>
            </div>
            <div class="td-block pub-card" v-else>
              <h3>{{ isBn() ? 'পরিচিতি' : 'About' }}</h3>
              <p class="td-muted">{{ isBn() ? 'বিস্তারিত শীঘ্রই যুক্ত হবে।' : 'Details coming soon.' }}</p>
            </div>
            <router-link to="/site/teachers" class="pub-btn pub-btn--ghost">← {{ t('section.teachers') }}</router-link>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import cmsPublicService from '@/services/cms-public.service'
import { useLang } from '@/composables/useLang'
import PublicHero from '@/components/public/PublicHero.vue'
import ContentState from '@/components/public/ContentState.vue'

const { t, isBn } = useLang()
const route = useRoute()
const teacher = ref(null)
const loading = ref(true)
const error = ref(false)
const photo = computed(() => teacher.value?.photo_url || teacher.value?.avatar_url || teacher.value?.image_url)

const load = async () => {
  loading.value = true; error.value = false
  try { teacher.value = cmsPublicService.extractData(await cmsPublicService.teacher(route.params.id)) }
  catch { error.value = true } finally { loading.value = false }
}
watch(() => route.params.id, load)
onMounted(load)
</script>

<style scoped>
.td-grid { display: grid; grid-template-columns: 300px 1fr; gap: 1.75rem; align-items: start; }
.td-card { padding: 1.8rem; text-align: center; }
.td-img { width: 140px; height: 140px; border-radius: 50%; object-fit: cover; margin: 0 auto 1rem; }
.td-img--ph { display: grid; place-items: center; font-size: 3rem; background: var(--pub-hero-grad); color: #fff; font-weight: 800; }
.td-card h2 { font-size: 1.3rem; font-weight: 800; margin: 0 0 0.25rem; color: var(--pub-text); }
.td-role { color: var(--pub-accent); font-weight: 600; margin: 0 0 1rem; }
.td-facts { list-style: none; padding: 0; margin: 0; text-align: left; display: flex; flex-direction: column; gap: 0.6rem; }
.td-facts li { display: flex; gap: 0.6rem; font-size: 0.88rem; color: var(--pub-text-soft); }
.td-block { padding: 1.6rem; margin-bottom: 1.25rem; }
.td-block h3 { font-size: 1.15rem; font-weight: 800; margin: 0 0 0.75rem; color: var(--pub-text); }
.td-rich { color: var(--pub-text-soft); line-height: 1.7; }
.td-muted { color: var(--pub-text-muted); }
@media (max-width: 760px) { .td-grid { grid-template-columns: 1fr; } }
</style>

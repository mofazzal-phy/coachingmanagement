<template>
  <div>
    <ContentState v-if="loading || error" :loading="loading" :error="error" :on-retry="load" />

    <template v-else-if="course">
      <PublicHero :title="course.name" :subtitle="course.short_description">
        <div class="cd-hero-meta">
          <span v-if="course.has_online" class="pub-chip pub-chip--green">{{ t('course.online') }}</span>
          <span v-if="course.has_offline" class="pub-chip">{{ t('course.offline') }}</span>
          <span v-if="course.duration_label" class="cd-hero-pill">🕒 {{ course.duration_label }}</span>
          <span v-if="course.category" class="cd-hero-pill">🏷️ {{ course.category }}</span>
        </div>
      </PublicHero>

      <section class="pub-section pub-pat pat-course">
        <div class="pub-container cd-grid">
          <div class="cd-main">
            <div v-if="course.cover_image_url" class="cd-cover">
              <img :src="course.cover_image_url" :alt="course.name" />
            </div>

            <div v-if="course.description" class="cd-block pub-card">
              <h2>{{ isBn() ? 'কোর্স পরিচিতি' : 'About this course' }}</h2>
              <div class="cd-rich" v-html="course.description"></div>
            </div>

            <div v-if="outcomes.length" class="cd-block pub-card">
              <h2>{{ isBn() ? 'যা শিখবে' : "What you'll learn" }}</h2>
              <ul class="cd-outcomes">
                <li v-for="(o, i) in outcomes" :key="i">✓ {{ o }}</li>
              </ul>
            </div>

            <div v-if="course.subjects?.length" class="cd-block pub-card">
              <h2>{{ t('course.syllabus') }}</h2>
              <div class="cd-subjects">
                <span v-for="s in course.subjects" :key="s.id" class="pub-chip">{{ s.name }}</span>
              </div>
            </div>

            <div v-if="course.batches?.length" class="cd-block pub-card">
              <h2>{{ t('course.openBatches') }}</h2>
              <div class="cd-batches">
                <div v-for="b in course.batches" :key="b.id" class="cd-batch">
                  <div>
                    <strong>{{ b.name }}</strong>
                    <small>{{ batchSchedule(b) }}</small>
                  </div>
                  <div class="cd-batch-side">
                    <span class="pub-chip" :class="b.mode === 'online' ? 'pub-chip--green' : ''">{{ b.mode }}</span>
                    <span class="cd-seats">{{ b.available_seats ?? '—' }} {{ t('course.seatsLeft') }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <aside class="cd-aside">
            <div class="cd-buy pub-card">
              <div class="cd-fee-row">
                <span>{{ t('course.fee') }}</span>
                <strong v-if="fee">৳{{ fee }}</strong>
                <strong v-else class="cd-contact">{{ isBn() ? 'যোগাযোগ করুন' : 'Contact' }}</strong>
              </div>
              <router-link :to="`/site/enroll?course=${course.id}`" class="pub-btn pub-btn--primary cd-enroll">{{ t('action.enroll') }}</router-link>
              <a :href="telLink()" class="pub-btn pub-btn--ghost cd-call">📞 {{ t('action.callNow') }}</a>
              <a :href="wa" target="_blank" rel="noopener" class="pub-btn pub-btn--accent cd-call">💬 {{ t('action.whatsapp') }}</a>
            </div>
          </aside>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import enrollmentService from '@/services/enrollment.service'
import cmsPublicService from '@/services/cms-public.service'
import { useLang } from '@/composables/useLang'
import { telLink, whatsappLink } from '@/config/siteConfig'
import PublicHero from '@/components/public/PublicHero.vue'
import ContentState from '@/components/public/ContentState.vue'

const { t, isBn } = useLang()
const route = useRoute()

const course = ref(null)
const loading = ref(true)
const error = ref(false)

const wa = computed(() => whatsappLink(course.value ? `${course.value.name} সম্পর্কে জানতে চাই` : ''))
const fee = computed(() => {
  const f = Number(course.value?.one_time_fee || 0)
  return f > 0 ? f.toLocaleString('en-US') : null
})
const outcomes = computed(() => {
  const lo = course.value?.learning_outcomes
  if (Array.isArray(lo)) return lo
  if (typeof lo === 'string' && lo.trim()) return lo.split('\n').filter(Boolean)
  return []
})

const batchSchedule = (b) => {
  const days = Array.isArray(b.days) ? b.days.join(', ') : (b.days || '')
  const time = b.start_time ? `${b.start_time}${b.end_time ? '–' + b.end_time : ''}` : ''
  return [days, time].filter(Boolean).join(' · ') || '—'
}

const load = async () => {
  loading.value = true
  error.value = false
  try {
    const res = await enrollmentService.getPublicCourseDetails(route.params.id)
    course.value = cmsPublicService.extractData(res)
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

watch(() => route.params.id, load)
onMounted(load)
</script>

<style scoped>
.cd-hero-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
.cd-hero-pill { background: rgba(255,255,255,0.18); color: #fff; padding: 0.25rem 0.7rem; border-radius: var(--pub-radius-pill); font-size: 0.78rem; font-weight: 700; }
.cd-grid { display: grid; grid-template-columns: 1fr 320px; gap: 1.75rem; align-items: start; }
.cd-cover { border-radius: var(--pub-radius-lg); overflow: hidden; margin-bottom: 1.5rem; }
.cd-cover img { width: 100%; display: block; }
.cd-block { padding: 1.5rem; margin-bottom: 1.25rem; }
.cd-block h2 { font-size: 1.2rem; font-weight: 800; margin: 0 0 1rem; color: var(--pub-text); }
.cd-rich { color: var(--pub-text-soft); line-height: 1.7; }
.cd-rich :deep(p) { margin-bottom: 0.75rem; }
.cd-outcomes { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; }
.cd-outcomes li { color: var(--pub-text-soft); font-size: 0.9rem; }
.cd-subjects { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.cd-batches { display: flex; flex-direction: column; gap: 0.75rem; }
.cd-batch { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.9rem 1rem; border: 1px solid var(--pub-border); border-radius: var(--pub-radius); }
.cd-batch strong { display: block; color: var(--pub-text); }
.cd-batch small { color: var(--pub-text-muted); font-size: 0.78rem; }
.cd-batch-side { display: flex; flex-direction: column; align-items: flex-end; gap: 0.35rem; }
.cd-seats { font-size: 0.74rem; color: var(--pub-accent-2); font-weight: 700; }
.cd-aside { position: sticky; top: calc(var(--pub-header-h) + 1rem); }
.cd-buy { padding: 1.4rem; display: flex; flex-direction: column; gap: 0.7rem; }
.cd-fee-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; }
.cd-fee-row span { color: var(--pub-text-muted); font-weight: 600; }
.cd-fee-row strong { font-size: 1.6rem; font-weight: 800; color: var(--pub-text); }
.cd-contact { font-size: 1rem !important; }
.cd-enroll, .cd-call { width: 100%; }
@media (max-width: 860px) {
  .cd-grid { grid-template-columns: 1fr; }
  .cd-aside { position: static; }
  .cd-outcomes { grid-template-columns: 1fr; }
}
</style>

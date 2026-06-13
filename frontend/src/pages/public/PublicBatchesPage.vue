<template>
  <div>
    <PublicHero :title="t('nav.batches')" :subtitle="isBn() ? 'চলমান ও আসন্ন ব্যাচসমূহ দেখে নাও' : 'Browse running and upcoming batches'" />

    <section class="pub-section pub-pat pat-course">
      <div class="pub-container">
        <ContentState :loading="loading" :error="error" :empty="!loading && !error && !rows.length" :on-retry="load" />

        <div v-if="!loading && rows.length" class="batch-grid">
          <div v-for="b in rows" :key="b.id" class="batch-card pub-card">
            <div class="batch-top">
              <span class="pub-chip" :class="b.mode === 'online' ? 'pub-chip--green' : ''">{{ b.mode || '—' }}</span>
              <span v-if="b.status" class="batch-status" :class="b.status">{{ b.status }}</span>
            </div>
            <h3>{{ b.name }}</h3>
            <p class="batch-course">{{ b.courseName }}</p>
            <ul class="batch-meta">
              <li><span>📅</span> {{ scheduleText(b) }}</li>
              <li v-if="b.start_date"><span>🚀</span> {{ formatDate(b.start_date) }}</li>
              <li v-if="b.teacher"><span>👩‍🏫</span> {{ b.teacher.name }}</li>
              <li><span>🎟️</span> {{ b.available_seats ?? b.availableSeats ?? '—' }} {{ t('course.seatsLeft') }}</li>
            </ul>
            <router-link :to="`/site/courses/${b.course_id}`" class="pub-btn pub-btn--ghost batch-btn">{{ t('action.viewDetails') }}</router-link>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import enrollmentService from '@/services/enrollment.service'
import cmsPublicService from '@/services/cms-public.service'
import { useLang } from '@/composables/useLang'
import PublicHero from '@/components/public/PublicHero.vue'
import ContentState from '@/components/public/ContentState.vue'

const { t, isBn } = useLang()

const rows = ref([])
const loading = ref(true)
const error = ref(false)

const scheduleText = (b) => {
  const days = Array.isArray(b.days) ? b.days.join(', ') : (b.days || '')
  const time = b.start_time ? `${b.start_time}${b.end_time ? '–' + b.end_time : ''}` : ''
  return [days, time].filter(Boolean).join(' · ') || '—'
}
const formatDate = (d) => (d ? new Date(d).toLocaleDateString(isBn() ? 'bn-BD' : 'en-US', { day: 'numeric', month: 'short', year: 'numeric' }) : '')

const load = async () => {
  loading.value = true
  error.value = false
  try {
    const res = await enrollmentService.getPublicCourses()
    const courses = cmsPublicService.extractList(res)
    const results = await Promise.allSettled(
      courses.map((c) => enrollmentService.getPublicBatches(c.id)),
    )
    const all = []
    results.forEach((r, i) => {
      if (r.status === 'fulfilled') {
        const batches = cmsPublicService.extractList(r.value)
        batches.forEach((b) => all.push({ ...b, courseName: courses[i].name }))
      }
    })
    rows.value = all
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.batch-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
.batch-card { padding: 1.4rem; display: flex; flex-direction: column; }
.batch-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
.batch-status { font-size: 0.72rem; font-weight: 700; text-transform: capitalize; padding: 0.2rem 0.6rem; border-radius: var(--pub-radius-pill); background: var(--pub-accent-2-soft); color: var(--pub-accent-2); }
.batch-status.upcoming { background: color-mix(in srgb, var(--pub-gold) 18%, transparent); color: var(--pub-gold); }
.batch-card h3 { font-size: 1.1rem; font-weight: 800; margin: 0 0 0.2rem; color: var(--pub-text); }
.batch-course { font-size: 0.84rem; color: var(--pub-accent); font-weight: 600; margin: 0 0 1rem; }
.batch-meta { list-style: none; padding: 0; margin: 0 0 1.1rem; display: flex; flex-direction: column; gap: 0.5rem; }
.batch-meta li { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--pub-text-soft); }
.batch-btn { margin-top: auto; }
@media (max-width: 980px) { .batch-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .batch-grid { grid-template-columns: 1fr; } }
</style>

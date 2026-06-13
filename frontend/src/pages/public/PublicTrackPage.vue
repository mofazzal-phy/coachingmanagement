<template>
  <div>
    <PublicHero :title="t('track.title')" :subtitle="isBn() ? 'এনরোলমেন্ট নম্বর দিয়ে আবেদনের সর্বশেষ অবস্থা জানুন' : 'Check your application status using your enrollment number'" />

    <section class="pub-section">
      <div class="pub-container tk-wrap">
        <form class="tk-form pub-card" @submit.prevent="track">
          <label>{{ t('track.placeholder') }}</label>
          <div class="tk-input-row">
            <input v-model.trim="enrollNo" type="text" :placeholder="t('track.placeholder')" required />
            <button type="submit" class="pub-btn pub-btn--primary" :disabled="loading">
              {{ loading ? t('state.loading') : t('track.check') }}
            </button>
          </div>
          <p v-if="error" class="tk-error">{{ error }}</p>
        </form>

        <div v-if="result" class="tk-result pub-card" v-reveal>
          <div class="tk-result-head">
            <div>
              <small>{{ isBn() ? 'এনরোলমেন্ট নম্বর' : 'Enrollment No.' }}</small>
              <strong>{{ result.enrollment_no }}</strong>
            </div>
            <span class="tk-status" :class="statusClass">{{ prettyStatus }}</span>
          </div>
          <div class="tk-rows">
            <div class="tk-row"><span>{{ isBn() ? 'শিক্ষার্থী' : 'Student' }}</span><strong>{{ studentName }}</strong></div>
            <div class="tk-row" v-if="result.batch"><span>{{ isBn() ? 'ব্যাচ' : 'Batch' }}</span><strong>{{ result.batch.name }}</strong></div>
            <div class="tk-row" v-if="result.batch?.course"><span>{{ t('nav.courses') }}</span><strong>{{ result.batch.course.name }}</strong></div>
            <div class="tk-row" v-if="result.subjects?.length"><span>{{ t('course.syllabus') }}</span><strong>{{ result.subjects.map(s => s.name).join(', ') }}</strong></div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import enrollmentService from '@/services/enrollment.service'
import cmsPublicService from '@/services/cms-public.service'
import { useLang } from '@/composables/useLang'
import PublicHero from '@/components/public/PublicHero.vue'

const { t, isBn } = useLang()
const enrollNo = ref('')
const result = ref(null)
const loading = ref(false)
const error = ref('')

const studentName = computed(() => {
  const s = result.value?.student
  if (!s) return '—'
  return [s.first_name, s.last_name].filter(Boolean).join(' ')
})
const prettyStatus = computed(() => (result.value?.status || '').replace(/_/g, ' '))
const statusClass = computed(() => {
  const s = result.value?.status || ''
  if (['approved', 'active', 'enrolled', 'paid'].includes(s)) return 'ok'
  if (['rejected', 'cancelled'].includes(s)) return 'bad'
  return 'pending'
})

const track = async () => {
  if (!enrollNo.value) return
  loading.value = true; error.value = ''; result.value = null
  try {
    const res = await enrollmentService.trackEnrollment(enrollNo.value)
    result.value = cmsPublicService.extractData(res)
    if (!result.value) error.value = isBn() ? 'কোনো আবেদন পাওয়া যায়নি।' : 'No application found.'
  } catch {
    error.value = isBn() ? 'এই নম্বরে কোনো আবেদন পাওয়া যায়নি।' : 'No application found with this number.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.tk-wrap { max-width: 620px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem; }
.tk-form { padding: 1.75rem; }
.tk-form label { display: block; font-weight: 700; margin-bottom: 0.6rem; color: var(--pub-text); }
.tk-input-row { display: flex; gap: 0.75rem; }
.tk-input-row input {
  flex: 1; padding: 0.8rem 1rem; border-radius: var(--pub-radius-sm);
  border: 1.5px solid var(--pub-border); background: var(--pub-surface-2); color: var(--pub-text); font-size: 0.95rem;
}
.tk-input-row input:focus { outline: none; border-color: var(--pub-accent); }
.tk-error { color: var(--pub-accent); margin: 0.75rem 0 0; font-size: 0.88rem; }
.tk-result { padding: 1.75rem; }
.tk-result-head { display: flex; align-items: center; justify-content: space-between; padding-bottom: 1rem; border-bottom: 1px solid var(--pub-border); margin-bottom: 1rem; }
.tk-result-head small { display: block; color: var(--pub-text-muted); font-size: 0.78rem; }
.tk-result-head strong { font-size: 1.2rem; color: var(--pub-text); }
.tk-status { padding: 0.35rem 0.9rem; border-radius: var(--pub-radius-pill); font-weight: 800; font-size: 0.8rem; text-transform: capitalize; }
.tk-status.ok { background: var(--pub-accent-2-soft); color: var(--pub-accent-2); }
.tk-status.bad { background: color-mix(in srgb, #ef4444 16%, transparent); color: #ef4444; }
.tk-status.pending { background: color-mix(in srgb, var(--pub-gold) 18%, transparent); color: var(--pub-gold); }
.tk-rows { display: flex; flex-direction: column; gap: 0.7rem; }
.tk-row { display: flex; justify-content: space-between; gap: 1rem; }
.tk-row span { color: var(--pub-text-muted); }
.tk-row strong { color: var(--pub-text); text-align: right; }
@media (max-width: 480px) { .tk-input-row { flex-direction: column; } }
</style>

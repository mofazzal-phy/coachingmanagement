<template>
  <div class="live-exam-page" :class="{ fullscreen: inExam }">
    <div v-if="loading" class="loading"><ProgressSpinner /><p>Loading exam...</p></div>
    <div v-else-if="error" class="error"><Message severity="error" :closable="false">{{ error }}</Message>
      <Button label="Back" class="p-button-text" @click="goBack" />
    </div>

    <template v-else-if="!inExam">
      <div class="pre-exam-hero">
        <div class="pre-exam-badge">
          <i class="pi pi-desktop"></i>
          Live Online Exam
        </div>
        <h1 class="pre-exam-title">{{ routineMeta.exam_name }}</h1>
        <p class="pre-exam-subject">{{ routineMeta.subject_name }}</p>

        <div class="pre-exam-stats">
          <div class="stat-tile">
            <span class="stat-label">Date</span>
            <span class="stat-value">{{ formatExamDate(routineMeta.exam_date) }}</span>
          </div>
          <div class="stat-tile">
            <span class="stat-label">Duration</span>
            <span class="stat-value">{{ durationMinutes }} min</span>
          </div>
          <div class="stat-tile">
            <span class="stat-label">Questions</span>
            <span class="stat-value">{{ questions.length }}</span>
          </div>
          <div class="stat-tile">
            <span class="stat-label">Total Marks</span>
            <span class="stat-value">{{ routineMeta.total_marks || '—' }}</span>
          </div>
        </div>

        <p v-if="windowEndsAt" class="window-alert">
          <i class="pi pi-clock"></i>
          Exam window ends {{ formatDateTime(windowEndsAt) }}
        </p>

        <div class="pre-exam-cta">
          <button
            type="button"
            class="start-exam-btn"
            :disabled="questions.length === 0"
            @click="beginExam"
          >
            <i class="pi pi-play-circle"></i>
            <span class="start-exam-text">
              <strong>Start Exam</strong>
              <small>{{ questions.length }} questions · {{ durationMinutes }} minutes</small>
            </span>
          </button>
          <button type="button" class="cancel-exam-btn" @click="goBack">
            Cancel and go back
          </button>
        </div>
      </div>
    </template>

    <template v-else-if="!showResults">
      <div class="exam-toolbar">
        <div class="toolbar-left">
          <strong>{{ routineMeta.exam_name }}</strong>
          <span class="subject">{{ routineMeta.subject_name }}</span>
        </div>
        <div class="toolbar-center">
          <span class="timer" :class="{ urgent: remainingSeconds < 300 }">⏱ {{ timerDisplay }}</span>
        </div>
        <div class="toolbar-right">
          <span class="answered-count">{{ answeredCount }}/{{ questions.length }} answered</span>
        </div>
      </div>

      <div class="scroll-progress">
        <div class="scroll-progress-fill" :style="{ width: scrollProgress + '%' }"></div>
      </div>

      <div ref="scrollContainer" class="questions-scroll" @scroll="onScroll">
        <article
          v-for="(q, i) in questions"
          :key="q.id"
          :id="'question-' + i"
          class="question-card"
          :class="{ answered: isAnswered(q.id), active: currentIndex === i }"
        >
          <div class="question-card-header">
            <span class="q-badge">Question {{ i + 1 }} of {{ questions.length }}</span>
            <span v-if="isAnswered(q.id)" class="answered-tag">Answered</span>
          </div>

          <div class="question-block">
            <div class="q-title"><strong>Q{{ i + 1 }}.</strong> [{{ q.marks }} marks]</div>
            <div v-if="q.stimulus" class="stimulus">{{ q.stimulus }}</div>
            <div class="q-content">{{ q.content }}</div>

            <div v-if="q.question_type === 'mcq'" class="mcq-options">
              <label v-for="(opt, oi) in (q.options || [])" :key="oi" class="option-label">
                <input type="radio" :name="'q-' + q.id" :value="oi" v-model="responses[q.id]" />
                <span class="opt-key">{{ optionLabels[oi] || String.fromCharCode(97 + oi) }})</span>
                {{ opt }}
              </label>
            </div>

            <div v-else-if="q.question_type === 'cq'" class="cq-parts">
              <div v-for="(part, pi) in (q.parts || [])" :key="pi" class="part-row">
                <label>{{ part.label || String.fromCharCode(97 + pi) }} {{ part.content }} ({{ part.marks }}m)</label>
                <textarea v-model="responses[q.id + '_part_' + pi]" rows="3" class="answer-area" placeholder="Your answer..." />
              </div>
            </div>

            <textarea v-else v-model="responses[q.id]" rows="4" class="answer-area" placeholder="Your answer..." />
          </div>
        </article>
      </div>

      <div class="exam-footer">
        <span v-if="autoSaveHint" class="autosave-hint">{{ autoSaveHint }}</span>
        <div class="footer-actions">
          <Button label="Save Progress" icon="pi pi-save" class="p-button-outlined save-btn" :loading="saving" @click="saveProgress(false)" />
          <Button label="Submit Exam" icon="pi pi-check" class="submit-exam-btn" :loading="saving" @click="submitExam" />
        </div>
      </div>
    </template>

    <template v-else>
      <div class="results-card">
        <h2>Exam Submitted</h2>
        <p class="submitted-msg">Your answers have been recorded. MCQ sections are auto-scored; CQ/written parts will be reviewed by teachers.</p>
        <div v-if="evaluation" class="score-display">
          <span class="score">{{ evaluation.score }}</span>
          <span class="total">/ {{ evaluation.total_marks }}</span>
          <span class="pct">({{ evaluation.percentage }}% MCQ)</span>
        </div>
        <Button label="Back to Exams" @click="goBack" />
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import examService from '@/services/exam.service'
import { extractData } from '@/utils/api.utils'

const route = useRoute()
const router = useRouter()
const optionLabels = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ']

const loading = ref(true)
const saving = ref(false)
const error = ref(null)
const inExam = ref(false)
const showResults = ref(false)

const activeAttempt = ref(null)
const questions = ref([])
const responses = ref({})
const routineMeta = ref({})
const durationMinutes = ref(60)
const windowEndsAt = ref(null)
const evaluation = ref(null)

const currentIndex = ref(0)
const scrollProgress = ref(0)
const scrollContainer = ref(null)
const remainingSeconds = ref(0)
const autoSaveHint = ref('')
let timerHandle = null
let autoSaveHandle = null

const timerDisplay = computed(() => {
  const s = Math.max(0, remainingSeconds.value)
  const h = Math.floor(s / 3600)
  const m = Math.floor((s % 3600) / 60)
  const sec = s % 60
  if (h > 0) return `${h}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`
  return `${m}:${String(sec).padStart(2,'0')}`
})

const answeredCount = computed(() => {
  return questions.value.filter(q => isAnswered(q.id)).length
})

function isAnswered(qid) {
  if (responses.value[qid] !== undefined && responses.value[qid] !== null && responses.value[qid] !== '') return true
  const q = questions.value.find(x => x.id === qid)
  if (q?.question_type === 'cq' && q.parts?.length) {
    return q.parts.some((_, pi) => {
      const v = responses.value[`${qid}_part_${pi}`]
      return v !== undefined && v !== null && v !== ''
    })
  }
  return false
}

function onScroll() {
  const el = scrollContainer.value
  if (!el || !questions.value.length) return

  const cards = el.querySelectorAll('.question-card')
  const mid = el.scrollTop + el.clientHeight * 0.35
  cards.forEach((card, i) => {
    if (card.offsetTop <= mid && card.offsetTop + card.offsetHeight > mid) {
      currentIndex.value = i
    }
  })

  const maxScroll = el.scrollHeight - el.clientHeight
  scrollProgress.value = maxScroll > 0 ? Math.min(100, (el.scrollTop / maxScroll) * 100) : 100
}

function formatDateTime(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString()
}

function formatExamDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr.includes('T') ? dateStr : dateStr + 'T00:00:00')
  if (Number.isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
}

function buildAnswersPayload() {
  const answers = {}
  for (const [key, val] of Object.entries(responses.value)) {
    if (val === undefined || val === null || val === '') continue
    if (key.includes('_part_')) continue
    answers[key] = val
  }
  for (const q of questions.value) {
    if (q.question_type === 'cq' && q.parts?.length) {
      answers[q.id] = q.parts.map((_, pi) => responses.value[`${q.id}_part_${pi}`] || '')
    }
  }
  return answers
}

function hydrateSavedAnswers(saved) {
  if (!saved) return
  for (const [qid, val] of Object.entries(saved)) {
    if (Array.isArray(val)) {
      val.forEach((v, pi) => { responses.value[`${qid}_part_${pi}`] = v })
    } else {
      responses.value[qid] = val
    }
  }
}

function startTimer() {
  clearInterval(timerHandle)
  timerHandle = setInterval(() => {
    if (remainingSeconds.value > 0) {
      remainingSeconds.value--
    } else {
      submitExam(true)
    }
  }, 1000)
}

function startAutoSave() {
  clearInterval(autoSaveHandle)
  autoSaveHandle = setInterval(() => saveProgress(false, true), 30000)
}

async function loadOrStart() {
  loading.value = true
  error.value = null
  try {
    const routineId = route.params.routineId
    const res = await examService.attempts.start(routineId)
    const data = extractData(res, {})
    activeAttempt.value = data.attempt
    questions.value = data.questions || []
    durationMinutes.value = data.duration_minutes || 60
    windowEndsAt.value = data.window_ends_at || null
    routineMeta.value = {
      exam_name: data.attempt?.routine?.exam_name || 'Exam',
      subject_name: data.attempt?.routine?.subject_name || '',
      exam_date: data.attempt?.routine?.exam_date,
      total_marks: data.attempt?.routine?.total_marks,
    }
    hydrateSavedAnswers(data.saved_answers)

    if (data.resumed) {
      inExam.value = true
      beginExamTimers(data.remaining_seconds)
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Could not load exam'
  } finally {
    loading.value = false
  }
}

function beginExamTimers(serverRemaining = null) {
  const startedAt = activeAttempt.value?.started_at
  const durationSec = (durationMinutes.value || 60) * 60
  let remaining = serverRemaining != null ? Math.max(0, Math.floor(serverRemaining)) : durationSec

  if (startedAt && serverRemaining == null) {
    const elapsed = Math.floor((Date.now() - new Date(startedAt).getTime()) / 1000)
    remaining = Math.max(0, durationSec - elapsed)
  }

  if (windowEndsAt.value) {
    const diff = Math.floor((new Date(windowEndsAt.value) - Date.now()) / 1000)
    if (diff > 0) remaining = Math.min(remaining, diff)
  }

  remainingSeconds.value = remaining
  startTimer()
  startAutoSave()
}

function beginExam() {
  if (!questions.value.length) return
  inExam.value = true
  beginExamTimers()
}

async function saveProgress(submit = false, silent = false) {
  if (!activeAttempt.value) return
  saving.value = true
  try {
    const res = await examService.attempts.save(activeAttempt.value.id, {
      answers: buildAnswersPayload(),
      submit,
    })
    const data = extractData(res, {})
    if (!silent) autoSaveHint.value = 'Saved ' + new Date().toLocaleTimeString()
    if (submit) {
      evaluation.value = data.evaluation
      showResults.value = true
      inExam.value = false
      clearInterval(timerHandle)
      clearInterval(autoSaveHandle)
    }
  } catch (err) {
    if (!silent) alert(err.response?.data?.message || 'Save failed')
  } finally {
    saving.value = false
  }
}

function submitExam(auto = false) {
  if (!auto && !confirm('Submit your exam? You cannot change answers after submission.')) return
  saveProgress(true)
}

function goBack() {
  router.push({ name: 'StudentExams', query: { tab: 'live' } })
}

onMounted(loadOrStart)
onUnmounted(() => {
  clearInterval(timerHandle)
  clearInterval(autoSaveHandle)
})
</script>

<style scoped>
.live-exam-page { max-width: 900px; margin: 0 auto; padding: 1rem 1rem 5rem; }
.live-exam-page.fullscreen { max-width: none; }
.loading, .error { text-align: center; padding: 3rem; }
.pre-exam-hero {
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 1.75rem 1.5rem 1.5rem;
  box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
  text-align: center;
}
.pre-exam-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #4338ca;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  padding: 0.35rem 0.75rem;
  border-radius: 999px;
  margin-bottom: 0.85rem;
}
.pre-exam-title {
  margin: 0 0 0.25rem;
  font-size: 1.45rem;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.25;
}
.pre-exam-subject {
  margin: 0 0 1.25rem;
  font-size: 1rem;
  font-weight: 600;
  color: #4f46e5;
}
.pre-exam-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.65rem;
  margin-bottom: 1rem;
  text-align: left;
}
.stat-tile {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 0.65rem 0.75rem;
}
.stat-label {
  display: block;
  font-size: 0.68rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-muted);
  margin-bottom: 0.2rem;
}
.stat-value {
  display: block;
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text-dark);
}
.window-alert {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0 0 1.25rem;
  padding: 0.5rem 0.85rem;
  border-radius: 8px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #b45309;
  font-size: 0.85rem;
  font-weight: 600;
}
.pre-exam-cta {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  max-width: 420px;
  margin: 0 auto;
}
.start-exam-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  width: 100%;
  padding: 1rem 1.25rem;
  border: none;
  border-radius: 12px;
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  color: #fff;
  cursor: pointer;
  box-shadow: 0 8px 24px rgba(5, 150, 105, 0.35);
  transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
}
.start-exam-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 12px 28px rgba(5, 150, 105, 0.42);
  filter: brightness(1.03);
}
.start-exam-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
  box-shadow: none;
}
.start-exam-btn .pi { font-size: 1.75rem; }
.start-exam-text {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  text-align: left;
  line-height: 1.2;
}
.start-exam-text strong { font-size: 1.1rem; font-weight: 800; }
.start-exam-text small { font-size: 0.78rem; opacity: 0.92; font-weight: 500; }
.cancel-exam-btn {
  background: transparent;
  border: none;
  color: var(--text-muted);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  padding: 0.35rem;
  text-decoration: underline;
  text-underline-offset: 3px;
}
.cancel-exam-btn:hover { color: var(--text-secondary); }
@media (max-width: 640px) {
  .pre-exam-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
.exam-toolbar {
  display: flex; justify-content: space-between; align-items: center; gap: 1rem;
  background: #1e3a5f; color: #fff; padding: 0.75rem 1rem; border-radius: 10px; margin-bottom: 0.5rem; flex-wrap: wrap;
  position: sticky; top: 0; z-index: 20;
}
.toolbar-left .subject { display: block; font-size: 0.8rem; opacity: 0.85; }
.timer { font-size: 1.25rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.timer.urgent { color: #fca5a5; }
.scroll-progress { height: 4px; background: #e5e7eb; border-radius: 4px; margin-bottom: 0.75rem; overflow: hidden; }
.scroll-progress-fill { height: 100%; background: linear-gradient(90deg, #4f46e5, #059669); transition: width 0.2s; }
.questions-scroll {
  max-height: calc(100vh - 220px);
  overflow-y: auto;
  scroll-behavior: smooth;
  padding-right: 0.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.question-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1rem 1.1rem;
  border: 2px solid #e5e7eb;
  scroll-margin-top: 80px;
}
.question-card.answered { border-color: #86efac; background: #f0fdf4; }
.question-card.active { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12); }
.question-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
.q-badge { font-size: 0.8rem; font-weight: 600; color: #4f46e5; }
.answered-tag { font-size: 0.72rem; font-weight: 700; color: #047857; background: #d1fae5; padding: 0.15rem 0.5rem; border-radius: 999px; }
.question-block .stimulus { background: var(--bg-accent); padding: 0.75rem; border-left: 3px solid #4f46e5; margin-bottom: 0.5rem; }
.q-content { margin-bottom: 0.75rem; }
.mcq-options { display: flex; flex-direction: column; gap: 0.4rem; }
.option-label { display: flex; gap: 0.5rem; align-items: flex-start; cursor: pointer; }
.opt-key { font-weight: 600; min-width: 1.5rem; }
.answer-area { width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 8px; box-sizing: border-box; }
.part-row { margin-bottom: 0.75rem; }
.exam-footer {
  position: fixed;
  left: 0; right: 0; bottom: 0;
  background: var(--bg-card);
  border-top: 2px solid #d1d5db;
  padding: 0.85rem 1.25rem;
  z-index: 30;
  box-shadow: 0 -6px 20px rgba(0,0,0,0.1);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}
.footer-actions { display: flex; gap: 0.65rem; margin-left: auto; }
.autosave-hint { font-size: 0.8rem; color: var(--text-muted); }
.save-btn { border-color: var(--text-muted) !important; color: var(--text-secondary) !important; }
:deep(.submit-exam-btn) {
  background: #059669 !important;
  border: 2px solid #047857 !important;
  color: #fff !important;
  font-weight: 700 !important;
  padding: 0.65rem 1.25rem !important;
  box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35) !important;
}
:deep(.submit-exam-btn:hover) { background: #047857 !important; }
.results-card { background: var(--bg-card); border-radius: 12px; padding: 2rem; text-align: center; }
.score { font-size: 2.5rem; font-weight: 700; color: #4f46e5; }
.total { font-size: 1.25rem; color: var(--text-muted); }
.pct { display: block; color: #059669; margin: 0.5rem 0 1rem; }
</style>

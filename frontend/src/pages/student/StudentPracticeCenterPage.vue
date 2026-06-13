<template>
  <div class="practice-page">
    <div class="page-header">
      <h1>🎯 Practice Center</h1>
      <p class="subtitle">Unlimited practice attempts — scores do not affect official results</p>
    </div>

    <!-- List view -->
    <template v-if="!activeAttempt">
      <div v-if="loading" class="loading"><ProgressSpinner /></div>
      <div v-else-if="error" class="error"><Message severity="error" :closable="false">{{ error }}</Message></div>

      <template v-else>
        <div v-if="routines.length === 0" class="empty">
          <i class="pi pi-inbox empty-icon"></i>
          <h3>No Practice Sets Available</h3>
          <p>Your institute has not published any practice exams for your batch yet.</p>
          <ul class="empty-hints">
            <li>Admin must create a <strong>Practice Exam</strong> in Exam Setup Wizard</li>
            <li>Attach <strong>approved questions</strong> to each routine</li>
            <li>Publish the exam and routines</li>
            <li>Your enrollment must be active for the matching batch</li>
          </ul>
        </div>

        <div v-else class="routine-grid">
          <div v-for="r in routines" :key="r.id" class="routine-card">
            <!-- Card Header -->
            <div class="card-header">
              <div class="exam-info">
                <h3 class="exam-name">{{ r.exam_name }}</h3>
                <span class="subject-badge">{{ r.subject_name }}</span>
              </div>
              <Tag value="Practice" severity="info" class="practice-tag" />
            </div>

            <!-- Stats Row -->
            <div class="stats-row">
              <div class="stat-item">
                <i class="pi pi-question-circle"></i>
                <span>{{ r.question_count }} Questions</span>
              </div>
              <div class="stat-item" v-if="r.duration_minutes">
                <i class="pi pi-clock"></i>
                <span>{{ r.duration_minutes }} min</span>
              </div>
              <div class="stat-item" v-if="r.best_score != null">
                <i class="pi pi-star-fill" style="color: #f59e0b"></i>
                <span>Best: {{ r.best_score }}/{{ r.best_total }}</span>
              </div>
            </div>

            <!-- Warning for no questions -->
            <div v-if="!r.can_start" class="warning-box">
              <i class="pi pi-exclamation-triangle"></i>
              <span>No questions attached — contact admin to add approved questions</span>
            </div>

            <!-- Action Buttons -->
            <div class="card-actions">
              <Button
                v-if="r.in_progress_attempt_id"
                class="action-btn resume-btn"
                @click="resumePractice(r)"
              >
                <i class="pi pi-play-circle"></i>
                <span>Resume Practice</span>
              </Button>
              <Button
                v-else
                class="action-btn start-btn"
                :disabled="!r.can_start"
                @click="startPractice(r)"
              >
                <i class="pi pi-play"></i>
                <span>Start Practice</span>
              </Button>
            </div>
          </div>
        </div>

        <!-- History Section -->
        <div v-if="history.length" class="history-section">
          <h2>📊 Recent Practice History</h2>
          <div class="history-list">
            <div v-for="a in history" :key="a.id" class="history-card">
              <div class="history-main">
                <div class="history-exam-info">
                  <strong>{{ a.routine?.exam_name }}</strong>
                  <span class="history-subject">{{ a.routine?.subject_name }}</span>
                </div>
                <div class="history-score">
                  <span class="score-value">{{ a.score }}/{{ a.total_marks }}</span>
                  <Tag 
                    :value="a.status === 'submitted' ? 'Completed' : 'In Progress'" 
                    :severity="a.status === 'submitted' ? 'success' : 'warning'" 
                  />
                </div>
              </div>
              <Button 
                v-if="a.status === 'submitted'" 
                class="review-btn"
                @click="reviewAttempt(a.id)"
              >
                <i class="pi pi-eye"></i>
                <span>Review Answers</span>
              </Button>
            </div>
          </div>
        </div>
      </template>
    </template>

    <!-- Pre-exam briefing -->
    <template v-else-if="!showResults && !inExam">
      <div class="pre-exam-card">
        <h2>{{ attemptMeta.exam_name }}</h2>
        <p class="pre-subject">{{ attemptMeta.subject_name }}</p>
        <div class="pre-meta">
          <div><span class="lbl">Questions</span><strong>{{ questions.length }}</strong></div>
          <div><span class="lbl">Duration</span><strong>{{ durationMinutes }} min</strong></div>
          <div><span class="lbl">Mode</span><strong>Timed practice</strong></div>
        </div>
        <ul class="pre-rules">
          <li>Timer starts when you begin — auto-submits when time ends</li>
          <li>Answers auto-save every 30 seconds</li>
          <li>Unlimited retries — scores do not affect official results</li>
        </ul>
        <div class="pre-actions">
          <Button label="Begin Practice" icon="pi pi-play" :disabled="!questions.length" @click="beginPractice" />
          <Button label="Back" class="p-button-outlined" @click="resetAttempt" />
        </div>
      </div>
    </template>

    <!-- Attempt view (exam-like) -->
    <template v-else-if="!showResults && inExam">
      <div class="exam-toolbar">
        <div class="toolbar-left">
          <strong>{{ attemptMeta.exam_name }}</strong>
          <span class="subject-badge">{{ attemptMeta.subject_name }}</span>
        </div>
        <div class="toolbar-center">
          <span class="live-timer" :class="{ urgent: remainingSeconds < 300 }">
            <i class="pi pi-clock"></i> {{ timerDisplay }}
          </span>
        </div>
        <div class="toolbar-right">
          {{ answeredCount }}/{{ questions.length }} answered
        </div>
      </div>

      <div v-if="questions.length === 0" class="empty">
        <Message severity="warn" :closable="false">No questions loaded for this practice set.</Message>
        <Button label="Back to List" class="p-button-text" @click="resetAttempt" />
      </div>

      <div v-else class="exam-layout">
        <div class="palette">
          <button
            v-for="(q, i) in questions"
            :key="q.id"
            type="button"
            class="palette-btn"
            :class="{ active: currentIndex === i, answered: isAnswered(q.id) }"
            @click="currentIndex = i"
          >{{ i + 1 }}</button>
        </div>

        <div class="question-panel" v-if="currentQuestion">
          <div class="q-nav">
            <Button icon="pi pi-chevron-left" class="p-button-text" :disabled="currentIndex === 0" @click="currentIndex--" />
            <span>Question {{ currentIndex + 1 }} of {{ questions.length }}</span>
            <Button icon="pi pi-chevron-right" class="p-button-text" :disabled="currentIndex >= questions.length - 1" @click="currentIndex++" />
          </div>

          <div class="question-card single-q">
            <div class="question-inner">
          <div class="question-header">
            <div class="question-number">
              <strong>Question {{ currentIndex + 1 }}</strong>
              <span class="marks-badge">{{ currentQuestion.marks }} marks</span>
            </div>
            <span class="type-tag">{{ currentQuestion.question_type?.toUpperCase() }}</span>
          </div>
          
          <div v-if="currentQuestion.stimulus" class="stimulus-box">
            <i class="pi pi-info-circle"></i>
            <p>{{ currentQuestion.stimulus }}</p>
          </div>
          
          <div class="question-content">{{ currentQuestion.content }}</div>

          <!-- MCQ Options -->
          <div v-if="currentQuestion.question_type === 'mcq'" class="mcq-options">
            <label 
              v-for="(opt, oi) in (currentQuestion.options || [])" 
              :key="oi" 
              class="option-item"
              :class="{ selected: responses[currentQuestion.id] == oi }"
            >
              <div class="radio-custom">
                <input 
                  type="radio" 
                  :name="'q-' + currentQuestion.id" 
                  :value="oi" 
                  v-model="responses[currentQuestion.id]"
                />
                <span class="radio-control"></span>
              </div>
              <span class="option-letter">{{ optionLabels[oi] || String.fromCharCode(97 + oi) }}</span>
              <span class="option-text">{{ opt }}</span>
            </label>
          </div>

          <!-- CQ Parts -->
          <div v-else-if="currentQuestion.question_type === 'cq'" class="cq-parts">
            <div v-for="(part, pi) in (currentQuestion.parts || [])" :key="pi" class="part-item">
              <div class="part-header">
                <span class="part-letter">{{ part.label || String.fromCharCode(97 + pi) }}</span>
                <span class="part-content">{{ part.content }}</span>
                <span class="part-marks">({{ part.marks }} marks)</span>
              </div>
              <textarea 
                v-model="responses[currentQuestion.id + '_part_' + pi]" 
                rows="3" 
                class="answer-textarea"
                placeholder="Write your answer here..."
              />
            </div>
          </div>

          <!-- Generic text answer -->
          <textarea 
            v-else 
            v-model="responses[currentQuestion.id]" 
            rows="3" 
            class="answer-textarea"
            placeholder="Write your answer here..."
          />
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Action Bar -->
      <div v-if="questions.length" class="bottom-actions">
        <div class="actions-left">
          <span v-if="autoSaveHint" class="autosave-hint">{{ autoSaveHint }}</span>
          <Button 
            label="Save Progress" 
            icon="pi pi-save" 
            class="p-button-outlined save-btn"
            :loading="saving" 
            @click="saveProgress(false)" 
          />
        </div>
        <div class="actions-right">
          <Button 
            label="Cancel" 
            class="p-button-text p-button-danger cancel-btn"
            @click="cancelAttempt" 
          />
          <Button 
            label="Submit & See Score" 
            icon="pi pi-check-circle" 
            class="submit-btn"
            :loading="saving" 
            @click="submitAttempt" 
          />
        </div>
      </div>
    </template>

    <!-- Results view -->
    <template v-else>
      <div class="results-container">
        <div class="results-card">
          <div class="results-header">
            <i class="pi pi-check-circle" style="font-size: 3rem; color: #10b981"></i>
            <h2>Practice Complete!</h2>
            <p class="results-subtitle">{{ attemptMeta.exam_name }} — {{ attemptMeta.subject_name }}</p>
          </div>
          
          <div class="score-display">
            <div class="score-circle">
              <span class="score-number">{{ evaluation.score }}</span>
              <span class="score-divider">/</span>
              <span class="score-total">{{ evaluation.total_marks }}</span>
            </div>
            <div class="percentage-bar">
              <div class="percentage-fill" :style="{ width: evaluation.percentage + '%' }"></div>
            </div>
            <span class="percentage-text">{{ evaluation.percentage }}%</span>
          </div>
          
          <div v-if="evaluation.weak_subjects?.length" class="improvement-box">
            <i class="pi pi-lightbulb"></i>
            <div>
              <strong>Focus Areas for Improvement:</strong>
              <p>{{ evaluation.weak_subjects.join(', ') }}</p>
            </div>
          </div>

          <div class="breakdown-section">
            <h3>Answer Review</h3>
            <div v-for="(item, bi) in evaluation.breakdown" :key="item.question_id" class="breakdown-item detailed">
              <div class="breakdown-left">
                <span class="breakdown-q">Q{{ bi + 1 }}</span>
                <span class="breakdown-type">{{ item.question_type?.toUpperCase() }}</span>
              </div>
              <div class="breakdown-right">
                <span v-if="item.is_correct === true" class="result-correct">
                  <i class="pi pi-check"></i> Correct — {{ item.marks_obtained }}/{{ item.marks }}
                </span>
                <span v-else-if="item.is_correct === false" class="result-wrong">
                  <i class="pi pi-times"></i> Wrong — 0/{{ item.marks }}
                  <span v-if="item.correct_answer?.index != null" class="correct-hint">
                    (Correct: {{ optionLabels[item.correct_answer.index] || item.correct_answer.index }})
                  </span>
                </span>
                <span v-else class="result-manual">
                  <i class="pi pi-pencil"></i> CQ — not auto-scored
                </span>
              </div>
            </div>
          </div>

          <div class="results-actions">
            <Button 
              class="retry-btn"
              @click="resetAttempt"
            >
              <i class="pi pi-refresh"></i>
              <span>Try Again</span>
            </Button>
            <Button 
              label="Back to Practice List" 
              class="p-button-outlined back-btn"
              @click="finishReview" 
            />
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import examService from '@/services/exam.service'
import { extractData } from '@/utils/api.utils'
import { useExamPlayer, OPTION_LABELS } from '@/composables/useExamPlayer'

const optionLabels = OPTION_LABELS

const loading = ref(false)
const saving = ref(false)
const error = ref(null)
const routines = ref([])
const history = ref([])

const activeAttempt = ref(null)
const attemptMeta = ref({})
const showResults = ref(false)
const evaluation = ref(null)

const player = useExamPlayer({ autoSubmitOnTimeout: true, onTimeout: () => submitAttempt(true) })
const {
  responses, questions, currentIndex, remainingSeconds, durationMinutes,
  autoSaveHint, inExam, currentQuestion, timerDisplay, answeredCount,
  isAnswered, hydrateSavedAnswers, buildAnswersPayload, resetPlayerState,
  initTimersFromAttempt, startAutoSave, stopTimers, setupLeaveWarning,
} = player

let removeLeaveWarning = () => {}

async function loadData() {
  loading.value = true
  error.value = null
  try {
    const [rRes, hRes] = await Promise.all([
      examService.student.practiceRoutines(),
      examService.attempts.my({ is_practice: 1 }),
    ])
    const rData = extractData(rRes, {})
    routines.value = rData.routines || []
    const hData = extractData(hRes, {})
    history.value = (hData.attempts || []).slice(0, 10)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load practice center'
  } finally {
    loading.value = false
  }
}

async function openAttempt(routine, data, skipPreExam = false) {
  activeAttempt.value = data.attempt
  questions.value = data.questions || []
  durationMinutes.value = data.duration_minutes || routine.duration_minutes || 45
  attemptMeta.value = {
    exam_name: routine.exam_name,
    subject_name: routine.subject_name,
  }
  responses.value = {}
  hydrateSavedAnswers(data.saved_answers)
  showResults.value = false
  evaluation.value = null
  currentIndex.value = 0

  if (skipPreExam || data.resumed) {
    inExam.value = true
    initTimersFromAttempt({
      startedAt: data.attempt?.started_at,
      durationMin: durationMinutes.value,
      serverRemaining: data.remaining_seconds,
      onTimeoutSubmit: () => submitAttempt(true),
    })
    startAutoSave((submit, silent) => saveProgress(submit, silent))
    removeLeaveWarning = setupLeaveWarning(true)
  } else {
    inExam.value = false
  }
}

function beginPractice() {
  if (!questions.value.length) return
  inExam.value = true
  initTimersFromAttempt({
    startedAt: activeAttempt.value?.started_at,
    durationMin: durationMinutes.value,
    serverRemaining: null,
    onTimeoutSubmit: () => submitAttempt(true),
  })
  startAutoSave((submit, silent) => saveProgress(submit, silent))
  removeLeaveWarning = setupLeaveWarning(true)
}

async function startPractice(routine) {
  saving.value = true
  try {
    const res = await examService.attempts.start(routine.id)
    const data = extractData(res, {})
    if (!data.questions?.length) {
      alert('No questions available for this practice set.')
      return
    }
    await openAttempt(routine, {
      ...data,
      remaining_seconds: data.remaining_seconds,
    })
  } catch (err) {
    error.value = err.response?.data?.message || 'Could not start practice'
  } finally {
    saving.value = false
  }
}

async function resumePractice(routine) {
  saving.value = true
  try {
    const res = await examService.attempts.get(routine.in_progress_attempt_id)
    const data = extractData(res, {})
    if (data.questions?.length) {
      await openAttempt(routine, {
        attempt: data.attempt,
        questions: data.questions,
        saved_answers: data.saved_answers,
        duration_minutes: data.duration_minutes || routine.duration_minutes,
        remaining_seconds: data.remaining_seconds,
        resumed: true,
      }, true)
    } else {
      await startPractice(routine)
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Could not resume practice'
  } finally {
    saving.value = false
  }
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
      activeAttempt.value = data.attempt
      inExam.value = false
      stopTimers()
      removeLeaveWarning()
    }
  } catch (err) {
    if (!silent) error.value = err.response?.data?.message || 'Save failed'
  } finally {
    saving.value = false
  }
}

function submitAttempt(auto = false) {
  if (!auto && !confirm('Submit your answers? You can practice again unlimited times.')) return
  saveProgress(true)
}

function cancelAttempt() {
  if (confirm('Leave practice? Your in-progress attempt stays saved — you can resume later.')) {
    resetAttempt()
  }
}

function resetAttempt() {
  activeAttempt.value = null
  resetPlayerState()
  showResults.value = false
  evaluation.value = null
  removeLeaveWarning()
}

function finishReview() {
  resetAttempt()
  loadData()
}

async function reviewAttempt(id) {
  try {
    const res = await examService.attempts.get(id)
    const data = extractData(res, {})
    evaluation.value = data.evaluation
    attemptMeta.value = {
      exam_name: data.attempt?.routine?.exam_name,
      subject_name: data.attempt?.routine?.subject_name,
    }
    showResults.value = true
    activeAttempt.value = data.attempt
  } catch (err) {
    alert('Could not load attempt review')
  }
}

onMounted(loadData)
onUnmounted(() => { stopTimers(); removeLeaveWarning() })
</script>

<style scoped>
/* Base Styles */
.practice-page {
  max-width: 1000px;
  margin: 0 auto;
  padding: 2rem 1rem;
}

.page-header {
  text-align: center;
  margin-bottom: 2.5rem;
}

.page-header h1 {
  margin: 0 0 0.5rem;
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
}

.subtitle {
  color: var(--text-muted);
  margin: 0;
  font-size: 0.95rem;
}

.loading, .empty {
  text-align: center;
  padding: 3rem 2rem;
  color: var(--text-muted);
}

.empty-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
  display: block;
  color: var(--text-muted);
}

.empty-hints {
  text-align: left;
  max-width: 500px;
  margin: 1.5rem auto 0;
  font-size: 0.9rem;
  color: var(--text-muted);
  line-height: 1.6;
}

/* Card Grid */
.routine-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.25rem;
  margin-bottom: 2rem;
}

/* Individual Card */
.routine-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid var(--border-color);
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.routine-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.75rem;
}

.exam-info {
  flex: 1;
}

.exam-name {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  line-height: 1.4;
}

.subject-badge {
  display: inline-block;
  background: #eef2ff;
  color: #4f46e5;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  margin-top: 0.5rem;
}

.practice-tag {
  flex-shrink: 0;
}

/* Stats Row */
.stats-row {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  padding: 0.75rem 0;
  border-top: 1px solid #f3f4f6;
  border-bottom: 1px solid var(--border-light);
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  color: var(--text-muted);
}

.stat-item i {
  font-size: 1rem;
  color: var(--text-muted);
}

/* Warning Box */
.warning-box {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  background: #fef3c7;
  padding: 0.75rem;
  border-radius: 8px;
  font-size: 0.85rem;
  color: #92400e;
  line-height: 1.5;
}

.warning-box i {
  color: #f59e0b;
  font-size: 1.1rem;
  margin-top: 0.1rem;
}

/* Card Actions */
.card-actions {
  margin-top: auto;
}

.action-btn {
  width: 100%;
  padding: 0.75rem !important;
  font-weight: 600 !important;
  font-size: 0.95rem !important;
  display: flex !important;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  border-radius: 8px !important;
  transition: all 0.2s ease;
}

.action-btn i {
  font-size: 1.1rem;
}

.start-btn {
  background: #4f46e5 !important;
  border: none !important;
  color: white !important;
}

.start-btn:hover:not(:disabled) {
  background: #4338ca !important;
  transform: translateY(-1px);
}

.start-btn:disabled {
  background: #9ca3af !important;
  cursor: not-allowed !important;
}

.resume-btn {
  background: #f59e0b !important;
  border: none !important;
  color: white !important;
}

.resume-btn:hover {
  background: #d97706 !important;
  transform: translateY(-1px);
}

/* History Section */
.history-section {
  margin-top: 2rem;
}

.history-section h2 {
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 1rem;
  color: var(--text-primary);
}

.history-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.history-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 1rem 1.25rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  transition: all 0.2s ease;
}

.history-card:hover {
  border-color: #d1d5db;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.history-main {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  flex: 1;
  flex-wrap: wrap;
}

.history-exam-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.history-exam-info strong {
  color: var(--text-primary);
  font-size: 0.95rem;
}

.history-subject {
  color: var(--text-muted);
  font-size: 0.8rem;
}

.history-score {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.score-value {
  font-weight: 600;
  color: #4f46e5;
  font-size: 0.95rem;
}

.review-btn {
  background: transparent !important;
  border: 1px solid var(--border-color) !important;
  color: #4f46e5 !important;
  padding: 0.5rem 1rem !important;
  font-size: 0.85rem !important;
  display: flex !important;
  align-items: center;
  gap: 0.5rem;
  border-radius: 6px !important;
  transition: all 0.2s ease;
}

.review-btn:hover {
  background: #eef2ff !important;
  border-color: #4f46e5 !important;
}

/* Attempt View */
.attempt-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding: 1.25rem;
  background: var(--bg-card);
  border-radius: 12px;
  border: 1px solid var(--border-color);
  flex-wrap: wrap;
  gap: 1rem;
}

.attempt-info {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.attempt-info h2 {
  margin: 0;
  font-size: 1.25rem;
  color: var(--text-primary);
}

.timer-badge {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #eef2ff;
  color: #4f46e5;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 600;
}

/* Question Cards */
.questions-list {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  margin-bottom: 5rem;
}

.question-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid var(--border-color);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.question-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.question-number {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.question-number strong {
  font-size: 1.05rem;
  color: var(--text-primary);
}

.marks-badge {
  background: #f3f4f6;
  color: var(--text-muted);
  padding: 0.2rem 0.6rem;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
}

.type-tag {
  background: #eef2ff;
  color: #4f46e5;
  padding: 0.25rem 0.75rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.5px;
}

.stimulus-box {
  background: var(--bg-accent);
  border-left: 4px solid #4f46e5;
  padding: 1rem;
  margin-bottom: 1rem;
  border-radius: 4px;
  display: flex;
  gap: 0.75rem;
}

.stimulus-box i {
  color: #4f46e5;
  font-size: 1.1rem;
  margin-top: 0.1rem;
}

.stimulus-box p {
  margin: 0;
  font-size: 0.9rem;
  color: var(--text-secondary);
  line-height: 1.6;
}

.question-content {
  margin-bottom: 1.25rem;
  font-size: 0.95rem;
  color: var(--text-primary);
  line-height: 1.6;
}

/* MCQ Options */
.mcq-options {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.option-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 0.9rem;
}

.option-item:hover {
  border-color: #4f46e5;
  background: var(--bg-accent);
}

.option-item.selected {
  border-color: #4f46e5;
  background: #eef2ff;
}

.radio-custom {
  position: relative;
  width: 20px;
  height: 20px;
}

.radio-custom input {
  position: absolute;
  opacity: 0;
  cursor: pointer;
}

.radio-control {
  position: absolute;
  top: 0;
  left: 0;
  width: 20px;
  height: 20px;
  border: 2px solid #d1d5db;
  border-radius: 50%;
  transition: all 0.2s ease;
}

.radio-custom input:checked ~ .radio-control {
  border-color: #4f46e5;
  background: #4f46e5;
}

.radio-custom input:checked ~ .radio-control::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 8px;
  height: 8px;
  background: var(--bg-card);
  border-radius: 50%;
}

.option-letter {
  font-weight: 700;
  color: #4f46e5;
  min-width: 1.5rem;
  font-size: 1rem;
}

.option-text {
  color: var(--text-secondary);
}

/* CQ Parts */
.cq-parts {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.part-item {
  background: var(--bg-accent);
  padding: 1rem;
  border-radius: 8px;
  border: 1px solid var(--border-color);
}

.part-header {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
  align-items: baseline;
  flex-wrap: wrap;
}

.part-letter {
  font-weight: 700;
  color: #4f46e5;
  font-size: 1rem;
}

.part-content {
  flex: 1;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.part-marks {
  color: var(--text-muted);
  font-size: 0.8rem;
  font-weight: 600;
}

/* Answer Textarea */
.answer-textarea {
  width: 100%;
  padding: 0.75rem;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  font-size: 0.9rem;
  font-family: inherit;
  resize: vertical;
  transition: border-color 0.2s ease;
  box-sizing: border-box;
}

.answer-textarea:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

/* Bottom Actions */
.bottom-actions {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: var(--bg-card);
  border-top: 2px solid #e5e7eb;
  padding: 1rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 -4px 6px rgba(0, 0, 0, 0.05);
  z-index: 1000;
}

.actions-left, .actions-right {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.save-btn {
  padding: 0.75rem 1.5rem !important;
  font-weight: 600 !important;
}

.submit-btn {
  background: #10b981 !important;
  border: none !important;
  color: white !important;
  padding: 0.75rem 1.5rem !important;
  font-weight: 600 !important;
}

.submit-btn:hover {
  background: #059669 !important;
}

.cancel-btn {
  font-weight: 600 !important;
}

/* Results View */
.results-container {
  max-width: 700px;
  margin: 0 auto;
}

.results-card {
  background: var(--bg-card);
  border-radius: 16px;
  padding: 2.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  text-align: center;
}

.results-header {
  margin-bottom: 2rem;
}

.results-header i {
  display: block;
  margin-bottom: 1rem;
}

.results-header h2 {
  margin: 0 0 0.5rem;
  font-size: 1.5rem;
  color: var(--text-primary);
}

.results-subtitle {
  color: var(--text-muted);
  margin: 0;
  font-size: 0.95rem;
}

.score-display {
  margin: 2rem 0;
}

.score-circle {
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 0.25rem;
  margin-bottom: 1rem;
}

.score-number {
  font-size: 3.5rem;
  font-weight: 700;
  color: #4f46e5;
}

.score-divider {
  font-size: 2rem;
  color: var(--text-muted);
  margin: 0 0.25rem;
}

.score-total {
  font-size: 2rem;
  color: var(--text-muted);
}

.percentage-bar {
  width: 100%;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 0.5rem;
}

.percentage-fill {
  height: 100%;
  background: linear-gradient(90deg, #4f46e5, #10b981);
  border-radius: 4px;
  transition: width 0.5s ease;
}

.percentage-text {
  font-size: 1.25rem;
  font-weight: 600;
  color: #10b981;
}

.improvement-box {
  display: flex;
  gap: 1rem;
  background: #fef3c7;
  padding: 1rem 1.25rem;
  border-radius: 10px;
  margin: 1.5rem 0;
  text-align: left;
  align-items: flex-start;
}

.improvement-box i {
  font-size: 1.5rem;
  color: #f59e0b;
  margin-top: 0.1rem;
}

.improvement-box strong {
  display: block;
  margin-bottom: 0.25rem;
  color: #92400e;
}

.improvement-box p {
  margin: 0;
  color: #92400e;
  font-size: 0.9rem;
}

.breakdown-section {
  text-align: left;
  margin: 2rem 0;
}

.breakdown-section h3 {
  font-size: 1.1rem;
  margin-bottom: 1rem;
  color: var(--text-primary);
}

.breakdown-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--border-light);
}

.breakdown-type {
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--text-muted);
}

.result-correct {
  color: #10b981;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.result-wrong {
  color: #ef4444;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.result-manual {
  color: var(--text-muted);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.results-actions {
  display: flex;
  gap: 1rem;
  margin-top: 2rem;
  justify-content: center;
  flex-wrap: wrap;
}

.pre-exam-card { background: var(--bg-card); border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 1rem; }
.pre-subject { color: #4f46e5; font-weight: 600; margin: 0 0 1rem; }
.pre-meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem; }
.pre-meta .lbl { display: block; font-size: 0.75rem; color: var(--text-muted); }
.pre-rules { margin: 0 0 1.25rem; padding-left: 1.25rem; color: var(--text-secondary); font-size: 0.9rem; }
.pre-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.exam-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; background: linear-gradient(135deg, #1e3a5f, #2d4a6f); color: #fff; padding: 0.85rem 1.25rem; border-radius: 12px; margin-bottom: 1rem; flex-wrap: wrap; }
.live-timer { font-size: 1.2rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.live-timer.urgent { color: #fca5a5; }
.exam-layout { display: grid; grid-template-columns: 72px 1fr; gap: 1rem; }
.palette { display: flex; flex-direction: column; gap: 0.35rem; max-height: 65vh; overflow-y: auto; }
.palette-btn { width: 38px; height: 38px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-card); cursor: pointer; font-size: 0.8rem; }
.palette-btn.active { background: #4f46e5; color: #fff; border-color: #4f46e5; }
.palette-btn.answered { background: #d1fae5; border-color: #34d399; }
.question-panel { background: var(--bg-card); border-radius: 12px; padding: 1rem; border: 1px solid var(--border-color); }
.q-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.autosave-hint { font-size: 0.8rem; color: var(--text-muted); margin-right: 0.5rem; }
.correct-hint { font-size: 0.78rem; color: var(--text-muted); margin-left: 0.35rem; }
.breakdown-item.detailed { flex-direction: column; align-items: flex-start; gap: 0.25rem; }
.breakdown-q { font-weight: 700; color: #4f46e5; margin-right: 0.5rem; }

.retry-btn {
  background: #4f46e5 !important;
  border: none !important;
  color: white !important;
  padding: 0.75rem 2rem !important;
  font-weight: 600 !important;
  display: flex !important;
  align-items: center;
  gap: 0.5rem;
}

.retry-btn:hover {
  background: #4338ca !important;
}

.back-btn {
  padding: 0.75rem 2rem !important;
  font-weight: 600 !important;
}

/* Responsive Design */
@media (max-width: 768px) {
  .practice-page {
    padding: 1rem;
  }
  
  .routine-grid {
    grid-template-columns: 1fr;
  }
  
  .history-card {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .history-main {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .bottom-actions {
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
  }
  
  .actions-left, .actions-right {
    width: 100%;
    justify-content: stretch;
  }
  
  .actions-left .save-btn,
  .actions-right .cancel-btn,
  .actions-right .submit-btn {
    flex: 1;
  }
  
  .results-card {
    padding: 1.5rem;
  }
}
</style>
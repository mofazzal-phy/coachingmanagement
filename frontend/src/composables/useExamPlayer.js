import { ref, computed, onUnmounted } from 'vue'

export const OPTION_LABELS = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ']

/**
 * Shared exam/practice player state: timer, autosave, answers, navigation.
 */
export function useExamPlayer(options = {}) {
  const {
    autoSubmitOnTimeout = true,
    autoSaveIntervalMs = 30000,
    onTimeout = null,
  } = options

  const responses = ref({})
  const questions = ref([])
  const currentIndex = ref(0)
  const remainingSeconds = ref(0)
  const durationMinutes = ref(60)
  const windowEndsAt = ref(null)
  const autoSaveHint = ref('')
  const inExam = ref(false)

  let timerHandle = null
  let autoSaveHandle = null

  const currentQuestion = computed(() => questions.value[currentIndex.value] || null)

  const timerDisplay = computed(() => {
    const s = Math.max(0, remainingSeconds.value)
    const h = Math.floor(s / 3600)
    const m = Math.floor((s % 3600) / 60)
    const sec = s % 60
    if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
    return `${m}:${String(sec).padStart(2, '0')}`
  })

  const answeredCount = computed(() =>
    questions.value.filter(q => isAnswered(q.id)).length
  )

  function isAnswered(qid) {
    if (responses.value[qid] !== undefined && responses.value[qid] !== null && responses.value[qid] !== '') {
      return true
    }
    const q = questions.value.find(x => x.id === qid)
    if (q?.question_type === 'cq' && q.parts?.length) {
      return q.parts.some((_, pi) => {
        const v = responses.value[`${qid}_part_${pi}`]
        return v !== undefined && v !== null && v !== ''
      })
    }
    return false
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

  function resetPlayerState() {
    responses.value = {}
    questions.value = []
    currentIndex.value = 0
    remainingSeconds.value = 0
    inExam.value = false
    autoSaveHint.value = ''
    stopTimers()
  }

  function stopTimers() {
    clearInterval(timerHandle)
    clearInterval(autoSaveHandle)
    timerHandle = null
    autoSaveHandle = null
  }

  function computeRemainingSeconds({ startedAt, durationMin, windowEnd, serverRemaining } = {}) {
    if (serverRemaining != null && serverRemaining >= 0) {
      return Math.max(0, Math.floor(serverRemaining))
    }

    const durationSec = (durationMin || 60) * 60
    let limit = durationSec

    if (startedAt) {
      const elapsed = Math.floor((Date.now() - new Date(startedAt).getTime()) / 1000)
      limit = Math.max(0, durationSec - elapsed)
    }

    if (windowEnd) {
      const windowSec = Math.floor((new Date(windowEnd) - Date.now()) / 1000)
      if (windowSec > 0) limit = Math.min(limit, windowSec)
    }

    return Math.max(0, limit)
  }

  function beginTimers(onTickSubmit) {
    clearInterval(timerHandle)
    timerHandle = setInterval(() => {
      if (remainingSeconds.value > 0) {
        remainingSeconds.value--
      } else if (autoSubmitOnTimeout) {
        const handler = onTimeout || onTickSubmit
        if (handler) handler(true)
      }
    }, 1000)
  }

  function initTimersFromAttempt({ startedAt, durationMin, windowEnd, serverRemaining, onTimeoutSubmit }) {
    durationMinutes.value = durationMin || 60
    windowEndsAt.value = windowEnd || null
    remainingSeconds.value = computeRemainingSeconds({
      startedAt,
      durationMin: durationMin || 60,
      windowEnd,
      serverRemaining,
    })
    beginTimers(onTimeoutSubmit)
  }

  function startAutoSave(saveFn) {
    clearInterval(autoSaveHandle)
    if (!saveFn) return
    autoSaveHandle = setInterval(() => saveFn(false, true), autoSaveIntervalMs)
  }

  function setupLeaveWarning(enabled = true) {
    const handler = (e) => {
      if (!inExam.value) return
      e.preventDefault()
      e.returnValue = ''
    }
    if (enabled) {
      window.addEventListener('beforeunload', handler)
      return () => window.removeEventListener('beforeunload', handler)
    }
    return () => {}
  }

  onUnmounted(stopTimers)

  return {
    OPTION_LABELS,
    responses,
    questions,
    currentIndex,
    remainingSeconds,
    durationMinutes,
    windowEndsAt,
    autoSaveHint,
    inExam,
    currentQuestion,
    timerDisplay,
    answeredCount,
    isAnswered,
    hydrateSavedAnswers,
    buildAnswersPayload,
    resetPlayerState,
    stopTimers,
    computeRemainingSeconds,
    initTimersFromAttempt,
    startAutoSave,
    setupLeaveWarning,
  }
}

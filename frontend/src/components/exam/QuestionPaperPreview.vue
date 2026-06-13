<template>
  <div class="preview-overlay" @click.self="$emit('close')">
    <div class="preview-dialog">
      <div class="preview-header">
        <h3>Question Paper Preview</h3>
        <button class="close-btn" @click="$emit('close')">&times;</button>
      </div>

      <div class="variant-tabs">
        <button
          v-for="v in variants"
          :key="v.id"
          class="tab"
          :class="{ active: variant === v.id }"
          @click="variant = v.id; loadPreview()"
        >{{ v.label }}</button>
      </div>

      <div v-if="loading" class="loading">Loading preview...</div>
      <div v-else-if="error" class="error">{{ error }}</div>
      <div v-else class="preview-content">
        <div class="paper-meta">
          <span>{{ questions.length }} questions</span>
          <span>Total marks: {{ totalMarks }}</span>
        </div>
        <div v-for="(q, i) in questions" :key="q.id" class="preview-q">
          <div class="q-head">
            <strong>Q{{ i + 1 }}.</strong>
            <span class="marks">[{{ q.marks }}]</span>
            <span class="type">{{ q.question_type?.toUpperCase() }}</span>
          </div>
          <div v-if="q.stimulus" class="stimulus">{{ q.stimulus }}</div>
          <div class="content">{{ q.content }}</div>
          <div v-if="q.question_type === 'mcq'" class="options">
            <div v-for="(opt, oi) in (q.options || [])" :key="oi" class="option">
              <span class="key">{{ optionLabels[oi] || String.fromCharCode(97 + oi) }})</span> {{ formatOpt(opt) }}
              <span v-if="showAnswers && q.correct_answer?.index === oi" class="correct">✓</span>
            </div>
          </div>
          <div v-if="q.question_type === 'cq' && q.parts?.length" class="parts">
            <div v-for="(part, pi) in q.parts" :key="pi" class="part">
              {{ String.fromCharCode(97 + pi) }}) {{ part.text || part.content }} ({{ part.marks }}m)
            </div>
          </div>
        </div>
      </div>

      <div class="preview-footer">
        <button class="btn btn-outline" @click="downloadPdf">Download PDF</button>
        <button class="btn btn-primary" @click="$emit('close')">Close</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import examService from '@/services/exam.service'

const props = defineProps({
  routineId: { type: String, required: true },
})

defineEmits(['close'])

const variants = [
  { id: 'student', label: 'Student Copy' },
  { id: 'answer_sheet', label: 'Answer Sheet' },
  { id: 'invigilator', label: 'Invigilator Copy' },
]

const optionLabels = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ']
const variant = ref('student')
const loading = ref(false)
const error = ref(null)
const questions = ref([])

const showAnswers = computed(() => ['answer_sheet', 'invigilator'].includes(variant.value))
const totalMarks = computed(() => questions.value.reduce((s, q) => s + (parseFloat(q.marks) || 0), 0))

function formatOpt(opt) {
  return typeof opt === 'object' ? (opt.text || JSON.stringify(opt)) : opt
}

async function loadPreview() {
  loading.value = true
  error.value = null
  try {
    const res = await examService.routines.getQuestions(props.routineId, {
      include_answers: showAnswers.value,
      variant: variant.value,
    })
    const data = res.data?.data || res.data
    questions.value = data.questions || []
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load preview'
    questions.value = []
  } finally {
    loading.value = false
  }
}

async function downloadPdf() {
  try {
    const res = await examService.routines.exportQuestionPaper(props.routineId, variant.value)
    const blob = new Blob([res.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `question-paper-${variant.value}-${props.routineId}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    alert(err.response?.data?.message || 'PDF download failed')
  }
}

onMounted(loadPreview)
</script>

<style scoped>
.preview-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 1200;
  display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.preview-dialog {
  background: var(--bg-card); border-radius: 12px; width: 100%; max-width: 720px;
  max-height: 90vh; display: flex; flex-direction: column;
}
.preview-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color);
}
.preview-header h3 { margin: 0; }
.close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; }
.variant-tabs { display: flex; gap: 0.25rem; padding: 0.75rem 1.25rem; border-bottom: 1px solid var(--border-light); }
.tab {
  padding: 0.35rem 0.75rem; border: 1px solid var(--border-color); border-radius: 6px;
  background: var(--bg-card); cursor: pointer; font-size: 0.8rem;
}
.tab.active { background: #4f46e5; color: #fff; border-color: #4f46e5; }
.preview-content { flex: 1; overflow-y: auto; padding: 1rem 1.25rem; }
.paper-meta { display: flex; gap: 1rem; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; }
.preview-q { margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-light); }
.q-head { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem; }
.marks { color: var(--text-muted); font-size: 0.85rem; }
.type { font-size: 0.65rem; background: #dbeafe; padding: 2px 5px; border-radius: 4px; }
.stimulus { background: var(--bg-accent); padding: 0.5rem; border-left: 3px solid #4f46e5; margin-bottom: 0.5rem; font-size: 0.9rem; }
.content { font-size: 0.9rem; margin-bottom: 0.35rem; }
.options { margin-left: 1rem; font-size: 0.85rem; }
.option { margin: 0.2rem 0; }
.key { font-weight: 600; margin-right: 0.25rem; }
.correct { color: #059669; font-weight: 700; margin-left: 0.25rem; }
.parts { margin-left: 1rem; font-size: 0.85rem; }
.part { margin: 0.2rem 0; }
.loading, .error { padding: 2rem; text-align: center; color: var(--text-muted); }
.error { color: #dc2626; }
.preview-footer {
  display: flex; justify-content: flex-end; gap: 0.5rem;
  padding: 0.75rem 1.25rem; border-top: 1px solid var(--border-color);
}
.btn { padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; }
.btn-primary { background: #4f46e5; color: #fff; }
.btn-outline { background: var(--bg-card); border: 1px solid var(--border-color); }
</style>

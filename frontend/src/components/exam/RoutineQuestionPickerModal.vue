<template>
  <div class="picker-overlay" @click.self="$emit('close')">
    <div class="picker-dialog">
      <div class="picker-header">
        <div>
          <h3>Attach Questions</h3>
          <p class="subtitle">{{ routine?.subject_name || routine?.subject?.name }} — {{ routine?.batch_name || '' }}</p>
        </div>
        <button class="close-btn" @click="$emit('close')">&times;</button>
      </div>

      <div class="picker-toolbar">
        <input v-model="search" class="search-input" placeholder="Search approved questions..." />
        <label class="check-label">
          <input type="checkbox" v-model="randomize" /> Randomize order for students
        </label>
      </div>

      <div class="picker-body">
        <div class="bank-panel">
          <h4>Question Bank (Approved)</h4>
          <div v-if="loading" class="loading">Loading...</div>
          <div v-else-if="filteredBank.length === 0" class="empty">No approved questions for this subject.</div>
          <div v-else class="bank-list">
            <div
              v-for="q in filteredBank"
              :key="q.id"
              class="bank-item"
              :class="{ selected: isSelected(q.id) }"
              @click="toggleQuestion(q)"
            >
              <span class="type-badge">{{ q.question_type?.toUpperCase() }}</span>
              <span class="q-text">{{ truncate(q.content || q.stimulus, 80) }}</span>
              <span class="q-marks">{{ q.marks }}m</span>
            </div>
          </div>
        </div>

        <div class="selected-panel">
          <h4>Selected ({{ selected.length }}) — drag to reorder</h4>
          <div v-if="selected.length === 0" class="empty">Click questions from the bank to add.</div>
          <div v-else class="selected-list">
            <div
              v-for="(item, idx) in selected"
              :key="item.question_id"
              class="selected-item"
              draggable="true"
              @dragstart="onDragStart(idx)"
              @dragover.prevent
              @drop="onDrop(idx)"
            >
              <span class="drag-handle">☰</span>
              <span class="order">{{ idx + 1 }}</span>
              <span class="q-text">{{ truncate(item.content, 60) }}</span>
              <input
                v-model.number="item.marks_override"
                type="number"
                min="0"
                step="0.5"
                class="marks-input"
                placeholder="Marks"
                title="Override marks (optional)"
              />
              <button class="remove-btn" @click="removeAt(idx)">&times;</button>
            </div>
          </div>
          <div class="total-marks">Total: {{ totalMarks }}</div>
        </div>
      </div>

      <div class="picker-footer">
        <button class="btn btn-outline" @click="$emit('close')">Cancel</button>
        <button class="btn btn-outline" @click="previewPaper">Preview</button>
        <button class="btn btn-primary" :disabled="saving || selected.length === 0" @click="save">
          {{ saving ? 'Saving...' : 'Save Questions' }}
        </button>
      </div>
    </div>

    <QuestionPaperPreview
      v-if="showPreview"
      :routine-id="routine.id"
      @close="showPreview = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import examService from '@/services/exam.service'
import QuestionPaperPreview from '@/components/exam/QuestionPaperPreview.vue'
import { extractData } from '@/utils/api.utils'

const props = defineProps({
  routine: { type: Object, required: true },
})

const emit = defineEmits(['close', 'saved'])

const loading = ref(false)
const saving = ref(false)
const search = ref('')
const randomize = ref(false)
const bank = ref([])
const selected = ref([])
const dragIndex = ref(null)
const showPreview = ref(false)

const filteredBank = computed(() => {
  const q = search.value.toLowerCase()
  return bank.value.filter(item => {
    if (!q) return true
    return (item.content || '').toLowerCase().includes(q)
      || (item.stimulus || '').toLowerCase().includes(q)
  })
})

const totalMarks = computed(() =>
  selected.value.reduce((sum, item) => sum + (parseFloat(item.marks_override ?? item.marks) || 0), 0)
)

function truncate(text, len) {
  if (!text) return '(no text)'
  return text.length > len ? text.slice(0, len) + '…' : text
}

function isSelected(id) {
  return selected.value.some(s => s.question_id === id)
}

function toggleQuestion(q) {
  const idx = selected.value.findIndex(s => s.question_id === q.id)
  if (idx >= 0) {
    selected.value.splice(idx, 1)
  } else {
    selected.value.push({
      question_id: q.id,
      content: q.content || q.stimulus,
      marks: q.marks,
      marks_override: null,
      sort_order: selected.value.length + 1,
    })
  }
}

function removeAt(idx) {
  selected.value.splice(idx, 1)
}

function onDragStart(idx) {
  dragIndex.value = idx
}

function onDrop(idx) {
  if (dragIndex.value === null || dragIndex.value === idx) return
  const item = selected.value.splice(dragIndex.value, 1)[0]
  selected.value.splice(idx, 0, item)
  dragIndex.value = null
}

async function loadBank() {
  loading.value = true
  try {
    const subjectId = props.routine.subject_id
    const res = await examService.questions.list({
      status: 'approved',
      subject_id: subjectId,
      per_page: 200,
    })
    bank.value = extractData(res, [])
  } finally {
    loading.value = false
  }
}

async function loadAttached() {
  try {
    const res = await examService.routines.getQuestions(props.routine.id, { include_answers: true })
    const data = res.data?.data || res.data
    randomize.value = !!data.randomize_questions
    selected.value = (data.questions || []).map((q, i) => ({
      question_id: q.id,
      content: q.content || q.stimulus,
      marks: q.marks,
      marks_override: q.marks_override,
      sort_order: q.sort_order ?? i + 1,
    }))
  } catch {
    selected.value = []
  }
}

async function save() {
  saving.value = true
  try {
    await examService.routines.syncQuestions(props.routine.id, {
      randomize_questions: randomize.value,
      questions: selected.value.map((item, i) => ({
        question_id: item.question_id,
        sort_order: i + 1,
        marks_override: item.marks_override || null,
      })),
    })
    emit('saved')
    emit('close')
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save questions')
  } finally {
    saving.value = false
  }
}

function previewPaper() {
  showPreview.value = true
}

onMounted(async () => {
  await Promise.all([loadBank(), loadAttached()])
})

watch(() => props.routine?.id, async () => {
  await Promise.all([loadBank(), loadAttached()])
})
</script>

<style scoped>
.picker-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1100;
  display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.picker-dialog {
  background: var(--bg-card); border-radius: 12px; width: 100%; max-width: 960px;
  max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.picker-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color);
}
.picker-header h3 { margin: 0; font-size: 1.1rem; }
.subtitle { margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--text-muted); }
.close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
.picker-toolbar {
  display: flex; gap: 1rem; align-items: center; padding: 0.75rem 1.25rem; border-bottom: 1px solid var(--border-light);
}
.search-input {
  flex: 1; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem;
}
.check-label { font-size: 0.85rem; white-space: nowrap; display: flex; align-items: center; gap: 0.35rem; }
.picker-body { display: grid; grid-template-columns: 1fr 1fr; gap: 0; flex: 1; overflow: hidden; min-height: 360px; }
.bank-panel, .selected-panel { padding: 1rem; overflow-y: auto; }
.bank-panel { border-right: 1px solid #e5e7eb; }
.bank-panel h4, .selected-panel h4 { margin: 0 0 0.75rem; font-size: 0.9rem; color: var(--text-secondary); }
.bank-item, .selected-item {
  display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.6rem;
  border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 0.4rem; cursor: pointer; font-size: 0.82rem;
}
.bank-item.selected { border-color: #4f46e5; background: #eef2ff; }
.selected-item { cursor: grab; }
.type-badge { font-size: 0.65rem; background: #dbeafe; color: #1d4ed8; padding: 2px 5px; border-radius: 4px; }
.q-text { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.q-marks { font-weight: 600; color: var(--text-muted); }
.drag-handle { color: var(--text-muted); }
.order { font-weight: 700; color: #4f46e5; min-width: 1.2rem; }
.marks-input { width: 60px; padding: 2px 4px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 0.8rem; }
.remove-btn { background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.1rem; }
.total-marks { margin-top: 0.75rem; font-weight: 600; font-size: 0.9rem; }
.empty, .loading { color: var(--text-muted); font-size: 0.85rem; padding: 1rem 0; }
.picker-footer {
  display: flex; justify-content: flex-end; gap: 0.5rem;
  padding: 0.75rem 1.25rem; border-top: 1px solid var(--border-color);
}
.btn { padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; }
.btn-primary { background: #4f46e5; color: #fff; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-outline { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-secondary); }
</style>

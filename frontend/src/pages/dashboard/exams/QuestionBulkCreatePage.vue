<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <button class="btn btn-outline btn-sm" @click="goBack">← Back</button>
        <h1>{{ isMcq ? 'Bulk MCQ Entry' : 'Bulk CQ Entry' }}</h1>
      </div>
      <div class="header-actions">
        <span class="count-chip">{{ activeCount }} question{{ activeCount === 1 ? '' : 's' }}</span>
        <span class="marks-chip">Total marks: {{ totalMarks }}</span>
      </div>
    </div>

    <div class="info-banner">
      <template v-if="isMcq">
        একসাথে অনেক MCQ তৈরি করুন (বাংলা পরীক্ষার ফরম্যাট)। Save করলে <strong>Draft</strong> থাকবে — Admin review-তে পাঠাতে হলে আলাদা করে Submit করতে হবে।
      </template>
      <template v-else>
        প্রতিটি CQ সেটে উদ্দীপক + (ক)(খ)(গ)(ঘ) চারটি প্রশ্ন (১+২+৩+৪ = ১০ নম্বর)। Save = Draft, Submit = Admin review।
      </template>
    </div>

    <!-- Shared metadata -->
    <div class="meta-card">
      <h3>Set Information</h3>
      <div class="meta-grid">
        <div class="form-group">
          <label>Class *</label>
          <select v-model="shared.class_id" class="form-control" @change="onClassChange">
            <option value="">Select class</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Course *</label>
          <select v-model="shared.course_id" class="form-control" @change="onCourseChange" :disabled="!shared.class_id">
            <option value="">Select course</option>
            <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Batch *</label>
          <select v-model="shared.batch_id" class="form-control" @change="onBatchChange" :disabled="!shared.course_id">
            <option value="">Select batch</option>
            <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Subject *</label>
          <select v-model="shared.subject_id" class="form-control" :disabled="!shared.batch_id">
            <option value="">Select subject</option>
            <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div class="form-group span-2">
          <label>Set Title</label>
          <input v-model="shared.set_title" class="form-control" placeholder="e.g. Physics Model Test - MCQ 30" />
        </div>
        <div class="form-group">
          <label>Difficulty</label>
          <select v-model="shared.difficulty" class="form-control">
            <option value="easy">Easy</option>
            <option value="medium">Medium</option>
            <option value="hard">Hard</option>
          </select>
        </div>
      </div>
    </div>

    <!-- MCQ bulk editor -->
    <div v-if="isMcq" class="editor-card">
      <div class="editor-toolbar">
        <button class="btn btn-outline btn-sm" @click="addMcq(1)">+ Add 1</button>
        <button class="btn btn-outline btn-sm" @click="addMcq(5)">+ Add 5</button>
        <button class="btn btn-outline btn-sm" @click="addMcq(10)">+ Add 10</button>
      </div>

      <div v-for="(q, idx) in mcqItems" :key="q._key" class="mcq-row">
        <div class="row-head">
          <span class="q-num">{{ idx + 1 }}.</span>
          <button v-if="mcqItems.length > 1" type="button" class="btn-remove" @click="removeMcq(idx)">Remove</button>
        </div>

        <div class="form-group">
          <label>উদ্দীপক / Stimulus (optional — e.g. poem for Q8-9)</label>
          <textarea v-model="q.stimulus" class="form-control" rows="2" placeholder="Shared passage for this and following questions..."></textarea>
        </div>

        <div class="form-group">
          <label>Question *</label>
          <textarea v-model="q.content" class="form-control" rows="2" placeholder="Question text"></textarea>
        </div>

        <div class="options-grid">
          <div v-for="(label, oi) in optionLabels" :key="oi" class="option-cell">
            <label class="opt-label">
              <input type="radio" :name="'correct_' + q._key" :value="oi" v-model="q.correct_index" />
              {{ label }}
            </label>
            <input v-model="q.options[oi]" class="form-control" :placeholder="'Option ' + label" />
          </div>
        </div>

        <div class="marks-row">
          <label>Marks</label>
          <input v-model.number="q.marks" type="number" min="0.25" step="0.25" class="form-control marks-input" />
        </div>
      </div>
    </div>

    <!-- CQ bulk editor -->
    <div v-else class="editor-card">
      <div class="editor-toolbar">
        <button class="btn btn-outline btn-sm" @click="addCqSet">+ Add CQ Set</button>
      </div>

      <div v-for="(set, idx) in cqSets" :key="set._key" class="cq-set">
        <div class="row-head">
          <span class="q-num">CQ {{ idx + 1 }}</span>
          <button v-if="cqSets.length > 1" type="button" class="btn-remove" @click="removeCqSet(idx)">Remove set</button>
        </div>

        <div class="form-group">
          <label>Section (e.g. ক অংশ — গদ্য)</label>
          <input v-model="set.section_label" class="form-control" placeholder="ক অংশ — গদ্য" />
        </div>

        <div class="form-group">
          <label>Chapter / Topic</label>
          <div class="inline-2">
            <input v-model="set.chapter" class="form-control" placeholder="Chapter" />
            <input v-model="set.topic" class="form-control" placeholder="Topic" />
          </div>
        </div>

        <div class="form-group">
          <label>উদ্দীপক (Stimulus) *</label>
          <textarea v-model="set.stimulus" class="form-control stimulus-area" rows="5" placeholder="Passage / story / scenario..."></textarea>
        </div>

        <div class="cq-parts">
          <div v-for="(part, pi) in set.parts" :key="part.key" class="cq-part">
            <div class="part-head">
              <strong>{{ part.label }}</strong>
              <span class="part-marks">{{ part.marks }} marks</span>
            </div>
            <textarea v-model="part.content" class="form-control" rows="2" :placeholder="part.label + ' question...'"></textarea>
          </div>
        </div>
      </div>
    </div>

    <div v-if="error" class="error-banner">{{ error }}</div>
    <div v-if="successMsg" class="success-banner">{{ successMsg }}</div>

    <div class="footer-actions">
      <button class="btn btn-outline" @click="goBack">Cancel</button>
      <button class="btn btn-primary" :disabled="saving" @click="saveDraft">
        {{ saving ? 'Saving...' : '💾 Save All as Draft' }}
      </button>
      <button
        v-if="lastSetId"
        class="btn btn-success"
        :disabled="submitting"
        @click="submitForReview"
      >
        {{ submitting ? 'Submitting...' : '📤 Submit Set for Review' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import examService from '@/services/exam.service'
import academicService from '@/services/academic.service'
import enrollmentService from '@/services/enrollment.service'
import { extractData } from '@/utils/api.utils'

const route = useRoute()
const router = useRouter()

const isMcq = computed(() => route.meta?.bulkType === 'mcq' || route.path.includes('/mcq'))
const optionLabels = ['(ক)', '(খ)', '(গ)', '(ঘ)']

const classes = ref([])
const courses = ref([])
const batches = ref([])
const subjects = ref([])

const shared = ref({
  class_id: '',
  course_id: '',
  batch_id: '',
  subject_id: '',
  set_title: '',
  difficulty: 'medium',
  chapter: '',
  topic: '',
})

const mcqItems = ref([])
const cqSets = ref([])
const saving = ref(false)
const submitting = ref(false)
const error = ref('')
const successMsg = ref('')
const lastSetId = ref('')

let keySeq = 1
const nextKey = () => keySeq++

function emptyMcq() {
  return {
    _key: nextKey(),
    stimulus: '',
    content: '',
    options: ['', '', '', ''],
    correct_index: 0,
    marks: 1,
  }
}

function emptyCqSet() {
  return {
    _key: nextKey(),
    section_label: '',
    chapter: '',
    topic: '',
    stimulus: '',
    parts: [
      { key: 'ka', label: '(ক)', content: '', marks: 1 },
      { key: 'kha', label: '(খ)', content: '', marks: 2 },
      { key: 'ga', label: '(গ)', content: '', marks: 3 },
      { key: 'gha', label: '(ঘ)', content: '', marks: 4 },
    ],
  }
}

function addMcq(n = 1) {
  for (let i = 0; i < n; i++) mcqItems.value.push(emptyMcq())
}

function removeMcq(idx) {
  mcqItems.value.splice(idx, 1)
}

function addCqSet() {
  cqSets.value.push(emptyCqSet())
}

function removeCqSet(idx) {
  cqSets.value.splice(idx, 1)
}

const activeCount = computed(() => (isMcq.value ? mcqItems.value.filter(hasMcqContent).length : cqSets.value.filter(hasCqContent).length))

const totalMarks = computed(() => {
  if (isMcq.value) {
    return mcqItems.value.filter(hasMcqContent).reduce((s, q) => s + (Number(q.marks) || 0), 0)
  }
  return cqSets.value.filter(hasCqContent).length * 10
})

function hasMcqContent(q) {
  return q.content?.trim() || q.options.some(o => o?.trim())
}

function hasCqContent(set) {
  return set.stimulus?.trim() || set.parts.some(p => p.content?.trim())
}

onMounted(async () => {
  try {
    const res = await academicService.classes.list({ per_page: 200 })
    classes.value = extractData(res, [])
  } catch {
    classes.value = []
  }
  if (isMcq.value) {
    for (let i = 0; i < 15; i++) addMcq(1)
  } else {
    addCqSet()
    addCqSet()
  }
})

async function onClassChange() {
  shared.value.course_id = ''
  shared.value.batch_id = ''
  shared.value.subject_id = ''
  courses.value = []
  batches.value = []
  subjects.value = []
  if (!shared.value.class_id) return
  const res = await enrollmentService.getCourses({ class_id: shared.value.class_id })
  courses.value = extractData(res, [])
}

async function onCourseChange() {
  shared.value.batch_id = ''
  shared.value.subject_id = ''
  batches.value = []
  subjects.value = []
  if (!shared.value.course_id) return
  const res = await enrollmentService.getBatchesByCourse(shared.value.course_id)
  batches.value = extractData(res, [])
}

async function onBatchChange() {
  shared.value.subject_id = ''
  subjects.value = []
  if (!shared.value.course_id || !shared.value.batch_id) return
  const res = await academicService.subjects.byCourse(shared.value.course_id)
  subjects.value = extractData(res, [])
  if (subjects.value.length === 1) shared.value.subject_id = subjects.value[0].id
}

function validateShared() {
  if (!shared.value.class_id || !shared.value.course_id || !shared.value.batch_id || !shared.value.subject_id) {
    error.value = 'Class, Course, Batch, and Subject are all required.'
    return false
  }
  return true
}

function buildPayload() {
  if (isMcq.value) {
    const questions = mcqItems.value
      .filter(hasMcqContent)
      .map((q, i) => ({
        content: q.content.trim(),
        stimulus: q.stimulus?.trim() || null,
        options: q.options.map(o => o.trim()).filter(Boolean).length >= 2
          ? q.options.map(o => o.trim())
          : q.options.map(o => o.trim()),
        correct_index: Number(q.correct_index) || 0,
        marks: Number(q.marks) || 1,
        sort_order: i + 1,
      }))
    return { type: 'mcq', shared: { ...shared.value }, questions }
  }

  const questions = cqSets.value
    .filter(hasCqContent)
    .map((set, i) => ({
      section_label: set.section_label?.trim() || `CQ ${i + 1}`,
      stimulus: set.stimulus?.trim() || '',
      chapter: set.chapter?.trim() || null,
      topic: set.topic?.trim() || null,
      sort_order: i + 1,
      parts: set.parts.map(p => ({ key: p.key, content: p.content.trim(), marks: p.marks })),
    }))
  return { type: 'cq', shared: { ...shared.value }, questions }
}

async function saveDraft() {
  error.value = ''
  successMsg.value = ''
  if (!validateShared()) return

  const payload = buildPayload()
  if (!payload.questions.length) {
    error.value = 'Add at least one question with content.'
    return
  }

  saving.value = true
  try {
    const res = await examService.questions.bulkCreate(payload)
    const data = extractData(res, {})
    lastSetId.value = data.question_set_id || ''
    successMsg.value = `${data.count || payload.questions.length} question(s) saved as draft. Submit for review when ready.`
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save'
  } finally {
    saving.value = false
  }
}

async function submitForReview() {
  if (!lastSetId.value) {
    error.value = 'Save as draft first, then submit.'
    return
  }
  if (!confirm('Submit entire set to admin for review?')) return
  submitting.value = true
  error.value = ''
  try {
    const res = await examService.questions.bulkSubmit({ question_set_id: lastSetId.value })
    const data = extractData(res, {})
    successMsg.value = `${data.submitted_count || 0} question(s) submitted for admin review.`
    setTimeout(() => goBack(), 1500)
  } catch (err) {
    error.value = err.response?.data?.message || 'Submit failed'
  } finally {
    submitting.value = false
  }
}

function goBack() {
  if (route.meta?.teacherMode) {
    router.push({ name: 'TeacherQuestions' })
  } else {
    router.push({ name: 'QuestionBank' })
  }
}
</script>

<style scoped>
.page-container { max-width: 960px; margin: 0 auto; padding-bottom: 2rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem; }
.header-left { display: flex; align-items: center; gap: 0.75rem; }
.header-left h1 { margin: 0; font-size: 1.35rem; }
.count-chip, .marks-chip { font-size: 0.8rem; padding: 0.25rem 0.6rem; border-radius: 999px; background: #e0e7ff; color: #4338ca; font-weight: 600; }
.info-banner { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; }
.meta-card, .editor-card { background: var(--bg-card); border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; box-shadow: var(--shadow-sm); }
.meta-card h3 { margin: 0 0 0.75rem; font-size: 1rem; }
.meta-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.75rem; }
.meta-grid .span-2 { grid-column: span 2; }
.form-group label { display: block; font-size: 0.78rem; font-weight: 600; margin-bottom: 0.25rem; color: var(--text-secondary); }
.form-control { width: 100%; padding: 0.45rem 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.9rem; }
.editor-toolbar { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
.mcq-row, .cq-set { border: 1px solid var(--border-color); border-radius: 10px; padding: 1rem; margin-bottom: 1rem; background: #fafafa; }
.row-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
.q-num { font-weight: 700; font-size: 1.1rem; color: #4f46e5; }
.btn-remove { background: none; border: none; color: #dc2626; cursor: pointer; font-size: 0.8rem; }
.options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin: 0.5rem 0; }
.option-cell { display: flex; flex-direction: column; gap: 0.25rem; }
.opt-label { font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 0.35rem; }
.marks-row { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; }
.marks-input { width: 80px; }
.stimulus-area { font-family: inherit; line-height: 1.6; }
.cq-parts { display: flex; flex-direction: column; gap: 0.75rem; }
.cq-part { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.75rem; }
.part-head { display: flex; justify-content: space-between; margin-bottom: 0.35rem; }
.part-marks { font-size: 0.75rem; color: var(--text-muted); }
.inline-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
.footer-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: flex-end; position: sticky; bottom: 0; background: var(--bg-card); padding: 1rem; border-top: 1px solid var(--border-color); margin: 0 -0.5rem; }
.btn { padding: 0.55rem 1rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 0.85rem; }
.btn-primary { background: #4f46e5; color: white; }
.btn-success { background: #059669; color: white; }
.btn-outline { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-secondary); }
.btn-sm { padding: 0.35rem 0.65rem; font-size: 0.78rem; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.error-banner { background: #fee2e2; color: #991b1b; padding: 0.65rem 1rem; border-radius: 8px; margin-bottom: 0.75rem; }
.success-banner { background: #d1fae5; color: #065f46; padding: 0.65rem 1rem; border-radius: 8px; margin-bottom: 0.75rem; }
</style>

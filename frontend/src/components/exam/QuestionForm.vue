<template>
  <div class="question-form">
    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <div class="form-row">
      <div class="form-group">
        <label>Class <span class="required">*</span></label>
        <select v-model="form.class_id" class="form-control" @change="onClassChange">
          <option value="">Select class</option>
          <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <div class="form-group">
        <label>Course <span class="required">*</span></label>
        <select v-model="form.course_id" class="form-control" @change="onCourseChange" :disabled="!form.class_id">
          <option value="">Select course</option>
          <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Batch <span class="required">*</span></label>
        <select v-model="form.batch_id" class="form-control" @change="onBatchChange" :disabled="!form.course_id">
          <option value="">Select batch</option>
          <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
      </div>
      <div class="form-group">
        <label>Subject <span class="required">*</span></label>
        <select v-model="form.subject_id" class="form-control" :disabled="!form.batch_id">
          <option value="">Select subject</option>
          <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Type <span class="required">*</span></label>
        <select v-model="form.question_type" class="form-control" @change="onTypeChange">
          <option value="mcq">MCQ</option>
          <option value="cq">CQ</option>
          <option value="written">Written</option>
          <option value="practical">Practical</option>
        </select>
      </div>
      <div class="form-group">
        <label>Difficulty</label>
        <select v-model="form.difficulty" class="form-control">
          <option value="easy">Easy</option>
          <option value="medium">Medium</option>
          <option value="hard">Hard</option>
        </select>
      </div>
      <div class="form-group">
        <label>Marks <span class="required">*</span></label>
        <input v-model.number="form.marks" type="number" min="0.25" step="0.25" class="form-control" />
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Chapter</label>
        <input v-model="form.chapter" class="form-control" placeholder="e.g. Chapter 3" />
      </div>
      <div class="form-group">
        <label>Topic</label>
        <input v-model="form.topic" class="form-control" placeholder="e.g. Algebra" />
      </div>
    </div>

    <div class="form-group">
      <label>Question <span class="required">*</span></label>
      <textarea v-model="form.content" class="form-control" rows="4" placeholder="Enter question text"></textarea>
    </div>

    <div v-if="form.question_type === 'mcq'" class="mcq-block">
      <label>Options <span class="required">*</span></label>
      <div v-for="(opt, idx) in form.options" :key="idx" class="option-row">
        <input
          type="radio"
          name="correct_option"
          :value="idx"
          v-model="form.correct_index"
        />
        <input v-model="form.options[idx]" class="form-control" :placeholder="`Option ${idx + 1}`" />
        <button v-if="form.options.length > 2" type="button" class="btn-icon danger" @click="removeOption(idx)">✕</button>
      </div>
      <button type="button" class="btn btn-outline btn-sm" @click="addOption">+ Add option</button>
    </div>

    <div class="form-group">
      <label>Attachment</label>
      <input type="file" class="form-control" accept="image/*,.pdf" @change="onFileChange" />
      <a v-if="existingAttachmentUrl && !attachmentFile" :href="existingAttachmentUrl" target="_blank" class="attachment-link">View current attachment</a>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import academicService from '@/services/academic.service'
import enrollmentService from '@/services/enrollment.service'
import { extractData } from '@/utils/api.utils'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  existingAttachmentUrl: { type: String, default: '' },
  error: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'attachment-change'])

const classes = ref([])
const courses = ref([])
const batches = ref([])
const subjects = ref([])
const attachmentFile = ref(null)

const form = ref({
  class_id: '',
  course_id: '',
  batch_id: '',
  subject_id: '',
  chapter: '',
  topic: '',
  question_type: 'mcq',
  difficulty: 'medium',
  marks: 1,
  content: '',
  options: ['', '', '', ''],
  correct_index: 0,
})

watch(form, () => emitUpdate(), { deep: true })

watch(() => props.modelValue, async (val) => {
  if (!val || !Object.keys(val).length) return
  form.value = {
    ...form.value,
    ...val,
    options: val.options?.length ? [...val.options] : form.value.options,
    correct_index: val.correct_answer?.index ?? val.correct_index ?? 0,
  }
  if (val.class_id && courses.value.length === 0) {
    await onClassChange()
    form.value.course_id = val.course_id || ''
    if (form.value.course_id) {
      await onCourseChange()
      form.value.batch_id = val.batch_id || ''
      if (form.value.batch_id) {
        await onBatchChange()
        form.value.subject_id = val.subject_id || form.value.subject_id
      }
    }
  }
}, { immediate: true, deep: true })

function emitUpdate() {
  const payload = { ...form.value }
  if (form.value.question_type === 'mcq') {
    payload.correct_answer = { index: Number(form.value.correct_index), value: form.value.options[form.value.correct_index] }
  }
  emit('update:modelValue', payload)
}

onMounted(async () => {
  try {
    const res = await academicService.classes.list({ per_page: 200 })
    classes.value = extractData(res, [])
  } catch {
    classes.value = []
  }
  if (form.value.class_id) await onClassChange()
})

async function onClassChange() {
  form.value.course_id = ''
  form.value.batch_id = ''
  form.value.subject_id = ''
  courses.value = []
  batches.value = []
  subjects.value = []
  if (!form.value.class_id) return
  const res = await enrollmentService.getCourses({ class_id: form.value.class_id })
  courses.value = extractData(res, [])
}

async function onCourseChange() {
  form.value.batch_id = ''
  form.value.subject_id = ''
  batches.value = []
  subjects.value = []
  if (!form.value.course_id) return
  const res = await enrollmentService.getBatchesByCourse(form.value.course_id)
  batches.value = extractData(res, [])
}

async function onBatchChange() {
  form.value.subject_id = ''
  subjects.value = []
  if (!form.value.course_id || !form.value.batch_id) return
  const res = await academicService.subjects.byCourse(form.value.course_id)
  subjects.value = extractData(res, [])
  if (subjects.value.length === 1) form.value.subject_id = subjects.value[0].id
}

function onTypeChange() {
  if (form.value.question_type !== 'mcq') {
    form.value.options = []
    form.value.correct_index = 0
  } else if (!form.value.options.length) {
    form.value.options = ['', '', '', '']
  }
}

function addOption() {
  form.value.options.push('')
}

function removeOption(idx) {
  form.value.options.splice(idx, 1)
  if (form.value.correct_index >= form.value.options.length) {
    form.value.correct_index = 0
  }
}

function onFileChange(e) {
  attachmentFile.value = e.target.files?.[0] || null
  emit('attachment-change', attachmentFile.value)
}

function buildFormData() {
  const fd = new FormData()
  const data = { ...form.value }
  delete data.options
  delete data.correct_index

  Object.entries(data).forEach(([key, val]) => {
    if (val === null || val === undefined || val === '') return
    if (key === 'correct_answer') return
    fd.append(key, val)
  })

  if (form.value.question_type === 'mcq') {
    fd.append('options', JSON.stringify(form.value.options.filter(Boolean)))
    fd.append('correct_answer', String(form.value.correct_index))
  }

  if (attachmentFile.value) {
    fd.append('attachment', attachmentFile.value)
  }

  return fd
}

defineExpose({ buildFormData, form })
</script>

<style scoped>
.question-form { display: flex; flex-direction: column; gap: 0.75rem; }
.form-row { display: flex; gap: 1rem; flex-wrap: wrap; }
.form-row .form-group { flex: 1; min-width: 180px; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; }
.form-control { width: 100%; padding: 0.5rem 0.65rem; border: 1px solid var(--border-color); border-radius: 6px; }
.required { color: #dc2626; }
.mcq-block { border: 1px solid var(--border-color); border-radius: 8px; padding: 0.75rem; }
.option-row { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
.option-row .form-control { flex: 1; }
.btn-icon.danger { background: none; border: none; color: #dc2626; cursor: pointer; }
.alert-danger { background: #fee2e2; color: #991b1b; padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem; }
.attachment-link { font-size: 0.8rem; color: #4f46e5; }
</style>

<template>
  <div class="modal-overlay" v-if="visible" @click.self="$emit('close')">
    <div class="modal-dialog modal-lg">
      <div class="modal-header">
        <h3>⚡ Generate Class Routine</h3>
        <button class="modal-close" @click="$emit('close')">✕</button>
      </div>
      <div class="modal-body">
        <!-- Step Indicator -->
        <div class="step-indicator">
          <div v-for="(s, i) in steps" :key="i" :class="['step-dot', { active: step >= i + 1, done: step > i + 1 }]">
            <span class="dot-num">{{ i + 1 }}</span>
            <span class="dot-label">{{ s }}</span>
          </div>
        </div>

        <!-- Step 1: Level + Subjects -->
        <div v-show="step === 1">
          <div class="form-row">
            <div class="form-group col-4">
              <label>Generation Level</label>
              <select v-model="form.level" class="form-control" @change="onLevelChange">
                <option value="">Select</option>
                <option value="batch">Batch-wise</option>
                <option value="course">Course-wise</option>
                <option value="class">Class-wise</option>
              </select>
            </div>
            <div class="form-group col-8">
              <label>{{ levelLabel }}</label>
              <select v-model="form.level_id" class="form-control" @change="onLevelIdChange">
                <option value="">Select {{ levelLabel }}</option>
                <option v-for="item in levelOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Academic Session</label>
            <select v-model="form.academic_session_id" class="form-control">
              <option value="">Select</option>
              <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Subjects <span class="req">*</span></label>
            <div class="subject-chips">
              <label v-for="s in availableSubjects" :key="s.id" :class="['chip', { selected: form.subject_ids.includes(s.id) }]">
                <input type="checkbox" :value="s.id" v-model="form.subject_ids" hidden />
                {{ s.name }}
              </label>
            </div>
            <p v-if="availableSubjects.length === 0 && form.level_id" class="text-muted">No subjects found for this selection.</p>
          </div>
        </div>

        <!-- Step 2: Constraints -->
        <div v-show="step === 2">
          <div class="form-group">
            <label>Max Classes Per Day</label>
            <input v-model.number="form.constraints.max_per_day" type="number" min="1" max="12" class="form-control" />
          </div>
          <div class="form-group">
            <label>Active Days</label>
            <div class="day-chips">
              <label v-for="d in allDays" :key="d" :class="['chip', { selected: form.constraints.days.includes(d) }]">
                <input type="checkbox" :value="d" v-model="form.constraints.days" hidden />
                {{ dayNames[d] }}
              </label>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-6">
              <label>Start Date</label>
              <input v-model="form.start_date" type="date" class="form-control" />
            </div>
            <div class="form-group col-6">
              <label>End Date</label>
              <input v-model="form.end_date" type="date" class="form-control" />
            </div>
          </div>
        </div>

        <!-- Step 3: Preview -->
        <div v-show="step === 3">
          <div v-if="generating" class="loading-center">
            <div class="spinner-lg"></div>
            <p>Generating optimal schedule...</p>
          </div>
          <div v-else-if="preview">
            <div class="preview-stats">
              <span class="stat-item">📊 {{ preview.total_slots }} slots generated</span>
              <span v-if="preview.warnings?.length" class="stat-item stat-warn">⚠️ {{ preview.warnings.length }} warnings</span>
            </div>
            <div v-if="preview.warnings?.length" class="warnings-box">
              <h4>Warnings</h4>
              <ul><li v-for="(w, i) in preview.warnings" :key="i">{{ w }}</li></ul>
            </div>
            <div class="preview-table-wrap">
              <table class="preview-table">
                <thead><tr><th>Day</th><th>Subject</th><th>Teacher</th><th>Period</th></tr></thead>
                <tbody>
                  <tr v-for="(r, i) in preview.generated" :key="i">
                    <td>{{ dayNames[r.day_of_week] }}</td>
                    <td>{{ r._subject_name || 'Subject' }}</td>
                    <td>{{ r._teacher_name || 'Teacher' }}</td>
                    <td>{{ r._period_name || 'Period' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Error -->
        <div v-if="genError" class="alert alert-danger">{{ genError }}</div>
      </div>

      <div class="modal-footer">
        <button v-if="step > 1" class="btn btn-outline" @click="step--">← Back</button>
        <button v-if="step === 1" class="btn btn-outline" @click="$emit('close')">Cancel</button>
        <button v-if="step < 3" class="btn btn-primary" @click="step++" :disabled="!canProceed">Continue →</button>
        <button v-if="step === 3 && !preview" class="btn btn-primary" @click="doGenerate" :disabled="generating">
          {{ generating ? 'Generating...' : '⚡ Generate' }}
        </button>
        <button v-if="step === 3 && preview" class="btn btn-success" @click="applyGenerate" :disabled="submitting">
          {{ submitting ? 'Saving...' : '✅ Apply & Save' }}
        </button>
        <button v-if="step === 3 && preview" class="btn btn-outline" @click="doGenerate">🔄 Regenerate</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useClassRoutineStore } from '@/stores/class-routine.store'
import classRoutineService from '@/services/class-routine.service'
import academicService from '@/services/academic.service'
import enrollmentService from '@/services/enrollment.service'

const props = defineProps({ visible: Boolean, sessions: { type: Array, default: () => [] } })
const emit = defineEmits(['close', 'applied'])

const store = useClassRoutineStore()
const step = ref(1)
const generating = ref(false)
const submitting = ref(false)
const preview = ref(null)
const genError = ref(null)

const steps = ['Level & Subjects', 'Constraints', 'Preview & Save']
const allDays = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri']
const dayNames = { sat: 'Sat', sun: 'Sun', mon: 'Mon', tue: 'Tue', wed: 'Wed', thu: 'Thu', fri: 'Fri' }

const form = reactive({
  level: '', level_id: '', academic_session_id: '',
  subject_ids: [], constraints: { days: ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'], max_per_day: 8 },
  start_date: '', end_date: ''
})

const levelOptions = ref([])
const availableSubjects = ref([])
const batches = ref([])
const courses = ref([])
const classes = ref([])

const levelLabel = computed(() => {
  const map = { batch: 'Batch', course: 'Course', class: 'Class' }
  return map[form.level] || 'Item'
})

const canProceed = computed(() => {
  if (step.value === 1) return form.level && form.level_id && form.subject_ids.length > 0 && form.academic_session_id
  if (step.value === 2) return form.constraints.max_per_day > 0 && form.constraints.days.length > 0
  return true
})

const onLevelChange = () => {
  form.level_id = ''
  form.subject_ids = []
  levelOptions.value = []
  availableSubjects.value = []
  if (form.level === 'batch') levelOptions.value = batches.value
  else if (form.level === 'course') levelOptions.value = courses.value
  else if (form.level === 'class') levelOptions.value = classes.value
}

const onLevelIdChange = async () => {
  availableSubjects.value = []
  try {
    if (form.level === 'class') {
      const res = await academicService.subjects.byClass(form.level_id)
      availableSubjects.value = res.data?.data || []
    } else if (form.level === 'batch') {
      const batch = batches.value.find(b => b.id === form.level_id)
      if (batch?.course_id) {
        const res = await enrollmentService.getCourse(batch.course_id)
        availableSubjects.value = res.data?.data?.subjects || []
      }
    } else if (form.level === 'course') {
      const res = await enrollmentService.getCourse(form.level_id)
      availableSubjects.value = res.data?.data?.subjects || []
    }
  } catch (e) { /* silent */ }
}

const doGenerate = async () => {
  generating.value = true
  genError.value = null
  preview.value = null
  try {
    const result = await store.generate({ ...form })
    const previewData = result?.data || result
    if (previewData?.generated) {
      // Resolve names for preview
      const periodsRes = await classRoutineService.listAllPeriods()
      const periods = periodsRes.data?.data || []
      previewData.generated = previewData.generated.map(r => ({
        ...r,
        _subject_name: availableSubjects.value.find(s => s.id === r.subject_id)?.name || 'Subject',
        _period_name: periods.find(p => p.id === r.period_id)?.name || 'Period',
        _teacher_name: 'Teacher',
      }))
    }
    preview.value = previewData
  } catch (e) {
    genError.value = e.response?.data?.message || 'Generation failed'
  } finally {
    generating.value = false
  }
}

const applyGenerate = async () => {
  submitting.value = true
  try {
    await store.generate({ ...form, apply: true })
    emit('applied')
    emit('close')
  } catch (e) {
    genError.value = e.response?.data?.message || 'Failed to apply'
  } finally {
    submitting.value = false
  }
}

// Load initial data
academicService.getClasses({ per_page: 100 }).then(r => { classes.value = r.data?.data || [] })
enrollmentService.listAllCourses().then(r => { courses.value = r.data?.data || [] })
enrollmentService.getBatches({ per_page: 200 }).then(r => {
  batches.value = r.data?.data?.data || r.data?.data || []
})

watch(() => props.visible, (v) => {
  if (v) { step.value = 1; preview.value = null; genError.value = null }
})
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-dialog { background: var(--bg-card); border-radius: 16px; width: 700px; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); }
.modal-header h3 { margin: 0; font-size: 1.1rem; }
.modal-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted); }
.modal-body { padding: 1.5rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); }

.step-indicator { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; }
.step-dot { flex: 1; text-align: center; }
.step-dot .dot-num { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #e5e7eb; color: var(--text-muted); font-size: 0.8rem; font-weight: 700; margin-bottom: 0.25rem; }
.step-dot.active .dot-num { background: #3b82f6; color: #fff; }
.step-dot.done .dot-num { background: #10b981; color: #fff; }
.step-dot .dot-label { display: block; font-size: 0.7rem; color: var(--text-muted); }

.form-row { display: flex; gap: 1rem; }
.form-group { flex: 1; margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.3rem; }
.form-control { width: 100%; padding: 0.55rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; }
.col-4 { flex: 0 0 33%; }
.col-8 { flex: 1; }
.req { color: #ef4444; }

.subject-chips, .day-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.chip { display: inline-flex; padding: 0.35rem 0.75rem; border: 1px solid var(--border-color); border-radius: 20px; font-size: 0.8rem; cursor: pointer; transition: all 0.15s; user-select: none; }
.chip:hover { border-color: #3b82f6; }
.chip.selected { background: #3b82f6; color: #fff; border-color: #3b82f6; }

.loading-center { text-align: center; padding: 2rem; }
.spinner-lg { width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.6s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }

.preview-stats { display: flex; gap: 1rem; margin-bottom: 1rem; }
.stat-item { padding: 0.5rem 1rem; background: #f0fdf4; border-radius: 8px; font-size: 0.85rem; }
.stat-warn { background: #fef3c7; }
.warnings-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 0.75rem; margin-bottom: 1rem; }
.warnings-box h4 { margin: 0 0 0.3rem; font-size: 0.85rem; }
.warnings-box ul { margin: 0; padding-left: 1.2rem; font-size: 0.8rem; color: #92400e; }
.preview-table-wrap { max-height: 250px; overflow-y: auto; }
.preview-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.preview-table th { background: var(--bg-surface-muted); padding: 0.5rem; text-align: left; border-bottom: 2px solid var(--border-color); }
.preview-table td { padding: 0.4rem 0.5rem; border-bottom: 1px solid var(--border-light); }

.btn { padding: 0.55rem 1.15rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; }
.btn-outline { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-color); }
.btn-primary { background: #3b82f6; color: #fff; }
.btn-primary:disabled { opacity: 0.5; }
.btn-success { background: #10b981; color: #fff; }
.alert-danger { background: #fef2f2; color: #dc2626; padding: 0.75rem; border-radius: 8px; margin-top: 0.75rem; font-size: 0.85rem; }
.text-muted { color: var(--text-muted); font-size: 0.8rem; }
</style>

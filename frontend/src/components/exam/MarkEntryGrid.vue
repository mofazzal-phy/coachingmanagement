<template>
  <div class="mark-entry-grid">
    <p v-if="perComponentPass" class="grid-hint grid-hint--pass">
      <span class="hint-icon">✓</span>
      Pass rule: each part must meet its own pass mark — <strong>{{ passCriteriaText }}</strong>. Failing any part fails the subject.
    </p>
    <p v-else-if="columns.length" class="grid-hint">
      Enter marks out of the maximum shown in each column header. You cannot exceed that limit.
    </p>
    <div class="grid-table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th class="col-num">#</th>
          <th>Student</th>
          <th>Roll</th>
          <th v-for="col in columns" :key="col.key" class="col-score">
            {{ col.label }}
            <span class="col-max">max {{ col.max_marks }}</span>
            <span v-if="col.pass_marks > 0" class="col-pass">pass {{ col.pass_marks }}</span>
          </th>
          <th>Total</th>
          <th v-if="perComponentPass">Result</th>
          <th>Grade</th>
          <th v-if="showEvaluationStatus">Eval</th>
          <th>Remarks</th>
          <th v-if="perRowSave">Save</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(student, index) in students" :key="student.id">
          <td class="col-num">{{ index + 1 }}</td>
          <td class="col-student"><strong>{{ student.name }}</strong></td>
          <td>{{ student.roll_no || '—' }}</td>
          <td v-for="col in columns" :key="col.key">
            <input
              v-model.number="localEntries[student.id].breakdown[col.key]"
              type="number"
              min="0"
              :max="col.max_marks"
              step="0.01"
              class="score-input"
              :class="componentInputClass(student.id, col)"
              :disabled="disabled"
              :title="componentTitle(col)"
              @input="onInput(student.id, col)"
              @blur="clampComponent(student.id, col)"
            />
          </td>
          <td class="col-total"><strong>{{ localEntries[student.id].total || 0 }}</strong><span class="total-denom"> / {{ totalMarks }}</span></td>
          <td v-if="perComponentPass">
            <span class="pass-fail-badge" :class="passFailClass(student.id)">
              {{ passFailLabel(student.id) }}
            </span>
          </td>
          <td>
            <span class="grade-badge" :class="gradeClass(student.id)">
              {{ gradeFor(student.id) }}
            </span>
          </td>
          <td v-if="showEvaluationStatus">
            <span class="eval-badge" :class="localEntries[student.id].evaluation_status">
              {{ evaluationLabel(localEntries[student.id].evaluation_status) }}
            </span>
          </td>
          <td>
            <input
              v-model="localEntries[student.id].remarks"
              class="remarks-input"
              placeholder="Optional"
              :disabled="disabled"
              @input="emitUpdate"
            />
          </td>
          <td v-if="perRowSave">
            <button
              type="button"
              class="btn-row-save"
              :disabled="rowSaveDisabled(student.id)"
              @click="emit('save-row', student)"
            >
              <span v-if="savingStudentId === student.id" class="spinner-xs"></span>
              <span v-else>Save</span>
            </button>
          </td>
        </tr>
      </tbody>
    </table>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import {
  getActiveComponents,
  sumBreakdown,
  createEmptyBreakdown,
  evaluationStatusLabel,
  usesPerComponentPass,
  formatPassCriteria,
  evaluateSubjectPass,
  componentMarkPasses,
} from '@/utils/markConfig.utils'
import { calculateResultGrade, getGradeCssClass } from '@/utils/grading.utils'

const props = defineProps({
  students: { type: Array, default: () => [] },
  markConfig: { type: Object, default: null },
  totalMarks: { type: Number, default: 100 },
  modelValue: { type: Object, default: () => ({}) },
  showEvaluationStatus: { type: Boolean, default: false },
  perRowSave: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  savingStudentId: { type: String, default: '' },
  rowSaveDisabled: { type: Function, default: () => false },
})

const emit = defineEmits(['update:modelValue', 'save-row'])

const columns = computed(() => getActiveComponents(props.markConfig))
const perComponentPass = computed(() => usesPerComponentPass(props.markConfig))
const passCriteriaText = computed(() => formatPassCriteria(props.markConfig))

const localEntries = reactive({})

function buildEntry(studentId, existing = {}) {
  const breakdown = {
    ...createEmptyBreakdown(columns.value),
    ...(existing.breakdown || {}),
  }

  return {
    breakdown,
    remarks: existing.remarks || '',
    total: sumBreakdown(breakdown, columns.value),
    evaluation_status: existing.evaluation_status || 'pending',
  }
}

function syncEntries() {
  const nextIds = new Set(props.students.map((s) => s.id))
  Object.keys(localEntries).forEach((id) => {
    if (!nextIds.has(id)) delete localEntries[id]
  })

  props.students.forEach((student) => {
    localEntries[student.id] = buildEntry(student.id, props.modelValue[student.id])
  })
}

watch([() => props.students, () => props.markConfig], syncEntries, { immediate: true, deep: true })

watch(() => props.modelValue, () => {
  props.students.forEach((student) => {
    if (!props.modelValue[student.id]) return
    localEntries[student.id] = buildEntry(student.id, props.modelValue[student.id])
  })
}, { deep: true })

function deriveEvalStatus(breakdown) {
  const cols = columns.value
  if (!cols.length) return 'pending'

  let filled = 0
  cols.forEach((col) => {
    const val = breakdown[col.key]
    if (val !== null && val !== '' && val !== undefined) filled++
  })

  if (filled === 0) return 'pending'
  if (filled === cols.length) return 'complete'
  return 'partial'
}

function clampValue(value, max) {
  if (value === null || value === '' || value === undefined) return value
  const num = Number(value)
  if (Number.isNaN(num)) return null
  return Math.min(Math.max(0, num), max)
}

function clampComponent(studentId, col) {
  const entry = localEntries[studentId]
  if (!entry) return
  entry.breakdown[col.key] = clampValue(entry.breakdown[col.key], col.max_marks)
  onInput(studentId, col)
}

function isOverMax(studentId, col) {
  const val = localEntries[studentId]?.breakdown[col.key]
  if (val === null || val === '' || val === undefined) return false
  return Number(val) > col.max_marks
}

function onInput(studentId, col) {
  const entry = localEntries[studentId]
  if (col?.max_marks != null) {
    entry.breakdown[col.key] = clampValue(entry.breakdown[col.key], col.max_marks)
  }
  entry.total = sumBreakdown(entry.breakdown, columns.value)
  entry.evaluation_status = deriveEvalStatus(entry.breakdown)
  emitUpdate()
}

function emitUpdate() {
  const payload = {}
  Object.entries(localEntries).forEach(([id, entry]) => {
    payload[id] = {
      breakdown: { ...entry.breakdown },
      remarks: entry.remarks,
      total: entry.total,
      evaluation_status: entry.evaluation_status,
    }
  })
  emit('update:modelValue', payload)
}

function resultFor(studentId) {
  const entry = localEntries[studentId]
  if (!entry) return { grade: '—', passed: null }
  return calculateResultGrade(entry.total || 0, props.totalMarks, entry.breakdown, props.markConfig)
}

function gradeFor(studentId) {
  const r = resultFor(studentId)
  if (!entryHasMarks(studentId)) return '—'
  return r.grade
}

function gradeClass(studentId) {
  if (!entryHasMarks(studentId)) return ''
  return getGradeCssClass(gradeFor(studentId))
}

function entryHasMarks(studentId) {
  const entry = localEntries[studentId]
  if (!entry) return false
  return (entry.total || 0) > 0 || Object.values(entry.breakdown || {}).some((v) => v !== null && v !== '' && v !== undefined)
}

function subjectPassState(studentId) {
  const entry = localEntries[studentId]
  if (!entry) return { passed: null }
  return evaluateSubjectPass(entry.breakdown, props.markConfig, entry.total)
}

function passFailLabel(studentId) {
  const { passed, evaluated } = subjectPassState(studentId)
  if (!entryHasMarks(studentId)) return '—'
  if (!evaluated || passed === null) return '…'
  return passed ? 'Pass' : 'Fail'
}

function passFailClass(studentId) {
  const label = passFailLabel(studentId)
  if (label === 'Pass') return 'pf-pass'
  if (label === 'Fail') return 'pf-fail'
  return 'pf-pending'
}

function componentInputClass(studentId, col) {
  const classes = []
  if (isOverMax(studentId, col)) classes.push('input-over')
  const entry = localEntries[studentId]
  if (!entry || !perComponentPass.value || !(col.pass_marks > 0)) return classes
  const passes = componentMarkPasses(entry.breakdown[col.key], col)
  if (passes === false) classes.push('input-below-pass')
  if (passes === true) classes.push('input-meets-pass')
  return classes
}

function componentTitle(col) {
  let t = `Maximum ${col.max_marks} for ${col.label}`
  if (col.pass_marks > 0) t += ` · Pass mark: ${col.pass_marks}`
  return t
}

function evaluationLabel(status) {
  return evaluationStatusLabel(status)
}
</script>

<style scoped>
.grid-hint {
  font-size: 0.82rem;
  color: var(--text-muted);
  margin: 0 0 0.85rem;
  padding: 0.55rem 0.75rem;
  background: var(--bg-surface-muted);
  border-radius: 8px;
  border: 1px solid var(--border-color);
}
.grid-hint--pass {
  background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
  border-color: #86efac;
  color: #166534;
}
.hint-icon { margin-right: 0.35rem; }
.grid-table-wrap {
  overflow-x: auto;
  border-radius: 10px;
  border: 1px solid var(--border-color);
}
.data-table { width: 100%; border-collapse: collapse; min-width: 640px; }
.data-table th {
  background: linear-gradient(180deg, #f8fafc, #f1f5f9);
  padding: 0.7rem 0.55rem;
  text-align: left;
  font-size: 0.68rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-secondary);
  border-bottom: 2px solid #e2e8f0;
}
.data-table td { padding: 0.6rem 0.5rem; font-size: 0.84rem; border-bottom: 1px solid var(--border-light); }
.data-table tbody tr:hover { background: #fafbff; }
.col-num { width: 2.5rem; color: var(--text-muted); font-weight: 700; }
.col-student { min-width: 140px; }
.col-score { min-width: 88px; }
.col-max { display: block; font-weight: 700; color: #2563eb; text-transform: none; font-size: 0.65rem; margin-top: 2px; }
.col-pass { display: block; font-weight: 700; color: #059669; text-transform: none; font-size: 0.62rem; margin-top: 1px; }
.col-total { white-space: nowrap; font-variant-numeric: tabular-nums; }
.total-denom { font-weight: 500; color: var(--text-muted); font-size: 0.78rem; }
.score-input {
  width: 76px;
  padding: 0.4rem 0.45rem;
  border: 1px solid var(--border-strong);
  border-radius: 8px;
  text-align: center;
  font-size: 0.82rem;
  font-weight: 600;
  transition: border-color 0.12s, box-shadow 0.12s;
}
.score-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }
.score-input.input-over { border-color: #dc2626; background: #fef2f2; }
.score-input.input-below-pass { border-color: #f87171; background: #fff1f2; }
.score-input.input-meets-pass { border-color: #34d399; background: #f0fdf4; }
.pass-fail-badge {
  display: inline-block;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.pass-fail-badge.pf-pass { background: #d1fae5; color: #047857; }
.pass-fail-badge.pf-fail { background: #fee2e2; color: #b91c1c; }
.pass-fail-badge.pf-pending { background: var(--bg-accent); color: var(--text-muted); }
.remarks-input { width: 130px; padding: 0.35rem 0.5rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.8rem; }
.grade-badge { padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
.grade-badge.a-plus { background: #d1fae5; color: #059669; }
.grade-badge.a { background: #dbeafe; color: #2563eb; }
.grade-badge.a-minus { background: #e0e7ff; color: #4f46e5; }
.grade-badge.b { background: #fef3c7; color: #d97706; }
.grade-badge.c { background: #fde68a; color: #b45309; }
.grade-badge.d { background: #fecaca; color: #dc2626; }
.grade-badge.f { background: #fee2e2; color: #b91c1c; }
.eval-badge { padding: 0.15rem 0.45rem; border-radius: 999px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; }
.eval-badge.complete { background: #d1fae5; color: #059669; }
.eval-badge.partial { background: #fef3c7; color: #b45309; }
.eval-badge.pending { background: #f3f4f6; color: var(--text-muted); }
.btn-row-save {
  padding: 0.3rem 0.55rem;
  font-size: 0.72rem;
  font-weight: 700;
  color: #4f46e5;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  border-radius: 6px;
  cursor: pointer;
  white-space: nowrap;
}
.btn-row-save:hover:not(:disabled) { background: #e0e7ff; }
.btn-row-save:disabled { opacity: 0.5; cursor: not-allowed; }
.spinner-xs {
  display: inline-block;
  width: 12px;
  height: 12px;
  border: 2px solid #c7d2fe;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  vertical-align: middle;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>

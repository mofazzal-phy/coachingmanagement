<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left"><h1>Fee Structures</h1><span class="badge-count">{{ displayItems.length }} total</span></div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadItems" :disabled="loading">🔄 Refresh</button>
        <button class="btn btn-primary" @click="openCreateDialog">+ Add Structure</button>
        <button class="btn btn-success" @click="openExamFeeDialog">📝 Create Exam Fee</button>
      </div>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading fee structures...</p></div>
    <div v-else-if="error" class="error-state"><p>⚠️ {{ error }}</p><button class="btn btn-outline" @click="loadItems">Try Again</button></div>
    <div v-else-if="displayItems.length === 0" class="empty-state">
      <div class="empty-icon">📋</div><h3>No Fee Structures Found</h3><p>Create fee structure for classes</p>
      <button class="btn btn-primary" @click="openCreateDialog">+ Add Structure</button>
    </div>
    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr><th>Class</th><th>Fee Type</th><th>Amount</th><th>Due Day / Date</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="row in displayItems" :key="row.key">
            <td><strong>{{ row.class?.name || '—' }}</strong></td>
            <td>
              <template v-if="row.type === 'exam_fee_group'">
                <span class="fee-type-cell fee-type-cell-stack">
                  <span class="cat-badge cat-event_based">Event</span>
                  <span class="fee-type-name">Exam Fee — {{ courseNames[row.course_id] || 'Course' }}</span>
                  <span class="exam-fee-meta">{{ row.examCount }} exam{{ row.examCount === 1 ? '' : 's' }}</span>
                </span>
              </template>
              <template v-else>
                <span v-if="row.fee_type" class="fee-type-cell">
                  <span class="cat-badge" :class="'cat-' + (row.fee_type.category || 'monthly')">
                    {{ categoryLabel(row.fee_type.category) }}
                  </span>
                  <span class="fee-type-name">{{ row.fee_type.name }}</span>
                </span>
                <span v-else>—</span>
              </template>
            </td>
            <td>
              <strong v-if="row.type === 'exam_fee_group'">{{ row.amountSummary }}</strong>
              <strong v-else>{{ formatCurrency(row.amount) }}</strong>
            </td>
            <td>
              <template v-if="row.due_date">{{ row.due_date }}</template>
              <template v-else-if="row.due_day">Day {{ row.due_day }}</template>
              <template v-else>—</template>
            </td>
            <td>
              <div class="action-buttons">
                <button
                  class="btn-icon"
                  title="Edit"
                  @click="row.type === 'exam_fee_group' ? openEditExamFeeGroup(row) : openEditDialog(row)"
                >✏️</button>
                <button class="btn-icon danger" title="Delete" @click="confirmDelete(row)">🗑️</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ====== CREATE/EDIT FEE STRUCTURE DIALOG ====== -->
    <div class="modal-overlay" v-if="showDialog" @click.self="closeDialog">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3>{{ editingItem ? 'Edit Fee Structure' : 'Create Fee Structure' }}</h3>
          <button class="modal-close" @click="closeDialog">✕</button>
        </div>
        <div class="modal-body">
          <div v-if="dialogError" class="alert alert-danger">{{ dialogError }}</div>
          <div class="form-row">
            <div class="form-group">
              <label>Class <span class="required">*</span></label>
              <select v-model="form.class_id" class="form-control">
                <option value="">Select class...</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Fee Type <span class="required">*</span></label>
              <select v-model="form.fee_type_id" class="form-control">
                <option value="">Select fee type...</option>
                <option v-for="f in feeTypes" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Amount <span class="required">*</span></label>
              <input v-model.number="form.amount" type="number" min="0" class="form-control" placeholder="0.00" />
            </div>
            <div class="form-group" v-if="selectedFeeTypeCategory === 'monthly'">
              <label>Due Day of Month</label>
              <input v-model.number="form.due_day" type="number" min="1" max="31" class="form-control" placeholder="e.g., 10" />
            </div>
          </div>
          <div class="form-row" v-if="selectedFeeTypeCategory === 'event_based'">
            <div class="form-group">
              <label>Due Date (Deadline) <span class="required">*</span></label>
              <input v-model="form.due_date" type="date" class="form-control" />
            </div>
            <div class="form-group">
              <label>Event Date (Optional)</label>
              <input v-model="form.event_date" type="date" class="form-control" />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="closeDialog">Cancel</button>
          <button class="btn btn-primary" @click="saveItem" :disabled="!form.class_id || !form.fee_type_id || !form.amount || dialogLoading">
            {{ dialogLoading ? 'Saving...' : (editingItem ? 'Update' : 'Create') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ====== EXAM FEE CREATION DIALOG ====== -->
    <div class="modal-overlay" v-if="showExamFeeDialog" @click.self="closeExamFeeDialog">
      <div class="modal-dialog modal-xl">
        <div class="modal-header">
          <h3>📝 {{ editingExamFeeGroup ? 'Edit Exam Fee' : 'Create Exam Fee' }}</h3>
          <button class="modal-close" @click="closeExamFeeDialog">✕</button>
        </div>
        <div class="modal-body">
          <div v-if="examFeeError" class="alert alert-danger">{{ examFeeError }}</div>
          <div v-if="examFeeSuccess" class="alert alert-success">{{ examFeeSuccess }}</div>

          <div class="form-row form-row-three">
            <div class="form-group">
              <label>Academic Session <span class="required">*</span></label>
              <select v-model="examFeeForm.academic_session_id" class="form-control" @change="onExamFeeSessionChange" :disabled="!!editingExamFeeGroup">
                <option value="">Select session...</option>
                <option v-for="s in academicSessions" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Class <span class="required">*</span></label>
              <select v-model="examFeeForm.class_id" class="form-control" @change="onExamFeeClassChange" :disabled="!examFeeForm.academic_session_id || !!editingExamFeeGroup">
                <option value="">Select class...</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Course <span class="required">*</span></label>
              <select v-model="examFeeForm.course_id" class="form-control" @change="onExamFeeCourseChange" :disabled="!examFeeForm.class_id || !!editingExamFeeGroup">
                <option value="">{{ examFeeForm.class_id ? (examFeeCourses.length ? 'Select course...' : 'No courses') : 'Select class first' }}</option>
                <option v-for="c in examFeeCourses" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
          </div>

          <div class="exam-list-section">
            <div class="exam-list-head">
              <h4>All exams — check applicable ones for this course</h4>
              <div v-if="courseExamRows.length" class="bulk-tools">
                <label class="check-row">
                  <input type="checkbox" v-model="selectAllExams" @change="toggleAllExams" />
                  All
                </label>
                <input v-model.number="bulkExamAmount" type="number" min="0" class="form-control small-input" placeholder="৳ all" />
                <button type="button" class="btn btn-sm btn-outline" @click="applyBulkExamAmount">Apply</button>
              </div>
            </div>

            <div v-if="!examFeeForm.academic_session_id || !examFeeForm.class_id || !examFeeForm.course_id" class="exam-list-placeholder">
              Select session, class and course first.
            </div>
            <div v-else-if="courseExamsLoading" class="exam-list-placeholder">Loading all exams...</div>
            <div v-else-if="courseExamRows.length" class="exam-list">
              <div v-for="exam in courseExamRows" :key="exam.exam_id" class="exam-list-row">
                <input type="checkbox" v-model="exam.enabled" class="exam-check" />
                <div class="exam-list-info">
                  <span class="exam-list-name">{{ exam.name }}</span>
                </div>
                <div class="exam-list-amount">
                  <span class="amount-label">৳</span>
                  <input
                    v-model.number="exam.amount"
                    type="number"
                    min="0"
                    class="form-control amount-input"
                    placeholder="0"
                    :disabled="!exam.enabled"
                  />
                </div>
              </div>
            </div>
            <p v-else class="exam-list-placeholder warn">No exams in this session. Create exams from the Exam module first.</p>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Fee Type <span class="required">*</span></label>
              <select v-model="examFeeForm.fee_type_id" class="form-control">
                <option value="">Select...</option>
                <option v-for="f in examFeeTypes" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Due Date <span class="required">*</span></label>
              <input v-model="examFeeForm.due_date" type="date" class="form-control" />
            </div>
          </div>
          <p class="modal-hint">Checked exams apply to <strong>all batches</strong> of this course. Notify students from Exam Routine → <strong>Exam fee required</strong>.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="closeExamFeeDialog">Cancel</button>
          <button class="btn btn-success" @click="createExamFee" :disabled="!canSaveExamFees || examFeeLoading">
            <template v-if="examFeeLoading"><span class="spinner-sm"></span> Saving...</template>
            <template v-else>💾 {{ editingExamFeeGroup ? 'Update Exam Fees' : 'Save Exam Fees' }}</template>
          </button>
        </div>
      </div>
    </div>

    <!-- ====== DELETE CONFIRM DIALOG ====== -->
    <div class="modal-overlay" v-if="showDeleteDialog" @click.self="showDeleteDialog = false">
      <div class="modal-dialog modal-sm">
        <div class="modal-header"><h3>Confirm Delete</h3><button class="modal-close" @click="showDeleteDialog = false">✕</button></div>
        <div class="modal-body">
          <p v-if="selectedItem?.type === 'exam_fee_group'">
            Delete all exam fees for <strong>{{ courseNames[selectedItem.course_id] || 'this course' }}</strong>?
            ({{ selectedItem.examCount }} exam{{ selectedItem.examCount === 1 ? '' : 's' }})
          </p>
          <p v-else>Delete this fee structure?</p>
          <p class="text-danger">This cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showDeleteDialog = false">Cancel</button>
          <button class="btn btn-danger" @click="deleteItem" :disabled="deleteLoading">{{ deleteLoading ? 'Deleting...' : 'Delete' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import financeService from '@/services/finance.service'
import academicService from '@/services/academic.service'
import smartFeeService from '@/services/smart-fee.service'
import examService from '@/services/exam.service'
import enrollmentService from '@/services/enrollment.service'
import { extractData } from '@/utils/api.utils'

const categoryLabel = (cat) => {
  const labels = { one_time: 'One-Time', monthly: 'Monthly', event_based: 'Event' }
  return labels[cat] || cat || 'Monthly'
}

// ====== Fee Structure CRUD ======
const rawItems = ref([])
const courseNames = ref({})
const classes = ref([])
const feeTypes = ref([])
const loading = ref(false)
const error = ref(null)
const showDialog = ref(false)
const editingItem = ref(null)
const dialogLoading = ref(false)
const dialogError = ref(null)
const form = ref({ class_id: '', fee_type_id: '', amount: 0, due_day: '', due_date: '', event_date: '' })
const showDeleteDialog = ref(false)
const selectedItem = ref(null)
const deleteLoading = ref(false)

// ====== Exam Fee Creation ======
const showExamFeeDialog = ref(false)
const examFeeLoading = ref(false)
const examFeeError = ref(null)
const examFeeSuccess = ref(null)
const examFeeCourses = ref([])
const courseExamRows = ref([])
const courseExamsLoading = ref(false)
const selectAllExams = ref(false)
const bulkExamAmount = ref(null)
const savedExamFeeStructures = ref([])
const editingExamFeeGroup = ref(null)
const examFeeForm = ref({
  academic_session_id: '',
  class_id: '',
  course_id: '',
  fee_type_id: '',
  due_date: '',
})

const examFeeTypes = computed(() => feeTypes.value.filter(f => f.category === 'event_based'))

const canSaveExamFees = computed(() => {
  if (!examFeeForm.value.academic_session_id || !examFeeForm.value.class_id || !examFeeForm.value.course_id || !examFeeForm.value.fee_type_id || !examFeeForm.value.due_date) {
    return false
  }
  return courseExamRows.value.some(e => e.enabled && Number(e.amount) > 0)
})

const summarizeExamFeeAmounts = (structures) => {
  const amounts = structures.map(s => Number(s.amount)).filter(a => a > 0)
  if (!amounts.length) return '—'
  const min = Math.min(...amounts)
  const max = Math.max(...amounts)
  if (min === max) return formatCurrency(min)
  return `${formatCurrency(min)} – ${formatCurrency(max)}`
}

const displayItems = computed(() => {
  const regular = []
  const examGroups = new Map()

  for (const item of rawItems.value) {
    if (item.exam_id && item.course_id) {
      const key = `exam-${item.academic_session_id}-${item.class_id}-${item.course_id}`
      if (!examGroups.has(key)) {
        examGroups.set(key, {
          type: 'exam_fee_group',
          key,
          academic_session_id: item.academic_session_id,
          class_id: item.class_id,
          course_id: item.course_id,
          class: item.class,
          fee_type: item.fee_type,
          due_date: item.due_date,
          structures: [],
        })
      }
      examGroups.get(key).structures.push(item)
    } else {
      regular.push({ ...item, type: 'regular', key: `regular-${item.id}` })
    }
  }

  const grouped = [...examGroups.values()].map(g => ({
    ...g,
    examCount: g.structures.length,
    amountSummary: summarizeExamFeeAmounts(g.structures),
  }))

  return [...regular, ...grouped]
})

const loadCourseNamesForItems = async (rows) => {
  const classIds = [...new Set(rows.filter(r => r.exam_id && r.course_id && r.class_id).map(r => r.class_id))]
  if (!classIds.length) return
  const map = { ...courseNames.value }
  await Promise.all(classIds.map(async (classId) => {
    try {
      const res = await enrollmentService.getCourses({ class_id: classId })
      const courses = res.data?.data || []
      courses.forEach(c => { map[c.id] = c.name })
    } catch {}
  }))
  courseNames.value = map
}

const loadItems = async () => {
  loading.value = true; error.value = null
  try {
    const res = await financeService.feeStructures.list({ per_page: 500 })
    rawItems.value = res.data.data || []
    await loadCourseNamesForItems(rawItems.value)
  }
  catch (err) { error.value = err.response?.data?.message || 'Failed to load' }
  finally { loading.value = false }
}

const loadDependencies = async () => {
  try {
    const [classesRes, feeTypesRes] = await Promise.all([
      academicService.classes.list(), financeService.feeTypes.list()
    ])
    classes.value = classesRes.data.data || []
    feeTypes.value = feeTypesRes.data.data || []
  } catch {}
}

const formatCurrency = (amount) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'BDT', minimumFractionDigits: 0 }).format(amount || 0)

const selectedFeeTypeCategory = computed(() => {
  if (!form.value.fee_type_id) return null
  const feeType = feeTypes.value.find(f => f.id === form.value.fee_type_id)
  return feeType?.category || null
})

const openCreateDialog = () => { editingItem.value = null; form.value = { class_id: '', fee_type_id: '', amount: 0, due_day: '', due_date: '', event_date: '' }; dialogError.value = null; showDialog.value = true }
const openEditDialog = (item) => { editingItem.value = item; form.value = { class_id: item.class_id || '', fee_type_id: item.fee_type_id || '', amount: item.amount || 0, due_day: item.due_day || '', due_date: item.due_date || '', event_date: item.event_date || '' }; dialogError.value = null; showDialog.value = true }
const closeDialog = () => { showDialog.value = false; editingItem.value = null }

const saveItem = async () => {
  dialogLoading.value = true; dialogError.value = null
  try {
    if (editingItem.value) await financeService.feeStructures.update(editingItem.value.id, form.value)
    else await financeService.feeStructures.create(form.value)
    closeDialog(); loadItems()
  } catch (err) { dialogError.value = err.response?.data?.message || 'Failed to save' }
  finally { dialogLoading.value = false }
}

const confirmDelete = (item) => { selectedItem.value = item; showDeleteDialog.value = true }
const deleteItem = async () => {
  deleteLoading.value = true
  try {
    const item = selectedItem.value
    if (item?.type === 'exam_fee_group') {
      await Promise.all(item.structures.map(s => financeService.feeStructures.delete(s.id)))
    } else {
      await financeService.feeStructures.delete(item.id)
    }
    showDeleteDialog.value = false
    loadItems()
  }
  catch (err) { error.value = err.response?.data?.message || 'Failed to delete'; showDeleteDialog.value = false }
  finally { deleteLoading.value = false }
}

// ====== Exam Fee Methods ======
const loadSavedExamFeeStructures = async () => {
  try {
    const res = await financeService.feeStructures.list({ per_page: 500 })
    const rows = res.data?.data || []
    savedExamFeeStructures.value = rows.filter(s => s.exam_id && s.course_id)
  } catch {
    savedExamFeeStructures.value = []
  }
}

const openExamFeeDialog = async () => {
  editingExamFeeGroup.value = null
  const currentSession = academicSessions.value.find(s => s.is_current)
  examFeeForm.value = {
    academic_session_id: currentSession?.id || '',
    class_id: '',
    course_id: '',
    fee_type_id: '',
    due_date: '',
  }
  examFeeError.value = null
  examFeeSuccess.value = null
  examFeeCourses.value = []
  courseExamRows.value = []
  selectAllExams.value = false
  bulkExamAmount.value = null
  showExamFeeDialog.value = true
  await loadSavedExamFeeStructures()
}

const openEditExamFeeGroup = async (group) => {
  editingExamFeeGroup.value = group
  const first = group.structures[0] || {}
  examFeeForm.value = {
    academic_session_id: group.academic_session_id,
    class_id: group.class_id,
    course_id: group.course_id,
    fee_type_id: first.fee_type_id || group.fee_type?.id || '',
    due_date: first.due_date || group.due_date || '',
  }
  examFeeError.value = null
  examFeeSuccess.value = null
  selectAllExams.value = false
  bulkExamAmount.value = null
  showExamFeeDialog.value = true
  await loadSavedExamFeeStructures()
  try {
    const res = await enrollmentService.getCourses({ class_id: group.class_id })
    examFeeCourses.value = res.data?.data || []
    examFeeCourses.value.forEach(c => { courseNames.value[c.id] = c.name })
  } catch {
    examFeeCourses.value = []
  }
  await loadAllSessionExams()
}

const closeExamFeeDialog = () => {
  showExamFeeDialog.value = false
  editingExamFeeGroup.value = null
  examFeeError.value = null
  examFeeSuccess.value = null
}

const onExamFeeSessionChange = () => {
  examFeeForm.value.class_id = ''
  examFeeForm.value.course_id = ''
  examFeeCourses.value = []
  courseExamRows.value = []
}

const onExamFeeClassChange = async () => {
  examFeeForm.value.course_id = ''
  courseExamRows.value = []
  examFeeCourses.value = []
  if (!examFeeForm.value.class_id) return
  try {
    const res = await enrollmentService.getCourses({ class_id: examFeeForm.value.class_id })
    examFeeCourses.value = res.data?.data || []
  } catch {
    examFeeCourses.value = []
  }
}

const parseExamList = (res) => {
  const payload = extractData(res, [])
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  return []
}

const loadAllSessionExams = async () => {
  const { course_id, class_id, academic_session_id } = examFeeForm.value
  if (!course_id || !class_id || !academic_session_id) {
    courseExamRows.value = []
    return
  }
  courseExamsLoading.value = true
  examFeeError.value = null
  try {
    const seen = new Set()
    const merged = []
    const addExam = (exam) => {
      if (!exam?.id || seen.has(exam.id)) return
      seen.add(exam.id)
      merged.push(exam)
    }

    // All exams in session (not filtered by course)
    const sessionRes = await examService.exams.list({
      academic_session_id,
      per_page: 500,
    })
    parseExamList(sessionRes).forEach(addExam)

    if (merged.length === 0) {
      const allRes = await examService.exams.list({ per_page: 500 })
      parseExamList(allRes)
        .filter(e => !e.academic_session_id || e.academic_session_id === academic_session_id)
        .forEach(addExam)
    }

    if (merged.length === 0 && class_id) {
      try {
        const classRes = await examService.exams.byClass(class_id)
        parseExamList(classRes)
          .filter(e => !e.academic_session_id || e.academic_session_id === academic_session_id)
          .forEach(addExam)
      } catch {}
    }

    merged.sort((a, b) => String(a.start_date || a.name || '').localeCompare(String(b.start_date || b.name || '')))

    courseExamRows.value = merged.map(exam => {
      const saved = savedExamFeeStructures.value.find(s =>
        s.exam_id === exam.id
        && s.course_id === course_id
        && s.class_id === class_id
      )
      return {
        exam_id: exam.id,
        name: exam.name,
        amount: saved ? Number(saved.amount) : 0,
        enabled: !!(saved && Number(saved.amount) > 0),
      }
    })
    selectAllExams.value = courseExamRows.value.length > 0 && courseExamRows.value.every(e => e.enabled)
  } catch (err) {
    examFeeError.value = err.response?.data?.message || 'Failed to load exams'
    courseExamRows.value = []
  } finally {
    courseExamsLoading.value = false
  }
}

const onExamFeeCourseChange = async () => {
  await loadAllSessionExams()
}

const toggleAllExams = () => {
  courseExamRows.value.forEach(e => { e.enabled = selectAllExams.value })
}

const applyBulkExamAmount = () => {
  const amt = Number(bulkExamAmount.value)
  if (!amt || amt <= 0) return
  courseExamRows.value.forEach(e => {
    if (e.enabled) e.amount = amt
  })
}

const createExamFee = async () => {
  examFeeLoading.value = true
  examFeeError.value = null
  examFeeSuccess.value = null
  try {
    const items = courseExamRows.value
      .filter(e => e.enabled && Number(e.amount) > 0)
      .map(e => ({
        exam_id: e.exam_id,
        class_id: examFeeForm.value.class_id,
        course_id: examFeeForm.value.course_id,
        amount: Number(e.amount),
        enabled: true,
        title: `${e.name} Fee`,
      }))

    const previouslySaved = editingExamFeeGroup.value
      ? editingExamFeeGroup.value.structures
      : savedExamFeeStructures.value.filter(s =>
        s.class_id === examFeeForm.value.class_id
        && s.course_id === examFeeForm.value.course_id
        && s.academic_session_id === examFeeForm.value.academic_session_id
      )
    const enabledExamIds = new Set(items.map(i => i.exam_id))
    const toDelete = previouslySaved.filter(s => !enabledExamIds.has(s.exam_id))
    await Promise.all(toDelete.map(s => financeService.feeStructures.delete(s.id)))

    if (items.length === 0) {
      examFeeSuccess.value = '✅ Exam fees removed for this course.'
      loadItems()
      setTimeout(() => closeExamFeeDialog(), 1200)
      return
    }

    const res = await smartFeeService.admin.bulkCreateExamFee({
      academic_session_id: examFeeForm.value.academic_session_id,
      fee_type_id: examFeeForm.value.fee_type_id,
      due_date: examFeeForm.value.due_date,
      items,
    })
    const data = res.data?.data || res.data
    examFeeSuccess.value = `✅ ${data.message || 'Exam fees saved!'}`
    loadItems()
    setTimeout(() => closeExamFeeDialog(), 1200)
  } catch (err) {
    examFeeError.value = err.response?.data?.message || 'Failed to save exam fees'
  } finally {
    examFeeLoading.value = false
  }
}

// ====== Academic Sessions ======
const academicSessions = ref([])

const loadAcademicSessions = async () => {
  try {
    const res = await academicService.sessions.list({ per_page: 50 })
    academicSessions.value = res.data?.data || []
  } catch {}
}

onMounted(() => { loadItems(); loadDependencies(); loadAcademicSessions() })
</script>

<style scoped>
.page-container { max-width: 1000px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem; }
.header-left { display: flex; align-items: center; gap: 0.75rem; }
.header-left h1 { font-size: 1.5rem; color: var(--text-primary); margin: 0; }
.badge-count { background: #e8eaf6; color: #4f46e5; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.header-actions { display: flex; gap: 0.5rem; }
.btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; gap: 0.4rem; }
.btn-primary { background: #4f46e5; color: white; }
.btn-primary:hover { background: #4338ca; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-success { background: #10b981; color: white; }
.btn-success:hover { background: #059669; }
.btn-success:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-outline { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-color); }
.btn-outline:hover { background: var(--bg-accent); border-color: var(--text-muted); }
.btn-danger { background: #ef4444; color: white; }
.btn-danger:hover { background: #dc2626; }
.loading-state { text-align: center; padding: 3rem; color: var(--text-muted); }
.spinner { width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
.spinner-sm { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.error-state { text-align: center; padding: 2rem; background: #fef2f2; border-radius: 12px; color: #dc2626; }
.empty-state { text-align: center; padding: 3rem; background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); }
.empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.empty-state h3 { color: var(--text-primary); margin: 0 0 0.5rem; }
.empty-state p { color: var(--text-muted); margin: 0 0 1.25rem; }
.table-container { background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { background: var(--bg-accent); padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); border-bottom: 1px solid var(--border-color); }
.data-table td { padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-light); }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: var(--bg-accent); }
.action-buttons { display: flex; gap: 0.25rem; }
.fee-type-cell { display: inline-flex; align-items: center; gap: 0.4rem; }
.fee-type-cell-stack { flex-direction: column; align-items: flex-start; gap: 0.2rem; }
.fee-type-name { font-size: 0.85rem; color: var(--text-secondary); }
.exam-fee-meta { font-size: 0.72rem; color: var(--text-muted); font-weight: 500; }
.cat-badge { display: inline-flex; align-items: center; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; width: fit-content; }
.cat-badge.cat-one_time { background: #dbeafe; color: #1e40af; }
.cat-badge.cat-monthly { background: #d1fae5; color: #065f46; }
.cat-badge.cat-event_based { background: #fef3c7; color: #92400e; }
.btn-icon { width: 32px; height: 32px; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.btn-icon:hover { background: #f3f4f6; }
.btn-icon.danger:hover { background: #fef2f2; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(2px); }
.modal-dialog { background: var(--bg-card); border-radius: 16px; width: 90%; max-width: 520px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
.modal-lg { max-width: 640px; }
.modal-xl { max-width: 860px; }
.exam-list-section { margin: 1rem 0; border: 1px solid var(--border-color); border-radius: 10px; overflow: hidden; background: var(--bg-card); }
.exam-list-head { display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0.85rem; background: var(--bg-accent); border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 0.5rem; }
.exam-list-head h4 { margin: 0; font-size: 0.85rem; color: var(--text-secondary); font-weight: 700; }
.bulk-tools { display: flex; align-items: center; gap: 0.45rem; }
.check-row { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.78rem; font-weight: 600; color: var(--text-secondary); }
.small-input { width: 90px; padding: 0.35rem 0.5rem; font-size: 0.8rem; }
.exam-list-placeholder { padding: 1.1rem; text-align: center; font-size: 0.82rem; color: var(--text-muted); }
.exam-list-placeholder.warn { color: #b45309; }
.exam-list { max-height: 320px; overflow-y: auto; }
.exam-list-row {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 0.85rem;
  border-bottom: 1px solid var(--border-light);
}
.exam-list-row:last-child { border-bottom: none; }
.exam-list-row:hover { background: #fafafa; }
.exam-check { width: 16px; height: 16px; cursor: pointer; }
.exam-list-info { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; }
.exam-list-name { font-size: 0.88rem; font-weight: 600; color: var(--text-primary); }
.exam-list-amount { display: flex; align-items: center; gap: 0.25rem; }
.amount-label { font-size: 0.85rem; font-weight: 700; color: var(--text-secondary); }
.amount-input { width: 110px; padding: 0.45rem 0.55rem; text-align: right; }
.modal-hint { margin: 0.5rem 0 0; font-size: 0.75rem; color: var(--text-muted); }
.form-row-three { grid-template-columns: repeat(3, 1fr); }
@media (max-width: 720px) { .form-row-three { grid-template-columns: 1fr; } }
.modal-sm { max-width: 400px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); }
.modal-header h3 { margin: 0; font-size: 1.1rem; color: var(--text-primary); }
.modal-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted); padding: 0.25rem; }
.modal-close:hover { color: var(--text-secondary); }
.modal-body { padding: 1.5rem; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem; }
.required { color: #ef4444; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-control { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: var(--bg-card); box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
textarea.form-control { resize: vertical; min-height: 60px; }
.alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; }
.alert-danger { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.text-danger { color: #dc2626; font-size: 0.85rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); }
.info-box { display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem 1rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; margin-top: 0.5rem; }
.info-icon { font-size: 1.1rem; flex-shrink: 0; }
.info-text { font-size: 0.8rem; color: #1e40af; line-height: 1.5; }
.info-text strong { color: #1e3a8a; }
</style>

<template>
  <div class="wizard-page">
    <div class="wizard-header">
      <router-link to="/dashboard/exams" class="back-link">← Back to Exams</router-link>
      <h1>Exam Setup Wizard</h1>
      <p class="subtitle">Create an exam in 6 guided steps</p>
    </div>

    <div class="stepper">
      <div
        v-for="(label, i) in stepLabels"
        :key="i"
        class="step"
        :class="{ active: step === i, done: step > i }"
        @click="i < step && (step = i)"
      >
        <span class="num">{{ i + 1 }}</span>
        <span class="label">{{ label }}</span>
      </div>
    </div>

    <div v-if="scheduleSlotLabel" class="slot-prefill-banner">
      <strong>Grid slot:</strong> {{ scheduleSlotLabel }}
      <span class="slot-prefill-hint">— date &amp; time below stay pre-filled through all steps</span>
    </div>

    <div class="message-slot" :class="{ 'has-message': !!(error || successMsg) }">
      <div v-if="error" class="alert alert-danger">{{ error }}</div>
      <div v-if="successMsg" class="alert alert-success">{{ successMsg }}</div>
    </div>

    <!-- Step 1: Exam Info -->
    <div v-if="step === 0" class="step-card">
      <h2>Step 1 — Exam Information</h2>
      <p class="direction">Give the exam a name (Daily/Weekly/Model…) and choose paper format (MCQ, CQ, or both).</p>
      <div class="form-row">
        <div class="form-group">
          <label>Select Exam *</label>
          <select v-model="selectedExamId" class="form-control" @change="onWizardExamPick">
            <option value="">+ Create new exam</option>
            <option v-for="e in existingExams" :key="e.id" :value="e.id">
              {{ e.name }}
            </option>
          </select>
          <p class="field-hint">Same list as Exams page — pick an existing exam or create a new one below.</p>
        </div>
        <div class="form-group">
          <label>Paper Format *</label>
          <select v-model="form.exam_type_id" class="form-control">
            <option value="">Select format</option>
            <option v-for="t in activeExamTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>
      </div>
      <div v-if="!selectedExamId" class="form-row">
        <div class="form-group">
          <label>New Exam Name *</label>
          <input v-model="form.name" class="form-control" placeholder="e.g. Model Test, Monthly Exam" />
        </div>
      </div>
      <div v-else class="selected-exam-banner">
        Configuring: <strong>{{ form.name }}</strong>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Academic Session *</label>
          <select v-model="form.academic_session_id" class="form-control">
            <option value="">Select session</option>
            <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}{{ s.is_current ? ' (Current)' : '' }}</option>
          </select>
          <p class="field-hint">Auto-selected from current session; batch in Step 2 may override</p>
        </div>
        <div class="form-group">
          <label>Delivery Mode</label>
          <select
            v-model="form.delivery_mode"
            class="form-control"
            :disabled="!!routeDeliveryMode"
            @change="onDeliveryModeChange"
          >
            <option value="offline">Offline</option>
            <option value="online">Online</option>
            <option value="hybrid">Hybrid</option>
          </select>
          <p v-if="routeDeliveryMode" class="field-hint">
            Locked to <strong>{{ routeDeliveryMode }}</strong> from routine grid — offline and online schedules are separate.
          </p>
        </div>
      </div>
      <div v-if="scheduleSlotLabel" class="form-row">
        <div class="form-group">
          <label>Grid Slot Date *</label>
          <input v-model="form.start_date" type="date" class="form-control" />
          <p class="field-hint">Routine will be scheduled on this date (exam's overall dates unchanged).</p>
        </div>
        <div class="form-group">
          <label>Slot Start Time *</label>
          <input v-model="genForm.start_time" type="time" class="form-control" @input="markScheduleTimesEdited" />
          <p class="field-hint">Pre-filled from grid — change if needed.</p>
        </div>
        <div class="form-group">
          <label>Slot End Time *</label>
          <input v-model="genForm.end_time" type="time" class="form-control" @input="markScheduleTimesEdited" />
        </div>
      </div>
      <template v-else-if="routeGridScheduleDate && route.query.from === 'grid'">
        <p class="slot-step-hint">
          Routine date from grid: <strong>{{ gridScheduleDateLabel }}</strong>
          — fields below are the exam’s overall period only (not the routine slot date).
        </p>
        <div class="form-row">
          <div class="form-group">
            <label>Exam Period Start *</label>
            <input v-model="form.start_date" type="date" class="form-control" />
            <p class="field-hint">Routine stays on {{ gridScheduleDateLabel }} regardless of this.</p>
          </div>
          <div class="form-group">
            <label>Exam Period End *</label>
            <input v-model="form.end_date" type="date" class="form-control" />
          </div>
        </div>
      </template>
      <div v-else class="form-row">
        <div class="form-group">
          <label>Start Date *</label>
          <input v-model="form.start_date" type="date" class="form-control" />
        </div>
        <div class="form-group">
          <label>End Date *</label>
          <input v-model="form.end_date" type="date" class="form-control" />
        </div>
      </div>
      <label class="check-row">
        <input type="checkbox" v-model="form.is_practice" /> This is a practice exam (no official results)
      </label>
      <label class="check-row" v-if="!form.is_practice">
        <input type="checkbox" v-model="form.eligibility_check_enabled" />
        Check attendance before admit card download
      </label>
      <div v-if="form.eligibility_check_enabled && !form.is_practice" class="form-row">
        <div class="form-group">
          <label>Minimum attendance % (optional)</label>
          <input v-model.number="form.min_attendance_percent" type="number" min="0" max="100" step="0.1" class="form-control" placeholder="Default: 75%" />
        </div>
      </div>
      <div class="step-actions">
        <button class="btn btn-primary" :disabled="!canStep1 || saving" @click="saveStep1">
          {{ saving ? 'Saving...' : 'Continue →' }}
        </button>
      </div>
    </div>

    <!-- Step 2: Class / Course / Batch -->
    <div v-if="step === 1" class="step-card">
      <h2>Step 2 — Class, Course & Batch</h2>
      <p class="direction">Select class first, then course and batch. Session updates automatically from the batch.</p>
      <div class="form-row">
        <div class="form-group">
          <label>Class *</label>
          <select v-model="form.class_id" class="form-control" @change="onClassChange">
            <option value="">Select class</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Course *</label>
          <select v-model="form.course_id" class="form-control" @change="onCourseChangeWizard" :disabled="!form.class_id">
            <option value="">Select course</option>
            <option v-for="c in filteredCourses" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Batch *</label>
        <select v-model="form.batch_id" class="form-control" @change="onBatchChangeWizard" :disabled="!form.course_id">
          <option value="">Select batch</option>
          <option v-for="b in filteredBatches" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
      </div>
      <div class="step-actions">
        <button class="btn btn-outline" @click="step = 0">← Back</button>
        <button class="btn btn-primary" :disabled="!canStep2 || saving" @click="saveStep2">Continue →</button>
      </div>
    </div>

    <!-- Step 3: Generate Routines -->
    <div v-if="step === 2" class="step-card">
      <h2>Step 3 — Subject Routines</h2>
      <p class="hint">Check subjects, preview the schedule, then continue to Marks to <strong>save as draft</strong>. Nothing is published until Step 6.</p>

      <div class="form-group">
        <label>Subjects *</label>
        <div v-if="subjectsLoading" class="empty">Loading subjects...</div>
        <div v-else-if="subjects.length === 0" class="empty warn">
          No subjects found for this course. Go back and check Class → Course → Batch.
        </div>
        <template v-else>
          <p class="field-hint subject-count-hint">
            {{ selectedSubjectIds.length }} of {{ subjects.length }} selected on {{ targetScheduleDate || 'chosen date' }}.
            <template v-if="scheduledSubjectIdsOnTargetDate.length">
              {{ scheduledSubjectIdsOnTargetDate.length }} subject(s) already have a slot this date — another time is allowed if it does not overlap.
            </template>
          </p>
          <div class="subject-list">
            <label class="subject-check all">
              <input type="checkbox" :checked="allSubjectsSelected" @change="toggleAllSubjects" />
              Select all ({{ subjects.length }})
            </label>
            <label v-for="s in subjects" :key="s.id" class="subject-check">
              <input type="checkbox" :value="s.id" v-model="selectedSubjectIds" />
              {{ s.name }} <span v-if="s.code" class="code">{{ s.code }}</span>
              <span v-if="scheduledSubjectIdsOnTargetDate.includes(normId(s.id))" class="scheduled-badge">Has slot this date</span>
              <span v-else-if="scheduledSubjectIds.includes(normId(s.id))" class="scheduled-badge other-date">Other date</span>
            </label>
          </div>
        </template>
      </div>

      <p v-if="gridScheduleDateLabel" class="slot-step-hint">Schedule date from grid: <strong>{{ gridScheduleDateLabel }}</strong></p>
      <p v-else-if="scheduleSlotLabel" class="slot-step-hint">Pre-filled from grid: <strong>{{ scheduleSlotLabel }}</strong></p>
      <p v-if="selectedSubjectIds.length > 1 && !scheduleSlotLabel" class="field-hint">
        {{ selectedSubjectIds.length }} subjects need {{ selectedSubjectIds.length }} slot(s).
        You have ~{{ countAvailableScheduleDates() * estimateSlotsPerDay() }} slot(s)
        ({{ countAvailableScheduleDates() }} day(s) × {{ estimateSlotsPerDay() }}/day).
        Extend End Date or reduce Slot Duration if needed.
      </p>
      <p v-if="scheduleSlotLabel && selectedSubjectIds.length > 1" class="field-hint">
        Multiple subjects schedule back-to-back after any existing slots on this date ({{ genForm.gap_minutes || 0 }} min gap).
      </p>

      <div class="form-row">
        <div class="form-group">
          <label>Start Date *</label>
          <input v-model="genForm.start_date" type="date" class="form-control" />
        </div>
        <div class="form-group">
          <label>End Date *</label>
          <input v-model="genForm.end_date" type="date" class="form-control" />
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>{{ scheduleSlotLabel ? 'Slot Start Time *' : 'Daily Start Time *' }}</label>
          <input v-model="genForm.start_time" type="time" class="form-control" @input="markScheduleTimesEdited" />
        </div>
        <div class="form-group">
          <label>{{ scheduleSlotLabel ? 'Slot End Time *' : 'Daily End Limit' }}</label>
          <input
            v-if="scheduleSlotLabel"
            v-model="genForm.end_time"
            type="time"
            class="form-control"
            @input="markScheduleTimesEdited"
          />
          <input
            v-else
            v-model="genForm.end_time_limit"
            type="time"
            class="form-control"
            @input="markScheduleTimesEdited"
          />
          <p v-if="scheduleSlotLabel" class="field-hint">Adjust end time or slot duration — both stay in sync.</p>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Slot Duration (minutes) *</label>
          <input
            v-model.number="genForm.slot_duration"
            type="number"
            min="1"
            max="1440"
            step="1"
            class="form-control"
            placeholder="Minutes — e.g. 45, 90, 120, 180"
          />
          <p class="field-hint">
            Duration in minutes (1–1440). Examples: 45 min, 90 min (1.5 hr), 120 min (2 hr), 180 min (3 hr).
          </p>
        </div>
        <div class="form-group">
          <label>Gap Between Slots</label>
          <select v-model.number="genForm.gap_minutes" class="form-control">
            <option :value="0">None</option>
            <option :value="10">10 min</option>
            <option :value="15">15 min</option>
            <option :value="30">30 min</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Exclude Days</label>
        <div class="exclude-days">
          <label v-for="d in weekdayOptions" :key="d.value" class="subject-check">
            <input type="checkbox" :value="d.value" v-model="genForm.exclude_days" />
            {{ d.label }}
          </label>
        </div>
      </div>
      <label class="check-row">
        <input type="checkbox" v-model="genForm.auto_assign_teachers" /> Auto-assign teachers
      </label>

      <p v-if="pendingRoutinePlan?.preview?.length && newSubjectsToSchedule.length" class="hint-box hint-box--preview">
        <strong>{{ pendingRoutinePlan.preview.length }} new slot(s) previewed</strong> (not saved yet).
        Click <em>Continue to Marks</em> to save as draft, or adjust subjects and preview again.
      </p>
      <p v-else-if="routinesInWizardContext.length" class="hint-box">
        <strong>{{ routinesInWizardContext.length }} draft routine(s)</strong> already saved on
        {{ targetScheduleDate || 'this schedule' }} for this batch (from a previous Continue to Marks).
        Preview only shows new slots — click <em>Continue to Marks</em> to proceed, or pick new subjects to preview more.
      </p>

      <div v-if="generatedPreview.length" class="preview-table-wrap">
        <h3>Generated Schedule Preview</h3>
        <table class="preview-table">
          <thead>
            <tr><th>Date</th><th>Subject</th><th>Time</th><th>Teacher</th></tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in generatedPreview" :key="i">
              <td>{{ row.exam_date }}</td>
              <td>{{ row._subject_name }}</td>
              <td>{{ formatPreviewTime(row.start_time) }} – {{ formatPreviewTime(row.end_time) }}</td>
              <td>{{ row._teacher_name || '—' }}</td>
            </tr>
          </tbody>
        </table>
        <p v-if="generateWarnings.length" class="warn-hint">{{ generateWarnings.join(' · ') }}</p>
      </div>

      <div class="step-actions">
        <button class="btn btn-outline" @click="step = 1">← Back</button>
        <button class="btn btn-secondary" :disabled="saving || !canGenerate" @click="generateRoutines(false)">
          {{ saving ? 'Previewing...' : (routinesInWizardContext.length || pendingRoutinePlan ? 'Preview More (not saved)' : 'Preview Schedule (not saved)') }}
        </button>
        <button
          v-if="isGridScheduleContext && (hasRoutinesOnTargetDate || selectedSubjectsOnWrongDate.length) && selectedSubjectIds.length"
          class="btn btn-outline btn-warn"
          :disabled="saving || !canGenerate"
          @click="generateRoutines(true)"
        >
          {{ selectedSubjectsOnWrongDate.length && !hasRoutinesOnTargetDate ? 'Move to this date' : 'Reschedule on this date' }}
        </button>
        <button
          v-else-if="!isGridScheduleContext && (routinesInWizardContext.length || pendingRoutinePlan) && newSubjectsToSchedule.length"
          class="btn btn-outline btn-warn"
          :disabled="saving || !canGenerate"
          @click="generateRoutines(true)"
        >
          Replace Selected Subjects
        </button>
        <button class="btn btn-primary" :disabled="!canContinueToMarks || saving" @click="goToStep4">
          {{ saving ? 'Saving draft...' : 'Save Draft & Continue to Marks →' }}
        </button>
      </div>
      <p v-if="pendingRoutinePlan?.preview?.length" class="field-hint">
        Preview is not saved yet. Draft routines are written to the grid only when you click
        <strong>Save Draft &amp; Continue to Marks</strong> (published in Step 6).
      </p>
      <p v-if="selectedSubjectIds.length === 0" class="hint">Check at least one subject above.</p>
      <p v-else-if="!hasRoutinesOnTargetDate && !pendingRoutinePlan" class="hint">Set schedule, then click <strong>Preview Schedule</strong>.</p>
      <p v-else-if="!canContinueToMarks" class="warn-hint">
        Click <strong>{{ isGridScheduleContext ? 'Preview Schedule' : 'Preview Schedule' }}</strong> for your checked subject(s) on {{ targetScheduleDate || 'the chosen date' }}, then continue.
      </p>
      <p v-else-if="pendingRoutinePlan?.preview?.length && newSubjectsToSchedule.length" class="success-hint">
        Schedule preview ready — click <strong>Save Draft &amp; Continue to Marks</strong> (not published yet).
      </p>
      <p v-else-if="isGridScheduleContext && selectedSubjectsCoveredOnTargetDate" class="success-hint">
        Draft routine(s) already saved on {{ targetScheduleDate }} — continue to marks when ready.
      </p>
      <p v-else-if="isGridScheduleContext && pendingRoutinePlan?.preview?.length" class="success-hint">
        Preview ready for {{ targetScheduleDate }} — click <strong>Save Draft &amp; Continue to Marks</strong>.
      </p>
      <p v-else-if="isGridScheduleContext" class="success-hint">
        Schedule on {{ targetScheduleDate }}, preview, then save draft to continue.
      </p>
      <p v-else class="success-hint">✓ {{ routinesInWizardContext.length || routines.length }} routine(s) ready — continue when ready.</p>
    </div>

    <!-- Step 4: Mark Distribution -->
    <div v-if="step === 3" class="step-card">
      <h2>Step 4 — Mark Distribution (per subject)</h2>
      <div class="direction-box">
        <p><strong>What to fill:</strong></p>
        <ul>
          <li><strong>MCQ / CQ marks</strong> — maximum marks for each part (shown based on paper format).</li>
          <li><strong>Pass marks</strong> — minimum total needed to pass this subject.</li>
          <li><strong>Duration</strong> — exam time in minutes for this subject paper.</li>
        </ul>
        <p class="field-hint">Teachers will enter MCQ and CQ numbers separately when paper format is MCQ + CQ.</p>
      </div>
      <div v-if="routinesForMarks.length === 0" class="empty">No routines yet. Go back to Step 3 and preview, then save as draft.</div>
      <p v-else class="field-hint">Adjust if needed — all rows save together when you click Continue.</p>
      <div v-if="routinesForMarks.length" class="marks-table-head" :style="{ gridTemplateColumns: marksGridColumns }">
        <span>Subject</span>
        <span v-if="showMcqCol">MCQ Max</span>
        <span v-if="showCqCol">CQ Max</span>
        <span v-if="!showMcqCol && !showCqCol">Total</span>
        <span>Pass</span>
        <span>Duration</span>
      </div>
      <div v-for="r in routinesForMarks" :key="r.id" class="routine-row marks-row" :style="{ gridTemplateColumns: marksGridColumns }">
        <strong>{{ r.subject?.name || r.subject_name }}</strong>
        <input v-if="showMcqCol" v-model.number="r._mcq_marks" type="number" min="0" class="small-input" title="MCQ maximum marks" />
        <input v-if="showCqCol" v-model.number="r._cq_marks" type="number" min="0" class="small-input" title="CQ maximum marks" />
        <input v-if="!showMcqCol && !showCqCol" v-model.number="r._total_marks" type="number" min="1" class="small-input" title="Total marks" />
        <input v-model.number="r._pass_marks" type="number" min="0" class="small-input" title="Pass marks" />
        <input v-model.number="r._duration" type="number" min="1" class="small-input" title="Duration in minutes" />
      </div>
      <div class="step-actions">
        <button class="btn btn-outline" @click="step = 2">← Back</button>
        <button class="btn btn-primary" :disabled="saving" @click="continueFromMarks">
          {{ saving ? 'Saving...' : 'Continue →' }}
        </button>
      </div>
    </div>

    <!-- Step 5: Attach Questions -->
    <div v-if="step === 4" class="step-card">
      <h2>Step 5 — Question Papers {{ requiresQuestions ? '(required)' : '(optional)' }}</h2>
      <div class="direction-box">
        <p v-if="requiresQuestions">
          <strong>Practice</strong> and <strong>online/hybrid</strong> exams require approved questions on every subject routine before publish.
        </p>
        <p v-else>Attach <strong>approved questions</strong> from the Question Bank to each subject routine.</p>
        <ul>
          <li>Click <strong>Attach Questions</strong> → pick MCQ/CQ from bank → save.</li>
          <li>Click <strong>PDF</strong> to download student question paper for printing.</li>
          <li v-if="!requiresQuestions">Skip this step if you only need offline manual papers without question bank.</li>
        </ul>
      </div>
      <div v-for="r in routinesForMarks" :key="r.id" class="routine-row">
        <span>{{ r.subject?.name || r.subject_name }}</span>
        <span v-if="requiresQuestions" class="q-count-badge" :class="{ ok: (r.question_count || 0) > 0 }">{{ r.question_count || 0 }} questions</span>
        <button class="btn btn-sm btn-outline" @click="openPicker(r)">Attach Questions</button>
        <button class="btn btn-sm btn-outline" @click="downloadPaper(r.id, 'student')">PDF</button>
      </div>
      <p v-if="requiresQuestions && !allRoutinesHaveQuestions" class="warn-hint">
        Each subject routine needs at least one approved question before you can publish.
      </p>
      <div class="step-actions">
        <button class="btn btn-outline" @click="step = 3">← Back</button>
        <button class="btn btn-primary" :disabled="requiresQuestions && !allRoutinesHaveQuestions" @click="step = 5">Continue →</button>
      </div>
    </div>

    <!-- Step 6: Publish -->
    <div v-if="step === 5" class="step-card">
      <h2>Step 6 — Publish Exam</h2>
      <div class="summary">
        <p><strong>{{ form.name }}</strong></p>
        <p>{{ routinesForMarks.length }} subject routine(s) · {{ form.is_practice ? 'Practice' : 'Official' }} · {{ form.delivery_mode }}</p>
        <p v-if="publishDone" class="success-hint">✓ Exam and routines published successfully!</p>
        <p v-else-if="requiresQuestions && !allRoutinesHaveQuestions" class="warn-hint">
          Attach approved questions in Step 5 for:
          {{ routinesForMarks.filter(r => !(r.question_count > 0)).map(r => r.subject?.name || r.subject_name).join(', ') }}
        </p>
      </div>
      <div class="step-actions">
        <button class="btn btn-outline" @click="step = 4">← Back</button>
        <button
          class="btn btn-success"
          :disabled="saving || !examId || routinesForMarks.length === 0 || (requiresQuestions && !allRoutinesHaveQuestions)"
          @click="publishExam"
        >
          {{ saving ? 'Publishing...' : 'Publish Exam & Routines' }}
        </button>
        <router-link v-if="publishDone" :to="'/dashboard/exams/routines'" class="btn btn-primary">Go to Routines</router-link>
      </div>
    </div>

    <RoutineQuestionPickerModal
      v-if="pickerRoutine"
      :routine="pickerRoutine"
      @close="pickerRoutine = null"
      @saved="() => loadRoutines({ includeQuestionCounts: true })"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import examService from '@/services/exam.service'
import academicService from '@/services/academic.service'
import enrollmentService from '@/services/enrollment.service'
import RoutineQuestionPickerModal from '@/components/exam/RoutineQuestionPickerModal.vue'
import { markConfigFromExamType } from '@/utils/examType.utils'
import { configTotalMarks } from '@/utils/markConfig.utils'
import { extractData, extractErrorMessage } from '@/utils/api.utils'

const route = useRoute()
const existingExams = ref([])
const selectedExamId = ref('')
const successMsg = ref('')

const stepLabels = ['Exam Info', 'Class/Batch', 'Subjects', 'Marks', 'Questions', 'Publish']
const step = ref(0)
const saving = ref(false)
const error = ref(null)
const examId = ref(null)
const examStatus = ref('draft')
const examTypes = ref([])
const sessions = ref([])
const classes = ref([])
const routines = ref([])
const pickerRoutine = ref(null)
const subjects = ref([])
const selectedSubjectIds = ref([])
const subjectsLoading = ref(false)
const publishDone = ref(false)
const generatedPreview = ref([])
const generateWarnings = ref([])
const lastGeneratedSubjectIds = ref([])
/** Step 3 preview — persisted to DB only when continuing to Marks */
const pendingRoutinePlan = ref(null)

const weekdayOptions = [
  { value: 'sun', label: 'Sunday' },
  { value: 'mon', label: 'Monday' },
  { value: 'tue', label: 'Tuesday' },
  { value: 'wed', label: 'Wednesday' },
  { value: 'thu', label: 'Thursday' },
  { value: 'fri', label: 'Friday' },
  { value: 'sat', label: 'Saturday' },
]

const form = ref({
  name: '', exam_type_id: '', academic_session_id: '',
  start_date: '', end_date: '', description: '',
  is_practice: false, delivery_mode: 'offline',
  eligibility_check_enabled: false,
  min_attendance_percent: null,
  class_id: '', course_id: '', batch_id: '',
})

const genForm = ref({
  start_date: '',
  end_date: '',
  start_time: '10:00',
  end_time: '11:00',
  end_time_limit: '17:00',
  slot_duration: 60,
  gap_minutes: 15,
  exclude_days: ['fri'],
  auto_assign_teachers: true,
  auto_assign_rooms: false,
})

/** Persisted from routine grid cell click — survives step navigation & saveStep1 */
const scheduleSlotPrefill = ref(null)
/** Grid cell date only (no time/scope prefill) */
const gridScheduleDate = ref(null)
/** Locked from route — never overwritten by exam period dates on Step 1 */
const routeGridScheduleDate = ref('')
/** Locked from grid tab (online/offline) — offline routines must not block online wizard */
const routeDeliveryMode = ref('')
/** Exam's real date range — kept when grid slot pre-fills Step 1 display only */
const examDateSnapshot = ref({ start: '', end: '' })
/** User changed Daily Start / End Limit — do not overwrite on preview or save */
const userEditedScheduleTimes = ref(false)
let applyingScheduleDefaults = false

function markScheduleTimesEdited() {
  if (!applyingScheduleDefaults) {
    userEditedScheduleTimes.value = true
  }
}

const allSubjectsSelected = computed(() =>
  subjects.value.length > 0 && selectedSubjectIds.value.length === subjects.value.length
)

function scheduleEndLimitRaw() {
  if (scheduleSlotPrefill.value?.date) {
    return (genForm.value.end_time || '').slice(0, 5)
  }
  return (genForm.value.end_time_limit || genForm.value.end_time || '17:00').slice(0, 5)
}

function scheduleWindowMinutes() {
  const start = timeToMinutes(genForm.value.start_time)
  const end = timeToMinutes(scheduleEndLimitRaw())
  if (!start || !end) return 0
  return end > start ? end - start : (24 * 60 - start) + end
}

const canGenerate = computed(() => {
  if (!selectedSubjectIds.value.length
    || !genForm.value.start_date
    || !genForm.value.end_date
    || !genForm.value.start_time
    || !genForm.value.slot_duration) {
    return false
  }
  const end = scheduleEndLimitRaw()
  if (!end || timeToMinutes(end) <= timeToMinutes(genForm.value.start_time)) {
    return false
  }
  const dur = Number(genForm.value.slot_duration) || 60
  return scheduleWindowMinutes() >= dur
})

const filteredCourses = computed(() => filteredCoursesList.value)
const filteredBatches = computed(() => filteredBatchesList.value)

const filteredCoursesList = ref([])
const filteredBatchesList = ref([])

const activeExamTypes = computed(() => examTypes.value.filter(t => t.status !== 'inactive'))

const selectedPaperType = computed(() =>
  examTypes.value.find(t => t.id === form.value.exam_type_id)
)

const showMcqCol = computed(() => {
  const code = (selectedPaperType.value?.code || '').toUpperCase()
  return !code || code === 'MCQ' || code === 'BOTH'
})

const showCqCol = computed(() => {
  const code = (selectedPaperType.value?.code || '').toUpperCase()
  return !code || code === 'CQ' || code === 'BOTH'
})

const canStep1 = computed(() => {
  const hasName = selectedExamId.value || form.value.name?.trim()
  return hasName && form.value.exam_type_id && form.value.academic_session_id
    && form.value.start_date && form.value.end_date
})
const canStep2 = computed(() => form.value.class_id && form.value.course_id && form.value.batch_id)

const requiresQuestions = computed(() =>
  form.value.is_practice || ['online', 'hybrid'].includes(form.value.delivery_mode)
)

function normId(id) {
  if (id == null || id === '') return ''
  return String(id)
}

function routineDeliveryChannel(routine) {
  if (routine?.delivery_channel === 'online' || routine?.delivery_channel === 'offline') {
    return routine.delivery_channel
  }
  const mode = routine?.delivery_mode || 'offline'
  return ['online', 'hybrid'].includes(mode) ? 'online' : 'offline'
}

function effectiveWizardDeliveryMode() {
  return routeDeliveryMode.value || form.value.delivery_mode || 'offline'
}

function wizardDeliveryChannel() {
  return ['online', 'hybrid'].includes(effectiveWizardDeliveryMode()) ? 'online' : 'offline'
}

function routinesForWizardChannel(list = routines.value) {
  const channel = wizardDeliveryChannel()
  return list.filter((r) => {
    if (r.status === 'cancelled') return false
    if (!r.delivery_mode && !r.delivery_channel) {
      return true
    }
    return routineDeliveryChannel(r) === channel
  })
}

function routineSubjectIds(list = routines.value) {
  return [...new Set(
    list
      .filter(r => r.status !== 'cancelled')
      .map(r => normId(r.subject_id || r.subject?.id))
      .filter(Boolean),
  )].sort()
}

function routineSubjectIdsOnDate(date, list = routines.value) {
  if (!date) return []
  const day = String(date).slice(0, 10)
  return [...new Set(
    list
      .filter(r => r.status !== 'cancelled' && formatDateInput(r.exam_date) === day)
      .map(r => normId(r.subject_id || r.subject?.id))
      .filter(Boolean),
  )]
}

const isGridScheduleContext = computed(() =>
  !!scheduleSlotPrefill.value?.date || !!gridScheduleDate.value || !!routeGridScheduleDate.value,
)

const targetScheduleDate = computed(() =>
  formatDateInput(
    scheduleSlotPrefill.value?.date
    || routeGridScheduleDate.value
    || gridScheduleDate.value
    || genForm.value.start_date
    || '',
  ),
)

function routinesForCurrentBatch(list = routines.value) {
  const scoped = routinesForWizardChannel(list)
  const batchId = normId(form.value.batch_id)
  if (!batchId) return scoped
  return scoped.filter((r) => {
    const routineBatch = normId(r.batch_id || r.batch?.id)
    return !routineBatch || routineBatch === batchId
  })
}

const scheduledSubjectIds = computed(() =>
  routineSubjectIds(
    isGridScheduleContext.value ? routinesForCurrentBatch() : routinesForWizardChannel(),
  ),
)

const selectedSubjectsOnWrongDate = computed(() => {
  if (!isGridScheduleContext.value || !targetScheduleDate.value) return []
  const onTarget = new Set(scheduledSubjectIdsOnTargetDate.value)
  return selectedSubjectIds.value.filter((id) => {
    const nid = normId(id)
    return scheduledSubjectIds.value.includes(nid) && !onTarget.has(nid)
  })
})

function routinesForScheduleDate(list = routines.value) {
  const batchId = normId(form.value.batch_id)
  const date = formatDateInput(targetScheduleDate.value)
  const scoped = routinesForWizardChannel(list)
  if (!date) return scoped

  return scoped.filter((r) => {
    if (formatDateInput(r.exam_date) !== date) return false
    if (batchId) {
      const routineBatch = normId(r.batch_id || r.batch?.id)
      if (routineBatch && routineBatch !== batchId) return false
    }
    return true
  })
}

const routinesInWizardContext = computed(() => {
  if (isGridScheduleContext.value) {
    return routinesForScheduleDate()
  }
  const batchId = normId(form.value.batch_id)
  const scoped = routinesForWizardChannel()
  if (!batchId) return scoped
  return scoped.filter((r) => {
    const routineBatch = normId(r.batch_id || r.batch?.id)
    return !routineBatch || routineBatch === batchId
  })
})

const scheduledSubjectIdsOnTargetDate = computed(() =>
  routineSubjectIdsOnDate(targetScheduleDate.value, routinesInWizardContext.value),
)

/** Selected subjects to place in the next preview (same subject on same date is OK at a different time). */
const newSubjectsToSchedule = computed(() =>
  selectedSubjectIds.value.map(normId).filter(Boolean),
)

const hasRoutinesOnTargetDate = computed(() =>
  routinesOnTargetDate(routinesInWizardContext.value).length > 0,
)

const selectedSubjectsCoveredOnTargetDate = computed(() => {
  if (!selectedSubjectIds.value.length) return false
  const onDate = new Set(scheduledSubjectIdsOnTargetDate.value)
  return selectedSubjectIds.value.every(id => onDate.has(normId(id)))
})

function previewMatchesTargetDate() {
  const target = formatDateInput(targetScheduleDate.value)
  const preview = pendingRoutinePlan.value?.preview || []
  if (!preview.length || !target) return !!preview.length
  return preview.every(row => formatDateInput(row.exam_date) === target)
}

function selectedSubjectsReadyForMarks() {
  if (!selectedSubjectIds.value.length) return false
  if (pendingRoutinePlan.value?.preview?.length) {
    return previewMatchesTargetDate()
  }
  if (selectedSubjectsCoveredOnTargetDate.value) return true
  const onDate = routinesForScheduleDate()
  return selectedSubjectIds.value.every(id =>
    onDate.some(r => normId(r.subject_id || r.subject?.id) === normId(id)),
  )
}

const selectedSubjectsHaveRoutines = computed(() => {
  if (isGridScheduleContext.value) {
    return selectedSubjectsReadyForMarks()
  }
  if (pendingRoutinePlan.value?.preview?.length) return true
  if (selectedSubjectIds.value.length === 0) return routines.value.length > 0
  const onDate = new Set(scheduledSubjectIdsOnTargetDate.value)
  return selectedSubjectIds.value.every(id => onDate.has(normId(id)))
})

const routinesForMarks = computed(() => {
  if (isGridScheduleContext.value) {
    // Grid adds one date at a time — keep all batch routines on every date for marks/publish.
    return routinesForCurrentBatch()
  }
  const batchId = normId(form.value.batch_id)
  const scoped = routinesForWizardChannel()
  if (!batchId) return scoped
  return scoped.filter((r) => {
    const routineBatch = normId(r.batch_id || r.batch?.id)
    return !routineBatch || routineBatch === batchId
  })
})

const canContinueToMarks = computed(() => {
  if (!selectedSubjectIds.value.length) return false
  if (isGridScheduleContext.value) {
    return selectedSubjectsReadyForMarks()
  }
  if (pendingRoutinePlan.value?.preview?.length) return true
  return routines.value.length > 0 && selectedSubjectsHaveRoutines.value
})

const allRoutinesHaveQuestions = computed(() =>
  routinesForMarks.value.length > 0 && routinesForMarks.value.every(r => (r.question_count || 0) > 0)
)

const marksGridColumns = computed(() => {
  const cols = ['1.4fr']
  if (showMcqCol.value) cols.push('72px')
  if (showCqCol.value) cols.push('72px')
  if (!showMcqCol.value && !showCqCol.value) cols.push('72px')
  cols.push('72px', '72px')
  return cols.join(' ')
})

function parseSlotFromRoute() {
  const slotDate = route.query.slot_date
  if (typeof slotDate !== 'string' || !slotDate.trim()) return null
  return {
    date: slotDate.slice(0, 10),
    startTime: typeof route.query.slot_start === 'string' ? route.query.slot_start : '',
    endTime: typeof route.query.slot_end === 'string' ? route.query.slot_end : '',
  }
}

function parseGridScheduleDateFromRoute() {
  const raw = route.query.schedule_date
  if (typeof raw !== 'string' || !raw.trim()) return ''
  return raw.slice(0, 10)
}

function restoreRouteGridScheduleDate() {
  if (!routeGridScheduleDate.value) return
  gridScheduleDate.value = routeGridScheduleDate.value
  applyGridScheduleDate()
}

function applyRouteDeliveryMode({ withScheduleDefaults = true } = {}) {
  const mode = routeDeliveryMode.value || route.query.delivery_mode
  if (typeof mode === 'string' && ['online', 'offline', 'hybrid'].includes(mode)) {
    form.value.delivery_mode = mode
    if (withScheduleDefaults) {
      applyScheduleDefaultsForContext({
        forceOnlineGrid: mode === 'online' && route.query.from === 'grid',
      })
    }
  }
}

function applyGridScheduleDate() {
  const day = formatDateInput(gridScheduleDate.value || routeGridScheduleDate.value)
  if (!day) return
  genForm.value.start_date = day
  genForm.value.end_date = day

  const weekdayKeys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat']
  const clickedKey = weekdayKeys[new Date(`${day}T12:00:00`).getDay()]
  if (clickedKey) {
    genForm.value.exclude_days = (genForm.value.exclude_days || []).filter(
      (d) => String(d).slice(0, 3).toLowerCase() !== clickedKey,
    )
  }
}

function patchGeneratePayloadForScheduleContext(payload, { replaceMode = false } = {}) {
  const day = formatDateInput(
    routeGridScheduleDate.value || targetScheduleDate.value,
  )
  if (day) {
    payload.start_date = day
    payload.end_date = day
  }
  payload.delivery_mode = effectiveWizardDeliveryMode()
  payload.merge_mode = replaceMode ? 'replace' : (payload.merge_mode || 'append')
  return payload
}

function scheduleNotReadyError() {
  const target = formatDateInput(targetScheduleDate.value)
  const channel = wizardDeliveryChannel()
  const scoped = routinesForWizardChannel()

  const mismatched = selectedSubjectIds.value
    .map((id) => {
      const sid = normId(id)
      const match = scoped.find(r =>
        normId(r.subject_id || r.subject?.id) === sid
        && r.status !== 'cancelled'
        && target
        && formatDateInput(r.exam_date) !== target,
      )
      if (!match) return null
      return {
        subject: match.subject?.name || match.subject_name || 'Subject',
        savedDate: formatDateInput(match.exam_date),
      }
    })
    .filter(Boolean)

  if (mismatched.length) {
    const first = mismatched[0]
    return `${first.subject} is saved on ${first.savedDate}, not ${target} (${channel} schedule). `
      + `Delete it from the ${channel} routine grid, or click Move to this date.`
  }
  if (pendingRoutinePlan.value?.preview?.length) {
    return 'Preview could not be saved. Click Preview Schedule again, then Save Draft & Continue to Marks.'
  }
  return 'Select subjects, click Preview Schedule, then Save Draft & Continue to Marks.'
}

async function fetchChannelRoutines() {
  if (!examId.value) return []
  applyRouteDeliveryMode({ withScheduleDefaults: false })
  const res = await examService.routines.byExam(examId.value, {
    delivery_channel: wizardDeliveryChannel(),
  })
  return extractData(res, []).filter(r => r.status !== 'cancelled')
}

async function enforceTargetDateForSelectedSubjects(subjectIds = null) {
  const target = formatDateInput(targetScheduleDate.value)
  const ids = subjectIds ?? selectedSubjectIds.value
  if (!target || !ids?.length || !examId.value) return 0

  const subjectSet = new Set(ids.map(normId))
  const channelRows = await fetchChannelRoutines()
  const toRemove = channelRows.filter((r) => {
    if (!subjectSet.has(normId(r.subject_id || r.subject?.id))) return false
    return formatDateInput(r.exam_date) !== target
  })

  for (const r of toRemove) {
    await examService.routines.delete(r.id)
  }
  if (toRemove.length) {
    await loadRoutines({ reconcile: false })
  }
  return toRemove.length
}

async function purgeWrongDateRoutinesForSelected(subjectIds = null) {
  return enforceTargetDateForSelectedSubjects(subjectIds)
}

async function deleteAllRoutinesForSelectedSubjects(subjectIds, { date = null } = {}) {
  if (!subjectIds?.length || !examId.value) return 0
  const subjectSet = new Set(subjectIds.map(normId))
  const targetDay = date ? formatDateInput(date) : null
  const channelRows = await fetchChannelRoutines()
  const toRemove = channelRows.filter((r) => {
    if (!subjectSet.has(normId(r.subject_id || r.subject?.id))) return false
    if (targetDay) {
      return formatDateInput(r.exam_date) === targetDay
    }
    return true
  })
  for (const r of toRemove) {
    await examService.routines.delete(r.id)
  }
  if (toRemove.length) {
    await loadRoutines({ reconcile: false })
  }
  return toRemove.length
}

const gridScheduleDateLabel = computed(() => {
  const day = formatDateInput(routeGridScheduleDate.value || gridScheduleDate.value)
  if (!day) return ''
  return new Date(`${day}T12:00:00`).toLocaleDateString('en-US', {
    weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
  })
})

function parseScopeFromRoute() {
  const pick = (key) => {
    const value = route.query[key]
    return typeof value === 'string' && value.trim() ? value.trim() : ''
  }
  return {
    class_id: pick('class_id'),
    course_id: pick('course_id'),
    batch_id: pick('batch_id'),
  }
}

async function applyGridScopeFromRoute() {
  const scope = parseScopeFromRoute()
  if (scope.class_id || scope.course_id || scope.batch_id) {
    await loadClassCourseBatch(scope.class_id, scope.course_id, scope.batch_id)
    return
  }
  if (!examId.value) return
  try {
    const res = await examService.exams.get(examId.value)
    const exam = extractData(res, {})
    const savedClassId = exam.class_id || exam.class?.id || ''
    const savedCourseId = exam.course_id || exam.course?.id || ''
    const savedBatchId = exam.batch_id || exam.batch?.id || ''
    if (savedClassId && savedCourseId && savedBatchId) {
      await loadClassCourseBatch(savedClassId, savedCourseId, savedBatchId)
    }
  } catch {
    /* user picks scope manually in Step 2 */
  }
}

function applyScheduleSlotPrefill() {
  const slot = scheduleSlotPrefill.value
  if (!slot?.date) return
  form.value.start_date = slot.date
  form.value.end_date = slot.date
  genForm.value.start_date = slot.date
  genForm.value.end_date = slot.date
  if (slot.startTime) {
    genForm.value.start_time = slot.startTime.length >= 5 ? slot.startTime.slice(0, 5) : slot.startTime
  }
  if (slot.endTime) {
    genForm.value.end_time = slot.endTime.length >= 5 ? slot.endTime.slice(0, 5) : slot.endTime
  } else if (genForm.value.start_time) {
    genForm.value.end_time = resolveSlotEndTime(genForm.value.start_time)
  }
  if (slot.endTime && slot.startTime) {
    const startM = timeToMinutes(slot.startTime)
    const endM = timeToMinutes(slot.endTime)
    if (endM > startM) {
      genForm.value.slot_duration = Math.min(1440, Math.max(1, endM - startM))
    }
  } else if (genForm.value.start_time && genForm.value.end_time) {
    const diff = timeToMinutes(genForm.value.end_time) - timeToMinutes(genForm.value.start_time)
    if (diff > 0) {
      genForm.value.slot_duration = Math.min(1440, Math.max(1, diff))
    }
  }
}

const scheduleSlotLabel = computed(() => {
  const slot = scheduleSlotPrefill.value
  if (!slot?.date) return ''
  const d = new Date(`${slot.date}T12:00:00`).toLocaleDateString('en-US', {
    weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
  })
  const time = scheduleSlotTimeDisplay.value
  return time ? `${d} · ${time}` : d
})

const scheduleSlotTimeDisplay = computed(() => {
  const slot = scheduleSlotPrefill.value
  if (!slot?.startTime) return '—'
  const start = slot.startTime.length >= 5 ? slot.startTime.slice(0, 5) : slot.startTime
  if (!slot.endTime) return start
  const end = slot.endTime.length >= 5 ? slot.endTime.slice(0, 5) : slot.endTime
  return `${start} – ${end}`
})

function clearPendingRoutinePlan() {
  pendingRoutinePlan.value = null
}

function resetScopeForFreshSchedule() {
  form.value.class_id = ''
  form.value.course_id = ''
  form.value.batch_id = ''
  filteredCoursesList.value = []
  filteredBatchesList.value = []
  subjects.value = []
  selectedSubjectIds.value = []
  lastGeneratedSubjectIds.value = []
  generatedPreview.value = []
  generateWarnings.value = []
  routines.value = []
  clearPendingRoutinePlan()
}

function ensureMinimumScheduleWindow() {
  const start = timeToMinutes(genForm.value.start_time)
  const dur = Number(genForm.value.slot_duration) || 60
  const window = scheduleWindowMinutes()
  if (!start || window >= dur) return

  const isOnline = ['online', 'hybrid'].includes(form.value.delivery_mode)
  const minEnd = start + dur
  if (scheduleSlotPrefill.value?.date) {
    genForm.value.end_time = minutesToTime(Math.min(23 * 60 + 59, minEnd))
  } else if (isOnline) {
    genForm.value.end_time_limit = '23:59'
  } else {
    genForm.value.end_time_limit = minutesToTime(Math.min(23 * 60 + 59, minEnd + 30))
  }
  genForm.value.end_time = resolveSlotEndTime(genForm.value.start_time)
}

function shouldForceOnlineGridDefaults() {
  if (userEditedScheduleTimes.value) return false
  if (!isGridScheduleContext.value || route.query.from !== 'grid') return false
  if (!['online', 'hybrid'].includes(form.value.delivery_mode)) return false
  if (scheduleSlotPrefill.value?.startTime) return false
  const dur = Number(genForm.value.slot_duration) || 60
  const window = scheduleWindowMinutes()
  const endLimit = timeToMinutes((genForm.value.end_time_limit || '00:00').slice(0, 5))
  return window < dur || endLimit < timeToMinutes('20:00')
}

function applyScheduleDefaultsForContext({ forceOnlineGrid = false } = {}) {
  applyingScheduleDefaults = true
  try {
    const hasSlotTime = !!scheduleSlotPrefill.value?.startTime
    if (hasSlotTime) {
      userEditedScheduleTimes.value = false
      applyScheduleSlotPrefill()
      ensureMinimumScheduleWindow()
      return
    }

    const isOnline = ['online', 'hybrid'].includes(form.value.delivery_mode)
    const shouldUseOnlineGridDefaults = !userEditedScheduleTimes.value
      && (forceOnlineGrid || shouldForceOnlineGridDefaults())

    if (isOnline) {
      if (shouldUseOnlineGridDefaults) {
        genForm.value.start_time = '20:00'
        genForm.value.end_time_limit = '23:59'
        if ((Number(genForm.value.slot_duration) || 0) > 180) {
          genForm.value.slot_duration = 60
        }
        if ((Number(genForm.value.gap_minutes) || 0) > 15) {
          genForm.value.gap_minutes = 10
        }
      } else if (!userEditedScheduleTimes.value
        && (!genForm.value.end_time_limit || timeToMinutes(genForm.value.end_time_limit) <= timeToMinutes(genForm.value.start_time))) {
        genForm.value.end_time_limit = '23:59'
      }
    } else if (!userEditedScheduleTimes.value
      && (!genForm.value.start_time || genForm.value.start_time === '20:00')) {
      genForm.value.start_time = '10:00'
      genForm.value.end_time_limit = '17:00'
    }

    genForm.value.end_time = resolveSlotEndTime(genForm.value.start_time)
    ensureMinimumScheduleWindow()
  } finally {
    applyingScheduleDefaults = false
  }
}

function onDeliveryModeChange() {
  userEditedScheduleTimes.value = false
  applyScheduleDefaultsForContext({ forceOnlineGrid: false })
}

function countAvailableScheduleDates() {
  const dayMap = { sun: 0, mon: 1, tue: 2, wed: 3, thu: 4, fri: 5, sat: 6 }
  const excluded = new Set(
    (genForm.value.exclude_days || [])
      .map(d => dayMap[String(d).slice(0, 3).toLowerCase()])
      .filter(n => n !== undefined),
  )
  const start = formatDateInput(genForm.value.start_date)
  const end = formatDateInput(genForm.value.end_date)
  if (!start || !end) return 0

  let count = 0
  const cur = new Date(`${start}T12:00:00`)
  const last = new Date(`${end}T12:00:00`)
  while (cur <= last) {
    if (!excluded.has(cur.getDay())) count++
    cur.setDate(cur.getDate() + 1)
  }
  return count
}

function estimateSlotsPerDay() {
  const total = scheduleWindowMinutes()
  const dur = Number(genForm.value.slot_duration) || 60
  const gap = Number(genForm.value.gap_minutes) || 0
  let count = 0
  let cursor = 0
  while (cursor + dur <= total) {
    count++
    cursor += dur + gap
  }
  return count
}

function validateScheduleCapacity(subjectCount) {
  if (!subjectCount) return ''
  ensureMinimumScheduleWindow()
  const days = countAvailableScheduleDates()
  const perDay = estimateSlotsPerDay()
  const total = days * perDay
  if (total >= subjectCount) return ''

  const endLimit = scheduleEndLimitRaw()
  const window = scheduleWindowMinutes()
  const dur = Number(genForm.value.slot_duration) || 60
  if (window < dur) {
    return `Daily time window is only ${window} minutes (${genForm.value.start_time}–${endLimit}) but each slot needs ${dur} minutes. `
      + (['online', 'hybrid'].includes(form.value.delivery_mode)
        ? 'For online exams use Daily Start 20:00 and Daily End Limit 23:59, or reduce Slot Duration.'
        : 'Set Daily End Limit later or reduce Slot Duration.')
  }
  return `Not enough slots for ${subjectCount} subject(s) — only ${total} available (${days} day(s) × ${perDay} slot(s)/day). `
    + `Try: extend End Date, set Daily End Limit later than ${endLimit}, or reduce Slot Duration (now ${dur} min).`
}

async function onClassChange() {
  form.value.course_id = ''
  form.value.batch_id = ''
  filteredCoursesList.value = []
  filteredBatchesList.value = []
  subjects.value = []
  selectedSubjectIds.value = []
  lastGeneratedSubjectIds.value = []
  generatedPreview.value = []
  clearPendingRoutinePlan()
  autoSelectSession()
  if (!form.value.class_id) return
  const res = await enrollmentService.getCourses({ class_id: form.value.class_id })
  filteredCoursesList.value = extractData(res, [])
}

async function onCourseChangeWizard() {
  form.value.batch_id = ''
  filteredBatchesList.value = []
  selectedSubjectIds.value = []
  lastGeneratedSubjectIds.value = []
  generatedPreview.value = []
  clearPendingRoutinePlan()
  if (!form.value.course_id) return
  const res = await enrollmentService.getBatchesByCourse(form.value.course_id)
  filteredBatchesList.value = extractData(res, [])
}

async function onBatchChangeWizard() {
  const batch = filteredBatchesList.value.find(b => b.id === form.value.batch_id)
  if (batch?.academic_session_id) {
    form.value.academic_session_id = batch.academic_session_id
  }
}

function autoSelectSession() {
  const current = sessions.value.find(s => s.is_current)
  if (current && !form.value.academic_session_id) {
    form.value.academic_session_id = current.id
  }
}

function toggleAllSubjects(e) {
  selectedSubjectIds.value = e.target.checked ? subjects.value.map(s => s.id) : []
}

function syncSelectedSubjectsFromRoutines() {
  const fromRoutines = routineSubjectIds()
  if (!fromRoutines.length) return
  selectedSubjectIds.value = fromRoutines.filter(id =>
    subjects.value.some(s => s.id === id),
  )
  lastGeneratedSubjectIds.value = [...selectedSubjectIds.value]
}

async function loadSubjects({ fromRoutines = false } = {}) {
  if (!form.value.course_id) {
    subjects.value = []
    if (!fromRoutines) selectedSubjectIds.value = []
    return
  }
  subjectsLoading.value = true
  try {
    const res = await academicService.subjects.byCourse(form.value.course_id)
    subjects.value = extractData(res, [])
    if (fromRoutines && routines.value.length) {
      syncSelectedSubjectsFromRoutines()
    } else if (!selectedSubjectIds.value.length) {
      // New setup: nothing pre-checked — user picks subjects explicitly
      selectedSubjectIds.value = []
    } else {
      // Keep user's checks that still exist in this course
      selectedSubjectIds.value = selectedSubjectIds.value.filter(id =>
        subjects.value.some(s => s.id === id),
      )
    }
  } catch {
    subjects.value = []
    if (!fromRoutines) selectedSubjectIds.value = []
  } finally {
    subjectsLoading.value = false
  }
}

async function loadDeps() {
  const [typesRes, sessionsRes, classesRes, examsRes] = await Promise.all([
    examService.types.list(),
    academicService.sessions.list(),
    academicService.classes.list(),
    examService.exams.list({ per_page: 200, include_all: 1 }).catch(() => null),
  ])
  examTypes.value = extractData(typesRes, [])
  sessions.value = extractData(sessionsRes, [])
  classes.value = extractData(classesRes, [])
  const examRows = examsRes ? extractData(examsRes, []) : []
  existingExams.value = Array.isArray(examRows) ? examRows : []
  autoSelectSession()
}

function formatDateInput(value) {
  if (!value) return ''
  if (value instanceof Date) {
    const y = value.getFullYear()
    const m = String(value.getMonth() + 1).padStart(2, '0')
    const d = String(value.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
  }
  const str = String(value)
  const match = str.match(/^(\d{4}-\d{2}-\d{2})/)
  return match ? match[1] : str.slice(0, 10)
}

function routinesOnTargetDate(list = routines.value) {
  const date = formatDateInput(targetScheduleDate.value)
  if (!date) return []
  return list.filter(r =>
    r.status !== 'cancelled' && formatDateInput(r.exam_date) === date,
  )
}

function subjectScheduledInContext(subjectId, date, list = null) {
  const day = formatDateInput(date)
  const sid = normId(subjectId)
  if (!day || !sid) return false
  const scoped = list ?? routinesForScheduleDate(routines.value)
  return scoped.some(r =>
    normId(r.subject_id || r.subject?.id) === sid
    && formatDateInput(r.exam_date) === day,
  )
}

function slotOverlapsExisting(date, startTime, endTime, list = null) {
  const day = formatDateInput(date)
  const start = timeToMinutes(startTime)
  const end = timeToMinutes(endTime)
  if (!day || end <= start) return false

  const scoped = list ?? routinesForScheduleDate(routines.value)
  return scoped.some((r) => {
    if (formatDateInput(r.exam_date) !== day) return false
    const rStart = timeToMinutes(r.start_time)
    const rEnd = timeToMinutes(r.end_time)
    return start < rEnd && end > rStart
  })
}

function getNextAvailableStartTime(date, preferredStart, gapMinutes = 0) {
  const day = formatDateInput(date)
  let cursor = timeToMinutes(preferredStart)
  const gap = Number(gapMinutes) || 0

  for (const r of routinesForScheduleDate(routines.value)) {
    if (formatDateInput(r.exam_date) !== day) continue
    const afterExisting = timeToMinutes(r.end_time) + gap
    if (afterExisting > cursor) {
      cursor = afterExisting
    }
  }

  return minutesToTime(cursor)
}

function reconcilePendingPlanWithDb() {
  const plan = pendingRoutinePlan.value
  if (!plan?.payloads?.length) return

  const remaining = plan.replaceMode
    ? plan.payloads
    : plan.payloads.filter(p =>
      !slotOverlapsExisting(p.exam_date, p.start_time, p.end_time),
    )

  if (remaining.length === 0) {
    clearPendingRoutinePlan()
    generatedPreview.value = []
  }
}

function formatPreviewTime(value) {
  if (!value) return '—'
  const raw = String(value).slice(0, 8)
  const [h, m] = raw.split(':').map(Number)
  if (Number.isNaN(h) || Number.isNaN(m)) return value
  const suffix = h >= 12 ? 'PM' : 'AM'
  const hour12 = h % 12 || 12
  return `${hour12}:${String(m).padStart(2, '0')} ${suffix}`
}

function timeToMinutes(value) {
  const raw = String(value).slice(0, 5)
  const [h, m] = raw.split(':').map(Number)
  if (Number.isNaN(h) || Number.isNaN(m)) return 0
  return h * 60 + m
}

function minutesToTime(totalMinutes) {
  const capped = Math.min(Math.max(0, totalMinutes), 23 * 60 + 59)
  const h = Math.floor(capped / 60)
  const m = capped % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function addMinutesToTime(timeStr, minutes) {
  return minutesToTime(timeToMinutes(timeStr) + Number(minutes || 0))
}

function clearMessages() {
  error.value = null
  successMsg.value = ''
}

async function loadClassCourseBatch(savedClassId, savedCourseId, savedBatchId) {
  if (!savedClassId) return
  form.value.class_id = savedClassId
  await onClassChange()
  if (savedCourseId) {
    form.value.course_id = savedCourseId
    await onCourseChangeWizard()
  }
  if (savedBatchId) {
    form.value.batch_id = savedBatchId
    await onBatchChangeWizard()
  }
}

async function onWizardExamPick() {
  clearMessages()
  if (!selectedExamId.value) {
    examId.value = null
    examStatus.value = 'draft'
    form.value.name = ''
    routines.value = []
    selectedSubjectIds.value = []
    lastGeneratedSubjectIds.value = []
    generatedPreview.value = []
    clearPendingRoutinePlan()
    return
  }

  try {
    const res = await examService.exams.get(selectedExamId.value)
    const exam = extractData(res, {})
    examId.value = exam.id
    examStatus.value = exam.status || 'draft'
    form.value.name = exam.name || ''
    form.value.exam_type_id = exam.exam_type_id || exam.exam_type?.id || ''
    form.value.academic_session_id = exam.academic_session_id || exam.session?.id || ''
    examDateSnapshot.value = {
      start: formatDateInput(exam.start_date),
      end: formatDateInput(exam.end_date),
    }
    form.value.description = exam.description || ''
    form.value.is_practice = !!exam.is_practice
    form.value.eligibility_check_enabled = !!exam.eligibility_check_enabled
    form.value.min_attendance_percent = exam.min_attendance_percent ?? null

    if (!routeDeliveryMode.value) {
      form.value.delivery_mode = exam.delivery_mode || 'offline'
    }
    applyRouteDeliveryMode()

    if (scheduleSlotPrefill.value?.date) {
      resetScopeForFreshSchedule()
      applyScheduleSlotPrefill()
    } else if (route.query.from === 'grid') {
      resetScopeForFreshSchedule()
      form.value.start_date = examDateSnapshot.value.start
      form.value.end_date = examDateSnapshot.value.end
      restoreRouteGridScheduleDate()
      applyScheduleDefaultsForContext({ forceOnlineGrid: true })
      generatedPreview.value = []
      generateWarnings.value = []
      clearPendingRoutinePlan()
    } else {
      form.value.start_date = examDateSnapshot.value.start
      form.value.end_date = examDateSnapshot.value.end
      const savedClassId = exam.class_id || exam.class?.id || ''
      const savedCourseId = exam.course_id || exam.course?.id || ''
      const savedBatchId = exam.batch_id || exam.batch?.id || ''
      await loadClassCourseBatch(savedClassId, savedCourseId, savedBatchId)
      genForm.value.start_date = form.value.start_date
      genForm.value.end_date = form.value.end_date
      await loadRoutines()
      if (form.value.course_id) {
        await loadSubjects({ fromRoutines: routines.value.length > 0 })
      }
      generatedPreview.value = []
      generateWarnings.value = []
    }
  } catch (err) {
    error.value = extractErrorMessage(err, 'Failed to load selected exam')
  }
}

async function resumeWizardStep() {
  if (!examId.value) return
  if (form.value.class_id && form.value.course_id && form.value.batch_id) {
    await loadRoutines({ includeQuestionCounts: true })
    await loadSubjects({ fromRoutines: routines.value.length > 0 })
    if (routines.value.length > 0) {
      if (requiresQuestions.value && !allRoutinesHaveQuestions.value) {
        step.value = 4
      } else if (examStatus.value === 'published') {
        step.value = 5
      } else {
        step.value = 3
      }
    } else {
      step.value = 2
    }
  } else if (form.value.name) {
    step.value = 1
  }
}

/** Opened from routine grid — fresh Step 1; slot date/time pre-filled; scope/subjects not carried over. */
async function resumeWizardStepForSchedule() {
  if (!examId.value) return
  publishDone.value = false
  resetScopeForFreshSchedule()
  step.value = 0
}

async function saveStep1() {
  saving.value = true
  clearMessages()
  try {
    const keepExamDates = !!scheduleSlotPrefill.value?.date && examDateSnapshot.value.start
    const payload = {
      name: form.value.name,
      exam_type_id: form.value.exam_type_id,
      academic_session_id: form.value.academic_session_id,
      start_date: keepExamDates ? examDateSnapshot.value.start : form.value.start_date,
      end_date: keepExamDates ? examDateSnapshot.value.end : form.value.end_date,
      description: form.value.description || null,
      is_practice: form.value.is_practice,
      delivery_mode: form.value.delivery_mode,
      eligibility_check_enabled: form.value.is_practice ? false : !!form.value.eligibility_check_enabled,
      min_attendance_percent: form.value.is_practice || form.value.min_attendance_percent == null || form.value.min_attendance_percent === ''
        ? null
        : Number(form.value.min_attendance_percent),
      status: 'draft',
    }
    if (examId.value) {
      await examService.exams.update(examId.value, payload)
    } else {
      if (!form.value.name?.trim()) {
        error.value = 'Enter a name for the new exam or select an existing exam from the list.'
        return
      }
      const res = await examService.exams.create(payload)
      examId.value = res.data?.data?.id || res.data?.id
      examStatus.value = 'draft'
      if (examId.value) {
        selectedExamId.value = examId.value
        existingExams.value = [
          { id: examId.value, name: form.value.name, session: sessions.value.find(s => s.id === form.value.academic_session_id) },
          ...existingExams.value,
        ]
      }
    }
    if (scheduleSlotPrefill.value?.date) {
      applyScheduleSlotPrefill()
    } else if (routeGridScheduleDate.value) {
      restoreRouteGridScheduleDate()
      applyScheduleDefaultsForContext({ forceOnlineGrid: true })
    } else {
      genForm.value.start_date = form.value.start_date
      genForm.value.end_date = form.value.end_date
    }
    step.value = 1
  } catch (err) {
    error.value = extractErrorMessage(err, 'Failed to save exam')
  } finally {
    saving.value = false
  }
}

async function saveStep2() {
  if (!examId.value) {
    error.value = 'Save exam info (Step 1) first.'
    return
  }
  saving.value = true
  clearMessages()
  try {
    await examService.exams.update(examId.value, {
      class_id: form.value.class_id,
      course_id: form.value.course_id,
      batch_id: form.value.batch_id,
      academic_session_id: form.value.academic_session_id || null,
    })
    const fromGridEntry = route.query.from === 'grid' || !!scheduleSlotPrefill.value?.date
    if (fromGridEntry) {
      clearPendingRoutinePlan()
      generatedPreview.value = []
      selectedSubjectIds.value = []
      await loadSubjects({ fromRoutines: false })
      await loadRoutines({ reconcile: false })
      applyRouteDeliveryMode()
      restoreRouteGridScheduleDate()
      applyScheduleSlotPrefill()
      applyScheduleDefaultsForContext({ forceOnlineGrid: true })
    } else {
      await loadRoutines()
      await loadSubjects({ fromRoutines: routines.value.length > 0 })
      applyScheduleSlotPrefill()
    }
    step.value = 2
  } catch (err) {
    error.value = extractErrorMessage(err, 'Failed to save scope')
  } finally {
    saving.value = false
  }
}

function resolveSlotEndTime(startTime) {
  const startM = timeToMinutes(startTime)
  const duration = Number(genForm.value.slot_duration) || 60
  let endM = startM + duration
  const maxM = 23 * 60 + 59
  if (endM > maxM) endM = maxM
  if (endM <= startM) endM = Math.min(startM + 15, maxM)
  const h = Math.floor(endM / 60)
  const m = endM % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function getSlotEndTime(startTime) {
  const start = (startTime || genForm.value.start_time || '10:00').slice(0, 5)
  const end = (genForm.value.end_time || '').slice(0, 5)
  if (end && timeToMinutes(end) > timeToMinutes(start)) {
    return end
  }
  return resolveSlotEndTime(start)
}

function validateSlotTimes(startTime, endTime) {
  if (timeToMinutes(endTime) <= timeToMinutes(startTime)) {
    return 'End time must be after start time. Increase slot duration or adjust start time.'
  }
  return ''
}

function buildGridSlotPlan({ force = false, replaceMode = false, subjectIdsOverride = null } = {}) {
  const slot = scheduleSlotPrefill.value
  const date = formatDateInput(
    genForm.value.start_date || slot?.date || routeGridScheduleDate.value || gridScheduleDate.value,
  )
  const gridStartTime = (genForm.value.start_time || slot?.startTime || '10:00').slice(0, 5)
  const gapMinutes = Number(genForm.value.gap_minutes) || 0
  const firstEndTime = getSlotEndTime(gridStartTime)
  const timeErr = validateSlotTimes(gridStartTime, firstEndTime)
  if (timeErr) {
    return { error: timeErr }
  }

  const sourceSubjectIds = subjectIdsOverride ?? selectedSubjectIds.value
  const subjectsToRun = sourceSubjectIds
    .map(normId)
    .filter(id => id)

  if (!subjectsToRun.length) {
    return {
      error: 'Select at least one subject to schedule on this date.',
    }
  }

  const payloads = []
  const preview = []
  let cursorStart = replaceMode
    ? gridStartTime
    : getNextAvailableStartTime(date, gridStartTime, gapMinutes)

  for (const subjectId of subjectsToRun) {
    if (!replaceMode && slotOverlapsExisting(date, cursorStart, getSlotEndTime(cursorStart))) {
      cursorStart = getNextAvailableStartTime(date, cursorStart, gapMinutes)
    }

    const slotEnd = getSlotEndTime(cursorStart)
    const slotErr = validateSlotTimes(cursorStart, slotEnd)
    if (slotErr) {
      return {
        error: `${slotErr} (subject: ${subjects.value.find(s => normId(s.id) === subjectId)?.name || 'selected'})`,
      }
    }

    if (!replaceMode && slotOverlapsExisting(date, cursorStart, slotEnd)) {
      const subjectName = subjects.value.find(s => normId(s.id) === subjectId)?.name || 'selected'
      return {
        error: `No free slot after existing routines for ${subjectName} on this date. Use Reschedule on this date or pick another grid cell.`,
      }
    }

    payloads.push({
      exam_id: examId.value,
      subject_id: subjectId,
      exam_type_id: form.value.exam_type_id || null,
      exam_date: date,
      start_time: cursorStart,
      end_time: slotEnd,
      duration_minutes: genForm.value.slot_duration,
      batch_id: form.value.batch_id,
      course_id: form.value.course_id,
      class_id: form.value.class_id,
      delivery_mode: wizardDeliveryChannel() === 'online' ? 'online' : effectiveWizardDeliveryMode(),
      status: 'draft',
    })
    preview.push({
      exam_date: date,
      start_time: cursorStart,
      end_time: slotEnd,
      subject_id: subjectId,
      _subject_name: subjects.value.find(s => normId(s.id) === subjectId)?.name || 'Subject',
      _teacher_name: 'Not assigned',
    })
    cursorStart = addMinutesToTime(slotEnd, gapMinutes)
  }

  return {
    mode: 'grid',
    date,
    replaceMode,
    subjectsToRun,
    payloads,
    preview,
  }
}

async function persistPendingRoutines() {
  const plan = pendingRoutinePlan.value
  if (!plan) return 0

  await loadRoutines({ reconcile: false })

  const subjectsToSave = plan.subjectsToRun || selectedSubjectIds.value
  // Only move/reschedule flows may remove the same subject on other dates — never on append.
  if (isGridScheduleContext.value && subjectsToSave.length && plan.replaceMode) {
    await purgeWrongDateRoutinesForSelected(subjectsToSave)
  }

  if (plan.mode === 'grid') {
    if (plan.replaceMode) {
      await deleteAllRoutinesForSelectedSubjects(plan.subjectsToRun, {
        date: plan.date || targetScheduleDate.value,
      })
    }

    let payloadsToCreate = []
    if (plan.replaceMode) {
      const freshPlan = buildGridSlotPlan({
        replaceMode: true,
        force: true,
        subjectIdsOverride: plan.subjectsToRun,
      })
      if (freshPlan.error) {
        throw new Error(freshPlan.error)
      }
      payloadsToCreate = freshPlan.payloads || []
    } else {
      payloadsToCreate = (plan.payloads || []).filter(p =>
        !slotOverlapsExisting(p.exam_date, p.start_time, p.end_time),
      )
    }

    if (!payloadsToCreate.length) {
      clearPendingRoutinePlan()
      return 0
    }

    let created = 0
    const saveErrors = []
    for (const payload of payloadsToCreate) {
      if (!plan.replaceMode && slotOverlapsExisting(payload.exam_date, payload.start_time, payload.end_time)) {
        continue
      }
      try {
        await examService.routines.create(payload)
        created++
      } catch (err) {
        saveErrors.push(extractErrorMessage(err, 'Failed to create routine'))
      }
    }

    if (created === 0) {
      throw new Error(
        saveErrors[0]
          || 'No routines were saved on this date. Remove conflicting drafts from the grid or use Reschedule on this date.',
      )
    }

    await loadRoutines({ includeQuestionCounts: false, reconcile: false })
    const createdSubjectIds = new Set(payloadsToCreate.map(p => normId(p.subject_id)))
    if (createdSubjectIds.size) {
      await applyDefaultMarksToRoutines(routines.value.filter(r =>
        createdSubjectIds.has(normId(r.subject_id || r.subject?.id))
        && formatDateInput(r.exam_date) === formatDateInput(plan.date),
      ))
    }
  } else if (plan.mode === 'generate') {
    if (plan.replaceMode && plan.subjectsToRun?.length) {
      await deleteAllRoutinesForSelectedSubjects(plan.subjectsToRun, {
        date: isGridScheduleContext.value ? (plan.date || targetScheduleDate.value) : null,
      })
    }
    const payload = patchGeneratePayloadForScheduleContext(
      { ...plan.generatePayload },
      { replaceMode: !!plan.replaceMode },
    )
    const res = await examService.routines.generate({
      ...payload,
      apply: true,
    })
    const result = res.data?.data || res.data
    if (result?.error && !(result?.generated || []).length) {
      throw new Error(result.error)
    }
    const generated = result?.generated || []
    if (!generated.length) {
      throw new Error(result?.error || 'No routines were saved from the preview. Try widening the date range or slot duration.')
    }
    const targetDay = formatDateInput(targetScheduleDate.value)
    const wrongDateRows = generated.filter(g =>
      targetDay && formatDateInput(g.exam_date) !== targetDay,
    )
    if (wrongDateRows.length) {
      await enforceTargetDateForSelectedSubjects(plan.subjectsToRun)
      throw new Error(
        `Routine was generated on ${formatDateInput(wrongDateRows[0].exam_date)} instead of ${targetDay}. `
        + 'Click Preview Schedule again, then Save Draft & Continue.',
      )
    }
    await loadRoutines({ reconcile: false })
    const newSubjectIds = new Set(generated.map(g => g.subject_id).filter(Boolean))
    if (newSubjectIds.size) {
      await applyDefaultMarksToRoutines(routines.value.filter(r =>
        newSubjectIds.has(normId(r.subject_id || r.subject?.id))
        && (!targetDay || formatDateInput(r.exam_date) === targetDay),
      ))
    }
    clearPendingRoutinePlan()
    return generated.length
  }

  clearPendingRoutinePlan()
  return 1
}

async function previewGridSlotPlan({ force = false, replaceMode = false } = {}) {
  await loadRoutines({ reconcile: false })

  if (!selectedSubjectIds.value.length) {
    error.value = 'Select at least one subject to preview on this date.'
    return false
  }

  const plan = buildGridSlotPlan({ force, replaceMode })
  if (plan.error) {
    error.value = plan.error
    return false
  }

  pendingRoutinePlan.value = plan
  generatedPreview.value = plan.preview
  lastGeneratedSubjectIds.value = [...plan.subjectsToRun]
  error.value = null

  const scheduleNote = plan.subjectsToRun.length > 1
    ? ' back-to-back after any existing slots'
    : ''
  successMsg.value = replaceMode
    ? `Previewed ${plan.preview.length} replacement slot(s)${scheduleNote}. Continue to Marks to save as draft.`
    : `Previewed ${plan.preview.length} slot(s)${scheduleNote}. Continue to Marks to save as draft (not published yet).`
  return true
}

async function replaceRoutinesAtGridSlot() {
  if (!confirm('Replace routine(s) on this date only? Routines on other dates stay unchanged.')) {
    return
  }
  saving.value = true
  clearMessages()
  generateWarnings.value = []
  try {
    await previewGridSlotPlan({ force: true, replaceMode: true })
  } finally {
    saving.value = false
  }
}

async function createRoutinesAtGridSlot({ force = false } = {}) {
  saving.value = true
  clearMessages()
  generateWarnings.value = []
  try {
    await previewGridSlotPlan({ force })
  } finally {
    saving.value = false
  }
}

async function generateRoutines(replaceMode = false) {
  applyRouteDeliveryMode({ withScheduleDefaults: false })
  if (routeGridScheduleDate.value) {
    restoreRouteGridScheduleDate()
  }
  if (!userEditedScheduleTimes.value) {
    ensureMinimumScheduleWindow()
  }
  if (!canGenerate.value) {
    const window = scheduleWindowMinutes()
    const dur = Number(genForm.value.slot_duration) || 60
    if (window < dur) {
      error.value = validateScheduleCapacity(Math.max(1, selectedSubjectIds.value.length))
    } else {
      error.value = 'Select at least one subject and fill in the schedule fields.'
    }
    return
  }
  if (isGridScheduleContext.value) {
    if (replaceMode) return replaceRoutinesAtGridSlot()
    return createRoutinesAtGridSlot()
  }
  if (formatDateInput(genForm.value.end_date) < formatDateInput(genForm.value.start_date)) {
    error.value = 'End date must be on or after start date.'
    return
  }

  const subjectsToRun = replaceMode
    ? selectedSubjectIds.value
    : newSubjectsToSchedule.value

  if (!subjectsToRun.length) {
    error.value = 'Select at least one subject to schedule.'
    return
  }

  if (replaceMode) {
    if (!confirm('Replace routines only for the selected subjects that already exist? Other subjects stay unchanged.')) {
      return
    }
  }

  const capacityErr = validateScheduleCapacity(subjectsToRun.length)
  if (capacityErr) {
    error.value = capacityErr
    return
  }

  const generatePayload = patchGeneratePayloadForScheduleContext({
    exam_id: examId.value,
    exam_type_id: form.value.exam_type_id || null,
    subject_ids: subjectsToRun,
    start_date: genForm.value.start_date,
    end_date: genForm.value.end_date,
    slot_duration: Number(genForm.value.slot_duration) || 60,
    gap_minutes: Number(genForm.value.gap_minutes) || 0,
    start_time: genForm.value.start_time,
    end_time_limit: genForm.value.end_time_limit || null,
    exclude_days: genForm.value.exclude_days,
    auto_assign_teachers: genForm.value.auto_assign_teachers,
    auto_assign_rooms: genForm.value.auto_assign_rooms,
    apply: false,
    merge_mode: replaceMode ? 'replace' : 'append',
    class_id: form.value.class_id,
    course_id: form.value.course_id,
    batch_id: form.value.batch_id,
    delivery_mode: effectiveWizardDeliveryMode(),
  }, { replaceMode })

  saving.value = true
  clearMessages()
  generateWarnings.value = []
  try {
    const res = await examService.routines.generate(generatePayload)
    const result = res.data?.data || res.data
    if (result?.error) {
      error.value = result.error
      generatedPreview.value = result.generated || []
      generateWarnings.value = result.warnings || []
      return
    }

    const targetDay = formatDateInput(targetScheduleDate.value)
    const generatedRows = result?.generated || []
    const wrongPreview = generatedRows.filter(g =>
      targetDay && formatDateInput(g.exam_date) !== targetDay,
    )
    if (wrongPreview.length) {
      error.value = `Preview used ${formatDateInput(wrongPreview[0].exam_date)} instead of ${targetDay}. `
        + 'Routine date was reset from the grid — click Preview Schedule again.'
      restoreRouteGridScheduleDate()
      return
    }

    const added = generatedRows.length
    const skipped = result?.skipped_existing || 0
    if (added === 0 && skipped > 0) {
      error.value = result?.error || 'No free time slot for the selected subject(s). Widen the daily time window or pick another date.'
      return
    }
    if (added === 0) {
      error.value = result?.error || 'No routines fit the schedule. Try widening the date range or slot duration.'
      return
    }

    pendingRoutinePlan.value = {
      mode: 'generate',
      replaceMode,
      subjectsToRun,
      generatePayload,
      preview: result.generated || [],
    }
    generatedPreview.value = result.generated || []
    generateWarnings.value = result?.warnings || []
    lastGeneratedSubjectIds.value = [...subjectsToRun]
    error.value = null
    successMsg.value = replaceMode
      ? `Previewed ${added} replacement slot(s). Continue to Marks to save as draft.`
      : `Previewed ${added} slot(s). Continue to Marks to save as draft (not published yet).`
  } catch (err) {
    error.value = extractErrorMessage(err, 'Failed to preview routines')
  } finally {
    saving.value = false
  }
}

async function goToStep4() {
  clearMessages()
  saving.value = true

  try {
    applyRouteDeliveryMode({ withScheduleDefaults: false })
    if (routeGridScheduleDate.value) {
      restoreRouteGridScheduleDate()
    }
    await loadRoutines({ includeQuestionCounts: false, reconcile: false })

    const pendingReplaceMode = !!pendingRoutinePlan.value?.replaceMode

    if (isGridScheduleContext.value && selectedSubjectIds.value.length && pendingReplaceMode) {
      await enforceTargetDateForSelectedSubjects()
      await loadRoutines({ includeQuestionCounts: false, reconcile: false })
    }

    const hadPendingPreview = !!pendingRoutinePlan.value?.preview?.length
      && previewMatchesTargetDate()
    if (hadPendingPreview) {
      const wasReplace = !!pendingRoutinePlan.value?.replaceMode
      await persistPendingRoutines()
      if (wasReplace) {
        await enforceTargetDateForSelectedSubjects()
        await loadRoutines({ includeQuestionCounts: false, reconcile: false })
      }
    }

    await loadRoutines({ includeQuestionCounts: false })

    if (isGridScheduleContext.value) {
      if (!selectedSubjectsReadyForMarks()) {
        error.value = scheduleNotReadyError()
        return
      }
    } else if (routinesForMarks.value.length === 0) {
      error.value = 'Preview routines first before continuing.'
      return
    } else if (!selectedSubjectsHaveRoutines.value) {
      error.value = 'Preview routines for newly checked subjects first, or uncheck subjects without routines.'
      return
    }
    step.value = 3
  } catch (err) {
    error.value = extractErrorMessage(err, 'Failed to save routines')
  } finally {
    saving.value = false
  }
}

async function loadRoutines({ includeQuestionCounts = false, reconcile = true } = {}) {
  if (!examId.value) return
  const list = await fetchChannelRoutines()
  const paperType = selectedPaperType.value
  const defaultConfig = markConfigFromExamType(paperType)

  const withCounts = await Promise.all(list.map(async (r) => {
    let questionCount = r.question_count ?? 0
    if (includeQuestionCounts) {
      try {
        const qRes = await examService.routines.getQuestions(r.id)
        const qData = extractData(qRes, {})
        questionCount = (qData.questions || []).length
      } catch {
        questionCount = 0
      }
    }
    return { r, questionCount }
  }))

  routines.value = withCounts.map(({ r, questionCount }) => {
    const cfg = r.mark_config || defaultConfig
    return {
      ...r,
      question_count: questionCount,
      _total_marks: r.total_marks || configTotalMarks(cfg) || 100,
      _pass_marks: r.pass_marks || 40,
      _duration: r.duration_minutes || genForm.value.slot_duration || 60,
      _mcq_marks: cfg?.mcq?.max_marks || 30,
      _cq_marks: cfg?.cq?.max_marks || 50,
      subject_name: r.subject?.name,
    }
  })
  if (reconcile) {
    reconcilePendingPlanWithDb()
  }
}

function buildMarkConfigForRoutine(r) {
  const code = (selectedPaperType.value?.code || '').toUpperCase()
  if (code === 'MCQ') {
    return {
      mcq: { enabled: true, max_marks: r._mcq_marks || 50, pass_marks: r._pass_marks, evaluation: 'auto' },
      cq: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'manual' },
      written: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'manual' },
      practical: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'manual' },
    }
  }
  if (code === 'CQ') {
    return {
      mcq: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'auto' },
      cq: { enabled: true, max_marks: r._cq_marks || 70, pass_marks: r._pass_marks, evaluation: 'manual' },
      written: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'manual' },
      practical: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'manual' },
    }
  }
  return {
    mcq: { enabled: true, max_marks: r._mcq_marks || 30, pass_marks: Math.round((r._pass_marks || 40) * 0.4), evaluation: 'auto' },
    cq: { enabled: true, max_marks: r._cq_marks || 50, pass_marks: Math.round((r._pass_marks || 40) * 0.6), evaluation: 'manual' },
    written: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'manual' },
    practical: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'manual' },
  }
}

async function saveRoutineMarks(r) {
  const markConfig = buildMarkConfigForRoutine(r)
  const total = configTotalMarks(markConfig) || r._total_marks
  await examService.routines.update(r.id, {
    total_marks: total,
    pass_marks: r._pass_marks,
    duration_minutes: r._duration,
    mark_config: markConfig,
  })
  r._total_marks = total
}

async function applyDefaultMarksToRoutines(targetRoutines) {
  if (!targetRoutines?.length) return
  const markConfig = markConfigFromExamType(selectedPaperType.value)
  for (const r of targetRoutines) {
    if (markConfig) {
      r._mcq_marks = markConfig.mcq?.max_marks ?? r._mcq_marks
      r._cq_marks = markConfig.cq?.max_marks ?? r._cq_marks
      r._pass_marks = r._pass_marks || 40
    }
    r._duration = genForm.value.slot_duration || r._duration || 60
    await saveRoutineMarks(r)
  }
  await loadRoutines()
}

function activeSubjectIdsForSync() {
  if (selectedSubjectIds.value.length) {
    return [...selectedSubjectIds.value]
  }
  return routineSubjectIds(routines.value)
}

async function pruneOrphanRoutines() {
  const keepIds = activeSubjectIdsForSync()
  if (!examId.value || !keepIds.length) return 0
  const res = await examService.routines.pruneSubjects(
    examId.value,
    keepIds,
    effectiveWizardDeliveryMode(),
  )
  const data = extractData(res, {})
  return data.removed ?? 0
}

async function continueFromMarks() {
  saving.value = true
  clearMessages()
  try {
    for (const r of routinesForMarks.value) {
      await saveRoutineMarks(r)
    }
    await loadRoutines({ includeQuestionCounts: true })
    successMsg.value = 'Mark distribution saved.'
    step.value = 4
  } catch (err) {
    error.value = extractErrorMessage(err, 'Failed to save mark distribution')
  } finally {
    saving.value = false
  }
}

function openPicker(r) {
  pickerRoutine.value = {
    ...r,
    subject_id: r.subject_id || r.subject?.id,
    subject_name: r.subject?.name || r.subject_name,
    batch_name: r.batch?.name,
  }
}

async function downloadPaper(routineId, variant) {
  try {
    const res = await examService.routines.exportQuestionPaper(routineId, variant)
    const blob = new Blob([res.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `question-paper-${routineId}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    alert('PDF download failed')
  }
}

async function publishExam() {
  saving.value = true
  clearMessages()
  publishDone.value = false
  try {
    applyRouteDeliveryMode({ withScheduleDefaults: false })
    await loadRoutines({ includeQuestionCounts: true })

    // Grid scheduling is additive — never prune routines on other dates/subjects.
    if (!isGridScheduleContext.value) {
      const keepSubjectIds = [
        ...new Set(
          routinesForMarks.value
            .map(r => r.subject_id || r.subject?.id)
            .filter(Boolean)
            .map(normId),
        ),
      ]
      if (keepSubjectIds.length) {
        await examService.routines.pruneSubjects(
          examId.value,
          keepSubjectIds,
          effectiveWizardDeliveryMode(),
        )
        await loadRoutines({ includeQuestionCounts: true })
      }
    }

    if (routinesForMarks.value.length === 0) {
      error.value = 'No routines to publish. Go back to Step 3 and save subject routines as draft.'
      return
    }
    if (requiresQuestions.value && !allRoutinesHaveQuestions.value) {
      const missing = routinesForMarks.value
        .filter(r => !(r.question_count > 0))
        .map(r => r.subject?.name || r.subject_name || 'Subject')
      error.value = `Attach approved questions for: ${missing.join(', ')} (Step 5 — Attach Questions).`
      step.value = 4
      return
    }
    const channel = wizardDeliveryChannel()
    await examService.exams.publish(examId.value, { delivery_channel: channel })
    await examService.routines.publish(examId.value, { delivery_channel: channel })
    publishDone.value = true
    examStatus.value = 'published'
    successMsg.value = 'Exam and routines published successfully!'
  } catch (err) {
    error.value = extractErrorMessage(err, 'Failed to publish')
  } finally {
    saving.value = false
  }
}

watch(step, async (s, prev) => {
  if (s === 2 && scheduleSlotPrefill.value?.date) {
    applyScheduleSlotPrefill()
  }
  if (s === 2 && gridScheduleDate.value) {
    applyGridScheduleDate()
  }
  if (s === 2) {
    restoreRouteGridScheduleDate()
    if (!userEditedScheduleTimes.value) {
      applyScheduleDefaultsForContext({ forceOnlineGrid: false })
    }
  }
  if (s === 2 && examId.value) {
    await loadRoutines({ reconcile: false })
  }
})

watch(
  () => [
    form.value.batch_id,
    genForm.value.start_date,
    genForm.value.end_date,
    genForm.value.start_time,
    genForm.value.end_time,
    genForm.value.slot_duration,
  ],
  () => {
    if (!pendingRoutinePlan.value) return
    clearPendingRoutinePlan()
    generatedPreview.value = []
    successMsg.value = ''
  },
)

watch(() => form.value.start_date, (d) => {
  if (!scheduleSlotPrefill.value?.date || !d) return
  scheduleSlotPrefill.value = { ...scheduleSlotPrefill.value, date: d.slice(0, 10) }
  genForm.value.start_date = d
  genForm.value.end_date = d
})

watch(() => genForm.value.start_date, (d) => {
  if (step.value < 2 || !d) return
  if (routeGridScheduleDate.value && step.value < 2) return
  if (!gridScheduleDate.value) return
  const next = formatDateInput(d)
  if (next && next !== formatDateInput(gridScheduleDate.value)) {
    gridScheduleDate.value = next
    genForm.value.end_date = next
  }
})

watch(() => genForm.value.start_time, (t) => {
  if (!t) return
  const start = t.slice(0, 5)
  const end = (genForm.value.end_time || '').slice(0, 5)
  if (!end || timeToMinutes(end) <= timeToMinutes(start)) {
    genForm.value.end_time = resolveSlotEndTime(start)
  } else {
    genForm.value.slot_duration = timeToMinutes(end) - timeToMinutes(start)
  }
  if (scheduleSlotPrefill.value?.date) {
    scheduleSlotPrefill.value = {
      ...scheduleSlotPrefill.value,
      startTime: start,
      endTime: getSlotEndTime(start),
    }
  }
})

watch(() => genForm.value.end_time, (end) => {
  if (!end || !genForm.value.start_time) return
  const start = genForm.value.start_time.slice(0, 5)
  const endNorm = end.slice(0, 5)
  const diff = timeToMinutes(endNorm) - timeToMinutes(start)
  if (diff > 0) {
    genForm.value.slot_duration = Math.min(1440, Math.max(1, diff))
    if (scheduleSlotPrefill.value?.date) {
      scheduleSlotPrefill.value = {
        ...scheduleSlotPrefill.value,
        endTime: endNorm,
      }
    }
  }
})

watch(() => genForm.value.slot_duration, () => {
  if (!genForm.value.start_time) return
  const start = genForm.value.start_time.slice(0, 5)
  const newEnd = resolveSlotEndTime(start)
  genForm.value.end_time = newEnd
  if (scheduleSlotPrefill.value?.date) {
    scheduleSlotPrefill.value = {
      ...scheduleSlotPrefill.value,
      endTime: newEnd,
    }
  } else {
    ensureMinimumScheduleWindow()
  }
})

onMounted(async () => {
  await loadDeps()
  const qDelivery = route.query.delivery_mode
  const scheduleIntent = route.query.intent === 'schedule'
  if (scheduleIntent) {
    scheduleSlotPrefill.value = parseSlotFromRoute()
  }
  if (typeof qDelivery === 'string' && ['online', 'offline', 'hybrid'].includes(qDelivery)) {
    form.value.delivery_mode = qDelivery
    onDeliveryModeChange()
  }
  const fromGrid = route.query.from === 'grid'
  if (fromGrid) {
    const gridDate = parseGridScheduleDateFromRoute()
    if (gridDate) {
      routeGridScheduleDate.value = gridDate
      gridScheduleDate.value = gridDate
    }
  }
  if (typeof qDelivery === 'string' && ['online', 'offline', 'hybrid'].includes(qDelivery)) {
    routeDeliveryMode.value = qDelivery
  }
  const qid = route.query.exam_id
  if (qid) {
    selectedExamId.value = String(qid)
    await onWizardExamPick()
    if (typeof qDelivery === 'string' && ['online', 'offline', 'hybrid'].includes(qDelivery)) {
      form.value.delivery_mode = qDelivery
      onDeliveryModeChange()
    }
    if (scheduleIntent) {
      await resumeWizardStepForSchedule()
      await applyGridScopeFromRoute()
      applyScheduleSlotPrefill()
    } else if (fromGrid) {
      publishDone.value = false
      clearPendingRoutinePlan()
      generatedPreview.value = []
      generateWarnings.value = []
      step.value = 0
      restoreRouteGridScheduleDate()
      applyRouteDeliveryMode()
    } else {
      await resumeWizardStep()
    }
  }
})
</script>

<style scoped>
.wizard-page { max-width: 800px; margin: 0 auto; padding: 1rem; }
.wizard-header { margin-bottom: 1.5rem; }
.back-link { font-size: 0.85rem; color: #4f46e5; text-decoration: none; }
.wizard-header h1 { margin: 0.5rem 0 0.25rem; font-size: 1.5rem; }
.subtitle { color: var(--text-muted); margin: 0; font-size: 0.9rem; }
.stepper { display: flex; gap: 0.25rem; margin-bottom: 1.5rem; overflow-x: auto; }
.step {
  flex: 1; min-width: 80px; text-align: center; padding: 0.5rem; border-radius: 8px;
  background: #f3f4f6; font-size: 0.75rem; cursor: default;
}
.step.active { background: #4f46e5; color: #fff; }
.step.done { background: #dbeafe; color: #1d4ed8; cursor: pointer; }
.num { display: block; font-weight: 700; font-size: 0.9rem; }
.selected-exam-banner {
  margin-bottom: 1rem;
  padding: 0.65rem 0.85rem;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  border-radius: 8px;
  font-size: 0.9rem;
  color: #3730a3;
}
.step-card {
  background: var(--bg-card); border-radius: 12px; padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.step-card h2 { margin: 0 0 1rem; font-size: 1.1rem; }
.form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; }
.form-control {
  width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border-color);
  border-radius: 8px; font-size: 0.85rem; box-sizing: border-box;
}
.check-row {
  display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem;
  margin: 0.5rem 0 1rem; min-height: 1.75rem; flex-shrink: 0;
}
.check-row input[type="checkbox"] { flex-shrink: 0; }
.hint { color: var(--text-muted); font-size: 0.85rem; margin: 0 0 1rem; }
.step-actions { display: flex; gap: 0.5rem; margin-top: 1.25rem; flex-wrap: wrap; }
.btn {
  padding: 0.55rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600;
  cursor: pointer; border: 1px solid transparent; text-decoration: none;
  display: inline-flex; align-items: center; flex-shrink: 0;
  transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease, opacity 0.15s ease;
}
.btn:active:not(:disabled) { transform: none; opacity: 0.92; }
.btn-primary { background: #4f46e5; color: #fff; border-color: #4f46e5; }
.btn-secondary { background: #6b7280; color: #fff; border-color: var(--text-muted); }
.btn-success { background: #059669; color: #fff; border-color: #059669; }
.btn-outline { background: var(--bg-card); border-color: #d1d5db; color: var(--text-secondary); }
.btn-sm { padding: 0.3rem 0.6rem; font-size: 0.78rem; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.alert-danger { background: #fef2f2; color: #dc2626; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
.routine-row {
  display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-light); flex-wrap: wrap;
}
.small-input { width: 70px; padding: 0.3rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.8rem; }
.summary { background: var(--bg-accent); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
.empty { color: var(--text-muted); font-size: 0.85rem; padding: 1rem 0; }
.direction { color: var(--text-secondary); font-size: 0.88rem; margin: 0 0 1rem; }
.direction-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.85rem; color: #0c4a6e; }
.direction-box ul { margin: 0.35rem 0 0.5rem 1.1rem; padding: 0; }
.field-hint { font-size: 0.75rem; color: var(--text-muted); margin: 0.25rem 0 0; }
.warn-hint { color: #b45309; font-size: 0.85rem; margin: 0.5rem 0 0; }
.q-count-badge { font-size: 0.75rem; padding: 0.15rem 0.45rem; border-radius: 6px; background: #fef3c7; color: #92400e; }
.q-count-badge.ok { background: #d1fae5; color: #065f46; }
.exclude-days { display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; margin-top: 0.35rem; }
.preview-table-wrap { margin: 1rem 0; overflow-x: auto; }
.preview-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.preview-table th, .preview-table td { border: 1px solid var(--border-color); padding: 0.45rem 0.6rem; text-align: left; }
.preview-table th { background: var(--bg-accent); }
.marks-table-head, .marks-row {
  display: grid;
  gap: 0.5rem; align-items: center; padding: 0.35rem 0;
}
.marks-table-head { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border-color); margin-bottom: 0.35rem; }
.empty.warn { color: #b45309; background: #fffbeb; padding: 0.75rem; border-radius: 8px; }
.subject-list {
  border: 1px solid var(--border-color); border-radius: 8px; padding: 0.5rem;
  max-height: 220px; overflow-y: auto; scrollbar-gutter: stable;
  display: flex; flex-direction: column; gap: 0.25rem;
}
.message-slot { min-height: 0; margin-bottom: 0; }
.message-slot.has-message { margin-bottom: 1rem; }
.alert-success { background: #ecfdf5; color: #047857; padding: 0.75rem; border-radius: 8px; }
.subject-check {
  display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem;
  padding: 0.35rem 0.5rem; border-radius: 6px; cursor: pointer;
  min-height: 2rem; flex-shrink: 0;
}
.subject-check input[type="checkbox"] { flex-shrink: 0; }
.subject-check.all { font-weight: 600; border-bottom: 1px solid var(--border-light); margin-bottom: 0.25rem; padding-bottom: 0.5rem; }
.subject-check .code { color: var(--text-muted); font-size: 0.75rem; }
.subject-count-hint { margin: 0 0 0.5rem; }
.success-hint { color: #059669; font-size: 0.85rem; margin: 0.5rem 0 0; }
.slot-prefill-banner {
  margin-bottom: 1rem; padding: 0.65rem 0.85rem;
  background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px;
  font-size: 0.85rem; color: #3730a3;
}
.slot-prefill-hint { color: #6366f1; font-size: 0.78rem; margin-left: 0.35rem; }
.slot-step-hint {
  margin: 0 0 0.75rem; padding: 0.5rem 0.65rem;
  background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px;
  font-size: 0.82rem; color: #166534;
}
.hint-box {
  background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px;
  padding: 0.65rem 0.85rem; font-size: 0.85rem; color: #0c4a6e; margin: 0.75rem 0;
}
.hint-box--preview {
  background: #fffbeb;
  border-color: #fde68a;
  color: #92400e;
}
.scheduled-badge {
  margin-left: auto; font-size: 0.68rem; font-weight: 600;
  padding: 0.1rem 0.4rem; border-radius: 4px;
  background: #d1fae5; color: #065f46;
}
.scheduled-badge.other-date {
  background: #e0e7ff; color: #4338ca;
}
.btn-warn { border-color: #f59e0b; color: #b45309; }
.btn-warn:hover:not(:disabled) { background: #fffbeb; }
</style>

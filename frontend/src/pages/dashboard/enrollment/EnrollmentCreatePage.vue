<template>
  <div class="enrollment-wizard">
    <!-- Header -->
    <div class="wizard-header">
      <h1>📝 New Enrollment</h1>
      <p class="text-muted">Smart enrollment — complete in 5 steps</p>
    </div>

    <!-- Progress Bar -->
    <div class="wizard-progress">
      <div
        v-for="(step, idx) in steps"
        :key="step.key"
        class="progress-step"
        :class="{
          completed: currentStep > idx + 1,
          active: currentStep === idx + 1,
        }"
      >
        <div class="step-circle">
          <span v-if="currentStep > idx + 1">✓</span>
          <span v-else>{{ idx + 1 }}</span>
        </div>
        <span class="step-label">{{ step.label }}</span>
      </div>
      <div class="progress-line">
        <div class="progress-fill" :style="{ width: progressPercent + '%' }"></div>
      </div>
    </div>

    <!-- Step Content -->
    <div class="wizard-body">
      <!-- Step 1: Student -->
      <div v-show="currentStep === 1">
        <StudentSearchCard
          ref="studentCard"
          @student-selected="onStudentSelected"
        />
      </div>

      <!-- Step 2: Course -->
      <div v-show="currentStep === 2">
        <CourseSelectCard
          ref="courseCard"
          :class-id="selectedStudent?.current_class_id || selectedStudent?.current_class?.id"
          :group-id="selectedStudent?._group_id || null"
          :target="null"
          @course-selected="onCourseSelected"
        />
      </div>

      <!-- Step 3: Batch -->
      <div v-show="currentStep === 3">
        <BatchSelectCard
          ref="batchCard"
          :course-id="selectedCourse?.id"
          @batch-selected="onBatchSelected"
          @waitlist-requested="onWaitlistRequested"
        />
      </div>

      <!-- Step 4: Fee -->
      <div v-show="currentStep === 4">
        <FeeBreakdownCard
          :course-id="selectedCourse?.id"
          :student-id="selectedStudent?.id"
          :subject-ids="selectedCourse?.subjects?.filter(s => s.is_mandatory).map(s => s.id) || []"
          @fee-calculated="onFeeCalculated"
          @payment-updated="onPaymentUpdated"
        />
      </div>

      <!-- Step 5: Confirm -->
      <div v-show="currentStep === 5">
        <EnrollmentConfirmCard
          :student="selectedStudent"
          :course="selectedCourse"
          :batch="selectedBatch"
          :fee-data="feeData"
          :payment="paymentInfo"
          :submitting="submitting"
          @confirm="submitEnrollment"
        />
      </div>
    </div>

    <!-- Navigation Buttons -->
    <div class="wizard-footer">
      <button
        v-if="currentStep > 1"
        class="btn btn-outline"
        @click="prevStep"
      >
        ← Back
      </button>
      <div class="spacer"></div>
      <button
        v-if="currentStep < 5"
        class="btn btn-primary"
        :disabled="!canProceed"
        @click="nextStep"
      >
        Continue →
      </button>
    </div>

    <!-- Success Modal -->
    <div v-if="showSuccess" class="success-overlay">
      <div class="success-modal">
        <div class="success-icon">🎉</div>
        <h2>Enrollment {{ enrollmentResult?.status === 'active' ? 'Confirmed!' : 'Created!' }}</h2>
        <p class="enrollment-no">{{ enrollmentResult?.enrollment_no }}</p>
        <div class="success-details">
          <p>{{ selectedStudent?.first_name }} {{ selectedStudent?.last_name }} — {{ selectedCourse?.name }}</p>
          <p>{{ selectedBatch?.name }} ({{ selectedBatch?.mode }})</p>
          <p v-if="enrollmentResult?.status === 'pending'" class="text-warning">
            ⚠️ Pending — Please complete payment of ৳{{ Number(feeData?.payable_fee - (paymentInfo?.amount || 0)).toLocaleString() }}
          </p>
          <p v-else class="text-success">
            ✅ Confirmed — SMS & Email sent to student & guardian
          </p>
        </div>
        <div class="success-actions">
          <button class="btn btn-primary" @click="$router.push('/dashboard/enrollment/enrollments')">
            View All Enrollments
          </button>
          <button class="btn btn-outline" @click="resetWizard">
            New Enrollment
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import enrollmentService from '@/services/enrollment.service'
import StudentSearchCard from '@/components/enrollment/StudentSearchCard.vue'
import CourseSelectCard from '@/components/enrollment/CourseSelectCard.vue'
import BatchSelectCard from '@/components/enrollment/BatchSelectCard.vue'
import FeeBreakdownCard from '@/components/enrollment/FeeBreakdownCard.vue'
import EnrollmentConfirmCard from '@/components/enrollment/EnrollmentConfirmCard.vue'

const router = useRouter()

const steps = [
  { key: 'student', label: 'Student' },
  { key: 'course', label: 'Course' },
  { key: 'batch', label: 'Batch' },
  { key: 'fee', label: 'Fee' },
  { key: 'confirm', label: 'Confirm' },
]

const currentStep = ref(1)
const selectedStudent = ref(null)
const selectedCourse = ref(null)
const selectedBatch = ref(null)
const feeData = ref(null)
const paymentInfo = ref({ amount: 0, method: 'cash', transaction_id: '', reference: '', due_amount: 0 })
const submitting = ref(false)
const showSuccess = ref(false)
const enrollmentResult = ref(null)

const progressPercent = computed(() => ((currentStep.value - 1) / (steps.length - 1)) * 100)

const canProceed = computed(() => {
  switch (currentStep.value) {
    case 1: return !!selectedStudent.value
    case 2: return !!selectedCourse.value
    case 3: return !!selectedBatch.value
    case 4: return !!feeData.value
    default: return true
  }
})

const nextStep = () => {
  if (currentStep.value < 5 && canProceed.value) {
    currentStep.value++
  }
}

const prevStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

const onStudentSelected = (student) => {
  selectedStudent.value = student
  // Reset course/batch/fee when student changes
  selectedCourse.value = null
  selectedBatch.value = null
  feeData.value = null
  paymentInfo.value = { amount: 0, method: 'cash', transaction_id: '', reference: '', due_amount: 0 }
  if (student) nextStep()
}

const onCourseSelected = async (course) => {
  selectedCourse.value = course
  if (!course || !selectedStudent.value) return

  // Check duplicate enrollment
  try {
    const res = await enrollmentService.checkDuplicate(selectedStudent.value.id, course.id)
    const data = res.data?.data || res.data
    if (data?.is_duplicate) {
      const existing = data.existing_enrollment
      alert(`⚠️ This student is already enrolled in this course (${existing?.enrollment_no || 'Active'}). Duplicate enrollment is not allowed.`)
      selectedCourse.value = null
      return
    }
  } catch (e) { /* ignore duplicate check errors */ }

  nextStep()
}

const onBatchSelected = (batch) => {
  selectedBatch.value = batch
  if (batch) nextStep()
}

const waitlisting = ref(false)
const onWaitlistRequested = async (batch) => {
  if (!selectedStudent.value) return
  waitlisting.value = true
  try {
    await enrollmentService.addToWaitingList({
      student_id: selectedStudent.value.id,
      batch_id: batch.id,
    })
    showSuccess.value = true
    enrollmentResult.value = { enrollment_no: 'Waiting List', status: 'waiting' }
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to add to waiting list')
  } finally {
    waitlisting.value = false
  }
}

const onFeeCalculated = (data) => {
  feeData.value = data
}

const onPaymentUpdated = (data) => {
  paymentInfo.value = data
}

const submitEnrollment = async () => {
  if (!selectedStudent.value || !selectedBatch.value) return

  submitting.value = true
  try {
    const student = selectedStudent.value
    const guardian = student?.guardian || {}
    const payload = {
      student_id: student.id,
      batch_id: selectedBatch.value.id,
      enrollment_type: 'new',
      subject_ids: selectedCourse.value?.subjects
        ?.filter(s => s.is_mandatory)
        .map(s => s.id) || [],
      paid_amount: paymentInfo.value.amount || 0,
      payment_method: paymentInfo.value.method || 'cash',
      payment_reference: paymentInfo.value.reference || null,
      payment_transaction_id: paymentInfo.value.transaction_id || null,
      discount_percent: feeData.value?.discount_percent || null,
      discount_id: feeData.value?.applied_discount_id || null,
      guardian_phone: guardian.guardian_phone || guardian.father_phone || student.phone || null,
      guardian_email: guardian.guardian_email || guardian.father_email || null,
    }

    const res = await enrollmentService.enrollStudent(payload)
    enrollmentResult.value = res.data?.data || res.data
    showSuccess.value = true
  } catch (e) {
    console.error(e)
    alert(e.response?.data?.message || 'Enrollment failed. Please try again.')
  } finally {
    submitting.value = false
  }
}

const resetWizard = () => {
  currentStep.value = 1
  selectedStudent.value = null
  selectedCourse.value = null
  selectedBatch.value = null
  feeData.value = null
  paymentInfo.value = { amount: 0, method: 'cash', transaction_id: '', reference: '', due_amount: 0 }
  showSuccess.value = false
  enrollmentResult.value = null
}
</script>

<style scoped>
.enrollment-wizard {
  max-width: 900px;
  margin: 0 auto;
}

.wizard-header {
  margin-bottom: 1.5rem;
}
.wizard-header h1 {
  margin: 0;
  font-size: 1.4rem;
}

/* Progress Bar */
.wizard-progress {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  position: relative;
  padding: 0 0.5rem;
}

.progress-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  z-index: 1;
  position: relative;
}

.step-circle {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.85rem;
  background: #e5e7eb;
  color: var(--text-muted);
  transition: all 0.3s;
}

.progress-step.active .step-circle {
  background: #4a90d9;
  color: white;
  box-shadow: 0 0 0 4px rgba(74,144,217,0.2);
}

.progress-step.completed .step-circle {
  background: #27ae60;
  color: white;
}

.step-label {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 500;
}

.progress-step.active .step-label { color: #4a90d9; font-weight: 600; }
.progress-step.completed .step-label { color: #27ae60; }

.progress-line {
  position: absolute;
  top: 17px;
  left: 25px;
  right: 25px;
  height: 3px;
  background: #e5e7eb;
  z-index: 0;
}

.progress-fill {
  height: 100%;
  background: #27ae60;
  transition: width 0.4s ease;
  border-radius: 2px;
}

/* Body */
.wizard-body {
  min-height: 400px;
  margin-bottom: 1.5rem;
}

/* Footer */
.wizard-footer {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-light);
}
.spacer { flex: 1; }

.btn-outline {
  padding: 0.65rem 1.25rem;
  border: 2px solid #ddd;
  border-radius: 10px;
  background: var(--bg-card);
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 600;
  transition: all 0.2s;
}
.btn-outline:hover { border-color: #4a90d9; color: #4a90d9; }

/* Success Modal */
.success-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}

.success-modal {
  background: var(--bg-card);
  border-radius: 16px;
  padding: 2rem;
  text-align: center;
  max-width: 450px;
  width: 90%;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}

.success-icon { font-size: 3.5rem; margin-bottom: 0.5rem; }
.enrollment-no {
  font-size: 1.3rem;
  font-weight: 700;
  color: #4a90d9;
  font-family: monospace;
  background: #f0f4ff;
  display: inline-block;
  padding: 0.3rem 1rem;
  border-radius: 6px;
  margin: 0.5rem 0;
}

.success-details { margin: 1rem 0; font-size: 0.9rem; }
.success-actions { display: flex; gap: 0.75rem; justify-content: center; }

.text-muted { color: #888; }
.text-warning { color: #f39c12; }
.text-success { color: #27ae60; }
</style>

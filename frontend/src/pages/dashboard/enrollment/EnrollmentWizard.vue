<template>
  <div class="enrollment-wizard">
    <div class="wizard-header">
      <div>
        <h1>{{ isEditMode ? '✏️ Edit Enrollment' : '📝 New Enrollment' }}</h1>
        <p v-if="isEditMode && enrollmentData.enrollment_no" class="text-muted">{{ enrollmentData.enrollment_no }}</p>
      </div>
    </div>

    <EnrollmentStepper
      :steps="stepLabels"
      :currentStep="currentStep"
      :completedSteps="completedSteps"
      @go-step="goToStep"
    />

    <div class="wizard-body">
      <!-- Edit Mode: Loading -->
      <div v-if="isEditMode && editLoading" class="loading-state">
        <div class="spinner"></div>
        <p>Loading enrollment data...</p>
      </div>

      <template v-else>
        <!-- Step 0: Type (only for new enrollment) -->
        <div v-if="!isEditMode && currentStep === 0" class="step-card">
          <h2>Choose Enrollment Type</h2>
          <div class="type-cards">
            <div class="type-card" :class="{ selected: enrollmentType === 'new' }" @click="enrollmentType = 'new'">
              <span class="type-icon">🆕</span><strong>New Student</strong><p>Create from scratch</p>
            </div>
            <div class="type-card" :class="{ selected: enrollmentType === 'existing' }" @click="enrollmentType = 'existing'">
              <span class="type-icon">🔍</span><strong>Existing Student</strong><p>Search & select</p>
            </div>
          </div>
          <div v-if="enrollmentType === 'existing'" class="mt-2">
            <StudentSearchCard @student-selected="onStudentSelected" />
          </div>
          <div class="step-actions">
            <button
              v-if="enrollmentType === 'existing' && selectedStudent"
              class="btn btn-outline"
              @click="currentStep = 3"
            >
              Skip to Course →
            </button>
            <button class="btn btn-primary" :disabled="!canProceed(0)" @click="currentStep = 1">Continue →</button>
          </div>
        </div>

        <!-- Step 1: Student Info (step 0 in edit mode) -->
        <div v-if="currentStep === (isEditMode ? 0 : 1)" class="step-card">
          <h2>👤 Personal & Guardian Info</h2>
          <form @submit.prevent="currentStep = isEditMode ? 1 : 2">
            <div class="form-section"><h4>Photo</h4>
              <div class="photo-upload-section">
                <div class="photo-preview" @click="$refs.studentPhotoInput.click()">
                  <img v-if="studentPhotoPreview" :src="studentPhotoPreview" alt="Student photo" />
                  <div v-else class="photo-placeholder"><span>📷</span><span>Upload photo</span></div>
                </div>
                <input ref="studentPhotoInput" type="file" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" style="display:none" @change="onStudentPhotoChange" />
                <button v-if="studentPhotoFile" type="button" class="btn btn-outline btn-sm" @click="removeStudentPhoto">Remove</button>
              </div>
            </div>
            <div class="form-section"><h4>Personal</h4>
              <div class="form-row">
                <div class="form-group"><label>First Name *</label><input v-model="student.first_name" class="form-input" required /></div>
                <div class="form-group"><label>Last Name</label><input v-model="student.last_name" class="form-input" /></div>
              </div>
              <div class="form-row">
                <div class="form-group"><label>Phone *</label><input v-model="student.phone" class="form-input" required /></div>
                <div class="form-group"><label>Email</label><input v-model="student.email" class="form-input" type="email" /></div>
              </div>
              <div class="form-row">
                <div class="form-group"><label>Gender</label><select v-model="student.gender" class="form-select"><option value="">Select</option><option value="male">Male</option><option value="female">Female</option></select></div>
                <div class="form-group"><label>DOB</label><input v-model="student.date_of_birth" class="form-input" type="date" /></div>
              </div>
            </div>
            <div class="form-section"><h4>🔐 Login Account</h4>
              <p class="text-muted small" style="margin-bottom:8px;font-size:0.78rem;">Set username & password so the student can login to the portal.</p>
              <div class="form-row">
                <div class="form-group"><label>Username</label><input v-model="student.username" class="form-input" placeholder="e.g. student name or ID" /></div>
                <div class="form-group"><label>Password</label><input v-model="student.password" type="password" class="form-input" placeholder="Set a password (min 6 chars)" /></div>
              </div>
            </div>
            <div class="form-section"><h4>👨‍👩‍👧 Guardian</h4>
              <div class="form-row">
                <div class="form-group"><label>Guardian Name *</label><input v-model="guardian.guardian_name" class="form-input" required /></div>
                <div class="form-group"><label>Guardian Phone *</label><input v-model="guardian.guardian_phone" class="form-input" required /></div>
              </div>
              <div class="form-row">
                <div class="form-group"><label>Relation</label><select v-model="guardian.guardian_relation" class="form-select"><option value="">Select</option><option value="Father">Father</option><option value="Mother">Mother</option><option value="Brother">Brother</option><option value="Sister">Sister</option><option value="Other">Other</option></select></div>
                <div class="form-group"><label>Guardian Email</label><input v-model="guardian.guardian_email" class="form-input" /></div>
              </div>
            </div>
            <div class="step-actions"><button type="submit" class="btn btn-primary">Continue →</button></div>
          </form>
        </div>

        <!-- Step 2: Academic (only for new enrollment) -->
        <div v-if="!isEditMode && currentStep === 2" class="step-card">
          <h2>📚 Academic Details</h2>
          <form @submit.prevent="currentStep = 3">
            <div class="form-row">
              <div class="form-group"><label>Class</label>
                <select v-model="academic.current_class_id" class="form-select" @change="onClassChange">
                  <option value="">Select</option>
                  <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div class="form-group"><label>Group</label>
                <select v-if="academic.showGroup" v-model="academic.group_id" class="form-select">
                  <option value="">Select</option>
                  <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                </select>
                <span v-else class="text-muted" style="display:block;padding-top:8px;">N/A (Class ≤ 8)</span>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group"><label>Previous School</label><input v-model="academic.previous_school" class="form-input" /></div>
              <div class="form-group"><label>Previous Result</label><input v-model="academic.ssc_result" class="form-input" type="number" step="0.01" min="0" max="5" /></div>
            </div>
            <div class="form-row">
              <div class="form-group"><label>Present Address</label><input v-model="address.present" class="form-input" /></div>
              <div class="form-group"><label>Permanent Address</label><input v-model="address.permanent" class="form-input" /></div>
            </div>
            <div class="step-actions"><button type="submit" class="btn btn-primary">Continue →</button></div>
          </form>
        </div>

        <!-- Step: Course & Batch (step 1 in edit mode, step 3 in new) -->
        <div v-if="currentStep === (isEditMode ? 1 : 3)" class="step-card course-batch-step">
          <h2>📖 Course & Batch</h2>

          <div class="fee-type-panel">
            <p class="panel-label">💳 Fee Plan</p>
            <div class="fee-type-cards">
              <button type="button" class="fee-plan-card one-time" :class="{ active: feeType === 'one_time' }" @click="feeType = 'one_time'; onFeeTypeChange()">
                <span class="plan-icon">💰</span>
                <span class="plan-title">One-Time</span>
                <span class="plan-desc">Full course fee at once</span>
              </button>
              <button type="button" class="fee-plan-card monthly" :class="{ active: feeType === 'monthly' }" @click="feeType = 'monthly'; onFeeTypeChange()">
                <span class="plan-icon">📅</span>
                <span class="plan-title">Monthly</span>
                <span class="plan-desc">Pay per month by subject</span>
              </button>
            </div>
          </div>

          <div class="picker-section picker-course">
            <div class="picker-head">
              <div class="picker-title">
                <span class="picker-icon course">📚</span>
                <h3>Select Course</h3>
              </div>
              <button v-if="selectedCourse" type="button" class="btn-change" @click="clearCourseSelection">Change</button>
            </div>
            <div v-if="selectedCourse" class="selected-card course-selected">
              <div class="selected-accent" :class="selectedCourse.category === 'academic' ? 'academic' : 'admission'"></div>
              <div class="selected-body">
                <div class="selected-top">
                  <strong>{{ selectedCourse.name }}</strong>
                  <span class="code-badge">{{ selectedCourse.code }}</span>
                  <span :class="['type-badge', selectedCourse.category === 'academic' ? 'academic' : 'admission']">
                    {{ selectedCourse.category === 'academic' ? 'Academic' : 'Admission' }}
                  </span>
                </div>
                <div class="selected-meta">
                  <span v-if="selectedCourse.class" class="meta-item class">🏫 Class {{ selectedCourse.class.name }}</span>
                  <span v-if="selectedCourse.duration_label" class="meta-item duration">⏱ {{ selectedCourse.duration_label }}</span>
                  <span v-if="selectedCourse.group" class="meta-item group">📐 {{ selectedCourse.group.name }}</span>
                </div>
              </div>
              <span class="selected-check">✓</span>
            </div>
            <CourseSelectCard
              v-else
              compact
              embedded
              :class-id="academic.current_class_id || null"
              @course-selected="onCourseSelected"
            />
          </div>

          <div v-if="selectedCourse" class="picker-section picker-batch">
            <div class="picker-head">
              <div class="picker-title">
                <span class="picker-icon batch">📦</span>
                <h3>Select Batch</h3>
              </div>
              <button v-if="selectedBatch" type="button" class="btn-change" @click="clearBatchSelection">Change</button>
            </div>
            <div v-if="selectedBatch" class="selected-card batch-selected">
              <div class="selected-accent" :class="selectedBatch.mode"></div>
              <div class="selected-body">
                <div class="selected-top">
                  <strong>{{ selectedBatch.name }}</strong>
                  <span class="code-badge">{{ selectedBatch.code }}</span>
                  <span :class="['type-badge', selectedBatch.mode]">{{ selectedBatch.mode }}</span>
                </div>
                <div class="selected-meta">
                  <span class="meta-item schedule">📅 {{ selectedBatch.days?.join(', ') || 'TBA' }}</span>
                  <span class="meta-item time">⏰ {{ selectedBatch.start_time }} – {{ selectedBatch.end_time }}</span>
                  <span v-if="selectedBatch.teacher" class="meta-item teacher">👨‍🏫 {{ selectedBatch.teacher.first_name }} {{ selectedBatch.teacher.last_name }}</span>
                  <span class="meta-item seats">🪑 {{ selectedBatch.enrolled_count }}/{{ selectedBatch.capacity }} seats</span>
                </div>
              </div>
              <span class="selected-check">✓</span>
            </div>
            <BatchSelectCard
              v-else
              compact
              embedded
              :key="selectedCourse.id"
              :course-id="selectedCourse.id"
              @batch-selected="onBatchSelected"
              @waitlist-requested="onWaitlistRequested"
            />
          </div>

          <p v-if="duplicateWarning" class="error-msg">{{ duplicateWarning }}</p>

          <div v-if="selectedBatch" class="course-batch-footer">
            <div v-if="feeData" class="fee-panel">
              <div class="fee-panel-header" :class="feeType">
                <span class="fee-panel-icon">{{ feeType === 'monthly' ? '📅' : '💰' }}</span>
                <div>
                  <strong>{{ feeType === 'monthly' ? 'Monthly Fee Breakdown' : 'One-Time Fee Breakdown' }}</strong>
                  <p>Review amounts before continuing to payment</p>
                </div>
              </div>
              <div class="fee-panel-body">
              <div class="fee-line enrollment">
                <span class="fee-label">🎓 Enrollment Fee</span>
                <span class="fee-value purple">৳{{ Number(enrollmentFeeAmount).toLocaleString() }}</span>
              </div>

              <!-- Monthly breakdown with discount -->
              <div v-if="feeType === 'monthly' && feeData.monthly_breakdown" class="monthly-breakdown">
                <p class="breakdown-title">📚 Per-Month Subject Fees</p>
                <div v-for="sub in feeData.monthly_breakdown" :key="sub.id" class="fee-line subject">
                  <span class="fee-label">{{ sub.name }}</span>
                  <span class="fee-value">৳{{ Number(sub.monthly_fee).toLocaleString() }}</span>
                </div>
                <div class="fee-line subtotal">
                  <span class="fee-label">Subtotal / Month</span>
                  <span class="fee-value blue">৳{{ Number(feeData.total_fee).toLocaleString() }}</span>
                </div>
                <div v-if="feeData.discount_percent>0" class="fee-line discount">
                  <span class="fee-label">🏷️ Discount ({{ feeData.discount_reason }})</span>
                  <span class="fee-value green">− ৳{{ Number(feeData.discount_amount).toLocaleString() }} ({{ feeData.discount_percent }}%)</span>
                </div>
                <div class="fee-line subtotal">
                  <span class="fee-label">📘 Course Fee / Month</span>
                  <span class="fee-value blue">৳{{ Number(coursePayableAmount).toLocaleString() }}</span>
                </div>
                <div class="fee-line grand-total">
                  <span class="fee-label">✨ Total Due Now</span>
                  <span class="fee-value total">৳{{ Number(totalDueAtEnrollment).toLocaleString() }}</span>
                </div>
                <p class="fee-hint">Enrollment fee + 1st month course fee</p>
              </div>

              <!-- One-time breakdown with discount -->
              <div v-else>
                <div class="fee-line">
                  <span class="fee-label">📘 Course Fee (One-Time)</span>
                  <span class="fee-value blue">৳{{ Number(feeData.total_fee).toLocaleString() }}</span>
                </div>
                <div v-if="feeData.discount_percent>0" class="fee-line discount">
                  <span class="fee-label">🏷️ Discount ({{ feeData.discount_reason }})</span>
                  <span class="fee-value green">− ৳{{ Number(feeData.discount_amount).toLocaleString() }} ({{ feeData.discount_percent }}%)</span>
                </div>
                <div class="fee-line subtotal">
                  <span class="fee-label">📘 Course Fee Payable</span>
                  <span class="fee-value blue">৳{{ Number(coursePayableAmount).toLocaleString() }}</span>
                </div>
                <div class="fee-line grand-total">
                  <span class="fee-label">✨ Total Due Now</span>
                  <span class="fee-value total">৳{{ Number(totalDueAtEnrollment).toLocaleString() }}</span>
                </div>
                <p class="fee-hint">Course fee portion can be paid later from student portal</p>
              </div>
              </div>
            </div>

            <!-- Discount Selection Section — applies to both fee types -->
            <div v-if="feeData" class="discount-section">
              <div class="discount-header" @click="showDiscountPanel = !showDiscountPanel">
                <span>🏷️ Apply Discount</span>
                <span v-if="selectedDiscountId || manualDiscountPercent" class="discount-badge">1 Applied</span>
                <span class="toggle-icon">{{ showDiscountPanel ? '▲' : '▼' }}</span>
              </div>
              <div v-if="showDiscountPanel" class="discount-panel">
                <p class="discount-note">
                  💡 <strong v-if="feeType === 'monthly'">Monthly fee:</strong>
                  <strong v-else>One-time fee:</strong>
                  Discount can be <strong>flat (fixed amount)</strong> or <strong>percentage</strong>.
                  Flat discount is preferable for monthly fees.
                </p>
                <!-- Pre-defined Discount Rules -->
                <div v-if="discountRules.length > 0" class="discount-rules-list">
                  <p class="discount-section-title">Select a Discount Rule:</p>
                  <div
                    v-for="rule in discountRules"
                    :key="rule.id"
                    class="discount-rule-item"
                    :class="{ selected: selectedDiscountId === rule.id }"
                    @click="onSelectDiscount(rule.id)"
                  >
                    <div class="rule-info">
                      <span class="rule-name">{{ rule.name }}</span>
                      <span class="rule-value">
                        {{ rule.discount_type === 'percentage' ? rule.discount_value + '%' : '৳' + Number(rule.discount_value).toLocaleString() }}
                      </span>
                    </div>
                    <div class="rule-desc">{{ rule.condition_type === 'early_bird' ? 'Early enrollment discount' : rule.condition_type === 'sibling' ? 'Sibling discount' : rule.condition_type === 'loyalty' ? 'Returning student discount' : rule.condition_type === 'merit' ? 'Merit-based scholarship' : rule.condition_type === 'bulk' ? 'Bulk enrollment discount' : rule.condition_type === 'need_based' ? 'Need-based scholarship' : 'Custom discount' }}</div>
                  </div>
                </div>

                <!-- Manual Discount Input -->
                <div class="manual-discount">
                  <p class="discount-section-title">Or enter manual discount:</p>
                  <div class="manual-discount-input">
                    <input
                      v-model.number="manualDiscountPercent"
                      type="number"
                      min="0"
                      max="100"
                      step="1"
                      class="form-input discount-input"
                      placeholder="Discount %"
                      @input="onManualDiscountChange"
                    />
                    <span class="discount-input-suffix">%</span>
                  </div>
                </div>

                <!-- Clear Discount -->
                <button v-if="selectedDiscountId || manualDiscountPercent" class="btn-link clear-discount" @click="onClearDiscount">
                  ✕ Clear Discount
                </button>
              </div>
            </div>

            <button class="btn btn-primary" :disabled="!feeData" @click="goToPaymentStep">Continue →</button>
          </div>
        </div>

        <!-- Step: Payment (step 2 in edit mode, step 4 in new) -->
        <div v-if="currentStep === (isEditMode ? 2 : 4)" class="step-card">
          <h2>💳 Payment</h2>
          <div v-if="feeCalcError" class="status-alert warning">{{ feeCalcError }}</div>
          <div v-if="feeData" class="fee-summary">
            <p v-if="feeType === 'monthly'" class="fee-type-badge monthly">📅 Monthly Fee Plan</p>
            <p v-else class="fee-type-badge one-time">💰 One-Time Fee Plan</p>
            <div class="fee-breakdown">
              <div class="fee-row enrollment-fee-row">
                <span>Enrollment Fee (Admission):</span>
                <span>৳{{ Number(enrollmentFeeAmount).toLocaleString() }}</span>
              </div>
              <div class="fee-row">
                <span>{{ feeType === 'monthly' ? 'Monthly Course Fee (before discount):' : 'Course Fee (One-Time, before discount):' }}</span>
                <span>৳{{ Number(feeData?.total_fee || 0).toLocaleString() }}<span v-if="feeType === 'monthly'"> /month</span></span>
              </div>
              <!-- Discount applies to course fee only, not enrollment fee -->
              <div v-if="feeData?.discount_amount > 0" class="fee-row discount-row">
                <span>
                  Course Fee Discount:
                  <template v-if="feeData.discount_flat_amount > 0 && feeType === 'monthly'">
                    (Flat ৳{{ Number(feeData.discount_flat_amount).toLocaleString() }}/mo)
                  </template>
                  <template v-else-if="feeData.discount_percent > 0">
                    ({{ feeData.discount_percent }}%)
                  </template>
                </span>
                <span class="discount-amount">-৳{{ Number(feeData.discount_amount || 0).toLocaleString() }}</span>
              </div>
              <div class="fee-row">
                <span>{{ feeType === 'monthly' ? 'Course Fee Payable (1st month):' : 'Course Fee Payable:' }}</span>
                <span>৳{{ Number(coursePayableAmount).toLocaleString() }}<span v-if="feeType === 'monthly'"> /month</span></span>
              </div>
              <div class="fee-row total-row">
                <span>Total Due at Enrollment:</span>
                <span>৳{{ Number(totalDueAtEnrollment).toLocaleString() }}</span>
              </div>
              <div v-if="feeType === 'one_time'" class="fee-row text-muted small">
                <span>Course fee portion can be paid later from student portal (Other Fees)</span>
              </div>
              <div v-else class="fee-row text-muted small">
                <span>1st month course fee can be collected now or paid later</span>
              </div>
            </div>
            <p v-if="enrollmentFeeAmount > 0" class="text-muted small">Pay enrollment fee to activate enrollment. Unpaid enrollment fee keeps status pending.</p>
            <p v-if="feeType === 'monthly'" class="text-muted small">First month's course fee can be collected now or paid later.</p>
            <p v-if="feeData?.discount_amount > 0 && feeData?.discount_reason" class="discount-reason-label">Reason: {{ feeData.discount_reason }}</p>
          </div>
          <div v-else class="status-alert warning">Fee could not be calculated. Go back and re-select course/batch.</div>
          <div class="form-group"><label class="form-label">Paying Amount</label><input v-model.number="payment.amount" type="number" class="form-input" min="0" :max="maxPayableAmount" /></div>
          <div class="form-group"><label class="form-label">Method</label>
            <div class="method-chips">
              <button v-for="m in methods" :key="m" type="button" class="method-chip" :class="{active:payment.method===m}" @click="payment.method=m">{{ m }}</button>
            </div>
          </div>
          <div v-if="payment.method!=='cash'" class="form-row">
            <div class="form-group"><label>Transaction ID</label><input v-model="payment.transaction_id" class="form-input" /></div>
          </div>
          <div :class="['status-alert', paymentStatusMessage.type]">
            {{ paymentStatusMessage.text }}
          </div>
          <div class="step-actions">
            <button class="btn btn-primary btn-lg" :disabled="!canSubmitEnrollment" @click="submitEnrollment">
              {{ submitting ? 'Processing...' : (isEditMode ? '💾 Update Enrollment' : '✅ Confirm & Complete') }}
            </button>
            <p v-if="submitError" class="error-msg">{{ submitError }}</p>
          </div>
        </div>
      </template>
    </div>

    <!-- Success -->
    <StepSuccess v-if="showSuccess" :enrollment="result?.enrollment" :student="result?.student" :payment="result?.payment" @new="resetAll" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import EnrollmentStepper from '@/components/enrollment/EnrollmentStepper.vue'
import StudentSearchCard from '@/components/enrollment/StudentSearchCard.vue'
import CourseSelectCard from '@/components/enrollment/CourseSelectCard.vue'
import BatchSelectCard from '@/components/enrollment/BatchSelectCard.vue'
import StepSuccess from '@/components/enrollment/StepSuccess.vue'
import enrollmentService from '@/services/enrollment.service'
import academicService from '@/services/academic.service'
import { buildStudentFormData } from '@/utils/photo.utils'

const router = useRouter()
const route = useRoute()

// Edit mode: if route has :id param, we're editing an existing enrollment
const isEditMode = computed(() => !!route.params.id)
const enrollmentData = ref({})
const editLoading = ref(false)

// Wizard state
const currentStep = ref(0)
const enrollmentType = ref('new')
const selectedStudent = ref(null)
const selectedCourse = ref(null)
const selectedBatch = ref(null)
const feeData = ref(null)
const feeType = ref('one_time')
const submitting = ref(false)
const submitError = ref('')
const showSuccess = ref(false)
const result = ref(null)
const classes = ref([])
const groups = ref([])

// Discount state
const discountRules = ref([])
const selectedDiscountId = ref(null)
const manualDiscountPercent = ref(null)
const discountLoading = ref(false)
const showDiscountPanel = ref(false)
const feeCalcError = ref('')
const duplicateWarning = ref('')

// Form data
const student = reactive({ first_name:'', last_name:'', phone:'', email:'', gender:'', date_of_birth:'', username:'', password:'' })
const guardian = reactive({ guardian_name:'', guardian_phone:'', guardian_relation:'Father', guardian_email:'' })
const academic = reactive({ current_class_id:'', group_id:'', previous_school:'', ssc_result:null, showGroup:false })
const address = reactive({ present:'', permanent:'' })
const payment = reactive({ amount:0, method:'cash', transaction_id:'' })
const studentPhotoFile = ref(null)
const studentPhotoPreview = ref(null)

const onStudentPhotoChange = (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  studentPhotoFile.value = file
  const reader = new FileReader()
  reader.onload = (e) => { studentPhotoPreview.value = e.target.result }
  reader.readAsDataURL(file)
}

const removeStudentPhoto = () => {
  studentPhotoFile.value = null
  studentPhotoPreview.value = null
}

const methods = ['cash','bkash','nagad','rocket','bank']

const resolveEnrollmentFee = () => {
  const fromCalc = feeData.value?.enrollment_fee
  if (fromCalc !== undefined && fromCalc !== null && Number(fromCalc) > 0) {
    return Number(fromCalc)
  }
  return Number(selectedCourse.value?.enrollment_fee || 0)
}

const enrollmentFeeAmount = computed(() => resolveEnrollmentFee())
const coursePayableAmount = computed(() => Number(feeData.value?.course_payable_fee ?? feeData.value?.payable_fee ?? 0))
const totalDueAtEnrollment = computed(() => {
  const fromApi = feeData.value?.due_at_enrollment
  if (fromApi !== undefined && fromApi !== null) {
    return Number(fromApi)
  }
  return enrollmentFeeAmount.value + coursePayableAmount.value
})
const maxPayableAmount = computed(() => totalDueAtEnrollment.value)

const canSubmitEnrollment = computed(() => {
  if (submitting.value || !selectedBatch.value || !feeData.value) return false
  return true
})

const paymentStatusMessage = computed(() => {
  const amount = Number(payment.amount || 0)
  const enrollFee = enrollmentFeeAmount.value
  const coursePayable = coursePayableAmount.value

  if (enrollFee > 0 && amount < enrollFee) {
    return { type: 'warning', text: '⚠️ Enrollment fee not fully paid — status will be pending' }
  }
  if (feeType.value === 'one_time' && amount >= enrollFee && amount < enrollFee + coursePayable) {
    return { type: 'warning', text: '⚠️ Partial course fee — remaining due can be paid from student portal (Other Fees)' }
  }
  if (feeType.value === 'monthly' && amount >= enrollFee && amount < enrollFee + coursePayable) {
    return { type: 'warning', text: '⚠️ First month fee not fully paid — can be paid later' }
  }
  if (amount >= enrollFee + coursePayable && coursePayable > 0) {
    return { type: 'success', text: '✅ Full payment collected — confirm now' }
  }
  if (enrollFee > 0 && amount >= enrollFee) {
    return { type: 'success', text: '✅ Enrollment fee paid — enrollment will be active' }
  }
  if (enrollFee <= 0) {
    return { type: 'success', text: '✅ No enrollment fee — confirm enrollment' }
  }
  return { type: 'warning', text: '⚠️ Review payment amount before confirming' }
})

const stepLabels = computed(() => {
  return isEditMode.value ? ['Student','Course','Payment'] : ['Type','Student','Academic','Course','Payment']
})

const completedSteps = computed(() => {
  const done = []
  if (currentStep.value > 0) done.push(0)
  if (currentStep.value > 1) done.push(1)
  if (currentStep.value > 2) done.push(2)
  if (currentStep.value > 3) done.push(3)
  return done
})

const onStudentSelected = (selected) => {
  selectedStudent.value = selected
  if (selected) {
    // Pre-fill student personal info into the reactive form object
    student.first_name = selected.first_name || ''
    student.last_name = selected.last_name || ''
    student.phone = selected.phone || ''
    student.email = selected.email || ''
    student.gender = selected.gender || ''

    // Format date_of_birth to YYYY-MM-DD for <input type="date">
    if (selected.date_of_birth) {
      const d = new Date(selected.date_of_birth)
      if (!isNaN(d.getTime())) {
        student.date_of_birth = d.toISOString().split('T')[0]
      } else {
        student.date_of_birth = selected.date_of_birth.substring(0, 10)
      }
    } else {
      student.date_of_birth = ''
    }

    // Pre-fill address
    address.present = selected.present_address || selected.address || ''
    address.permanent = selected.permanent_address || ''

    // Pre-fill guardian info — try guardian relation first, then fallback to father/mother fields
    const g = selected.guardian
    if (g) {
      guardian.guardian_name = g.guardian_name || ''
      guardian.guardian_phone = g.guardian_phone || ''
      guardian.guardian_relation = g.guardian_relation || 'Father'
      guardian.guardian_email = g.guardian_email || ''
    } else {
      // Fallback: use father_name as guardian_name, father_phone as guardian_phone
      guardian.guardian_name = selected.father_name || selected.mother_name || ''
      guardian.guardian_phone = selected.father_phone || selected.mother_phone || ''
      guardian.guardian_relation = selected.father_name ? 'Father' : (selected.mother_name ? 'Mother' : 'Father')
      guardian.guardian_email = ''
    }

    // Pre-fill academic info
    if (selected.current_class_id) {
      academic.current_class_id = selected.current_class_id
      // Trigger class change logic to show/hide group dropdown
      onClassChange()
    }
    if (selected.previous_school) {
      academic.previous_school = selected.previous_school
    }
  } else {
    // Clear all fields when selection is cleared
    Object.assign(student, { first_name:'', last_name:'', phone:'', email:'', gender:'', date_of_birth:'' })
    Object.assign(guardian, { guardian_name:'', guardian_phone:'', guardian_relation:'Father', guardian_email:'' })
    Object.assign(address, { present:'', permanent:'' })
    academic.current_class_id = ''
    academic.previous_school = ''
  }
}

const canProceed = (step) => {
  if (step === 0) return enrollmentType.value ? true : false
  return true
}

const onClassChange = () => {
  if (!academic.current_class_id) { academic.showGroup = false; return }
  const cls = classes.value.find(c => c.id === academic.current_class_id)
  const num = parseInt(cls?.numeric_value || cls?.name?.match(/\d+/)?.[0] || 0)
  academic.showGroup = num >= 9
  if (!academic.showGroup) academic.group_id = ''
}

const onFeeTypeChange = async () => {
  // Recalculate fee when fee type changes
  if (selectedCourse.value) {
    await onCourseSelected(selectedCourse.value)
  }
}

function clearCourseSelection() {
  selectedCourse.value = null
  selectedBatch.value = null
  feeData.value = null
  duplicateWarning.value = ''
  submitError.value = ''
}

function clearBatchSelection() {
  selectedBatch.value = null
  feeData.value = null
  submitError.value = ''
}

const onCourseSelected = async (course) => {
  selectedCourse.value = course
  selectedBatch.value = null
  feeData.value = null
  duplicateWarning.value = ''
  if (course) {
    await checkDuplicateEnrollment()
    await recalculateFee()
  }
}

const checkDuplicateEnrollment = async () => {
  duplicateWarning.value = ''
  const studentId = selectedStudent.value?.id || (enrollmentType.value === 'existing' ? null : null)
  if (!studentId || !selectedCourse.value?.id) return
  try {
    const res = await enrollmentService.checkDuplicate(studentId, selectedCourse.value.id)
    const data = res.data?.data || res.data
    if (data?.duplicate || data?.exists) {
      duplicateWarning.value = `Student already enrolled in this course (${data.enrollment_no || 'active'}).`
    }
  } catch {
    // non-blocking
  }
}

const onWaitlistRequested = async (batch) => {
  const studentId = selectedStudent.value?.id
  if (!studentId) {
    submitError.value = 'Select an existing student to join the waitlist.'
    return
  }
  try {
    await enrollmentService.addToWaitingList({ student_id: studentId, batch_id: batch.id })
    submitError.value = ''
    alert('Added to waiting list successfully.')
  } catch (e) {
    submitError.value = e.response?.data?.message || 'Failed to add to waiting list'
  }
}

const onBatchSelected = async (batch) => {
  selectedBatch.value = batch
  if (selectedCourse.value) {
    await recalculateFee()
  }
}

// Discount methods
const onSelectDiscount = async (discountId) => {
  selectedDiscountId.value = discountId
  manualDiscountPercent.value = null // Clear manual discount when selecting a rule
  await recalculateFee()
}

const onClearDiscount = async () => {
  selectedDiscountId.value = null
  manualDiscountPercent.value = null
  await recalculateFee()
}

const onManualDiscountChange = async () => {
  if (manualDiscountPercent.value !== null && manualDiscountPercent.value > 0) {
    selectedDiscountId.value = null // Clear rule selection when using manual
  } else {
    manualDiscountPercent.value = null
  }
  await recalculateFee()
}

const recalculateFee = async () => {
  if (!selectedCourse.value) return
  feeCalcError.value = ''
  try {
    const subjectIds = (selectedCourse.value.subjects || [])
      .filter(s => s.pivot?.is_mandatory ?? s.is_mandatory ?? true)
      .map(s => s.id)
    const params = {
      course_id: selectedCourse.value.id,
      subject_ids: subjectIds,
      fee_type: feeType.value,
    }
    if (selectedStudent.value?.id) {
      params.student_id = selectedStudent.value.id
    }
    if (selectedDiscountId.value) {
      params.discount_id = selectedDiscountId.value
    }
    if (manualDiscountPercent.value !== null && manualDiscountPercent.value > 0) {
      params.discount_percent = manualDiscountPercent.value
    }
    const res = await enrollmentService.calculateFee(params)
    const data = res.data?.data || res.data
    if (data) {
      const courseEnrollFee = Number(selectedCourse.value?.enrollment_fee || 0)
      if ((!data.enrollment_fee || Number(data.enrollment_fee) <= 0) && courseEnrollFee > 0) {
        data.enrollment_fee = courseEnrollFee
      }
      const coursePayable = Number(data.course_payable_fee ?? data.payable_fee ?? 0)
      data.due_at_enrollment = Number(data.enrollment_fee || 0) + coursePayable
      feeData.value = data
    } else {
      feeData.value = null
      feeCalcError.value = 'Fee calculation returned no data.'
    }
  } catch (e) {
    feeCalcError.value = e.response?.data?.message || 'Failed to calculate fee. Please try again.'
    console.error('Fee calculation failed:', e)
  }
}

const setDefaultPaymentAmount = () => {
  if (!feeData.value) return
  if (feeType.value === 'monthly') {
    payment.amount = totalDueAtEnrollment.value
  } else {
    payment.amount = enrollmentFeeAmount.value
  }
}

watch(currentStep, async (step) => {
  const paymentStep = isEditMode.value ? 2 : 4
  if (step === paymentStep && selectedCourse.value) {
    await recalculateFee()
    if (payment.amount === 0) {
      setDefaultPaymentAmount()
    }
  }
})

const fetchDiscountRules = async () => {
  discountLoading.value = true
  try {
    const res = await enrollmentService.getDiscountRules()
    const rules = res.data?.data || res.data || []
    // Only show active discount rules (DB uses 'status' field: 'active'/'inactive')
    discountRules.value = (Array.isArray(rules) ? rules : []).filter(r => r.status === 'active')
  } catch (e) {
    console.warn('Failed to load discount rules:', e)
    discountRules.value = []
  } finally {
    discountLoading.value = false
  }
}

const submitEnrollment = async () => {
  submitting.value = true
  submitError.value = ''
  try {
    if (isEditMode.value) {
      // === EDIT MODE: Update existing enrollment ===
      const enrollmentId = route.params.id
      const updatePayload = {
        batch_id: selectedBatch.value?.id,
        subject_ids: selectedCourse.value?.subjects?.filter(s => s.is_mandatory).map(s => s.id) || [],
        fee_type: feeType.value,
        paid_amount: payment.amount || 0,
        payment_method: payment.method,
        payment_transaction_id: payment.transaction_id || null,
        guardian_phone: guardian.guardian_phone || null,
        guardian_email: guardian.guardian_email || null,
        discount_id: selectedDiscountId.value || null,
        discount_percent: manualDiscountPercent.value || null,
      }
      const eRes = await enrollmentService.updateEnrollment(enrollmentId, updatePayload)
      const updated = eRes.data?.data || eRes.data
      result.value = { enrollment: updated, student: updated?.student }
      showSuccess.value = true
    } else {
      // === NEW ENROLLMENT MODE ===
      // Validate batch belongs to selected course
      if (selectedBatch.value.course_id && selectedBatch.value.course_id !== selectedCourse.value?.id) {
        throw new Error('Selected batch does not belong to the selected course. Please re-select.')
      }

      // 1. Create student
      let studentId
      let createdStudent = null
      if (enrollmentType.value === 'new') {
        const payload = {
          first_name: student.first_name,
          last_name: student.last_name || '',
          phone: student.phone,
          email: student.email || null,
          gender: student.gender || null,
          date_of_birth: student.date_of_birth || null,
          present_address: address.present || null,
          permanent_address: address.permanent || null,
          previous_school: academic.previous_school || null,
          current_class_id: academic.current_class_id || null,
          group_id: academic.group_id || null,
          academic_session_id: academic.academic_session_id || null,
          // Login account fields
          username: student.username || null,
          password: student.password || null,
        }
        const studentBody = studentPhotoFile.value
          ? buildStudentFormData({ ...payload, photo: studentPhotoFile.value })
          : payload
        const sRes = await enrollmentService.createStudentRecord(studentBody)
        createdStudent = sRes.data?.data || sRes.data
        studentId = createdStudent?.id

        // Create guardian record separately
        if (studentId && (guardian.guardian_name || guardian.guardian_phone)) {
          try {
            await enrollmentService.createGuardian(studentId, {
              guardian_name: guardian.guardian_name,
              guardian_phone: guardian.guardian_phone,
              guardian_relation: guardian.guardian_relation,
              guardian_email: guardian.guardian_email || null,
            })
          } catch (gErr) {
            console.warn('Guardian creation failed (non-blocking):', gErr)
          }
        }
      } else {
        studentId = selectedStudent.value?.id
      }

      if (!studentId) throw new Error('Student creation failed')

      // 2. Create enrollment (with discount data)
      const enrollPayload = {
        student_id: studentId,
        course_id: selectedCourse.value?.id,
        batch_id: selectedBatch.value.id,
        subject_ids: selectedCourse.value?.subjects?.filter(s => s.is_mandatory).map(s => s.id) || [],
        fee_type: feeType.value,
        paid_amount: payment.amount || 0,
        payment_method: payment.method,
        payment_transaction_id: payment.transaction_id || null,
        guardian_phone: guardian.guardian_phone || null,
        guardian_email: guardian.guardian_email || null,
        academic_session_id: academic.academic_session_id || null,
        discount_id: selectedDiscountId.value || null,
        discount_percent: manualDiscountPercent.value || null,
      }
      const eRes = await enrollmentService.enrollStudent(enrollPayload)
      const enrollmentData = eRes.data?.data || eRes.data

      result.value = {
        enrollment: enrollmentData,
        student: enrollmentData?.student || createdStudent || selectedStudent.value,
      }
      showSuccess.value = true
    }
  } catch (e) {
    console.error('Enrollment failed:', e)
    submitError.value = e.response?.data?.message || e.message || 'Enrollment failed. Please try again.'
  } finally {
    submitting.value = false
  }
}

const goToStep = (step) => { currentStep.value = step }

const goToPaymentStep = async () => {
  await recalculateFee()
  setDefaultPaymentAmount()
  currentStep.value = isEditMode.value ? 2 : 4
}

const resetAll = () => {
  studentPhotoFile.value = null
  studentPhotoPreview.value = null
  if (isEditMode.value) {
    // In edit mode, go back to the enrollment list
    router.push('/dashboard/enrollment/enrollments')
    return
  }
  currentStep.value = 0
  enrollmentType.value = 'new'
  selectedCourse.value = null
  selectedBatch.value = null
  feeData.value = null
  feeType.value = 'one_time'
  showSuccess.value = false
  result.value = null
  submitError.value = ''
  Object.assign(student, { first_name:'', last_name:'', phone:'', email:'', gender:'', date_of_birth:'', username:'', password:'' })
  Object.assign(guardian, { guardian_name:'', guardian_phone:'', guardian_relation:'Father', guardian_email:'' })
  Object.assign(academic, { current_class_id:'', group_id:'', previous_school:'', ssc_result:null })
  Object.assign(address, { present:'', permanent:'' })
  Object.assign(payment, { amount:0, method:'cash', transaction_id:'' })
}

async function loadEnrollmentForEdit() {
  if (!isEditMode.value) return
  editLoading.value = true
  try {
    const res = await enrollmentService.getEnrollment(route.params.id)
    const enr = res.data?.data || res.data
    enrollmentData.value = enr

    // Pre-fill student info
    if (enr.student) {
      student.first_name = enr.student.first_name || ''
      student.last_name = enr.student.last_name || ''
      student.phone = enr.student.phone || ''
      student.email = enr.student.email || ''
      student.gender = enr.student.gender || ''
      student.date_of_birth = enr.student.date_of_birth || ''
    }

    // Pre-fill guardian info
    guardian.guardian_name = enr.guardian_name || enr.student?.guardian?.guardian_name || ''
    guardian.guardian_phone = enr.guardian_phone || enr.student?.guardian?.guardian_phone || ''
    guardian.guardian_relation = enr.student?.guardian?.guardian_relation || 'Father'
    guardian.guardian_email = enr.guardian_email || enr.student?.guardian?.guardian_email || ''

    // Pre-fill academic info
    if (enr.student) {
      academic.current_class_id = enr.student.current_class_id || ''
      academic.group_id = enr.student.group_id || ''
      academic.previous_school = enr.student.previous_school || ''
    }

    // Pre-fill fee type
    if (enr.fee_type) {
      feeType.value = enr.fee_type
    }

    // Pre-fill course & batch
    if (enr.batch?.course) {
      selectedCourse.value = enr.batch.course
      selectedBatch.value = enr.batch
      // Calculate fee with fee type
      try {
        const feeRes = await enrollmentService.calculateFee({
          course_id: enr.batch.course.id,
          subject_ids: [],
          fee_type: feeType.value
        })
        feeData.value = feeRes.data?.data || feeRes.data
      } catch {}
    }

    // Pre-fill payment
    payment.amount = enr.paid_amount || 0
    payment.method = enr.payment_method || 'cash'
    payment.transaction_id = enr.payment_transaction_id || ''

    // Set enrollment type to existing since student already exists
    enrollmentType.value = 'existing'
    selectedStudent.value = enr.student

    // Skip type step in edit mode
    currentStep.value = 0
  } catch (e) {
    console.error('Failed to load enrollment for edit:', e)
    submitError.value = 'Failed to load enrollment data.'
  } finally {
    editLoading.value = false
  }
}

onMounted(async () => {
  try {
    const [cRes, gRes, sRes] = await Promise.all([
      academicService.getClasses({ per_page: 100 }),
      academicService.getGroups({ per_page: 100 }),
      academicService.sessions.current(),
    ])
    classes.value = cRes.data?.data || cRes.data || []
    groups.value = gRes.data?.data || gRes.data || []
    const sessionData = sRes.data?.data || sRes.data
    if (sessionData?.id) {
      academic.academic_session_id = sessionData.id
    }
  } catch {}

  // Fetch available discount rules for the enrollment wizard
  await fetchDiscountRules()

  // Load existing enrollment data if in edit mode
  await loadEnrollmentForEdit()
})
</script>

<style scoped>
.enrollment-wizard { max-width: 950px; margin: 0 auto; }
.wizard-header { margin-bottom: 0.5rem; }
.wizard-header h1 { margin: 0; font-size: 1.3rem; }
.wizard-header .text-muted { font-size: 0.85rem; margin-top: 0.25rem; }
.wizard-body { min-height: 400px; }

.loading-state { text-align: center; padding: 3rem; }
.spinner { width: 36px; height: 36px; border: 3px solid #eee; border-top-color: #4a90d9; border-radius: 50%; animation: spin 0.7s linear infinite; margin: 0 auto; }
@keyframes spin { to { transform: rotate(360deg); } }

.step-card { background: var(--bg-card); border-radius: 14px; padding: 1.5rem 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 1rem; }
.step-card h2 { margin: 0 0 1rem 0; font-size: 1.15rem; }

.type-cards { display: flex; gap: 1rem; }
.type-card { flex:1; padding:1.5rem; border:2px solid #e5e7eb; border-radius:12px; text-align:center; cursor:pointer; transition:all 0.2s; }
.type-card:hover { border-color:#4a90d9; }
.type-card.selected { border-color:#4a90d9; background:#f0f4ff; }
.type-icon { font-size:2rem; display:block; margin-bottom:0.5rem; }
.type-card strong { display:block; font-size:1rem; }

.form-section { margin-bottom: 1.25rem; }
.form-section h4 { font-size:0.9rem; margin:0 0 0.5rem 0; color:#555; }
.form-row { display:flex; gap:1rem; margin-bottom:0.75rem; }
.form-group { flex:1; }
.form-group label { display:block; font-size:0.8rem; font-weight:600; margin-bottom:0.25rem; color:#555; }
.form-input, .form-select { width:100%; padding:0.55rem; border: 1px solid var(--border-color); border-radius:8px; font-size:0.9rem; }

.method-chips { display:flex; gap:0.5rem; flex-wrap:wrap; }
.method-chip { padding:0.5rem 1rem; border: 1px solid var(--border-color); border-radius:20px; background: var(--bg-card); cursor:pointer; font-size:0.85rem; text-transform:capitalize; }
.method-chip:hover { border-color:#4a90d9; }
.method-chip.active { background:#4a90d9; color:#fff; border-color:#4a90d9; }

.selected-badge { background:#eafaf1; border:1px solid #a3e4b8; border-radius:8px; padding:0.5rem 1rem; margin:0.75rem 0; font-size:0.9rem; display:flex; align-items:center; gap:0.5rem; }
.fee-summary { background: var(--bg-accent); padding:0.75rem 1rem; border-radius:8px; margin:0.5rem 0; }

/* Course & Batch step — business-class UI */
.course-batch-step {
  padding: 1.35rem 1.5rem;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  border: 1px solid var(--border-color);
}
.course-batch-step > h2 {
  color: var(--text-primary);
  font-size: 1.2rem;
  border-bottom: 2px solid #4f46e5;
  padding-bottom: 0.5rem;
  display: inline-block;
  margin-bottom: 1rem;
}

.panel-label {
  margin: 0 0 0.6rem;
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--text-secondary);
}
.fee-type-panel { margin-bottom: 1.1rem; }
.fee-type-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.fee-plan-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.15rem;
  padding: 0.85rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  background: var(--bg-card);
  cursor: pointer;
  text-align: left;
  transition: all 0.2s;
}
.fee-plan-card:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08); }
.fee-plan-card.one-time.active { border-color: #d97706; background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); box-shadow: 0 4px 14px rgba(217, 119, 6, 0.2); }
.fee-plan-card.monthly.active { border-color: #2563eb; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); box-shadow: 0 4px 14px rgba(37, 99, 235, 0.2); }
.plan-icon { font-size: 1.35rem; }
.plan-title { font-size: 0.95rem; font-weight: 700; color: var(--text-primary); }
.plan-desc { font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; }

.picker-section {
  margin-top: 1.1rem;
  padding: 1rem;
  border-radius: 12px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}
.picker-course { border-left: 4px solid #4f46e5; }
.picker-batch { border-left: 4px solid #0891b2; }
.picker-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
.picker-title { display: flex; align-items: center; gap: 0.55rem; }
.picker-icon {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 8px;
  font-size: 1rem;
}
.picker-icon.course { background: #eef2ff; border: 1px solid #c7d2fe; }
.picker-icon.batch { background: #ecfeff; border: 1px solid #a5f3fc; }
.picker-head h3 { margin: 0; font-size: 0.95rem; font-weight: 700; color: var(--text-primary); }
.btn-change {
  padding: 0.35rem 0.75rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: #4f46e5;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-change:hover { background: #4f46e5; color: #fff; }

.selected-card {
  display: flex;
  align-items: stretch;
  gap: 0.75rem;
  padding: 0.85rem 1rem;
  border-radius: 10px;
  background: var(--bg-card);
  border: 2px solid #c7d2fe;
  box-shadow: 0 2px 8px rgba(79, 70, 229, 0.12);
}
.selected-accent { width: 5px; border-radius: 4px; flex-shrink: 0; }
.selected-accent.academic { background: linear-gradient(180deg, #3b82f6, #1d4ed8); }
.selected-accent.admission { background: linear-gradient(180deg, #f59e0b, #d97706); }
.selected-accent.online { background: linear-gradient(180deg, #06b6d4, #0891b2); }
.selected-accent.offline { background: linear-gradient(180deg, #10b981, #059669); }
.selected-accent.hybrid { background: linear-gradient(180deg, #8b5cf6, #7c3aed); }
.selected-body { flex: 1; min-width: 0; }
.selected-top { display: flex; align-items: center; flex-wrap: wrap; gap: 0.45rem 0.6rem; }
.selected-top strong { font-size: 1rem; color: var(--text-primary); font-weight: 700; }
.code-badge {
  padding: 0.15rem 0.5rem;
  background: var(--bg-accent);
  border: 1px solid var(--border-strong);
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-secondary);
  font-family: ui-monospace, monospace;
}
.type-badge {
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: capitalize;
}
.type-badge.academic { background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd; }
.type-badge.admission { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; }
.type-badge.online { background: #cffafe; color: #0e7490; border: 1px solid #67e8f9; }
.type-badge.offline { background: #d1fae5; color: #047857; border: 1px solid #6ee7b7; }
.type-badge.hybrid { background: #ede9fe; color: #6d28d9; border: 1px solid #c4b5fd; }
.selected-meta { display: flex; flex-wrap: wrap; gap: 0.4rem 0.65rem; margin-top: 0.5rem; }
.meta-item {
  padding: 0.25rem 0.55rem;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text-secondary);
  background: var(--bg-surface-muted);
  border: 1px solid var(--border-color);
}
.meta-item.class { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
.meta-item.duration { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.meta-item.schedule { background: #fef3c7; border-color: #fde68a; color: #92400e; }
.meta-item.time { background: #fce7f3; border-color: #fbcfe8; color: #9d174d; }
.meta-item.teacher { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
.meta-item.seats { background: #ecfeff; border-color: #a5f3fc; color: #0e7490; }
.selected-check {
  width: 28px; height: 28px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 50%;
  background: #4f46e5;
  color: #fff;
  font-weight: 700;
  font-size: 0.85rem;
  flex-shrink: 0;
  align-self: center;
}

.course-batch-footer { margin-top: 1.1rem; }
.fee-panel {
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--border-color);
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
  margin-bottom: 0.85rem;
}
.fee-panel-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem 1rem;
  color: #fff;
}
.fee-panel-header.one_time { background: linear-gradient(135deg, #d97706 0%, #b45309 100%); }
.fee-panel-header.monthly { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
.fee-panel-icon { font-size: 1.5rem; }
.fee-panel-header strong { display: block; font-size: 0.95rem; }
.fee-panel-header p { margin: 0.15rem 0 0; font-size: 0.75rem; opacity: 0.9; }
.fee-panel-body { padding: 0.85rem 1rem; background: var(--bg-card); }
.fee-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0.65rem;
  margin-bottom: 0.35rem;
  border-radius: 8px;
  background: var(--bg-surface-muted);
  border: 1px solid var(--border-light);
}
.fee-line.subject { background: var(--bg-card); border-color: #e2e8f0; }
.fee-line.enrollment { background: #f5f3ff; border-color: #ddd6fe; }
.fee-line.discount { background: #ecfdf5; border-color: #a7f3d0; }
.fee-line.subtotal { background: #eff6ff; border-color: #bfdbfe; }
.fee-line.grand-total {
  background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
  border: none;
  margin-top: 0.5rem;
  padding: 0.65rem 0.85rem;
}
.fee-label { font-size: 0.82rem; font-weight: 600; color: var(--text-secondary); }
.fee-line.grand-total .fee-label { color: #e2e8f0; }
.fee-value { font-size: 0.88rem; font-weight: 700; color: var(--text-primary); }
.fee-value.purple { color: #7c3aed; }
.fee-value.blue { color: #2563eb; }
.fee-value.green { color: #059669; }
.fee-value.total { font-size: 1.1rem; color: #fbbf24; }
.fee-hint { margin: 0.5rem 0 0; font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; }
.breakdown-title { font-size: 0.8rem; font-weight: 700; color: #1e40af; margin: 0.35rem 0 0.5rem; }

/* Fee Type Selector (other steps) */
.fee-type-selector { margin-bottom: 1.25rem; }
.form-label { display:block; font-size:0.8rem; font-weight:600; margin-bottom:0.5rem; color:#555; }
.fee-type-chips { display:flex; gap:0.75rem; }
.fee-type-chip { flex:1; display:flex; flex-direction:column; align-items:center; gap:0.25rem; padding:1rem; border:2px solid #e5e7eb; border-radius:12px; background: var(--bg-card); cursor:pointer; transition:all 0.2s; }
.fee-type-chip:hover { border-color:#4a90d9; }
.fee-type-chip.active { border-color:#4a90d9; background:#f0f4ff; }
.chip-icon { font-size:1.5rem; }
.chip-label { font-weight:700; font-size:0.95rem; }
.chip-desc { font-size:0.75rem; color:#888; }

/* Fee type badges */
.fee-type-badge { display:inline-block; padding:0.25rem 0.75rem; border-radius:20px; font-size:0.8rem; font-weight:600; margin-bottom:0.5rem; }
.fee-type-badge.monthly { background:#e8f4fd; color:#2980b9; border:1px solid #b3d9f2; }
.fee-type-badge.one-time { background:#fef9e7; color:#d4a017; border:1px solid #f9e79f; }

/* Monthly breakdown */
.monthly-breakdown { margin-top:0.75rem; border-top: 1px solid var(--border-color); padding-top:0.75rem; }
.breakdown-title { font-size:0.8rem; font-weight:600; color:#555; margin-bottom:0.5rem; }
.breakdown-row { display:flex; justify-content:space-between; padding:0.25rem 0; font-size:0.85rem; }
.breakdown-total { display:flex; justify-content:space-between; padding:0.5rem 0 0 0; margin-top:0.25rem; border-top:1px dashed #ddd; font-size:0.9rem; }
.fee-amount-row { display:flex; justify-content:space-between; padding:0.25rem 0; font-size:0.85rem; }
.enrollment-fee-row { color:#7c3aed; font-weight:600; }
.fee-discount-row { display:flex; justify-content:space-between; padding:0.25rem 0; font-size:0.85rem; color:#27ae60; }
.discount-amount { font-weight:600; }
.fee-total-row { display:flex; justify-content:space-between; padding:0.5rem 0 0 0; margin-top:0.25rem; border-top:1px dashed #ddd; font-size:0.95rem; }
.fee-grand-total-row { display:flex; justify-content:space-between; padding:0.6rem 0 0 0; margin-top:0.35rem; border-top:2px solid #4a90d9; font-size:1rem; color:#1e3a5f; }
.fee-grand-hint { margin:0.35rem 0 0; font-size:0.75rem; color: var(--text-label); }
.small { font-size:0.8rem; }

/* Discount Section */
.discount-section { margin:0.75rem 0; border:2px solid #c4b5fd; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(124,58,237,0.1); }
.discount-header { display:flex; align-items:center; gap:0.5rem; padding:0.7rem 1rem; background:linear-gradient(135deg,#f5f3ff 0%,#ede9fe 100%); cursor:pointer; user-select:none; font-size:0.88rem; font-weight:700; color:#5b21b6; }
.discount-header:hover { background:linear-gradient(135deg,#ede9fe 0%,#ddd6fe 100%); }
.discount-badge { background:#4a90d9; color:#fff; font-size:0.7rem; padding:0.15rem 0.5rem; border-radius:10px; font-weight:700; }
.toggle-icon { margin-left:auto; font-size:0.75rem; color: var(--text-muted); }
.discount-panel { padding:0.75rem 1rem; border-top: 1px solid var(--border-color); background: var(--bg-card); }
.discount-section-title { font-size:0.8rem; font-weight:600; color:#555; margin:0 0 0.5rem 0; }
.discount-rules-list { margin-bottom:0.75rem; padding-bottom:0.75rem; border-bottom:1px dashed #eee; }
.discount-rule-item { display:flex; flex-direction:column; padding:0.5rem 0.75rem; margin-bottom:0.35rem; border: 1px solid var(--border-color); border-radius:8px; cursor:pointer; transition:all 0.15s; }
.discount-rule-item:hover { border-color:#4a90d9; background:#f0f4ff; }
.discount-rule-item.selected { border-color:#4a90d9; background:#e8f0fe; }
.rule-info { display:flex; justify-content:space-between; align-items:center; }
.rule-name { font-size:0.85rem; font-weight:600; color: var(--text-dark); }
.rule-value { font-size:0.8rem; font-weight:700; color:#4a90d9; background:#e8f0fe; padding:0.15rem 0.5rem; border-radius:6px; }
.rule-desc { font-size:0.75rem; color:#888; margin-top:0.2rem; }
.manual-discount { margin-bottom:0.5rem; }
.manual-discount-input { display:flex; align-items:center; gap:0.5rem; }
.discount-input { width:100px !important; text-align:center; }
.discount-input-suffix { font-size:0.85rem; font-weight:600; color:#555; }
.clear-discount { font-size:0.8rem; color:#e74c3c; margin-top:0.25rem; display:inline-block; }
.discount-note { font-size:0.78rem; color:#888; background:#fef9e7; padding:0.4rem 0.6rem; border-radius:6px; margin-bottom:0.75rem; border:1px solid #f9e79f; }

/* Fee breakdown in payment step */
.fee-breakdown { margin:0.5rem 0; }
.fee-row { display:flex; justify-content:space-between; padding:0.3rem 0; font-size:0.85rem; color:#555; }
.fee-row.discount-row { color:#27ae60; }
.fee-row.total-row { border-top:2px solid #4a90d9; margin-top:0.25rem; padding-top:0.5rem; font-size:0.95rem; font-weight:700; color: var(--text-dark); }
.discount-amount { font-weight:600; }
.discount-reason-label { font-size:0.78rem; color:#888; margin-top:0.25rem; font-style:italic; }

.status-alert { padding:0.75rem; border-radius:10px; font-size:0.85rem; font-weight:600; margin:1rem 0; }
.status-alert.success { background:#eafaf1; color:#27ae60; }
.status-alert.warning { background:#fff8e1; color:#f39c12; }

.step-actions { text-align:right; margin-top:1rem; }
.btn-primary { background:#4a90d9; color:#fff; border:none; padding:0.7rem 1.5rem; border-radius:10px; cursor:pointer; font-size:0.95rem; font-weight:600; }
.btn-primary:disabled { opacity:0.5; cursor:not-allowed; }
.btn-lg { padding:0.85rem 2rem; font-size:1rem; font-weight:700; }
.btn-link { color:#e74c3c; background:none; border:none; cursor:pointer; font-size:0.8rem; text-decoration:underline; }
.error-msg { color:#e74c3c; font-size:0.85rem; margin-top:0.5rem; }
.text-muted { color:#888; }
.mt-2 { margin-top:1rem; }
.photo-upload-section { display:flex; flex-direction:column; align-items:flex-start; gap:8px; }
.photo-preview { width:96px; height:96px; border-radius:10px; border:2px dashed #cbd5e1; overflow:hidden; cursor:pointer; background: var(--bg-surface-muted); }
.photo-preview img { width:100%; height:100%; object-fit:cover; }
.photo-placeholder { width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; color: var(--text-muted); font-size:11px; }
.btn-sm { padding:0.35rem 0.75rem; font-size:0.8rem; }
</style>

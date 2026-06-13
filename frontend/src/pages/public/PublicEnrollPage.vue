<template>
  <div class="pe-page">
    <PublicHero :title="t('nav.apply')" :subtitle="isBn() ? 'অনলাইনে ভর্তি হও — ক্যাশ অথবা অনলাইন পেমেন্টে।' : 'Enroll online — pay by cash or online.'" />

    <section class="pub-section pe-matte">
      <div class="pub-container pe-wrap">
        <div class="pe-tabs">
          <button type="button" :class="{ active: tab === 'apply' }" @click="tab = 'apply'">{{ isBn() ? 'ভর্তি হও' : 'Enroll now' }}</button>
          <button type="button" :class="{ active: tab === 'track' }" @click="tab = 'track'">{{ t('nav.trackApplication') }}</button>
        </div>

        <!-- ============ APPLY ============ -->
        <div v-if="tab === 'apply'" class="pe-panel pub-card">
          <!-- SUCCESS -->
          <div v-if="success" class="pe-success">
            <div class="pe-success-ic" :class="success.login_active ? 'ok' : 'wait'">{{ success.login_active ? '✓' : '⏳' }}</div>
            <h2>{{ success.login_active ? (isBn() ? 'ভর্তি নিশ্চিত হয়েছে!' : 'Enrollment confirmed!') : (isBn() ? 'আবেদন জমা হয়েছে' : 'Application submitted') }}</h2>
            <p class="pe-enrollno-label">{{ isBn() ? 'এনরোলমেন্ট নম্বর' : 'Enrollment number' }}</p>
            <p class="enroll-no">{{ success.enrollment_no }}</p>

            <div v-if="success.fees" class="pe-feecard">
              <h4>{{ isBn() ? 'ফি হিসাব' : 'Fee summary' }}</h4>
              <div class="pe-fee-row"><span>{{ isBn() ? 'ভর্তি ফি' : 'Admission fee' }}</span><b>৳ {{ fmt(success.fees.enrollment_fee) }}</b></div>
              <div class="pe-fee-row paid"><span>{{ isBn() ? 'ভর্তি ফি কেটে নেওয়া হয়েছে' : 'Enrollment fee deducted' }}</span><b>− ৳ {{ fmt(success.fees.enrollment_fee_paid) }}</b></div>
              <div class="pe-fee-row"><span>{{ isBn() ? 'কোর্স ফি' : 'Course fee' }}</span><b>৳ {{ fmt(success.fees.course_payable) }}</b></div>
              <div class="pe-fee-row total"><span>{{ isBn() ? 'মোট পরিশোধিত' : 'Total paid' }}</span><b>৳ {{ fmt(success.fees.total_paid) }}</b></div>
              <div class="pe-fee-row"><span>{{ isBn() ? 'বাকি (সেন্টারে প্রদেয়)' : 'Due (at centre)' }}</span><b>৳ {{ fmt(success.fees.due) }}</b></div>
            </div>

            <div v-if="success.credentials" class="pe-creds">
              <h4>{{ isBn() ? 'তোমার স্টুডেন্ট পোর্টাল লগইন' : 'Your student portal login' }}</h4>
              <div class="pe-cred-row"><span>{{ isBn() ? 'ইউজারনেম' : 'Username' }}</span><code>{{ success.credentials.username }}</code></div>
              <div v-if="success.credentials.password" class="pe-cred-row"><span>{{ isBn() ? 'পাসওয়ার্ড' : 'Password' }}</span><code>{{ success.credentials.password }}</code></div>
              <p class="pe-cred-note">
                {{ success.login_active
                  ? (isBn() ? 'এই তথ্য সংরক্ষণ করো। এখনই লগইন করতে পারবে।' : 'Save these — you can log in now.')
                  : (isBn() ? 'এই তথ্য সংরক্ষণ করো। অ্যাডমিন অনুমোদনের পর লগইন করতে পারবে।' : 'Save these — you can log in after admin approval.') }}
              </p>
            </div>

            <p v-else class="text-muted">
              {{ isBn()
                ? 'অ্যাডমিন অনুমোদন করলে তোমার ভর্তি নিশ্চিত হবে এবং লগইন তথ্য সক্রিয় হবে।'
                : 'Once the admin approves, your enrollment will be confirmed and login will be activated.' }}
            </p>

            <div class="pe-success-actions">
              <a :href="success.pdf_url" target="_blank" rel="noopener" class="pub-btn pub-btn--primary">⬇ {{ isBn() ? 'PDF ডাউনলোড' : 'Download PDF' }}</a>
              <router-link v-if="success.login_active" to="/login" class="pub-btn pub-btn--accent">{{ isBn() ? 'লগইন করো' : 'Login now' }}</router-link>
              <button type="button" class="pub-btn pub-btn--ghost" @click="resetApply">{{ isBn() ? 'আরেকটি ভর্তি' : 'Enroll another' }}</button>
            </div>
          </div>

          <!-- FORM -->
          <form v-else @submit.prevent="submitApply" autocomplete="off">
            <!-- Honeypot (hidden from humans) -->
            <input v-model="form.website" class="pe-hp" type="text" tabindex="-1" autocomplete="off" aria-hidden="true" />

            <!-- Step 1: program -->
            <section class="pe-section">
              <h3><span class="pe-step-no">1</span>{{ isBn() ? 'প্রোগ্রাম' : 'Program' }}</h3>

              <!-- Locked to a single course (came from course detail page) -->
              <div v-if="lockedCourse" class="pe-locked">
                <div class="pe-locked-ic">🎯</div>
                <div class="pe-locked-info">
                  <strong>{{ lockedCourse.name }}</strong>
                  <span v-if="lockedCourse.class">{{ lockedCourse.class.name }}</span>
                </div>
                <router-link to="/courses" class="pe-locked-change">{{ isBn() ? 'অন্য কোর্স' : 'Change' }}</router-link>
              </div>

              <template v-else>
                <div v-if="loadingCourses" class="pe-loading">{{ t('state.loading') }}</div>
                <div v-else class="course-grid">
                  <button
                    v-for="c in courses"
                    :key="c.id"
                    type="button"
                    class="course-card"
                    :class="{ active: form.course_id === c.id }"
                    @click="selectCourse(c)"
                  >
                    <strong>{{ c.name }}</strong>
                    <span v-if="c.class">{{ c.class.name }}</span>
                  </button>
                </div>
              </template>
            </section>

            <!-- Step 2: batch -->
            <section v-if="form.course_id" class="pe-section">
              <h3><span class="pe-step-no">2</span>{{ isBn() ? 'ব্যাচ নির্বাচন করো' : 'Select batch' }}</h3>
              <div v-if="loadingBatches" class="pe-loading">{{ t('state.loading') }}</div>
              <div v-else-if="!batches.length" class="pe-empty">{{ isBn() ? 'এই মুহূর্তে কোনো ওপেন ব্যাচ নেই।' : 'No open batches right now.' }}</div>
              <div v-else class="batch-grid">
                <button
                  v-for="b in batches"
                  :key="b.id"
                  type="button"
                  class="batch-card"
                  :class="{ active: form.batch_id === b.id, full: b.is_full }"
                  :disabled="b.is_full"
                  @click="form.batch_id = b.id"
                >
                  {{ b.name }}
                  <span v-if="b.is_full">({{ isBn() ? 'পূর্ণ' : 'Full' }})</span>
                  <span v-else-if="b.available_seats != null">{{ b.available_seats }} {{ t('course.seatsLeft') }}</span>
                </button>
              </div>
            </section>

            <!-- Step 3: fee plan + preview -->
            <section v-if="form.course_id" class="pe-section">
              <h3><span class="pe-step-no">3</span>{{ isBn() ? 'ফি প্ল্যান' : 'Fee plan' }}</h3>
              <div class="fee-toggle">
                <button type="button" :class="{ active: feeType === 'one_time' }" @click="feeType = 'one_time'">
                  {{ isBn() ? 'এককালীন (ফুল কোর্স)' : 'One-time (full course)' }}
                </button>
                <button type="button" :class="{ active: feeType === 'monthly' }" @click="feeType = 'monthly'">
                  {{ isBn() ? 'মাসিক' : 'Monthly' }}
                </button>
              </div>

              <div class="fee-box" :class="{ loading: loadingFee }">
                <template v-if="feePreview">
                  <ul v-if="feePreview.subjects?.length" class="fee-subjects">
                    <li v-for="s in feePreview.subjects" :key="s.id">
                      <span>{{ s.name }}</span>
                      <b>৳ {{ fmt(feeType === 'monthly' ? s.monthly_fee : s.fee) }}</b>
                    </li>
                  </ul>
                  <div class="fee-line">
                    <span>{{ isBn() ? 'ভর্তি ফি (এককালীন)' : 'Admission fee (one-time)' }}</span>
                    <b>৳ {{ fmt(feePreview.enrollment_fee) }}</b>
                  </div>
                  <div class="fee-line">
                    <span>{{ feeType === 'monthly' ? (isBn() ? 'কোর্স ফি / মাস' : 'Course fee / month') : (isBn() ? 'কোর্স ফি' : 'Course fee') }}</span>
                    <b>৳ {{ fmt(feePreview.course_payable_fee) }}</b>
                  </div>
                  <div v-if="feePreview.discount_amount > 0" class="fee-line discount">
                    <span>{{ isBn() ? 'ছাড়' : 'Discount' }} <small v-if="feePreview.discount_reason">({{ feePreview.discount_reason }})</small></span>
                    <b>− ৳ {{ fmt(feePreview.discount_amount) }}</b>
                  </div>
                  <div class="fee-line total">
                    <span>{{ isBn() ? 'এখন প্রদেয়' : 'Payable now' }}</span>
                    <b>৳ {{ fmt(amountNow) }}</b>
                  </div>
                  <p class="fee-note">
                    {{ isBn()
                      ? 'অনলাইন পেমেন্টে এই অংকটি এখনই দিতে হবে; ক্যাশে সেন্টারে দিতে পারবে।'
                      : 'For online payment this amount is due now; for cash you can pay at the centre.' }}
                  </p>
                </template>
                <p v-else-if="loadingFee" class="fee-note">{{ t('state.loading') }}</p>
              </div>
            </section>

            <!-- Step 4: student -->
            <section class="pe-section">
              <h3><span class="pe-step-no">4</span>{{ isBn() ? 'শিক্ষার্থীর তথ্য' : 'Student details' }}</h3>
              <div class="form-row">
                <div class="form-group">
                  <label>{{ isBn() ? 'নাম (প্রথম অংশ)' : 'First name' }} *</label>
                  <input v-model="form.first_name" class="form-input" maxlength="120" required />
                </div>
                <div class="form-group">
                  <label>{{ isBn() ? 'নাম (শেষ অংশ)' : 'Last name' }}</label>
                  <input v-model="form.last_name" class="form-input" maxlength="120" />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>{{ t('contact.phone') }} *</label>
                  <input v-model="form.phone" class="form-input" inputmode="tel" maxlength="20" required />
                </div>
                <div class="form-group">
                  <label>{{ t('contact.email') }}</label>
                  <input v-model="form.email" type="email" class="form-input" maxlength="255" />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>{{ isBn() ? 'জন্ম তারিখ' : 'Date of birth' }}</label>
                  <input v-model="form.date_of_birth" type="date" class="form-input" />
                </div>
                <div class="form-group">
                  <label>{{ isBn() ? 'লিঙ্গ' : 'Gender' }}</label>
                  <select v-model="form.gender" class="form-input">
                    <option value="male">{{ isBn() ? 'ছেলে' : 'Male' }}</option>
                    <option value="female">{{ isBn() ? 'মেয়ে' : 'Female' }}</option>
                    <option value="other">{{ isBn() ? 'অন্যান্য' : 'Other' }}</option>
                  </select>
                </div>
              </div>
            </section>

            <!-- Step 5: guardian -->
            <section class="pe-section">
              <h3><span class="pe-step-no">5</span>{{ isBn() ? 'অভিভাবকের তথ্য' : 'Guardian details' }}</h3>
              <div class="form-row">
                <div class="form-group">
                  <label>{{ isBn() ? 'অভিভাবকের নাম' : 'Guardian name' }} *</label>
                  <input v-model="form.guardian_name" class="form-input" maxlength="160" required />
                </div>
                <div class="form-group">
                  <label>{{ isBn() ? 'সম্পর্ক' : 'Relationship' }}</label>
                  <select v-model="form.relationship" class="form-input">
                    <option value="father">{{ isBn() ? 'বাবা' : 'Father' }}</option>
                    <option value="mother">{{ isBn() ? 'মা' : 'Mother' }}</option>
                    <option value="brother">{{ isBn() ? 'ভাই' : 'Brother' }}</option>
                    <option value="sister">{{ isBn() ? 'বোন' : 'Sister' }}</option>
                    <option value="other">{{ isBn() ? 'অন্যান্য' : 'Other' }}</option>
                  </select>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>{{ isBn() ? 'অভিভাবকের ফোন' : 'Guardian phone' }} *</label>
                  <input v-model="form.guardian_phone" class="form-input" inputmode="tel" maxlength="20" required />
                </div>
                <div class="form-group">
                  <label>{{ isBn() ? 'অভিভাবকের ইমেইল' : 'Guardian email' }}</label>
                  <input v-model="form.guardian_email" type="email" class="form-input" maxlength="255" />
                </div>
              </div>
            </section>

            <!-- Step 6: login credentials -->
            <section class="pe-section">
              <h3><span class="pe-step-no">6</span>{{ isBn() ? 'লগইন তথ্য (ঐচ্ছিক)' : 'Login credentials (optional)' }}</h3>
              <p class="pe-hint">{{ isBn() ? 'খালি রাখলে সিস্টেম স্বয়ংক্রিয়ভাবে তৈরি করে দেবে।' : 'Leave blank and the system will generate them for you.' }}</p>
              <div class="form-row">
                <div class="form-group">
                  <label>{{ isBn() ? 'ইউজারনেম' : 'Username' }}</label>
                  <input v-model="form.username" class="form-input" maxlength="60" autocomplete="off" placeholder="e.g. rahim2026" />
                </div>
                <div class="form-group">
                  <label>{{ isBn() ? 'পাসওয়ার্ড' : 'Password' }}</label>
                  <input v-model="form.password" type="password" class="form-input" minlength="6" maxlength="64" autocomplete="new-password" />
                </div>
              </div>
            </section>

            <!-- Step 7: payment -->
            <section class="pe-section">
              <h3><span class="pe-step-no">7</span>{{ isBn() ? 'পেমেন্ট পদ্ধতি' : 'Payment method' }}</h3>
              <div class="pay-options">
                <button type="button" class="pay-opt" :class="{ active: payMode === 'cash' }" @click="payMode = 'cash'">
                  <span class="pay-ic">🏫</span>
                  <span class="pay-txt">
                    <strong>{{ isBn() ? 'ক্যাশ (সেন্টারে)' : 'Cash (at center)' }}</strong>
                    <small>{{ isBn() ? 'অ্যাডমিন অনুমোদনের পর নিশ্চিত' : 'Confirmed after admin approval' }}</small>
                  </span>
                </button>
                <button type="button" class="pay-opt" :class="{ active: payMode === 'online' }" @click="payMode = 'online'">
                  <span class="pay-ic">⚡</span>
                  <span class="pay-txt">
                    <strong>{{ isBn() ? 'অনলাইন পেমেন্ট' : 'Pay online now' }}</strong>
                    <small>{{ isBn() ? 'সাথে সাথে ভর্তি ও লগইন' : 'Instant enrollment & login' }}</small>
                  </span>
                </button>
              </div>

              <div v-if="payMode === 'online'" class="method-chips">
                <button
                  v-for="m in onlineMethods"
                  :key="m.key"
                  type="button"
                  class="method-chip"
                  :class="{ active: form.payment_method === m.key }"
                  @click="form.payment_method = m.key"
                >{{ m.label }}</button>
              </div>
              <p v-if="payMode === 'online'" class="pe-sandbox-note">{{ isBn() ? 'ডেমো মোড: পেমেন্ট সিমুলেট করা হবে।' : 'Demo mode: payment will be simulated.' }}</p>
            </section>

            <p v-if="applyError" class="pe-error">{{ applyError }}</p>
            <button type="submit" class="pub-btn pub-btn--primary pe-submit" :disabled="submitting || !canSubmit">
              {{ submitting ? t('action.sending') : (payMode === 'online' ? (isBn() ? 'পেমেন্ট করে ভর্তি হও' : 'Pay & enroll') : (isBn() ? 'আবেদন জমা দাও' : 'Submit application')) }}
            </button>
          </form>
        </div>

        <!-- ============ TRACK ============ -->
        <div v-else class="pe-panel pub-card">
          <form @submit.prevent="trackStatus">
            <div class="form-group">
              <label>{{ t('track.placeholder') }}</label>
              <input v-model="trackNo" class="form-input" placeholder="e.g. CMS-2026-0001" required />
            </div>
            <button type="submit" class="pub-btn pub-btn--primary pe-submit" :disabled="tracking">{{ t('track.check') }}</button>
          </form>
          <div v-if="trackResult" class="track-result">
            <p><strong>{{ isBn() ? 'স্ট্যাটাস' : 'Status' }}:</strong> <span class="track-badge" :class="trackResult.status">{{ trackResult.status }}</span></p>
            <p><strong>{{ isBn() ? 'শিক্ষার্থী' : 'Student' }}:</strong> {{ trackResult.student?.first_name }} {{ trackResult.student?.last_name }}</p>
            <p><strong>{{ isBn() ? 'ব্যাচ' : 'Batch' }}:</strong> {{ trackResult.batch?.name }}</p>
            <p><strong>{{ t('nav.courses') }}:</strong> {{ trackResult.batch?.course?.name }}</p>
            <p v-if="trackResult.login_ready"><strong>{{ isBn() ? 'লগইন ইউজারনেম' : 'Login username' }}:</strong> {{ trackResult.login_username }}</p>
            <div class="track-actions">
              <a :href="pdfUrl(trackResult.enrollment_no)" target="_blank" rel="noopener" class="pub-btn pub-btn--ghost">⬇ PDF</a>
              <router-link v-if="trackResult.login_ready" to="/login" class="pub-btn pub-btn--accent">{{ isBn() ? 'লগইন' : 'Login' }}</router-link>
            </div>
          </div>
          <p v-if="trackError" class="pe-error">{{ trackError }}</p>
        </div>

        <footer class="pe-footer">
          <router-link to="/login">{{ isBn() ? 'স্টাফ লগইন' : 'Staff login' }}</router-link>
        </footer>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import enrollmentService from '@/services/enrollment.service'
import { useLang } from '@/composables/useLang'
import PublicHero from '@/components/public/PublicHero.vue'

const { t, isBn } = useLang()
const route = useRoute()

const tab = ref('apply')
const courses = ref([])
const batches = ref([])
const lockedCourse = ref(null)
const loadingCourses = ref(false)
const loadingBatches = ref(false)
const submitting = ref(false)
const applyError = ref('')
const success = ref(null)
const payMode = ref('cash')
const feeType = ref('one_time')
const feePreview = ref(null)
const loadingFee = ref(false)

const onlineMethods = [
  { key: 'bkash', label: 'bKash' },
  { key: 'nagad', label: 'Nagad' },
  { key: 'rocket', label: 'Rocket' },
  { key: 'card', label: isBn() ? 'কার্ড' : 'Card' },
]

const form = reactive({
  course_id: '',
  batch_id: '',
  class_id: '',
  group_id: '',
  first_name: '',
  last_name: '',
  phone: '',
  email: '',
  date_of_birth: '',
  gender: 'male',
  guardian_name: '',
  guardian_phone: '',
  guardian_email: '',
  relationship: 'father',
  username: '',
  password: '',
  payment_method: 'bkash',
  website: '', // honeypot
})

const trackNo = ref('')
const tracking = ref(false)
const trackResult = ref(null)
const trackError = ref('')

const amountNow = computed(() => {
  if (!feePreview.value) return 0
  const ef = Number(feePreview.value.enrollment_fee || 0)
  const payable = Number(feePreview.value.payable_fee || feePreview.value.course_payable_fee || 0)
  return ef > 0 ? ef : payable
})

const canSubmit = computed(() =>
  form.course_id && form.batch_id && form.class_id
  && form.first_name?.trim() && form.phone?.trim()
  && form.guardian_name?.trim() && form.guardian_phone?.trim()
  && (payMode.value === 'cash' || !!form.payment_method),
)

function fmt(n) {
  return Number(n || 0).toLocaleString(isBn() ? 'bn-BD' : 'en-US')
}

function pdfUrl(no) {
  return `/api/v1/enrollment/public/${no}/pdf`
}

async function loadCourses() {
  loadingCourses.value = true
  try {
    const res = await enrollmentService.getPublicCourses()
    courses.value = res.data?.data || res.data || []
  } catch {
    courses.value = []
    applyError.value = 'Could not load courses. Try again later.'
  } finally {
    loadingCourses.value = false
  }
}

// Locked flow: only the chosen course + its batches are shown.
async function loadLockedCourse(courseId) {
  loadingBatches.value = true
  try {
    const res = await enrollmentService.getPublicCourseDetails(courseId)
    const course = res.data?.data || res.data
    if (!course) {
      await loadCourses()
      return
    }
    lockedCourse.value = course
    form.course_id = course.id
    form.class_id = course.class_id || course.class?.id || ''
    form.group_id = course.group_id || course.group?.id || ''
    batches.value = (course.batches || []).map((b) => ({
      ...b,
      is_full: b.available_seats != null ? b.available_seats <= 0 : b.is_full,
    }))
    await loadFee()
  } catch {
    await loadCourses()
  } finally {
    loadingBatches.value = false
  }
}

async function selectCourse(course) {
  form.course_id = course.id
  form.class_id = course.class_id || course.class?.id || ''
  form.group_id = course.group_id || course.group?.id || ''
  form.batch_id = ''
  batches.value = []
  loadingBatches.value = true
  try {
    const res = await enrollmentService.getPublicBatches(course.id)
    batches.value = res.data?.data || res.data || []
  } catch {
    batches.value = []
  } finally {
    loadingBatches.value = false
  }
  await loadFee()
}

async function loadFee() {
  if (!form.course_id) {
    feePreview.value = null
    return
  }
  loadingFee.value = true
  try {
    const res = await enrollmentService.calculatePublicFee({
      course_id: form.course_id,
      fee_type: feeType.value,
    })
    feePreview.value = res.data?.data || res.data
  } catch {
    feePreview.value = null
  } finally {
    loadingFee.value = false
  }
}

watch(feeType, loadFee)

async function submitApply() {
  submitting.value = true
  applyError.value = ''
  try {
    const payOnline = payMode.value === 'online'
    const res = await enrollmentService.applyOnline({
      first_name: form.first_name.trim(),
      last_name: form.last_name?.trim() || '-',
      phone: form.phone.trim(),
      email: form.email || null,
      date_of_birth: form.date_of_birth || null,
      gender: form.gender || 'male',
      guardian_name: form.guardian_name.trim(),
      guardian_phone: form.guardian_phone.trim(),
      guardian_email: form.guardian_email || null,
      relationship: form.relationship || 'father',
      class_id: form.class_id,
      group_id: form.group_id || null,
      course_id: form.course_id,
      batch_id: form.batch_id,
      fee_type: feeType.value,
      username: form.username?.trim() || null,
      password: form.password || null,
      payment_method: payOnline ? form.payment_method : 'cash',
      pay_online: payOnline,
      website: form.website || '', // honeypot
    })
    const data = res.data?.data || res.data
    success.value = {
      enrollment_no: data?.enrollment_no || 'Submitted',
      status: data?.status,
      login_active: !!data?.login_active,
      fees: data?.fees || null,
      credentials: data?.credentials || null,
      pdf_url: data?.pdf_url || pdfUrl(data?.enrollment_no),
    }
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    applyError.value = e.response?.data?.message || 'Enrollment failed. Please try again.'
  } finally {
    submitting.value = false
  }
}

async function trackStatus() {
  tracking.value = true
  trackError.value = ''
  trackResult.value = null
  try {
    const res = await enrollmentService.trackEnrollment(trackNo.value.trim())
    trackResult.value = res.data?.data || res.data
  } catch (e) {
    trackError.value = e.response?.data?.message || 'Enrollment not found'
  } finally {
    tracking.value = false
  }
}

function resetApply() {
  success.value = null
  payMode.value = 'cash'
  feeType.value = 'one_time'
  Object.assign(form, {
    batch_id: '',
    first_name: '', last_name: '', phone: '', email: '',
    date_of_birth: '', gender: 'male',
    guardian_name: '', guardian_phone: '', guardian_email: '', relationship: 'father',
    username: '', password: '', payment_method: 'bkash', website: '',
  })
  // Keep the locked course selection if any; otherwise clear the picker.
  if (!lockedCourse.value) {
    form.course_id = ''
    form.class_id = ''
    form.group_id = ''
    batches.value = []
    feePreview.value = null
  } else {
    loadFee()
  }
}

onMounted(async () => {
  const cid = route.query.course
  if (cid) {
    await loadLockedCourse(cid)
  } else {
    await loadCourses()
  }
})
</script>

<style scoped>
.pe-wrap { max-width: 760px; margin: 0 auto; }
.pe-matte { background: var(--pub-bg); }
.pe-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; }
.pe-tabs button {
  flex: 1; padding: 0.7rem; border-radius: var(--pub-radius-sm); border: 1.5px solid var(--pub-border);
  background: var(--pub-surface); color: var(--pub-text-soft); cursor: pointer; font-weight: 700; font-family: inherit;
  transition: all 0.16s;
}
.pe-tabs button.active { background: var(--pub-accent); color: #fff; border-color: var(--pub-accent); }
.pe-panel { padding: 1.75rem; }
.pe-section { margin-bottom: 1.6rem; }
.pe-section h3 { margin: 0 0 0.85rem; font-size: 1rem; font-weight: 800; color: var(--pub-text); display: flex; align-items: center; gap: 0.55rem; }
.pe-step-no { width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; background: var(--pub-accent); color: #fff; font-size: 0.82rem; flex: none; }
.pe-hint { font-size: 0.8rem; color: var(--pub-text-muted); margin: -0.35rem 0 0.6rem; }

/* honeypot */
.pe-hp { position: absolute !important; left: -9999px !important; width: 1px; height: 1px; opacity: 0; pointer-events: none; }

/* locked course banner */
.pe-locked {
  display: flex; align-items: center; gap: 0.85rem; padding: 0.9rem 1.1rem;
  border: 1.5px solid var(--pub-accent); border-radius: var(--pub-radius-md); background: var(--pub-accent-soft);
}
.pe-locked-ic { font-size: 1.5rem; }
.pe-locked-info { display: flex; flex-direction: column; flex: 1; }
.pe-locked-info strong { color: var(--pub-text); }
.pe-locked-info span { font-size: 0.8rem; color: var(--pub-text-muted); }
.pe-locked-change { font-size: 0.82rem; font-weight: 700; color: var(--pub-accent); text-decoration: none; }

.course-grid, .batch-grid { display: grid; gap: 0.6rem; }
.course-grid { grid-template-columns: repeat(2, 1fr); }
.course-card, .batch-card {
  text-align: left; padding: 0.85rem; border-radius: var(--pub-radius-sm); border: 1.5px solid var(--pub-border);
  background: var(--pub-surface-2); color: var(--pub-text); cursor: pointer; font-family: inherit; transition: all 0.16s;
}
.course-card strong { display: block; }
.course-card span, .batch-card span { font-size: 0.8rem; color: var(--pub-text-muted); }
.course-card:hover, .batch-card:hover { border-color: var(--pub-accent); }
.course-card.active, .batch-card.active { border-color: var(--pub-accent); background: var(--pub-accent-soft); color: var(--pub-accent); }
.course-card.active span, .batch-card.active span { color: var(--pub-accent); }
.batch-card.full { opacity: 0.5; cursor: not-allowed; }

/* fee plan */
.fee-toggle { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.85rem; }
.fee-toggle button {
  padding: 0.6rem; border-radius: var(--pub-radius-sm); border: 1.5px solid var(--pub-border);
  background: var(--pub-surface-2); color: var(--pub-text-soft); font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.16s;
}
.fee-toggle button.active { border-color: var(--pub-accent); background: var(--pub-accent-soft); color: var(--pub-accent); }
.fee-box { border: 1.5px solid var(--pub-border); border-radius: var(--pub-radius-md); padding: 1rem 1.1rem; background: var(--pub-surface-2); transition: opacity 0.16s; }
.fee-box.loading { opacity: 0.55; }
.fee-subjects { list-style: none; margin: 0 0 0.6rem; padding: 0 0 0.6rem; border-bottom: 1px dashed var(--pub-border); }
.fee-subjects li { display: flex; justify-content: space-between; padding: 0.2rem 0; font-size: 0.86rem; color: var(--pub-text-soft); }
.fee-line { display: flex; justify-content: space-between; padding: 0.32rem 0; font-size: 0.9rem; color: var(--pub-text-soft); }
.fee-line b { color: var(--pub-text); }
.fee-line.discount b { color: var(--pub-accent-2, #0ea882); }
.fee-line.total { margin-top: 0.4rem; padding-top: 0.6rem; border-top: 1.5px solid var(--pub-border); font-size: 1.02rem; }
.fee-line.total span, .fee-line.total b { color: var(--pub-accent); font-weight: 800; }
.fee-note { font-size: 0.74rem; color: var(--pub-text-muted); margin: 0.5rem 0 0; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; }
.form-group { margin-bottom: 0.75rem; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 0.3rem; color: var(--pub-text-soft); }
.form-input {
  width: 100%; padding: 0.65rem 0.8rem; border: 1.5px solid var(--pub-border); border-radius: var(--pub-radius-sm);
  background: var(--pub-surface-2); color: var(--pub-text); font-size: 0.92rem; font-family: inherit;
}
.form-input:focus { outline: none; border-color: var(--pub-accent); }

/* payment */
.pay-options { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.pay-opt {
  display: flex; align-items: center; gap: 0.7rem; text-align: left; padding: 0.9rem 1rem;
  border-radius: var(--pub-radius-sm); border: 1.5px solid var(--pub-border); background: var(--pub-surface-2);
  color: var(--pub-text); cursor: pointer; font-family: inherit; transition: all 0.16s;
}
.pay-opt:hover { border-color: var(--pub-accent); }
.pay-opt.active { border-color: var(--pub-accent); background: var(--pub-accent-soft); }
.pay-ic { font-size: 1.5rem; }
.pay-txt strong { display: block; font-size: 0.92rem; }
.pay-txt small { color: var(--pub-text-muted); font-size: 0.74rem; }
.method-chips { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.85rem; }
.method-chip {
  padding: 0.5rem 1rem; border-radius: var(--pub-radius-pill); border: 1.5px solid var(--pub-border);
  background: var(--pub-surface); color: var(--pub-text-soft); font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.16s;
}
.method-chip.active { background: var(--pub-accent); border-color: var(--pub-accent); color: #fff; }
.pe-sandbox-note { margin-top: 0.5rem; font-size: 0.76rem; color: var(--pub-text-muted); }

.pe-submit { width: 100%; margin-top: 0.5rem; }
.pe-error { color: #ef4444; margin-top: 0.5rem; font-size: 0.88rem; }

/* success */
.pe-success { text-align: center; padding: 1rem 0; }
.pe-success-ic { width: 70px; height: 70px; border-radius: 50%; display: grid; place-items: center; margin: 0 auto 1rem; font-size: 2rem; }
.pe-success-ic.ok { background: var(--pub-accent-2-soft); color: var(--pub-accent-2); }
.pe-success-ic.wait { background: #fef3c7; color: #b45309; }
.pe-success h2 { color: var(--pub-text); margin: 0 0 0.5rem; }
.pe-success p { color: var(--pub-text-muted); margin: 0.25rem 0; }
.pe-enrollno-label { font-size: 0.82rem; }
.enroll-no { font-size: 1.6rem; font-weight: 800; color: var(--pub-accent) !important; margin: 0.25rem 0 1rem !important; letter-spacing: 1px; }
.pe-feecard { max-width: 380px; margin: 1rem auto; padding: 1rem 1.25rem; border: 1.5px solid var(--pub-border); border-radius: var(--pub-radius-md); background: var(--pub-surface-2); text-align: left; }
.pe-feecard h4 { margin: 0 0 0.6rem; color: var(--pub-text); font-size: 0.92rem; text-align: center; }
.pe-fee-row { display: flex; justify-content: space-between; padding: 0.3rem 0; font-size: 0.88rem; color: var(--pub-text-soft); }
.pe-fee-row b { color: var(--pub-text); }
.pe-fee-row.paid b { color: var(--pub-accent-2, #0ea882); }
.pe-fee-row.total { margin-top: 0.35rem; padding-top: 0.55rem; border-top: 1.5px solid var(--pub-border); }
.pe-fee-row.total span, .pe-fee-row.total b { color: var(--pub-accent); font-weight: 800; }
.pe-creds { max-width: 360px; margin: 1rem auto; padding: 1rem 1.25rem; border: 1.5px dashed var(--pub-accent); border-radius: var(--pub-radius-md); background: var(--pub-accent-soft); text-align: left; }
.pe-creds h4 { margin: 0 0 0.6rem; color: var(--pub-accent); font-size: 0.92rem; text-align: center; }
.pe-cred-row { display: flex; justify-content: space-between; align-items: center; padding: 0.35rem 0; }
.pe-cred-row span { font-size: 0.82rem; color: var(--pub-text-soft); }
.pe-cred-row code { font-size: 1rem; font-weight: 800; color: var(--pub-text); background: var(--pub-surface); padding: 0.15rem 0.6rem; border-radius: 6px; }
.pe-cred-note { font-size: 0.74rem !important; color: var(--pub-text-muted) !important; margin-top: 0.5rem !important; }
.pe-success-actions { display: flex; flex-wrap: wrap; gap: 0.6rem; justify-content: center; margin-top: 1.25rem; }

.pe-footer { text-align: center; margin-top: 1.5rem; font-size: 0.88rem; }
.pe-footer a { color: var(--pub-accent); text-decoration: none; font-weight: 600; }
.pe-loading, .pe-empty { color: var(--pub-text-muted); font-size: 0.9rem; }
.track-result { margin-top: 1rem; padding: 1rem 1.25rem; background: var(--pub-surface-2); border-radius: var(--pub-radius-sm); border: 1px solid var(--pub-border); }
.track-result p { margin: 0.35rem 0; color: var(--pub-text-soft); }
.track-result strong { color: var(--pub-text); }
.track-badge { text-transform: capitalize; padding: 0.1rem 0.6rem; border-radius: 10px; font-size: 0.78rem; font-weight: 700; background: #fef3c7; color: #b45309; }
.track-badge.active { background: var(--pub-accent-2-soft); color: var(--pub-accent-2); }
.track-actions { display: flex; gap: 0.5rem; margin-top: 0.75rem; }
.text-muted { color: var(--pub-text-muted); font-size: 0.85rem; }
@media (max-width: 600px) {
  .form-row, .course-grid, .pay-options, .fee-toggle { grid-template-columns: 1fr; }
}
</style>

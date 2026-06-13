<template>
  <div class="page-container">
    <div class="page-header">
      <h1 class="page-title">Collect Fee</h1>
      <p class="page-subtitle">Search for a student and collect fees</p>
    </div>

    <!-- Step 1: Search Student -->
    <div class="card search-card">
      <div class="search-section">
        <div class="search-input-wrapper">
          <span class="search-icon">&#128269;</span>
          <input
            v-model="searchQuery"
            type="text"
            class="search-input"
            placeholder="Search by student name or Student ID..."
            @input="onSearchInput"
            @keydown.enter="searchStudent"
          >
          <button
            v-if="searchQuery"
            class="search-clear-btn"
            @click="clearSearch"
          >
            &#10005;
          </button>
        </div>
        <button
          class="search-btn"
          @click="searchStudent"
          :disabled="!searchQuery.trim() || searching"
        >
          <span v-if="searching" class="spinner"></span>
          <span v-else>Search</span>
        </button>
      </div>

      <!-- Search Results Dropdown -->
      <div v-if="searchResults.length > 0 && !selectedStudent" class="search-results">
        <div
          v-for="result in searchResults"
          :key="result.student_id || result.id"
          class="search-result-item"
          @click="selectStudent(result)"
        >
          <div class="result-avatar">
            {{ getInitials(result.first_name + ' ' + result.last_name) }}
          </div>
          <div class="result-info">
            <div class="result-name">{{ result.first_name }} {{ result.last_name }}</div>
            <div class="result-details">
              <span class="result-id">ID: {{ result.student_id }}</span>
              <span class="result-separator">&bull;</span>
              <span class="result-course">{{ result.course_name || result.course || 'N/A' }}</span>
            </div>
          </div>
          <div class="result-arrow">&rarr;</div>
        </div>
      </div>

      <!-- No Results -->
      <div v-if="searchPerformed && searchResults.length === 0 && !selectedStudent" class="no-results">
        <div class="no-results-icon">&#128237;</div>
        <p>No students found matching "{{ searchQuery }}"</p>
      </div>
    </div>

    <!-- Step 2: Student Info and Fee Summary -->
    <div v-if="selectedStudent" class="student-section">
      <!-- Student Info Card -->
      <div class="card student-info-card">
        <div class="student-info-header">
          <div class="student-avatar-large">
            {{ getInitials(selectedStudent.first_name + ' ' + selectedStudent.last_name) }}
          </div>
          <div class="student-meta">
            <h2 class="student-name">{{ selectedStudent.first_name }} {{ selectedStudent.last_name }}</h2>
            <div class="student-badges">
              <span class="badge badge-id">ID: {{ selectedStudent.student_id }}</span>
              <span class="badge badge-course">{{ selectedStudent.course_name || selectedStudent.course || 'N/A' }}</span>
              <span v-if="selectedStudent.batch_name" class="badge badge-batch">{{ selectedStudent.batch_name }}</span>
            </div>
            <div class="student-contact" v-if="selectedStudent.phone || selectedStudent.email">
              <span v-if="selectedStudent.phone">&#128222; {{ selectedStudent.phone }}</span>
              <span v-if="selectedStudent.email">&#9993; {{ selectedStudent.email }}</span>
            </div>
          </div>
          <div class="student-total-due">
            <div class="total-due-label">Total Due</div>
            <div class="total-due-amount">&#2547; {{ formatNumber(totalDueAmount) }}</div>
          </div>
        </div>
      </div>

      <!-- Enrollment Selection (if multiple enrollments) -->
      <div v-if="enrollments.length > 1" class="card enrollment-select-card">
        <label class="enrollment-label">Select Enrollment:</label>
        <div class="enrollment-options">
          <button
            v-for="enr in enrollments"
            :key="enr.id"
            class="enrollment-option"
            :class="{ active: selectedEnrollmentId === enr.id }"
            @click="selectEnrollment(enr.id)"
          >
            <div class="enr-course">{{ enr.course_name || enr.course }}</div>
            <div class="enr-batch">{{ enr.batch_name || enr.batch }}</div>
            <div class="enr-due">Due: &#2547; {{ formatNumber(enr.total_due || 0) }}</div>
          </button>
        </div>
      </div>

      <!-- Loading State for Fee Data -->
      <div v-if="loadingFees" class="card loading-card">
        <div class="spinner large"></div>
        <p>Loading fee details...</p>
      </div>

      <!-- Fee Groups -->
      <div v-if="!loadingFees && feeGroups.length > 0" class="fee-groups">
        <div class="card fee-group-card" v-for="(group, index) in feeGroups" :key="index">
          <div class="fee-group-header">
            <div class="fee-group-title">
              <span class="fee-group-icon">{{ getCategoryIcon(group.category) }}</span>
              <div>
                <h3>{{ group.fee_type_name }}</h3>
                <span class="fee-group-category">{{ group.category_label }}</span>
              </div>
            </div>
            <div class="fee-group-total">
              <span class="due-label">Due:</span>
              <span class="due-amount">&#2547; {{ formatNumber(group.total_due) }}</span>
            </div>
          </div>

          <div class="fee-items">
            <div
              v-for="item in group.items"
              :key="item.id"
              class="fee-item"
              :class="{ selected: selectedAssignmentIds.includes(item.id) }"
              @click="toggleAssignment(item)"
            >
              <div class="fee-item-check">
                <div
                  class="checkbox"
                  :class="{ checked: selectedAssignmentIds.includes(item.id) }"
                >
                  <span v-if="selectedAssignmentIds.includes(item.id)">&#10003;</span>
                </div>
              </div>
              <div class="fee-item-info">
                <div class="fee-item-period">{{ item.period_label || 'One-time' }}</div>
                <div class="fee-item-meta">
                  <span class="fee-item-due-date" v-if="item.due_date">Due: {{ item.due_date }}</span>
                  <span class="fee-item-status" :class="item.status">{{ item.status }}</span>
                </div>
              </div>
              <div class="fee-item-amounts">
                <div class="fee-item-original" v-if="item.original_amount !== item.final_amount">
                  <span class="strike">&#2547; {{ formatNumber(item.original_amount) }}</span>
                </div>
                <div class="fee-item-final">&#2547; {{ formatNumber(item.due_amount) }}</div>
                <div class="fee-item-paid" v-if="item.paid_amount > 0">
                  Paid: &#2547; {{ formatNumber(item.paid_amount) }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- No Pending Fees -->
      <div v-if="!loadingFees && feeGroups.length === 0 && !paymentSuccess" class="card no-fees-card">
        <div class="no-fees-content">
          <div class="no-fees-icon">&#9989;</div>
          <h3>No Pending Fees</h3>
          <p>This student has no pending fees to collect.</p>
        </div>
      </div>

      <!-- Step 3: Payment Form -->
      <div v-if="selectedAssignmentIds.length > 0" class="card payment-card">
        <div class="payment-header">
          <h3>Collect Payment</h3>
          <div class="payment-selected-count">
            {{ selectedAssignmentIds.length }} item(s) selected
          </div>
        </div>

        <div class="payment-form">
          <div class="form-row">
            <div class="form-group">
              <label>Total Amount to Collect</label>
              <div class="amount-display">&#2547; {{ formatNumber(selectedTotalDue) }}</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="paymentAmount">Payment Amount</label>
              <div class="input-with-symbol">
                <span class="input-symbol">&#2547;</span>
                <input
                  id="paymentAmount"
                  v-model.number="paymentForm.amount"
                  type="number"
                  class="form-input"
                  :max="selectedTotalDue"
                  min="1"
                  placeholder="Enter amount"
                >
              </div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="paymentMethod">Payment Method</label>
              <select
                id="paymentMethod"
                v-model="paymentForm.payment_method"
                class="form-input"
              >
                <option value="">Select method</option>
                <option value="cash">Cash</option>
                <option value="bkash">bKash</option>
                <option value="nagad">Nagad</option>
                <option value="rocket">Rocket</option>
                <option value="bank">Bank</option>
                <option value="card">Card</option>
                <option value="check">Check</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="referenceNo">Reference No. (optional)</label>
              <input
                id="referenceNo"
                v-model="paymentForm.reference_no"
                type="text"
                class="form-input"
                placeholder="e.g., Check no, TrxID"
              >
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="remarks">Remarks (optional)</label>
              <textarea
                id="remarks"
                v-model="paymentForm.remarks"
                class="form-input form-textarea"
                placeholder="Any notes about this collection"
                rows="2"
              ></textarea>
            </div>
          </div>

          <div class="form-actions">
            <button
              class="btn btn-secondary"
              @click="clearSelection"
            >
              Clear Selection
            </button>
            <button
              class="btn btn-primary"
              @click="collectPayment"
              :disabled="!isPaymentValid || processingPayment"
            >
              <span v-if="processingPayment" class="spinner"></span>
              <span v-else>&#128179; Collect Payment</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Success State -->
      <div v-if="paymentSuccess" class="card success-card">
        <div class="success-content">
          <div class="success-icon">&#127881;</div>
          <h2>Payment Collected Successfully!</h2>
          <div class="success-details">
            <div class="success-row">
              <span class="success-label">Transaction No:</span>
              <span class="success-value">{{ paymentResult.transaction?.transaction_no }}</span>
            </div>
            <div class="success-row">
              <span class="success-label">Amount:</span>
              <span class="success-value">&#2547; {{ formatNumber(paymentResult.transaction?.amount) }}</span>
            </div>
            <div class="success-row">
              <span class="success-label">Method:</span>
              <span class="success-value">{{ paymentResult.transaction?.payment_method }}</span>
            </div>
            <div class="success-row">
              <span class="success-label">Status:</span>
              <span class="success-value status-confirmed">{{ paymentResult.transaction?.status }}</span>
            </div>
          </div>
          <div class="success-actions">
            <button
              v-if="hasInvoice"
              class="btn btn-primary"
              @click="downloadInvoice"
            >
              &#128196; Download Invoice
            </button>
            <button
              class="btn btn-secondary"
              @click="resetAll"
            >
              Collect Another Fee
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, getCurrentInstance } from 'vue'
import enrollmentService from '@/services/enrollment.service'
import smartFeeService from '@/services/smart-fee.service'

const { proxy } = getCurrentInstance()
const $toast = proxy.$toast

// State
const searchQuery = ref('')
const searching = ref(false)
const searchPerformed = ref(false)
const searchResults = ref([])
const selectedStudent = ref(null)
const enrollments = ref([])
const selectedEnrollmentId = ref(null)
const loadingFees = ref(false)
const feeGroups = ref([])
const selectedAssignmentIds = ref([])
const processingPayment = ref(false)
const paymentSuccess = ref(false)
const paymentResult = ref({})
const hasInvoice = ref(false)

const paymentForm = ref({
  amount: 0,
  payment_method: '',
  reference_no: '',
  remarks: '',
})

// Debounce timer
let searchTimer = null

// Computed
const totalDueAmount = computed(() => {
  return feeGroups.value.reduce((sum, g) => sum + (g.total_due || 0), 0)
})

const selectedTotalDue = computed(() => {
  let total = 0
  for (const group of feeGroups.value) {
    for (const item of group.items) {
      if (selectedAssignmentIds.value.includes(item.id)) {
        total += item.due_amount
      }
    }
  }
  return total
})

const isPaymentValid = computed(() => {
  return (
    paymentForm.value.amount > 0 &&
    paymentForm.value.amount <= selectedTotalDue.value &&
    paymentForm.value.payment_method
  )
})

// Methods
function getInitials(name) {
  if (!name) return '?'
  return name
    .split(' ')
    .map(w => w[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

function formatNumber(num) {
  if (num === null || num === undefined) return '0'
  return Number(num).toLocaleString('en-BD', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

function getCategoryIcon(category) {
  const icons = {
    monthly: '&#128197;',
    one_time: '&#128188;',
    event_based: '&#128204;',
  }
  return icons[category] || '&#128176;'
}

function onSearchInput() {
  searchPerformed.value = false
  if (searchTimer) clearTimeout(searchTimer)
  if (searchQuery.value.trim().length >= 2) {
    searchTimer = setTimeout(searchStudent, 400)
  } else {
    searchResults.value = []
  }
}

async function searchStudent() {
  const q = searchQuery.value.trim()
  if (!q) return

  searching.value = true
  searchPerformed.value = true
  searchResults.value = []

  try {
    const response = await enrollmentService.searchStudent(q)
    const data = response.data

    if (data.data) {
      searchResults.value = Array.isArray(data.data) ? data.data : [data.data]
    } else if (data.students) {
      searchResults.value = data.students
    } else if (Array.isArray(data)) {
      searchResults.value = data
    } else {
      searchResults.value = []
    }
  } catch (error) {
    console.error('Search failed:', error)
    $toast.error('Failed to search students. Please try again.')
    searchResults.value = []
  } finally {
    searching.value = false
  }
}

function selectStudent(result) {
  selectedStudent.value = result
  searchResults.value = []
  searchQuery.value = (result.first_name || '') + ' ' + (result.last_name || '')

  // The search endpoint returns Student model data without enrollments.
  // loadPendingFees() will query all enrollments for this student via the API.
  enrollments.value = []
  selectedEnrollmentId.value = null
  loadPendingFees()
}

function selectEnrollment(enrollmentId) {
  selectedEnrollmentId.value = enrollmentId
  selectedAssignmentIds.value = []
  loadPendingFees()
}

async function loadPendingFees() {
  if (!selectedStudent.value) return

  loadingFees.value = true
  feeGroups.value = []
  selectedAssignmentIds.value = []
  paymentSuccess.value = false
  paymentResult.value = {}
  hasInvoice.value = false

  // IMPORTANT: Use the UUID (selectedStudent.value.id), NOT student_id (display ID like "STU-2026-000005")
  // The backend queries enrollments.student_id which stores the UUID foreign key.
  const studentId = selectedStudent.value.id

  try {
    const params = {}
    if (selectedEnrollmentId.value) {
      params.enrollment_id = selectedEnrollmentId.value
    }

    const response = await smartFeeService.admin.studentPendingFees(studentId, params)
    const data = response.data

    // Response structure: { status: 'success', message: 'Success', data: { success: true, grouped: [...], total_due: ... } }
    console.log('[CollectFeePage] Pending fees response:', data)

    if (data.data) {
      feeGroups.value = data.data.grouped || data.data.groups || []
    } else if (data.grouped) {
      feeGroups.value = data.grouped
    } else if (Array.isArray(data)) {
      feeGroups.value = data
    } else {
      feeGroups.value = []
    }

    console.log('[CollectFeePage] Extracted fee groups:', feeGroups.value)

    if (feeGroups.value.length > 0) {
      const firstItem = feeGroups.value[0]?.items?.[0]
      if (firstItem) {
        paymentForm.value.amount = firstItem.due_amount
      }
    }
  } catch (error) {
    console.error('[CollectFeePage] Failed to load pending fees:', error)
    $toast.error('Failed to load fee details.')
    feeGroups.value = []
  } finally {
    loadingFees.value = false
  }
}

function toggleAssignment(item) {
  const idx = selectedAssignmentIds.value.indexOf(item.id)
  if (idx === -1) {
    selectedAssignmentIds.value.push(item.id)
  } else {
    selectedAssignmentIds.value.splice(idx, 1)
  }

  if (selectedAssignmentIds.value.length > 0) {
    paymentForm.value.amount = selectedTotalDue.value
  } else {
    paymentForm.value.amount = 0
  }
}

function clearSelection() {
  selectedAssignmentIds.value = []
  paymentForm.value.amount = 0
}

async function collectPayment() {
  if (!isPaymentValid.value) return

  processingPayment.value = true

  // Use UUID, not display ID
  const studentId = selectedStudent.value.id

  let enrollmentId = selectedEnrollmentId.value
  if (!enrollmentId && feeGroups.value.length > 0) {
    for (const group of feeGroups.value) {
      for (const item of group.items) {
        if (selectedAssignmentIds.value.includes(item.id) && item.enrollment_id) {
          enrollmentId = item.enrollment_id
          break
        }
      }
      if (enrollmentId) break
    }
  }

  const payload = {
    enrollment_id: enrollmentId,
    student_id: studentId,
    amount: paymentForm.value.amount,
    payment_method: paymentForm.value.payment_method,
    fee_assignment_ids: [...selectedAssignmentIds.value],
    reference_no: paymentForm.value.reference_no || undefined,
    remarks: paymentForm.value.remarks || 'Fee collected via Collect Fee page',
  }

  try {
    const response = await smartFeeService.admin.manualPaymentWithAllocations(payload)
    const data = response.data

    if (data.data) {
      paymentResult.value = data.data
    } else {
      paymentResult.value = data
    }

    paymentSuccess.value = true

    hasInvoice.value =
      paymentResult.value.auto_confirmed !== false &&
      (paymentResult.value.transaction?.status === 'confirmed')

    $toast.success(data.message || 'Payment collected successfully!')

    await loadPendingFees()
  } catch (error) {
    console.error('Payment failed:', error)
    const msg = error.response?.data?.message || 'Failed to process payment. Please try again.'
    $toast.error(msg)
  } finally {
    processingPayment.value = false
  }
}

async function downloadInvoice() {
  const transactionId = paymentResult.value.transaction?.id
  if (!transactionId) {
    $toast.error('Invoice not available.')
    return
  }

  try {
    const invoiceResponse = await smartFeeService.invoices.getByTransaction(transactionId)
    const invoiceData = invoiceResponse.data

    let invoiceId = null
    if (invoiceData.data?.id) {
      invoiceId = invoiceData.data.id
    } else if (invoiceData.id) {
      invoiceId = invoiceData.id
    } else if (invoiceData.data?.invoice?.id) {
      invoiceId = invoiceData.data.invoice.id
    }

    if (!invoiceId) {
      try {
        const genResponse = await smartFeeService.admin.generateInvoice(transactionId)
        const genData = genResponse.data
        invoiceId = genData.data?.id || genData.id || genData.data?.invoice?.id
      } catch (genErr) {
        console.error('Invoice generation failed:', genErr)
      }
    }

    if (invoiceId) {
      const blobResponse = await smartFeeService.invoices.download(invoiceId)
      const blob = blobResponse.data

      if (blob instanceof Blob) {
        const url = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.download = `invoice-${paymentResult.value.transaction?.transaction_no || invoiceId}.pdf`
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)
        $toast.success('Invoice downloaded successfully!')
      }
    } else {
      $toast.error('Could not find or generate invoice.')
    }
  } catch (error) {
    console.error('Invoice download failed:', error)
    $toast.error('Failed to download invoice.')
  }
}

function resetAll() {
  selectedStudent.value = null
  searchQuery.value = ''
  searchResults.value = []
  searchPerformed.value = false
  enrollments.value = []
  selectedEnrollmentId.value = null
  feeGroups.value = []
  selectedAssignmentIds.value = []
  paymentSuccess.value = false
  paymentResult.value = {}
  hasInvoice.value = false
  paymentForm.value = {
    amount: 0,
    payment_method: '',
    reference_no: '',
    remarks: '',
  }
}

function clearSearch() {
  searchQuery.value = ''
  searchResults.value = []
  searchPerformed.value = false
  if (selectedStudent.value) {
    resetAll()
  }
}
</script>

<style scoped>
.page-container {
  padding: 24px;
  max-width: 900px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 24px;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #1a202c;
  margin: 0 0 4px 0;
}

.page-subtitle {
  font-size: 14px;
  color: #718096;
  margin: 0;
}

/* Card */
.card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

/* Search Card */
.search-card {
  position: relative;
}

.search-section {
  display: flex;
  gap: 12px;
  align-items: center;
}

.search-input-wrapper {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 14px;
  font-size: 16px;
  color: #a0aec0;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 12px 40px 12px 42px;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  font-size: 15px;
  color: #2d3748;
  background: #f7fafc;
  transition: all 0.2s ease;
  outline: none;
  box-sizing: border-box;
}

.search-input:focus {
  border-color: #4299e1;
  background: var(--bg-card);
  box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15);
}

.search-input::placeholder {
  color: #a0aec0;
}

.search-clear-btn {
  position: absolute;
  right: 10px;
  background: none;
  border: none;
  font-size: 14px;
  color: #a0aec0;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
  transition: all 0.2s;
}

.search-clear-btn:hover {
  color: #e53e3e;
  background: #fff5f5;
}

.search-btn {
  padding: 12px 24px;
  background: #4299e1;
  color: #ffffff;
  border: none;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 8px;
}

.search-btn:hover:not(:disabled) {
  background: #3182ce;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
}

.search-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Search Results */
.search-results {
  margin-top: 12px;
  border: 1px solid var(--border-color);
  border-radius: 10px;
  overflow: hidden;
  max-height: 320px;
  overflow-y: auto;
}

.search-result-item {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  cursor: pointer;
  transition: background 0.15s;
  border-bottom: 1px solid #f0f0f0;
}

.search-result-item:last-child {
  border-bottom: none;
}

.search-result-item:hover {
  background: #ebf8ff;
}

.result-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #4299e1, #667eea);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  flex-shrink: 0;
}

.result-info {
  flex: 1;
  margin-left: 12px;
  min-width: 0;
}

.result-name {
  font-weight: 600;
  color: #2d3748;
  font-size: 15px;
  margin-bottom: 2px;
}

.result-details {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #718096;
  flex-wrap: wrap;
}

.result-separator {
  color: #cbd5e0;
}

.result-arrow {
  color: #a0aec0;
  font-size: 18px;
  margin-left: 8px;
}

/* No Results */
.no-results {
  text-align: center;
  padding: 32px 16px;
  color: #718096;
}

.no-results-icon {
  font-size: 40px;
  margin-bottom: 8px;
}

.no-results p {
  margin: 0;
  font-size: 14px;
}

/* Student Section */
.student-section {
  margin-top: 8px;
}

/* Student Info Card */
.student-info-header {
  display: flex;
  align-items: center;
  gap: 16px;
}

.student-avatar-large {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: linear-gradient(135deg, #4299e1, #667eea);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 20px;
  flex-shrink: 0;
}

.student-meta {
  flex: 1;
  min-width: 0;
}

.student-name {
  font-size: 18px;
  font-weight: 700;
  color: #1a202c;
  margin: 0 0 6px 0;
}

.student-badges {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 4px;
}

.badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.badge-id {
  background: #ebf8ff;
  color: #2b6cb0;
}

.badge-course {
  background: #f0fff4;
  color: #276749;
}

.badge-batch {
  background: #faf5ff;
  color: #6b46c1;
}

.student-contact {
  display: flex;
  gap: 12px;
  font-size: 13px;
  color: #718096;
  margin-top: 4px;
}

.student-total-due {
  text-align: right;
  flex-shrink: 0;
}

.total-due-label {
  font-size: 12px;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 2px;
}

.total-due-amount {
  font-size: 22px;
  font-weight: 700;
  color: #e53e3e;
}

/* Enrollment Selection */
.enrollment-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #4a5568;
  margin-bottom: 10px;
}

.enrollment-options {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.enrollment-option {
  flex: 1;
  min-width: 180px;
  padding: 12px 16px;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  background: var(--bg-card);
  cursor: pointer;
  transition: all 0.2s;
  text-align: left;
}

.enrollment-option:hover {
  border-color: #4299e1;
  background: #ebf8ff;
}

.enrollment-option.active {
  border-color: #4299e1;
  background: #ebf8ff;
  box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15);
}

.enr-course {
  font-weight: 600;
  color: #2d3748;
  font-size: 14px;
  margin-bottom: 2px;
}

.enr-batch {
  font-size: 12px;
  color: #718096;
  margin-bottom: 4px;
}

.enr-due {
  font-size: 13px;
  color: #e53e3e;
  font-weight: 500;
}

/* Loading Card */
.loading-card {
  text-align: center;
  padding: 40px;
  color: #718096;
}

.loading-card p {
  margin: 12px 0 0 0;
  font-size: 14px;
}

/* Spinner */
.spinner {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

.spinner.large {
  width: 32px;
  height: 32px;
  border-width: 3px;
  border-color: rgba(66, 153, 225, 0.2);
  border-top-color: #4299e1;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Fee Groups */
.fee-groups {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.fee-group-card {
  padding: 0;
  overflow: hidden;
}

.fee-group-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  background: #f7fafc;
  border-bottom: 1px solid var(--border-color);
}

.fee-group-title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.fee-group-icon {
  font-size: 24px;
}

.fee-group-title h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
}

.fee-group-category {
  font-size: 12px;
  color: #718096;
}

.fee-group-total {
  text-align: right;
}

.due-label {
  font-size: 12px;
  color: #718096;
  display: block;
}

.due-amount {
  font-size: 18px;
  font-weight: 700;
  color: #e53e3e;
}

/* Fee Items */
.fee-items {
  padding: 4px 0;
}

.fee-item {
  display: flex;
  align-items: center;
  padding: 10px 20px;
  cursor: pointer;
  transition: background 0.15s;
  border-bottom: 1px solid #f7fafc;
}

.fee-item:last-child {
  border-bottom: none;
}

.fee-item:hover {
  background: #f7fafc;
}

.fee-item.selected {
  background: #ebf8ff;
}

.fee-item-check {
  margin-right: 12px;
  flex-shrink: 0;
}

.checkbox {
  width: 22px;
  height: 22px;
  border: 2px solid #cbd5e0;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  font-size: 12px;
  color: #ffffff;
}

.checkbox.checked {
  background: #4299e1;
  border-color: #4299e1;
}

.fee-item-info {
  flex: 1;
  min-width: 0;
}

.fee-item-period {
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
  margin-bottom: 2px;
}

.fee-item-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
}

.fee-item-due-date {
  color: #718096;
}

.fee-item-status {
  display: inline-block;
  padding: 1px 8px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 500;
  text-transform: capitalize;
}

.fee-item-status.pending {
  background: #fefcbf;
  color: #975a16;
}

.fee-item-status.partial {
  background: #feebc8;
  color: #9c4221;
}

.fee-item-status.paid,
.fee-item-status.confirmed {
  background: #c6f6d5;
  color: #276749;
}

.fee-item-status.overdue {
  background: #fed7d7;
  color: #9b2c2c;
}

.fee-item-amounts {
  text-align: right;
  margin-left: 12px;
  flex-shrink: 0;
}

.fee-item-original {
  font-size: 12px;
  margin-bottom: 2px;
}

.strike {
  text-decoration: line-through;
  color: #a0aec0;
}

.fee-item-final {
  font-size: 16px;
  font-weight: 700;
  color: #2d3748;
}

.fee-item-paid {
  font-size: 11px;
  color: #38a169;
  margin-top: 2px;
}

/* No Fees Card */
.no-fees-card {
  text-align: center;
  padding: 40px;
}

.no-fees-content {
  color: #718096;
}

.no-fees-icon {
  font-size: 48px;
  margin-bottom: 12px;
}

.no-fees-content h3 {
  margin: 0 0 8px 0;
  font-size: 18px;
  color: #2d3748;
}

.no-fees-content p {
  margin: 0;
  font-size: 14px;
}

/* Payment Card */
.payment-card {
  border: 2px solid #4299e1;
  background: linear-gradient(135deg, #ffffff, #f7fafc);
}

.payment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border-color);
}

.payment-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
}

.payment-selected-count {
  font-size: 13px;
  color: #4299e1;
  font-weight: 500;
  background: #ebf8ff;
  padding: 4px 12px;
  border-radius: 20px;
}

/* Payment Form */
.payment-form {
  max-width: 500px;
}

.form-row {
  margin-bottom: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
  margin-bottom: 6px;
}

.form-input {
  padding: 10px 14px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  color: #2d3748;
  background: var(--bg-card);
  transition: all 0.2s;
  outline: none;
  width: 100%;
  box-sizing: border-box;
}

.form-input:focus {
  border-color: #4299e1;
  box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15);
}

.form-textarea {
  resize: vertical;
  min-height: 60px;
  font-family: inherit;
}

select.form-input {
  cursor: pointer;
  appearance: auto;
}

.amount-display {
  font-size: 28px;
  font-weight: 700;
  color: #2d3748;
  padding: 8px 0;
}

.input-with-symbol {
  position: relative;
  display: flex;
  align-items: center;
}

.input-symbol {
  position: absolute;
  left: 14px;
  font-size: 16px;
  font-weight: 700;
  color: #718096;
  pointer-events: none;
}

.input-with-symbol .form-input {
  padding-left: 32px;
}

/* Form Actions */
.form-actions {
  display: flex;
  gap: 12px;
  margin-top: 24px;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: #4299e1;
  color: #ffffff;
}

.btn-primary:hover:not(:disabled) {
  background: #3182ce;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
}

.btn-secondary {
  background: #edf2f7;
  color: #4a5568;
}

.btn-secondary:hover:not(:disabled) {
  background: #e2e8f0;
}

/* Success Card */
.success-card {
  text-align: center;
  padding: 40px;
  border: 2px solid #48bb78;
  background: linear-gradient(135deg, #f0fff4, #ffffff);
}

.success-content {
  max-width: 400px;
  margin: 0 auto;
}

.success-icon {
  font-size: 56px;
  margin-bottom: 12px;
}

.success-content h2 {
  font-size: 22px;
  font-weight: 700;
  color: #276749;
  margin: 0 0 20px 0;
}

.success-details {
  text-align: left;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 16px;
  margin-bottom: 24px;
}

.success-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #f7fafc;
}

.success-row:last-child {
  border-bottom: none;
}

.success-label {
  font-size: 14px;
  color: #718096;
}

.success-value {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
}

.status-confirmed {
  color: #38a169 !important;
}

.success-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}

/* Responsive */
@media (max-width: 768px) {
  .page-container {
    padding: 16px;
  }

  .search-section {
    flex-direction: column;
  }

  .search-btn {
    width: 100%;
    justify-content: center;
  }

  .student-info-header {
    flex-direction: column;
    text-align: center;
  }

  .student-total-due {
    text-align: center;
    margin-top: 8px;
  }

  .student-contact {
    justify-content: center;
  }

  .enrollment-options {
    flex-direction: column;
  }

  .enrollment-option {
    min-width: auto;
  }

  .fee-group-header {
    flex-direction: column;
    gap: 8px;
    text-align: center;
  }

  .fee-group-total {
    text-align: center;
  }

  .fee-item {
    flex-wrap: wrap;
  }

  .fee-item-amounts {
    width: 100%;
    text-align: left;
    margin-left: 34px;
    margin-top: 4px;
  }

  .form-actions {
    flex-direction: column;
  }

  .form-actions .btn {
    width: 100%;
    justify-content: center;
  }

  .success-actions {
    flex-direction: column;
  }

  .success-actions .btn {
    width: 100%;
    justify-content: center;
  }
}

</style>

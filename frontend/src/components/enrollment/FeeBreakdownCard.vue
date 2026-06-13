<template>
  <div class="fee-breakdown-card">
    <h3 class="step-title">
      <span class="step-badge">4</span>
      Fee & Payment
    </h3>

    <!-- Loading -->
    <div v-if="calculating" class="loading-state">
      <div class="spinner"></div>
      <p>Calculating fee...</p>
    </div>

    <!-- No data -->
    <div v-else-if="!feeData" class="empty-state">
      <p class="text-muted">Select a course and student to calculate fees</p>
    </div>

    <!-- Fee Breakdown -->
    <div v-else class="fee-content">
      <!-- Subject-wise breakdown -->
      <div v-if="feeData.subjects?.length" class="fee-section">
        <h4>📚 Subject-wise Fee</h4>
        <table class="fee-table">
          <thead>
            <tr><th>Subject</th><th class="text-right">Fee</th><th>Type</th></tr>
          </thead>
          <tbody>
            <tr v-for="s in feeData.subjects" :key="s.id">
              <td>{{ s.name }}</td>
              <td class="text-right">৳{{ Number(s.fee).toLocaleString() }}</td>
              <td>
                <span v-if="s.is_mandatory" class="badge badge-info">Mandatory</span>
                <span v-else class="badge badge-secondary">Optional</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Totals -->
      <div class="totals-box">
        <div class="total-row">
          <span>Total Fee</span>
          <span>৳{{ Number(feeData.total_fee).toLocaleString() }}</span>
        </div>
        <div v-if="feeData.discount_percent > 0" class="total-row discount">
          <span>Discount ({{ feeData.discount_percent }}%)</span>
          <span class="text-success">− ৳{{ Number(feeData.discount_amount).toLocaleString() }}</span>
        </div>
        <div v-if="feeData.discount_reason" class="discount-reason">
          💡 {{ feeData.discount_reason }}
        </div>
        <div class="total-row grand">
          <span>Payable Fee</span>
          <span>৳{{ Number(feeData.payable_fee).toLocaleString() }}</span>
        </div>
      </div>

      <!-- Payment Input -->
      <div class="payment-section">
        <h4>💳 Payment</h4>

        <div class="form-row">
          <div class="form-group col-4">
            <label class="form-label">Amount</label>
            <input
              v-model.number="payment.amount"
              type="number"
              class="form-input"
              :min="0"
              :max="feeData.payable_fee"
              placeholder="0"
            />
          </div>
          <div class="form-group col-4">
            <label class="form-label">Method</label>
            <select v-model="payment.method" class="form-select">
              <option value="cash">Cash</option>
              <option value="bkash">bKash</option>
              <option value="nagad">Nagad</option>
              <option value="rocket">Rocket</option>
              <option value="bank">Bank Transfer</option>
            </select>
          </div>
          <div class="form-group col-4">
            <label class="form-label">Transaction ID</label>
            <input v-model="payment.transaction_id" class="form-input" placeholder="Optional" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-12">
            <label class="form-label">Reference / Note</label>
            <input v-model="payment.reference" class="form-input" placeholder="e.g., bKash sender number" />
          </div>
        </div>

        <!-- Payment Status Preview -->
        <div class="payment-preview">
          <div class="preview-row">
            <span>Paying Now</span>
            <strong>৳{{ Number(payment.amount || 0).toLocaleString() }}</strong>
          </div>
          <div class="preview-row">
            <span>Remaining Due</span>
            <strong :class="{ 'text-danger': dueAmount > 0, 'text-success': dueAmount <= 0 }">
              ৳{{ Number(dueAmount).toLocaleString() }}
            </strong>
          </div>
          <div class="preview-status">
            <span v-if="dueAmount <= 0 && feeData.payable_fee > 0" class="status-active">
              ✅ Full Payment — Enrollment will be CONFIRMED
            </span>
            <span v-else-if="payment.amount > 0" class="status-pending">
              ⚠️ Partial Payment — Enrollment will be PENDING
            </span>
            <span v-else class="status-none">
              ⚠️ No Payment — Enrollment will be PENDING
            </span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="errorMsg" class="alert alert-danger mt-2">{{ errorMsg }}</div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import enrollmentService from '@/services/enrollment.service'

const props = defineProps({
  courseId: { type: String, default: null },
  studentId: { type: String, default: null },
  subjectIds: { type: Array, default: () => [] },
})
const emit = defineEmits(['fee-calculated', 'payment-updated'])

const feeData = ref(null)
const calculating = ref(false)
const errorMsg = ref('')

const payment = ref({
  amount: 0,
  method: 'cash',
  transaction_id: '',
  reference: '',
})

const dueAmount = computed(() => {
  if (!feeData.value) return 0
  return Math.max(0, feeData.value.payable_fee - (payment.value.amount || 0))
})

const calculateFee = async () => {
  if (!props.courseId) return
  calculating.value = true
  errorMsg.value = ''
  try {
    const payload = { course_id: props.courseId }
    if (props.subjectIds.length) payload.subject_ids = props.subjectIds
    if (props.studentId) payload.student_id = props.studentId
    const res = await enrollmentService.calculateFee(payload)
    feeData.value = res.data?.data || res.data
    payment.value.amount = feeData.value?.payable_fee || 0
    emit('fee-calculated', feeData.value)
  } catch (e) {
    console.error(e)
    errorMsg.value = 'Failed to calculate fee'
  } finally {
    calculating.value = false
  }
}

watch(() => [props.courseId, props.studentId, props.subjectIds], () => {
  if (props.courseId) calculateFee()
}, { immediate: true })

watch(payment, (val) => {
  emit('payment-updated', { ...val, due_amount: dueAmount.value })
}, { deep: true })
</script>

<style scoped>
.fee-breakdown-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.step-title { font-size: 1.1rem; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem; }
.step-badge { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #4a90d9; color: white; font-size: 0.85rem; font-weight: 700; }

.fee-section { margin-bottom: 1rem; }
.fee-section h4 { font-size: 0.95rem; margin: 0 0 0.5rem 0; }

.fee-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.fee-table th { background: var(--bg-page); padding: 0.5rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); }
.fee-table td { padding: 0.5rem; border-bottom: 1px solid #f0f0f0; }
.text-right { text-align: right; }

.totals-box {
  background: var(--bg-accent);
  border-radius: 10px;
  padding: 1rem;
  margin-bottom: 1rem;
}
.total-row {
  display: flex;
  justify-content: space-between;
  padding: 0.35rem 0;
  font-size: 0.9rem;
}
.total-row.grand {
  font-size: 1.1rem;
  font-weight: 700;
  border-top: 2px solid #4a90d9;
  margin-top: 0.3rem;
  padding-top: 0.5rem;
  color: #4a90d9;
}
.total-row.discount { color: #27ae60; }
.discount-reason { font-size: 0.8rem; color: #888; margin-top: 0.2rem; }

.payment-section { margin-top: 1rem; }
.payment-section h4 { font-size: 0.95rem; margin: 0 0 0.5rem 0; }

.form-row { display: flex; gap: 1rem; margin-bottom: 0.75rem; }
.form-group { flex: 1; }
.form-label { display: block; margin-bottom: 0.25rem; font-size: 0.8rem; font-weight: 600; color: #555; }
.form-input, .form-select { width: 100%; padding: 0.55rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; }
.col-4 { flex: 0 0 calc(33.33% - 0.67rem); }
.col-12 { flex: 0 0 100%; }

.payment-preview {
  margin-top: 1rem;
  padding: 0.75rem 1rem;
  background: #fffbe6;
  border-radius: 8px;
  border: 1px solid #fde68a;
}
.preview-row { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.25rem; }
.preview-status { font-size: 0.8rem; margin-top: 0.4rem; font-weight: 600; }
.status-active { color: #27ae60; }
.status-pending { color: #f39c12; }
.status-none { color: #e74c3c; }

.loading-state, .empty-state { text-align: center; padding: 1.5rem; }
.spinner { width: 32px; height: 32px; border: 3px solid #eee; border-top-color: #4a90d9; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 0.5rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.alert-danger { background: #fdeaea; color: #e74c3c; padding: 0.75rem; border-radius: 8px; font-size: 0.85rem; }
.text-muted { color: #888; }
.text-success { color: #27ae60; }
.text-danger { color: #e74c3c; }
.mt-2 { margin-top: 0.5rem; }
.badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 10px; font-size: 0.7rem; font-weight: 600; }
.badge-info { background: #eef4ff; color: #4a90d9; }
.badge-secondary { background: #f0f0f0; color: var(--text-muted); }
</style>

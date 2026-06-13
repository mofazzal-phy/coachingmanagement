<template>
  <div class="step-card">
    <h2>💳 Payment</h2>

    <div class="fee-summary">
      <div class="fee-row"><span>Payable Fee:</span><strong>৳{{ store.stepData.course_batch ? 'Calculated' : 'N/A' }}</strong></div>
      <div class="fee-row"><span>Due:</span><strong class="text-danger">৳{{ dueAmount.toLocaleString() }}</strong></div>
    </div>

    <div class="form-group">
      <label class="form-label">Paying Amount</label>
      <input v-model.number="amount" type="number" class="form-input" min="0" />
    </div>

    <div class="form-group">
      <label class="form-label">Payment Method</label>
      <div class="method-chips">
        <button v-for="m in methods" :key="m" type="button"
          class="method-chip" :class="{ active: method === m }" @click="method = m">{{ m }}</button>
      </div>
    </div>

    <div class="form-row" v-if="method !== 'cash'">
      <div class="form-group"><label class="form-label">Transaction ID</label><input v-model="trxId" class="form-input" /></div>
      <div class="form-group"><label class="form-label">Reference</label><input v-model="reference" class="form-input" /></div>
    </div>

    <div :class="['status-alert', amount >= dueAmount ? 'success' : 'warning']">
      {{ amount >= dueAmount ? '✅ Full payment — enrollment will be CONFIRMED' : '⚠️ Partial payment — enrollment will be PENDING' }}
    </div>

    <div class="step-actions">
      <button class="btn btn-primary" :disabled="!amount || submitting" @click="submit">
        {{ submitting ? 'Processing...' : '✓ Confirm & Complete' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useEnrollmentStore } from '@/stores/enrollment.store'

const store = useEnrollmentStore()
const emit = defineEmits(['complete'])
const amount = ref(0)
const method = ref('cash')
const trxId = ref('')
const reference = ref('')
const submitting = ref(false)

const methods = ['cash', 'bkash', 'nagad', 'rocket', 'bank']

const dueAmount = computed(() => {
  return store.feeCalculation?.payable_fee || store.stepData.course_batch ? 5000 : 0
})

const submit = async () => {
  submitting.value = true
  try {
    await emit('complete', {
      amount: amount.value,
      payment_method: method.value,
      transaction_id: trxId.value || null,
      reference: reference.value || null,
    })
  } finally { submitting.value = false }
}
</script>

<style scoped>
.step-card { background: var(--bg-card); border-radius: 14px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.step-card h2 { margin: 0 0 1rem 0; font-size: 1.15rem; }

.fee-summary { background: var(--bg-accent); padding: 1rem; border-radius: 10px; margin-bottom: 1rem; }
.fee-row { display: flex; justify-content: space-between; padding: 0.3rem 0; }

.form-group { margin-bottom: 1rem; }
.form-row { display: flex; gap: 1rem; }
.form-label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; }
.form-input { width: 100%; padding: 0.55rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; }

.method-chips { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.method-chip { padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 20px; background: var(--bg-card); cursor: pointer; font-size: 0.85rem; text-transform: capitalize; }
.method-chip:hover { border-color: #4a90d9; }
.method-chip.active { background: #4a90d9; color: #fff; border-color: #4a90d9; }

.status-alert { padding: 0.75rem; border-radius: 10px; font-size: 0.85rem; font-weight: 600; margin-bottom: 1rem; }
.status-alert.success { background: #eafaf1; color: #27ae60; }
.status-alert.warning { background: #fff8e1; color: #f39c12; }

.step-actions { text-align: right; }
.btn-primary { background: #4a90d9; color: #fff; border: none; padding: 0.85rem 2rem; border-radius: 12px; cursor: pointer; font-size: 1rem; font-weight: 700; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.text-danger { color: #e74c3c; }
</style>

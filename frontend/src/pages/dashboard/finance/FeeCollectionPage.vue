<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left"><h1>Fee Collections</h1><span class="badge-count">{{ items.length }} total</span></div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadItems" :disabled="loading">🔄 Refresh</button>
        <button class="btn btn-primary" @click="openCreateDialog">+ Collect Fee</button>
      </div>
    </div>

    <!-- Tabs: Legacy Collections | Smart Collection -->
    <div class="tabs">
      <button class="tab-btn" :class="{ active: activeTab === 'legacy' }" @click="activeTab = 'legacy'">Legacy Collections</button>
      <button class="tab-btn" :class="{ active: activeTab === 'smart' }" @click="activeTab = 'smart'">Smart Collection</button>
    </div>

    <!-- ============ LEGACY COLLECTIONS TAB ============ -->
    <template v-if="activeTab === 'legacy'">
      <!-- Summary -->
      <div class="summary-cards" v-if="items.length > 0">
        <div class="summary-card">
          <div class="summary-label">Total Collected</div>
          <div class="summary-value">{{ formatCurrency(totalCollected) }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">This Month</div>
          <div class="summary-value">{{ formatCurrency(monthlyCollected) }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">Pending</div>
          <div class="summary-value pending">{{ formatCurrency(pendingAmount) }}</div>
        </div>
      </div>

      <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading collections...</p></div>
      <div v-else-if="error" class="error-state"><p>⚠️ {{ error }}</p><button class="btn btn-outline" @click="loadItems">Try Again</button></div>
      <div v-else-if="items.length === 0" class="empty-state">
        <div class="empty-icon">💳</div><h3>No Collections Yet</h3><p>Record your first fee collection</p>
        <button class="btn btn-primary" @click="openCreateDialog">+ Collect Fee</button>
      </div>
      <div v-else class="table-container">
        <table class="data-table">
          <thead>
            <tr><th>Student</th><th>Fee Type</th><th>Amount</th><th>Paid Date</th><th>Method</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td><strong>{{ item.student?.name || '—' }}</strong></td>
              <td>
                <span v-if="item.fee_type" class="fee-type-cell">
                  <span class="cat-badge" :class="'cat-' + (item.fee_type.category || 'monthly')">
                    {{ categoryLabel(item.fee_type.category) }}
                  </span>
                  <span class="fee-type-name">{{ item.fee_type.name }}</span>
                </span>
                <span v-else>—</span>
              </td>
              <td><strong>{{ formatCurrency(item.amount) }}</strong></td>
              <td>{{ formatDate(item.paid_date) }}</td>
              <td><span class="badge">{{ item.payment_method || 'cash' }}</span></td>
              <td>
                <span class="status-badge" :class="item.status">{{ item.status || 'paid' }}</span>
              </td>
              <td>
                <div class="action-buttons">
                  <button class="btn-icon" title="Edit" @click="openEditDialog(item)">✏️</button>
                  <button class="btn-icon danger" title="Delete" @click="confirmDelete(item)">🗑️</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="modal-overlay" v-if="showDialog" @click.self="closeDialog">
        <div class="modal-dialog">
          <div class="modal-header">
            <h3>{{ editingItem ? 'Edit Collection' : 'Collect Fee' }}</h3>
            <button class="modal-close" @click="closeDialog">✕</button>
          </div>
          <div class="modal-body">
            <div v-if="dialogError" class="alert alert-danger">{{ dialogError }}</div>
            <div class="form-group">
              <label>Student <span class="required">*</span></label>
              <select v-model="form.student_id" class="form-control">
                <option value="">Select student...</option>
                <option v-for="s in students" :key="s.id" :value="s.id">{{ s.name }} ({{ s.roll_no || 'N/A' }})</option>
              </select>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Fee Type <span class="required">*</span></label>
                <select v-model="form.fee_type_id" class="form-control">
                  <option value="">Select fee type...</option>
                  <option v-for="f in feeTypes" :key="f.id" :value="f.id">{{ f.name }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Amount <span class="required">*</span></label>
                <input v-model.number="form.amount" type="number" min="0" class="form-control" placeholder="0.00" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Paid Date <span class="required">*</span></label>
                <input v-model="form.paid_date" type="date" class="form-control" />
              </div>
              <div class="form-group">
                <label>Payment Method</label>
                <select v-model="form.payment_method" class="form-control">
                  <option value="cash">Cash</option>
                  <option value="bank">Bank Transfer</option>
                  <option value="bkash">bKash</option>
                  <option value="nagad">Nagad</option>
                  <option value="card">Card</option>
                  <option value="check">Check</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>Status</label>
              <select v-model="form.status" class="form-control">
                <option value="paid">Paid</option>
                <option value="partial">Partial</option>
                <option value="pending">Pending</option>
                <option value="overdue">Overdue</option>
              </select>
            </div>
            <div class="form-group">
              <label>Remarks</label>
              <textarea v-model="form.remarks" class="form-control" rows="2" placeholder="Optional"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline" @click="closeDialog">Cancel</button>
            <button class="btn btn-primary" @click="saveItem" :disabled="!form.student_id || !form.fee_type_id || !form.amount || !form.paid_date || dialogLoading">
              {{ dialogLoading ? 'Saving...' : (editingItem ? 'Update' : 'Collect') }}
            </button>
          </div>
        </div>
      </div>

      <div class="modal-overlay" v-if="showDeleteDialog" @click.self="showDeleteDialog = false">
        <div class="modal-dialog modal-sm">
          <div class="modal-header"><h3>Confirm Delete</h3><button class="modal-close" @click="showDeleteDialog = false">✕</button></div>
          <div class="modal-body"><p>Delete this collection record?</p><p class="text-danger">This cannot be undone.</p></div>
          <div class="modal-footer">
            <button class="btn btn-outline" @click="showDeleteDialog = false">Cancel</button>
            <button class="btn btn-danger" @click="deleteItem" :disabled="deleteLoading">{{ deleteLoading ? 'Deleting...' : 'Delete' }}</button>
          </div>
        </div>
      </div>
    </template>

    <!-- ============ SMART COLLECTION TAB ============ -->
    <template v-if="activeTab === 'smart'">
      <div class="smart-collection">
        <!-- Step 1: Select Student -->
        <div class="card">
          <div class="card-header"><h3>Step 1: Select Student</h3></div>
          <div class="card-body">
            <div class="form-group">
              <select v-model="smartForm.student_id" class="form-control" @change="loadStudentPendingFees">
                <option value="">Select student...</option>
                <option v-for="s in students" :key="s.id" :value="s.id">{{ s.name }} ({{ s.roll_no || 'N/A' }})</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Step 2: View Pending Fees -->
        <div class="card" v-if="smartForm.student_id && pendingFees">
          <div class="card-header">
            <h3>Step 2: Select Fees to Collect</h3>
            <span class="badge-info">Total Due: {{ formatCurrency(pendingFees.total_due) }}</span>
          </div>
          <div class="card-body">
            <div v-if="pendingFeesLoading" class="loading-state"><div class="spinner"></div></div>
            <div v-else-if="pendingFees.grouped && pendingFees.grouped.length === 0" class="empty-state-sm">
              <p>✅ No pending fees for this student.</p>
            </div>
            <div v-else>
              <div v-for="(group, gIdx) in pendingFees.grouped" :key="gIdx" class="fee-group">
                <div class="fee-group-header">
                  <span class="fee-category-badge" :class="group.category">{{ group.category_label }}</span>
                  <strong>{{ group.fee_type_name }}</strong>
                  <span class="group-total">Due: {{ formatCurrency(group.total_due) }}</span>
                </div>
                <div class="fee-items">
                  <div v-for="item in group.items" :key="item.id" class="fee-item" :class="{ selected: selectedAssignmentIds.includes(item.id) }" @click="toggleAssignment(item.id)">
                    <input type="checkbox" :checked="selectedAssignmentIds.includes(item.id)" @click.stop="toggleAssignment(item.id)" />
                    <div class="fee-item-info">
                      <span class="period-label">{{ item.period_label || 'One-time' }}</span>
                      <span class="due-date" v-if="item.due_date">Due: {{ formatDate(item.due_date) }}</span>
                    </div>
                    <div class="fee-item-amounts">
                      <span class="amount-due">{{ formatCurrency(item.due_amount) }}</span>
                      <span class="amount-paid" v-if="item.paid_amount > 0">Paid: {{ formatCurrency(item.paid_amount) }}</span>
                    </div>
                    <span class="status-badge small" :class="item.status">{{ item.status }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 3: Collect Payment -->
        <div class="card" v-if="selectedAssignmentIds.length > 0">
          <div class="card-header"><h3>Step 3: Record Payment</h3></div>
          <div class="card-body">
            <div v-if="smartError" class="alert alert-danger">{{ smartError }}</div>
            <div v-if="smartSuccess" class="alert alert-success">{{ smartSuccess }}</div>
            <div class="form-row">
              <div class="form-group">
                <label>Amount <span class="required">*</span></label>
                <input v-model.number="smartForm.amount" type="number" min="1" step="0.01" class="form-control" placeholder="0.00" />
              </div>
              <div class="form-group">
                <label>Payment Method <span class="required">*</span></label>
                <select v-model="smartForm.payment_method" class="form-control">
                  <option value="cash">Cash</option>
                  <option value="bank">Bank Transfer</option>
                  <option value="check">Check</option>
                  <option value="bkash">bKash</option>
                  <option value="nagad">Nagad</option>
                  <option value="card">Card</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>Reference No.</label>
              <input v-model="smartForm.reference_no" type="text" class="form-control" placeholder="Optional reference" />
            </div>
            <div class="form-group">
              <label>Remarks</label>
              <textarea v-model="smartForm.remarks" class="form-control" rows="2" placeholder="Optional"></textarea>
            </div>
            <button class="btn btn-primary" @click="submitSmartCollection" :disabled="!smartForm.amount || smartForm.amount <= 0 || smartSubmitting">
              {{ smartSubmitting ? 'Processing...' : 'Record Payment & Generate Invoice' }}
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const categoryLabel = (cat) => {
  const labels = { one_time: 'One-Time', monthly: 'Monthly', event_based: 'Event' }
  return labels[cat] || cat || 'Monthly'
}
import financeService from '@/services/finance.service'
import smartFeeService from '@/services/smart-fee.service'
import studentService from '@/services/student.service'

const activeTab = ref('legacy')

// Legacy collection state
const items = ref([])
const students = ref([])
const feeTypes = ref([])
const loading = ref(false)
const error = ref(null)
const showDialog = ref(false)
const editingItem = ref(null)
const dialogLoading = ref(false)
const dialogError = ref(null)
const form = ref({ student_id: '', fee_type_id: '', amount: 0, paid_date: new Date().toISOString().split('T')[0], payment_method: 'cash', status: 'paid', remarks: '' })
const showDeleteDialog = ref(false)
const selectedItem = ref(null)
const deleteLoading = ref(false)

// Smart collection state
const pendingFees = ref(null)
const pendingFeesLoading = ref(false)
const selectedAssignmentIds = ref([])
const smartSubmitting = ref(false)
const smartError = ref(null)
const smartSuccess = ref(null)
const smartForm = ref({
  student_id: '',
  enrollment_id: '',
  amount: 0,
  payment_method: 'cash',
  reference_no: '',
  remarks: '',
})

const totalCollected = computed(() => items.value.reduce((sum, i) => sum + (i.amount || 0), 0))
const monthlyCollected = computed(() => {
  const now = new Date()
  return items.value.filter(i => {
    const d = new Date(i.paid_date)
    return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear()
  }).reduce((sum, i) => sum + (i.amount || 0), 0)
})
const pendingAmount = computed(() => items.value.filter(i => i.status === 'pending' || i.status === 'overdue').reduce((sum, i) => sum + (i.amount || 0), 0))

const loadItems = async () => {
  loading.value = true; error.value = null
  try { const res = await financeService.collections.list(); items.value = res.data.data || [] }
  catch (err) { error.value = err.response?.data?.message || 'Failed to load' }
  finally { loading.value = false }
}

const loadDependencies = async () => {
  try {
    const [studentsRes, feeTypesRes] = await Promise.all([
      studentService.list(), financeService.feeTypes.list()
    ])
    students.value = studentsRes.data.data || []
    feeTypes.value = feeTypesRes.data.data || []
  } catch {}
}

const formatCurrency = (amount) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'BDT', minimumFractionDigits: 0 }).format(amount || 0)
const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

const openCreateDialog = () => { editingItem.value = null; form.value = { student_id: '', fee_type_id: '', amount: 0, paid_date: new Date().toISOString().split('T')[0], payment_method: 'cash', status: 'paid', remarks: '' }; dialogError.value = null; showDialog.value = true }
const openEditDialog = (item) => { editingItem.value = item; form.value = { student_id: item.student_id || '', fee_type_id: item.fee_type_id || '', amount: item.amount || 0, paid_date: item.paid_date?.split('T')[0] || '', payment_method: item.payment_method || 'cash', status: item.status || 'paid', remarks: item.remarks || '' }; dialogError.value = null; showDialog.value = true }
const closeDialog = () => { showDialog.value = false; editingItem.value = null }

const saveItem = async () => {
  dialogLoading.value = true; dialogError.value = null
  try {
    if (editingItem.value) await financeService.collections.update(editingItem.value.id, form.value)
    else await financeService.collections.create(form.value)
    closeDialog(); loadItems()
  } catch (err) { dialogError.value = err.response?.data?.message || 'Failed to save' }
  finally { dialogLoading.value = false }
}

const confirmDelete = (item) => { selectedItem.value = item; showDeleteDialog.value = true }
const deleteItem = async () => {
  deleteLoading.value = true
  try { await financeService.collections.delete(selectedItem.value.id); showDeleteDialog.value = false; loadItems() }
  catch (err) { error.value = err.response?.data?.message || 'Failed to delete'; showDeleteDialog.value = false }
  finally { deleteLoading.value = false }
}

// ============ Smart Collection Methods ============

const loadStudentPendingFees = async () => {
  if (!smartForm.value.student_id) return
  pendingFeesLoading.value = true
  pendingFees.value = null
  selectedAssignmentIds.value = []
  smartError.value = null
  smartSuccess.value = null
  try {
    const res = await smartFeeService.admin.studentPendingFees(smartForm.value.student_id)
    pendingFees.value = res.data.data || res.data
    if (pendingFees.value?.total_due) {
      smartForm.value.amount = pendingFees.value.total_due
    }
  } catch (err) {
    smartError.value = err.response?.data?.message || 'Failed to load pending fees'
  } finally {
    pendingFeesLoading.value = false
  }
}

const toggleAssignment = (id) => {
  const idx = selectedAssignmentIds.value.indexOf(id)
  if (idx === -1) {
    selectedAssignmentIds.value.push(id)
  } else {
    selectedAssignmentIds.value.splice(idx, 1)
  }
  recalcSuggestedAmount()
}

const recalcSuggestedAmount = () => {
  let total = 0
  if (pendingFees.value?.grouped) {
    for (const group of pendingFees.value.grouped) {
      for (const item of group.items) {
        if (selectedAssignmentIds.value.includes(item.id)) {
          total += item.due_amount
        }
      }
    }
  }
  smartForm.value.amount = total > 0 ? total : 0
}

const submitSmartCollection = async () => {
  if (!smartForm.value.amount || smartForm.value.amount <= 0) return
  smartSubmitting.value = true
  smartError.value = null
  smartSuccess.value = null

  let enrollmentId = ''
  if (pendingFees.value?.grouped) {
    for (const group of pendingFees.value.grouped) {
      for (const item of group.items) {
        if (selectedAssignmentIds.value.includes(item.id)) {
          enrollmentId = item.enrollment_id
          break
        }
      }
      if (enrollmentId) break
    }
  }

  try {
    const payload = {
      enrollment_id: enrollmentId,
      student_id: smartForm.value.student_id,
      amount: smartForm.value.amount,
      payment_method: smartForm.value.payment_method,
      fee_assignment_ids: selectedAssignmentIds.value,
      reference_no: smartForm.value.reference_no || undefined,
      remarks: smartForm.value.remarks || 'Manual collection via Smart Fee',
    }
    const res = await smartFeeService.admin.manualPaymentWithAllocations(payload)
    smartSuccess.value = res.data?.message || 'Payment recorded successfully!'
    smartForm.value.amount = 0
    smartForm.value.reference_no = ''
    smartForm.value.remarks = ''
    selectedAssignmentIds.value = []
    pendingFees.value = null
    loadItems()
  } catch (err) {
    smartError.value = err.response?.data?.message || 'Failed to record payment'
  } finally {
    smartSubmitting.value = false
  }
}

onMounted(() => { loadItems(); loadDependencies() })
</script>

<style scoped>
.fee-type-cell { display: inline-flex; align-items: center; gap: 0.4rem; }
.fee-type-name { font-size: 0.85rem; color: var(--text-secondary); }
.cat-badge { display: inline-flex; align-items: center; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; width: fit-content; }
.cat-badge.cat-one_time { background: #dbeafe; color: #1e40af; }
.cat-badge.cat-monthly { background: #d1fae5; color: #065f46; }
.cat-badge.cat-event_based { background: #fef3c7; color: #92400e; }
.page-container { max-width: 1100px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem; }
.header-left { display: flex; align-items: center; gap: 0.75rem; }
.header-left h1 { font-size: 1.5rem; color: var(--text-primary); margin: 0; }
.badge-count { background: #e8eaf6; color: #4f46e5; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.header-actions { display: flex; gap: 0.5rem; }
.summary-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.25rem; }
.summary-card { background: var(--bg-card); border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-sm); }
.summary-label { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem; }
.summary-value { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); }
.summary-value.pending { color: #dc2626; }
.btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; gap: 0.4rem; }
.btn-primary { background: #4f46e5; color: white; }
.btn-primary:hover { background: #4338ca; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-outline { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-color); }
.btn-outline:hover { background: var(--bg-accent); border-color: var(--text-muted); }
.btn-danger { background: #ef4444; color: white; }
.btn-danger:hover { background: #dc2626; }
.loading-state { text-align: center; padding: 3rem; color: var(--text-muted); }
.spinner { width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
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
.badge { padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; background: #f3f4f6; color: var(--text-secondary); }
.status-badge { padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
.status-badge.paid { background: #d1fae5; color: #059669; }
.status-badge.partial { background: #fef3c7; color: #d97706; }
.status-badge.pending { background: #dbeafe; color: #2563eb; }
.status-badge.overdue { background: #fee2e2; color: #dc2626; }
.action-buttons { display: flex; gap: 0.25rem; }
.btn-icon { width: 32px; height: 32px; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.btn-icon:hover { background: #f3f4f6; }
.btn-icon.danger:hover { background: #fef2f2; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(2px); }
.modal-dialog { background: var(--bg-card); border-radius: 16px; width: 90%; max-width: 520px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
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
textarea.form-control { resize: vertical; }
.alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; }
.alert-danger { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.text-danger { color: #dc2626; font-size: 0.85rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); }

/* Tabs */
.tabs { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; }
.tab-btn { padding: 0.5rem 1.25rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-muted); transition: all 0.2s; }
.tab-btn.active { background: #4f46e5; color: white; border-color: #4f46e5; }
.tab-btn:hover:not(.active) { background: var(--bg-accent); border-color: var(--text-muted); }

/* Smart Collection */
.smart-collection { display: flex; flex-direction: column; gap: 1.25rem; }
.card { background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); overflow: hidden; }
.card-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); }
.card-header h3 { margin: 0; font-size: 1rem; color: var(--text-primary); }
.card-body { padding: 1.25rem; }
.badge-info { background: #e8eaf6; color: #4f46e5; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.empty-state-sm { text-align: center; padding: 1.5rem; color: var(--text-muted); }
.fee-group { margin-bottom: 1rem; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; }
.fee-group-header { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; background: var(--bg-accent); border-bottom: 1px solid var(--border-color); }
.fee-category-badge { padding: 0.15rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
.fee-category-badge.one_time { background: #dbeafe; color: #2563eb; }
.fee-category-badge.monthly { background: #d1fae5; color: #059669; }
.fee-category-badge.event_based { background: #fef3c7; color: #d97706; }
.group-total { margin-left: auto; font-size: 0.85rem; font-weight: 600; color: #4f46e5; }
.fee-items { display: flex; flex-direction: column; }
.fee-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 1rem; cursor: pointer; transition: background 0.15s; border-bottom: 1px solid var(--border-light); }
.fee-item:last-child { border-bottom: none; }
.fee-item:hover { background: var(--bg-accent); }
.fee-item.selected { background: #eef2ff; }
.fee-item input[type="checkbox"] { width: 18px; height: 18px; accent-color: #4f46e5; cursor: pointer; flex-shrink: 0; }
.fee-item-info { display: flex; flex-direction: column; flex: 1; }
.period-label { font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); }
.due-date { font-size: 0.75rem; color: var(--text-muted); }
.fee-item-amounts { display: flex; flex-direction: column; align-items: flex-end; gap: 0.15rem; }
.amount-due { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); }
.amount-paid { font-size: 0.75rem; color: var(--text-muted); }
.status-badge.small { font-size: 0.65rem; padding: 0.1rem 0.4rem; }
.alert-success { background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; }
</style>

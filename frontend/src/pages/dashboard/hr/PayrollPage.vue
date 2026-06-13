<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left"><h1>Payroll</h1><span class="badge-count">{{ items.length }} records</span></div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadItems" :disabled="loading">🔄 Refresh</button>
        <button class="btn btn-primary" @click="openCreateDialog">+ Add Payroll</button>
        <button class="btn btn-success" @click="openProcessDialog">⚙️ Process</button>
      </div>
    </div>

    <!-- Summary -->
    <div class="summary-cards" v-if="items.length > 0">
      <div class="summary-card">
        <div class="summary-label">Total Payroll</div>
        <div class="summary-value">{{ formatCurrency(totalPayroll) }}</div>
      </div>
      <div class="summary-card">
        <div class="summary-label">Paid</div>
        <div class="summary-value">{{ formatCurrency(paidAmount) }}</div>
      </div>
      <div class="summary-card">
        <div class="summary-label">Pending</div>
        <div class="summary-value pending">{{ formatCurrency(pendingAmount) }}</div>
      </div>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading payroll...</p></div>
    <div v-else-if="error" class="error-state"><p>⚠️ {{ error }}</p><button class="btn btn-outline" @click="loadItems">Try Again</button></div>
    <div v-else-if="items.length === 0" class="empty-state">
      <div class="empty-icon">💰</div><h3>No Payroll Records</h3><p>Process payroll for employees</p>
      <button class="btn btn-primary" @click="openCreateDialog">+ Add Payroll</button>
    </div>
    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr><th>Employee</th><th>Month</th><th>Year</th><th>Basic</th><th>Allowances</th><th>Deductions</th><th>Net Pay</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td><strong>{{ item.employee?.name || '—' }}</strong></td>
            <td>{{ item.month }}</td>
            <td>{{ item.year }}</td>
            <td>{{ formatCurrency(item.basic_salary) }}</td>
            <td>{{ formatCurrency(item.allowances) }}</td>
            <td>{{ formatCurrency(item.deductions) }}</td>
            <td><strong>{{ formatCurrency(item.net_pay) }}</strong></td>
            <td><span class="status-badge" :class="item.status">{{ item.status }}</span></td>
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

    <!-- Create/Edit Dialog -->
    <div class="modal-overlay" v-if="showDialog" @click.self="closeDialog">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3>{{ editingItem ? 'Edit Payroll' : 'Add Payroll' }}</h3>
          <button class="modal-close" @click="closeDialog">✕</button>
        </div>
        <div class="modal-body">
          <div v-if="dialogError" class="alert alert-danger">{{ dialogError }}</div>
          <div class="form-group">
            <label>Employee <span class="required">*</span></label>
            <select v-model="form.employee_id" class="form-control">
              <option value="">Select employee...</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Month <span class="required">*</span></label>
              <select v-model="form.month" class="form-control">
                <option value="">Select month...</option>
                <option v-for="(m, i) in months" :key="i" :value="m.value">{{ m.label }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Year <span class="required">*</span></label>
              <select v-model="form.year" class="form-control">
                <option value="">Select year...</option>
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Basic Salary <span class="required">*</span></label>
              <input v-model.number="form.basic_salary" type="number" min="0" class="form-control" placeholder="0.00" />
            </div>
            <div class="form-group">
              <label>Allowances</label>
              <input v-model.number="form.allowances" type="number" min="0" class="form-control" placeholder="0.00" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Deductions</label>
              <input v-model.number="form.deductions" type="number" min="0" class="form-control" placeholder="0.00" />
            </div>
            <div class="form-group">
              <label>Net Pay</label>
              <input v-model.number="form.net_pay" type="number" min="0" class="form-control" placeholder="Auto-calculated" />
            </div>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select v-model="form.status" class="form-control">
              <option value="pending">Pending</option>
              <option value="paid">Paid</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="closeDialog">Cancel</button>
          <button class="btn btn-primary" @click="saveItem" :disabled="!form.employee_id || !form.month || !form.year || !form.basic_salary || dialogLoading">
            {{ dialogLoading ? 'Saving...' : (editingItem ? 'Update' : 'Create') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Process Dialog -->
    <div class="modal-overlay" v-if="showProcessDialog" @click.self="showProcessDialog = false">
      <div class="modal-dialog modal-sm">
        <div class="modal-header"><h3>Process Payroll</h3><button class="modal-close" @click="showProcessDialog = false">✕</button></div>
        <div class="modal-body">
          <div v-if="processError" class="alert alert-danger">{{ processError }}</div>
          <div class="form-group">
            <label>Month <span class="required">*</span></label>
            <select v-model="processForm.month" class="form-control">
              <option value="">Select month...</option>
              <option v-for="(m, i) in months" :key="i" :value="m.value">{{ m.label }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Year <span class="required">*</span></label>
            <select v-model="processForm.year" class="form-control">
              <option value="">Select year...</option>
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showProcessDialog = false">Cancel</button>
          <button class="btn btn-success" @click="processPayroll" :disabled="!processForm.month || !processForm.year || processLoading">
            {{ processLoading ? 'Processing...' : 'Process' }}
          </button>
        </div>
      </div>
    </div>

    <div class="modal-overlay" v-if="showDeleteDialog" @click.self="showDeleteDialog = false">
      <div class="modal-dialog modal-sm">
        <div class="modal-header"><h3>Confirm Delete</h3><button class="modal-close" @click="showDeleteDialog = false">✕</button></div>
        <div class="modal-body"><p>Delete this payroll record?</p><p class="text-danger">This cannot be undone.</p></div>
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
import hrService from '@/services/hr.service'

const items = ref([])
const employees = ref([])
const loading = ref(false)
const error = ref(null)
const showDialog = ref(false)
const editingItem = ref(null)
const dialogLoading = ref(false)
const dialogError = ref(null)
const form = ref({ employee_id: '', month: '', year: '', basic_salary: 0, allowances: 0, deductions: 0, net_pay: 0, status: 'pending' })
const showDeleteDialog = ref(false)
const selectedItem = ref(null)
const deleteLoading = ref(false)
const showProcessDialog = ref(false)
const processForm = ref({ month: '', year: '' })
const processLoading = ref(false)
const processError = ref(null)

const months = [
  { value: 'January', label: 'January' }, { value: 'February', label: 'February' },
  { value: 'March', label: 'March' }, { value: 'April', label: 'April' },
  { value: 'May', label: 'May' }, { value: 'June', label: 'June' },
  { value: 'July', label: 'July' }, { value: 'August', label: 'August' },
  { value: 'September', label: 'September' }, { value: 'October', label: 'October' },
  { value: 'November', label: 'November' }, { value: 'December', label: 'December' },
]
const currentYear = new Date().getFullYear()
const years = Array.from({ length: 10 }, (_, i) => currentYear - 5 + i)

const totalPayroll = computed(() => items.value.reduce((sum, i) => sum + (i.net_pay || 0), 0))
const paidAmount = computed(() => items.value.filter(i => i.status === 'paid').reduce((sum, i) => sum + (i.net_pay || 0), 0))
const pendingAmount = computed(() => items.value.filter(i => i.status === 'pending').reduce((sum, i) => sum + (i.net_pay || 0), 0))

const loadItems = async () => {
  loading.value = true; error.value = null
  try { const res = await hrService.payroll.list(); items.value = res.data.data || [] }
  catch (err) { error.value = err.response?.data?.message || 'Failed to load' }
  finally { loading.value = false }
}

const loadEmployees = async () => {
  try { const res = await hrService.employees.list(); employees.value = res.data.data || [] }
  catch {}
}

const formatCurrency = (amount) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'BDT', minimumFractionDigits: 0 }).format(amount || 0)

const openCreateDialog = () => { editingItem.value = null; form.value = { employee_id: '', month: '', year: '', basic_salary: 0, allowances: 0, deductions: 0, net_pay: 0, status: 'pending' }; dialogError.value = null; showDialog.value = true }
const openEditDialog = (item) => { editingItem.value = item; form.value = { employee_id: item.employee_id || '', month: item.month || '', year: item.year || '', basic_salary: item.basic_salary || 0, allowances: item.allowances || 0, deductions: item.deductions || 0, net_pay: item.net_pay || 0, status: item.status || 'pending' }; dialogError.value = null; showDialog.value = true }
const closeDialog = () => { showDialog.value = false; editingItem.value = null }

const saveItem = async () => {
  dialogLoading.value = true; dialogError.value = null
  try {
    if (editingItem.value) await hrService.payroll.update(editingItem.value.id, form.value)
    else await hrService.payroll.create(form.value)
    closeDialog(); loadItems()
  } catch (err) { dialogError.value = err.response?.data?.message || 'Failed to save' }
  finally { dialogLoading.value = false }
}

const openProcessDialog = () => { processForm.value = { month: '', year: '' }; processError.value = null; showProcessDialog.value = true }
const processPayroll = async () => {
  processLoading.value = true; processError.value = null
  try { await hrService.payroll.process(processForm.value); showProcessDialog.value = false; loadItems() }
  catch (err) { processError.value = err.response?.data?.message || 'Failed to process' }
  finally { processLoading.value = false }
}

const confirmDelete = (item) => { selectedItem.value = item; showDeleteDialog.value = true }
const deleteItem = async () => {
  deleteLoading.value = true
  try { await hrService.payroll.delete(selectedItem.value.id); showDeleteDialog.value = false; loadItems() }
  catch (err) { error.value = err.response?.data?.message || 'Failed to delete'; showDeleteDialog.value = false }
  finally { deleteLoading.value = false }
}

onMounted(() => { loadItems(); loadEmployees() })
</script>

<style scoped>
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
.btn-success { background: #059669; color: white; }
.btn-success:hover { background: #047857; }
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
.status-badge { padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
.status-badge.pending { background: #fef3c7; color: #d97706; }
.status-badge.paid { background: #d1fae5; color: #059669; }
.status-badge.cancelled { background: #fee2e2; color: #dc2626; }
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
.alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; }
.alert-danger { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.text-danger { color: #dc2626; font-size: 0.85rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); }
</style>

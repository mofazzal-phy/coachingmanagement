<template>
  <div class="monthly-fee-page">
    <div class="page-header">
      <div>
        <h1>📅 Monthly Fee Records</h1>
        <p class="text-muted">{{ pagination?.total || 0 }} total records</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-sm btn-outline-secondary" @click="loadRecords">
          🔄 Refresh
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
      <div class="summary-card total">
        <div class="card-icon">💰</div>
        <div class="card-info">
          <span class="card-label">Total Fee</span>
          <span class="card-value">৳{{ totalStats.totalFee.toLocaleString() }}</span>
        </div>
      </div>
      <div class="summary-card paid">
        <div class="card-icon">✅</div>
        <div class="card-info">
          <span class="card-label">Total Collected</span>
          <span class="card-value">৳{{ totalStats.totalPaid.toLocaleString() }}</span>
        </div>
      </div>
      <div class="summary-card due">
        <div class="card-icon">⏳</div>
        <div class="card-info">
          <span class="card-label">Total Due</span>
          <span class="card-value">৳{{ totalStats.totalDue.toLocaleString() }}</span>
        </div>
      </div>
      <div class="summary-card pending-confirm">
        <div class="card-icon">🔄</div>
        <div class="card-info">
          <span class="card-label">Pending Confirmation</span>
          <span class="card-value">{{ pendingCount }}</span>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <input v-model="searchQuery" class="search-input" placeholder="🔍 Search by student name, ID, enrollment..." @input="debouncedSearch" />
      <select v-model="filters.payment_status" class="filter-select" @change="loadRecords">
        <option value="">All Payment</option>
        <option value="paid">✅ Paid</option>
        <option value="pending">⏳ Pending</option>
        <option value="partial">⚠️ Partial</option>
      </select>
      <select v-model="filters.month" class="filter-select" @change="loadRecords">
        <option value="">All Months</option>
        <option v-for="m in availableMonths" :key="m" :value="m">{{ m }}</option>
      </select>
      <button class="btn btn-sm btn-outline-secondary" @click="showOverdue = !showOverdue" :class="{ active: showOverdue }">
        🔴 Overdue Only
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="skeleton-wrap">
      <div v-for="n in 5" :key="n" class="sk-row"><span class="sk w40"/><span class="sk w150"/><span class="sk w100"/><span class="sk w70"/><span class="sk w80"/><span class="sk w60"/><span class="sk w60"/></div>
    </div>

    <!-- Empty -->
    <div v-else-if="records.length === 0" class="empty-state">
      <div class="empty-icon">📭</div>
      <h3>No monthly fee records found</h3>
    </div>

    <!-- Table -->
    <div v-else class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Enrollment</th>
            <th>Month</th>
            <th>Fee</th>
            <th>Paid</th>
            <th>Due</th>
            <th>Status</th>
            <th>Payments</th>
            <th>Due Date</th>
            <th style="width:120px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rec in records" :key="rec.id" :class="'row-' + rec.payment_status">
            <td>
              <strong>{{ rec.enrollment?.student?.first_name }} {{ rec.enrollment?.student?.last_name }}</strong>
              <br /><small class="text-muted">{{ rec.enrollment?.student?.student_id }}</small>
            </td>
            <td><span class="enr-no">{{ rec.enrollment?.enrollment_no }}</span></td>
            <td><strong>{{ formatMonth(rec.month) }}</strong></td>
            <td>৳{{ Number(rec.total_monthly_fee).toLocaleString() }}</td>
            <td>৳{{ Number(rec.paid_amount).toLocaleString() }}</td>
            <td>৳{{ Number(rec.due_amount).toLocaleString() }}</td>
            <td><span :class="['status-badge', rec.payment_status]">{{ rec.payment_status }}</span></td>
            <td>
              <div class="payment-indicators">
                <span v-if="rec.confirmed_payments?.length" class="pay-indicator confirmed" title="Confirmed payments">✅ {{ rec.confirmed_payments.length }}</span>
                <span v-if="rec.unconfirmed_payments?.length" class="pay-indicator pending-confirm" title="Awaiting confirmation">🔄 {{ rec.unconfirmed_payments.length }}</span>
                <span v-if="!rec.confirmed_payments?.length && !rec.unconfirmed_payments?.length" class="pay-indicator none">—</span>
              </div>
            </td>
            <td><small>{{ rec.due_date ? new Date(rec.due_date).toLocaleDateString('en-BD', { day:'numeric', month:'short', year:'numeric' }) : '-' }}</small></td>
            <td>
              <div class="action-btns">
                <button class="btn btn-sm btn-primary" @click="openPaymentDialog(rec)" :disabled="rec.payment_status === 'paid'">
                  💳 Pay
                </button>
                <button v-if="rec.confirmed_payments?.length || rec.unconfirmed_payments?.length" class="btn btn-sm btn-outline-secondary" @click="viewPayments(rec)" title="View Payments">
                  📋
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination" class="pagination-footer">
      <div class="pagination-info">
        Showing {{ pagination.from || 0 }} - {{ pagination.to || 0 }} of {{ pagination.total || 0 }}
      </div>
      <div class="pagination-btns">
        <button class="btn btn-sm btn-outline" :disabled="!pagination.prev_page_url" @click="loadRecords(pagination.current_page - 1)">← Prev</button>
        <span class="page-indicator">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
        <button class="btn btn-sm btn-outline" :disabled="!pagination.next_page_url" @click="loadRecords(pagination.current_page + 1)">Next →</button>
      </div>
    </div>

    <!-- Payment Dialog -->
    <div v-if="showPaymentDialog" class="modal-overlay" @click.self="showPaymentDialog = false">
      <div class="modal-content payment-modal">
        <div class="modal-header">
          <h3>💳 Record Monthly Fee Payment</h3>
          <button class="modal-close" @click="showPaymentDialog = false">&times;</button>
        </div>
        <div class="modal-body">
          <div class="payment-info">
            <div class="info-row"><span>Student:</span><strong>{{ selectedRecord?.enrollment?.student?.first_name }} {{ selectedRecord?.enrollment?.student?.last_name }}</strong></div>
            <div class="info-row"><span>Month:</span><strong>{{ formatMonth(selectedRecord?.month) }}</strong></div>
            <div class="info-row"><span>Total Fee:</span><strong>৳{{ Number(selectedRecord?.total_monthly_fee || 0).toLocaleString() }}</strong></div>
            <div class="info-row"><span>Due Amount:</span><strong class="text-danger">৳{{ Number(selectedRecord?.due_amount || 0).toLocaleString() }}</strong></div>
          </div>
          <div class="form-group">
            <label class="form-label">Amount *</label>
            <input v-model.number="paymentForm.amount" type="number" class="form-input" min="1" :max="selectedRecord?.due_amount || 0" />
          </div>
          <div class="form-group">
            <label class="form-label">Payment Method</label>
            <div class="method-chips">
              <button v-for="m in methods" :key="m" type="button" class="method-chip" :class="{active:paymentForm.method===m}" @click="paymentForm.method=m; onMethodChange()">{{ m }}</button>
            </div>
          </div>
          <div v-if="paymentForm.method !== 'cash'" class="form-group">
            <label class="form-label">Transaction ID</label>
            <input v-model="paymentForm.transaction_id" class="form-input" placeholder="e.g. BKASH-XXXXXX" />
          </div>
          <div v-if="['bkash', 'nagad', 'rocket'].includes(paymentForm.method)" class="form-group">
            <label class="form-label">Sender Number</label>
            <input v-model="paymentForm.sender_number" class="form-input" placeholder="e.g. 01XXXXXXXXX" />
          </div>
          <div v-if="paymentForm.method === 'bank_transfer'" class="form-group">
            <label class="form-label">Bank Name</label>
            <input v-model="paymentForm.bank_name" class="form-input" placeholder="e.g. Dutch-Bangla Bank" />
          </div>
          <div class="form-group">
            <label class="form-label">Note (optional)</label>
            <input v-model="paymentForm.note" class="form-input" />
          </div>
          <div v-if="paymentForm.method !== 'cash'" class="info-note">
            <small>⚠️ {{ paymentForm.method }} payments will require admin confirmation before being applied.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-sm btn-outline" @click="showPaymentDialog = false">Cancel</button>
          <button class="btn btn-sm btn-primary" :disabled="!paymentForm.amount || paying" @click="submitPayment">
            {{ paying ? 'Processing...' : '💳 Record Payment' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Payments Detail Dialog -->
    <div v-if="showPaymentsDialog" class="modal-overlay" @click.self="showPaymentsDialog = false">
      <div class="modal-content payments-modal">
        <div class="modal-header">
          <h3>📋 Payments for {{ formatMonth(paymentsRecord?.month) }}</h3>
          <button class="modal-close" @click="showPaymentsDialog = false">&times;</button>
        </div>
        <div class="modal-body">
          <div v-if="!paymentsRecord?.confirmed_payments?.length && !paymentsRecord?.unconfirmed_payments?.length" class="empty-state">
            <p>No payments recorded for this month.</p>
          </div>
          <div v-else>
            <h4 v-if="paymentsRecord?.confirmed_payments?.length" class="payments-section-title">✅ Confirmed Payments</h4>
            <div v-for="pay in paymentsRecord?.confirmed_payments" :key="pay.id" class="payment-card confirmed">
              <div class="pay-header">
                <span class="pay-amount">৳{{ Number(pay.amount).toLocaleString() }}</span>
                <span class="pay-method">{{ pay.payment_method }}</span>
                <span class="pay-status confirmed">Confirmed</span>
              </div>
              <div class="pay-details">
                <span>TxID: {{ pay.transaction_id || 'N/A' }}</span>
                <span>{{ new Date(pay.payment_date).toLocaleDateString('en-BD', { day:'numeric', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) }}</span>
                <a v-if="pay.invoice" :href="`/monthly-fees/${pay.id}/invoice/download`" class="invoice-link" target="_blank" @click.prevent="downloadInvoice(pay.id)">📄 Invoice: {{ pay.invoice.invoice_no }}</a>
              </div>
            </div>
            <h4 v-if="paymentsRecord?.unconfirmed_payments?.length" class="payments-section-title">🔄 Awaiting Confirmation</h4>
            <div v-for="pay in paymentsRecord?.unconfirmed_payments" :key="pay.id" class="payment-card unconfirmed">
              <div class="pay-header">
                <span class="pay-amount">৳{{ Number(pay.amount).toLocaleString() }}</span>
                <span class="pay-method">{{ pay.payment_method }}</span>
                <span class="pay-status unconfirmed">Pending</span>
              </div>
              <div class="pay-details">
                <span>TxID: {{ pay.transaction_id || 'N/A' }}</span>
                <span>Sender: {{ pay.sender_number || 'N/A' }}</span>
                <span>{{ new Date(pay.payment_date).toLocaleDateString('en-BD', { day:'numeric', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-sm btn-outline" @click="showPaymentsDialog = false">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import monthlyFeeService from '@/services/monthly-fee.service';
import { debounce } from '@/utils/api.utils';

export default {
  name: 'MonthlyFeeListPage',
  data() {
    return {
      records: [],
      loading: false,
      searchQuery: '',
      filters: { payment_status: '', month: '' },
      enrollmentId: '',
      pagination: null,
      showOverdue: false,
      showPaymentDialog: false,
      showPaymentsDialog: false,
      selectedRecord: null,
      paymentsRecord: null,
      paying: false,
      pendingCount: 0,
      totalStats: { totalFee: 0, totalPaid: 0, totalDue: 0 },
      paymentForm: { amount: 0, method: 'cash', transaction_id: '', sender_number: '', bank_name: '', note: '' },
      methods: ['cash', 'bkash', 'nagad', 'rocket', 'bank_transfer'],
      availableMonths: [],
    };
  },
  created() {
    this.enrollmentId = this.$route.query.enrollment_id || '';
    this.loadRecords();
    this.loadPendingCount();
    this.generateAvailableMonths();
  },
  methods: {
    debouncedSearch: debounce(function() { this.loadRecords(); }, 400),
    formatMonth(monthStr) {
      if (!monthStr) return '';
      const [y, m] = monthStr.split('-');
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      return months[parseInt(m) - 1] + ' ' + y;
    },
    onMethodChange() {
      if (this.paymentForm.method === 'cash') {
        this.paymentForm.transaction_id = '';
        this.paymentForm.sender_number = '';
        this.paymentForm.bank_name = '';
      }
    },
    generateAvailableMonths() {
      const months = [];
      const now = new Date();
      for (let i = -6; i <= 6; i++) {
        const d = new Date(now.getFullYear(), now.getMonth() + i, 1);
        const m = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        months.push(m);
      }
      this.availableMonths = months;
    },
    async loadPendingCount() {
      try {
        const res = await monthlyFeeService.getPendingConfirmations({ per_page: 1 });
        const data = res.data?.data || res.data;
        this.pendingCount = data.total || data.length || 0;
      } catch (e) {
        console.error('Failed to load pending count:', e);
      }
    },
    async loadRecords(page = 1) {
      this.loading = true;
      try {
        let res;
        if (this.showOverdue) {
          res = await monthlyFeeService.getOverdueRecords({ page, per_page: 15, search: this.searchQuery });
        } else {
          const params = { page, per_page: 15, search: this.searchQuery, ...this.filters };
          if (this.filters.month) params.month = this.filters.month;
          if (this.enrollmentId) params.enrollment_id = this.enrollmentId;
          res = await monthlyFeeService.getRecords(params);
        }
        const data = res.data?.data || res.data;
        this.records = data.data || data || [];
        this.pagination = data.data ? { current_page: data.current_page, last_page: data.last_page, total: data.total, from: data.from, to: data.to, prev_page_url: data.prev_page_url, next_page_url: data.next_page_url } : null;

        // Calculate totals
        let tf = 0, tp = 0, td = 0;
        (this.records || []).forEach(r => {
          tf += Number(r.total_monthly_fee || 0);
          tp += Number(r.paid_amount || 0);
          td += Number(r.due_amount || 0);
        });
        this.totalStats = { totalFee: tf, totalPaid: tp, totalDue: td };
      } catch (e) {
        console.error('Failed to load monthly fee records:', e);
      } finally {
        this.loading = false;
      }
    },
    openPaymentDialog(record) {
      this.selectedRecord = record;
      this.paymentForm = { amount: record.due_amount || 0, method: 'cash', transaction_id: '', sender_number: '', bank_name: '', note: '' };
      this.showPaymentDialog = true;
    },
    viewPayments(record) {
      this.paymentsRecord = record;
      this.showPaymentsDialog = true;
    },
    async downloadInvoice(paymentId) {
      try {
        const res = await monthlyFeeService.downloadInvoice(paymentId);
        const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `invoice-${paymentId}.pdf`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
      } catch (e) {
        console.error('Download failed:', e);
        this.$toast?.error('Failed to download invoice');
      }
    },
    async submitPayment() {
      if (!this.paymentForm.amount || !this.selectedRecord) return;
      this.paying = true;
      try {
        const payload = {
          amount: Number(this.paymentForm.amount),
          payment_method: this.paymentForm.method,
          transaction_id: this.paymentForm.transaction_id || null,
          sender_number: this.paymentForm.sender_number || null,
          bank_name: this.paymentForm.bank_name || null,
          note: this.paymentForm.note || null,
        };
        await monthlyFeeService.recordPayment(this.selectedRecord.id, payload);
        this.showPaymentDialog = false;
        this.selectedRecord = null;
        await this.loadRecords(this.pagination?.current_page || 1);
        await this.loadPendingCount();
        this.$toast?.success('Payment recorded successfully');
      } catch (e) {
        console.error(e);
        this.$toast?.error(e.response?.data?.message || 'Payment failed');
      } finally {
        this.paying = false;
      }
    },
  },
};
</script>

<style scoped>
.monthly-fee-page { max-width: 1200px; }

.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem; }
.page-header h1 { margin: 0; font-size: 1.3rem; }
.text-muted { color: #888; font-size: 0.8rem; }

.header-actions { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }

/* Summary Cards */
.summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
.summary-card { display: flex; align-items: center; gap: 0.75rem; background: var(--bg-card); border-radius: 10px; padding: 0.85rem 1rem; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.summary-card .card-icon { font-size: 1.5rem; }
.summary-card .card-info { display: flex; flex-direction: column; }
.summary-card .card-label { font-size: 0.7rem; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
.summary-card .card-value { font-size: 1.1rem; font-weight: 700; color: var(--text-dark); }
.summary-card.paid .card-value { color: #27ae60; }
.summary-card.due .card-value { color: #e74c3c; }
.summary-card.pending-confirm .card-value { color: #f39c12; }

.filters-bar { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
.search-input { flex: 1; min-width: 180px; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; }
.filter-select { padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.8rem; background: var(--bg-card); }

.table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.table th { background: var(--bg-page); padding: 0.6rem 0.75rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); border-bottom: 2px solid var(--border-color); white-space: nowrap; }
.table td { padding: 0.65rem 0.75rem; border-bottom: 1px solid #f0f0f0; }
.table tr:hover td { background: var(--bg-surface-muted); }
.row-paid td { background: #f0faf4; }
.row-pending td { background: #fffdf5; }
.row-partial td { background: #fff8e1; }

.enr-no { font-family: monospace; font-size: 0.8rem; color: #4a90d9; font-weight: 600; }

.status-badge { padding: 0.15rem 0.5rem; border-radius: 10px; font-size: 0.7rem; font-weight: 600; text-transform: capitalize; }
.status-badge.paid { background: #eafaf1; color: #27ae60; }
.status-badge.pending { background: #fdeaea; color: #e74c3c; }
.status-badge.partial { background: #fff8e1; color: #f39c12; }

.payment-indicators { display: flex; gap: 0.3rem; flex-wrap: wrap; }
.pay-indicator { font-size: 0.7rem; padding: 0.1rem 0.4rem; border-radius: 4px; white-space: nowrap; }
.pay-indicator.confirmed { background: #eafaf1; color: #27ae60; }
.pay-indicator.pending-confirm { background: #fff8e1; color: #f39c12; }
.pay-indicator.none { color: #ccc; }

.action-btns { display: flex; gap: 0.3rem; }

.loading-state, .empty-state { text-align: center; padding: 3rem; }
.empty-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }

.pagination-footer { margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
.pagination-info { font-size: 0.8rem; color: #888; }
.pagination-btns { display: flex; align-items: center; gap: 0.5rem; }
.page-indicator { font-size: 0.8rem; color: var(--text-muted); }

.btn-sm { padding: 0.3rem 0.6rem; font-size: 0.8rem; border-radius: 6px; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 0.2rem; }
.btn-primary { background: #4a90d9; color: #fff; text-decoration: none; }
.btn-outline { border: 1px solid #4a90d9; color: #4a90d9; background: none; }
.btn-outline-secondary { border: 1px solid var(--border-color); color: var(--text-muted); background: var(--bg-card); }
.btn-outline-secondary:hover, .btn-outline-secondary.active { border-color: #4a90d9; color: #4a90d9; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.skeleton-wrap { display: flex; flex-direction: column; gap: 0.5rem; }
.sk-row { display: flex; gap: 0.5rem; padding: 0.7rem; background: var(--bg-card); border-radius: 8px; }
.sk { height: 14px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 4px; display: inline-block; }
.w40{width:40px}.w60{width:60px}.w70{width:70px}.w80{width:80px}.w100{width:100px}.w150{width:150px}
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0}}

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { background: var(--bg-card); border-radius: 14px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 8px 30px rgba(0,0,0,0.15); }
.payments-modal { max-width: 550px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid #eee; }
.modal-header h3 { margin: 0; font-size: 1.05rem; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); padding: 0; line-height: 1; }
.modal-close:hover { color: var(--text-dark); }
.modal-body { padding: 1.5rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem; border-top: 1px solid #eee; }

.payment-info { background: var(--bg-accent); border-radius: 8px; padding: 0.75rem; margin-bottom: 1rem; }
.info-row { display: flex; justify-content: space-between; padding: 0.3rem 0; font-size: 0.85rem; }
.text-danger { color: #e74c3c; }

.form-group { margin-bottom: 0.75rem; }
.form-label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; color: #555; }
.form-input { width: 100%; padding: 0.55rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; box-sizing: border-box; }

.method-chips { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.method-chip { padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 20px; background: var(--bg-card); cursor: pointer; font-size: 0.85rem; text-transform: capitalize; }
.method-chip:hover { border-color: #4a90d9; }
.method-chip.active { background: #4a90d9; color: #fff; border-color: #4a90d9; }

.info-note { background: #fff8e1; border: 1px solid #fce8b3; border-radius: 6px; padding: 0.5rem 0.75rem; margin-top: 0.5rem; }
.info-note small { color: #856404; }

/* Payment Cards */
.payments-section-title { font-size: 0.85rem; margin: 1rem 0 0.5rem; color: #555; }
.payment-card { border: 1px solid var(--border-color); border-radius: 8px; padding: 0.75rem; margin-bottom: 0.5rem; }
.payment-card.confirmed { border-color: #a3d9b1; background: #f0faf4; }
.payment-card.unconfirmed { border-color: #fce8b3; background: #fffdf5; }
.pay-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.4rem; }
.pay-amount { font-size: 1rem; font-weight: 700; color: var(--text-dark); }
.pay-method { font-size: 0.75rem; color: var(--text-muted); background: #f0f0f0; padding: 0.1rem 0.5rem; border-radius: 4px; text-transform: capitalize; }
.pay-status { font-size: 0.7rem; font-weight: 600; padding: 0.1rem 0.5rem; border-radius: 4px; }
.pay-status.confirmed { background: #d1fae5; color: #065f46; }
.pay-status.unconfirmed { background: #fef3c7; color: #92400e; }
.pay-details { display: flex; flex-wrap: wrap; gap: 0.5rem; font-size: 0.75rem; color: var(--text-muted); }
.invoice-link { color: #4a90d9; text-decoration: none; cursor: pointer; }
.invoice-link:hover { text-decoration: underline; }
</style>

<template>
  <div class="payment-confirmation-page">
    <div class="page-header">
      <div>
        <h1>🔄 Payment Confirmations</h1>
        <p class="text-muted">{{ pagination?.total || 0 }} pending confirmations</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-sm btn-outline-secondary" @click="loadPayments">
          🔄 Refresh
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
      <div class="summary-card pending">
        <div class="card-icon">🔄</div>
        <div class="card-info">
          <span class="card-label">Pending</span>
          <span class="card-value">{{ pendingCount }}</span>
        </div>
      </div>
      <div class="summary-card total-amount">
        <div class="card-icon">💰</div>
        <div class="card-info">
          <span class="card-label">Total Amount</span>
          <span class="card-value">৳{{ totalAmount.toLocaleString() }}</span>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <input v-model="searchQuery" class="search-input" placeholder="🔍 Search by student, transaction ID, sender number..." @input="debouncedSearch" />
      <select v-model="filters.payment_method" class="filter-select" @change="loadPayments">
        <option value="">All Methods</option>
        <option value="bkash">bKash</option>
        <option value="nagad">Nagad</option>
        <option value="rocket">Rocket</option>
        <option value="bank_transfer">Bank Transfer</option>
        <option value="online">Online</option>
      </select>
      <input v-model="filters.date_from" type="date" class="filter-select" @change="loadPayments" placeholder="From" />
      <input v-model="filters.date_to" type="date" class="filter-select" @change="loadPayments" placeholder="To" />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="skeleton-wrap">
      <div v-for="n in 3" :key="n" class="sk-card"><span class="sk w200"/><span class="sk w150"/><span class="sk w100"/><span class="sk w250"/></div>
    </div>

    <!-- Empty -->
    <div v-else-if="payments.length === 0" class="empty-state">
      <div class="empty-icon">✅</div>
      <h3>No pending confirmations</h3>
      <p class="text-muted">All payments have been processed.</p>
    </div>

    <!-- Payment Cards -->
    <div v-else class="payment-list">
      <div v-for="pay in payments" :key="pay.id" class="payment-card">
        <div class="payment-card-header">
          <div class="student-info">
            <div class="student-avatar">{{ getInitials(pay.monthly_fee_record?.enrollment?.student) }}</div>
            <div>
              <strong>{{ pay.monthly_fee_record?.enrollment?.student?.first_name }} {{ pay.monthly_fee_record?.enrollment?.student?.last_name }}</strong>
              <br /><small class="text-muted">{{ pay.monthly_fee_record?.enrollment?.student?.student_id }}</small>
            </div>
          </div>
          <div class="payment-amount">৳{{ Number(pay.amount).toLocaleString() }}</div>
        </div>

        <div class="payment-details-grid">
          <div class="detail-item">
            <span class="detail-label">Payment Method</span>
            <span class="detail-value method-badge" :class="pay.payment_method">{{ pay.payment_method }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Transaction ID</span>
            <span class="detail-value">{{ pay.transaction_id || 'N/A' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Sender Number</span>
            <span class="detail-value">{{ pay.sender_number || 'N/A' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Month</span>
            <span class="detail-value">{{ formatMonth(pay.monthly_fee_record?.month) }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Course</span>
            <span class="detail-value">{{ pay.monthly_fee_record?.enrollment?.batch?.course?.name }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Payment Date</span>
            <span class="detail-value">{{ new Date(pay.payment_date).toLocaleDateString('en-BD', { day:'numeric', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) }}</span>
          </div>
          <div class="detail-item" v-if="pay.reference">
            <span class="detail-label">Reference</span>
            <span class="detail-value">{{ pay.reference }}</span>
          </div>
          <div class="detail-item" v-if="pay.note">
            <span class="detail-label">Note</span>
            <span class="detail-value">{{ pay.note }}</span>
          </div>
        </div>

        <div class="payment-card-actions">
          <button class="btn btn-sm btn-success" :disabled="processingId === pay.id" @click="confirmPayment(pay)">
            {{ processingId === pay.id ? 'Processing...' : '✅ Confirm' }}
          </button>
          <button class="btn btn-sm btn-danger" :disabled="processingId === pay.id" @click="openRejectDialog(pay)">
            {{ processingId === pay.id ? 'Processing...' : '❌ Reject' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination && pagination.last_page > 1" class="pagination-footer">
      <div class="pagination-info">
        Showing {{ pagination.from || 0 }} - {{ pagination.to || 0 }} of {{ pagination.total || 0 }}
      </div>
      <div class="pagination-btns">
        <button class="btn btn-sm btn-outline" :disabled="!pagination.prev_page_url" @click="loadPayments(pagination.current_page - 1)">← Prev</button>
        <span class="page-indicator">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
        <button class="btn btn-sm btn-outline" :disabled="!pagination.next_page_url" @click="loadPayments(pagination.current_page + 1)">Next →</button>
      </div>
    </div>

    <!-- Reject Dialog -->
    <div v-if="showRejectDialog" class="modal-overlay" @click.self="showRejectDialog = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>❌ Reject Payment</h3>
          <button class="modal-close" @click="showRejectDialog = false">&times;</button>
        </div>
        <div class="modal-body">
          <div class="payment-info">
            <div class="info-row"><span>Student:</span><strong>{{ rejectPaymentData?.monthly_fee_record?.enrollment?.student?.first_name }} {{ rejectPaymentData?.monthly_fee_record?.enrollment?.student?.last_name }}</strong></div>
            <div class="info-row"><span>Amount:</span><strong>৳{{ Number(rejectPaymentData?.amount || 0).toLocaleString() }}</strong></div>
            <div class="info-row"><span>Method:</span><strong>{{ rejectPaymentData?.payment_method }}</strong></div>
          </div>
          <div class="form-group">
            <label class="form-label">Rejection Reason *</label>
            <textarea v-model="rejectionReason" class="form-textarea" rows="3" placeholder="Explain why this payment is being rejected..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-sm btn-outline" @click="showRejectDialog = false">Cancel</button>
          <button class="btn btn-sm btn-danger" :disabled="!rejectionReason || rejecting" @click="submitRejection">
            {{ rejecting ? 'Rejecting...' : '❌ Reject Payment' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import monthlyFeeService from '@/services/monthly-fee.service';
import { debounce } from '@/utils/api.utils';

export default {
  name: 'PaymentConfirmationPage',
  data() {
    return {
      payments: [],
      loading: false,
      searchQuery: '',
      filters: { payment_method: '', date_from: '', date_to: '' },
      pagination: null,
      pendingCount: 0,
      totalAmount: 0,
      processingId: null,
      showRejectDialog: false,
      rejectPaymentData: null,
      rejectionReason: '',
      rejecting: false,
    };
  },
  created() {
    this.loadPayments();
  },
  methods: {
    debouncedSearch: debounce(function() { this.loadPayments(); }, 400),
    getInitials(student) {
      if (!student) return '?';
      return (student.first_name?.[0] || '') + (student.last_name?.[0] || '');
    },
    formatMonth(monthStr) {
      if (!monthStr) return '';
      const [y, m] = monthStr.split('-');
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      return months[parseInt(m) - 1] + ' ' + y;
    },
    async loadPayments(page = 1) {
      this.loading = true;
      try {
        const params = { page, per_page: 10, search: this.searchQuery, ...this.filters };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
        const res = await monthlyFeeService.getPendingConfirmations(params);
        const data = res.data?.data || res.data;
        this.payments = data.data || data || [];
        this.pagination = data.data ? { current_page: data.current_page, last_page: data.last_page, total: data.total, from: data.from, to: data.to, prev_page_url: data.prev_page_url, next_page_url: data.next_page_url } : null;
        this.pendingCount = data.total || this.payments.length || 0;
        this.totalAmount = (this.payments || []).reduce((sum, p) => sum + Number(p.amount || 0), 0);
      } catch (e) {
        console.error('Failed to load pending payments:', e);
      } finally {
        this.loading = false;
      }
    },
    async confirmPayment(pay) {
      if (!confirm('Confirm this payment of ৳' + Number(pay.amount).toLocaleString() + ' from ' + pay.monthly_fee_record?.enrollment?.student?.first_name + '? An invoice will be generated automatically.')) return;
      this.processingId = pay.id;
      try {
        await monthlyFeeService.confirmPayment(pay.id);
        this.$toast?.success('Payment confirmed and invoice generated!');
        await this.loadPayments(this.pagination?.current_page || 1);
      } catch (e) {
        console.error(e);
        this.$toast?.error(e.response?.data?.message || 'Confirmation failed');
      } finally {
        this.processingId = null;
      }
    },
    openRejectDialog(pay) {
      this.rejectPaymentData = pay;
      this.rejectionReason = '';
      this.showRejectDialog = true;
    },
    async submitRejection() {
      if (!this.rejectionReason || !this.rejectPaymentData) return;
      this.rejecting = true;
      try {
        await monthlyFeeService.rejectPayment(this.rejectPaymentData.id, { rejection_reason: this.rejectionReason });
        this.showRejectDialog = false;
        this.rejectPaymentData = null;
        this.rejectionReason = '';
        this.$toast?.success('Payment rejected');
        await this.loadPayments(this.pagination?.current_page || 1);
      } catch (e) {
        console.error(e);
        this.$toast?.error(e.response?.data?.message || 'Rejection failed');
      } finally {
        this.rejecting = false;
      }
    },
  },
};
</script>

<style scoped>
.payment-confirmation-page { max-width: 900px; }

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
.summary-card.pending .card-value { color: #f39c12; }
.summary-card.total-amount .card-value { color: #4a90d9; }

.filters-bar { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
.search-input { flex: 1; min-width: 180px; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; }
.filter-select { padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.8rem; background: var(--bg-card); }

.loading-state, .empty-state { text-align: center; padding: 3rem; }
.empty-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }

/* Payment Cards */
.payment-list { display: flex; flex-direction: column; gap: 1rem; }
.payment-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.payment-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.student-info { display: flex; align-items: center; gap: 0.75rem; }
.student-avatar { width: 40px; height: 40px; border-radius: 50%; background: #4a90d9; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; }
.payment-amount { font-size: 1.3rem; font-weight: 700; color: #27ae60; }

.payment-details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; padding: 0.75rem; background: var(--bg-accent); border-radius: 8px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: 0.7rem; color: #888; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 0.15rem; }
.detail-value { font-size: 0.85rem; color: var(--text-dark); font-weight: 500; }
.method-badge { display: inline-block; padding: 0.1rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; width: fit-content; }
.method-badge.bkash { background: #e2136e20; color: #e2136e; }
.method-badge.nagad { background: #ed1c2420; color: #ed1c24; }
.method-badge.rocket { background: #662d9120; color: #662d91; }
.method-badge.bank_transfer { background: #4a90d920; color: #4a90d9; }
.method-badge.online { background: #27ae6020; color: #27ae60; }

.payment-card-actions { display: flex; gap: 0.5rem; justify-content: flex-end; }

.pagination-footer { margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
.pagination-info { font-size: 0.8rem; color: #888; }
.pagination-btns { display: flex; align-items: center; gap: 0.5rem; }
.page-indicator { font-size: 0.8rem; color: var(--text-muted); }

.btn-sm { padding: 0.3rem 0.6rem; font-size: 0.8rem; border-radius: 6px; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 0.2rem; }
.btn-success { background: #27ae60; color: #fff; }
.btn-danger { background: #e74c3c; color: #fff; }
.btn-outline { border: 1px solid #4a90d9; color: #4a90d9; background: none; }
.btn-outline-secondary { border: 1px solid var(--border-color); color: var(--text-muted); background: var(--bg-card); }
.btn-outline-secondary:hover { border-color: #4a90d9; color: #4a90d9; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.skeleton-wrap { display: flex; flex-direction: column; gap: 0.75rem; }
.sk-card { display: flex; flex-direction: column; gap: 0.5rem; padding: 1rem; background: var(--bg-card); border-radius: 12px; }
.sk { height: 14px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 4px; display: inline-block; }
.w100{width:100px}.w150{width:150px}.w200{width:200px}.w250{width:250px}
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0}}

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { background: var(--bg-card); border-radius: 14px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 8px 30px rgba(0,0,0,0.15); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid #eee; }
.modal-header h3 { margin: 0; font-size: 1.05rem; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); padding: 0; line-height: 1; }
.modal-close:hover { color: var(--text-dark); }
.modal-body { padding: 1.5rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem; border-top: 1px solid #eee; }

.payment-info { background: var(--bg-accent); border-radius: 8px; padding: 0.75rem; margin-bottom: 1rem; }
.info-row { display: flex; justify-content: space-between; padding: 0.3rem 0; font-size: 0.85rem; }

.form-group { margin-bottom: 0.75rem; }
.form-label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; color: #555; }
.form-textarea { width: 100%; padding: 0.55rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; box-sizing: border-box; resize: vertical; font-family: inherit; }
</style>

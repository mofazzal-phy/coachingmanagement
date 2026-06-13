<template>
  <div class="pending-page">
    <div class="page-header">
      <div>
        <h1>⚠️ Pending Enrollments</h1>
        <p class="text-muted">Enrollments waiting for payment confirmation</p>
      </div>
      <router-link to="/dashboard/enrollment/enrollments/create" class="btn btn-primary">+ New Enrollment</router-link>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <input v-model="searchQuery" class="search-input" placeholder="🔍 Search student or enrollment no..." @input="debouncedSearch" />
      <select v-model="filters.course_id" class="filter-select" @change="loadEnrollments">
        <option value="">All Courses</option>
        <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state"><div class="spinner"></div></div>

    <!-- Empty -->
    <div v-else-if="enrollments.length === 0" class="empty-state">
      <div class="empty-icon">✅</div>
      <h3>No pending enrollments</h3>
      <p>All caught up!</p>
    </div>

    <!-- Pending Cards -->
    <div v-else class="pending-cards">
      <div v-for="enr in enrollments" :key="enr.id" class="pending-card">
        <div class="card-main">
          <div class="enr-info">
            <span class="enr-no">{{ enr.enrollment_no }}</span>
            <strong class="student-name">{{ enr.student?.first_name }} {{ enr.student?.last_name }}</strong>
            <span class="student-phone">📱 {{ enr.student?.phone || 'N/A' }}</span>
          </div>
          <div class="course-info">
            <span class="badge badge-primary">{{ enr.batch?.course?.name || 'N/A' }}</span>
            <span class="badge badge-secondary">{{ enr.batch?.name }}</span>
          </div>
          <div class="payment-info">
            <div class="fee-row">
              <span>Payable: <strong>৳{{ Number(enr.payable_fee).toLocaleString() }}</strong></span>
              <span>Paid: <strong>৳{{ Number(enr.paid_amount).toLocaleString() }}</strong></span>
              <span class="due">Due: <strong class="text-danger">৳{{ Number(enr.due_amount).toLocaleString() }}</strong></span>
            </div>
            <span :class="['status-badge', enr.payment_status]">{{ enr.payment_status }}</span>
          </div>
        </div>
        <div class="card-actions">
          <router-link :to="`/dashboard/enrollment/enrollments/${enr.id}`" class="btn btn-sm btn-outline">View</router-link>
          <button v-if="enr.paid_amount > 0" @click="verifyPayment(enr)" class="btn btn-sm btn-success">✅ Verify</button>
          <button @click="openPaymentModal(enr)" class="btn btn-sm btn-primary">💰 Record Payment</button>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination" class="pagination-footer">
      <pagination :data="pagination" @change="loadEnrollments" />
    </div>

    <!-- Payment Modal -->
    <modal v-if="showPaymentModal" @close="showPaymentModal = false">
      <template #header>Record Payment — {{ payingEnrollment?.enrollment_no }}</template>
      <template #body>
        <div class="payment-form">
          <div class="info-row"><span>Student:</span> <strong>{{ payingEnrollment?.student?.first_name }} {{ payingEnrollment?.student?.last_name }}</strong></div>
          <div class="info-row"><span>Due:</span> <strong class="text-danger">৳{{ Number(payingEnrollment?.due_amount).toLocaleString() }}</strong></div>
          <div class="form-group mt-2">
            <label class="form-label">Amount</label>
            <input v-model.number="paymentForm.amount" type="number" class="form-input" :max="payingEnrollment?.due_amount" min="1" required />
          </div>
          <div class="form-group">
            <label class="form-label">Method</label>
            <select v-model="paymentForm.method" class="form-select">
              <option value="cash">Cash</option>
              <option value="bkash">bKash</option>
              <option value="nagad">Nagad</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Transaction ID</label>
            <input v-model="paymentForm.transaction_id" class="form-input" placeholder="Optional" />
          </div>
        </div>
      </template>
      <template #footer>
        <button @click="showPaymentModal = false" class="btn btn-secondary">Cancel</button>
        <button @click="recordPayment" class="btn btn-primary" :disabled="paying">Record Payment</button>
      </template>
    </modal>
  </div>
</template>

<script>
import enrollmentService from '@/services/enrollment.service';
import { debounce } from '@/utils/api.utils';

export default {
  name: 'PendingEnrollmentsPage',
  data() {
    return {
      enrollments: [],
      courses: [],
      loading: false,
      searchQuery: '',
      filters: { course_id: '' },
      pagination: null,
      showPaymentModal: false,
      payingEnrollment: null,
      paymentForm: { amount: 0, method: 'cash', transaction_id: '' },
      paying: false,
    };
  },
  created() {
    this.loadEnrollments();
    this.loadCourses();
    this.debouncedSearch = debounce(() => this.loadEnrollments(), 300);
  },
  methods: {
    async loadEnrollments(page = 1) {
      this.loading = true;
      try {
        const params = { page, per_page: 15, ...this.filters };
        if (this.searchQuery) params.search = this.searchQuery;
        const res = await enrollmentService.getPendingPayments(params);
        this.enrollments = res.data?.data || res.data || [];
        this.pagination = res.data?.meta || null;
      } catch (e) { console.error(e); }
      finally { this.loading = false; }
    },
    async loadCourses() {
      try {
        const res = await enrollmentService.listAllCourses();
        this.courses = res.data?.data || res.data || [];
      } catch (e) { console.error(e); }
    },
    openPaymentModal(enr) {
      this.payingEnrollment = enr;
      this.paymentForm = { amount: enr.due_amount, method: 'cash', transaction_id: '' };
      this.showPaymentModal = true;
    },
    async recordPayment() {
      this.paying = true;
      try {
        await enrollmentService.recordPayment(this.payingEnrollment.id, this.paymentForm);
        this.showPaymentModal = false;
        this.loadEnrollments();
      } catch (e) {
        alert(e.response?.data?.message || 'Payment failed');
      } finally { this.paying = false; }
    },
    async verifyPayment(enr) {
      if (!confirm('Verify payment for ' + enr.enrollment_no + '?')) return;
      try { await enrollmentService.verifyPayment(enr.id); this.loadEnrollments(); }
      catch { alert('Verification failed'); }
    },
  },
};
</script>

<style scoped>
.pending-page { max-width: 1000px; }

.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
.page-header h1 { margin: 0; font-size: 1.3rem; }
.text-muted { color: #888; font-size: 0.85rem; margin: 0.2rem 0 0 0; }

.filters-bar { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; }
.search-input { flex: 1; min-width: 200px; padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 10px; font-size: 0.9rem; }
.filter-select { padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 10px; font-size: 0.85rem; background: var(--bg-card); }

.pending-cards { display: flex; flex-direction: column; gap: 0.75rem; }

.pending-card {
  background: var(--bg-card);
  border: 1px solid #fde68a;
  border-left: 4px solid #f39c12;
  border-radius: 12px;
  padding: 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: all 0.2s;
}
.pending-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.06); }

.card-main { flex: 1; display: flex; gap: 2rem; align-items: center; flex-wrap: wrap; }

.enr-info { display: flex; flex-direction: column; gap: 0.2rem; min-width: 180px; }
.enr-no { font-family: monospace; font-size: 0.8rem; color: #4a90d9; font-weight: 600; }
.student-name { font-size: 0.95rem; }
.student-phone { font-size: 0.78rem; color: #888; }

.payment-info { display: flex; align-items: center; gap: 1rem; }
.fee-row { display: flex; gap: 1rem; font-size: 0.82rem; }
.due { color: #e74c3c; }

.status-badge {
  padding: 0.2rem 0.6rem;
  border-radius: 10px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
}
.status-badge.pending { background: #fff8e1; color: #f39c12; }
.status-badge.partial { background: #eef4ff; color: #4a90d9; }

.card-actions { display: flex; gap: 0.5rem; }

.badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 10px; font-size: 0.7rem; font-weight: 600; }
.badge-primary { background: #eef4ff; color: #4a90d9; }
.badge-secondary { background: #f0f0f0; color: var(--text-muted); }

.info-row { display: flex; gap: 0.5rem; margin-bottom: 0.3rem; font-size: 0.9rem; }
.form-group { margin-bottom: 0.75rem; }
.form-label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; }
.form-input, .form-select { width: 100%; padding: 0.55rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; }
.mt-2 { margin-top: 0.5rem; }
.text-danger { color: #e74c3c; }

.loading-state, .empty-state { text-align: center; padding: 3rem; }
.spinner { width: 36px; height: 36px; border: 3px solid #eee; border-top-color: #4a90d9; border-radius: 50%; animation: spin 0.7s linear infinite; margin: 0 auto; }
@keyframes spin { to { transform: rotate(360deg); } }
.empty-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
.pagination-footer { margin-top: 1.5rem; display: flex; justify-content: center; }

.btn-sm { padding: 0.35rem 0.85rem; font-size: 0.8rem; border-radius: 8px; cursor: pointer; border: none; }
.btn-success { background: #12b76a; color: #fff; }
.btn-outline { border: 1px solid #4a90d9; color: #4a90d9; background: none; text-decoration: none; }
.btn-outline:hover { background: #eef4ff; }
</style>

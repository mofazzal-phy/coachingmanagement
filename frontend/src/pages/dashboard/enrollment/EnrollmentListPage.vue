<template>
  <div class="enrollment-list-page">
    <div class="page-header">
      <div>
        <h1>📋 Enrollments</h1>
        <p class="text-muted">{{ pagination?.total || 0 }} total enrollments</p>
      </div>
      <div class="header-actions">
        <!-- Export Dropdown (3-dot) -->
        <div class="dropdown" ref="exportDropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-trigger" @click="toggleExportMenu">
            ⋯ <span style="margin-left:4px">Export</span>
          </button>
          <div v-if="showExportMenu" class="dropdown-menu dropdown-menu-right">
            <button class="dropdown-item" @click="exportCSV">📄 Export CSV</button>
            <button class="dropdown-item" @click="exportExcel">📊 Export Excel</button>
            <button class="dropdown-item" @click="exportPDF">📕 Export PDF</button>
            <button class="dropdown-item" @click="printTable">🖨 Print</button>
          </div>
        </div>
        <router-link to="/dashboard/enrollment/enrollments/create" class="btn btn-primary">+ New Enrollment</router-link>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <input v-model="searchQuery" class="search-input" placeholder="🔍 Search by name, ID, phone..." @input="debouncedSearch" />
      <select v-model="filters.status" class="filter-select" @change="loadEnrollments">
        <option value="">All Status</option>
        <option value="pending">⚠️ Pending</option>
        <option value="active">✅ Active</option>
        <option value="completed">✔️ Completed</option>
        <option value="dropped">❌ Dropped</option>
        <option value="waiting">⏳ Waiting</option>
      </select>
      <select v-model="filters.payment_status" class="filter-select" @change="loadEnrollments">
        <option value="">All Payment</option>
        <option value="pending">Unpaid</option>
        <option value="partial">Partial</option>
        <option value="paid">Paid</option>
      </select>
      <select v-model="filters.mode" class="filter-select" @change="loadEnrollments">
        <option value="">All Mode</option>
        <option value="online">🖥 Online</option>
        <option value="offline">🏫 Offline</option>
        <option value="hybrid">🔄 Hybrid</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="skeleton-wrap">
      <div v-for="n in 5" :key="n" class="sk-row"><span class="sk w40"/><span class="sk w150"/><span class="sk w100"/><span class="sk w70"/><span class="sk w80"/><span class="sk w60"/><span class="sk w60"/><span class="sk w80"/></div>
    </div>

    <!-- Empty -->
    <div v-else-if="enrollments.length === 0" class="empty-state">
      <div class="empty-icon">📭</div>
      <h3>No enrollments found</h3>
    </div>

    <!-- Table -->
    <div v-else class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th style="width:32px">
              <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" />
            </th>
            <th>Enrollment No</th>
            <th>Student</th>
            <th>Course / Batch</th>
            <th>Mode</th>
            <th>Fee Type</th>
            <th>Fee</th>
            <th>Paid</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Date</th>
            <th style="width:50px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="enr in enrollments" :key="enr.id" :class="{ 'row-pending': enr.status === 'pending', 'row-waiting': enr.status === 'waiting', 'row-selected': selectedIds.has(enr.id) }">
            <td>
              <input type="checkbox" :checked="selectedIds.has(enr.id)" @change="toggleSelect(enr.id)" />
            </td>
            <td><span class="enr-no">{{ enr.enrollment_no }}</span></td>
            <td>
              <strong>{{ enr.student?.first_name }} {{ enr.student?.last_name }}</strong>
              <br /><small class="text-muted">{{ enr.student?.student_id }}</small>
            </td>
            <td>
              <div>{{ enr.batch?.course?.name || 'N/A' }}</div>
              <small class="text-muted">{{ enr.batch?.name }}</small>
            </td>
            <td><span :class="['mode-badge', enr.mode]">{{ enr.mode }}</span></td>
            <td>
              <span v-if="enr.fee_type === 'monthly'" class="fee-type-badge monthly">📅 Monthly</span>
              <span v-else class="fee-type-badge one-time">💰 One-Time</span>
            </td>
            <td>
              ৳{{ Number(enr.payable_fee).toLocaleString() }}<span v-if="enr.fee_type === 'monthly'"><br><small class="text-muted">/month</small></span>
              <br v-if="Number(enr.enrollment_fee) > 0" /><small v-if="Number(enr.enrollment_fee) > 0" class="text-muted">+ ৳{{ Number(enr.enrollment_fee).toLocaleString() }} adm.</small>
            </td>
            <td>
              ৳{{ totalPaid(enr).toLocaleString() }}
              <br v-if="Number(enr.enrollment_fee_paid) > 0" /><small v-if="Number(enr.enrollment_fee_paid) > 0" class="text-muted">adm. ৳{{ Number(enr.enrollment_fee_paid).toLocaleString() }} paid</small>
            </td>
            <td><span :class="['status-badge', enr.status]">{{ enr.status }}</span></td>
            <td><span :class="['pay-badge', enr.payment_status]">{{ enr.payment_status }}</span></td>
            <td><small>{{ formatDate(enr.created_at) }}</small></td>
            <td>
              <!-- Row Actions Dropdown (3-dot) -->
              <div class="dropdown" :ref="'rowDropdown_' + enr.id">
                <button class="btn btn-sm row-action-trigger" @click.stop="toggleRowMenu(enr.id)">⋮</button>
                <div v-if="openRowMenu === enr.id" class="dropdown-menu dropdown-menu-right" @click.stop>
                  <router-link :to="`/dashboard/enrollment/enrollments/${enr.id}`" class="dropdown-item">👁 View Details</router-link>
                  <router-link :to="`/dashboard/enrollment/enrollments/${enr.id}/edit`" class="dropdown-item">✏️ Edit</router-link>
                  <button v-if="enr.status === 'pending'" class="dropdown-item" @click="confirmEnrollment(enr)">✅ Confirm</button>
                  <button v-if="enr.status !== 'dropped' && enr.status !== 'completed'" class="dropdown-item" @click="dropoutEnrollment(enr)">🚫 Dropout</button>
                  <button class="dropdown-item" @click="showTimeline(enr)">📜 Timeline</button>
                  <div class="dropdown-divider"></div>
                  <button class="dropdown-item text-danger" @click="confirmDelete(enr)">🗑 Delete</button>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Bulk Actions -->
    <div v-if="selectedIds.size > 0" class="bulk-bar">
      <span class="bulk-count">{{ selectedIds.size }} selected</span>
      <button class="btn btn-sm btn-warning" @click="bulkDropout">🚫 Dropout Selected</button>
      <button class="btn btn-sm btn-success" @click="bulkConfirm">✅ Confirm Selected</button>
      <button class="btn btn-sm btn-outline-secondary" @click="selectedIds.clear()">Clear</button>
    </div>

    <!-- Pagination -->
    <div v-if="pagination" class="pagination-footer">
      <div class="pagination-info">
        Showing {{ pagination.from || 0 }} - {{ pagination.to || 0 }} of {{ pagination.total || 0 }}
      </div>
      <div class="pagination-btns">
        <button class="btn btn-sm btn-outline" :disabled="!pagination.prev_page_url" @click="loadEnrollments(pagination.current_page - 1)">← Prev</button>
        <span class="page-indicator">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
        <button class="btn btn-sm btn-outline" :disabled="!pagination.next_page_url" @click="loadEnrollments(pagination.current_page + 1)">Next →</button>
      </div>
    </div>

    <!-- Timeline Modal -->
    <div v-if="showTimelineModal" class="modal-overlay" @click.self="showTimelineModal = false">
      <div class="modal-content timeline-modal">
        <div class="modal-header">
          <h3>📜 Activity Timeline</h3>
          <button class="modal-close" @click="showTimelineModal = false">&times;</button>
        </div>
        <div class="modal-body">
          <div v-if="timelineLoading" class="text-center p-3">Loading...</div>
          <div v-else-if="timeline.length === 0" class="text-center p-3 text-muted">No activity recorded yet.</div>
          <div v-else class="timeline">
            <div v-for="(entry, i) in timeline" :key="i" class="timeline-item">
              <div class="timeline-dot" :class="entry.action"></div>
              <div class="timeline-content">
                <div class="timeline-header">
                  <strong>{{ entry.action }}</strong>
                  <span class="text-muted">{{ formatDate(entry.created_at) }}</span>
                </div>
                <div class="timeline-desc">{{ entry.description || entry.action }} by {{ entry.user?.name || 'System' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal-content confirm-modal">
        <div class="modal-header">
          <h3>🗑 Delete Enrollment</h3>
          <button class="modal-close" @click="deleteTarget = null">&times;</button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete enrollment <strong>{{ deleteTarget.enrollment_no }}</strong>?</p>
          <p class="text-danger">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-sm btn-outline" @click="deleteTarget = null">Cancel</button>
          <button class="btn btn-sm btn-danger" @click="doDelete" :disabled="deleting">{{ deleting ? 'Deleting...' : 'Yes, Delete' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import enrollmentService from '@/services/enrollment.service';
import { debounce } from '@/utils/api.utils';
import { exportToExcel, exportToPDF, printTable } from '@/utils/export.utils';

export default {
  name: 'EnrollmentListPage',
  data() {
    return {
      enrollments: [],
      loading: false,
      searchQuery: '',
      filters: { status: '', payment_status: '', mode: '' },
      pagination: null,
      selectedIds: new Set(),
      // Export dropdown
      showExportMenu: false,
      // Row dropdown
      openRowMenu: null,
      // Timeline
      showTimelineModal: false,
      timelineLoading: false,
      timeline: [],
      // Delete
      deleteTarget: null,
      deleting: false,
    };
  },
  computed: {
    allSelected() {
      return this.enrollments.length > 0 && this.enrollments.every(e => this.selectedIds.has(e.id));
    },
  },
  created() {
    this.loadEnrollments();
    this.debouncedSearch = debounce(() => this.loadEnrollments(), 300);
    document.addEventListener('click', this.handleClickOutside);
  },
  beforeUnmount() {
    document.removeEventListener('click', this.handleClickOutside);
  },
  methods: {
    handleClickOutside(e) {
      // Close export menu if click outside
      if (this.showExportMenu && this.$refs.exportDropdown && !this.$refs.exportDropdown.contains(e.target)) {
        this.showExportMenu = false;
      }
      // Close row menu if click outside
      if (this.openRowMenu !== null) {
        const refKey = 'rowDropdown_' + this.openRowMenu;
        const el = this.$refs[refKey];
        if (el && !el.contains(e.target)) {
          this.openRowMenu = null;
        }
      }
    },

    toggleExportMenu() {
      this.showExportMenu = !this.showExportMenu;
    },

    toggleRowMenu(id) {
      this.openRowMenu = this.openRowMenu === id ? null : id;
    },

    async loadEnrollments(page = 1) {
      this.loading = true;
      try {
        const params = { page, per_page: 20, ...this.filters };
        if (this.searchQuery) params.search = this.searchQuery;
        const res = await enrollmentService.getEnrollments(params);
        this.enrollments = res.data?.data || res.data || [];
        this.pagination = res.data?.meta || null;
      } catch (e) { console.error(e); }
      finally { this.loading = false; }
    },

    toggleSelect(id) {
      if (this.selectedIds.has(id)) this.selectedIds.delete(id);
      else this.selectedIds.add(id);
      // Trigger reactivity
      this.selectedIds = new Set(this.selectedIds);
    },

    toggleSelectAll() {
      if (this.allSelected) {
        this.selectedIds = new Set();
      } else {
        this.selectedIds = new Set(this.enrollments.map(e => e.id));
      }
    },

    async confirmEnrollment(enr) {
      this.openRowMenu = null;
      if (!confirm(`Confirm enrollment ${enr.enrollment_no}?`)) return;
      try {
        await enrollmentService.confirmEnrollment(enr.id);
        this.$toast?.success?.('Enrollment confirmed');
        this.loadEnrollments(this.pagination?.current_page);
      } catch (e) {
        alert(e.response?.data?.message || 'Failed to confirm');
      }
    },

    async dropoutEnrollment(enr) {
      this.openRowMenu = null;
      const reason = prompt(`Reason for dropping ${enr.enrollment_no}? (optional)`, '');
      if (reason === null) return;
      try {
        await enrollmentService.dropoutStudent(enr.id, reason);
        this.$toast?.success?.('Student dropped out');
        this.loadEnrollments(this.pagination?.current_page);
      } catch (e) {
        alert(e.response?.data?.message || 'Failed to dropout');
      }
    },

    confirmDelete(enr) {
      this.openRowMenu = null;
      this.deleteTarget = enr;
    },

    async doDelete() {
      if (!this.deleteTarget) return;
      this.deleting = true;
      try {
        await enrollmentService.deleteEnrollment(this.deleteTarget.id);
        this.$toast?.success?.('Enrollment deleted');
        this.deleteTarget = null;
        this.loadEnrollments(this.pagination?.current_page);
      } catch (e) {
        alert(e.response?.data?.message || 'Failed to delete');
      } finally {
        this.deleting = false;
      }
    },

    async showTimeline(enr) {
      this.openRowMenu = null;
      this.showTimelineModal = true;
      this.timelineLoading = true;
      this.timeline = [];
      try {
        const res = await enrollmentService.getTimeline(enr.id);
        this.timeline = res.data?.data || res.data || [];
      } catch (e) {
        console.error(e);
      } finally {
        this.timelineLoading = false;
      }
    },

    async bulkDropout() {
      if (this.selectedIds.size === 0) return;
      if (!confirm(`Dropout ${this.selectedIds.size} selected enrollments?`)) return;
      for (const id of this.selectedIds) {
        try { await enrollmentService.dropoutStudent(id, 'Bulk dropout'); } catch {}
      }
      this.$toast?.success?.('Bulk dropout completed');
      this.selectedIds = new Set();
      this.loadEnrollments(this.pagination?.current_page);
    },

    async bulkConfirm() {
      if (this.selectedIds.size === 0) return;
      if (!confirm(`Confirm ${this.selectedIds.size} selected enrollments?`)) return;
      for (const id of this.selectedIds) {
        try { await enrollmentService.confirmEnrollment(id); } catch {}
      }
      this.$toast?.success?.('Bulk confirm completed');
      this.selectedIds = new Set();
      this.loadEnrollments(this.pagination?.current_page);
    },

    exportCSV() {
      this.showExportMenu = false;
      const params = { ...this.filters, format: 'csv' };
      if (this.searchQuery) params.search = this.searchQuery;
      enrollmentService.exportEnrollments(params).then(res => {
        const url = window.URL.createObjectURL(new Blob([res.data]));
        const a = document.createElement('a');
        a.href = url;
        a.download = `enrollments-${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
      }).catch(e => {
        alert('Export failed');
        console.error(e);
      });
    },

    exportExcel() {
      this.showExportMenu = false;
      const headers = ['Enrollment No', 'Student Name', 'Student ID', 'Phone', 'Course', 'Batch', 'Mode', 'Total Fee', 'Paid', 'Due', 'Status', 'Payment', 'Date'];
      const rows = this.enrollments.map(e => [
        e.enrollment_no,
        `${e.student?.first_name || ''} ${e.student?.last_name || ''}`.trim(),
        e.student?.student_id || '',
        e.student?.phone || '',
        e.batch?.course?.name || 'N/A',
        e.batch?.name || 'N/A',
        e.mode || '',
        e.payable_fee || 0,
        e.paid_amount || 0,
        e.due_amount || 0,
        e.status || '',
        e.payment_status || '',
        this.formatDate(e.created_at),
      ]);
      exportToExcel(headers, rows, `enrollments-${new Date().toISOString().slice(0,10)}`);
    },

    async exportPDF() {
      this.showExportMenu = false;
      const headers = ['Enrollment No', 'Student', 'Student ID', 'Course', 'Batch', 'Mode', 'Fee', 'Paid', 'Status', 'Payment', 'Date'];
      const rows = this.enrollments.map(e => [
        e.enrollment_no,
        `${e.student?.first_name || ''} ${e.student?.last_name || ''}`.trim(),
        e.student?.student_id || '',
        e.batch?.course?.name || 'N/A',
        e.batch?.name || 'N/A',
        e.mode || '',
        `৳${Number(e.payable_fee).toLocaleString()}`,
        `৳${Number(e.paid_amount).toLocaleString()}`,
        e.status || '',
        e.payment_status || '',
        this.formatDate(e.created_at),
      ]);
      try {
        await exportToPDF('Enrollments List', headers, rows, `enrollments-${new Date().toISOString().slice(0,10)}`);
      } catch (e) {
        console.error('PDF export failed:', e);
        alert('PDF export failed. Please try again.');
      }
    },

    printTable() {
      this.showExportMenu = false;
      const headers = ['Enrollment No', 'Student', 'Student ID', 'Course', 'Batch', 'Mode', 'Fee', 'Paid', 'Status', 'Payment', 'Date'];
      const rows = this.enrollments.map(e => [
        e.enrollment_no,
        `${e.student?.first_name || ''} ${e.student?.last_name || ''}`.trim(),
        e.student?.student_id || '',
        e.batch?.course?.name || 'N/A',
        e.batch?.name || 'N/A',
        e.mode || '',
        `৳${Number(e.payable_fee).toLocaleString()}`,
        `৳${Number(e.paid_amount).toLocaleString()}`,
        e.status || '',
        e.payment_status || '',
        this.formatDate(e.created_at),
      ]);
      printTable('Enrollments List', headers, rows);
    },

    formatDate(d) {
      if (!d) return '-';
      return new Date(d).toLocaleDateString('en-BD', { day: 'numeric', month: 'short', year: 'numeric' });
    },
    // Total collected = course fee paid + admission/enrollment fee paid.
    totalPaid(enr) {
      return Number(enr.paid_amount || 0) + Number(enr.enrollment_fee_paid || 0);
    },
  },
};
</script>

<style scoped>
.enrollment-list-page { max-width: 1200px; }

.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem; }
.page-header h1 { margin: 0; font-size: 1.3rem; }
.text-muted { color: #888; font-size: 0.8rem; }

.header-actions { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }

.filters-bar { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
.search-input { flex: 1; min-width: 180px; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; }
.filter-select { padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.8rem; background: var(--bg-card); }

.table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.table th { background: var(--bg-page); padding: 0.6rem 0.75rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); border-bottom: 2px solid var(--border-color); white-space: nowrap; }
.table td { padding: 0.65rem 0.75rem; border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
.table tr:hover td { background: var(--bg-surface-muted); }
/* Theme-aware row tints — mix the accent into the card surface so they read
   correctly in both light and dark mode (no near-white rows on dark). */
.row-pending td { background: color-mix(in srgb, #f39c12 12%, var(--bg-card)); }
.row-waiting td { background: color-mix(in srgb, #d97706 12%, var(--bg-card)); }
.row-selected td { background: color-mix(in srgb, #4a90d9 16%, var(--bg-card)) !important; }

.enr-no { font-family: monospace; font-size: 0.8rem; color: #4a90d9; font-weight: 600; }

.mode-badge { padding: 0.1rem 0.4rem; border-radius: 8px; font-size: 0.7rem; font-weight: 600; text-transform: capitalize; }
.mode-badge.online { background: color-mix(in srgb, #4a90d9 16%, transparent); color: #4a90d9; }
.mode-badge.offline { background: color-mix(in srgb, #27ae60 16%, transparent); color: #27ae60; }
.mode-badge.hybrid { background: color-mix(in srgb, #f39c12 16%, transparent); color: #f39c12; }

.fee-type-badge { padding: 0.15rem 0.5rem; border-radius: 10px; font-size: 0.7rem; font-weight: 600; white-space: nowrap; }
.fee-type-badge.monthly { background: color-mix(in srgb, #2980b9 16%, transparent); color: #2980b9; }
.fee-type-badge.one-time { background: color-mix(in srgb, #d4a017 18%, transparent); color: #d4a017; }

.status-badge { padding: 0.15rem 0.5rem; border-radius: 10px; font-size: 0.7rem; font-weight: 600; text-transform: capitalize; }
.status-badge.active { background: color-mix(in srgb, #27ae60 16%, transparent); color: #27ae60; }
.status-badge.pending { background: color-mix(in srgb, #f39c12 18%, transparent); color: #f39c12; }
.status-badge.completed { background: color-mix(in srgb, #4a90d9 16%, transparent); color: #4a90d9; }
.status-badge.dropped { background: color-mix(in srgb, #e74c3c 16%, transparent); color: #e74c3c; }
.status-badge.waiting { background: color-mix(in srgb, #d97706 18%, transparent); color: #d97706; }

.pay-badge { padding: 0.15rem 0.5rem; border-radius: 10px; font-size: 0.7rem; font-weight: 600; text-transform: capitalize; }
.pay-badge.paid { background: color-mix(in srgb, #27ae60 16%, transparent); color: #27ae60; }
.pay-badge.pending { background: color-mix(in srgb, #e74c3c 16%, transparent); color: #e74c3c; }
.pay-badge.partial { background: color-mix(in srgb, #f39c12 18%, transparent); color: #f39c12; }

.bulk-bar { display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1rem; background: color-mix(in srgb, #4a90d9 14%, var(--bg-card)); border-radius: 8px; margin-top: 0.5rem; }
.bulk-count { font-weight: 600; font-size: 0.85rem; color: #4a90d9; }

.loading-state, .empty-state { text-align: center; padding: 3rem; }
.spinner { width: 36px; height: 36px; border: 3px solid #eee; border-top-color: #4a90d9; border-radius: 50%; animation: spin 0.7s linear infinite; margin: 0 auto; }
@keyframes spin { to { transform: rotate(360deg); } }
.empty-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }

.pagination-footer { margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
.pagination-info { font-size: 0.8rem; color: #888; }
.pagination-btns { display: flex; align-items: center; gap: 0.5rem; }
.page-indicator { font-size: 0.8rem; color: var(--text-muted); }

/* Dropdown */
.dropdown { position: relative; display: inline-block; }
.dropdown-trigger { cursor: pointer; }
.dropdown-menu { position: absolute; top: 100%; right: 0; z-index: 100; min-width: 180px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; box-shadow: 0 6px 20px rgba(0,0,0,0.12); padding: 0.35rem 0; margin-top: 4px; }
.dropdown-menu-right { right: 0; left: auto; }
.dropdown-item { display: flex; align-items: center; gap: 0.4rem; width: 100%; padding: 0.5rem 1rem; font-size: 0.82rem; color: var(--text-dark); background: none; border: none; text-align: left; cursor: pointer; text-decoration: none; white-space: nowrap; }
.dropdown-item:hover { background: var(--bg-page); color: #4a90d9; }
.dropdown-item.text-danger:hover { color: #e74c3c; }
.dropdown-divider { height: 1px; background: #eee; margin: 0.25rem 0; }

/* Row action trigger (⋮) */
.row-action-trigger { background: none; border: 1px solid transparent; border-radius: 6px; padding: 0.2rem 0.4rem; font-size: 1.1rem; line-height: 1; cursor: pointer; color: #888; }
.row-action-trigger:hover { background: #f0f0f0; border-color: #ddd; color: var(--text-dark); }

.btn-sm { padding: 0.3rem 0.6rem; font-size: 0.8rem; border-radius: 6px; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 0.2rem; }
.btn-primary { background: #4a90d9; color: #fff; text-decoration: none; }
.btn-outline { border: 1px solid #4a90d9; color: #4a90d9; background: none; text-decoration: none; display: inline-block; }
.btn-outline-secondary { border: 1px solid var(--border-color); color: var(--text-muted); background: var(--bg-card); }
.btn-outline-secondary:hover { border-color: #4a90d9; color: #4a90d9; }
.btn-success { background: #27ae60; color: #fff; }
.btn-warning { background: #f39c12; color: #fff; }
.btn-danger { background: #e74c3c; color: #fff; }
.btn-info { background: #3498db; color: #fff; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.skeleton-wrap { display: flex; flex-direction: column; gap: 0.5rem; }
.sk-row { display: flex; gap: 0.5rem; padding: 0.7rem; background: var(--bg-card); border-radius: 8px; }
.sk { height: 14px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 4px; display: inline-block; }
.w40{width:40px}.w60{width:60px}.w70{width:70px}.w80{width:80px}.w100{width:100px}.w150{width:150px}
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0}}

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { background: var(--bg-card); border-radius: 14px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 8px 30px rgba(0,0,0,0.15); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid #eee; }
.modal-header h3 { margin: 0; font-size: 1.05rem; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); padding: 0; line-height: 1; }
.modal-close:hover { color: var(--text-dark); }
.modal-body { padding: 1.5rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem; border-top: 1px solid #eee; }

/* Timeline */
.timeline { position: relative; padding-left: 24px; }
.timeline::before { content: ''; position: absolute; left: 8px; top: 0; bottom: 0; width: 2px; background: #e5e7eb; }
.timeline-item { position: relative; margin-bottom: 1rem; }
.timeline-dot { position: absolute; left: -20px; top: 4px; width: 12px; height: 12px; border-radius: 50%; background: #4a90d9; border: 2px solid #fff; box-shadow: 0 0 0 2px #4a90d9; }
.timeline-dot.created { background: #27ae60; box-shadow: 0 0 0 2px #27ae60; }
.timeline-dot.updated { background: #f39c12; box-shadow: 0 0 0 2px #f39c12; }
.timeline-dot.deleted { background: #e74c3c; box-shadow: 0 0 0 2px #e74c3c; }
.timeline-dot.payment { background: #3498db; box-shadow: 0 0 0 2px #3498db; }
.timeline-dot.confirmed { background: #27ae60; box-shadow: 0 0 0 2px #27ae60; }
.timeline-dot.dropout { background: #e74c3c; box-shadow: 0 0 0 2px #e74c3c; }
.timeline-content { background: var(--bg-accent); padding: 0.6rem 0.8rem; border-radius: 8px; }
.timeline-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.2rem; }
.timeline-header strong { font-size: 0.8rem; text-transform: capitalize; }
.timeline-desc { font-size: 0.78rem; color: var(--text-muted); }

.text-center { text-align: center; }
.text-danger { color: #e74c3c; }
.p-3 { padding: 1rem; }
</style>

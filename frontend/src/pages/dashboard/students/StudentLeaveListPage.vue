<template>
  <div class="student-leave-page">
    <div class="page-header">
      <div>
        <h1>📋 Student Leave Applications</h1>
        <p class="text-muted">Manage and approve/reject student leave requests</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-sm btn-outline-secondary" @click="loadLeaves">
          🔄 Refresh
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
      <div class="summary-card pending" @click="filters.status = 'pending'; loadLeaves()">
        <div class="card-icon">⏳</div>
        <div class="card-info">
          <span class="card-label">Pending</span>
          <span class="card-value">{{ stats.pending }}</span>
        </div>
      </div>
      <div class="summary-card approved" @click="filters.status = 'approved'; loadLeaves()">
        <div class="card-icon">✅</div>
        <div class="card-info">
          <span class="card-label">Approved</span>
          <span class="card-value">{{ stats.approved }}</span>
        </div>
      </div>
      <div class="summary-card rejected" @click="filters.status = 'rejected'; loadLeaves()">
        <div class="card-icon">❌</div>
        <div class="card-info">
          <span class="card-label">Rejected</span>
          <span class="card-value">{{ stats.rejected }}</span>
        </div>
      </div>
      <div class="summary-card total" @click="filters.status = ''; loadLeaves()">
        <div class="card-icon">📋</div>
        <div class="card-info">
          <span class="card-label">Total</span>
          <span class="card-value">{{ stats.total }}</span>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <input v-model="searchQuery" class="search-input" placeholder="🔍 Search by student name or ID..." @input="debouncedSearch" />
      <select v-model="filters.leave_type" class="filter-select" @change="loadLeaves">
        <option value="">All Types</option>
        <option value="sick">Sick</option>
        <option value="personal">Personal</option>
        <option value="emergency">Emergency</option>
        <option value="other">Other</option>
      </select>
      <select v-model="filters.status" class="filter-select" @change="loadLeaves">
        <option value="">All Status</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
      <p>Loading leave applications...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error-state">
      <Message severity="error" :closable="false">{{ error }}</Message>
      <Button label="Try Again" icon="pi pi-refresh" @click="loadLeaves" class="p-button-outlined mt-3" />
    </div>

    <!-- Leave List -->
    <template v-else-if="leaves.length > 0">
      <div class="leave-list">
        <div v-for="leave in leaves" :key="leave.id" class="leave-card" :class="leave.status">
          <div class="leave-header">
            <div class="student-info">
              <strong>{{ leave.student?.first_name }} {{ leave.student?.last_name }}</strong>
              <span class="student-id">ID: {{ leave.student?.student_id }}</span>
            </div>
            <span class="status-badge" :class="leave.status">
              {{ leave.status }}
            </span>
          </div>
          <div class="leave-body">
            <div class="leave-details">
              <div class="detail-row">
                <span class="detail-label">Leave Type:</span>
                <span class="detail-value">{{ leave.leave_type }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Duration:</span>
                <span class="detail-value">{{ formatDate(leave.start_date) }} - {{ formatDate(leave.end_date) }} ({{ leave.total_days }} days)</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Reason:</span>
                <span class="detail-value">{{ leave.reason }}</span>
              </div>
              <div class="detail-row" v-if="leave.rejection_reason">
                <span class="detail-label">Rejection Reason:</span>
                <span class="detail-value text-danger">{{ leave.rejection_reason }}</span>
              </div>
              <div class="detail-row" v-if="leave.approver">
                <span class="detail-label">Reviewed By:</span>
                <span class="detail-value">{{ leave.approver?.name }}</span>
              </div>
            </div>
            <div class="leave-actions" v-if="leave.status === 'pending'">
              <button class="btn btn-success btn-sm" @click="approveLeave(leave.id)">
                ✅ Approve
              </button>
              <button class="btn btn-danger btn-sm" @click="openRejectDialog(leave)">
                ❌ Reject
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div class="pagination" v-if="pagination">
        <button :disabled="!pagination.prev_page_url" @click="changePage(pagination.current_page - 1)">Previous</button>
        <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
        <button :disabled="!pagination.next_page_url" @click="changePage(pagination.current_page + 1)">Next</button>
      </div>
    </template>

    <!-- Empty -->
    <div v-else class="empty-state">
      <i class="pi pi-inbox empty-icon"></i>
      <h3>No Leave Applications</h3>
      <p>There are no student leave applications matching your filters.</p>
    </div>

    <!-- Reject Dialog -->
    <Dialog v-model:visible="rejectDialogVisible" header="Reject Leave Application" :modal="true" :style="{ width: '450px' }">
      <div class="dialog-content">
        <p>Are you sure you want to reject this leave application?</p>
        <div class="form-group">
          <label>Rejection Reason *</label>
          <textarea v-model="rejectionReason" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea>
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" icon="pi pi-times" @click="rejectDialogVisible = false" class="p-button-text" />
        <Button label="Reject" icon="pi pi-check" @click="rejectLeave" :disabled="!rejectionReason" class="p-button-danger" />
      </template>
    </Dialog>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue'
import apiClient from '@/services/api.service'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Message from 'primevue/message'

export default {
  name: 'StudentLeaveListPage',
  components: { Dialog, Button, Message },
  setup() {
    const loading = ref(false)
    const error = ref(null)
    const leaves = ref([])
    const pagination = ref(null)
    const searchQuery = ref('')
    const filters = reactive({
      status: '',
      leave_type: '',
    })
    const stats = reactive({ pending: 0, approved: 0, rejected: 0, total: 0 })

    // Reject dialog
    const rejectDialogVisible = ref(false)
    const rejectionReason = ref('')
    const selectedLeave = ref(null)

    let debounceTimer = null

    const loadLeaves = async (page = 1) => {
      loading.value = true
      error.value = null
      try {
        const params = { page, per_page: 15, ...filters }
        if (searchQuery.value) params.search = searchQuery.value

        const res = await apiClient.get('/v1/student-leaves', { params })
        const data = res.data?.data || {}
        leaves.value = data.data || []
        pagination.value = {
          current_page: data.current_page,
          last_page: data.last_page,
          prev_page_url: data.prev_page_url,
          next_page_url: data.next_page_url,
          total: data.total,
        }

        // Calculate stats from all pages (simplified - just count current)
        stats.pending = leaves.value.filter(l => l.status === 'pending').length
        stats.approved = leaves.value.filter(l => l.status === 'approved').length
        stats.rejected = leaves.value.filter(l => l.status === 'rejected').length
        stats.total = leaves.value.length
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load leave applications.'
      } finally {
        loading.value = false
      }
    }

    const debouncedSearch = () => {
      clearTimeout(debounceTimer)
      debounceTimer = setTimeout(() => loadLeaves(), 500)
    }

    const changePage = (page) => {
      if (page < 1 || page > (pagination.value?.last_page || 1)) return
      loadLeaves(page)
    }

    const approveLeave = async (id) => {
      if (!confirm('Are you sure you want to approve this leave application?')) return
      try {
        await apiClient.post(`/v1/student-leaves/${id}/approve`)
        loadLeaves()
      } catch (err) {
        alert(err.response?.data?.message || 'Failed to approve leave.')
      }
    }

    const openRejectDialog = (leave) => {
      selectedLeave.value = leave
      rejectionReason.value = ''
      rejectDialogVisible.value = true
    }

    const rejectLeave = async () => {
      if (!rejectionReason.value || !selectedLeave.value) return
      try {
        await apiClient.post(`/v1/student-leaves/${selectedLeave.value.id}/reject`, {
          rejection_reason: rejectionReason.value,
        })
        rejectDialogVisible.value = false
        selectedLeave.value = null
        rejectionReason.value = ''
        loadLeaves()
      } catch (err) {
        alert(err.response?.data?.message || 'Failed to reject leave.')
      }
    }

    const formatDate = (date) => {
      if (!date) return ''
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric'
      })
    }

    onMounted(() => {
      loadLeaves()
    })

    return {
      loading, error, leaves, pagination, searchQuery, filters, stats,
      rejectDialogVisible, rejectionReason,
      loadLeaves, debouncedSearch, changePage,
      approveLeave, openRejectDialog, rejectLeave, formatDate,
    }
  }
}
</script>

<style scoped>
.student-leave-page {
  max-width: 1000px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.page-header h1 {
  margin: 0 0 0.25rem;
  font-size: 1.5rem;
  color: var(--text-primary);
}

.text-muted {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin: 0;
}

.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.summary-card {
  background: var(--bg-card);
  padding: 1rem;
  border-radius: 12px;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  cursor: pointer;
  transition: transform 0.2s;
}

.summary-card:hover {
  transform: translateY(-2px);
}

.card-icon {
  font-size: 1.5rem;
}

.card-label {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.card-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
}

.filters-bar {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.search-input {
  flex: 1;
  min-width: 200px;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.9rem;
}

.filter-select {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.9rem;
  background: var(--bg-card);
}

.leave-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.leave-card {
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  overflow: hidden;
  border-left: 4px solid #d1d5db;
}

.leave-card.pending { border-left-color: #d97706; }
.leave-card.approved { border-left-color: #059669; }
.leave-card.rejected { border-left-color: #dc2626; }

.leave-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.25rem;
  background: var(--bg-accent);
  border-bottom: 1px solid var(--border-color);
}

.student-info {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.student-info strong {
  font-size: 1rem;
  color: var(--text-primary);
}

.student-id {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: capitalize;
}

.status-badge.pending { background: #fef3c7; color: #d97706; }
.status-badge.approved { background: #d1fae5; color: #059669; }
.status-badge.rejected { background: #fee2e2; color: #dc2626; }

.leave-body {
  padding: 1rem 1.25rem;
}

.leave-details {
  margin-bottom: 1rem;
}

.detail-row {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.detail-label {
  color: var(--text-muted);
  min-width: 120px;
  flex-shrink: 0;
}

.detail-value {
  color: var(--text-primary);
  word-break: break-word;
}

.text-danger { color: #dc2626; }

.leave-actions {
  display: flex;
  gap: 0.5rem;
  padding-top: 0.75rem;
  border-top: 1px solid var(--border-color);
}

.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 8px;
  font-size: 0.85rem;
  cursor: pointer;
  font-weight: 500;
  transition: opacity 0.2s;
}

.btn:hover { opacity: 0.9; }
.btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; }
.btn-success { background: #059669; color: white; }
.btn-danger { background: #dc2626; color: white; }
.btn-outline-secondary { background: transparent; border: 1px solid var(--border-color); color: var(--text-secondary); }

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin-top: 1.5rem;
  padding: 1rem;
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.pagination button {
  padding: 0.5rem 1rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--bg-card);
  cursor: pointer;
  font-size: 0.85rem;
}

.pagination button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.loading-state, .error-state, .empty-state {
  text-align: center;
  padding: 3rem;
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.empty-icon {
  font-size: 3rem;
  color: #d1d5db;
  margin-bottom: 1rem;
}

.dialog-content {
  padding: 1rem 0;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--text-secondary);
}

.form-control {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.9rem;
  box-sizing: border-box;
}
</style>

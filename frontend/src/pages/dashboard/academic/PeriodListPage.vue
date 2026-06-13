<template>
  <div class="period-page">
    <div class="page-header">
      <h1 class="page-title">Time Slot / Period Management</h1>
      <button class="btn btn-primary" @click="openCreateModal">
        <i class="fas fa-plus"></i> Add Period
      </button>
    </div>

    <!-- Filters -->
    <div class="filters card">
      <div class="filter-row">
        <div class="form-group">
          <label>Academic Session</label>
          <select v-model="filters.academic_session_id" class="form-control" @change="loadPeriods">
            <option value="">All Sessions</option>
            <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select v-model="filters.status" class="form-control" @change="loadPeriods">
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="form-group">
          <label>Show Breaks</label>
          <select v-model="filters.is_break" class="form-control" @change="loadPeriods">
            <option value="">All</option>
            <option value="0">Regular Periods</option>
            <option value="1">Break Periods</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner"></div>
      <p>Loading periods...</p>
    </div>

    <!-- Period Table -->
    <div v-else class="card">
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Start Time</th>
              <th>End Time</th>
              <th>Duration</th>
              <th>Session</th>
              <th>Break</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="periods.length === 0">
              <td colspan="9" class="text-center">No periods found. Create your first time slot!</td>
            </tr>
            <tr v-for="(period, idx) in periods" :key="period.id">
              <td>{{ period.sort_order || idx + 1 }}</td>
              <td><strong>{{ period.name }}</strong></td>
              <td><span class="time-badge">{{ formatTime(period.start_time) }}</span></td>
              <td><span class="time-badge">{{ formatTime(period.end_time) }}</span></td>
              <td><span class="duration">{{ calculateDuration(period.start_time, period.end_time) }}</span></td>
              <td>{{ period.academic_session?.name || '—' }}</td>
              <td>
                <span :class="['badge', period.is_break ? 'badge-warning' : 'badge-secondary']">
                  {{ period.is_break ? 'Break' : 'Class' }}
                </span>
              </td>
              <td>
                <span :class="['badge', period.status === 'active' ? 'badge-success' : 'badge-danger']">
                  {{ period.status }}
                </span>
              </td>
              <td>
                <button class="btn btn-sm btn-outline" @click="openEditModal(period)" title="Edit">
                  <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(period)" title="Delete">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination" class="pagination-wrapper">
        <div class="pagination-info">
          Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} periods
        </div>
        <div class="pagination-buttons">
          <button class="btn btn-sm btn-outline" :disabled="!pagination.prev_page_url" @click="goToPage(pagination.current_page - 1)">
            Previous
          </button>
          <span class="page-indicator">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <button class="btn btn-sm btn-outline" :disabled="!pagination.next_page_url" @click="goToPage(pagination.current_page + 1)">
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ editingPeriod ? 'Edit Period' : 'Add New Period' }}</h3>
          <button class="btn-close" @click="closeModal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Academic Session <span class="required">*</span></label>
            <select v-model="form.academic_session_id" class="form-control" required>
              <option value="">Select Session</option>
              <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Period Name <span class="required">*</span></label>
              <input type="text" v-model="form.name" class="form-control" placeholder="e.g. 1st Period" required />
            </div>
            <div class="form-group">
              <label>Sort Order</label>
              <input type="number" v-model="form.sort_order" class="form-control" placeholder="e.g. 1" min="1" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Start Time <span class="required">*</span></label>
              <input type="time" v-model="form.start_time" class="form-control" required />
            </div>
            <div class="form-group">
              <label>End Time <span class="required">*</span></label>
              <input type="time" v-model="form.end_time" class="form-control" required />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>&nbsp;</label>
              <label class="checkbox-label">
                <input type="checkbox" v-model="form.is_break" />
                <span>This is a Break Period (e.g. Tiffin, Prayer)</span>
              </label>
            </div>
            <div class="form-group" v-if="editingPeriod">
              <label>Status</label>
              <select v-model="form.status" class="form-control">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeModal">Cancel</button>
          <button class="btn btn-primary" @click="savePeriod" :disabled="saving">
            {{ saving ? 'Saving...' : (editingPeriod ? 'Update Period' : 'Create Period') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation -->
    <div v-if="showDeleteConfirm" class="modal-overlay" @click.self="showDeleteConfirm = false">
      <div class="modal modal-sm">
        <div class="modal-header">
          <h3>Delete Period</h3>
          <button class="btn-close" @click="showDeleteConfirm = false">&times;</button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete <strong>{{ deletingPeriod?.name }}</strong>?</p>
          <p class="text-danger">This will also remove all routines using this period.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showDeleteConfirm = false">Cancel</button>
          <button class="btn btn-danger" @click="deletePeriod" :disabled="saving">
            {{ saving ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import academicService from '@/services/academic.service'

export default {
  name: 'PeriodListPage',
  data() {
    return {
      loading: false,
      saving: false,
      sessions: [],
      periods: [],
      pagination: null,
      filters: {
        academic_session_id: '',
        status: '',
        is_break: '',
        per_page: 20,
      },
      showModal: false,
      editingPeriod: null,
      showDeleteConfirm: false,
      deletingPeriod: null,
      form: {
        academic_session_id: '',
        name: '',
        start_time: '',
        end_time: '',
        sort_order: '',
        is_break: false,
        status: 'active',
      },
    }
  },
  async mounted() {
    await this.loadSessions()
    await this.loadPeriods()
  },
  methods: {
    formatTime(time) {
      if (!time) return '—'
      try {
        const [h, m] = time.split(':')
        const hour = parseInt(h)
        const ampm = hour >= 12 ? 'PM' : 'AM'
        const hour12 = hour % 12 || 12
        return `${hour12}:${m} ${ampm}`
      } catch {
        return time
      }
    },
    calculateDuration(start, end) {
      if (!start || !end) return '—'
      try {
        const [sh, sm] = start.split(':').map(Number)
        const [eh, em] = end.split(':').map(Number)
        const startMin = sh * 60 + sm
        const endMin = eh * 60 + em
        const diff = endMin - startMin
        if (diff <= 0) return '—'
        const hrs = Math.floor(diff / 60)
        const mins = diff % 60
        if (hrs > 0) return `${hrs}h ${mins}m`
        return `${mins} min`
      } catch {
        return '—'
      }
    },
    async loadSessions() {
      try {
        const res = await academicService.sessions.list({ per_page: 50 })
        this.sessions = res.data?.data || []
        if (this.sessions.length > 0) {
          const current = this.sessions.find(s => s.is_current)
          if (current) this.filters.academic_session_id = current.id
        }
      } catch (e) {
        console.error('Failed to load sessions:', e)
      }
    },
    async loadPeriods(page = 1) {
      this.loading = true
      try {
        const params = { ...this.filters, page }
        Object.keys(params).forEach(k => { if (params[k] === '' || params[k] === null || params[k] === undefined) delete params[k] })
        const res = await academicService.periods.list(params)
        const data = res.data?.data || {}
        this.periods = data.data || []
        this.pagination = {
          current_page: data.current_page,
          last_page: data.last_page,
          from: data.from,
          to: data.to,
          total: data.total,
          prev_page_url: data.prev_page_url,
          next_page_url: data.next_page_url,
        }
      } catch (e) {
        console.error('Failed to load periods:', e)
        this.$toast?.error('Failed to load periods')
      } finally {
        this.loading = false
      }
    },
    goToPage(page) {
      if (page >= 1 && page <= (this.pagination?.last_page || 1)) {
        this.loadPeriods(page)
      }
    },
    openCreateModal() {
      this.editingPeriod = null
      this.form = {
        academic_session_id: this.filters.academic_session_id || '',
        name: '',
        start_time: '',
        end_time: '',
        sort_order: '',
        is_break: false,
        status: 'active',
      }
      this.showModal = true
    },
    openEditModal(period) {
      this.editingPeriod = period
      this.form = {
        academic_session_id: period.academic_session_id,
        name: period.name,
        start_time: period.start_time,
        end_time: period.end_time,
        sort_order: period.sort_order || '',
        is_break: period.is_break || false,
        status: period.status || 'active',
      }
      this.showModal = true
    },
    closeModal() {
      this.showModal = false
      this.editingPeriod = null
    },
    async savePeriod() {
      if (!this.form.name || !this.form.start_time || !this.form.end_time || !this.form.academic_session_id) {
        this.$toast?.error('Name, Session, Start Time and End Time are required')
        return
      }
      this.saving = true
      try {
        const payload = { ...this.form }
        payload.sort_order = payload.sort_order ? parseInt(payload.sort_order) : null

        if (this.editingPeriod) {
          await academicService.periods.update(this.editingPeriod.id, payload)
          this.$toast?.success('Period updated successfully')
        } else {
          await academicService.periods.create(payload)
          this.$toast?.success('Period created successfully')
        }
        this.closeModal()
        await this.loadPeriods()
      } catch (e) {
        const msg = e.response?.data?.message || 'Failed to save period'
        this.$toast?.error(msg)
      } finally {
        this.saving = false
      }
    },
    confirmDelete(period) {
      this.deletingPeriod = period
      this.showDeleteConfirm = true
    },
    async deletePeriod() {
      if (!this.deletingPeriod) return
      this.saving = true
      try {
        await academicService.periods.delete(this.deletingPeriod.id)
        this.$toast?.success('Period deleted successfully')
        this.showDeleteConfirm = false
        this.deletingPeriod = null
        await this.loadPeriods()
      } catch (e) {
        const msg = e.response?.data?.message || 'Failed to delete period'
        this.$toast?.error(msg)
      } finally {
        this.saving = false
      }
    },
  },
}
</script>

<style scoped>
.period-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.page-title { font-size: 24px; font-weight: 600; color: #1a202c; }
.filters { margin-bottom: 20px; padding: 20px; }
.filter-row { display: flex; gap: 20px; flex-wrap: wrap; }
.filter-row .form-group { flex: 1; min-width: 200px; }
.table-wrapper { overflow-x: auto; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-color); }
.table th { background: #f7fafc; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #4a5568; }
.table tbody tr:hover { background: #f7fafc; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.badge-success { background: #c6f6d5; color: #276749; }
.badge-warning { background: #fefcbf; color: #975a16; }
.badge-secondary { background: #edf2f7; color: #4a5568; }
.badge-danger { background: #fed7d7; color: #9b2c2c; }
.time-badge { background: #ebf8ff; color: #2b6cb0; padding: 3px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; font-family: monospace; }
.duration { font-size: 12px; color: #718096; }
.pagination-wrapper { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-top: 1px solid #e2e8f0; flex-wrap: wrap; gap: 10px; }
.pagination-info { font-size: 13px; color: #718096; }
.pagination-buttons { display: flex; align-items: center; gap: 8px; }
.page-indicator { font-size: 13px; color: #4a5568; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: var(--bg-card); border-radius: 12px; width: 550px; max-width: 90vw; max-height: 90vh; overflow-y: auto; }
.modal-sm { width: 400px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--border-color); }
.modal-header h3 { margin: 0; font-size: 18px; }
.btn-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #718096; }
.modal-body { padding: 20px; }
.modal-body .form-group { margin-bottom: 16px; }
.form-row { display: flex; gap: 16px; }
.form-row .form-group { flex: 1; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 20px; border-top: 1px solid #e2e8f0; }
.checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; padding-top: 8px; }
.checkbox-label input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
.required { color: #e53e3e; }
.text-center { text-align: center; }
.text-danger { color: #e53e3e; }
.py-5 { padding: 40px 0; }
.spinner { width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #4299e1; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 12px; }
@keyframes spin { to { transform: rotate(360deg); } }
.btn { padding: 8px 16px; border-radius: 8px; font-size: 14px; cursor: pointer; border: 1px solid transparent; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: #4299e1; color: white; border-color: #4299e1; }
.btn-primary:hover { background: #3182ce; }
.btn-secondary { background: #edf2f7; color: #4a5568; border-color: #e2e8f0; }
.btn-secondary:hover { background: #e2e8f0; }
.btn-danger { background: #e53e3e; color: white; border-color: #e53e3e; }
.btn-danger:hover { background: #c53030; }
.btn-outline { background: var(--bg-card); color: #4a5568; border-color: #e2e8f0; }
.btn-outline:hover { background: #f7fafc; }
.btn-outline-danger { background: var(--bg-card); color: #e53e3e; border-color: #fed7d7; }
.btn-outline-danger:hover { background: #fff5f5; }
.btn-sm { padding: 4px 10px; font-size: 12px; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.card { background: var(--bg-card); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.form-control { width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; transition: border-color 0.2s; box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #4299e1; box-shadow: 0 0 0 3px rgba(66,153,225,0.15); }
label { display: block; font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 6px; }
</style>

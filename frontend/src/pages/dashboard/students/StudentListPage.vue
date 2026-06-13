<template>
  <div class="page-container">
    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <h1>👨‍🎓 Students</h1>
        <p class="text-muted">Manage all students — enrolled & non-enrolled</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary" @click="$router.push('/dashboard/students/create')">
          ➕ Add Student
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input
          v-model="filters.search"
          type="text"
          class="form-input"
          placeholder="Search by name, ID, email, phone..."
          @input="debouncedSearch"
        />
      </div>
      <div class="filter-tabs">
        <button
          :class="['filter-tab', { active: filters.enrollment_status === '' }]"
          @click="filters.enrollment_status = ''; fetchStudents()"
        >All</button>
        <button
          :class="['filter-tab', { active: filters.enrollment_status === 'enrolled' }]"
          @click="filters.enrollment_status = 'enrolled'; fetchStudents()"
        >📋 Enrolled</button>
        <button
          :class="['filter-tab', { active: filters.enrollment_status === 'non_enrolled' }]"
          @click="filters.enrollment_status = 'non_enrolled'; fetchStudents()"
        >📝 Non-Enrolled</button>
      </div>
    </div>

    <!-- Stats Summary -->
    <div class="stats-row" v-if="stats">
      <div class="stat-card blue">
        <span class="stat-val">{{ stats.total }}</span>
        <span class="stat-lbl">Total Students</span>
      </div>
      <div class="stat-card green">
        <span class="stat-val">{{ stats.enrolled }}</span>
        <span class="stat-lbl">Enrolled</span>
      </div>
      <div class="stat-card orange">
        <span class="stat-val">{{ stats.nonEnrolled }}</span>
        <span class="stat-lbl">Non-Enrolled</span>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading students...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error-state">
      <p class="error-msg">{{ error }}</p>
      <button class="btn btn-outline" @click="fetchStudents">Retry</button>
    </div>

    <!-- Empty State -->
    <div v-else-if="students.length === 0" class="empty-state">
      <div class="empty-icon">👨‍🎓</div>
      <h3>No Students Found</h3>
      <p v-if="filters.enrollment_status === 'enrolled'">No enrolled students yet. Create an enrollment first.</p>
      <p v-else-if="filters.enrollment_status === 'non_enrolled'">All students are enrolled! 🎉</p>
      <p v-else>No students yet. Click "Add Student" to create one.</p>
      <button v-if="filters.search" class="btn btn-outline" @click="filters.search = ''; fetchStudents()">Clear Search</button>
    </div>

    <!-- Student Table -->
    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Student ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Class</th>
            <th>Status</th>
            <th>Enrollment</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="student in students" :key="student.id" class="clickable-row" @click="viewStudent(student)">
            <td>
              <span class="badge badge-id clickable-badge">{{ student.student_id }}</span>
            </td>
            <td>
              <div class="student-name">
                <div class="avatar">
                  <img v-if="getPhotoUrl(student.photo_url || student.photo)" :src="getPhotoUrl(student.photo_url || student.photo)" alt="" class="avatar-img" />
                  <span v-else>{{ getInitials(student) }}</span>
                </div>
                <div>
                  <strong class="name-link">{{ student.first_name }} {{ student.last_name }}</strong>
                </div>
              </div>
            </td>
            <td>{{ student.email || '—' }}</td>
            <td>{{ student.phone || '—' }}</td>
            <td>{{ student.current_class?.name || '—' }}</td>
            <td>
              <span :class="['badge', 'badge-' + (student.status || 'active')]">
                {{ student.status || 'active' }}
              </span>
            </td>
            <td>
              <span v-if="student.enrollments_count > 0" class="badge badge-enrolled">
                ✅ Enrolled ({{ student.enrollments_count }})
              </span>
              <span v-else class="badge badge-non-enrolled">
                ❌ Not Enrolled
              </span>
            </td>
            <td>
              <div class="action-btns">
                <button class="btn-icon" title="View Details" @click="viewStudent(student)">
                  👁️
                </button>
                <button class="btn-icon" title="ID Card" @click.stop="downloadStudentIdCard(student)">
                  🪪
                </button>
                <button class="btn-icon" title="Edit" @click.stop="editStudent(student)">
                  ✏️
                </button>
                <button
                  v-if="student.enrollments_count === 0"
                  class="btn-icon text-danger"
                  title="Delete"
                  @click="confirmDelete(student)"
                >
                  🗑️
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="pagination" class="pagination">
        <button :disabled="!pagination.prev_page_url" @click="goToPage(pagination.current_page - 1)" class="btn btn-outline btn-sm">← Prev</button>
        <span class="page-info">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
        <button :disabled="!pagination.next_page_url" @click="goToPage(pagination.current_page + 1)" class="btn btn-outline btn-sm">Next →</button>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal-card confirm-modal">
        <h3>🗑️ Delete Student?</h3>
        <p>Are you sure you want to delete <strong>{{ deleteTarget.first_name }} {{ deleteTarget.last_name }}</strong> ({{ deleteTarget.student_id }})?</p>
        <p class="text-warning">This action cannot be undone.</p>
        <div class="modal-actions">
          <button class="btn btn-outline" @click="deleteTarget = null">Cancel</button>
          <button class="btn btn-danger" :disabled="deleting" @click="deleteStudent">
            {{ deleting ? 'Deleting...' : 'Yes, Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import studentService from '@/services/student.service'
import { getPhotoUrl, getPersonInitials, downloadBlobFile } from '@/utils/photo.utils'

const router = useRouter()

const students = ref([])
const loading = ref(false)
const error = ref(null)
const deleting = ref(false)
const deleteTarget = ref(null)
const pagination = ref(null)

const filters = ref({
  search: '',
  enrollment_status: '',
})

let searchTimeout = null
const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => fetchStudents(), 400)
}

// Stats computed from pagination meta
const stats = computed(() => {
  if (!pagination.value) return null
  // We'll compute from the full data set; for now use pagination total
  return {
    total: pagination.value.total || 0,
    enrolled: students.value.filter(s => s.enrollments_count > 0).length,
    nonEnrolled: students.value.filter(s => s.enrollments_count === 0).length,
  }
})

const fetchStudents = async (url = null) => {
  loading.value = true
  error.value = null
  try {
    const params = { ...filters.value }
    // Remove empty filters
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })

    let res
    if (url) {
      // Handle pagination URL with existing params
      const baseUrl = url.split('?')[0]
      const queryParams = new URLSearchParams(url.split('?')[1] || '')
      Object.entries(params).forEach(([k, v]) => queryParams.set(k, v))
      res = await studentService.list(Object.fromEntries(queryParams))
    } else {
      res = await studentService.list(params)
    }

    const data = res.data
    students.value = data.data || []
    pagination.value = {
      current_page: data.current_page,
      last_page: data.last_page,
      per_page: data.per_page,
      total: data.total,
      prev_page_url: data.prev_page_url,
      next_page_url: data.next_page_url,
    }
  } catch (e) {
    console.error('Failed to fetch students:', e)
    error.value = e.response?.data?.message || 'Failed to load students'
  } finally {
    loading.value = false
  }
}

const goToPage = (page) => {
  if (page < 1 || page > (pagination.value?.last_page || 1)) return
  fetchStudents(`/api/v1/students?page=${page}`)
}

const getInitials = (student) => getPersonInitials(student)

const downloadStudentIdCard = async (student) => {
  try {
    const res = await studentService.downloadIdCard(student.id)
    downloadBlobFile(res, `student-id-${student.student_id || student.id}.pdf`)
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to download ID card')
  }
}

const viewStudent = (student) => {
  router.push(`/dashboard/students/${student.id}`)
}

const editStudent = (student) => {
  router.push(`/dashboard/students/${student.id}/edit`)
}

const confirmDelete = (student) => {
  deleteTarget.value = student
}

const deleteStudent = async () => {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await studentService.delete(deleteTarget.value.id)
    students.value = students.value.filter(s => s.id !== deleteTarget.value.id)
    deleteTarget.value = null
  } catch (e) {
    console.error('Delete failed:', e)
    error.value = e.response?.data?.message || 'Failed to delete student'
  } finally {
    deleting.value = false
  }
}

onMounted(() => fetchStudents())
</script>

<style scoped>
.page-container {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}

.header-left h1 {
  margin: 0 0 4px;
  font-size: 1.75rem;
}

.header-actions {
  display: flex;
  gap: 8px;
}

.filter-bar {
  display: flex;
  gap: 16px;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.search-box {
  position: relative;
  flex: 1;
  min-width: 250px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 14px;
}

.search-box .form-input {
  padding-left: 36px;
  width: 100%;
}

.filter-tabs {
  display: flex;
  gap: 4px;
  background: var(--bg-accent);
  border-radius: 8px;
  padding: 3px;
}

.filter-tab {
  padding: 8px 16px;
  border: none;
  background: transparent;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  color: var(--text-muted);
  transition: all 0.2s;
}

.filter-tab.active {
  background: var(--bg-card);
  color: var(--text-dark);
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.filter-tab:hover:not(.active) {
  color: var(--text-secondary);
}

.stats-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 20px;
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-card.blue { border-left: 4px solid #3b82f6; }
.stat-card.green { border-left: 4px solid #22c55e; }
.stat-card.orange { border-left: 4px solid #f59e0b; }

.stat-val {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-dark);
}

.stat-lbl {
  font-size: 13px;
  color: var(--text-muted);
  font-weight: 500;
}

.table-container {
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th {
  text-align: left;
  padding: 12px 16px;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: var(--bg-surface-muted);
  border-bottom: 1px solid var(--border-color);
}

.data-table td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--border-light);
  font-size: 14px;
  color: var(--text-secondary);
}

.data-table tr:hover td {
  background: var(--bg-surface-muted);
}

.clickable-row {
  cursor: pointer;
  transition: background 0.15s;
}

.clickable-row:hover td {
  background: #eef2ff !important;
}

.name-link {
  color: #4f46e5;
  transition: color 0.2s;
}

.clickable-row:hover .name-link {
  color: #4338ca;
  text-decoration: underline;
}

.clickable-badge {
  cursor: pointer;
  transition: all 0.2s;
}

.clickable-row:hover .clickable-badge {
  background: #c7d2fe;
}

.student-name {
  display: flex;
  align-items: center;
  gap: 10px;
}

.avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  overflow: hidden;
  flex-shrink: 0;
}
.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  font-weight: 700;
}

.badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.badge-id {
  background: #eef2ff;
  color: #4f46e5;
  font-family: monospace;
}

.badge-active { background: #dcfce7; color: #16a34a; }
.badge-inactive { background: #fef3c7; color: #d97706; }
.badge-graduated { background: #dbeafe; color: #2563eb; }
.badge-suspended { background: #fee2e2; color: #dc2626; }

.badge-enrolled {
  background: #dcfce7;
  color: #16a34a;
}

.badge-non-enrolled {
  background: #fef3c7;
  color: #d97706;
}

.action-btns {
  display: flex;
  gap: 4px;
}

.btn-icon {
  background: none;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 6px 8px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}

.btn-icon:hover {
  background: var(--bg-accent);
  border-color: #cbd5e1;
}

.text-danger { color: #dc2626; }

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 16px;
  border-top: 1px solid #e2e8f0;
}

.page-info {
  font-size: 13px;
  color: var(--text-muted);
}

.loading-state,
.error-state,
.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: var(--shadow-sm);
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 16px;
}

@keyframes spin { to { transform: rotate(360deg); } }

.empty-icon {
  font-size: 48px;
  margin-bottom: 12px;
}

.error-msg {
  color: #dc2626;
  margin-bottom: 12px;
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 28px;
  max-width: 420px;
  width: 90%;
  box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}

.confirm-modal h3 {
  margin: 0 0 12px;
}

.confirm-modal p {
  color: var(--text-muted);
  margin: 0 0 8px;
  line-height: 1.5;
}

.text-warning {
  color: #d97706;
  font-weight: 500;
}

.modal-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 20px;
}

.btn {
  padding: 10px 20px;
  border-radius: 8px;
  border: none;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
}

.btn-primary:hover { opacity: 0.9; }

.btn-outline {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
}

.btn-outline:hover { background: var(--bg-accent); }

.btn-danger {
  background: #dc2626;
  color: #fff;
}

.btn-danger:hover { background: #b91c1c; }

.btn-sm {
  padding: 6px 14px;
  font-size: 13px;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .page-header { flex-direction: column; gap: 12px; }
  .filter-bar { flex-direction: column; }
  .stats-row { grid-template-columns: 1fr; }
  .data-table { font-size: 13px; }
}
</style>

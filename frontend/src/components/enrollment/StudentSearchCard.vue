<template>
  <div class="student-search-card">
    <h3 class="step-title">
      <span class="step-badge">1</span>
      Find Student
    </h3>

    <!-- Phone Search -->
    <div class="search-box">
      <div class="input-group">
        <span class="input-icon">📱</span>
        <input
          v-model="searchQuery"
          type="text"
          class="form-input search-input"
          placeholder="Search by phone, name, or student ID..."
          @input="debouncedSearch"
          :disabled="selectedStudent !== null"
        />
        <button v-if="searchQuery && !selectedStudent" class="clear-btn" @click="clearSearch">✕</button>
      </div>
      <span v-if="searching" class="search-hint">Searching...</span>
    </div>

    <!-- Search Results -->
    <div v-if="searchResults.length > 0 && !selectedStudent" class="search-results">
      <div
        v-for="student in searchResults"
        :key="student.id"
        class="result-card"
        :class="{ 'has-sibling': student._sibling_info?.has_sibling }"
        @click="selectStudent(student)"
      >
        <div class="result-left">
          <div class="student-avatar">
            {{ (student.first_name || '?')[0] }}{{ (student.last_name || '')[0] }}
          </div>
          <div class="student-info">
            <strong>{{ student.first_name }} {{ student.last_name }}</strong>
            <span class="text-muted">{{ student.student_id }}</span>
            <span class="text-muted">📱 {{ student.phone || 'N/A' }}</span>
          </div>
        </div>
        <div class="result-right">
          <span v-if="student.current_class" class="badge badge-primary">{{ student.current_class.name }}</span>
          <span v-if="student._sibling_info?.has_sibling" class="badge badge-success sibling-tag">
            👨‍👩‍👧 Sibling
          </span>
          <span class="select-hint">Select →</span>
        </div>
      </div>
    </div>

    <!-- No Results -->
    <div v-if="searchQuery.length >= 2 && searchResults.length === 0 && !searching && !selectedStudent" class="no-results">
      <div class="empty-icon">🔍</div>
      <p>No student found with "{{ searchQuery }}"</p>
      <button class="btn btn-primary btn-sm" @click="showQuickAdd = true">
        + Add New Student
      </button>
    </div>

    <!-- Selected Student -->
    <div v-if="selectedStudent" class="selected-student">
      <div class="student-profile">
        <div class="student-avatar large">
          {{ (selectedStudent.first_name || '?')[0] }}{{ (selectedStudent.last_name || '')[0] }}
        </div>
        <div class="profile-details">
          <h4>{{ selectedStudent.first_name }} {{ selectedStudent.last_name }}</h4>
          <div class="detail-row">
            <span>🆔 {{ selectedStudent.student_id }}</span>
            <span>📱 {{ selectedStudent.phone }}</span>
            <span v-if="selectedStudent.email">✉️ {{ selectedStudent.email }}</span>
          </div>
          <div class="detail-row">
            <span v-if="selectedStudent.current_class">🏫 Class {{ selectedStudent.current_class.name }}</span>
            <span v-if="selectedStudent.current_section">Section {{ selectedStudent.current_section.name }}</span>
            <span v-if="selectedStudent.gender">⚥ {{ selectedStudent.gender }}</span>
          </div>
          <!-- Guardian Info -->
          <div v-if="selectedStudent.guardian" class="guardian-info">
            <small class="text-muted">👨‍👩‍👧 Guardian: {{ selectedStudent.guardian.father_name || selectedStudent.guardian.guardian_name || 'N/A' }} | 📱 {{ selectedStudent.guardian.guardian_phone || selectedStudent.guardian.father_phone || 'N/A' }}</small>
          </div>
          <!-- Sibling Alert -->
          <div v-if="selectedStudent._sibling_info?.has_sibling" class="sibling-alert">
            🎉 <strong>Sibling Discount Available!</strong>
            {{ selectedStudent._sibling_info.sibling_names?.join(', ') || 'Another sibling' }} is already enrolled — 10% discount applicable.
          </div>
        </div>
        <button class="btn btn-sm btn-outline-danger" @click="clearSelection">Change</button>
      </div>
    </div>

    <!-- Quick Add Toggle -->
    <button
      v-if="!selectedStudent && !showQuickAdd"
      class="btn btn-link btn-sm mt-2"
      @click="showQuickAdd = true"
    >
      + New Student? Quick Add
    </button>

    <!-- Quick Add Form -->
    <div v-if="showQuickAdd && !selectedStudent" class="quick-add-form">
      <h4>⚡ Quick Add Student</h4>
      <div class="form-row">
        <div class="form-group col-6">
          <label class="form-label">First Name <span class="text-danger">*</span></label>
          <input v-model="quickForm.first_name" class="form-input" required placeholder="First name" />
        </div>
        <div class="form-group col-6">
          <label class="form-label">Last Name</label>
          <input v-model="quickForm.last_name" class="form-input" placeholder="Last name" />
        </div>
      </div>
      <div class="form-row">
        <div class="form-group col-6">
          <label class="form-label">Phone <span class="text-danger">*</span></label>
          <input v-model="quickForm.phone" class="form-input" required placeholder="01XXXXXXXXX" />
        </div>
        <div class="form-group col-6">
          <label class="form-label">School / Institution</label>
          <input v-model="quickForm.previous_school" class="form-input" placeholder="School name" />
        </div>
      </div>
      <div class="form-row">
        <div class="form-group col-6">
          <label class="form-label">Class</label>
          <select v-model="quickForm.current_class_id" class="form-select">
            <option value="">Select Class</option>
            <option v-for="c in classList" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="form-group col-6">
          <label class="form-label">Gender</label>
          <select v-model="quickForm.gender" class="form-select">
            <option value="">Select</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group col-6">
          <label class="form-label">Guardian Phone</label>
          <input v-model="quickForm.guardian_phone" class="form-input" placeholder="01XXXXXXXXX" />
        </div>
        <div class="form-group col-6">
          <label class="form-label">Guardian Name (Optional)</label>
          <input v-model="quickForm.guardian_name" class="form-input" placeholder="Father/Mother name" />
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary btn-sm" @click="quickAddStudent" :disabled="quickAdding">
          {{ quickAdding ? 'Adding...' : 'Add & Select' }}
        </button>
        <button class="btn btn-secondary btn-sm" @click="showQuickAdd = false">Cancel</button>
      </div>
    </div>

    <div v-if="errorMsg" class="alert alert-danger mt-2">{{ errorMsg }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import enrollmentService from '@/services/enrollment.service'
import studentService from '@/services/student.service'

const emit = defineEmits(['student-selected'])

const searchQuery = ref('')
const searchResults = ref([])
const searching = ref(false)
const selectedStudent = ref(null)
const showQuickAdd = ref(false)
const errorMsg = ref('')
const classList = ref([])
const quickAdding = ref(false)

const quickForm = ref({
  first_name: '',
  last_name: '',
  phone: '',
  previous_school: '',
  current_class_id: '',
  gender: '',
  guardian_phone: '',
  guardian_name: '',
})

// Debounced search
let searchTimer = null
const debouncedSearch = () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(doSearch, 350)
}

const doSearch = async () => {
  if (searchQuery.value.length < 2) {
    searchResults.value = []
    return
  }
  searching.value = true
  try {
    const res = await enrollmentService.searchStudent(searchQuery.value)
    const students = res.data?.data || res.data || []
    // Check sibling discount for each
    for (const s of students) {
      try {
        const sibRes = await enrollmentService.checkSiblingDiscount(s.id)
        s._sibling_info = sibRes.data?.data || sibRes.data || { has_sibling: false }
      } catch { s._sibling_info = { has_sibling: false } }
    }
    searchResults.value = students
  } catch (e) {
    console.error(e)
    errorMsg.value = 'Search failed. Try again.'
  } finally {
    searching.value = false
  }
}

const selectStudent = (student) => {
  selectedStudent.value = student
  showQuickAdd.value = false
  searchResults.value = []
  emit('student-selected', student)
}

const clearSelection = () => {
  selectedStudent.value = null
  searchQuery.value = ''
  searchResults.value = []
  showQuickAdd.value = false
  emit('student-selected', null)
}

const clearSearch = () => {
  searchQuery.value = ''
  searchResults.value = []
}

const quickAddStudent = async () => {
  if (!quickForm.value.first_name || !quickForm.value.phone) {
    errorMsg.value = 'Name and phone are required'
    return
  }
  quickAdding.value = true
  errorMsg.value = ''
  try {
    const res = await studentService.createStudent(quickForm.value)
    const newStudent = res.data?.data || res.data
    selectedStudent.value = newStudent
    showQuickAdd.value = false
    emit('student-selected', newStudent)
  } catch (e) {
    console.error(e)
    errorMsg.value = e.response?.data?.message || 'Failed to add student'
  } finally {
    quickAdding.value = false
  }
}

onMounted(async () => {
  try {
    const res = await studentService.getClasses?.() || { data: [] }
    classList.value = res.data?.data || res.data || []
  } catch { /* ignore */ }
})
</script>

<style scoped>
.student-search-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.step-title {
  font-size: 1.1rem;
  margin: 0 0 1rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.step-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #4a90d9;
  color: white;
  font-size: 0.85rem;
  font-weight: 700;
}

.search-box { margin-bottom: 1rem; }

.input-group {
  display: flex;
  align-items: center;
  border: 2px solid #e0e0e0;
  border-radius: 10px;
  padding: 0.5rem 1rem;
  transition: border-color 0.2s;
}

.input-group:focus-within { border-color: #4a90d9; }

.input-icon { font-size: 1.2rem; margin-right: 0.5rem; }

.search-input {
  flex: 1;
  border: none !important;
  outline: none !important;
  font-size: 1rem;
  padding: 0.4rem 0;
  box-shadow: none !important;
}

.clear-btn {
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  font-size: 1.1rem;
}

.search-hint { font-size: 0.8rem; color: var(--text-muted); display: block; margin-top: 0.25rem; }

.search-results { max-height: 350px; overflow-y: auto; }

.result-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  border: 1px solid #f0f0f0;
  border-radius: 10px;
  margin-bottom: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;
}

.result-card:hover { background: #f5f9ff; border-color: #4a90d9; }

.result-card.has-sibling { border-left: 3px solid #2ecc71; }

.result-left { display: flex; align-items: center; gap: 0.75rem; }

.student-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #4a90d9;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.9rem;
}

.student-avatar.large { width: 56px; height: 56px; font-size: 1.3rem; }

.student-info strong { display: block; }
.student-info span { display: block; font-size: 0.8rem; }

.result-right { display: flex; align-items: center; gap: 0.5rem; }

.sibling-tag { background: #2ecc71 !important; }

.select-hint { color: #4a90d9; font-size: 0.8rem; font-weight: 600; display: none; }
.result-card:hover .select-hint { display: inline; }

.no-results { text-align: center; padding: 2rem; }
.empty-icon { font-size: 3rem; margin-bottom: 0.5rem; }

.selected-student { margin-top: 1rem; }

.student-profile {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1rem;
  background: var(--bg-accent);
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.profile-details { flex: 1; }

.profile-details h4 { margin: 0 0 0.4rem 0; }

.detail-row { display: flex; flex-wrap: wrap; gap: 1rem; font-size: 0.85rem; color: #555; margin-bottom: 0.3rem; }

.guardian-info { margin-top: 0.5rem; }

.sibling-alert {
  margin-top: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: #eafaf1;
  border-radius: 8px;
  color: #27ae60;
  font-size: 0.85rem;
}

.quick-add-form {
  margin-top: 1rem;
  padding: 1.25rem;
  background: var(--bg-accent);
  border-radius: 12px;
  border: 1px dashed #ccc;
}

.quick-add-form h4 { margin: 0 0 0.75rem 0; }

.form-row { display: flex; gap: 1rem; margin-bottom: 0.75rem; }
.form-group { flex: 1; }
.form-label { display: block; margin-bottom: 0.25rem; font-size: 0.85rem; font-weight: 600; }
.form-input, .form-select { width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.9rem; }
.form-actions { display: flex; gap: 0.5rem; margin-top: 0.75rem; }

.btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; }
.btn-link { color: #4a90d9; text-decoration: underline; }
.btn-outline-danger { color: #e74c3c; border: 1px solid #e74c3c; background: none; border-radius: 6px; padding: 0.35rem 0.75rem; cursor: pointer; }
.btn-outline-danger:hover { background: #e74c3c; color: white; }

.alert-danger { background: #fdeaea; color: #e74c3c; padding: 0.75rem; border-radius: 8px; font-size: 0.85rem; }
.mt-2 { margin-top: 0.5rem; }
.text-muted { color: #888; }
.text-danger { color: #e74c3c; }
.badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
.badge-primary { background: #4a90d9; color: white; }
.badge-success { background: #2ecc71; color: white; }
.col-6 { flex: 0 0 calc(50% - 0.5rem); }
</style>

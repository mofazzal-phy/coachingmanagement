<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left"><h1>Classes</h1><span class="badge-count">{{ items.length }} total</span></div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadItems" :disabled="loading">🔄 Refresh</button>
        <button class="btn btn-primary" @click="openCreateDialog">+ Add Class</button>
      </div>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading classes...</p></div>
    <div v-else-if="error" class="error-state"><p>⚠️ {{ error }}</p><button class="btn btn-outline" @click="loadItems">Try Again</button></div>
    <div v-else-if="items.length === 0" class="empty-state">
      <div class="empty-icon">🏫</div><h3>No Classes Found</h3><p>Create your first class</p>
      <button class="btn btn-primary" @click="openCreateDialog">+ Add Class</button>
    </div>
    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr><th>Name</th><th>Code</th><th>Numeric Value</th><!-- <th>Type</th> --><th>Subjects</th><th>Description</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td><strong>{{ item.name }}</strong></td>
            <td>{{ item.code || '—' }}</td>
            <td>{{ item.numeric_value || '—' }}</td>
            <!-- <td><span class="type-badge" :class="'type-' + (item.type || 'common')">{{ item.type || 'common' }}</span></td> -->
            <td>{{ (item.subjects && item.subjects.length) || 0 }}</td>
            <td>{{ item.description || '—' }}</td>
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

    <!-- Dialog -->
    <div class="modal-overlay" v-if="showDialog" @click.self="closeDialog">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3>{{ editingItem ? 'Edit Class' : 'Create Class' }}</h3>
          <button class="modal-close" @click="closeDialog">✕</button>
        </div>
        <div class="modal-body">
          <div v-if="dialogError" class="alert alert-danger">{{ dialogError }}</div>
          <div class="form-group">
            <label>Class Name <span class="required">*</span></label>
            <input v-model="form.name" class="form-control" placeholder="e.g., Class 6" />
          </div>
          <div class="form-group">
            <label>Code <span class="required">*</span></label>
            <input v-model="form.code" class="form-control" placeholder="e.g., C06" />
          </div>
          <div class="form-group">
            <label>Numeric Value <span class="required">*</span></label>
            <input v-model="form.numeric_value" type="number" min="1" max="12" class="form-control" placeholder="e.g., 6" />
          </div>
          <!-- <div class="form-group">
            <label>Type</label>
            <select v-model="form.type" class="form-control">
              <option value="common">Common</option>
              <option value="boys">Boys</option>
              <option value="girls">Girls</option>
            </select>
          </div> -->
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" class="form-control" placeholder="Optional description"></textarea>
          </div>

          <!-- Subjects Selection -->
          <div class="form-group subjects-section">
            <label>Assign Subjects</label>
            <div v-if="subjectsLoading" class="subjects-loading">Loading subjects...</div>
            <div v-else-if="allSubjects.length === 0" class="subjects-empty">
              No subjects found. <router-link to="/dashboard/academic/subjects">Create subjects first</router-link>
            </div>
            <div v-else class="subjects-grid">
              <div v-for="subject in allSubjects" :key="subject.id" class="subject-checkbox-item">
                <label class="checkbox-label">
                  <input 
                    type="checkbox" 
                    :value="subject.id" 
                    v-model="selectedSubjectIds"
                    @change="onSubjectChange(subject.id)"
                  />
                  <span class="subject-name">{{ subject.name }}</span>
                  <span class="subject-code">{{ subject.code }}</span>
                </label>
                <div v-if="selectedSubjectIds.includes(subject.id)" class="subject-marks">
                  <div class="marks-row">
                    <div class="marks-field">
                      <label>Total Marks</label>
                      <input 
                        v-model="subjectMarks[subject.id].total_marks" 
                        type="number" min="1" class="form-control form-control-sm" 
                        placeholder="100"
                      />
                    </div>
                    <div class="marks-field">
                      <label>Pass Marks</label>
                      <input 
                        v-model="subjectMarks[subject.id].pass_marks" 
                        type="number" min="1" class="form-control form-control-sm" 
                        placeholder="33"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="closeDialog">Cancel</button>
          <button class="btn btn-primary" @click="saveItem" :disabled="!form.name || !form.code || !form.numeric_value || dialogLoading">
            {{ dialogLoading ? 'Saving...' : (editingItem ? 'Update' : 'Create') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete -->
    <div class="modal-overlay" v-if="showDeleteDialog" @click.self="showDeleteDialog = false">
      <div class="modal-dialog modal-sm">
        <div class="modal-header"><h3>Confirm Delete</h3><button class="modal-close" @click="showDeleteDialog = false">✕</button></div>
        <div class="modal-body"><p>Delete class <strong>{{ selectedItem?.name }}</strong>?</p><p class="text-danger">This cannot be undone.</p></div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showDeleteDialog = false">Cancel</button>
          <button class="btn btn-danger" @click="deleteItem" :disabled="deleteLoading">{{ deleteLoading ? 'Deleting...' : 'Delete' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import academicService from '@/services/academic.service'

const items = ref([])
const loading = ref(false)
const error = ref(null)

const showDialog = ref(false)
const editingItem = ref(null)
const dialogLoading = ref(false)
const dialogError = ref(null)
const form = ref({ name: '', code: '', numeric_value: '', type: 'common', description: '' })

const showDeleteDialog = ref(false)
const selectedItem = ref(null)
const deleteLoading = ref(false)

// Subject selection state
const allSubjects = ref([])
const subjectsLoading = ref(false)
const selectedSubjectIds = ref([])
const subjectMarks = reactive({})

const loadItems = async () => {
  loading.value = true; error.value = null
  try {
    const res = await academicService.classes.list()
    items.value = res.data.data || []
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load classes'
  } finally { loading.value = false }
}

const loadAllSubjects = async () => {
  subjectsLoading.value = true
  try {
    const res = await academicService.subjects.listAll()
    allSubjects.value = res.data.data || []
  } catch {
    allSubjects.value = []
  } finally { subjectsLoading.value = false }
}

const initSubjectMarks = () => {
  selectedSubjectIds.value = []
  // Clear reactive object
  Object.keys(subjectMarks).forEach(key => delete subjectMarks[key])
}

const onSubjectChange = (subjectId) => {
  if (selectedSubjectIds.value.includes(subjectId)) {
    // Subject was just checked - initialize marks
    subjectMarks[subjectId] = { total_marks: 100, pass_marks: 33 }
  } else {
    // Subject was unchecked - remove marks
    delete subjectMarks[subjectId]
  }
}

const openCreateDialog = () => {
  editingItem.value = null
  form.value = { name: '', code: '', numeric_value: '', type: 'common', description: '' }
  initSubjectMarks()
  dialogError.value = null; showDialog.value = true
  loadAllSubjects()
}

const openEditDialog = (item) => {
  editingItem.value = item
  form.value = { name: item.name, code: item.code || '', numeric_value: item.numeric_value || '', type: item.type || 'common', description: item.description || '' }
  dialogError.value = null; showDialog.value = true
  
  // Load subjects and pre-select existing ones
  loadAllSubjects().then(() => {
    initSubjectMarks()
    if (item.subjects && item.subjects.length > 0) {
      item.subjects.forEach(subject => {
        selectedSubjectIds.value.push(subject.id)
        subjectMarks[subject.id] = {
          total_marks: subject.pivot?.total_marks || 100,
          pass_marks: subject.pivot?.pass_marks || 33,
        }
      })
    }
  })
}

const closeDialog = () => { showDialog.value = false; editingItem.value = null }

const saveItem = async () => {
  dialogLoading.value = true; dialogError.value = null
  try {
    let classId
    
    if (editingItem.value) {
      // Update class info
      await academicService.classes.update(editingItem.value.id, form.value)
      classId = editingItem.value.id
    } else {
      // Create class first
      const res = await academicService.classes.create(form.value)
      classId = res.data.data?.id || res.data.id
    }
    
    // Assign subjects if any selected
    if (selectedSubjectIds.value.length > 0) {
      const subjectsData = selectedSubjectIds.value.map(id => ({
        id,
        total_marks: subjectMarks[id]?.total_marks || 100,
        pass_marks: subjectMarks[id]?.pass_marks || 33,
      }))
      await academicService.classes.assignSubjects(classId, { subjects: subjectsData })
    } else if (editingItem.value) {
      // If editing and no subjects selected, clear existing subjects
      await academicService.classes.assignSubjects(classId, { subjects: [] })
    }
    
    closeDialog(); loadItems()
  } catch (err) {
    dialogError.value = err.response?.data?.message || 'Failed to save'
  } finally { dialogLoading.value = false }
}

const confirmDelete = (item) => { selectedItem.value = item; showDeleteDialog.value = true }
const deleteItem = async () => {
  deleteLoading.value = true
  try {
    await academicService.classes.delete(selectedItem.value.id)
    showDeleteDialog.value = false; loadItems()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to delete'
    showDeleteDialog.value = false
  } finally { deleteLoading.value = false }
}

onMounted(() => { loadItems() })
</script>

<style scoped>
.page-container { max-width: 1000px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem; }
.header-left { display: flex; align-items: center; gap: 0.75rem; }
.header-left h1 { font-size: 1.5rem; color: var(--text-primary); margin: 0; }
.badge-count { background: #e8eaf6; color: #4f46e5; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.header-actions { display: flex; gap: 0.5rem; }
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
.error-state .btn { margin-top: 0.75rem; }
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
.action-buttons { display: flex; gap: 0.25rem; }
.btn-icon { width: 32px; height: 32px; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.btn-icon:hover { background: #f3f4f6; }
.btn-icon.danger:hover { background: #fef2f2; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(2px); }
.modal-dialog { background: var(--bg-card); border-radius: 16px; width: 95%; max-width: 600px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.modal-body { padding: 1.5rem; max-height: 70vh; overflow-y: auto; }
.modal-sm { max-width: 400px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); }
.modal-header h3 { margin: 0; font-size: 1.1rem; color: var(--text-primary); }
.modal-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted); padding: 0.25rem; }
.modal-close:hover { color: var(--text-secondary); }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem; }
.required { color: #ef4444; }
.form-control { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: var(--bg-card); box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
.alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; }
.alert-danger { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.text-danger { color: #dc2626; font-size: 0.85rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); }

/* Subjects Selection */
.subjects-section {
  border-top: 1px solid var(--border-color);
  padding-top: 1rem;
  margin-top: 0.5rem;
}

.subjects-section > label {
  font-size: 0.9rem;
  color: var(--text-primary);
  margin-bottom: 0.75rem;
}

.subjects-loading,
.subjects-empty {
  padding: 1rem;
  text-align: center;
  color: var(--text-muted);
  font-size: 0.85rem;
  background: var(--bg-accent);
  border-radius: 8px;
}

.subjects-empty a {
  color: #4f46e5;
  text-decoration: none;
  font-weight: 600;
}

.subjects-empty a:hover {
  text-decoration: underline;
}

.subjects-grid {
  max-height: 300px;
  overflow-y: auto;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 0.5rem;
}

.subject-checkbox-item {
  padding: 0.5rem;
  border-radius: 6px;
  transition: background 0.15s;
}

.subject-checkbox-item:hover {
  background: var(--bg-accent);
}

.subject-checkbox-item + .subject-checkbox-item {
  border-top: 1px solid #f3f4f6;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  padding: 0.25rem 0;
}

.checkbox-label input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: #4f46e5;
  cursor: pointer;
}

.subject-name {
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.subject-code {
  font-size: 0.75rem;
  color: var(--text-muted);
  background: #f3f4f6;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
}

.subject-marks {
  padding: 0.5rem 0 0.25rem 1.5rem;
}

.marks-row {
  display: flex;
  gap: 0.75rem;
}

.marks-field {
  flex: 1;
}

.marks-field label {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin-bottom: 0.25rem;
}

.form-control-sm {
  padding: 0.4rem 0.5rem;
  font-size: 0.8rem;
}

.type-badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: capitalize;
}
.type-common { background: #e8eaf6; color: #4f46e5; }
.type-boys { background: #dbeafe; color: #1d4ed8; }
.type-girls { background: #fce7f3; color: #db2777; }
</style>

<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1>✏️ Edit Student</h1>
        <p class="text-muted" v-if="studentData">Editing: {{ studentData.first_name }} {{ studentData.last_name }} ({{ studentData.student_id }})</p>
      </div>
      <button class="btn btn-outline" @click="$router.push('/dashboard/students')">
        ← Back to List
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading student data...</p>
    </div>

    <!-- Error -->
    <div v-else-if="loadError" class="error-state">
      <p class="error-msg">{{ loadError }}</p>
      <button class="btn btn-outline" @click="loadStudent">Retry</button>
    </div>

    <!-- Form -->
    <div v-else class="form-card">
      <form @submit.prevent="submitForm">
        <!-- Personal Information -->
        <div class="form-section">
          <h3>👤 Personal Information</h3>
          <div class="form-row">
            <div class="form-group">
              <label>First Name <span class="required">*</span></label>
              <input v-model="form.first_name" class="form-input" required />
            </div>
            <div class="form-group">
              <label>Last Name</label>
              <input v-model="form.last_name" class="form-input" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Email</label>
              <input v-model="form.email" type="email" class="form-input" />
            </div>
            <div class="form-group">
              <label>Phone</label>
              <input v-model="form.phone" class="form-input" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Gender</label>
              <select v-model="form.gender" class="form-select">
                <option value="">Select</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="form-group">
              <label>Date of Birth</label>
              <input v-model="form.date_of_birth" type="date" class="form-input" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Blood Group</label>
              <select v-model="form.blood_group" class="form-select">
                <option value="">Select</option>
                <option value="A+">A+</option><option value="A-">A-</option>
                <option value="B+">B+</option><option value="B-">B-</option>
                <option value="AB+">AB+</option><option value="AB-">AB-</option>
                <option value="O+">O+</option><option value="O-">O-</option>
              </select>
            </div>
            <div class="form-group">
              <label>Religion</label>
              <input v-model="form.religion" class="form-input" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Status</label>
              <select v-model="form.status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="graduated">Graduated</option>
                <option value="suspended">Suspended</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Academic Information -->
        <div class="form-section">
          <h3>📚 Academic Information</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Class</label>
              <select v-model="form.current_class_id" class="form-select">
                <option value="">Select Class</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Roll No</label>
              <input v-model="form.roll_no" class="form-input" />
            </div>
          </div>
        </div>

        <!-- Address -->
        <div class="form-section">
          <h3>📍 Address</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Present Address</label>
              <textarea v-model="form.present_address" class="form-textarea" rows="2"></textarea>
            </div>
            <div class="form-group">
              <label>Permanent Address</label>
              <textarea v-model="form.permanent_address" class="form-textarea" rows="2"></textarea>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>City</label>
              <input v-model="form.city" class="form-input" />
            </div>
            <div class="form-group">
              <label>State</label>
              <input v-model="form.state" class="form-input" />
            </div>
          </div>
        </div>

        <!-- Guardian Information -->
        <div class="form-section">
          <h3>👨‍👩‍👧 Guardian Information</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Father's Name</label>
              <input v-model="form.father_name" class="form-input" />
            </div>
            <div class="form-group">
              <label>Father's Phone</label>
              <input v-model="form.father_phone" class="form-input" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Mother's Name</label>
              <input v-model="form.mother_name" class="form-input" />
            </div>
            <div class="form-group">
              <label>Mother's Phone</label>
              <input v-model="form.mother_phone" class="form-input" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Emergency Contact</label>
              <input v-model="form.emergency_contact" class="form-input" />
            </div>
            <div class="form-group">
              <label>Emergency Phone</label>
              <input v-model="form.emergency_phone" class="form-input" />
            </div>
          </div>
        </div>

        <!-- Previous School -->
        <div class="form-section">
          <h3>🏫 Previous School</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Previous School</label>
              <input v-model="form.previous_school" class="form-input" />
            </div>
            <div class="form-group">
              <label>Previous Class</label>
              <input v-model="form.previous_class" class="form-input" />
            </div>
          </div>
        </div>

        <!-- Remarks -->
        <div class="form-section">
          <h3>📝 Remarks</h3>
          <div class="form-group">
            <textarea v-model="form.remarks" class="form-textarea" rows="3"></textarea>
          </div>
        </div>

        <!-- Submit -->
        <div class="form-actions">
          <button type="button" class="btn btn-outline" @click="$router.push('/dashboard/students')">Cancel</button>
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ submitting ? '⏳ Saving...' : '💾 Save Changes' }}
          </button>
        </div>

        <!-- Error -->
        <div v-if="error" class="error-alert">
          <p>{{ error }}</p>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import studentService from '@/services/student.service'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const loadError = ref(null)
const submitting = ref(false)
const error = ref(null)
const classes = ref([])
const studentData = ref(null)

const form = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  gender: '',
  date_of_birth: '',
  blood_group: '',
  religion: '',
  present_address: '',
  permanent_address: '',
  city: '',
  state: '',
  current_class_id: '',
  roll_no: '',
  father_name: '',
  father_phone: '',
  mother_name: '',
  mother_phone: '',
  emergency_contact: '',
  emergency_phone: '',
  previous_school: '',
  previous_class: '',
  status: 'active',
  remarks: '',
})

const loadStudent = async () => {
  loading.value = true
  loadError.value = null
  try {
    const res = await studentService.get(route.params.id)
    const student = res.data?.data || res.data
    studentData.value = student

    // Map student data to form
    const fields = Object.keys(form.value)
    fields.forEach(f => {
      if (student[f] !== undefined && student[f] !== null) {
        form.value[f] = student[f]
      }
    })
  } catch (e) {
    console.error('Load failed:', e)
    loadError.value = e.response?.data?.message || 'Failed to load student'
  } finally {
    loading.value = false
  }
}

const loadClasses = async () => {
  try {
    const res = await studentService.getClasses()
    classes.value = res.data?.data || res.data || []
  } catch (e) {
    console.warn('Failed to load classes:', e)
  }
}

const submitForm = async () => {
  submitting.value = true
  error.value = null
  try {
    const payload = { ...form.value }
    Object.keys(payload).forEach(k => {
      if (payload[k] === '') payload[k] = null
    })

    await studentService.update(route.params.id, payload)
    router.push(`/dashboard/students/${route.params.id}`)
  } catch (e) {
    console.error('Update failed:', e)
    const errData = e.response?.data
    if (errData?.errors) {
      const messages = Object.values(errData.errors).flat()
      error.value = messages.join(', ')
    } else {
      error.value = errData?.message || 'Failed to update student'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadClasses()
  loadStudent()
})
</script>

<style scoped>
.page-container {
  padding: 24px;
  max-width: 900px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}

.page-header h1 {
  margin: 0 0 4px;
  font-size: 1.75rem;
}

.form-card {
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: var(--shadow-sm);
  padding: 32px;
}

.form-section {
  margin-bottom: 28px;
  padding-bottom: 28px;
  border-bottom: 1px solid var(--border-light);
}

.form-section:last-of-type {
  border-bottom: none;
  margin-bottom: 0;
  padding-bottom: 0;
}

.form-section h3 {
  margin: 0 0 16px;
  font-size: 16px;
  color: var(--text-dark);
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 14px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
}

.required { color: #dc2626; }

.form-input,
.form-select,
.form-textarea {
  padding: 10px 12px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 14px;
  color: var(--text-dark);
  background: var(--bg-card);
  transition: border-color 0.2s;
  width: 100%;
  box-sizing: border-box;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}

.form-textarea { resize: vertical; font-family: inherit; }

.form-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
}

.btn {
  padding: 10px 24px;
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

.btn-primary:hover:not(:disabled) { opacity: 0.9; }

.btn-outline {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
}

.btn-outline:hover { background: var(--bg-accent); }

.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.loading-state,
.error-state {
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

.error-msg { color: #dc2626; margin-bottom: 12px; }

.error-alert {
  margin-top: 16px;
  padding: 12px 16px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  color: #dc2626;
  font-size: 14px;
}

.error-alert p { margin: 0; }

@media (max-width: 640px) {
  .form-row { grid-template-columns: 1fr; }
  .page-header { flex-direction: column; gap: 12px; }
}
</style>

<template>
  <div class="student-create-page">
    <header class="page-top">
      <div>
        <h1>Create Student</h1>
        <p>Complete the form below to register a new student</p>
      </div>
      <button type="button" class="btn-back" @click="$router.push('/dashboard/students')">
        ← Back to List
      </button>
    </header>

    <div class="form-sheet">
      <form @submit.prevent="submitForm">
        <!-- Photo + Personal -->
        <section class="form-section">
          <div class="section-head">
            <span class="section-icon">👤</span>
            <h3>Photo &amp; Personal Information</h3>
          </div>
          <div class="photo-personal-layout">
            <div class="photo-col">
              <div class="photo-upload-card" @click="$refs.photoInput.click()">
                <img v-if="photoPreview" :src="photoPreview" alt="Student photo preview" class="photo-img" />
                <div v-else class="photo-empty">
                  <span class="photo-ring">📷</span>
                  <strong>Upload Photo</strong>
                  <span>JPG, PNG</span>
                </div>
              </div>
              <input
                ref="photoInput"
                type="file"
                accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                style="display: none"
                @change="onPhotoChange"
              />
              <div class="photo-actions">
                <button type="button" class="btn-upload" @click="$refs.photoInput.click()">Choose File</button>
                <button v-if="form.photo" type="button" class="btn-remove" @click="removePhoto">Remove</button>
              </div>
            </div>
            <div class="fields-col">
          <div class="form-row">
            <div class="form-group">
              <label>First Name <span class="required">*</span></label>
              <input v-model="form.first_name" class="form-input" required placeholder="Enter first name" />
            </div>
            <div class="form-group">
              <label>Last Name</label>
              <input v-model="form.last_name" class="form-input" placeholder="Enter last name" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Email</label>
              <input v-model="form.email" type="email" class="form-input" placeholder="student@example.com" />
            </div>
            <div class="form-group">
              <label>Phone</label>
              <input v-model="form.phone" class="form-input" placeholder="01XXXXXXXXX" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Gender</label>
              <select v-model="form.gender" class="form-select">
                <option value="">Select gender</option>
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
                <option value="">Select blood group</option>
                <option value="A+">A+</option><option value="A-">A-</option>
                <option value="B+">B+</option><option value="B-">B-</option>
                <option value="AB+">AB+</option><option value="AB-">AB-</option>
                <option value="O+">O+</option><option value="O-">O-</option>
              </select>
            </div>
            <div class="form-group">
              <label>Religion</label>
              <input v-model="form.religion" class="form-input" placeholder="Religion" />
            </div>
          </div>
            </div>
          </div>
        </section>

        <section class="form-section">
          <div class="section-head">
            <span class="section-icon">📚</span>
            <h3>Academic Information</h3>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Class</label>
              <select v-model="form.current_class_id" class="form-select" @change="onClassChange">
                <option value="">Select class</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Roll No</label>
              <input v-model="form.roll_no" class="form-input" placeholder="Roll number" />
            </div>
          </div>
        </section>

        <section class="form-section">
          <div class="section-head">
            <span class="section-icon">📍</span>
            <h3>Address</h3>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Present Address</label>
              <textarea v-model="form.present_address" class="form-textarea" rows="2" placeholder="Present address"></textarea>
            </div>
            <div class="form-group">
              <label>Permanent Address</label>
              <textarea v-model="form.permanent_address" class="form-textarea" rows="2" placeholder="Permanent address"></textarea>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>City</label>
              <input v-model="form.city" class="form-input" placeholder="City" />
            </div>
            <div class="form-group">
              <label>State</label>
              <input v-model="form.state" class="form-input" placeholder="State / Division" />
            </div>
          </div>
        </section>

        <section class="form-section">
          <div class="section-head">
            <span class="section-icon">👨‍👩‍👧</span>
            <h3>Guardian Information</h3>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label>Father's Name</label>
              <input v-model="form.father_name" class="form-input" placeholder="Father's name" />
            </div>
            <div class="form-group">
              <label>Father's Phone</label>
              <input v-model="form.father_phone" class="form-input" placeholder="Father's phone" />
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label>Mother's Name</label>
              <input v-model="form.mother_name" class="form-input" placeholder="Mother's name" />
            </div>
            <div class="form-group">
              <label>Mother's Phone</label>
              <input v-model="form.mother_phone" class="form-input" placeholder="Mother's phone" />
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label>Emergency Contact</label>
              <input v-model="form.emergency_contact" class="form-input" placeholder="Emergency contact name" />
            </div>
            <div class="form-group">
              <label>Emergency Phone</label>
              <input v-model="form.emergency_phone" class="form-input" placeholder="Emergency phone" />
            </div>
          </div>
        </section>

        <section class="form-section">
          <div class="section-head">
            <span class="section-icon">🏫</span>
            <h3>Previous School</h3>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Previous School</label>
              <input v-model="form.previous_school" class="form-input" placeholder="Previous school name" />
            </div>
            <div class="form-group">
              <label>Previous Class</label>
              <input v-model="form.previous_class" class="form-input" placeholder="Previous class" />
            </div>
          </div>
        </section>

        <section class="form-section">
          <div class="section-head">
            <span class="section-icon">🔐</span>
            <h3>Student Portal Login</h3>
          </div>
          <div class="info-banner">
            <span class="info-icon">💡</span>
            <span>Set username &amp; password so the student can sign in to the portal after creation.</span>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Username</label>
              <input v-model="form.username" class="form-input" placeholder="e.g. student name or ID" />
              <span class="field-hint">Leave empty to use student ID as username</span>
            </div>
            <div class="form-group">
              <label>Password</label>
              <input v-model="form.password" type="password" class="form-input" placeholder="Min 6 characters" />
              <span class="field-hint">Leave empty to skip portal account creation</span>
            </div>
          </div>
        </section>

        <section class="form-section">
          <div class="section-head">
            <span class="section-icon">📝</span>
            <h3>Remarks</h3>
          </div>
          <div class="form-group">
            <textarea v-model="form.remarks" class="form-textarea" rows="3" placeholder="Any remarks or notes..."></textarea>
          </div>
        </section>

        <div v-if="error" class="error-alert">
          <span class="error-icon">⚠️</span>
          <p>{{ error }}</p>
        </div>

        <footer class="sheet-footer">
          <button type="button" class="btn-cancel" @click="$router.push('/dashboard/students')">Cancel</button>
          <button type="submit" class="btn-submit" :disabled="submitting">
            {{ submitting ? 'Creating...' : 'Create Student' }}
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import studentService from '@/services/student.service'
import { buildStudentFormData } from '@/utils/photo.utils'

const router = useRouter()

const submitting = ref(false)
const error = ref(null)
const classes = ref([])
const photoPreview = ref(null)

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
  remarks: '',
  username: '',
  password: '',
  photo: null,
})

const onPhotoChange = (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  form.value.photo = file
  const reader = new FileReader()
  reader.onload = (e) => { photoPreview.value = e.target.result }
  reader.readAsDataURL(file)
}

const removePhoto = () => {
  form.value.photo = null
  photoPreview.value = null
}

const onClassChange = () => {
  // Future: load sections/groups based on class
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

    const body = form.value.photo instanceof File
      ? buildStudentFormData(payload)
      : payload
    const res = await studentService.create(body)
    const student = res.data?.data || res.data
    router.push(`/dashboard/students/${student.id}`)
  } catch (e) {
    console.error('Create failed:', e)
    const errData = e.response?.data
    if (errData?.errors) {
      const messages = Object.values(errData.errors).flat()
      error.value = messages.join(', ')
    } else {
      error.value = errData?.message || 'Failed to create student'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => loadClasses())
</script>

<style scoped>
.student-create-page {
  max-width: 960px;
  margin: 0 auto;
  padding: 1.5rem 1.25rem 2.5rem;
}

/* Page header */
.page-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.25rem;
}
.page-top h1 {
  margin: 0 0 0.25rem;
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-primary);
}
.page-top p {
  margin: 0;
  font-size: 0.88rem;
  color: var(--text-secondary);
  font-weight: 500;
}
.btn-back {
  flex-shrink: 0;
  padding: 0.5rem 1rem;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text-secondary);
  background: var(--bg-card);
  border: 1px solid var(--border-strong);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-back:hover {
  background: var(--bg-surface-muted);
  border-color: var(--text-muted);
  color: var(--text-primary);
}

/* Single unified form sheet */
.form-sheet {
  background: var(--bg-card);
  border-radius: 14px;
  border: 1px solid var(--border-color);
  box-shadow: 0 4px 20px rgba(15, 23, 42, 0.08);
  overflow: hidden;
}

.form-section {
  padding: 1.35rem 1.75rem;
}
.form-section + .form-section {
  border-top: 1px solid #e8edf3;
  background: linear-gradient(180deg, #fafbfc 0%, #fff 12px);
}
.form-section:first-child {
  padding-top: 1.5rem;
}

.section-head {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 1.1rem;
}
.section-icon {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  font-size: 1rem;
  background: #eef2ff;
  flex-shrink: 0;
}
.section-head h3 {
  margin: 0;
  font-size: 0.82rem;
  font-weight: 800;
  color: var(--text-dark);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

/* Photo + personal side by side */
.photo-personal-layout {
  display: grid;
  grid-template-columns: 150px 1fr;
  gap: 1.5rem;
  align-items: start;
}
.photo-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
}
.fields-col { min-width: 0; }

/* Photo upload */
.photo-upload-card {
  width: 130px;
  height: 130px;
  border-radius: 12px;
  border: 2px dashed #c7d2fe;
  background: var(--bg-surface-muted);
  overflow: hidden;
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
}
.photo-upload-card:hover {
  border-color: #4f46e5;
  background: #f5f3ff;
}
.photo-img { width: 100%; height: 100%; object-fit: cover; }
.photo-empty {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.3rem;
  text-align: center;
  padding: 0.5rem;
}
.photo-ring {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #eef2ff;
  font-size: 1.1rem;
  margin-bottom: 0.15rem;
}
.photo-empty strong { font-size: 0.78rem; color: var(--text-secondary); }
.photo-empty span:last-child { font-size: 0.68rem; color: var(--text-muted); font-weight: 500; }
.photo-actions { display: flex; flex-direction: column; gap: 0.4rem; width: 100%; }
.btn-upload {
  padding: 0.4rem 0.65rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #4338ca;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  border-radius: 7px;
  cursor: pointer;
  transition: all 0.15s;
  width: 100%;
}
.btn-upload:hover { background: #4f46e5; color: #fff; border-color: #4f46e5; }
.btn-remove {
  padding: 0.4rem 0.85rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  cursor: pointer;
}

/* Guardian sub-labels */
.sub-label {
  display: inline-block;
  padding: 0.2rem 0.55rem;
  margin-bottom: 0.5rem;
  font-size: 0.72rem;
  font-weight: 700;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.sub-label.father { background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd; }
.sub-label.mother { background: #fce7f3; color: #9d174d; border: 1px solid #fbcfe8; }
.sub-label.emergency { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
.sub-label + .form-row { margin-bottom: 0.85rem; }

/* Login info banner */
.info-banner {
  display: flex;
  align-items: flex-start;
  gap: 0.55rem;
  padding: 0.65rem 0.85rem;
  margin-bottom: 1rem;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  color: #0369a1;
}
.info-icon { font-size: 1rem; flex-shrink: 0; }

/* Form fields */
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.85rem 1rem;
  margin-bottom: 0.85rem;
}
.form-row:last-child { margin-bottom: 0; }
.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.form-group label {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-secondary);
}
.required { color: #dc2626; }
.field-hint {
  font-size: 0.72rem;
  font-weight: 500;
  color: var(--text-muted);
}
.form-input,
.form-select,
.form-textarea {
  padding: 0.55rem 0.75rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 9px;
  font-size: 0.88rem;
  font-weight: 500;
  color: var(--text-primary);
  background: var(--bg-surface-muted);
  transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
  width: 100%;
  box-sizing: border-box;
}
.form-input::placeholder,
.form-textarea::placeholder { color: var(--text-muted); }
.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #4f46e5;
  background: var(--bg-card);
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}
.form-textarea { resize: vertical; font-family: inherit; min-height: 72px; }

/* Error */
.error-alert {
  display: flex;
  align-items: flex-start;
  gap: 0.6rem;
  margin: 0 1.75rem;
  padding: 0.85rem 1rem;
  background: #fef2f2;
  border: 1px solid #fca5a5;
  border-radius: 8px;
  color: #b91c1c;
  font-size: 0.88rem;
  font-weight: 600;
}
.error-alert p { margin: 0; }
.error-icon { font-size: 1.1rem; flex-shrink: 0; }

/* Footer inside sheet */
.sheet-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1.15rem 1.75rem;
  background: var(--bg-surface-muted);
  border-top: 1px solid #e2e8f0;
}
.btn-cancel {
  padding: 0.6rem 1.25rem;
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--text-secondary);
  background: var(--bg-surface-muted);
  border: 1.5px solid #cbd5e1;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-cancel:hover { background: var(--bg-accent); border-color: var(--text-muted); }
.btn-submit {
  padding: 0.6rem 1.5rem;
  font-size: 0.88rem;
  font-weight: 700;
  color: #fff;
  background: #4f46e5;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.15s;
}
.btn-submit:hover:not(:disabled) { background: #4338ca; }
.btn-submit:disabled {
  opacity: 0.55;
  cursor: not-allowed;
  transform: none;
}

@media (max-width: 720px) {
  .page-top { flex-direction: column; }
  .photo-personal-layout { grid-template-columns: 1fr; }
  .photo-col { flex-direction: row; flex-wrap: wrap; justify-content: flex-start; }
  .photo-actions { flex-direction: row; width: auto; }
  .form-row { grid-template-columns: 1fr; }
  .form-section { padding: 1.15rem 1.15rem; }
  .sheet-footer { flex-direction: column-reverse; padding: 1rem 1.15rem; }
  .btn-cancel, .btn-submit { width: 100%; text-align: center; }
}
</style>

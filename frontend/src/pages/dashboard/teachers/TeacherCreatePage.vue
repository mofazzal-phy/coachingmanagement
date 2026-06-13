<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left"><h1>Add Tea</h1></div>
      <div class="header-actions">
        <router-link to="/dashboard/teachers" class="btn btn-outline">← Back</router-link>
      </div>
    </div>

    <div class="form-card">
      <div v-if="error" class="alert alert-danger">{{ error }}</div>
      <div v-if="success" class="alert alert-success">{{ success }}</div>

      <!-- Photo Upload -->
      <h3 class="section-title">Photo</h3>
      <div class="photo-upload-section">
        <div class="photo-preview" @click="$refs.photoInput.click()">
          <img v-if="photoPreview" :src="photoPreview" alt="Teacher photo preview" />
          <div v-else class="photo-placeholder">
            <span class="photo-icon">📷</span>
            <span>Click to upload photo</span>
          </div>
        </div>
        <input
          ref="photoInput"
          type="file"
          accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
          style="display: none"
          @change="onPhotoChange"
        />
        <button v-if="form.photo" type="button" class="btn btn-sm btn-outline-danger" @click="removePhoto" style="margin-top: 8px;">
          Remove Photo
        </button>
      </div>

      <!-- General Information -->
      <h3 class="section-title">General Information</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Teacher ID <span class="required">*</span></label>
          <input v-model="form.teacher_id" class="form-control" placeholder="TCH-001" />
        </div>
        <div class="form-group">
          <label>User Account (optional)</label>
          <select v-model="form.user_id" class="form-control">
            <option value="">No user account</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>First Name <span class="required">*</span></label>
          <input v-model="form.first_name" class="form-control" placeholder="First name" />
        </div>
        <div class="form-group">
          <label>Last Name <span class="required">*</span></label>
          <input v-model="form.last_name" class="form-control" placeholder="Last name" />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Email <span class="required">*</span></label>
          <input v-model="form.email" type="email" class="form-control" placeholder="email@example.com" />
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input v-model="form.phone" class="form-control" placeholder="+880..." />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Gender</label>
          <select v-model="form.gender" class="form-control">
            <option value="">Select...</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>Date of Birth</label>
          <input v-model="form.date_of_birth" type="date" class="form-control" />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Qualification</label>
          <input v-model="form.qualification" class="form-control" placeholder="e.g. MSc in Mathematics" />
        </div>
        <div class="form-group">
          <label>Specialization</label>
          <input v-model="form.specialization" class="form-control" placeholder="e.g. Physics" />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Date of Joining <span class="required">*</span></label>
          <input v-model="form.date_of_joining" type="date" class="form-control" />
        </div>
        <div class="form-group">
          <label>Status</label>
          <select v-model="form.status" class="form-control">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Address</label>
        <textarea v-model="form.address" class="form-control" rows="2" placeholder="Optional"></textarea>
      </div>

      <!-- Teacher Portal Login Credentials -->
      <h3 class="section-title" style="margin-top: 24px;">Teacher Portal Login</h3>
      <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 12px;">
        Create a login account so the teacher can access the teacher portal. Leave blank to skip.
      </p>
      <div class="form-row">
        <div class="form-group">
          <label>Username</label>
          <input v-model="form.username" class="form-control" placeholder="e.g. teacher_rahim" />
          <small style="color: var(--text-muted); font-size: 11px;">Teacher will use this username to login</small>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input v-model="form.password" type="password" class="form-control" placeholder="Min 6 characters" />
          <small style="color: var(--text-muted); font-size: 11px;">Teacher will use this password to login</small>
        </div>
      </div>

      <!-- Teacher Type & Group -->
      <h3 class="section-title" style="margin-top: 24px;">Employment Details</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Teacher Type</label>
          <select v-model="form.teacher_type" class="form-control">
            <option value="permanent">Permanent</option>
            <option value="contracted">Contracted</option>
            <option value="guest">Guest</option>
          </select>
        </div>
        <div class="form-group">
          <label>Academic Group</label>
          <select v-model="form.group_id" class="form-control">
            <option value="">Select Group...</option>
            <option v-for="g in academicGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
        </div>
      </div>

      <!-- Experience -->
      <div class="form-row">
        <div class="form-group">
          <label>Experience (Years)</label>
          <input v-model.number="form.experience_years" type="number" min="0" class="form-control" placeholder="0" />
        </div>
        <div class="form-group">
          <label>Previous Institution</label>
          <input v-model="form.previous_institution" class="form-control" placeholder="Previous school/college name" />
        </div>
      </div>

      <!-- Salary -->
      <div class="form-row">
        <div class="form-group">
          <label>Salary Type</label>
          <select v-model="form.salary_type" class="form-control">
            <option value="monthly">Monthly</option>
            <option value="class_wise">Class Wise</option>
            <option value="subject_wise">Subject Wise</option>
          </select>
        </div>
        <div class="form-group">
          <label>Salary Amount</label>
          <input v-model.number="form.salary_amount" type="number" min="0" step="0.01" class="form-control" placeholder="0.00" />
        </div>
      </div>

      <!-- Assigned Classes (Multiple) -->
      <h3 class="section-title" style="margin-top: 24px;">Assigned Classes</h3>
      <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 12px;">Select the classes this teacher will teach.</p>
      <div v-if="classes.length === 0" style="color: var(--text-muted); font-size: 13px;">Loading classes...</div>
      <div v-else class="checkbox-grid">
        <label class="checkbox-item" v-for="cls in classes" :key="cls.id">
          <input type="checkbox" :value="cls.id" v-model="selectedClasses" />
          <span>{{ cls.name }}</span>
        </label>
      </div>

      <!-- Assigned Subjects (Multiple) -->
      <h3 class="section-title" style="margin-top: 24px;">Assigned Subjects</h3>
      <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 12px;">Select the subjects this teacher will teach.</p>
      <div v-if="subjects.length === 0" style="color: var(--text-muted); font-size: 13px;">Loading subjects...</div>
      <div v-else class="checkbox-grid">
        <label class="checkbox-item" v-for="sub in subjects" :key="sub.id">
          <input type="checkbox" :value="sub.id" v-model="selectedSubjects" />
          <span>{{ sub.name }} <small v-if="sub.code" style="color: var(--text-muted);">({{ sub.code }})</small></span>
        </label>
      </div>

      <div class="form-actions">
        <router-link to="/dashboard/teachers" class="btn btn-outline">Cancel</router-link>
        <button class="btn btn-primary" @click="saveItem" :disabled="!form.teacher_id || !form.first_name || !form.last_name || !form.email || !form.date_of_joining || saving">
          {{ saving ? 'Saving...' : 'Create Teacher' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import teacherService from '@/services/teacher.service'
import { downloadBlobFile } from '@/utils/photo.utils'
import userService from '@/services/user.service'
import academicService from '@/services/academic.service'

const router = useRouter()
const users = ref([])
const classes = ref([])
const subjects = ref([])
const academicGroups = ref([])
const selectedClasses = ref([])
const selectedSubjects = ref([])
const error = ref(null)
const success = ref(null)
const saving = ref(false)
const photoPreview = ref(null)

const form = ref({
  teacher_id: '',
  user_id: '',
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  gender: '',
  date_of_birth: '',
  qualification: '',
  specialization: '',
  date_of_joining: '',
  address: '',
  photo: null,
  status: 'active',
  teacher_type: 'permanent',
  group_id: '',
  experience_years: 0,
  previous_institution: '',
  salary_type: 'monthly',
  salary_amount: 0,
  username: '',
  password: '',
})

const loadData = async () => {
  try {
    const [usersRes, classesRes, subjectsRes, groupsRes] = await Promise.all([
      userService.list({ per_page: 100 }),
      academicService.classes.listAll(),
      academicService.subjects.listAll(),
      academicService.groups.listAll(),
    ])
    users.value = usersRes.data?.data || usersRes.data || []
    classes.value = classesRes.data?.data || classesRes.data || []
    subjects.value = subjectsRes.data?.data || subjectsRes.data || []
    academicGroups.value = groupsRes.data?.data || groupsRes.data || []
  } catch {}
}

const onPhotoChange = (event) => {
  const file = event.target.files[0]
  if (!file) return
  form.value.photo = file
  // Generate preview
  const reader = new FileReader()
  reader.onload = (e) => {
    photoPreview.value = e.target.result
  }
  reader.readAsDataURL(file)
}

const removePhoto = () => {
  form.value.photo = null
  photoPreview.value = null
  // Reset file input
  if (document.querySelector('input[type="file"]')) {
    document.querySelector('input[type="file"]').value = ''
  }
}

const saveItem = async () => {
  saving.value = true
  error.value = null
  success.value = null
  try {
    const payload = new FormData()
    
    // Append all form fields
    Object.keys(form.value).forEach(key => {
      const value = form.value[key]
      if (key === 'photo' && value instanceof File) {
        payload.append('photo', value)
      } else if (value !== null && value !== '') {
        payload.append(key, value)
      }
    })

    // Append arrays
    selectedClasses.value.forEach(id => payload.append('class_ids[]', id))
    selectedSubjects.value.forEach(id => payload.append('subject_ids[]', id))

    const res = await teacherService.create(payload)
    const teacher = res?.data || res
    success.value = 'Teacher created successfully!'
    try {
      if (teacher?.id) {
        const idRes = await teacherService.downloadIdCard(teacher.id)
        downloadBlobFile(idRes, `teacher-id-${teacher.teacher_id || teacher.id}.pdf`)
      }
    } catch {}
    setTimeout(() => router.push('/dashboard/teachers'), 1500)
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to create teacher'
  } finally {
    saving.value = false
  }
}

onMounted(loadData)
</script>

<style scoped>
.checkbox-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 8px;
  margin-bottom: 8px;
}
.checkbox-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: var(--transition);
  font-size: 14px;
}
.checkbox-item:hover {
  border-color: var(--primary-color);
  background: #f0f6ff;
}
.checkbox-item input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: var(--primary-color);
}

/* Photo upload styles */
.photo-upload-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 20px;
}
.photo-preview {
  width: 150px;
  height: 150px;
  border-radius: 50%;
  border: 2px dashed var(--border-color);
  overflow: hidden;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: var(--transition);
  background: var(--bg-secondary, #f8f9fa);
}
.photo-preview:hover {
  border-color: var(--primary-color);
  background: #f0f6ff;
}
.photo-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.photo-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  color: var(--text-muted);
  font-size: 12px;
  text-align: center;
}
.photo-icon {
  font-size: 32px;
}
</style>

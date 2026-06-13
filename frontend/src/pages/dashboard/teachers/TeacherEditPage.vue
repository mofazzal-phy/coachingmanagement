<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left"><h1>Edit Teacher</h1></div>
      <div class="header-actions">
        <router-link to="/dashboard/teachers" class="btn btn-outline">← Back</router-link>
      </div>
    </div>

    <div class="form-card">
      <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading teacher...</p></div>
      <div v-else>
        <div v-if="error" class="alert alert-danger">{{ error }}</div>
        <div v-if="success" class="alert alert-success">{{ success }}</div>

        <!-- General Information -->
        <h3 class="section-title">General Information</h3>
        <div class="form-row">
          <div class="form-group">
            <label>Teacher ID</label>
            <input v-model="form.teacher_id" class="form-control" disabled />
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
            <input v-model="form.first_name" class="form-control" />
          </div>
          <div class="form-group">
            <label>Last Name <span class="required">*</span></label>
            <input v-model="form.last_name" class="form-control" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Email</label>
            <input v-model="form.email" type="email" class="form-control" disabled />
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input v-model="form.phone" class="form-control" />
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
            <input v-model="form.qualification" class="form-control" />
          </div>
          <div class="form-group">
            <label>Specialization</label>
            <input v-model="form.specialization" class="form-control" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Date of Joining</label>
            <input v-model="form.date_of_joining" type="date" class="form-control" />
          </div>
          <div class="form-group">
            <label>Status</label>
            <select v-model="form.status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="resigned">Resigned</option>
              <option value="terminated">Terminated</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Address</label>
          <textarea v-model="form.address" class="form-control" rows="2"></textarea>
        </div>

        <!-- Employment Details -->
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

        <div class="form-row">
          <div class="form-group">
            <label>Experience (Years)</label>
            <input v-model.number="form.experience_years" type="number" min="0" class="form-control" />
          </div>
          <div class="form-group">
            <label>Previous Institution</label>
            <input v-model="form.previous_institution" class="form-control" placeholder="Previous school/college name" />
          </div>
        </div>

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
            <input v-model.number="form.salary_amount" type="number" min="0" step="0.01" class="form-control" />
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
          <button class="btn btn-primary" @click="updateItem" :disabled="!form.first_name || !form.last_name || saving">
            {{ saving ? 'Saving...' : 'Update Teacher' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import teacherService from '@/services/teacher.service'
import userService from '@/services/user.service'
import academicService from '@/services/academic.service'

const route = useRoute()
const router = useRouter()
const users = ref([])
const classes = ref([])
const subjects = ref([])
const academicGroups = ref([])
const selectedClasses = ref([])
const selectedSubjects = ref([])
const loading = ref(true)
const error = ref(null)
const success = ref(null)
const saving = ref(false)

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
  status: 'active',
  teacher_type: 'permanent',
  group_id: '',
  experience_years: 0,
  previous_institution: '',
  salary_type: 'monthly',
  salary_amount: 0,
})

const loadData = async () => {
  loading.value = true
  try {
    const [teacherRes, usersRes, classesRes, subjectsRes, groupsRes] = await Promise.all([
      teacherService.getById(route.params.id),
      userService.list({ per_page: 100 }),
      academicService.classes.listAll(),
      academicService.subjects.listAll(),
      academicService.groups.listAll(),
    ])
    const teacher = teacherRes.data || teacherRes
    form.value = {
      teacher_id: teacher.teacher_id || '',
      user_id: teacher.user_id || '',
      first_name: teacher.first_name || '',
      last_name: teacher.last_name || '',
      email: teacher.email || '',
      phone: teacher.phone || '',
      gender: teacher.gender || '',
      date_of_birth: teacher.date_of_birth || '',
      qualification: teacher.qualification || '',
      specialization: teacher.specialization || '',
      date_of_joining: teacher.date_of_joining || '',
      address: teacher.address || '',
      status: teacher.status || 'active',
      teacher_type: teacher.teacher_type || 'permanent',
      group_id: teacher.group_id || '',
      experience_years: teacher.experience_years || 0,
      previous_institution: teacher.previous_institution || '',
      salary_type: teacher.salary_type || 'monthly',
      salary_amount: teacher.salary_amount || 0,
    }
    users.value = usersRes.data?.data || usersRes.data || []
    classes.value = classesRes.data?.data || classesRes.data || []
    subjects.value = subjectsRes.data?.data || subjectsRes.data || []
    academicGroups.value = groupsRes.data?.data || groupsRes.data || []

    // Pre-select existing classes and subjects
    if (teacher.classes && teacher.classes.length) {
      selectedClasses.value = teacher.classes.map(c => c.id || c.pivot?.class_id || c)
    }
    if (teacher.subjects && teacher.subjects.length) {
      selectedSubjects.value = teacher.subjects.map(s => s.id || s.pivot?.subject_id || s)
    }
  } catch (e) {
    error.value = 'Failed to load teacher data'
  } finally {
    loading.value = false
  }
}

const updateItem = async () => {
  saving.value = true
  error.value = null
  success.value = null
  try {
    const payload = {
      ...form.value,
      user_id: form.value.user_id || null,
      class_ids: selectedClasses.value,
      subject_ids: selectedSubjects.value,
    }
    await teacherService.update(route.params.id, payload)
    success.value = 'Teacher updated successfully!'
    setTimeout(() => router.push('/dashboard/teachers'), 1000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to update teacher'
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
</style>

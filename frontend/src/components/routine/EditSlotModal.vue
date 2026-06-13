<template>
  <div class="modal-overlay" v-if="modelValue" @click.self="$emit('update:modelValue', false)">
    <div class="modal-dialog">
      <!-- Header -->
      <div class="modal-header">
        <h3>{{ isEditing ? 'Edit Class Slot' : 'Add Class Slot' }}</h3>
        <button class="modal-close" @click="$emit('update:modelValue', false)">✕</button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="modal-body">
        <!-- Class (cascading) -->
        <div class="form-group">
          <label>Class <span class="required">*</span></label>
          <div v-if="loadingClasses" class="text-center py-2">
            <div class="spinner" style="margin: 0 auto 0.3rem;"></div>
            <p class="text-muted">Loading classes...</p>
          </div>
          <select
            v-else
            v-model="form.class_id"
            class="form-control"
            required
          >
            <option value="" disabled>Select class</option>
            <option v-for="c in allClasses" :key="c.id" :value="c.id">
              {{ c.name || c.title }}
            </option>
          </select>
        </div>

        <!-- Course (filtered by class) -->
        <div v-if="form.class_id" class="form-group">
          <label>Course <span class="required">*</span></label>
          <div v-if="loadingCourses" class="text-center py-2">
            <div class="spinner" style="margin: 0 auto 0.3rem;"></div>
            <p class="text-muted">Loading courses...</p>
          </div>
          <select
            v-else
            v-model="form.course_id"
            class="form-control"
            required
          >
            <option value="" disabled>Select course</option>
            <option v-for="co in filteredCourses" :key="co.id" :value="co.id">
              {{ co.name || co.title }}
            </option>
          </select>
        </div>

        <!-- Batch (filtered by course) -->
        <div v-if="form.course_id" class="form-group">
          <label>Batch <span class="required">*</span></label>
          <div v-if="loadingBatches" class="text-center py-2">
            <div class="spinner" style="margin: 0 auto 0.3rem;"></div>
            <p class="text-muted">Loading batches...</p>
          </div>
          <select
            v-else
            v-model="form.batch_id"
            class="form-control"
            required
          >
            <option value="" disabled>Select batch</option>
            <option v-for="b in filteredBatches" :key="b.id" :value="b.id">
              {{ b.name || b.title }}
            </option>
          </select>
        </div>

        <!-- Subject -->
        <div class="form-group">
          <label>Subject <span class="required">*</span></label>
          <div v-if="loadingSubjects" class="text-center py-2">
            <div class="spinner" style="margin: 0 auto 0.3rem;"></div>
            <p class="text-muted">Loading subjects...</p>
          </div>
          <select
            v-else
            v-model="form.subject_id"
            class="form-control"
            required
          >
            <option value="" disabled>Select subject</option>
            <option v-for="subj in filteredSubjects" :key="subj.id" :value="subj.id">
              {{ subj.name }}
            </option>
          </select>
        </div>

        <!-- Teacher -->
        <div class="form-group">
          <label>Teacher <span class="required">*</span></label>
          <div v-if="loadingTeachers" class="text-center py-2">
            <div class="spinner" style="margin: 0 auto 0.3rem;"></div>
            <p class="text-muted">Loading teachers...</p>
          </div>
          <select
            v-else
            v-model="form.teacher_id"
            class="form-control"
            required
          >
            <option value="" disabled>Select teacher</option>
            <option v-for="t in filteredTeachers" :key="t.id" :value="t.id">
              {{ t.name || (t.first_name && t.last_name ? t.first_name + ' ' + t.last_name : '') || t.full_name || t.user?.name || t.teacher_id || 'Unknown' }}
            </option>
          </select>
        </div>

        <!-- Day of Week -->
        <div class="form-group">
          <label>Day <span class="required">*</span></label>
          <select
            v-model="form.day_of_week"
            class="form-control"
            required
          >
            <option value="" disabled>Select day</option>
            <option value="sat">Saturday</option>
            <option value="sun">Sunday</option>
            <option value="mon">Monday</option>
            <option value="tue">Tuesday</option>
            <option value="wed">Wednesday</option>
            <option value="thu">Thursday</option>
            <option value="fri">Friday</option>
          </select>
        </div>

        <!-- Time Range -->
        <div class="form-row">
          <div class="form-group">
            <label>Start Time <span class="required">*</span></label>
            <input
              v-model="form.start_time"
              type="time"
              class="form-control"
              required
            />
          </div>
          <div class="form-group">
            <label>End Time <span class="required">*</span></label>
            <input
              v-model="form.end_time"
              type="time"
              class="form-control"
              required
            />
          </div>
        </div>

        <!-- Room -->
        <div class="form-group">
          <label>Room</label>
          <select
            v-model="form.room_id"
            class="form-control"
          >
            <option value="">No room</option>
            <option v-for="r in rooms" :key="r.id" :value="r.id">
              {{ r.name || r.room_number }}
            </option>
          </select>
        </div>

        <!-- Group -->
        <div class="form-group">
          <label>Group</label>
          <select
            v-model="form.group_id"
            class="form-control"
          >
            <option value="">No group</option>
            <option v-for="g in groups" :key="g.id" :value="g.id">
              {{ g.name }}
            </option>
          </select>
        </div>

        <!-- Status -->
        <div class="form-group">
          <label>Status</label>
          <select
            v-model="form.status"
            class="form-control"
          >
            <option value="draft">Draft</option>
            <option value="published">Published</option>
          </select>
        </div>

        <!-- Error -->
        <div v-if="error" class="alert alert-danger">
          {{ error }}
        </div>
      </form>

      <!-- Footer -->
      <div class="modal-footer">
        <button
          v-if="isEditing"
          type="button"
          class="btn btn-danger"
          @click="$emit('delete', slotData?.id)"
        >
          Delete
        </button>
        <div v-else></div>
        <div style="display:flex; gap: 0.5rem;">
          <button
            type="button"
            class="btn btn-outline"
            @click="$emit('update:modelValue', false)"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="saving || !form.batch_id || !form.subject_id || !form.teacher_id || !form.day_of_week || !form.start_time || !form.end_time"
            @click="handleSubmit"
          >
            {{ saving ? 'Saving...' : isEditing ? 'Update' : 'Create' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import academicService from '@/services/academic.service'
import enrollmentService from '@/services/enrollment.service'
import teacherService from '@/services/teacher.service'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  slotData: { type: Object, default: null },
  subjects: { type: Array, default: () => [] },
  teachers: { type: Array, default: () => [] },
  rooms: { type: Array, default: () => [] },
  groups: { type: Array, default: () => [] },
  batches: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'save', 'delete'])

const isEditing = computed(() => !!props.slotData?.id)

// Cascading state
const allClasses = ref([])
const loadingClasses = ref(false)
const filteredCourses = ref([])
const loadingCourses = ref(false)
const filteredBatches = ref([])
const loadingBatches = ref(false)
const filteredSubjects = ref([])
const loadingSubjects = ref(false)
const filteredTeachers = ref([])
const loadingTeachers = ref(false)

const form = ref({
  class_id: '',
  course_id: '',
  batch_id: '',
  subject_id: '',
  teacher_id: '',
  day_of_week: '',
  start_time: '',
  end_time: '',
  room_id: '',
  group_id: '',
  status: 'draft',
})

// Flag to suppress cascading watchers during initial form setup
let isInitializing = false

// Load classes
async function loadClasses() {
  loadingClasses.value = true
  try {
    const res = await academicService.classes.list()
    allClasses.value = res?.data?.data || res?.data || []
  } catch (e) {
    console.warn('Failed to load classes', e)
    allClasses.value = []
  } finally {
    loadingClasses.value = false
  }
}

// Watch class_id → fetch courses
watch(() => form.value.class_id, async (newClassId) => {
  if (isInitializing) return
  form.value.course_id = ''
  form.value.batch_id = ''
  form.value.subject_id = ''
  form.value.teacher_id = ''
  filteredCourses.value = []
  filteredBatches.value = []
  filteredSubjects.value = []
  filteredTeachers.value = []
  if (!newClassId) return
  loadingCourses.value = true
  try {
    const res = await enrollmentService.getCourses({ class_id: newClassId })
    filteredCourses.value = res?.data?.data || res?.data || []
  } catch (e) {
    console.warn('Failed to fetch courses for class:', newClassId, e)
    filteredCourses.value = []
  } finally {
    loadingCourses.value = false
  }
})

// Watch course_id → fetch batches
watch(() => form.value.course_id, async (newCourseId) => {
  if (isInitializing) return
  form.value.batch_id = ''
  form.value.subject_id = ''
  form.value.teacher_id = ''
  filteredBatches.value = []
  filteredSubjects.value = []
  filteredTeachers.value = []
  if (!newCourseId) return
  loadingBatches.value = true
  try {
    const res = await enrollmentService.getBatchesByCourse(newCourseId)
    filteredBatches.value = res?.data?.data || res?.data || []
  } catch (e) {
    console.warn('Failed to fetch batches for course:', newCourseId, e)
    filteredBatches.value = []
  } finally {
    loadingBatches.value = false
  }
})

// Watch batch_id → fetch subjects
watch(() => form.value.batch_id, async (newBatchId) => {
  if (isInitializing) return
  form.value.subject_id = ''
  form.value.teacher_id = ''
  filteredSubjects.value = []
  filteredTeachers.value = []
  if (!newBatchId) return
  loadingSubjects.value = true
  try {
    const res = await enrollmentService.getBatch(newBatchId)
    const batch = res?.data?.data || res?.data
    if (batch?.course_id) {
      const courseRes = await enrollmentService.getCourse(batch.course_id)
      const course = courseRes?.data?.data || courseRes?.data
      filteredSubjects.value = course?.subjects || []
    } else {
      filteredSubjects.value = []
    }
  } catch (e) {
    console.warn('Failed to fetch subjects for batch:', newBatchId, e)
    filteredSubjects.value = []
  } finally {
    loadingSubjects.value = false
  }
})

// Watch subject_id → fetch teachers
watch(() => form.value.subject_id, async (newSubjectId) => {
  if (isInitializing) return
  form.value.teacher_id = ''
  filteredTeachers.value = []
  if (!newSubjectId || !form.value.batch_id) return
  loadingTeachers.value = true
  try {
    const res = await teacherService.bySubject(newSubjectId)
    const data = res?.data?.data || res?.data || []
    filteredTeachers.value = Array.isArray(data) ? data : []
  } catch (e) {
    console.warn('Failed to fetch teachers for subject:', newSubjectId, e)
    filteredTeachers.value = []
  } finally {
    loadingTeachers.value = false
  }
})

// Initialize form from slotData or reset
watch(() => props.slotData, async (val) => {
  isInitializing = true
  if (val) {
    const truncateTime = (t) => t ? t.substring(0, 5) : ''
    form.value = {
      class_id: '',
      course_id: '',
      batch_id: val.batch_id || val.batch?.id || '',
      subject_id: val.subject_id || val.subject?.id || '',
      teacher_id: val.teacher_id || val.teacher?.id || '',
      day_of_week: val.day_of_week || '',
      start_time: truncateTime(val.start_time),
      end_time: truncateTime(val.end_time),
      room_id: val.room_id || val.room?.id || '',
      group_id: val.group_id || val.group?.id || '',
      status: val.status || 'draft',
    }
    // If we have a batch_id, try to resolve class/course from batch
    if (form.value.batch_id) {
      try {
        const res = await enrollmentService.getBatch(form.value.batch_id)
        const batch = res?.data?.data || res?.data
        if (batch) {
          form.value.course_id = batch.course_id || ''
          if (batch.course_id) {
            const courseRes = await enrollmentService.getCourse(batch.course_id)
            const course = courseRes?.data?.data || courseRes?.data
            if (course) {
              form.value.class_id = course.class_id || ''
              filteredCourses.value = [course]
              // Load batches for this course
              const batchesRes = await enrollmentService.getBatchesByCourse(batch.course_id)
              filteredBatches.value = batchesRes?.data?.data || batchesRes?.data || []
              // Load subjects for this batch
              filteredSubjects.value = course?.subjects || []
            }
          }
        }
      } catch (e) {
        console.warn('Failed to resolve batch context', e)
      }
    }
    // Also try to load teachers for the subject if subject_id is set
    if (form.value.subject_id && form.value.batch_id) {
      try {
        const res = await teacherService.bySubject(form.value.subject_id)
        const data = res?.data?.data || res?.data || []
        filteredTeachers.value = Array.isArray(data) ? data : []
      } catch (e) {
        console.warn('Failed to load teachers for subject', e)
      }
    }
  } else {
    form.value = {
      class_id: '',
      course_id: '',
      batch_id: '',
      subject_id: '',
      teacher_id: '',
      day_of_week: '',
      start_time: '',
      end_time: '',
      room_id: '',
      group_id: '',
      status: 'draft',
    }
    filteredCourses.value = []
    filteredBatches.value = []
    filteredSubjects.value = []
    filteredTeachers.value = []
  }
  isInitializing = false
}, { immediate: true })

// Load classes on mount
watch(() => props.modelValue, (val) => {
  if (val) loadClasses()
}, { immediate: false })

function handleSubmit() {
  emit('save', { ...form.value })
}
</script>

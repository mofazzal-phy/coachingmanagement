<template>
  <div class="step-card">
    <h2>📚 Academic Details</h2>
    <form @submit.prevent="save">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Class</label>
          <select v-model="form.current_class_id" class="form-select">
            <option value="">Select Class</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Section</label>
          <select v-model="form.current_section_id" class="form-select">
            <option value="">Select</option>
            <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
      </div>
      <div class="form-row" v-if="showGroup">
        <div class="form-group">
          <label class="form-label">Group</label>
          <select v-model="form.group_id" class="form-select">
            <option value="">Select</option>
            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Session</label>
          <select v-model="form.academic_session_id" class="form-select">
            <option value="">Auto</option>
            <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Previous School</label><input v-model="form.previous_school" class="form-input" /></div>
        <div class="form-group"><label class="form-label">SSC Result (GPA)</label><input v-model="form.ssc_result" class="form-input" type="number" step="0.01" min="0" max="5" /></div>
      </div>

      <div class="step-actions">
        <button type="submit" class="btn btn-primary">Continue →</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import academicService from '@/services/academic.service'

const emit = defineEmits(['next'])
const form = reactive({ current_class_id:'', current_section_id:'', group_id:'', academic_session_id:'', previous_school:'', ssc_result:null })
const classes = ref([])
const groups = ref([])
const sections = ref([])
const sessions = ref([])

const showGroup = computed(() => {
  const cls = classes.value.find(c => c.id === form.current_class_id)
  const num = parseInt(cls?.numeric_value || cls?.name?.match(/\d+/)?.[0] || 0)
  return num >= 9
})

onMounted(async () => {
  try {
    const [cRes, gRes, sRes, ssRes] = await Promise.all([
      academicService.getClasses({ per_page: 100 }),
      academicService.getGroups({ per_page: 100 }),
      academicService.getSections?.({ per_page: 200 }) || Promise.resolve({ data: { data: [] } }),
      academicService.getSessions({ per_page: 20 }),
    ])
    classes.value = cRes.data?.data || cRes.data || []
    groups.value = gRes.data?.data || gRes.data || []
    sections.value = sRes.data?.data || sRes.data || []
    sessions.value = ssRes.data?.data || ssRes.data || []
  } catch (e) { console.error(e) }
})

const save = () => { emit('next', { ...form }) }
</script>

<style scoped>
.step-card { background: var(--bg-card); border-radius: 14px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.step-card h2 { margin: 0 0 1rem 0; font-size: 1.15rem; }
.form-row { display: flex; gap: 1rem; margin-bottom: 0.75rem; }
.form-group { flex: 1; }
.form-label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; color: #555; }
.form-input, .form-select { width: 100%; padding: 0.55rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; }
.step-actions { text-align: right; margin-top: 1rem; }
.btn-primary { background: #4a90d9; color: #fff; border: none; padding: 0.7rem 1.5rem; border-radius: 10px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
</style>

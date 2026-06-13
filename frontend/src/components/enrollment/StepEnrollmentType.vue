<template>
  <div class="step-card">
    <h2>Choose Enrollment Type</h2>
    <div class="type-cards">
      <div class="type-card" :class="{ selected: selected === 'new' }" @click="selected = 'new'">
        <span class="type-icon">🆕</span>
        <strong>New Student</strong>
        <p>Create from scratch</p>
      </div>
      <div class="type-card" :class="{ selected: selected === 'existing' }" @click="selected = 'existing'">
        <span class="type-icon">🔍</span>
        <strong>Existing Student</strong>
        <p>Search & select</p>
      </div>
    </div>

    <!-- Existing: search -->
    <div v-if="selected === 'existing'" class="mt-2">
      <StudentSearchCard @student-selected="onExistingSelected" />
    </div>

    <div class="step-actions">
      <button class="btn btn-primary" :disabled="!canContinue" @click="proceed">
        Continue →
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import StudentSearchCard from '@/components/enrollment/StudentSearchCard.vue'

const emit = defineEmits(['next'])
const selected = ref('new')
const existingStudent = ref(null)

const canContinue = computed(() => {
  if (selected.value === 'new') return true
  return !!existingStudent.value
})

const onExistingSelected = (student) => {
  existingStudent.value = student
}

const proceed = () => {
  emit('next', selected.value, existingStudent.value?.id || null)
}
</script>

<style scoped>
.step-card { background: var(--bg-card); border-radius: 14px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.step-card h2 { margin: 0 0 1.5rem 0; font-size: 1.2rem; }

.type-cards { display: flex; gap: 1rem; }
.type-card {
  flex: 1; padding: 1.5rem; border: 2px solid #e5e7eb; border-radius: 12px;
  text-align: center; cursor: pointer; transition: all 0.2s;
}
.type-card:hover { border-color: #4a90d9; }
.type-card.selected { border-color: #4a90d9; background: #f0f4ff; }
.type-icon { font-size: 2rem; display: block; margin-bottom: 0.5rem; }
.type-card strong { display: block; font-size: 1rem; margin-bottom: 0.25rem; }
.type-card p { font-size: 0.8rem; color: #888; margin: 0; }

.step-actions { margin-top: 1.5rem; text-align: right; }
.btn-primary { background: #4a90d9; color: #fff; border: none; padding: 0.7rem 1.5rem; border-radius: 10px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.mt-2 { margin-top: 1rem; }
</style>

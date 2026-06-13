<template>
  <div class="step-card">
    <h2>👤 Personal & Guardian Info</h2>
    <form @submit.prevent="save">
      <div class="form-section">
        <h4>Personal</h4>
        <div class="form-row">
          <div class="form-group"><label class="form-label">First Name *</label><input v-model="form.first_name" class="form-input" required /></div>
          <div class="form-group"><label class="form-label">Last Name</label><input v-model="form.last_name" class="form-input" /></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Phone *</label><input v-model="form.phone" class="form-input" required /></div>
          <div class="form-group"><label class="form-label">Email</label><input v-model="form.email" class="form-input" type="email" /></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">DOB</label><input v-model="form.date_of_birth" class="form-input" type="date" /></div>
          <div class="form-group"><label class="form-label">Gender</label><select v-model="form.gender" class="form-select"><option value="">Select</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
        </div>
      </div>

      <div class="form-section">
        <h4>👨‍👩‍👧 Guardian</h4>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Guardian Name *</label><input v-model="form.guardian_name" class="form-input" required /></div>
          <div class="form-group"><label class="form-label">Guardian Phone *</label><input v-model="form.guardian_phone" class="form-input" required /></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Relation</label><input v-model="form.guardian_relation" class="form-input" placeholder="Father/Mother" /></div>
          <div class="form-group"><label class="form-label">Father Name</label><input v-model="form.father_name" class="form-input" /></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Father Phone</label><input v-model="form.father_phone" class="form-input" /></div>
          <div class="form-group"><label class="form-label">Mother Name</label><input v-model="form.mother_name" class="form-input" /></div>
        </div>
      </div>

      <div class="form-section">
        <h4>📍 Address</h4>
        <div class="form-group"><label class="form-label">Present Address</label><input v-model="form.present_address" class="form-input" /></div>
        <div class="form-group"><label class="form-label">Permanent Address</label><input v-model="form.permanent_address" class="form-input" /></div>
      </div>

      <div class="step-actions">
        <button type="submit" class="btn btn-primary" :disabled="!form.first_name || !form.phone || !form.guardian_name || !form.guardian_phone">Continue →</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
const emit = defineEmits(['next'])
const form = reactive({ first_name:'', last_name:'', phone:'', email:'', date_of_birth:'', gender:'', guardian_name:'', guardian_phone:'', guardian_relation:'', father_name:'', father_phone:'', mother_name:'', mother_phone:'', present_address:'', permanent_address:'' })

const save = () => { emit('next', { ...form }) }
</script>

<style scoped>
.step-card { background: var(--bg-card); border-radius: 14px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.step-card h2 { margin: 0 0 1rem 0; font-size: 1.15rem; }

.form-section { margin-bottom: 1.25rem; }
.form-section h4 { font-size: 0.9rem; margin: 0 0 0.5rem 0; color: #555; }

.form-row { display: flex; gap: 1rem; margin-bottom: 0.75rem; }
.form-group { flex: 1; }
.form-label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; color: #555; }
.form-input, .form-select { width: 100%; padding: 0.55rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; }

.step-actions { text-align: right; margin-top: 1rem; }
.btn-primary { background: #4a90d9; color: #fff; border: none; padding: 0.7rem 1.5rem; border-radius: 10px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
</style>

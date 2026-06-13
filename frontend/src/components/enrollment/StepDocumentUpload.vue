<template>
  <div class="step-card">
    <h2>📎 Documents (Optional)</h2>
    <div v-for="doc in docTypes" :key="doc.type" class="doc-uploader">
      <div class="doc-header">
        <span class="doc-icon">{{ doc.icon }}</span>
        <strong>{{ doc.label }}</strong>
        <span class="text-muted">({{ doc.accept }}, max 5MB)</span>
      </div>
      <label class="dropzone" :class="{ 'has-file': doc.file }">
        <input type="file" :accept="doc.accept" @change="e => onFile(e, doc)" hidden />
        <template v-if="doc.file">
          <span>✓ {{ doc.file.name }}</span>
          <button type="button" class="btn-link" @click="doc.file = null">✕ Remove</button>
        </template>
        <template v-else>
          <span>📁 Click or drop file here</span>
        </template>
      </label>
    </div>

    <p class="text-muted mt-2">You can upload these later from the student profile.</p>

    <div class="step-actions">
      <button class="btn btn-outline" @click="$emit('next', [])">Skip</button>
      <button class="btn btn-primary" @click="save">Continue →</button>
    </div>
  </div>
</template>

<script setup>
import { reactive } from 'vue'
const emit = defineEmits(['next'])

const docTypes = reactive([
  { type: 'photo', label: 'Student Photo', icon: '🖼', accept: 'image/*', file: null },
  { type: 'birth_certificate', label: 'Birth Certificate', icon: '📄', accept: '.pdf,.jpg,.png', file: null },
  { type: 'marksheet', label: 'SSC Marksheet', icon: '📊', accept: '.pdf,.jpg,.png', file: null },
  { type: 'nid', label: 'NID (optional)', icon: '🆔', accept: '.pdf,.jpg,.png', file: null },
])

const onFile = (e, doc) => { doc.file = e.target.files[0] || null }

const save = () => {
  const uploaded = docTypes.filter(d => d.file).map(d => ({ type: d.type, file: d.file }))
  emit('next', uploaded)
}
</script>

<style scoped>
.step-card { background: var(--bg-card); border-radius: 14px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.step-card h2 { margin: 0 0 1rem 0; font-size: 1.15rem; }

.doc-uploader { margin-bottom: 1rem; }
.doc-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem; }
.doc-icon { font-size: 1.2rem; }

.dropzone {
  display: flex; align-items: center; justify-content: center;
  border: 2px dashed #ddd; border-radius: 10px; padding: 1.25rem;
  cursor: pointer; text-align: center; transition: all 0.2s; color: #888; font-size: 0.85rem;
}
.dropzone:hover { border-color: #4a90d9; color: #4a90d9; }
.dropzone.has-file { border-color: #27ae60; color: #27ae60; background: var(--bg-accent); }

.step-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem; }
.btn-primary { background: #4a90d9; color: #fff; border: none; padding: 0.7rem 1.5rem; border-radius: 10px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
.btn-outline { border: 1px solid var(--border-color); background: none; padding: 0.7rem 1.5rem; border-radius: 10px; cursor: pointer; }
.btn-link { color: #e74c3c; background: none; border: none; cursor: pointer; font-size: 0.8rem; }
.text-muted { color: #888; font-size: 0.8rem; }
.mt-2 { margin-top: 0.5rem; }
</style>

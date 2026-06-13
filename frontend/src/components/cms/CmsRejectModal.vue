<template>
  <div class="modal-overlay" v-if="show" @click.self="$emit('close')">
    <div class="modal-dialog modal-sm">
      <div class="modal-header">
        <h3>Reject Content</h3>
        <button class="modal-close" @click="$emit('close')">×</button>
      </div>
      <div class="modal-body">
        <p v-if="title">Reject <strong>{{ title }}</strong>?</p>
        <div class="form-group">
          <label>Reason <span class="required">*</span></label>
          <textarea
            :value="reason"
            class="form-control"
            rows="3"
            placeholder="Explain why this is rejected..."
            @input="$emit('update:reason', $event.target.value)"
          ></textarea>
        </div>
        <div v-if="error" class="alert alert-danger">{{ error }}</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" @click="$emit('close')">Cancel</button>
        <button class="btn btn-danger" :disabled="loading" @click="$emit('confirm')">
          {{ loading ? 'Rejecting...' : 'Reject' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  reason: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  error: { type: String, default: null },
})

defineEmits(['close', 'confirm', 'update:reason'])
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45);
  display: flex; align-items: center; justify-content: center; z-index: 1100; padding: 1rem;
}
.modal-dialog { background: var(--bg-card, #fff); border-radius: 12px; width: 100%; max-width: 420px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #e5e7eb; }
.modal-header h3 { margin: 0; font-size: 1.1rem; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #9ca3af; }
.modal-body { padding: 1.25rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.25rem; border-top: 1px solid #e5e7eb; }
.form-group { margin-bottom: 0.75rem; }
.form-group label { display: block; margin-bottom: 0.35rem; font-size: 0.875rem; font-weight: 500; }
.required { color: #dc2626; }
.form-control { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-family: inherit; }
.alert-danger { background: #fef2f2; color: #dc2626; padding: 0.5rem 0.75rem; border-radius: 8px; font-size: 0.875rem; }
.btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; font-size: 0.875rem; }
.btn-outline { background: transparent; border: 1px solid #d1d5db; }
.btn-danger { background: #dc2626; color: #fff; }
.btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }
</style>

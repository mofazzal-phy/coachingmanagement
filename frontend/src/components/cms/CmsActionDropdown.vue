<template>
  <div class="dropdown" :class="{ 'dropdown-open': open }">
    <button class="dots-button" @click.stop="$emit('toggle')">
      <span class="dots-icon">⋮</span>
    </button>
    <teleport to="body">
      <div v-if="open" class="dropdown-backdrop" @click="$emit('close')"></div>
      <div v-if="open" class="dropdown-menu" :style="menuStyle" @click.stop>
        <div class="dropdown-header">
          <span class="dropdown-title">Actions</span>
          <button class="dropdown-close" @click="$emit('close')">×</button>
        </div>
        <button class="dropdown-item" @click="act('edit')">
          <span class="item-icon">✏️</span>
          <span>{{ editLabel }}</span>
        </button>
        <button v-if="!isPublished" class="dropdown-item" @click="act('publish')">
          <span class="item-icon">📢</span>
          <span>{{ publishLabel }}</span>
        </button>
        <button v-else class="dropdown-item" @click="act('unpublish')">
          <span class="item-icon">🔽</span>
          <span>{{ unpublishLabel }}</span>
        </button>
        <button v-if="canSubmit" class="dropdown-item" @click="act('submit')">
          <span class="item-icon">📤</span>
          <span>Submit for Review</span>
        </button>
        <template v-if="item.approval_status === 'pending_review' && canApprove">
          <button class="dropdown-item" @click="act('approve')">
            <span class="item-icon">✅</span>
            <span>Approve</span>
          </button>
          <button class="dropdown-item dropdown-item-danger" @click="act('reject')">
            <span class="item-icon">❌</span>
            <span>Reject</span>
          </button>
        </template>
        <div class="dropdown-divider"></div>
        <button class="dropdown-item dropdown-item-danger" @click="act('delete')">
          <span class="item-icon">🗑️</span>
          <span>{{ deleteLabel }}</span>
        </button>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  item: { type: Object, required: true },
  open: { type: Boolean, default: false },
  menuStyle: { type: Object, default: () => ({}) },
  editLabel: { type: String, default: 'Edit' },
  deleteLabel: { type: String, default: 'Delete' },
  canSubmit: { type: Boolean, default: false },
  canApprove: { type: Boolean, default: false },
  statusMode: { type: String, default: 'published' },
})

const isPublished = computed(() => {
  if (props.statusMode === 'active') {
    return props.item.status === 'active'
  }
  return props.item.status === 'published'
})

const publishLabel = computed(() => (props.statusMode === 'active' ? 'Activate' : 'Publish'))
const unpublishLabel = computed(() => (props.statusMode === 'active' ? 'Deactivate' : 'Unpublish'))

const emit = defineEmits(['toggle', 'close', 'edit', 'publish', 'unpublish', 'submit', 'approve', 'reject', 'delete'])

const act = (name) => {
  emit(name)
  emit('close')
}
</script>

<style scoped>
.dropdown { position: relative; display: inline-block; }
.dots-button {
  display: inline-flex; align-items: center; justify-content: center;
  width: 36px; height: 36px; border: 1px solid var(--border-color, #e5e7eb);
  background: var(--bg-card, #fff); border-radius: 8px; cursor: pointer;
  font-size: 1.25rem; color: var(--text-muted, #6b7280); padding: 0; transition: all 0.2s;
}
.dots-button:hover { background: var(--bg-subtle, #f3f4f6); color: var(--text-primary, #111); }
.dots-icon { line-height: 1; font-weight: bold; }
.dropdown-backdrop { position: fixed; inset: 0; z-index: 999; background: transparent; }
.dropdown-menu {
  position: fixed; min-width: 220px; background: var(--bg-card, #fff);
  border: 1px solid var(--border-color, #e5e7eb); border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 1000; padding: 0.5rem;
  animation: dropdownFadeIn 0.2s ease;
}
@keyframes dropdownFadeIn {
  from { opacity: 0; transform: translateY(-8px) scale(0.96); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.dropdown-header { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--border-color, #e5e7eb); margin-bottom: 0.25rem; }
.dropdown-title { font-size: 0.75rem; font-weight: 600; color: var(--text-muted, #6b7280); text-transform: uppercase; }
.dropdown-close { background: none; border: none; font-size: 1.25rem; color: var(--text-muted, #9ca3af); cursor: pointer; }
.dropdown-item {
  display: flex; align-items: center; gap: 0.75rem; width: 100%; padding: 0.65rem 0.75rem;
  border: none; background: none; cursor: pointer; font-size: 0.875rem;
  color: var(--text-primary, #374151); border-radius: 8px; text-align: left;
}
.dropdown-item:hover { background: var(--bg-subtle, #f3f4f6); }
.dropdown-item-danger { color: #dc2626; }
.dropdown-item-danger:hover { background: #fef2f2; }
.dropdown-divider { height: 1px; background: var(--border-color, #e5e7eb); margin: 0.25rem 0; }
.item-icon { width: 20px; text-align: center; flex-shrink: 0; }
</style>

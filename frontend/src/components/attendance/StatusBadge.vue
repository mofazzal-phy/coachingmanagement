<template>
  <span class="status-badge" :class="status">
    <span class="status-dot"></span>
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, default: 'present' },
  label: { type: String, default: '' },
})

const statusMap = {
  present: { label: 'Present', color: '#12b76a' },
  late: { label: 'Late', color: '#f79009' },
  absent: { label: 'Absent', color: '#f04438' },
  leave: { label: 'Leave', color: '#2563eb' },
  holiday: { label: 'Holiday', color: '#8b5cf6' },
}
</script>

<style scoped>
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.3px;
  white-space: nowrap;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}

.status-badge.present {
  background: #ecfdf3;
  color: #067647;
  border: 1px solid #abefc6;
}
.status-badge.present .status-dot { background: #12b76a; }

.status-badge.late {
  background: #fffaeb;
  color: #b54708;
  border: 1px solid #fedf89;
}
.status-badge.late .status-dot { background: #f79009; }

.status-badge.absent {
  background: #fef3f2;
  color: #b42318;
  border: 1px solid #fecdca;
}
.status-badge.absent .status-dot { background: #f04438; }

.status-badge.leave {
  background: #eff8ff;
  color: #175cd3;
  border: 1px solid #b2ddff;
}
.status-badge.leave .status-dot { background: #2563eb; }

.status-badge.holiday {
  background: #f4f3ff;
  color: #5925dc;
  border: 1px solid #d9d6fe;
}
.status-badge.holiday .status-dot { background: #8b5cf6; }
</style>

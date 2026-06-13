<template>
  <div class="simulator-card">
    <div class="simulator-header">
      <div class="simulator-title-row">
        <span class="simulator-icon">🔬</span>
        <div>
          <h4 class="simulator-title">Quick Simulator</h4>
          <p class="simulator-subtitle">No Device Required</p>
        </div>
      </div>
      <span class="simulator-badge">Demo</span>
    </div>

    <div class="simulator-body">
      <div class="simulator-field">
        <label class="simulator-label">Select User</label>
        <select v-model="selectedUser" class="simulator-select">
          <option value="">Choose a user...</option>
          <option v-for="user in users" :key="user.id" :value="user.id">
            {{ user.name }} ({{ user.type }})
          </option>
        </select>
      </div>

      <div class="simulator-field">
        <label class="simulator-label">Scan Mode</label>
        <div class="mode-options">
          <button
            v-for="mode in scanModes"
            :key="mode.value"
            class="mode-btn"
            :class="{ active: selectedMode === mode.value }"
            @click="selectedMode = mode.value"
          >
            {{ mode.icon }} {{ mode.label }}
          </button>
        </div>
      </div>

      <button class="simulate-btn" @click="$emit('simulate', { userId: selectedUser, mode: selectedMode })" :disabled="!selectedUser">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
          <path d="M8 1a7 7 0 100 14A7 7 0 008 1zM7 5l4 3-4 3V5z"/>
        </svg>
        Simulate Fingerprint Scan
      </button>
    </div>

    <div class="simulator-footer" v-if="recentScans.length">
      <div class="footer-title">Recent Simulations</div>
      <div class="scan-list">
        <div v-for="(scan, i) in recentScans.slice(0, 3)" :key="i" class="scan-item">
          <span class="scan-dot" :class="scan.status"></span>
          <span class="scan-name">{{ scan.name }}</span>
          <span class="scan-time">{{ scan.time }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  users: { type: Array, default: () => [] },
  recentScans: { type: Array, default: () => [] },
})

defineEmits(['simulate'])

const selectedUser = ref('')
const selectedMode = ref('fingerprint')

const scanModes = [
  { value: 'fingerprint', icon: '🖐️', label: 'Fingerprint' },
  { value: 'card', icon: '💳', label: 'Card/RFID' },
  { value: 'face', icon: '👤', label: 'Face' },
]
</script>

<style scoped>
.simulator-card {
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
  border: 1px solid #bae6fd;
  border-radius: 16px;
  overflow: hidden;
}

.simulator-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid rgba(186, 230, 253, 0.5);
}

.simulator-title-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.simulator-icon {
  font-size: 1.5rem;
  line-height: 1;
}

.simulator-title {
  font-size: 0.9rem;
  font-weight: 700;
  color: #0c4a6e;
  margin: 0;
}

.simulator-subtitle {
  font-size: 0.7rem;
  color: #0369a1;
  margin: 0;
}

.simulator-badge {
  font-size: 0.65rem;
  font-weight: 600;
  color: #0369a1;
  background: rgba(186, 230, 253, 0.6);
  padding: 3px 10px;
  border-radius: 20px;
}

.simulator-body {
  padding: 1rem 1.25rem;
}

.simulator-field {
  margin-bottom: 0.875rem;
}

.simulator-label {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  color: #0c4a6e;
  margin-bottom: 0.4rem;
}

.simulator-select {
  width: 100%;
  padding: 0.6rem 0.75rem;
  border: 1px solid #bae6fd;
  border-radius: 10px;
  font-size: 0.8rem;
  color: #0c4a6e;
  background: var(--bg-card);
  outline: none;
  transition: border-color 0.2s;
}

.simulator-select:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.mode-options {
  display: flex;
  gap: 0.5rem;
}

.mode-btn {
  flex: 1;
  padding: 0.5rem;
  border: 1px solid #bae6fd;
  border-radius: 10px;
  background: var(--bg-card);
  font-size: 0.75rem;
  color: #0c4a6e;
  cursor: pointer;
  transition: all 0.2s;
}

.mode-btn:hover {
  border-color: #2563eb;
}

.mode-btn.active {
  background: #2563eb;
  color: #fff;
  border-color: #2563eb;
}

.simulate-btn {
  width: 100%;
  padding: 0.7rem;
  border: none;
  border-radius: 10px;
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #fff;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: all 0.25s;
}

.simulate-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.simulate-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.simulator-footer {
  padding: 0.75rem 1.25rem;
  border-top: 1px solid rgba(186, 230, 253, 0.5);
}

.footer-title {
  font-size: 0.7rem;
  font-weight: 600;
  color: #0369a1;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 0.5rem;
}

.scan-list {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.scan-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.75rem;
  color: #0c4a6e;
}

.scan-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #d1d5db;
}

.scan-dot.present { background: #12b76a; }
.scan-dot.late { background: #f79009; }
.scan-dot.absent { background: #f04438; }

.scan-name {
  flex: 1;
  font-weight: 500;
}

.scan-time {
  color: var(--text-muted);
  font-size: 0.65rem;
}
</style>

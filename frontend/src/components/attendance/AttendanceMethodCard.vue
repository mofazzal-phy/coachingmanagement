<template>
  <div class="attendance-method-card" :class="[theme, { active: isActive }]">
    <div class="method-icon-wrapper">
      <!-- Manual Entry Icon -->
      <svg v-if="theme === 'manual'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
        <line x1="16" y1="17" x2="8" y2="17"></line>
        <polyline points="10 9 9 9 8 9"></polyline>
      </svg>
      <!-- Fingerprint Icon -->
      <svg v-else-if="theme === 'fingerprint'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 2a10 10 0 0 0-10 10c0 1.5.3 3 .9 4.3"></path>
        <path d="M7 22c.6-1.5 2-2.5 3.5-2.5h3c1.5 0 2.9 1 3.5 2.5"></path>
        <path d="M12 18a6 6 0 0 1-6-6c0-1.5.5-3 1.5-4a6 6 0 0 1 8.5 0c1 1 1.5 2.5 1.5 4"></path>
        <path d="M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"></path>
      </svg>
      <!-- QR Code Icon -->
      <svg v-else-if="theme === 'qrcode'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="7" height="7"></rect>
        <rect x="14" y="3" width="7" height="7"></rect>
        <rect x="14" y="14" width="7" height="7"></rect>
        <rect x="3" y="14" width="7" height="7"></rect>
        <line x1="7" y1="7" x2="7" y2="7.01"></line>
        <line x1="17" y1="7" x2="17" y2="7.01"></line>
        <line x1="17" y1="17" x2="17" y2="17.01"></line>
        <line x1="7" y1="17" x2="7" y2="17.01"></line>
      </svg>
      <!-- Card/RFID Icon -->
      <svg v-else-if="theme === 'card'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect>
        <line x1="2" y1="10" x2="22" y2="10"></line>
        <line x1="6" y1="15" x2="10" y2="15"></line>
      </svg>
    </div>
    <div class="method-info">
      <div class="method-name">{{ name }}</div>
      <div class="method-status">{{ statusText }}</div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  name: { type: String, default: '' },
  theme: { type: String, default: 'manual' }, // manual, fingerprint, qrcode, card
  isActive: { type: Boolean, default: false },
  statusText: { type: String, default: 'Ready' },
})
</script>

<style scoped>
.attendance-method-card {
  background: var(--bg-card);
  border: 1.5px solid #f1f5f9;
  border-radius: 12px;
  padding: 1.25rem 0.75rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: center;
}

.attendance-method-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);
}

/* Manual Entry (Green Theme) */
.attendance-method-card.manual {
  border-color: #d1fae5;
  background: #f6fef9;
}
.attendance-method-card.manual .method-icon-wrapper {
  background: #e6fbf0;
  color: #12b76a;
}
.attendance-method-card.manual .method-status {
  color: #12b76a;
}

/* Fingerprint (Blue Theme) */
.attendance-method-card.fingerprint {
  border-color: #dbeafe;
  background: #f0f7ff;
}
.attendance-method-card.fingerprint .method-icon-wrapper {
  background: #e0f0ff;
  color: #2563eb;
}
.attendance-method-card.fingerprint .method-status {
  color: #2563eb;
}

/* QR Code (Purple Theme) */
.attendance-method-card.qrcode {
  border-color: #f3e8ff;
  background: #faf5ff;
}
.attendance-method-card.qrcode .method-icon-wrapper {
  background: #f3e8ff;
  color: #8b5cf6;
}
.attendance-method-card.qrcode .method-status {
  color: #8b5cf6;
}

/* Card/RFID (Orange Theme) */
.attendance-method-card.card {
  border-color: #ffedd5;
  background: #fffaf0;
}
.attendance-method-card.card .method-icon-wrapper {
  background: #ffedd5;
  color: #f97316;
}
.attendance-method-card.card .method-status {
  color: #f97316;
}

.method-icon-wrapper {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.2s;
}

.method-name {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-dark);
  margin-bottom: 0.15rem;
}

.method-status {
  font-size: 0.72rem;
  font-weight: 600;
}
</style>

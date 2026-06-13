<template>
  <div class="activity-card">
    <div class="activity-header">
      <h4 class="activity-title">Recent Activity</h4>
    </div>
    <div class="activity-list">
      <div v-for="(act, i) in activities.slice(0, 4)" :key="i" class="activity-item">
        <div class="activity-dot-wrapper">
          <span class="activity-dot" :class="act.status || 'present'"></span>
          <div class="activity-line" v-if="i < Math.min(activities.length, 4) - 1"></div>
        </div>
        <div class="activity-content">
          <span class="activity-time">{{ act.time }}</span>
          <span class="activity-name">{{ act.name }}</span>
          <span class="activity-badge" :class="act.status || 'present'">{{ act.status || 'Present' }}</span>
        </div>
      </div>
      <div v-if="!activities.length" class="activity-empty">
        <span class="empty-icon">📭</span>
        <p>No recent activity</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  activities: { type: Array, default: () => [] },
})
</script>

<style scoped>
.activity-card {
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
}

.activity-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #eef2f6;
}

.activity-title {
  font-size: 0.92rem;
  font-weight: 800;
  color: var(--text-dark);
  margin: 0;
}

.activity-list {
  padding: 1rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0px;
}

.activity-item {
  display: flex;
  gap: 1rem;
  height: 48px;
}

.activity-dot-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 16px;
  flex-shrink: 0;
  height: 100%;
}

.activity-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #12b76a;
  border: 2px solid #fff;
  box-shadow: 0 0 0 2px #e6fbf0;
  flex-shrink: 0;
  margin-top: 4px;
}

.activity-dot.present { background: #12b76a; box-shadow: 0 0 0 2px #e6fbf0; }
.activity-dot.late { background: #f79009; box-shadow: 0 0 0 2px #ffedd5; }
.activity-dot.absent { background: #f04438; box-shadow: 0 0 0 2px #fee2e2; }
.activity-dot.leave { background: #2563eb; box-shadow: 0 0 0 2px #dbeafe; }

.activity-line {
  width: 2px;
  flex: 1;
  background: #12b76a;
  opacity: 0.4;
  margin: 4px 0;
}

.activity-content {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 1rem;
  font-size: 0.8rem;
}

.activity-time {
  font-weight: 600;
  color: var(--text-muted);
  width: 65px;
}

.activity-name {
  font-weight: 700;
  color: var(--text-dark);
  flex: 1;
}

.activity-badge {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 12px;
  text-transform: capitalize;
}

.activity-badge.present {
  background: #e6fbf0;
  color: #12b76a;
}

.activity-badge.late {
  background: #fff8eb;
  color: #f79009;
}

.activity-badge.absent {
  background: #fef2f2;
  color: #f04438;
}

.activity-badge.leave {
  background: #f0f7ff;
  color: #2563eb;
}

.activity-empty {
  text-align: center;
  padding: 1.5rem;
  color: var(--text-muted);
}

.activity-empty .empty-icon {
  font-size: 1.5rem;
  display: block;
  margin-bottom: 0.5rem;
}

.activity-empty p {
  font-size: 0.8rem;
  margin: 0;
}
</style>

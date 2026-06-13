<template>
  <div class="alert-card">
    <div class="alert-header">
      <h4 class="alert-title">Low Attendance Alert</h4>
      <span class="alert-count">{{ students.length }} Students</span>
    </div>
    <div class="alert-body">
      <div v-for="(student, index) in students.slice(0, 5)" :key="student.id" class="alert-student">
        <div class="alert-student-name-col">
          <span class="student-num">{{ index + 1 }}.</span>
          <span class="student-name">{{ student.name }}</span>
        </div>
        <div class="alert-progress-col">
          <div class="alert-progress-bar">
            <div
              class="alert-progress-fill"
              :style="{ width: student.percentage + '%' }"
            ></div>
          </div>
        </div>
        <span class="alert-progress-text">
          {{ student.percentage }}%
        </span>
      </div>
      <div v-if="!students.length" class="alert-empty">
        <span class="empty-icon">✅</span>
        <p>All students above threshold</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  students: { type: Array, default: () => [] },
})
</script>

<style scoped>
.alert-card {
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
}

.alert-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #eef2f6;
}

.alert-title {
  font-size: 0.92rem;
  font-weight: 800;
  color: var(--text-dark);
  margin: 0;
}

.alert-count {
  font-size: 0.72rem;
  font-weight: 700;
  color: #ef4444;
  background: #fef2f2;
  padding: 4px 10px;
  border-radius: 20px;
}

.alert-body {
  padding: 1rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.alert-student {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.alert-student-name-col {
  width: 140px;
  display: flex;
  gap: 0.35rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-secondary);
  flex-shrink: 0;
}

.student-num {
  color: var(--text-muted);
}

.student-name {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.alert-progress-col {
  flex: 1;
}

.alert-progress-bar {
  height: 6px;
  background: var(--bg-accent);
  border-radius: 10px;
  overflow: hidden;
  width: 100%;
}

.alert-progress-fill {
  height: 100%;
  background: #ef4444;
  border-radius: 10px;
}

.alert-progress-text {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-dark);
  width: 38px;
  text-align: right;
  flex-shrink: 0;
}

.alert-empty {
  text-align: center;
  padding: 1.5rem;
  color: var(--text-muted);
}

.alert-empty .empty-icon {
  font-size: 1.5rem;
  display: block;
  margin-bottom: 0.4rem;
}

.alert-empty p {
  font-size: 0.8rem;
  margin: 0;
}
</style>

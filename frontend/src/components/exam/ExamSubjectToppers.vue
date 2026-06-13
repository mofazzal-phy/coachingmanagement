<template>
  <div class="exam-subject-toppers">
    <h4 class="st-title">Subject-wise toppers</h4>
    <p class="st-hint">Highest marks per subject among students in the current scope.</p>

    <div v-if="!toppers.length" class="st-empty">No subject toppers yet.</div>

    <div v-else class="st-table-wrap">
      <table class="st-table">
        <thead>
          <tr>
            <th>Subject</th>
            <th>Topper</th>
            <th>Roll</th>
            <th>Marks</th>
            <th>%</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in toppers" :key="row.subject_id">
            <td>
              <strong>{{ row.subject_name }}</strong>
              <span v-if="row.subject_code" class="st-code">{{ row.subject_code }}</span>
            </td>
            <td>{{ row.topper_name || '—' }}</td>
            <td>{{ row.topper_roll || '—' }}</td>
            <td>{{ row.highest_marks }}/{{ row.total_marks }}</td>
            <td>{{ row.percentage }}%</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
defineProps({
  toppers: { type: Array, default: () => [] },
})
</script>

<style scoped>
.exam-subject-toppers {
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid #e2e8f0;
}

.st-title {
  margin: 0 0 0.25rem;
  font-size: 0.92rem;
  color: var(--text-dark);
}

.st-hint {
  margin: 0 0 0.65rem;
  font-size: 0.76rem;
  color: var(--text-muted);
}

.st-empty {
  padding: 0.75rem 1rem;
  border-radius: 8px;
  background: var(--bg-surface-muted);
  color: var(--text-muted);
  font-size: 0.82rem;
}

.st-table-wrap {
  overflow-x: auto;
  border: 1px solid var(--border-color);
  border-radius: 8px;
}

.st-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
}

.st-table th {
  background: var(--bg-surface-muted);
  color: var(--text-secondary);
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 0.5rem 0.65rem;
  text-align: left;
}

.st-table td {
  padding: 0.5rem 0.65rem;
  border-top: 1px solid var(--border-light);
}

.st-code {
  margin-left: 0.35rem;
  font-size: 0.72rem;
  color: var(--text-muted);
}
</style>

<template>
  <section class="hero">
    <h1>Premium Admin Dashboard</h1>
    <p>Welcome back, {{ user?.name || 'Admin' }}. Here is a quick system overview.</p>
  </section>

  <section class="stats-grid">
    <article class="stat-card">
      <p>Total Students</p>
      <h2>1,248</h2>
    </article>
    <article class="stat-card">
      <p>Active Courses</p>
      <h2>42</h2>
    </article>
    <article class="stat-card">
      <p>Attendance Today</p>
      <h2>92%</h2>
    </article>
    <article class="stat-card">
      <p>Pending Approvals</p>
      <h2>18</h2>
    </article>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth.store'

const authStore = useAuthStore()
const user = computed(() => authStore.user)
</script>

<style scoped>
.hero {
  background: linear-gradient(135deg, #312e81, #1d4ed8);
  color: #fff;
  padding: 1.3rem;
  border-radius: 12px;
  margin-bottom: 1rem;
}

.hero p {
  margin-top: 0.35rem;
  opacity: 0.95;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.9rem;
}

.stat-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1rem;
  border: 1px solid var(--border-color);
}

.stat-card p {
  color: var(--text-muted);
}

.stat-card h2 {
  margin-top: 0.4rem;
}
</style>
<template>
  <DashboardPanel
    :title="panelTitle"
    :subtitle="panelSubtitle"
    icon="🧩"
    :loading="loading"
  >
    <div v-if="!items.length && !loading" class="summary-empty">
      No content modules available for your permissions.
    </div>
    <div v-else class="summary-grid">
      <router-link
        v-for="item in items"
        :key="item.key"
        :to="item.to"
        class="summary-card"
        :style="{ '--accent': item.color }"
      >
        <span class="summary-icon">{{ item.icon }}</span>
        <div class="summary-body">
          <strong>{{ item.label }}</strong>
          <span class="summary-meta">{{ item.meta }}</span>
        </div>
        <span class="summary-value">{{ item.value }}</span>
      </router-link>
    </div>
  </DashboardPanel>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import dashboardService from '@/services/dashboard.service'
import DashboardPanel from './DashboardPanel.vue'

const props = defineProps({
  portal: {
    type: String,
    default: 'admin',
    validator: (v) => ['admin', 'teacher', 'student', 'employee', 'guardian'].includes(v),
  },
})

const authStore = useAuthStore()
const loading = ref(false)
const items = ref([])

const panelTitle = computed(() => {
  const titles = {
    admin: 'CMS & Content Hub',
    teacher: 'Your Content Access',
    student: 'Learning Resources',
    employee: 'Resources & Notices',
    guardian: 'Family Portal Content',
  }
  return titles[props.portal] || 'Content Summary'
})

const panelSubtitle = computed(() => {
  const subs = {
    admin: 'Permission-based snapshot of CMS modules',
    teacher: 'Materials and notices you can access',
    student: 'Study materials, downloads, and notices',
    employee: 'Notices, events, and public downloads',
    guardian: 'Notices and downloads for your family',
  }
  return subs[props.portal] || 'Based on your permissions'
})

const loadSummary = async () => {
  loading.value = true
  try {
    items.value = await dashboardService.fetchPortalCmsSummary(
      (p) => authStore.hasPermission(p),
      props.portal,
    )
  } finally {
    loading.value = false
  }
}

onMounted(loadSummary)
</script>

<style scoped>
:deep(.dash-panel) {
  margin-bottom: 1.25rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.65rem;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.85rem;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  text-decoration: none;
  color: inherit;
  transition: all 0.2s;
}

.summary-card:hover {
  border-color: var(--accent);
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
  transform: translateY(-1px);
  background: var(--bg-card-hover);
}

.summary-icon { font-size: 1.3rem; flex-shrink: 0; }

.summary-body {
  flex: 1;
  min-width: 0;
}

.summary-body strong {
  display: block;
  font-size: 0.82rem;
  color: var(--text-primary);
}

.summary-meta {
  display: block;
  font-size: 0.68rem;
  color: var(--text-muted);
  margin-top: 0.1rem;
}

.summary-value {
  font-size: 1rem;
  font-weight: 800;
  color: var(--accent);
  flex-shrink: 0;
}

.summary-empty {
  text-align: center;
  padding: 1.5rem;
  color: var(--text-muted);
  font-size: 0.85rem;
}
</style>

<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>CMS Analytics</h1>
        <span class="badge-count">Last {{ dashboard.period_days || 30 }} days</span>
      </div>
      <div class="header-actions">
        <select v-model="periodDays" class="form-control period-select" @change="loadDashboard">
          <option :value="7">7 days</option>
          <option :value="30">30 days</option>
          <option :value="90">90 days</option>
        </select>
        <button class="btn btn-outline" @click="loadDashboard" :disabled="loading">Refresh</button>
      </div>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading analytics...</p></div>
    <div v-else-if="error" class="error-state"><p>{{ error }}</p></div>
    <template v-else>
      <div class="stats-grid">
        <div class="stat-card stat-views">
          <div class="stat-icon">👁️</div>
          <div class="stat-body">
            <span class="stat-label">Total Views</span>
            <span class="stat-value">{{ formatNum(dashboard.total_views) }}</span>
          </div>
        </div>
        <div class="stat-card stat-downloads">
          <div class="stat-icon">📥</div>
          <div class="stat-body">
            <span class="stat-label">Total Downloads</span>
            <span class="stat-value">{{ formatNum(dashboard.total_downloads) }}</span>
          </div>
        </div>
        <div class="stat-card stat-engagement">
          <div class="stat-icon">⚡</div>
          <div class="stat-body">
            <span class="stat-label">Total Engagement</span>
            <span class="stat-value">{{ formatNum(totalEngagement) }}</span>
          </div>
        </div>
        <div class="stat-card stat-types">
          <div class="stat-icon">📂</div>
          <div class="stat-body">
            <span class="stat-label">Active Content Types</span>
            <span class="stat-value">{{ (dashboard.by_content_type || []).length }}</span>
          </div>
        </div>
      </div>

      <div class="charts-grid">
        <CmsChartCard
          title="Engagement Overview"
          subtitle="Views vs downloads share"
          badge="Pie Chart"
          type="pie"
          size="md"
          :empty="!hasEngagement"
          :data="overviewChartData"
          :options="pieChartOptions"
        />

        <CmsChartCard
          title="Trend by Content Type"
          subtitle="Views & downloads across CMS modules"
          badge="Line Chart"
          type="line"
          size="lg"
          :empty="!dashboard.by_content_type?.length"
          :data="typeLineChartData"
          :options="lineChartOptions"
        />
      </div>

      <div class="charts-grid charts-grid--split">
        <CmsChartCard
          title="Top Viewed Content"
          subtitle="Performance radar of leading pages"
          badge="Radar Chart"
          type="radar"
          size="lg"
          :empty="!dashboard.top_views?.length"
          :data="topViewsRadarData"
          :options="radarChartOptions"
        />

        <CmsChartCard
          title="Top Downloaded Content"
          subtitle="Download share among top resources"
          badge="Polar Area"
          type="polarArea"
          size="lg"
          :empty="!dashboard.top_downloads?.length"
          :data="topDownloadsPolarData"
          :options="polarChartOptions"
        />
      </div>

      <div class="insight-panel" v-if="hasEngagement">
        <h3>Quick Insights</h3>
        <div class="insight-grid">
          <div class="insight-item">
            <span class="insight-label">Most viewed type</span>
            <strong>{{ topViewedType || '—' }}</strong>
          </div>
          <div class="insight-item">
            <span class="insight-label">Most downloaded type</span>
            <strong>{{ topDownloadedType || '—' }}</strong>
          </div>
          <div class="insight-item">
            <span class="insight-label">Download rate</span>
            <strong>{{ downloadRate }}%</strong>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import cmsService from '@/services/cms.service'
import CmsChartCard from '@/components/cms/CmsChartCard.vue'

const loading = ref(false)
const error = ref(null)
const periodDays = ref(30)
const dashboard = ref({})

const CHART_COLORS = {
  views: '#3b82f6',
  viewsFill: 'rgba(59, 130, 246, 0.18)',
  downloads: '#10b981',
  downloadsFill: 'rgba(16, 185, 129, 0.18)',
  palette: ['#6366f1', '#3b82f6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'],
  paletteSoft: [
    'rgba(99, 102, 241, 0.75)',
    'rgba(59, 130, 246, 0.75)',
    'rgba(6, 182, 212, 0.75)',
    'rgba(16, 185, 129, 0.75)',
    'rgba(245, 158, 11, 0.75)',
    'rgba(239, 68, 68, 0.75)',
    'rgba(139, 92, 246, 0.75)',
    'rgba(236, 72, 153, 0.75)',
  ],
}

const totalEngagement = computed(() =>
  (dashboard.value.total_views || 0) + (dashboard.value.total_downloads || 0)
)

const hasEngagement = computed(() => totalEngagement.value > 0)

const downloadRate = computed(() => {
  const views = dashboard.value.total_views || 0
  const downloads = dashboard.value.total_downloads || 0
  const total = views + downloads
  if (!total) return '0.0'
  return ((downloads / total) * 100).toFixed(1)
})

const topViewedType = computed(() => {
  const rows = dashboard.value.by_content_type || []
  if (!rows.length) return null
  const top = [...rows].sort((a, b) => b.views - a.views)[0]
  return formatType(top.content_type)
})

const topDownloadedType = computed(() => {
  const rows = dashboard.value.by_content_type || []
  if (!rows.length) return null
  const top = [...rows].sort((a, b) => b.downloads - a.downloads)[0]
  return formatType(top.content_type)
})

const overviewChartData = computed(() => ({
  labels: ['Views', 'Downloads'],
  datasets: [{
    data: [dashboard.value.total_views || 0, dashboard.value.total_downloads || 0],
    backgroundColor: [CHART_COLORS.views, CHART_COLORS.downloads],
    borderColor: '#ffffff',
    borderWidth: 3,
    hoverOffset: 12,
  }],
}))

const pieChartOptions = {
  plugins: {
    legend: { position: 'bottom' },
  },
}

const typeLineChartData = computed(() => {
  const rows = dashboard.value.by_content_type || []
  return {
    labels: rows.map((r) => truncate(formatType(r.content_type), 12)),
    datasets: [
      {
        label: 'Views',
        data: rows.map((r) => r.views),
        borderColor: CHART_COLORS.views,
        backgroundColor: CHART_COLORS.viewsFill,
        borderWidth: 2.5,
        pointRadius: 5,
        pointHoverRadius: 7,
        pointBackgroundColor: CHART_COLORS.views,
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        fill: true,
        tension: 0.4,
      },
      {
        label: 'Downloads',
        data: rows.map((r) => r.downloads),
        borderColor: CHART_COLORS.downloads,
        backgroundColor: CHART_COLORS.downloadsFill,
        borderWidth: 2.5,
        pointRadius: 5,
        pointHoverRadius: 7,
        pointBackgroundColor: CHART_COLORS.downloads,
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        fill: true,
        tension: 0.4,
      },
    ],
  }
})

const lineChartOptions = {
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: { position: 'top' },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: '#9ca3af', font: { size: 11 }, maxRotation: 45 },
    },
    y: {
      beginAtZero: true,
      grid: { color: 'rgba(148, 163, 184, 0.15)' },
      ticks: { color: '#9ca3af', precision: 0 },
    },
  },
}

const sliceTopItems = (items, limit = 8) => (items || []).slice(0, limit)

const topViewsRadarData = computed(() => {
  const items = sliceTopItems(dashboard.value.top_views)
  return {
    labels: items.map((item) => truncate(item.title || formatType(item.content_type), 18)),
    datasets: [{
      label: 'Views',
      data: items.map((item) => item.total),
      borderColor: CHART_COLORS.views,
      backgroundColor: 'rgba(59, 130, 246, 0.22)',
      borderWidth: 2,
      pointBackgroundColor: CHART_COLORS.views,
      pointBorderColor: '#fff',
      pointHoverBackgroundColor: '#1d4ed8',
      pointRadius: 4,
    }],
  }
})

const radarChartOptions = {
  plugins: {
    legend: { display: false },
  },
  scales: {
    r: {
      beginAtZero: true,
      angleLines: { color: 'rgba(148, 163, 184, 0.2)' },
      grid: { color: 'rgba(148, 163, 184, 0.15)' },
      pointLabels: {
        color: '#6b7280',
        font: { size: 10 },
      },
      ticks: {
        display: false,
        backdropColor: 'transparent',
      },
    },
  },
}

const topDownloadsPolarData = computed(() => {
  const items = sliceTopItems(dashboard.value.top_downloads)
  return {
    labels: items.map((item) => truncate(item.title || formatType(item.content_type), 18)),
    datasets: [{
      data: items.map((item) => item.total),
      backgroundColor: CHART_COLORS.paletteSoft.slice(0, items.length),
      borderColor: '#ffffff',
      borderWidth: 2,
    }],
  }
})

const polarChartOptions = {
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        boxWidth: 10,
        font: { size: 10 },
      },
    },
  },
  scales: {
    r: {
      beginAtZero: true,
      grid: { color: 'rgba(148, 163, 184, 0.12)' },
      ticks: {
        display: false,
        backdropColor: 'transparent',
      },
    },
  },
}

const loadDashboard = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await cmsService.foundation.analyticsDashboard({ days: periodDays.value })
    dashboard.value = res.data?.data || {}
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load analytics.'
  } finally {
    loading.value = false
  }
}

const formatType = (t) => (t ? t.replace(/_/g, ' ') : '—')
const formatNum = (n) => Number(n || 0).toLocaleString()
const truncate = (text, max) => {
  const value = String(text || '')
  return value.length > max ? `${value.slice(0, max - 1)}…` : value
}

onMounted(loadDashboard)
</script>

<style scoped>
.page-container { max-width: 1200px; margin: 0 auto; padding: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem; }
.header-left { display: flex; align-items: center; gap: 0.75rem; }
.header-left h1 { margin: 0; font-size: 1.4rem; }
.badge-count { background: #f3f4f6; color: #6b7280; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.8rem; }
.header-actions { display: flex; gap: 0.5rem; align-items: center; }
.period-select { min-width: 120px; }

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  background: var(--bg-card, #fff);
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 14px;
  padding: 1.1rem 1.2rem;
  box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
}

.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.stat-views .stat-icon { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }
.stat-downloads .stat-icon { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
.stat-engagement .stat-icon { background: linear-gradient(135deg, #ede9fe, #ddd6fe); }
.stat-types .stat-icon { background: linear-gradient(135deg, #ffedd5, #fed7aa); }

.stat-body { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.stat-label { font-size: 0.75rem; color: var(--text-muted, #6b7280); text-transform: uppercase; letter-spacing: 0.04em; }
.stat-value { font-size: 1.55rem; font-weight: 800; color: var(--text-primary, #111827); line-height: 1.1; }

.charts-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.charts-grid--split {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.insight-panel {
  background: linear-gradient(135deg, #f8fafc, #eef2ff);
  border: 1px solid #e0e7ff;
  border-radius: 14px;
  padding: 1.1rem 1.25rem;
}

.insight-panel h3 {
  margin: 0 0 0.85rem;
  font-size: 0.95rem;
}

.insight-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.75rem;
}

.insight-item {
  background: rgba(255, 255, 255, 0.75);
  border-radius: 10px;
  padding: 0.75rem 0.9rem;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.insight-label {
  font-size: 0.75rem;
  color: #6b7280;
}

.insight-item strong {
  font-size: 0.95rem;
  color: #1e293b;
}

.loading-state, .error-state {
  text-align: center;
  padding: 3rem;
  background: var(--bg-card, #fff);
  border-radius: 12px;
}

.form-control { padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; background: var(--bg-card, #fff); }
.btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; }
.btn-outline { background: transparent; border: 1px solid #d1d5db; }
.spinner { width: 32px; height: 32px; border: 3px solid #e5e7eb; border-top-color: #2563eb; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 900px) {
  .charts-grid,
  .charts-grid--split {
    grid-template-columns: 1fr;
  }
}
</style>

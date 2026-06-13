<template>
  <div class="audit-page">
    <h1>📋 Activity Logs</h1>

    <div class="filters-bar">
      <select v-model="filters.model_type" @change="loadLogs(1)">
        <option value="">All Models</option>
        <option value="course">Course</option>
        <option value="batch">Batch</option>
        <option value="enrollment">Enrollment</option>
      </select>
      <select v-model="filters.action" @change="loadLogs(1)">
        <option value="">All Actions</option>
        <option value="created">Created</option>
        <option value="updated">Updated</option>
        <option value="deleted">Deleted</option>
        <option value="restored">Restored</option>
        <option value="full">Seats Full</option>
        <option value="starting_soon">Starting Soon</option>
      </select>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div></div>

    <div v-else-if="logs.length === 0" class="empty-state"><p>No activity logs found</p></div>

    <div v-else class="log-list">
      <div v-for="log in logs" :key="log.id" class="log-row">
        <span :class="['action-icon', log.action]">
          {{ log.action === 'created' ? '➕' : log.action === 'deleted' ? '🗑' : log.action === 'full' ? '🔴' : '✏️' }}
        </span>
        <div class="log-body">
          <p class="log-desc">{{ log.description }}</p>
          <div class="log-meta">
            <span>{{ log.model_type || 'enrollment' }}</span>
            <span v-if="log.performer">· {{ log.performer.name }}</span>
            <span>· {{ log.ip_address }}</span>
            <span>· {{ log.created_at?.slice(0,16) || '—' }}</span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="pagination" class="pagination-footer"><pagination :data="pagination" @change="loadLogs" /></div>
  </div>
</template>

<script>
import enrollmentService from '@/services/enrollment.service';

export default {
  name: 'ActivityLogPage',
  data() {
    return {
      logs: [], loading: false, pagination: null,
      filters: { model_type: '', action: '' },
    };
  },
  created() { this.loadLogs(); },
  methods: {
    async loadLogs(page = 1) {
      this.loading = true;
      try {
        const r = await enrollmentService.getAuditLogs({ page, per_page: 25, ...this.filters });
        this.logs = r.data?.data || r.data || [];
        this.pagination = r.data?.meta || null;
      } catch {} finally { this.loading = false; }
    },
  },
};
</script>

<style scoped>
.audit-page { max-width: 950px; }
.audit-page h1 { font-size: 1.3rem; margin: 0 0 1rem 0; }

.filters-bar { display: flex; gap: 0.75rem; margin-bottom: 1rem; }
.filters-bar select { padding: 0.5rem 0.9rem; border: 1px solid #d0d5dd; border-radius: 8px; font-size: 0.85rem; background: var(--bg-card); cursor: pointer; }

.log-list { background: var(--bg-card); border-radius: 14px; box-shadow: 0 1px 6px rgba(0,0,0,0.04); overflow: hidden; }
.log-row { display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.7rem 1rem; border-bottom: 1px solid #f0f1f3; font-size: 0.85rem; }
.log-row:last-child { border-bottom: none; }

.action-icon { font-size: 1rem; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 8px; flex-shrink: 0; margin-top: 2px; }
.action-icon.created { background: #ecfdf5; }
.action-icon.updated { background: #eff8ff; }
.action-icon.deleted { background: #fef2f2; }
.action-icon.full { background: #fef2f2; }

.log-body { flex: 1; }
.log-desc { margin: 0; color: #344054; font-size: 0.87rem; }
.log-meta { margin-top: 3px; font-size: 0.7rem; color: var(--text-muted); display: flex; gap: 0.25rem; flex-wrap: wrap; }

.loading-state, .empty-state { text-align: center; padding: 3rem; }
.spinner { width: 32px; height: 32px; border: 3px solid #eee; border-top-color: #4a90d9; border-radius: 50%; animation: spin 0.7s linear infinite; margin: 0 auto; }
@keyframes spin { to { transform: rotate(360deg); } }
.pagination-footer { margin-top: 1rem; display: flex; justify-content: center; }
</style>

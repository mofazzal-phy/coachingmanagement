<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>CMS Audit Logs</h1>
        <span class="badge-count">{{ meta.total || 0 }} entries</span>
      </div>
      <button class="btn btn-outline" @click="loadLogs" :disabled="loading">Refresh</button>
    </div>

    <div class="filter-bar">
      <select v-model="filters.entity_type" class="form-control filter-select" @change="loadLogs(1)">
        <option value="">All Entity Types</option>
        <option value="pages">Pages</option>
        <option value="galleries">Galleries</option>
        <option value="testimonials">Testimonials</option>
        <option value="success_stories">Success Stories</option>
        <option value="study_materials">Study Materials</option>
        <option value="download_resources">Downloads</option>
        <option value="notice_boards">Notices</option>
      </select>
      <select v-model="filters.action" class="form-control filter-select" @change="loadLogs(1)">
        <option value="">All Actions</option>
        <option value="created">Created</option>
        <option value="updated">Updated</option>
        <option value="deleted">Deleted</option>
        <option value="submit_for_review">Submitted</option>
        <option value="approve">Approved</option>
        <option value="reject">Rejected</option>
        <option value="auto_publish">Auto Published</option>
      </select>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading audit logs...</p></div>
    <div v-else-if="error" class="error-state"><p>{{ error }}</p></div>
    <div v-else-if="logs.length === 0" class="empty-state"><p>No audit logs found.</p></div>
    <div v-else class="log-list">
      <div v-for="log in logs" :key="log.id" class="log-row">
        <span class="action-icon" :class="log.action">{{ actionIcon(log.action) }}</span>
        <div class="log-body">
          <p class="log-desc">{{ log.description || formatAction(log.action) }}</p>
          <div class="log-meta">
            <span class="entity-tag">{{ log.entity_type }}</span>
            <span v-if="log.performer">· {{ log.performer.name }}</span>
            <span>· {{ formatDate(log.created_at) }}</span>
          </div>
          <div v-if="hasChanges(log)" class="log-changes">
            <details>
              <summary>View changes</summary>
              <pre>{{ formatChanges(log) }}</pre>
            </details>
          </div>
        </div>
      </div>
    </div>

    <div v-if="meta.last_page > 1" class="pagination">
      <button class="btn btn-outline btn-sm" :disabled="meta.current_page <= 1" @click="loadLogs(meta.current_page - 1)">Previous</button>
      <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button class="btn btn-outline btn-sm" :disabled="meta.current_page >= meta.last_page" @click="loadLogs(meta.current_page + 1)">Next</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import cmsService from '@/services/cms.service'

const logs = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const error = ref(null)
const filters = ref({ entity_type: '', action: '' })

const loadLogs = async (page = 1) => {
  loading.value = true
  error.value = null
  try {
    const params = { page, per_page: 25 }
    if (filters.value.entity_type) params.entity_type = filters.value.entity_type
    if (filters.value.action) params.action = filters.value.action
    const res = await cmsService.foundation.auditLogs(params)
    logs.value = cmsService.extractList(res)
    meta.value = res.data?.meta || { current_page: 1, last_page: 1, total: logs.value.length }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load audit logs.'
  } finally {
    loading.value = false
  }
}

const formatDate = (d) => d ? new Date(d).toLocaleString() : '—'
const formatAction = (a) => a ? a.replace(/_/g, ' ') : 'action'
const actionIcon = (a) => ({
  created: '➕', updated: '✏️', deleted: '🗑️', approve: '✅', reject: '❌', submit_for_review: '📤', auto_publish: '🚀',
}[a] || '•')
const hasChanges = (log) => log.old_values || log.new_values
const formatChanges = (log) => JSON.stringify({ old: log.old_values, new: log.new_values }, null, 2)

onMounted(() => loadLogs())
</script>

<style scoped>
.page-container { max-width: 950px; margin: 0 auto; padding: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.header-left { display: flex; align-items: center; gap: 0.75rem; }
.header-left h1 { margin: 0; font-size: 1.4rem; }
.badge-count { background: #f3f4f6; color: #6b7280; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.8rem; }
.filter-bar { display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.filter-select { min-width: 180px; }
.log-list { background: var(--bg-card); border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; }
.log-row { display: flex; gap: 0.75rem; padding: 1rem; border-bottom: 1px solid #f0f1f3; }
.log-row:last-child { border-bottom: none; }
.action-icon { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #f3f4f6; flex-shrink: 0; }
.action-icon.approve { background: #ecfdf5; }
.action-icon.reject { background: #fef2f2; }
.action-icon.created { background: #ecfdf5; }
.action-icon.deleted { background: #fef2f2; }
.log-body { flex: 1; min-width: 0; }
.log-desc { margin: 0 0 0.35rem; font-weight: 500; }
.log-meta { font-size: 0.8rem; color: var(--text-muted); display: flex; gap: 0.25rem; flex-wrap: wrap; }
.entity-tag { background: #e0e7ff; color: #3730a3; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.7rem; }
.log-changes { margin-top: 0.5rem; }
.log-changes pre { font-size: 0.7rem; background: #f9fafb; padding: 0.5rem; border-radius: 6px; overflow: auto; max-height: 120px; }
.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 1rem; }
.loading-state, .error-state, .empty-state { text-align: center; padding: 3rem; background: var(--bg-card); border-radius: 12px; }
.form-control { padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; background: var(--bg-card); }
.btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; }
.btn-outline { background: transparent; border: 1px solid #d1d5db; }
.btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; }
.spinner { width: 32px; height: 32px; border: 3px solid #e5e7eb; border-top-color: #2563eb; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>

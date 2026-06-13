<template>
  <div class="page-container">
    <div class="page-header">
      <h1>📢 Notice Board</h1>
      <p class="text-muted">View all published notices and announcements</p>
    </div>

    <div v-if="loading" class="loading-state">
      <ProgressSpinner />
      <p>Loading notices...</p>
    </div>

    <div v-else-if="error" class="error-state">
      <Message severity="error" :closable="false">{{ error }}</Message>
    </div>

    <template v-else>
      <div v-if="notices.length === 0" class="empty-state">
        <i class="pi pi-inbox empty-icon"></i>
        <h3>No Notices Available</h3>
        <p>There are no published notices at this time.</p>
      </div>

      <div v-else class="notices-list">
        <div v-for="notice in notices" :key="notice.id" class="notice-card" :class="getPriorityClass(notice.priority)">
          <div class="notice-header">
            <div class="notice-title-area">
              <span class="priority-badge" :class="notice.priority?.toLowerCase()">
                {{ notice.priority || 'Normal' }}
              </span>
              <h3>{{ notice.title }}</h3>
            </div>
            <span class="notice-date">{{ formatDate(notice.publish_date || notice.created_at) }}</span>
          </div>
          <div class="notice-body">
            <p>{{ notice.description || notice.content }}</p>
          </div>
          <div class="notice-footer" v-if="notice.attachment_url">
            <a :href="notice.attachment_url" target="_blank" class="attachment-link">
              <i class="pi pi-paperclip"></i> View Attachment
            </a>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import studentPortalService from '@/services/student-portal.service'

export default {
  name: 'StudentNoticesPage',
  setup() {
    const loading = ref(false)
    const error = ref(null)
    const notices = ref([])

    const loadNotices = async () => {
      loading.value = true
      error.value = null
      try {
        const res = await studentPortalService.notices()
        notices.value = res.data?.data || []
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load notices.'
      } finally {
        loading.value = false
      }
    }

    const getPriorityClass = (notice) => {
      const priority = notice.priority?.toLowerCase()
      if (priority === 'urgent' || priority === 'high') return 'notice-urgent'
      if (priority === 'medium') return 'notice-medium'
      return ''
    }

    const formatDate = (date) => {
      if (!date) return ''
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric'
      })
    }

    onMounted(loadNotices)

    return { loading, error, notices, getPriorityClass, formatDate }
  }
}
</script>

<style scoped>
.page-container {
  max-width: 900px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 1.5rem;
}

.page-header h1 {
  margin: 0 0 0.25rem;
  font-size: 1.5rem;
}

.text-muted { color: var(--text-muted); font-size: 0.9rem; margin: 0; }

.notices-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.notice-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  border-left: 4px solid #e5e7eb;
  transition: box-shadow 0.2s;
}

.notice-card:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}

.notice-card.notice-urgent {
  border-left-color: #dc2626;
}

.notice-card.notice-medium {
  border-left-color: #f59e0b;
}

.notice-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.75rem;
  gap: 1rem;
}

.notice-title-area {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.notice-title-area h3 {
  margin: 0;
  font-size: 1.05rem;
  color: var(--text-primary);
}

.priority-badge {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
}

.priority-badge.urgent, .priority-badge.high {
  background: #fee2e2;
  color: #dc2626;
}

.priority-badge.medium {
  background: #fef3c7;
  color: #d97706;
}

.priority-badge.normal, .priority-badge.low {
  background: #e0e7ff;
  color: #4f46e5;
}

.notice-date {
  font-size: 0.8rem;
  color: var(--text-muted);
  white-space: nowrap;
}

.notice-body p {
  margin: 0;
  font-size: 0.9rem;
  color: var(--text-secondary);
  line-height: 1.6;
  white-space: pre-line;
}

.notice-footer {
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px solid #f3f4f6;
}

.attachment-link {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;
  color: #059669;
  text-decoration: none;
}

.attachment-link:hover {
  text-decoration: underline;
}

.loading-state, .error-state, .empty-state {
  text-align: center;
  padding: 3rem;
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.empty-icon {
  font-size: 3rem;
  color: #d1d5db;
  margin-bottom: 1rem;
}
</style>

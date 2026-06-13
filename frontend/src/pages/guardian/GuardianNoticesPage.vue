<template>
  <div class="guardian-page">
    <div class="page-header">
      <h1>Notice Board</h1>
      <p>School announcements and important updates</p>
    </div>

    <div v-if="loading" class="state-card"><ProgressSpinner /><p>Loading notices...</p></div>
    <div v-else-if="error" class="state-card"><Message severity="error" :closable="false">{{ error }}</Message></div>
    <div v-else-if="notices.length === 0" class="state-card">
      <h3>No Notices</h3>
      <p>There are no published notices at this time.</p>
    </div>
    <div v-else class="notices-list">
      <div v-for="notice in notices" :key="notice.id" class="notice-card">
        <div class="notice-top">
          <span class="priority-badge" :class="'priority-' + (notice.priority || 'normal').toLowerCase()">
            {{ notice.priority || 'Normal' }}
          </span>
          <span class="notice-date">{{ formatDate(notice.publish_date) }}</span>
        </div>
        <h3>{{ notice.title }}</h3>
        <p>{{ notice.description || notice.content }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import guardianPortalService from '@/services/guardian-portal.service'

export default {
  name: 'GuardianNoticesPage',
  setup() {
    const loading = ref(false)
    const error = ref(null)
    const notices = ref([])

    const loadNotices = async () => {
      loading.value = true
      error.value = null
      try {
        const res = await guardianPortalService.notices()
        notices.value = res.data?.data || []
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load notices.'
      } finally {
        loading.value = false
      }
    }

    const formatDate = (d) => {
      if (!d) return ''
      return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
    }

    onMounted(loadNotices)
    return { loading, error, notices, formatDate }
  },
}
</script>

<style scoped>
.guardian-page { max-width: 900px; margin: 0 auto; }
.page-header { margin-bottom: 1.5rem; }
.page-header h1 { margin: 0 0 0.35rem; font-size: 1.5rem; font-weight: 800; color: var(--text-primary); }
.page-header p { margin: 0; font-size: 0.9rem; color: var(--text-dark); font-weight: 600; }
.notices-list { display: flex; flex-direction: column; gap: 1rem; }
.notice-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-sm); }
.notice-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
.priority-badge { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; padding: 3px 10px; border-radius: 999px; }
.priority-high, .priority-urgent { background: #fee2e2; color: #dc2626; }
.priority-medium { background: #fef3c7; color: #d97706; }
.priority-normal, .priority-low { background: #cffafe; color: #0e7490; }
.notice-date { font-size: 0.8rem; color: var(--text-dark); font-weight: 600; }
.notice-card h3 { margin: 0 0 0.5rem; font-size: 1rem; font-weight: 800; color: var(--text-primary); }
.notice-card p { margin: 0; font-size: 0.9rem; color: var(--text-dark); font-weight: 600; line-height: 1.6; }
.state-card { text-align: center; padding: 2.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; color: var(--text-dark); font-weight: 600; }
</style>

<template>
  <div class="page-container">
    <div class="page-header">
      <h1>📥 Download Center</h1>
      <p class="text-muted">Download brochures, forms, syllabi, and other resources</p>
    </div>

    <div v-if="loading" class="loading-state">
      <ProgressSpinner />
      <p>Loading downloads...</p>
    </div>

    <div v-else-if="error" class="error-state">
      <Message severity="error" :closable="false">{{ error }}</Message>
    </div>

    <template v-else>
      <div v-if="downloads.length === 0" class="empty-state">
        <i class="pi pi-inbox empty-icon"></i>
        <h3>No Downloads Available</h3>
        <p>There are no downloadable resources at this time.</p>
      </div>

      <div v-else class="downloads-list">
        <div v-for="item in downloads" :key="item.id" class="download-card">
          <div class="download-header">
            <div class="download-title-area">
              <span class="category-badge" :class="item.category">{{ formatCategory(item.category) }}</span>
              <h3>{{ item.title }}</h3>
            </div>
          </div>
          <div class="download-body" v-if="item.description">
            <p>{{ item.description }}</p>
          </div>
          <div class="download-footer">
            <button class="download-btn" :disabled="downloadingId === item.id" @click="downloadFile(item)">
              <i class="pi pi-download"></i>
              {{ downloadingId === item.id ? 'Downloading...' : 'Download PDF' }}
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import studentPortalService from '@/services/student-portal.service'
import cmsService from '@/services/cms.service'

export default {
  name: 'StudentDownloadsPage',
  setup() {
    const loading = ref(false)
    const error = ref(null)
    const downloads = ref([])
    const downloadingId = ref(null)

    const formatCategory = (cat) => {
      if (!cat) return 'Other'
      return cat.charAt(0).toUpperCase() + cat.slice(1)
    }

    const loadDownloads = async () => {
      loading.value = true
      error.value = null
      try {
        const res = await studentPortalService.downloads()
        downloads.value = res.data?.data || []
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load downloads.'
      } finally {
        loading.value = false
      }
    }

    const downloadFile = async (item) => {
      downloadingId.value = item.id
      try {
        const res = await cmsService.downloads.download(item.id)
        const fileUrl = res.data?.data?.file_url
        if (fileUrl) {
          window.open(fileUrl, '_blank')
        } else {
          error.value = 'Download link not available.'
        }
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to download file.'
      } finally {
        downloadingId.value = null
      }
    }

    onMounted(loadDownloads)

    return { loading, error, downloads, downloadingId, formatCategory, downloadFile }
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

.downloads-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.download-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  border-left: 4px solid #059669;
  transition: box-shadow 0.2s;
}

.download-card:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}

.download-header {
  margin-bottom: 0.75rem;
}

.download-title-area {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.download-title-area h3 {
  margin: 0;
  font-size: 1.05rem;
  color: var(--text-primary);
}

.category-badge {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
}

.category-badge.brochure { background: #dbeafe; color: #1d4ed8; }
.category-badge.form { background: #fef3c7; color: #d97706; }
.category-badge.syllabus { background: #d1fae5; color: #059669; }
.category-badge.policy { background: #ede9fe; color: #7c3aed; }
.category-badge.other { background: #f3f4f6; color: #6b7280; }

.download-body p {
  margin: 0;
  font-size: 0.9rem;
  color: var(--text-secondary);
  line-height: 1.6;
}

.download-footer {
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px solid #f3f4f6;
}

.download-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;
  color: #fff;
  background: #2563eb;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s;
}

.download-btn:hover:not(:disabled) {
  background: #1d4ed8;
}

.download-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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

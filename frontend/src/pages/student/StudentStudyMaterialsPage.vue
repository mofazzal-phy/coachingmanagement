<template>
  <div class="page-container">
    <div class="page-header">
      <h1>📚 Study Materials</h1>
      <p class="text-muted">Download study materials shared for your classes</p>
    </div>

    <div v-if="loading" class="loading-state">
      <ProgressSpinner />
      <p>Loading study materials...</p>
    </div>

    <div v-else-if="error" class="error-state">
      <Message severity="error" :closable="false">{{ error }}</Message>
    </div>

    <template v-else>
      <div v-if="materials.length === 0" class="empty-state">
        <i class="pi pi-inbox empty-icon"></i>
        <h3>No Study Materials Available</h3>
        <p>There are no study materials available for you at this time.</p>
      </div>

      <div v-else class="materials-list">
        <div v-for="item in materials" :key="item.id" class="material-card">
          <div class="material-header">
            <div class="material-title-area">
              <span class="type-icon">{{ mediaIcon(item.media_type) }}</span>
              <div>
                <h3>{{ item.title }}</h3>
                <span v-if="item.subject?.name" class="meta-tag">{{ item.subject.name }}</span>
                <span v-if="item.school_class?.name" class="meta-tag">{{ item.school_class.name }}</span>
              </div>
            </div>
            <span class="type-badge">{{ item.media_type }}</span>
          </div>
          <div class="material-body" v-if="item.description">
            <p>{{ item.description }}</p>
          </div>
          <div class="material-footer">
            <button class="download-btn" :disabled="downloadingId === item.id" @click="downloadMaterial(item)">
              <i class="pi pi-download"></i>
              {{ downloadingId === item.id ? 'Downloading...' : 'Download' }}
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
  name: 'StudentStudyMaterialsPage',
  setup() {
    const loading = ref(false)
    const error = ref(null)
    const materials = ref([])
    const downloadingId = ref(null)

    const mediaIcon = (type) => {
      const icons = { pdf: '📄', video: '🎬', image: '🖼️', document: '📑' }
      return icons[type] || '📎'
    }

    const loadMaterials = async () => {
      loading.value = true
      error.value = null
      try {
        const res = await studentPortalService.studyMaterials()
        materials.value = res.data?.data || []
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load study materials.'
      } finally {
        loading.value = false
      }
    }

    const downloadMaterial = async (item) => {
      downloadingId.value = item.id
      try {
        const res = await cmsService.studyMaterials.download(item.id)
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

    onMounted(loadMaterials)

    return { loading, error, materials, downloadingId, mediaIcon, downloadMaterial }
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

.materials-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.material-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  border-left: 4px solid #4f46e5;
  transition: box-shadow 0.2s;
}

.material-card:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}

.material-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.75rem;
  gap: 1rem;
}

.material-title-area {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.type-icon {
  font-size: 1.75rem;
  flex-shrink: 0;
}

.material-title-area h3 {
  margin: 0 0 0.35rem;
  font-size: 1.05rem;
  color: var(--text-primary);
}

.meta-tag {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 600;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  background: #e0e7ff;
  color: #4338ca;
  margin-right: 0.35rem;
  text-transform: capitalize;
}

.type-badge {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  background: #f3f4f6;
  color: #6b7280;
  text-transform: uppercase;
  white-space: nowrap;
}

.material-body p {
  margin: 0;
  font-size: 0.9rem;
  color: var(--text-secondary);
  line-height: 1.6;
}

.material-footer {
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
  background: #059669;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s;
}

.download-btn:hover:not(:disabled) {
  background: #047857;
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

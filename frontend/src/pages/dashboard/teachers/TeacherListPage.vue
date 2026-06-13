<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left"><h1>Teachers</h1><span class="badge-count">{{ items.length }} total</span></div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadItems" :disabled="loading">🔄 Refresh</button>
        <router-link to="/dashboard/teachers/create" class="btn btn-primary" v-if="authStore.hasPermission('create teachers')">+ Add Teacher</router-link>
      </div>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading teachers...</p></div>
    <div v-else-if="error" class="error-state"><p>⚠️ {{ error }}</p><button class="btn btn-outline" @click="loadItems">Try Again</button></div>
    <div v-else-if="items.length === 0" class="empty-state">
      <div class="empty-icon">👨‍🏫</div><h3>No Teachers Found</h3><p>Add your first teacher</p>
      <router-link to="/dashboard/teachers/create" class="btn btn-primary">+ Add Teacher</router-link>
    </div>
    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr><th>Name</th><th>Teacher ID</th><th>Email</th><th>Phone</th><th>Type</th><th>Group</th><th>Classes</th><th>Subjects</th><th>Experience</th><th>Salary</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td>
              <div class="teacher-name-cell">
                <div class="avatar">
                  <img v-if="getPhotoUrl(item.photo_url || item.photo)" :src="getPhotoUrl(item.photo_url || item.photo)" alt="" class="avatar-img" />
                  <span v-else>{{ getInitials(item) }}</span>
                </div>
                <strong>{{ item.first_name }} {{ item.last_name }}</strong>
              </div>
            </td>
            <td>{{ item.teacher_id || '—' }}</td>
            <td>{{ item.email || '—' }}</td>
            <td>{{ item.phone || '—' }}</td>
            <td><span class="badge badge-type">{{ item.teacher_type || 'permanent' }}</span></td>
            <td>{{ item.academic_group?.name || item.group || '—' }}</td>
            <td>
              <span v-if="item.classes && item.classes.length" class="badge-list">
                <span class="badge badge-sm" v-for="c in item.classes" :key="c.id">{{ c.name }}</span>
              </span>
              <span v-else>—</span>
            </td>
            <td>
              <span v-if="item.subjects && item.subjects.length" class="badge-list">
                <span class="badge badge-sm badge-subject" v-for="s in item.subjects" :key="s.id">{{ s.name }}</span>
              </span>
              <span v-else>—</span>
            </td>
            <td>{{ item.experience_years || 0 }} yrs</td>
            <td>{{ item.salary_amount ? '$' + item.salary_amount : '—' }}</td>
            <td><span class="status-badge" :class="item.status">{{ item.status || 'active' }}</span></td>
            <td>
              <div class="action-buttons">
                <router-link :to="`/dashboard/teachers/${item.id}`" class="btn-icon" title="View Details">👁️</router-link>
                <button class="btn-icon" title="ID Card" @click="downloadTeacherIdCard(item)">🪪</button>
                <router-link :to="`/dashboard/teachers/${item.id}/edit`" class="btn-icon" title="Edit">✏️</router-link>
                <button class="btn-icon danger" title="Delete" @click="confirmDelete(item)">🗑️</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="modal-overlay" v-if="showDeleteDialog" @click.self="showDeleteDialog = false">
      <div class="modal-dialog modal-sm">
        <div class="modal-header"><h3>Confirm Delete</h3><button class="modal-close" @click="showDeleteDialog = false">✕</button></div>
        <div class="modal-body"><p>Delete teacher <strong>{{ selectedItem?.first_name }} {{ selectedItem?.last_name }}</strong>?</p><p class="text-danger">This cannot be undone.</p></div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showDeleteDialog = false">Cancel</button>
          <button class="btn btn-danger" @click="deleteItem" :disabled="deleteLoading">{{ deleteLoading ? 'Deleting...' : 'Delete' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import teacherService from '@/services/teacher.service'
import { getPhotoUrl, getPersonInitials, downloadBlobFile } from '@/utils/photo.utils'

const getInitials = (item) => getPersonInitials(item)

const downloadTeacherIdCard = async (item) => {
  try {
    const res = await teacherService.downloadIdCard(item.id)
    downloadBlobFile(res, `teacher-id-${item.teacher_id || item.id}.pdf`)
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to download ID card')
  }
}

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const error = ref(null)
const showDeleteDialog = ref(false)
const selectedItem = ref(null)
const deleteLoading = ref(false)

const loadItems = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await teacherService.getAll()
    items.value = Array.isArray(res) ? res : (res?.data || res || [])
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load teachers'
  } finally {
    loading.value = false
  }
}

const confirmDelete = (item) => {
  selectedItem.value = item
  showDeleteDialog.value = true
}

const deleteItem = async () => {
  deleteLoading.value = true
  try {
    await teacherService.delete(selectedItem.value.id)
    showDeleteDialog.value = false
    selectedItem.value = null
    await loadItems()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to delete teacher')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(loadItems)
</script>

<style scoped>
.teacher-name-cell { display: flex; align-items: center; gap: 0.6rem; }
.avatar {
  width: 34px; height: 34px; border-radius: 50%;
  background: linear-gradient(135deg, #059669, #10b981);
  color: #fff; display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; overflow: hidden; flex-shrink: 0;
}
.avatar-img { width: 100%; height: 100%; object-fit: cover; }
</style>

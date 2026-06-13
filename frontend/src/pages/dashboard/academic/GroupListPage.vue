<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left"><h1>Academic Groups</h1></div>
      <div class="header-actions">
        <router-link to="/dashboard/academic/groups/create" class="btn btn-primary">+ Add Group</router-link>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="table-toolbar">
          <input v-model="search" class="form-control search-input" placeholder="Search groups..." @input="loadData" />
        </div>

        <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading groups...</p></div>
        <div v-else-if="error" class="alert alert-danger">{{ error }}</div>
        <div v-else>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Slug</th>
                  <th>Description</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="group in groups" :key="group.id">
                  <td><strong>{{ group.name }}</strong></td>
                  <td><code>{{ group.slug }}</code></td>
                  <td>{{ group.description || '—' }}</td>
                  <td><span :class="'badge badge-' + (group.status === 'active' ? 'success' : 'secondary')">{{ group.status }}</span></td>
                  <td>
                    <div class="action-btns">
                      <router-link :to="`/dashboard/academic/groups/${group.id}/edit`" class="btn btn-sm btn-outline">Edit</router-link>
                      <button class="btn btn-sm btn-danger" @click="deleteItem(group.id)">Delete</button>
                    </div>
                  </td>
                </tr>
                <tr v-if="groups.length === 0">
                  <td colspan="5" class="text-center text-muted">No groups found</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="pagination-info" v-if="meta">
            Showing {{ meta.from }} to {{ meta.to }} of {{ meta.total }} groups
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import academicService from '@/services/academic.service'

const groups = ref([])
const meta = ref(null)
const loading = ref(false)
const error = ref(null)
const search = ref('')

const loadData = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await academicService.groups.list({ search: search.value })
    groups.value = res.data?.data || res.data || []
    meta.value = res.data?.meta || null
  } catch (e) {
    error.value = 'Failed to load groups'
  } finally {
    loading.value = false
  }
}

const deleteItem = async (id) => {
  if (!confirm('Are you sure you want to delete this group?')) return
  try {
    await academicService.groups.delete(id)
    loadData()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to delete group')
  }
}

onMounted(loadData)
</script>

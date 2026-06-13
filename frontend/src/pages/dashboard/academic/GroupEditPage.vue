<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left"><h1>Edit Academic Group</h1></div>
      <div class="header-actions">
        <router-link to="/dashboard/academic/groups" class="btn btn-outline">← Back</router-link>
      </div>
    </div>

    <div class="form-card">
      <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading group...</p></div>
      <div v-else>
        <div v-if="error" class="alert alert-danger">{{ error }}</div>
        <div v-if="success" class="alert alert-success">{{ success }}</div>

        <div class="form-group">
          <label>Name <span class="required">*</span></label>
          <input v-model="form.name" class="form-control" />
        </div>

        <div class="form-group">
          <label>Slug <span class="required">*</span></label>
          <input v-model="form.slug" class="form-control" />
        </div>

        <div class="form-group">
          <label>Description</label>
          <textarea v-model="form.description" class="form-control" rows="3"></textarea>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select v-model="form.status" class="form-control">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>

        <div class="form-actions">
          <router-link to="/dashboard/academic/groups" class="btn btn-outline">Cancel</router-link>
          <button class="btn btn-primary" @click="updateItem" :disabled="!form.name || !form.slug || saving">
            {{ saving ? 'Saving...' : 'Update Group' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import academicService from '@/services/academic.service'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const error = ref(null)
const success = ref(null)
const saving = ref(false)

const form = ref({
  name: '',
  slug: '',
  description: '',
  status: 'active',
})

const loadData = async () => {
  loading.value = true
  try {
    const res = await academicService.groups.get(route.params.id)
    const group = res.data?.data || res.data || res
    form.value = {
      name: group.name || '',
      slug: group.slug || '',
      description: group.description || '',
      status: group.status || 'active',
    }
  } catch (e) {
    error.value = 'Failed to load group'
  } finally {
    loading.value = false
  }
}

const updateItem = async () => {
  saving.value = true
  error.value = null
  success.value = null
  try {
    await academicService.groups.update(route.params.id, form.value)
    success.value = 'Group updated successfully!'
    setTimeout(() => router.push('/dashboard/academic/groups'), 1000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to update group'
  } finally {
    saving.value = false
  }
}

onMounted(loadData)
</script>

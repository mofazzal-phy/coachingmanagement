<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left"><h1>Add Academic Group</h1></div>
      <div class="header-actions">
        <router-link to="/dashboard/academic/groups" class="btn btn-outline">← Back</router-link>
      </div>
    </div>

    <div class="form-card">
      <div v-if="error" class="alert alert-danger">{{ error }}</div>
      <div v-if="success" class="alert alert-success">{{ success }}</div>

      <div class="form-group">
        <label>Name <span class="required">*</span></label>
        <input v-model="form.name" class="form-control" placeholder="e.g. Science" @input="generateSlug" />
      </div>

      <div class="form-group">
        <label>Slug <span class="required">*</span></label>
        <input v-model="form.slug" class="form-control" placeholder="e.g. science" />
      </div>

      <div class="form-group">
        <label>Description</label>
        <textarea v-model="form.description" class="form-control" rows="3" placeholder="Optional description"></textarea>
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
        <button class="btn btn-primary" @click="saveItem" :disabled="!form.name || !form.slug || saving">
          {{ saving ? 'Saving...' : 'Create Group' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import academicService from '@/services/academic.service'

const router = useRouter()
const error = ref(null)
const success = ref(null)
const saving = ref(false)

const form = ref({
  name: '',
  slug: '',
  description: '',
  status: 'active',
})

const generateSlug = () => {
  form.value.slug = form.value.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
}

const saveItem = async () => {
  saving.value = true
  error.value = null
  success.value = null
  try {
    await academicService.groups.create(form.value)
    success.value = 'Group created successfully!'
    setTimeout(() => router.push('/dashboard/academic/groups'), 1000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to create group'
  } finally {
    saving.value = false
  }
}
</script>

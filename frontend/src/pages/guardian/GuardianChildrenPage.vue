<template>
  <div class="guardian-page">
    <div class="page-header">
      <div>
        <h1>My Children</h1>
        <p>View academic progress, attendance, fees, and exam results for your wards</p>
      </div>
      <button class="refresh-btn" @click="loadChildren" :disabled="loading">Refresh</button>
    </div>

    <div v-if="loading" class="state-card"><ProgressSpinner /><p>Loading children...</p></div>
    <div v-else-if="error" class="state-card"><Message severity="error" :closable="false">{{ error }}</Message></div>
    <div v-else-if="children.length === 0" class="state-card">
      <h3>No Children Found</h3>
      <p>We could not find students linked to your account. Please contact the school if your phone or email should be on file.</p>
    </div>
    <div v-else class="children-grid">
      <router-link
        v-for="child in children"
        :key="child.id"
        :to="`/guardian/children/${child.id}`"
        class="child-card"
      >
        <div class="child-top">
          <div class="child-avatar">{{ initials(child) }}</div>
          <div>
            <strong>{{ fullName(child) }}</strong>
            <span>{{ child.student_id }}</span>
          </div>
        </div>
        <div class="child-meta">
          <span v-if="child.current_class?.name">Class: {{ child.current_class.name }}</span>
          <span v-if="child.current_section?.name">Section: {{ child.current_section.name }}</span>
          <span>Enrollments: {{ child.enrollments_count || 0 }}</span>
        </div>
        <span class="view-link">View Full Profile →</span>
      </router-link>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import guardianPortalService from '@/services/guardian-portal.service'

export default {
  name: 'GuardianChildrenPage',
  setup() {
    const authStore = useAuthStore()
    const loading = ref(false)
    const error = ref(null)
    const children = ref([])

    const fullName = (c) => `${c.first_name || ''} ${c.last_name || ''}`.trim()
    const initials = (c) => ((c.first_name?.[0] || '') + (c.last_name?.[0] || '')).toUpperCase() || 'S'

    const loadChildren = async () => {
      loading.value = true
      error.value = null
      try {
        children.value = await guardianPortalService.children(authStore.user)
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load children.'
      } finally {
        loading.value = false
      }
    }

    onMounted(loadChildren)
    return { loading, error, children, loadChildren, fullName, initials }
  },
}
</script>

<style scoped>
.guardian-page { max-width: 1100px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.page-header h1 { margin: 0 0 0.35rem; font-size: 1.5rem; font-weight: 800; color: var(--text-primary); }
.page-header p { margin: 0; font-size: 0.9rem; color: var(--text-dark); font-weight: 600; }
.refresh-btn { padding: 0.6rem 1rem; background: #0891b2; color: #fff; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; }
.children-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
.child-card { display: flex; flex-direction: column; gap: 0.85rem; padding: 1.25rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; text-decoration: none; transition: all 0.2s; box-shadow: var(--shadow-sm); }
.child-card:hover { border-color: #0891b2; transform: translateY(-2px); }
.child-top { display: flex; align-items: center; gap: 0.85rem; }
.child-avatar { width: 52px; height: 52px; border-radius: 14px; background: #cffafe; color: #0e7490; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; }
.child-top strong { display: block; font-size: 1rem; color: var(--text-primary); font-weight: 800; }
.child-top span { font-size: 0.8rem; color: var(--text-dark); font-weight: 600; }
.child-meta { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.8rem; color: var(--text-dark); font-weight: 600; }
.view-link { font-size: 0.8rem; color: #0891b2; font-weight: 700; }
.state-card { text-align: center; padding: 2.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; color: var(--text-dark); font-weight: 600; }
</style>

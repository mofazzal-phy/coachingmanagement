<template>
  <div class="dashboard-layout">
    <SidebarNav ref="sidebarRef" />
    
    <div class="main-wrapper" :class="{ 'sidebar-collapsed': isSidebarCollapsed }">
      <HeaderBar :pageTitle="pageTitle" />
      
      <main class="main-content" :class="{ 'dashboard-home': isDashboardHome }">
        <router-view />
      </main>
      
      <FooterBar />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, provide, watch } from 'vue'
import { useRoute } from 'vue-router'
import SidebarNav from '@/components/layout/SidebarNav.vue'
import HeaderBar from '@/components/layout/HeaderBar.vue'
import FooterBar from '@/components/common/Footer.vue'

const route = useRoute()
const pageTitle = ref('Dashboard')

const sidebarRef = ref(null)
provide('sidebarRef', sidebarRef)

const sidebarState = ref(null)
provide('sidebarState', sidebarState)

watch(sidebarRef, (newVal) => {
  if (newVal) {
    sidebarState.value = newVal
  }
})

const isSidebarCollapsed = computed(() => {
  const s = sidebarState.value
  if (!s?.isCollapsed) return false
  const c = s.isCollapsed
  return typeof c === 'object' && 'value' in c ? c.value : !!c
})

const isDashboardHome = computed(() => route.name === 'Dashboard')

// Update page title based on route
watch(route, (newRoute) => {
  const name = newRoute.name || ''
  // Convert camelCase/PascalCase to readable title
  const title = name
    .replace(/([A-Z])/g, ' $1')
    .replace(/^./, (str) => str.toUpperCase())
    .trim()
  pageTitle.value = title || 'Dashboard'
}, { immediate: true })
</script>

<style scoped>
.dashboard-layout {
  display: flex;
  min-height: 100vh;
}

.main-wrapper {
  margin-left: var(--sidebar-width);
  flex: 1;
  display: flex;
  flex-direction: column;
  transition: var(--transition);
  width: calc(100% - var(--sidebar-width));
}

.main-wrapper.sidebar-collapsed {
  margin-left: var(--sidebar-collapsed-width);
  width: calc(100% - var(--sidebar-collapsed-width));
}

.main-content {
  flex: 1;
  padding: 1rem 1.5rem;
  background: var(--bg-page);
  color: var(--text-dark);
}

.main-content.dashboard-home {
  padding: 1.25rem 1.5rem 1.5rem;
  background: var(--bg-page-gradient);
}

@media (max-width: 768px) {
  .main-wrapper {
    margin-left: 0 !important;
    width: 100% !important;
  }
  
  .main-content {
    padding: 1rem;
  }
}
</style>

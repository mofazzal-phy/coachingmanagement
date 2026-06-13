<template>
  <div class="admin-layout">
    <Sidebar ref="sidebarRef" />
    
    <div class="main-wrapper" :class="{ 'sidebar-collapsed': isSidebarCollapsed }">
      <Header :pageTitle="pageTitle" :breadcrumbItems="breadcrumbItems" />
      
      <main class="main-content">
        <Breadcrumb :items="breadcrumbItems" />
        <slot />
      </main>
      
      <Footer />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, provide, watch } from 'vue'
import Sidebar from '@/components/common/Sidebar.vue'
import Header from '@/components/common/Header.vue'
import Footer from '@/components/common/Footer.vue'
import Breadcrumb from '@/components/common/Breadcrumb.vue'

defineProps({
  pageTitle: {
    type: String,
    default: 'Dashboard'
  },
  breadcrumbItems: {
    type: Array,
    default: () => []
  }
})

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
</script>

<style scoped>
.admin-layout {
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
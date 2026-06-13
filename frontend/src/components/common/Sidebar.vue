<template>
  <!-- Mobile overlay -->
  <div 
    class="sidebar-overlay" 
    :class="{ active: isMobileOpen }"
    @click="closeMobileSidebar"
  ></div>

  <aside class="sidebar" :class="{ 
    collapsed: isCollapsed, 
    'mobile-open': isMobileOpen 
  }">
    <div class="sidebar-header">
      <img src="@/assets/images/logo.svg" alt="Logo" class="logo" />
      <h2 v-if="!isCollapsed" class="brand-name">CMS</h2>
      <button class="toggle-btn desktop-toggle" @click="toggleSidebar">
        <span v-if="!isCollapsed">◀</span>
        <span v-else>▶</span>
      </button>
      <button class="toggle-btn mobile-close" @click="closeMobileSidebar">
        ✕
      </button>
    </div>
    
    <nav class="sidebar-nav">
      <router-link to="/admin" class="nav-item" @click="closeMobileSidebar">
        <span class="nav-icon">📊</span>
        <span v-if="!isCollapsed" class="nav-text">Dashboard</span>
      </router-link>
      
      <router-link to="/admin/students" class="nav-item" @click="closeMobileSidebar">
        <span class="nav-icon">👨‍🎓</span>
        <span v-if="!isCollapsed" class="nav-text">Students</span>
      </router-link>
      
      <router-link to="/admin/academic" class="nav-item" @click="closeMobileSidebar">
        <span class="nav-icon">📚</span>
        <span v-if="!isCollapsed" class="nav-text">Academic</span>
      </router-link>
      
      <router-link to="/admin/attendance" class="nav-item" @click="closeMobileSidebar">
        <span class="nav-icon">✓</span>
        <span v-if="!isCollapsed" class="nav-text">Attendance</span>
      </router-link>
      
      <router-link to="/admin/exams" class="nav-item" @click="closeMobileSidebar">
        <span class="nav-icon">📝</span>
        <span v-if="!isCollapsed" class="nav-text">Exams</span>
      </router-link>
      
      <router-link to="/admin/finance" class="nav-item" @click="closeMobileSidebar">
        <span class="nav-icon">💰</span>
        <span v-if="!isCollapsed" class="nav-text">Finance</span>
      </router-link>
      
      <router-link to="/admin/hr" class="nav-item" @click="closeMobileSidebar">
        <span class="nav-icon">👥</span>
        <span v-if="!isCollapsed" class="nav-text">HR</span>
      </router-link>
      
      <router-link to="/admin/communication" class="nav-item" @click="closeMobileSidebar">
        <span class="nav-icon">📢</span>
        <span v-if="!isCollapsed" class="nav-text">Communication</span>
      </router-link>
      
      <router-link to="/admin/cms" class="nav-item" @click="closeMobileSidebar">
        <span class="nav-icon">📄</span>
        <span v-if="!isCollapsed" class="nav-text">CMS</span>
      </router-link>
      
      <router-link to="/admin/reports" class="nav-item" @click="closeMobileSidebar">
        <span class="nav-icon">📈</span>
        <span v-if="!isCollapsed" class="nav-text">Reports</span>
      </router-link>
    </nav>
  </aside>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const isCollapsed = ref(false)
const isMobileOpen = ref(false)
const isMobile = ref(false)

const checkMobile = () => {
  isMobile.value = window.innerWidth < 768
  if (!isMobile.value) {
    isMobileOpen.value = false
  }
}

const toggleSidebar = () => {
  if (isMobile.value) {
    isMobileOpen.value = !isMobileOpen.value
  } else {
    isCollapsed.value = !isCollapsed.value
  }
}

const closeMobileSidebar = () => {
  if (isMobile.value) {
    isMobileOpen.value = false
  }
}

// Expose for header hamburger
defineExpose({ toggleSidebar, isMobileOpen, isMobile, isCollapsed })

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})
</script>

<style scoped>
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 998;
}

.sidebar-overlay.active {
  display: block;
}

.sidebar {
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  width: var(--sidebar-width);
  background: var(--bg-dark);
  color: white;
  transition: var(--transition);
  z-index: 1000;
  overflow-y: auto;
  overflow-x: hidden;
}

.sidebar.collapsed {
  width: var(--sidebar-collapsed-width);
}

.sidebar-header {
  padding: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  min-height: var(--header-height);
}

.logo {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
}

.brand-name {
  font-size: 18px;
  white-space: nowrap;
  overflow: hidden;
}

.toggle-btn {
  margin-left: auto;
  color: white;
  font-size: 16px;
  padding: 0.25rem 0.5rem;
  border-radius: var(--radius-sm);
  transition: var(--transition);
}

.toggle-btn:hover {
  background: rgba(255,255,255,0.1);
}

.mobile-close {
  display: none;
}

.sidebar-nav {
  padding: 1rem 0;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.5rem;
  color: rgba(255,255,255,0.7);
  transition: var(--transition);
  white-space: nowrap;
  border-left: 3px solid transparent;
}

.nav-item:hover {
  background: rgba(255,255,255,0.1);
  color: white;
}

.nav-item.router-link-active {
  background: rgba(255,255,255,0.15);
  color: white;
  border-left-color: var(--primary-color);
}

.nav-icon {
  font-size: 20px;
  min-width: 24px;
  text-align: center;
}

.nav-text {
  font-size: 14px;
}

/* Mobile Responsive */
@media (max-width: 768px) {
  .sidebar {
    left: -100%;
    width: 280px;
  }
  
  .sidebar.mobile-open {
    left: 0;
  }
  
  .sidebar.collapsed {
    width: 280px;
  }
  
  .mobile-close {
    display: block;
  }
  
  .desktop-toggle {
    display: none;
  }
}
</style>
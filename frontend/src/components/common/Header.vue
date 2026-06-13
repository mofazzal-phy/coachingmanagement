<template>
  <header class="header">
    <div class="header-left">
      <button class="hamburger-btn" @click="toggleSidebar">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
      </button>
      <h3 class="page-title">{{ pageTitle }}</h3>
    </div>
    
    <div class="header-right">
      <!-- Notification -->
      <div class="dropdown">
        <button class="icon-btn" @click="toggleNotifications">
          🔔
          <span v-if="unreadCount" class="badge">{{ unreadCount }}</span>
        </button>
        <div v-if="showNotifications" class="dropdown-menu">
          <div class="dropdown-header">Notifications</div>
          <div class="dropdown-item">No new notifications</div>
        </div>
      </div>

      <!-- User Menu -->
      <div class="dropdown">
        <button class="user-btn" @click="toggleUserMenu">
          <img 
            :src="user?.avatar || 'https://ui-avatars.com/api/?name=' + (user?.name || 'Admin') + '&background=4a90d9&color=fff'" 
            alt="Avatar" 
            class="avatar" 
          />
          <span class="user-name">{{ user?.name || 'Admin' }}</span>
          <span class="arrow">▼</span>
        </button>
        
        <div v-if="showUserMenu" class="dropdown-menu user-menu">
          <div class="dropdown-item header-item">
            <strong>{{ user?.name }}</strong>
            <small>{{ user?.email }}</small>
          </div>
          <hr />
          <router-link to="/admin/profile" class="dropdown-item" @click="showUserMenu = false">
            👤 Profile
          </router-link>
          <router-link to="/admin/settings" class="dropdown-item" @click="showUserMenu = false">
            ⚙ Settings
          </router-link>
          <hr />
          <button class="dropdown-item logout-item" @click="handleLogout">
            🚪 Logout
          </button>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed, inject } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'

defineProps({
  pageTitle: {
    type: String,
    default: 'Dashboard'
  }
})

const router = useRouter()
const authStore = useAuthStore()
const user = computed(() => authStore.user)
const unreadCount = ref(0)
const showNotifications = ref(false)
const showUserMenu = ref(false)

// Inject sidebar ref from parent
const sidebarRef = inject('sidebarRef', null)

const toggleSidebar = () => {
  if (sidebarRef?.value) {
    sidebarRef.value.toggleSidebar()
  }
}

const toggleNotifications = () => {
  showNotifications.value = !showNotifications.value
  showUserMenu.value = false
}

const toggleUserMenu = () => {
  showUserMenu.value = !showUserMenu.value
  showNotifications.value = false
}

const handleLogout = () => {
  showUserMenu.value = false
  authStore.logout()
  router.push('/login')
}

// Close dropdowns on outside click
document.addEventListener('click', (e) => {
  if (!e.target.closest('.dropdown')) {
    showNotifications.value = false
    showUserMenu.value = false
  }
})
</script>

<style scoped>
.header {
  height: var(--header-height);
  background: var(--bg-white);
  padding: 0 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: var(--shadow-sm);
  position: sticky;
  top: 0;
  z-index: 999;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.hamburger-btn {
  display: none;
  flex-direction: column;
  gap: 4px;
  padding: 0.5rem;
  border-radius: var(--radius-sm);
}

.hamburger-btn:hover {
  background: var(--bg-light);
}

.hamburger-line {
  width: 20px;
  height: 2px;
  background: var(--text-dark);
  border-radius: 2px;
}

.page-title {
  font-size: 18px;
  color: var(--text-dark);
}

.header-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* Dropdown */
.dropdown {
  position: relative;
}

.icon-btn {
  position: relative;
  font-size: 20px;
  padding: 0.5rem;
  border-radius: var(--radius-sm);
  transition: var(--transition);
}

.icon-btn:hover {
  background: var(--bg-light);
}

.badge {
  position: absolute;
  top: 2px;
  right: 2px;
  background: var(--danger-color);
  color: white;
  font-size: 10px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.user-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  border-radius: var(--radius-sm);
  transition: var(--transition);
}

.user-btn:hover {
  background: var(--bg-light);
}

.avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  object-fit: cover;
}

.user-name {
  font-size: 14px;
  font-weight: 500;
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.arrow {
  font-size: 10px;
  color: var(--text-light);
}

/* Dropdown Menu */
.dropdown-menu {
  position: absolute;
  right: 0;
  top: calc(100% + 0.5rem);
  background: var(--bg-white);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-lg);
  min-width: 200px;
  z-index: 1001;
  border: 1px solid var(--border-color);
}

.user-menu {
  min-width: 250px;
}

.dropdown-header {
  padding: 0.75rem 1rem;
  font-weight: 600;
  border-bottom: 1px solid var(--border-color);
  font-size: 14px;
}

.dropdown-item {
  display: block;
  width: 100%;
  padding: 0.75rem 1rem;
  text-align: left;
  font-size: 14px;
  transition: var(--transition);
  color: var(--text-dark);
  text-decoration: none;
  cursor: pointer;
}

.dropdown-item:hover {
  background: var(--bg-light);
}

.header-item {
  cursor: default;
}

.header-item strong {
  display: block;
}

.header-item small {
  color: var(--text-light);
}

.logout-item {
  color: var(--danger-color) !important;
}

hr {
  margin: 0;
  border: none;
  border-top: 1px solid var(--border-color);
}

/* Mobile */
@media (max-width: 768px) {
  .hamburger-btn {
    display: flex;
  }
  
  .user-name {
    display: none;
  }
}
</style>
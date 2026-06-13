<template>
  <header class="header">
    <div class="header-left">
      <!-- Mobile hamburger -->
      <button class="hamburger-btn" @click="toggleMobileSidebar">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 6h18M3 12h18M3 18h18" stroke-linecap="round"/>
        </svg>
      </button>
      
      <div>
        <p class="eyebrow">Coaching Management System</p>
        <h2>{{ pageTitle }}</h2>
      </div>
    </div>
    
    <div class="header-right">
      <!-- Theme Toggle -->
      <button class="icon-btn" @click="toggleTheme" :title="theme === 'dark' ? 'Switch to Light' : 'Switch to Dark'">
        <svg v-if="theme === 'dark'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
        <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
      </button>

      <!-- Notification Bell -->
      <div class="notification-wrap" ref="notificationRef">
        <button class="icon-btn" title="Notifications" @click.stop="toggleNotifications">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>
          </svg>
          <span v-if="unreadCount > 0" class="notification-dot">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
        </button>

        <div v-if="showNotifications" class="notification-panel">
          <div class="panel-header">
            <strong>Notifications</strong>
            <button v-if="unreadCount > 0" class="link-btn" @click="markAllRead">Mark all read</button>
          </div>
          <div v-if="notificationsLoading" class="panel-empty">Loading...</div>
          <div v-else-if="notifications.length === 0" class="panel-empty">No notifications</div>
          <div v-else class="panel-list">
            <button
              v-for="item in notifications"
              :key="item.id"
              class="notification-item"
              :class="{ unread: !item.is_read, highlighted: item.is_highlighted }"
              @click="markOneRead(item)"
            >
              <div class="item-top">
                <span class="item-title">{{ item.title }}</span>
                <span v-if="item.is_highlighted && !item.is_read" class="new-badge">NEW</span>
              </div>
              <p class="item-message">{{ item.message }}</p>
              <small class="item-time">{{ formatTime(item.sent_at || item.created_at) }}</small>
            </button>
          </div>
        </div>
      </div>
      
      <!-- User Avatar Dropdown -->
      <div class="user-dropdown" @click="toggleDropdown" ref="dropdownRef">
        <img :src="userAvatar" alt="User" class="user-avatar" />
        <span class="user-name">{{ user?.name || 'User' }}</span>
        <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor">
          <path d="M3 4.5l3 3 3-3" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        
        <div class="dropdown-menu" v-if="showDropdown">
          <div class="dropdown-header">
            <strong>{{ user?.name }}</strong>
            <span>{{ user?.role }}</span>
          </div>
          <router-link to="/dashboard/settings" class="dropdown-item" @click="showDropdown = false">
            ⚙️ Settings
          </router-link>
          <hr />
          <button class="dropdown-item logout" @click="handleLogout">
            🚪 Logout
          </button>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import { useTheme } from '@/composables/useTheme'
import communicationService from '@/services/communication.service'

defineProps({
  pageTitle: {
    type: String,
    default: 'Dashboard'
  }
})

const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user)
const showDropdown = ref(false)
const dropdownRef = ref(null)
const showNotifications = ref(false)
const notificationRef = ref(null)
const notifications = ref([])
const unreadCount = ref(0)
const notificationsLoading = ref(false)

const { theme, toggleTheme } = useTheme()

const userAvatar = computed(() => {
  return user.value?.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.value?.name || 'User')}&background=4a90d9&color=fff`
})

const toggleDropdown = () => {
  showDropdown.value = !showDropdown.value
  showNotifications.value = false
}

const toggleNotifications = async () => {
  showNotifications.value = !showNotifications.value
  showDropdown.value = false
  if (showNotifications.value) {
    await loadNotifications()
  }
}

async function loadUnreadCount() {
  try {
    const res = await communicationService.unreadCount()
    unreadCount.value = res.data?.data?.count ?? 0
  } catch {
    unreadCount.value = 0
  }
}

async function loadNotifications() {
  notificationsLoading.value = true
  try {
    const res = await communicationService.list({ per_page: 15 })
    const payload = res.data?.data
    notifications.value = payload?.data || payload || []
    await loadUnreadCount()
  } catch {
    notifications.value = []
  } finally {
    notificationsLoading.value = false
  }
}

async function markOneRead(item) {
  if (item.is_read) return
  try {
    await communicationService.markRead(item.id)
    item.is_read = true
    item.is_highlighted = false
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  } catch {
    // ignore
  }
}

async function markAllRead() {
  try {
    await communicationService.markAllRead()
    notifications.value = notifications.value.map((n) => ({ ...n, is_read: true, is_highlighted: false }))
    unreadCount.value = 0
  } catch {
    // ignore
  }
}

function formatTime(value) {
  if (!value) return ''
  return new Date(value).toLocaleString('en-BD', {
    day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
  })
}

const toggleMobileSidebar = () => {
  // Dispatch custom event for mobile sidebar toggle
  window.dispatchEvent(new CustomEvent('toggle-sidebar'))
}

const handleLogout = () => {
  authStore.logout()
  router.push('/login')
}

const handleClickOutside = (e) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    showDropdown.value = false
  }
  if (notificationRef.value && !notificationRef.value.contains(e.target)) {
    showNotifications.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  loadUnreadCount()
  window.setInterval(loadUnreadCount, 60000)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1.5rem;
  background: var(--header-bg);
  border-bottom: 1px solid var(--header-border);
  position: sticky;
  top: 0;
  z-index: 100;
  min-height: 64px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.hamburger-btn {
  display: none;
  background: none;
  border: none;
  color: var(--header-text);
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 8px;
}

.hamburger-btn:hover {
  background: var(--header-hover-bg);
}

.eyebrow {
  font-size: 0.75rem;
  color: var(--header-text-muted);
  margin: 0;
}

h2 {
  font-size: 1.15rem;
  margin: 0.15rem 0 0 0;
  color: var(--header-text);
}

.header-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.icon-btn {
  position: relative;
  background: none;
  border: none;
  color: var(--header-text-muted);
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 8px;
  transition: all 0.2s;
}

.icon-btn:hover {
  background: var(--header-hover-bg);
  color: var(--header-text);
}

.notification-wrap { position: relative; }

.notification-dot {
  position: absolute;
  top: 2px;
  right: 2px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  background: #ef4444;
  color: white;
  border-radius: 999px;
  border: 2px solid var(--header-bg);
  font-size: 0.62rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.notification-panel {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 340px;
  max-height: 420px;
  overflow: hidden;
  background: var(--dropdown-bg);
  border: 1px solid var(--dropdown-border);
  border-radius: 12px;
  box-shadow: var(--shadow-lg);
  z-index: 1100;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--border-color);
  color: var(--dropdown-text);
}

.link-btn {
  border: none;
  background: none;
  color: var(--text-link);
  font-size: 0.75rem;
  cursor: pointer;
}

.panel-empty {
  padding: 1.25rem;
  text-align: center;
  color: var(--dropdown-text-muted);
  font-size: 0.85rem;
}

.panel-list {
  max-height: 340px;
  overflow-y: auto;
}

.notification-item {
  display: block;
  width: 100%;
  text-align: left;
  border: none;
  background: var(--dropdown-bg);
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--border-light);
  cursor: pointer;
  color: var(--dropdown-text);
}

.notification-item.unread { background: var(--notification-unread-bg); }
.notification-item.highlighted { border-left: 3px solid var(--primary-color); }

.item-top {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
  align-items: flex-start;
}

.item-title { font-size: 0.82rem; font-weight: 700; color: var(--text-primary); }
.new-badge {
  background: #ef4444;
  color: white;
  font-size: 0.62rem;
  font-weight: 700;
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
  flex-shrink: 0;
}

.item-message {
  margin: 0.25rem 0;
  font-size: 0.78rem;
  color: var(--text-secondary);
  line-height: 1.4;
}

.item-time { color: var(--text-muted); font-size: 0.7rem; }

.user-dropdown {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  padding: 0.4rem 0.75rem;
  border-radius: 10px;
  transition: background 0.2s;
}

.user-dropdown:hover {
  background: var(--header-hover-bg);
}

.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  object-fit: cover;
}

.user-name {
  font-size: 0.85rem;
  color: var(--header-text);
  font-weight: 500;
}

.dropdown-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  background: var(--dropdown-bg);
  border-radius: 12px;
  box-shadow: var(--shadow-lg);
  min-width: 200px;
  z-index: 1000;
  overflow: hidden;
  border: 1px solid var(--dropdown-border);
}

.dropdown-header {
  padding: 1rem;
  border-bottom: 1px solid var(--border-color);
}

.dropdown-header strong {
  display: block;
  font-size: 0.9rem;
  color: var(--dropdown-text);
}

.dropdown-header span {
  font-size: 0.75rem;
  color: var(--dropdown-text-muted);
  text-transform: capitalize;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  color: var(--dropdown-text);
  text-decoration: none;
  font-size: 0.85rem;
  transition: background 0.2s;
  width: 100%;
  border: none;
  background: none;
  cursor: pointer;
}

.dropdown-item:hover {
  background: var(--dropdown-hover-bg);
}

.dropdown-item.logout {
  color: var(--danger-color);
}

hr {
  margin: 0;
  border: none;
  border-top: 1px solid var(--border-color);
}

@media (max-width: 768px) {
  .hamburger-btn {
    display: block;
  }
  
  .user-name {
    display: none;
  }
}
</style>

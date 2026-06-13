<template>
  <!-- Mobile overlay -->
  <div 
    class="sidebar-overlay" 
    :class="{ active: isMobileOpen }"
    @click="closeMobileSidebar"
  ></div>

  <div
    class="sidebar-shell"
    :class="{
      collapsed: isCollapsed && !isMobile,
      'mobile-open': isMobileOpen,
    }"
  >
    <aside class="sidebar" :class="{ collapsed: isCollapsed && !isMobile }">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
      <!-- Brand Area -->
      <div class="brand-area">
        <div class="brand-icon">🎓</div>
        <div class="brand-text" v-show="!isCollapsed || isMobile">
          <h3>CMS</h3>
          <small>Coaching Management</small>
        </div>
      </div>
      
      <!-- Mobile Close -->
      <button class="mobile-close-btn" @click="closeMobileSidebar">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
    
    <!-- Navigation -->
    <nav class="sidebar-nav">
      <div class="nav-section" v-for="(section, idx) in visibleMenuItems" :key="idx">
        <div class="nav-section-title" v-show="!isCollapsed || isMobile">
          {{ section.title }}
        </div>
        
        <!-- Regular nav items -->
        <template v-for="item in section.items" :key="item.path || item.label">
          <!-- If item has children, render as expandable submenu -->
          <div v-if="item.children" class="nav-group">
            <div 
              class="nav-item nav-parent"
              :class="{ 'nav-parent-expanded': isMenuOpen(item.label, item.children) }"
              @click="toggleExpand(item.label, item.children)"
              :title="(isCollapsed && !isMobile) ? item.label : ''"
            >
              <span class="nav-icon">{{ item.icon }}</span>
              <span class="nav-text" v-show="!isCollapsed || isMobile">{{ item.label }}</span>
              <span 
                class="nav-expand-icon" 
                v-show="!isCollapsed || isMobile"
                :class="{ rotated: isMenuOpen(item.label, item.children) }"
              >▶</span>
            </div>
            <div 
              class="nav-children" 
              v-show="isMenuOpen(item.label, item.children) && (!isCollapsed || isMobile)"
            >
              <router-link
                v-for="child in item.children"
                :key="child.path"
                :to="child.path"
                class="nav-item nav-child-item"
                :class="{ 'router-link-active': isActive(child.path) }"
                @click="closeMobileSidebar"
              >
                <span class="nav-icon nav-child-icon">{{ child.icon }}</span>
                <span class="nav-text">{{ child.label }}</span>
              </router-link>
            </div>
          </div>
          <!-- Regular single item -->
          <router-link 
          v-else
          :to="item.path" 
          class="nav-item"
          :class="{ 'router-link-active': isActive(item.path) }"
          @click="closeMobileSidebar"
          :title="(isCollapsed && !isMobile) ? item.label : ''"
        >
          <span class="nav-icon">{{ item.icon }}</span>
          <span class="nav-text" v-show="!isCollapsed || isMobile">{{ item.label }}</span>
          <span class="nav-badge" v-if="item.badge && (!isCollapsed || isMobile)">
            {{ item.badge }}
          </span>
        </router-link>
        </template>
      </div>
    </nav>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer" v-show="!isCollapsed || isMobile">
      <div class="user-card">
        <img :src="userAvatar" alt="User" class="user-avatar" />
        <div class="user-info">
          <strong>{{ user?.name || 'User' }}</strong>
          <span>{{ user?.role || 'N/A' }}</span>
        </div>
      </div>
      <button class="logout-btn" @click="handleLogout">
        <span>🚪</span>
        <span>Logout</span>
      </button>
    </div>
    
    <!-- Collapsed Logout Button -->
    <div class="collapsed-footer" v-show="isCollapsed && !isMobile">
      <button class="logout-icon-btn" @click="handleLogout" title="Logout">
        🚪
      </button>
    </div>
    </aside>

    <!-- Desktop collapse toggle — outside aside so it is never clipped -->
    <button
      v-if="!isMobile"
      type="button"
      class="sidebar-edge-toggle"
      @click="toggleSidebar"
      :title="isCollapsed ? 'Expand Sidebar (Ctrl+B)' : 'Collapse Sidebar (Ctrl+B)'"
      :aria-label="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
    >
      <svg
        width="14"
        height="14"
        viewBox="0 0 16 16"
        fill="none"
        class="toggle-arrow"
        :class="{ rotated: isCollapsed }"
      >
        <path d="M10.5 3L5.5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const user = computed(() => authStore.user)
const userPermissions = computed(() => authStore.userPermissions)
const userAvatar = computed(() => {
  return user.value?.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.value?.name || 'User')}&background=4a90d9&color=fff`
})

const isCollapsed = ref(false)
const isMobileOpen = ref(false)
const isMobile = ref(false)
const expandedMenus = ref({})

// undefined = auto (expand when child route is active), true = open, false = closed
const isChildActive = (children) => {
  if (!children) return false
  return children.some(child => route.path === child.path || route.path.startsWith(child.path + '/'))
}

const isMenuOpen = (label, children) => {
  if (expandedMenus.value[label] === true) return true
  if (expandedMenus.value[label] === false) return false
  return isChildActive(children)
}

const toggleExpand = (label, children) => {
  expandedMenus.value[label] = !isMenuOpen(label, children)
}

// =============================================
// All possible menu items (hardcoded structure)
// =============================================
const allMenuSections = [
  {
    title: 'MAIN',
    permission: null,
    hideForRoles: ['student', 'teacher', 'guardian', 'employee'],
    items: [
      { path: '/dashboard', icon: '📊', label: 'Dashboard', permission: null },
    ]
  },
  {
    title: 'HOME',
    role: 'student',
    items: [
      { path: '/student', icon: '🏠', label: 'Dashboard', permission: null },
    ]
  },
  {
    title: 'ACADEMICS',
    role: 'student',
    items: [
      { path: '/student/class-routine', icon: '🏫', label: 'Class Routine', permission: 'view class routines' },
      { path: '/student/attendance', icon: '✓', label: 'Attendance', permission: 'view attendance' },
    ]
  },
  {
    title: 'EXAMS',
    role: 'student',
    items: [
      {
        icon: '📝',
        label: 'Exams',
        children: [
          { path: '/student/exams', icon: '📋', label: 'Exam List', permission: 'view exams' },
          { path: '/student/practice', icon: '🎯', label: 'Practice Center', permission: 'view exams' },
          { path: '/student/exam-routines', icon: '📅', label: 'Exam Routine', permission: 'view exam routines' },
          { path: '/student/exam-results', icon: '📊', label: 'Results', permission: 'view exam results' },
        ]
      },
    ]
  },
  {
    title: 'FINANCE',
    role: 'student',
    items: [
      {
        icon: '💰',
        label: 'Fees & Payments',
        children: [
          { path: '/student/fee-dashboard', icon: '📊', label: 'Fee Dashboard', permission: 'view fee collections' },
          { path: '/student/fee-ledger', icon: '📒', label: 'Fee Ledger', permission: 'view fee collections' },
          { path: '/student/fee-payment', icon: '💳', label: 'Pay Fee', permission: 'view fee collections' },
          { path: '/student/fee-notifications', icon: '🔔', label: 'Fee Alerts', permission: 'view fee collections' },
        ]
      },
    ]
  },
  {
    title: 'MORE',
    role: 'student',
    items: [
      { path: '/student/notices', icon: '📢', label: 'Notice Board', permission: 'view notice board' },
      { path: '/student/study-materials', icon: '📚', label: 'Study Materials', permission: 'view study materials' },
      { path: '/student/downloads', icon: '📥', label: 'Download Center', permission: 'view download center' },
      { path: '/student/leave-apply', icon: '📋', label: 'Apply Leave', permission: null },
    ]
  },
  {
    title: 'HOME',
    role: 'teacher',
    items: [
      { path: '/teacher', icon: '🏠', label: 'Dashboard', permission: null },
    ]
  },
  {
    title: 'SCHEDULE',
    role: 'teacher',
    items: [
      { path: '/dashboard/teacher/my-schedule', icon: '📅', label: 'My Schedule', permission: 'view my schedule' },
      { path: '/dashboard/my-attendance', icon: '✓', label: 'My Attendance', permission: 'view attendance' },
    ]
  },
  {
    title: 'EXAMS',
    role: 'teacher',
    items: [
      {
        icon: '📝',
        label: 'Exams',
        children: [
          { path: '/dashboard/teacher/my-exam-routines', icon: '📅', label: 'My Exam Routines', permission: 'view exam routines' },
          { path: '/dashboard/teacher/exam-duties', icon: '📋', label: 'Exam Duties', permission: 'view exam routines' },
          { path: '/dashboard/teacher/exam-marks', icon: '✏️', label: 'Marks Entry', permission: 'create exam results' },
          { path: '/dashboard/teacher/exam-leaderboard', icon: '🏆', label: 'Leaderboard', permission: 'view exam results' },
        ]
      },
    ]
  },
  {
    title: 'CLASSROOM',
    role: 'teacher',
    items: [
      { path: '/dashboard/attendance/students', icon: '✓', label: 'Mark Attendance', permission: 'view student attendance' },
      { path: '/dashboard/students', icon: '👨‍🎓', label: 'My Students', permission: 'view students' },
    ]
  },
  {
    title: 'QUESTIONS',
    role: 'teacher',
    items: [
      { path: '/dashboard/teacher/questions', icon: '❓', label: 'My Questions', permission: 'view questions' },
    ]
  },
  {
    title: 'MORE',
    role: 'teacher',
    items: [
      { path: '/dashboard/communication/notice-board', icon: '📢', label: 'Notice Board', permission: 'view notice board' },
    ]
  },
  {
    title: 'HOME',
    role: 'guardian',
    items: [
      { path: '/guardian', icon: '🏠', label: 'Dashboard', permission: null },
    ]
  },
  {
    title: 'MY WARD',
    role: 'guardian',
    items: [
      { path: '/guardian/children', icon: '👨‍🎓', label: 'My Children', permission: 'view students' },
    ]
  },
  {
    title: 'INSIGHTS',
    role: 'guardian',
    items: [
      {
        icon: '📊',
        label: 'Track Progress',
        children: [
          { path: '/guardian/children', icon: '✓', label: 'Attendance', permission: 'view attendance' },
          { path: '/guardian/children', icon: '📊', label: 'Exam Results', permission: 'view exam results' },
          { path: '/guardian/children', icon: '💰', label: 'Fee Status', permission: 'view fee collections' },
        ]
      },
    ]
  },
  {
    title: 'MORE',
    role: 'guardian',
    items: [
      { path: '/guardian/notices', icon: '📢', label: 'Notice Board', permission: 'view notice board' },
    ]
  },
  {
    title: 'HOME',
    role: 'employee',
    items: [
      { path: '/employee', icon: '🏠', label: 'Dashboard', permission: null },
    ]
  },
  {
    title: 'ATTENDANCE',
    role: 'employee',
    items: [
      { path: '/dashboard/my-attendance', icon: '✓', label: 'My Attendance', permission: 'view attendance' },
      { path: '/dashboard/hr/staff-attendance', icon: '📋', label: 'Staff Attendance', permission: 'view staff attendance' },
    ]
  },
  {
    title: 'HR & LEAVE',
    role: 'employee',
    items: [
      {
        icon: '📝',
        label: 'Leave & Payroll',
        children: [
          { path: '/dashboard/hr/leave-requests', icon: '📝', label: 'Leave Requests', permission: 'view leave requests' },
          { path: '/dashboard/hr/payroll', icon: '💵', label: 'Payroll', permission: 'view payroll' },
        ]
      },
    ]
  },
  {
    title: 'ORGANIZATION',
    role: 'employee',
    items: [
      { path: '/dashboard/hr/departments', icon: '🏢', label: 'Departments', permission: 'view departments' },
      { path: '/dashboard/hr/designations', icon: '📌', label: 'Designations', permission: 'view designations' },
      { path: '/dashboard/hr/employees', icon: '👥', label: 'Colleagues', permission: 'view employees' },
    ]
  },
  {
    title: 'MORE',
    role: 'employee',
    items: [
      { path: '/employee/notices', icon: '📢', label: 'Notice Board', permission: 'view notice board' },
      { path: '/dashboard/communication/notifications', icon: '🔔', label: 'Notifications', permission: 'view notifications' },
    ]
  },
  {
    title: 'USER MANAGEMENT',
    permission: 'view users',
    items: [
      { path: '/dashboard/users', icon: '👤', label: 'Users', permission: 'view users' },
      { path: '/dashboard/roles', icon: '🔑', label: 'Roles', permission: 'view roles' },
      { path: '/dashboard/permissions', icon: '🛡️', label: 'Permissions', permission: 'view permissions' },
    ]
  },
  {
    title: 'STUDENT MANAGEMENT',
    permission: 'view students',
    items: [
      { path: '/dashboard/students', icon: '👨‍🎓', label: 'Students', permission: 'view students', badge: 'New' },
      { path: '/dashboard/guardians', icon: '👪', label: 'Guardians', permission: 'view guardians' },
      { path: '/dashboard/admissions', icon: '📋', label: 'Admissions', permission: 'view admissions' },
      { path: '/dashboard/student-leaves', icon: '📋', label: 'Student Leaves', permission: 'view students' },
    ]
  },
  {
    title: 'ACADEMIC',
    permission: 'view classes',
    items: [
      { path: '/dashboard/academic/sessions', icon: '📅', label: 'Sessions', permission: 'view academic sessions' },
      { path: '/dashboard/academic/classes', icon: '🏫', label: 'Classes', permission: 'view classes' },
      { path: '/dashboard/academic/sections', icon: '📐', label: 'Sections', permission: 'view sections' },
      { path: '/dashboard/academic/subjects', icon: '📚', label: 'Subjects', permission: 'view subjects' },
      { path: '/dashboard/academic/groups', icon: '👥', label: 'Groups', permission: 'view academic groups' },
      { path: '/dashboard/academic/rooms', icon: '🚪', label: 'Rooms', permission: 'view rooms' },
      { path: '/dashboard/academic/periods', icon: '⏰', label: 'Time Slots', permission: 'view routine periods' },
      {
        icon: '📅',
        label: 'Routine',
        permission: 'view class routines',
        children: [
          { path: '/dashboard/academic/routine', icon: '📋', label: 'Routine Existing', permission: 'view class routines' },
          { path: '/dashboard/academic/routine-exceptions', icon: '🚨', label: 'Routine Exceptions', permission: 'view routine exceptions' },
        ]
      },
    ]
  },
  {
    title: 'TEACHERS',
    permission: 'view teachers',
    items: [
      { path: '/dashboard/teachers', icon: '👨‍🏫', label: 'Teachers', permission: 'view teachers' },
    ]
  },
  {
    title: 'ENROLLMENT',
    permission: 'view courses',
    items: [
      { path: '/dashboard/enrollment/courses', icon: '📚', label: 'Courses', permission: 'view courses' },
      { path: '/dashboard/enrollment/batches', icon: '📦', label: 'Batches', permission: 'view batches' },
      { path: '/dashboard/enrollment/enrollments', icon: '📝', label: 'Enrollments', permission: 'view enrollments' },
    ]
  },
  {
    title: 'ATTENDANCE',
    permission: 'view attendance',
    items: [
      { path: '/dashboard/my-attendance', icon: '📋', label: 'My Attendance', permission: 'view attendance' },
      { path: '/dashboard/attendance/dashboard', icon: '📈', label: 'Live Dashboard', permission: 'view attendance dashboard' },
      { path: '/dashboard/attendance/legacy', icon: '🗂️', label: 'Legacy View (Class)', permission: 'view attendance' },
      {
        icon: '👨‍🎓',
        label: 'Student Attendance',
        permission: 'view student attendance',
        children: [
          { path: '/dashboard/attendance/students', icon: '✓', label: 'Mark Attendance', permission: 'view student attendance' },
          { path: '/dashboard/attendance/sessions', icon: '📋', label: 'Sessions', permission: 'view attendance sessions' },
          { path: '/dashboard/attendance/reports', icon: '📊', label: 'Reports', permission: 'view attendance reports' },
        ]
      },
      { path: '/dashboard/attendance/teachers', icon: '👨‍🏫', label: 'Teacher Attendance', permission: 'view teacher attendance' },
      { path: '/dashboard/attendance/employees', icon: '👥', label: 'Employee Attendance', permission: 'view employee attendance' },
      {
        icon: '📟',
        label: 'Biometric & Devices',
        permission: 'view biometric devices',
        children: [
          { path: '/dashboard/attendance/devices', icon: '📟', label: 'Biometric Devices', permission: 'view biometric devices' },
          { path: '/dashboard/attendance/simulator', icon: '🔬', label: 'Device Simulator', permission: 'view device simulator' },
        ]
      },
    ]
  },
  {
    title: 'EXAMS',
    permission: 'view exams',
    items: [
      { path: '/dashboard/exams', icon: '📝', label: 'Exams', permission: 'view exams' },
      { path: '/dashboard/exams/types', icon: '🏷️', label: 'Exam Types', permission: 'view exam types' },
      { path: '/dashboard/exams/routines', icon: '📅', label: 'Routines', permission: 'view exam routines' },
      { path: '/dashboard/exams/results', icon: '📊', label: 'Results', permission: 'view exam results' },
      { path: '/dashboard/exams/leaderboard', icon: '🏆', label: 'Leaderboard', permission: 'view exam results' },
      { path: '/dashboard/exams/analytics', icon: '📈', label: 'Exam Analytics', permission: 'view reports' },
      { path: '/dashboard/exams/questions', icon: '❓', label: 'Question Bank', permission: 'view questions' },
      { path: '/dashboard/exams/questions/review', icon: '✅', label: 'Review Queue', permission: 'approve questions' },
      { path: '/dashboard/exams/grading-scale', icon: '🎯', label: 'Grade & GPA Scale', permission: 'view settings' },
    ]
  },
  {
    title: 'FINANCE',
    permission: 'view fee types',
    items: [
      { path: '/dashboard/finance/fee-types', icon: '💰', label: 'Fee Types', permission: 'view fee types' },
      { path: '/dashboard/finance/fee-structures', icon: '📋', label: 'Fee Structures', permission: 'view fee structures' },
      { path: '/dashboard/finance/collect-fee', icon: '💳', label: 'Collect Fee', permission: 'view fee collections' },
      { path: '/dashboard/finance/collections', icon: '📊', label: 'Collections', permission: 'view fee collections' },
      { path: '/dashboard/finance/expenses', icon: '📤', label: 'Expenses', permission: 'view expenses' },
      {
        icon: '💵',
        label: 'Payment Management',
        permission: 'view fee collections',
        children: [
          { path: '/dashboard/finance/payment-management', icon: '💳', label: 'All Payments', permission: 'view fee collections' },
          { path: '/dashboard/finance/invoices', icon: '📄', label: 'Invoices', permission: 'view fee collections' },
        ]
      },
    ]
  },
  {
    title: 'HR & PAYROLL',
    permission: 'view employees',
    items: [
      { path: '/dashboard/hr/employees', icon: '👥', label: 'Employees', permission: 'view employees' },
      { path: '/dashboard/hr/departments', icon: '🏢', label: 'Departments', permission: 'view departments' },
      { path: '/dashboard/hr/designations', icon: '📌', label: 'Designations', permission: 'view designations' },
      { path: '/dashboard/hr/staff-attendance', icon: '✓', label: 'Staff Attendance', permission: 'view staff attendance' },
      { path: '/dashboard/hr/leave-requests', icon: '📝', label: 'Leave Requests', permission: 'view leave requests' },
      { path: '/dashboard/hr/payroll', icon: '💵', label: 'Payroll', permission: 'view payroll' },
    ]
  },
  {
    title: 'COMMUNICATION',
    permission: 'view notice board',
    items: [
      { path: '/dashboard/communication/notice-board', icon: '📢', label: 'Notice Board', permission: 'view notice board' },
      { path: '/dashboard/communication/notifications', icon: '🔔', label: 'Notifications', permission: 'view notifications' },
    ]
  },
  {
    title: 'CMS',
    permission: 'view cms pages',
    items: [
      { path: '/dashboard/cms/pages', icon: '📄', label: 'Pages', permission: 'view cms pages' },
      { path: '/dashboard/cms/blog', icon: '📝', label: 'Blog', permission: 'view cms pages' },
      { path: '/dashboard/cms/sliders', icon: '🖼️', label: 'Sliders', permission: 'view sliders' },
      { path: '/dashboard/cms/events', icon: '🎉', label: 'Events', permission: 'view events' },
      { path: '/dashboard/cms/gallery', icon: '🖼️', label: 'Achievement Gallery', permission: 'view gallery' },
      { path: '/dashboard/cms/testimonials', icon: '⭐', label: 'Testimonials', permission: 'view testimonials' },
      { path: '/dashboard/cms/success-stories', icon: '🏆', label: 'Success Stories', permission: 'view success stories' },
      { path: '/dashboard/cms/study-materials', icon: '📚', label: 'Study Materials', permission: 'view study materials' },
      { path: '/dashboard/cms/download-center', icon: '📥', label: 'Download Center', permission: 'view download center' },
      { path: '/dashboard/cms/approval-queue', icon: '✅', label: 'Approval Queue', permission: 'approve cms content' },
      { path: '/dashboard/cms/audit-logs', icon: '📋', label: 'Audit Logs', permission: 'view cms audit logs' },
      { path: '/dashboard/cms/analytics', icon: '📊', label: 'Analytics', permission: 'view cms analytics' },
    ]
  },
  {
    title: 'REPORTS',
    permission: 'view reports',
    items: [
      { path: '/dashboard/reports', icon: '📈', label: 'Reports', permission: 'view reports' },
    ]
  },
  {
    title: 'SETTINGS',
    permission: 'view settings',
    items: [
      { path: '/dashboard/settings', icon: '⚙️', label: 'Settings', permission: 'view settings' },
    ]
  },
]

// =============================================
// Filter menu items based on user permissions
// =============================================
const visibleMenuItems = computed(() => {
  const role = authStore.userRole

  const canViewItem = (item) => {
    if (item.hidden) return false
    if (!item.permission) return true
    return authStore.hasPermission(item.permission)
  }

  const resolveVisibleItems = (items) => {
    return items
      .map(item => {
        if (item.children?.length) {
          const visibleChildren = item.children.filter(child => canViewItem(child))
          if (visibleChildren.length === 0) return null
          return { ...item, children: visibleChildren }
        }
        return canViewItem(item) ? item : null
      })
      .filter(Boolean)
  }

  return allMenuSections
    .map(section => {
      if (section.hideForRoles?.includes(role)) {
        return { ...section, items: [] }
      }

      // Portal users only see their dedicated sections
      if (role === 'student' && section.role !== 'student') {
        return { ...section, items: [] }
      }
      if (role === 'teacher' && section.role !== 'teacher') {
        return { ...section, items: [] }
      }
      if (role === 'guardian' && section.role !== 'guardian') {
        return { ...section, items: [] }
      }
      if (role === 'employee' && section.role !== 'employee') {
        return { ...section, items: [] }
      }

      if (section.role && role !== section.role) {
        return { ...section, items: [] }
      }

      return {
        ...section,
        items: resolveVisibleItems(section.items),
      }
    })
    .filter(section => section.items.length > 0)
})

const isActive = (path) => {
  return route.path === path || route.path.startsWith(path + '/')
}

const checkMobile = () => {
  const mobile = window.innerWidth < 768
  isMobile.value = mobile
  if (mobile) {
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

const handleLogout = () => {
  authStore.logout()
  router.push('/login')
}

const handleKeydown = (e) => {
  if (e.ctrlKey && e.key === 'b') {
    e.preventDefault()
    toggleSidebar()
  }
}

defineExpose({ toggleSidebar, isMobileOpen, isMobile, isCollapsed })

const handleToggleSidebarEvent = () => {
  toggleSidebar()
}

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
  window.addEventListener('keydown', handleKeydown)
  window.addEventListener('toggle-sidebar', handleToggleSidebarEvent)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
  window.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('toggle-sidebar', handleToggleSidebarEvent)
})
</script>

<style scoped>
/* Overlay */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: var(--bg-overlay);
  z-index: 998;
  backdrop-filter: blur(2px);
}

.sidebar-overlay.active {
  display: block;
}

/* Shell wraps sidebar + edge toggle (toggle stays visible) */
.sidebar-shell {
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  width: var(--sidebar-width);
  z-index: 1000;
  transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: visible;
}

.sidebar-shell.collapsed {
  width: var(--sidebar-collapsed-width);
}

.sidebar-shell.collapsed .nav-item {
  justify-content: center;
  padding-left: 0.65rem;
  padding-right: 0.65rem;
  margin-left: 0.35rem;
  margin-right: 0.35rem;
}

.sidebar-shell.collapsed .sidebar-header {
  justify-content: center;
  padding-left: 0.65rem;
  padding-right: 0.65rem;
}

.sidebar-shell.collapsed .brand-area {
  justify-content: center;
}

/* Sidebar */
.sidebar {
  width: 100%;
  height: 100%;
  background: var(--sidebar-bg);
  color: var(--sidebar-text);
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--sidebar-border);
  box-shadow: var(--shadow-md);
  overflow: hidden;
}

/* Header */
.sidebar-header {
  padding: 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--sidebar-border);
  min-height: 70px;
  flex-shrink: 0;
  background: var(--sidebar-header-bg);
}

.brand-area {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex: 1;
  overflow: hidden;
}

.brand-icon {
  width: 40px;
  height: 40px;
  min-width: 40px;
  background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}

.brand-text {
  overflow: hidden;
}

.brand-text h3 {
  font-size: 18px;
  font-weight: 800;
  margin: 0;
  line-height: 1.2;
  white-space: nowrap;
  color: var(--sidebar-text-heading);
}

.brand-text small {
  font-size: 10px;
  color: var(--sidebar-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
  font-weight: 700;
}

/* Edge toggle — sits on shell, fully visible */
.sidebar-edge-toggle {
  position: absolute;
  right: -14px;
  top: 26px;
  width: 28px;
  height: 28px;
  background: var(--sidebar-toggle-bg);
  border: 1px solid var(--sidebar-toggle-border);
  border-radius: 50%;
  color: var(--sidebar-toggle-text);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 1002;
  transition: all 0.2s;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12);
  padding: 0;
}

.sidebar-edge-toggle:hover {
  background: #4f46e5;
  border-color: #4f46e5;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
  transform: scale(1.05);
}

.toggle-arrow {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.toggle-arrow.rotated {
  transform: rotate(180deg);
}

/* Mobile Close */
.mobile-close-btn {
  display: none;
  color: var(--sidebar-text);
  padding: 0.5rem;
  border-radius: 8px;
  cursor: pointer;
  background: transparent;
  border: none;
}

.mobile-close-btn:hover {
  background: var(--sidebar-nav-hover-bg);
  color: var(--sidebar-text-heading);
}

/* Navigation */
.sidebar-nav {
  flex: 1;
  padding: 0.75rem 0;
  overflow-y: auto;
  overflow-x: hidden;
  min-height: 0;
}

.nav-section {
  margin-bottom: 0.35rem;
  padding-bottom: 0.35rem;
  border-bottom: 1px solid var(--sidebar-border);
}

.nav-section:last-child {
  border-bottom: none;
}

.nav-section-title {
  padding: 0.5rem 1.25rem;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: var(--sidebar-text-heading);
  font-weight: 800;
  margin-top: 0.5rem;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 1rem;
  margin: 3px 0.5rem;
  color: var(--sidebar-text);
  text-decoration: none;
  transition: background 0.2s, border-color 0.2s, color 0.2s;
  border-radius: 8px;
  border: 1px solid var(--sidebar-nav-border);
  background: var(--sidebar-nav-bg);
  position: relative;
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.nav-item:hover {
  background: var(--sidebar-nav-hover-bg);
  color: var(--sidebar-nav-hover-text);
  border-color: var(--border-strong);
}

.nav-item.router-link-active {
  background: var(--sidebar-nav-active-bg);
  color: var(--sidebar-nav-active-text);
  font-weight: 700;
  border-color: var(--sidebar-nav-active-border);
  box-shadow: none;
}

.nav-icon {
  font-size: 18px;
  min-width: 26px;
  text-align: center;
  flex-shrink: 0;
}

.nav-text {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  color: inherit;
  opacity: 1;
}

.nav-badge {
  background: var(--danger-color);
  color: white;
  font-size: 10px;
  padding: 2px 7px;
  border-radius: 10px;
  font-weight: 600;
}

/* Nav Group (expandable parent) */
.nav-group {
  margin: 2px 0;
}

.nav-parent {
  cursor: pointer;
  user-select: none;
}

.nav-parent:hover {
  background: var(--sidebar-nav-hover-bg);
  color: var(--sidebar-nav-hover-text);
  border-color: var(--border-strong);
}

.nav-parent-expanded {
  background: var(--sidebar-parent-expanded-bg);
  color: var(--sidebar-parent-expanded-text);
  border-color: var(--sidebar-parent-expanded-border);
}

.nav-expand-icon {
  font-size: 10px;
  transition: transform 0.2s ease;
  margin-left: auto;
  color: var(--sidebar-text);
  opacity: 1;
}

.nav-expand-icon.rotated {
  transform: rotate(90deg);
}

.nav-children {
  overflow: hidden;
  transition: max-height 0.3s ease;
}

.nav-child-item {
  padding-left: 2.5rem !important;
  margin: 3px 0.5rem !important;
  font-size: 13px !important;
  font-weight: 700 !important;
  color: var(--sidebar-text) !important;
  border-color: var(--sidebar-border) !important;
  background: var(--sidebar-nav-child-bg) !important;
}

.nav-child-icon {
  font-size: 14px !important;
  min-width: 20px !important;
}

.nav-child-item:hover {
  background: var(--sidebar-nav-hover-bg) !important;
  color: var(--sidebar-nav-hover-text) !important;
  border-color: var(--border-strong) !important;
}

.nav-child-item.router-link-active {
  background: var(--sidebar-nav-active-bg) !important;
  color: var(--sidebar-nav-active-text) !important;
  font-weight: 700 !important;
  border-color: var(--sidebar-nav-active-border) !important;
  box-shadow: none !important;
}

/* Sidebar Footer */
.sidebar-footer {
  border-top: 1px solid var(--sidebar-border);
  padding: 0.75rem;
  background: var(--sidebar-footer-bg);
  flex-shrink: 0;
}

.user-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.user-avatar {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  object-fit: cover;
  border: 2px solid var(--sidebar-border);
}

.user-info {
  flex: 1;
  overflow: hidden;
}

.user-info strong {
  display: block;
  font-size: 13px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: var(--sidebar-text-heading);
}

.user-info span {
  font-size: 11px;
  color: var(--sidebar-text);
  font-weight: 600;
  text-transform: capitalize;
}

.logout-btn {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 0.75rem;
  background: var(--logout-bg);
  color: var(--logout-text);
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.2s;
  cursor: pointer;
  border: 1px solid var(--logout-border);
}

.logout-btn:hover {
  background: var(--logout-hover-bg);
}

/* Collapsed Footer */
.collapsed-footer {
  border-top: 1px solid var(--sidebar-border);
  padding: 0.75rem;
  display: flex;
  justify-content: center;
  background: var(--sidebar-footer-bg);
  flex-shrink: 0;
}

.logout-icon-btn {
  width: 40px;
  height: 40px;
  background: var(--logout-bg);
  color: var(--logout-text);
  border-radius: 10px;
  font-size: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  border: 1px solid var(--logout-border);
}

.logout-icon-btn:hover {
  background: var(--logout-hover-bg);
}

/* Scrollbar */
.sidebar-nav::-webkit-scrollbar {
  width: 5px;
}

.sidebar-nav::-webkit-scrollbar-track {
  background: transparent;
}

.sidebar-nav::-webkit-scrollbar-thumb {
  background: var(--sidebar-scrollbar);
  border-radius: 3px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
  background: var(--sidebar-scrollbar-hover);
}

/* Mobile drawer */
@media (max-width: 768px) {
  .sidebar-shell {
    left: -100%;
    width: min(300px, 88vw) !important;
    transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1), width 0.3s;
  }

  .sidebar-shell.mobile-open {
    left: 0;
  }

  .sidebar-edge-toggle {
    display: none;
  }

  .mobile-close-btn {
    display: block;
  }

  .collapsed-footer {
    display: none;
  }
}

/* Tablet — respect user collapse preference */
@media (min-width: 769px) and (max-width: 1024px) {
  .sidebar-shell:not(.collapsed) {
    width: var(--sidebar-width);
  }
}
</style>

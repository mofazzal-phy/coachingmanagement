import userService from './user.service'
import enrollmentService from './enrollment.service'
import attendanceService from './attendance.service'
import smartFeeService from './smart-fee.service'
import examService from './exam.service'
import communicationService from './communication.service'
import studentPortalService from './student-portal.service'
import cmsService from './cms.service'

function unwrap(res) {
  return res?.data?.data ?? res?.data ?? null
}

async function safeFetch(fn) {
  try {
    const res = await fn()
    return unwrap(res)
  } catch {
    return null
  }
}

function buildFetches(map) {
  const keys = Object.keys(map)
  return Promise.allSettled(keys.map((key) => map[key]())).then((results) => {
    const out = {}
    keys.forEach((key, i) => {
      out[key] = results[i].status === 'fulfilled' ? results[i].value : null
    })
    return out
  })
}

function extractCount(res) {
  if (!res) return null
  const body = res?.data ?? res
  if (body?.meta?.total != null) return body.meta.total
  const list = body?.data
  if (Array.isArray(list)) return list.length
  if (Array.isArray(body)) return body.length
  return null
}

async function safeCount(fn) {
  try {
    const res = await fn()
    return extractCount(res)
  } catch {
    return null
  }
}

const PORTAL_CMS_MODULES = {
  admin: [
    { key: 'pages', permission: 'view cms pages', label: 'Pages', icon: '📄', color: '#4f46e5', meta: 'Static pages', to: '/dashboard/cms/pages', fetch: () => cmsService.pages.list({ per_page: 1 }) },
    { key: 'blog', permission: 'view cms pages', label: 'Blog', icon: '📝', color: '#2563eb', meta: 'Articles & posts', to: '/dashboard/cms/blog', fetch: () => cmsService.blog.list({ per_page: 1 }) },
    { key: 'gallery', permission: 'view gallery', label: 'Gallery', icon: '🖼️', color: '#db2777', meta: 'Photo albums', to: '/dashboard/cms/gallery', fetch: () => cmsService.galleries.list({ per_page: 1 }) },
    { key: 'testimonials', permission: 'view testimonials', label: 'Testimonials', icon: '💬', color: '#0891b2', meta: 'Reviews & quotes', to: '/dashboard/cms/testimonials', fetch: () => cmsService.testimonials.list({ per_page: 1 }) },
    { key: 'success-stories', permission: 'view success stories', label: 'Success Stories', icon: '🏆', color: '#d97706', meta: 'Student achievements', to: '/dashboard/cms/success-stories', fetch: () => cmsService.successStories.list({ per_page: 1 }) },
    { key: 'study-materials', permission: 'view study materials', label: 'Study Materials', icon: '📚', color: '#7c3aed', meta: 'Learning resources', to: '/dashboard/cms/study-materials', fetch: () => cmsService.studyMaterials.list({ per_page: 1 }) },
    { key: 'downloads', permission: 'view download center', label: 'Download Center', icon: '📥', color: '#059669', meta: 'Public files', to: '/dashboard/cms/download-center', fetch: () => cmsService.downloads.list({ per_page: 1 }) },
    { key: 'sliders', permission: 'view sliders', label: 'Sliders', icon: '🎠', color: '#ec4899', meta: 'Homepage banners', to: '/dashboard/cms/sliders', static: true },
    { key: 'events', permission: 'view events', label: 'Events', icon: '📅', color: '#06b6d4', meta: 'Upcoming events', to: '/dashboard/cms/events', static: true },
    { key: 'notices', permission: 'view notice board', label: 'Notice Board', icon: '📢', color: '#b45309', meta: 'Announcements', to: '/dashboard/communication/notice-board', fetch: () => communicationService.notices.list({ per_page: 1 }) },
    { key: 'approval-queue', permission: 'approve cms content', label: 'Approval Queue', icon: '✅', color: '#dc2626', meta: 'Pending review', to: '/dashboard/cms/approval-queue', fetch: () => cmsService.foundation.approvalQueue({ per_page: 1 }) },
    { key: 'audit-logs', permission: 'view cms audit logs', label: 'Audit Logs', icon: '📋', color: '#64748b', meta: 'Content history', to: '/dashboard/cms/audit-logs', fetch: () => cmsService.foundation.auditLogs({ per_page: 1 }) },
    { key: 'analytics', permission: 'view cms analytics', label: 'Analytics', icon: '📊', color: '#6366f1', meta: 'Engagement insights', to: '/dashboard/cms/analytics', static: true },
  ],
  teacher: [
    { key: 'study-materials', permission: 'view study materials', label: 'Study Materials', icon: '📚', color: '#7c3aed', meta: 'Course resources', to: '/dashboard/cms/study-materials', fetch: () => cmsService.studyMaterials.list({ per_page: 1 }) },
    { key: 'notices', permission: 'view notice board', label: 'Notice Board', icon: '📢', color: '#db2777', meta: 'Announcements', to: '/dashboard/communication/notice-board', fetch: () => communicationService.notices.list({ per_page: 1 }) },
    { key: 'downloads', permission: 'view download center', label: 'Downloads', icon: '📥', color: '#059669', meta: 'Shared files', to: '/dashboard/cms/download-center', fetch: () => cmsService.downloads.list({ per_page: 1 }) },
  ],
  student: [
    { key: 'study-materials', permission: 'view study materials', label: 'Study Materials', icon: '📚', color: '#7c3aed', meta: 'Your resources', to: '/student/study-materials', fetch: () => studentPortalService.studyMaterials() },
    { key: 'downloads', permission: 'view download center', label: 'Downloads', icon: '📥', color: '#059669', meta: 'Available files', to: '/student/downloads', fetch: () => studentPortalService.downloads() },
    { key: 'notices', permission: 'view notice board', label: 'Notices', icon: '📢', color: '#db2777', meta: 'Announcements', to: '/student/notices', fetch: () => studentPortalService.notices() },
  ],
  employee: [
    { key: 'notices', permission: 'view notice board', label: 'Notices', icon: '📢', color: '#db2777', meta: 'Announcements', to: '/employee/notices', fetch: () => communicationService.notices.list({ per_page: 1 }) },
    { key: 'downloads', permission: 'view download center', label: 'Downloads', icon: '📥', color: '#059669', meta: 'Shared files', to: '/dashboard/cms/download-center', fetch: () => cmsService.downloads.list({ per_page: 1 }) },
  ],
  guardian: [
    { key: 'notices', permission: 'view notice board', label: 'Notices', icon: '📢', color: '#0891b2', meta: 'Family updates', to: '/guardian/notices', fetch: () => communicationService.notices.list({ per_page: 1 }) },
    { key: 'downloads', permission: 'view download center', label: 'Downloads', icon: '📥', color: '#059669', meta: 'Shared files', to: '/dashboard/cms/download-center', fetch: () => cmsService.downloads.list({ per_page: 1 }) },
  ],
}

export default {
  unwrap,
  safeFetch,

  async fetchAdminData(hasPermission) {
    const fetches = {}

    if (hasPermission('view users')) {
      fetches.userStats = () => safeFetch(() => userService.stats())
    }
    if (hasPermission('view students') || hasPermission('view reports')) {
      fetches.overview = () => safeFetch(() => enrollmentService.getReportOverview())
      fetches.revenueTrend = () => safeFetch(() => enrollmentService.getReportRevenueTrend(6))
      fetches.occupancy = () => safeFetch(() => enrollmentService.getReportOccupancy())
      fetches.modeWise = () => safeFetch(() => enrollmentService.getModeWiseReport())
    }
    if (hasPermission('view courses')) {
      fetches.courseStats = () => safeFetch(() => enrollmentService.getCourseStatistics())
    }
    if (hasPermission('view batches')) {
      fetches.batchStats = () => safeFetch(() => enrollmentService.getBatchStatistics())
    }
    if (hasPermission('view attendance') || hasPermission('view student attendance')) {
      fetches.attendanceToday = () => safeFetch(() => attendanceService.getTodayOverview())
      fetches.attendanceTrend = () => safeFetch(() => attendanceService.getMonthlyTrend({ months: 6 }))
      fetches.realtime = () => safeFetch(() => attendanceService.getRealtimeData({ limit: 8 }))
      fetches.lowAlerts = () => safeFetch(() => attendanceService.getLowAttendanceAlerts({ limit: 5 }))
    }
    if (hasPermission('view fee collections')) {
      fetches.feeDashboard = () => safeFetch(() => smartFeeService.admin.dashboard())
    }
    if (hasPermission('view exams')) {
      fetches.exams = () => safeFetch(() => examService.exams.list({ per_page: 1, status: 'published' }))
    }
    if (hasPermission('view notifications') || hasPermission('view notice board')) {
      fetches.unread = () => safeFetch(() => communicationService.unreadCount())
    }

    return buildFetches(fetches)
  },

  async fetchStudentData() {
    return buildFetches({
      portal: () => safeFetch(() => studentPortalService.getDashboard()),
      exams: () => safeFetch(() => studentPortalService.getExams()),
    })
  },

  async fetchTeacherData(hasPermission) {
    const fetches = {}
    if (hasPermission('view attendance') || hasPermission('view student attendance')) {
      fetches.attendanceToday = () => safeFetch(() => attendanceService.getTodayOverview())
    }
    if (hasPermission('view exams')) {
      fetches.exams = () => safeFetch(() => examService.exams.list({ per_page: 5, status: 'published' }))
    }
    return buildFetches(fetches)
  },

  async fetchEmployeeData(hasPermission) {
    const fetches = {}
    if (hasPermission('view staff attendance') || hasPermission('view attendance')) {
      fetches.attendanceToday = () => safeFetch(() => attendanceService.getTodayOverview())
    }
    return buildFetches(fetches)
  },

  async fetchPortalCmsSummary(hasPermission, portal = 'admin') {
    const modules = PORTAL_CMS_MODULES[portal] || []
    const visible = modules.filter((m) => hasPermission(m.permission))
    if (!visible.length) return []

    const counts = await Promise.all(
      visible.map(async (mod) => {
        if (mod.static) {
          return { ...mod, value: 'Open' }
        }
        const count = await safeCount(mod.fetch)
        return {
          ...mod,
          value: count != null ? count : '—',
        }
      }),
    )

    return counts
  },
}

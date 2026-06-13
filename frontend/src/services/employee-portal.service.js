import apiClient from './api.service'
import hrService from './hr.service'
import attendanceService from './attendance.service'

const extractList = (res) => {
  const body = res?.data
  if (Array.isArray(body?.data)) return body.data
  if (Array.isArray(body)) return body
  return []
}

export default {
  async findEmployee(user) {
    if (!user?.id) return null

    const terms = [user.email, user.name, user.phone].filter(Boolean)

    for (const term of terms) {
      try {
        const res = await hrService.employees.list({ search: term, per_page: 50 })
        const list = extractList(res)
        const match = list.find((e) => e.user_id === user.id)
        if (match) return match
      } catch {
        // ignore
      }
    }

    try {
      const res = await hrService.employees.list({ per_page: 100 })
      const list = extractList(res)
      return list.find((e) => e.user_id === user.id) || null
    } catch {
      return null
    }
  },

  async profile(user) {
    const emp = await this.findEmployee(user)
    if (!emp?.id) return null
    try {
      const res = await hrService.employees.get(emp.id)
      return res.data?.data || res.data || emp
    } catch {
      return emp
    }
  },

  myAttendance() {
    return attendanceService.getMyAttendance()
  },

  leaveRequests(employeeId) {
    return hrService.leaveRequests.list({ employee_id: employeeId, per_page: 10 })
  },

  payroll(employeeId) {
    return hrService.payroll.list({ employee_id: employeeId, per_page: 5 })
  },

  notices() {
    return apiClient.get('/notices/published')
  },
}

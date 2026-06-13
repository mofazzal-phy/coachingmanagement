import apiClient from './api.service'
import studentService from './student.service'
import attendanceService from './attendance.service'
import examService from './exam.service'

const extractList = (res) => {
  const body = res?.data
  if (Array.isArray(body?.data)) return body.data
  if (Array.isArray(body)) return body
  return []
}

const normalizePhone = (phone) => String(phone || '').replace(/\D/g, '').slice(-10)

const phoneMatches = (a, b) => {
  const na = normalizePhone(a)
  const nb = normalizePhone(b)
  return na && nb && (na === nb || na.endsWith(nb) || nb.endsWith(na))
}

const matchesGuardian = (student, user) => {
  if (!user) return false
  const userEmail = (user.email || '').toLowerCase().trim()
  const userPhone = user.phone || ''
  const g = student.guardian || {}

  const phones = [
    g.guardian_phone, g.father_phone, g.mother_phone,
    student.guardian_phone, student.father_phone, student.mother_phone,
  ].filter(Boolean)

  const emails = [
    g.guardian_email, g.father_email, g.mother_email,
    student.guardian_email, student.email,
  ].map((e) => (e || '').toLowerCase().trim()).filter(Boolean)

  if (userEmail && emails.includes(userEmail)) return true
  if (userPhone && phones.some((p) => phoneMatches(p, userPhone))) return true
  return false
}

export default {
  async children(user) {
    const found = new Map()
    const terms = [user?.phone, user?.email].filter(Boolean)

    const addStudents = (list, requireMatch = true) => {
      list.forEach((student) => {
        if (!requireMatch || matchesGuardian(student, user)) {
          found.set(student.id, student)
        }
      })
    }

    await Promise.all(terms.map(async (term) => {
      try {
        const res = await studentService.list({
          search: term,
          per_page: 50,
          enrollment_status: 'enrolled',
        })
        addStudents(extractList(res), true)
      } catch {
        // ignore
      }
    }))

    if (found.size === 0 && terms.length) {
      try {
        const res = await studentService.list({ search: terms[0], per_page: 50 })
        addStudents(extractList(res), false)
      } catch {
        // ignore
      }
    }

    return Array.from(found.values())
  },

  child(id) {
    return studentService.get(id)
  },

  feeSummary(id) {
    return studentService.getFeeSummary(id)
  },

  attendanceSummary(studentId) {
    return attendanceService.getStudentSummary({ student_id: studentId })
  },

  notices() {
    return apiClient.get('/notices/published')
  },

  upcomingExams() {
    return examService.exams.list({ status: 'published', per_page: 10 })
  },
}

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import examService from '@/services/exam.service'

// Subject color palette for color-coding
const SUBJECT_COLORS = [
  '#6366F1', '#8B5CF6', '#EC4899', '#EF4444', '#F97316',
  '#EAB308', '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
  '#7C3AED', '#DB2777', '#DC2626', '#EA580C', '#D97706',
  '#65A30D', '#059669', '#0D9488', '#0284C7', '#4F46E5',
]

export const useExamRoutineStore = defineStore('examRoutine', () => {
  // ===== STATE =====
  const routines = ref([])
  const exams = ref([])
  const loading = ref(false)
  const error = ref(null)
  const selectedExam = ref(null)
  const selectedLevel = ref('class')
  const selectedLevelId = ref(null)
  const viewMode = ref('grid') // 'grid', 'table', 'calendar'

  // ===== COMPUTED =====
  const totalSlots = computed(() => routines.value.length)

  const publishedCount = computed(() => routines.value.filter(r => r.status === 'published').length)

  const draftCount = computed(() => routines.value.filter(r => r.status === 'draft').length)

  const completedCount = computed(() => routines.value.filter(r => r.status === 'completed').length)

  const cancelledCount = computed(() => routines.value.filter(r => r.status === 'cancelled').length)

  // Group routines by date
  const groupedByDate = computed(() => {
    const groups = {}
    routines.value.forEach(r => {
      const date = r.exam_date || (r.exam_date ? (typeof r.exam_date === 'string' ? r.exam_date.substring(0, 10) : '') : '')
      if (!groups[date]) groups[date] = []
      groups[date].push(r)
    })
    // Sort each group by start_time
    Object.keys(groups).forEach(date => {
      groups[date].sort((a, b) => (a.start_time || '00:00').localeCompare(b.start_time || '00:00'))
    })
    return groups
  })

  // Subject color mapping
  const subjectColorMap = computed(() => {
    const map = {}
    let colorIndex = 0
    const seen = new Set()
    routines.value.forEach(r => {
      const subjId = r.subject_id || r.subject?.id
      if (subjId && !seen.has(subjId)) {
        seen.add(subjId)
        map[subjId] = SUBJECT_COLORS[colorIndex % SUBJECT_COLORS.length]
        colorIndex++
      }
    })
    return map
  })

  // Stats
  const stats = computed(() => {
    const total = routines.value.length
    const uniqueSubjects = new Set(routines.value.map(r => r.subject_id || r.subject?.id).filter(Boolean)).size
    const uniqueTeachers = new Set(routines.value.map(r => r.teacher_id || r.teacher?.id).filter(Boolean)).size
    const uniqueRooms = new Set(routines.value.map(r => r.room_id || r.room?.id).filter(Boolean)).size
    const uniqueDates = new Set(routines.value.map(r => r.exam_date ? (typeof r.exam_date === 'string' ? r.exam_date.substring(0, 10) : '') : '')).size

    return {
      total,
      published: publishedCount.value,
      drafts: draftCount.value,
      completed: completedCount.value,
      cancelled: cancelledCount.value,
      uniqueSubjects,
      uniqueTeachers,
      uniqueRooms,
      uniqueDates,
    }
  })

  // ===== ACTIONS =====

  /**
   * Fetch all exams (unfiltered).
   */
  const fetchAllExams = async () => {
    loading.value = true
    error.value = null
    try {
      const res = await examService.exams.list({ include_all: 1 })
      const data = res.data?.data || []
      exams.value = data.filter((e) => !e.is_practice)
      if (!exams.value.length) {
        console.warn('[ExamRoutineStore] fetchAllExams returned empty', res.data)
      }
    } catch (e) {
      console.error('[ExamRoutineStore] fetchAllExams error:', e)
      error.value = e.response?.data?.message || 'Failed to load exams'
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch exams by level.
   */
  const fetchExams = async (level, levelId) => {
    loading.value = true
    error.value = null
    try {
      let res
      if (level === 'batch') res = await examService.exams.byBatch(levelId)
      else if (level === 'course') res = await examService.exams.byCourse(levelId)
      else res = await examService.exams.byClass(levelId)
      const data = res.data?.data || []
      exams.value = data.filter((e) => !e.is_practice)
      if (!exams.value.length) {
        console.warn(`[ExamRoutineStore] fetchExams returned empty for ${level}=${levelId}`, res.data)
      }
    } catch (e) {
      console.error('[ExamRoutineStore] fetchExams error:', e)
      error.value = e.response?.data?.message || 'Failed to load exams'
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch routines by level.
   */
  const fetchRoutines = async (params = {}) => {
    loading.value = true
    error.value = null
    try {
      const res = await examService.routines.list(params)
      routines.value = res.data?.data || []
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load routines'
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch routines by exam.
   */
  const fetchByExam = async (examId) => {
    loading.value = true
    error.value = null
    try {
      const res = await examService.routines.byExam(examId)
      routines.value = res.data?.data || []
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load'
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch routines by batch.
   */
  const fetchByBatch = async (batchId, params = {}) => {
    loading.value = true
    error.value = null
    try {
      const res = await examService.routines.byBatch(batchId, params)
      routines.value = res.data?.data || []
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load'
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch routines by course.
   */
  const fetchByCourse = async (courseId, params = {}) => {
    loading.value = true
    error.value = null
    try {
      const res = await examService.routines.byCourse(courseId, params)
      routines.value = res.data?.data || []
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load'
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch routines by class.
   */
  const fetchByClass = async (classId, params = {}) => {
    loading.value = true
    error.value = null
    try {
      const res = await examService.routines.byClass(classId, params)
      routines.value = res.data?.data || []
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load'
    } finally {
      loading.value = false
    }
  }

  /**
   * Create a single routine.
   */
  const createRoutine = async (data) => {
    error.value = null
    try {
      const res = await examService.routines.create(data)
      return res.data?.data || res.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to create routine'
      throw e
    }
  }

  /**
   * Update a routine.
   */
  const updateRoutine = async (id, data) => {
    error.value = null
    try {
      const res = await examService.routines.update(id, data)
      return res.data?.data || res.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to update routine'
      throw e
    }
  }

  /**
   * Delete a routine.
   */
  const deleteRoutine = async (id) => {
    error.value = null
    try {
      await examService.routines.delete(id)
      routines.value = routines.value.filter(r => r.id !== id)
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to delete routine'
      throw e
    }
  }

  /**
   * Bulk store routines.
   */
  const bulkStore = async (data) => {
    error.value = null
    try {
      const res = await examService.routines.bulkStore(data)
      return res.data?.data || res.data
    } catch (e) {
      // Extract validation error details for better user feedback
      const errData = e.response?.data
      if (errData?.errors) {
        const messages = Object.values(errData.errors).flat()
        error.value = messages.join('; ')
      } else {
        error.value = errData?.message || 'Bulk store failed'
      }
      throw e
    }
  }

  /**
   * Auto-generate routines.
   */
  const generate = async (data) => {
    loading.value = true
    error.value = null
    try {
      const res = await examService.routines.generate(data)
      return res.data?.data || res.data
    } catch (e) {
      const msg = e.response?.data?.message || 'Generation failed'
      const errors = e.response?.data?.errors
      if (errors) {
        const details = Object.values(errors).flat().join('; ')
        error.value = msg + ': ' + details
        console.error('[ExamRoutineStore] Generate validation errors:', errors)
      } else {
        error.value = msg
      }
      console.error('[ExamRoutineStore] Generate failed:', e.response?.data || e.message)
      throw e
    } finally {
      loading.value = false
    }
  }

  /**
   * Publish routines for an exam.
   */
  const publishRoutines = async (examId, params = {}) => {
    error.value = null
    try {
      await examService.routines.publish(examId, params)
    } catch (e) {
      error.value = e.response?.data?.message || 'Publish failed'
      throw e
    }
  }

  /**
   * Complete routines for an exam.
   */
  const completeRoutines = async (examId) => {
    error.value = null
    try {
      await examService.routines.complete(examId)
    } catch (e) {
      error.value = e.response?.data?.message || 'Complete failed'
      throw e
    }
  }

  /**
   * Cancel routines for an exam.
   */
  const cancelRoutines = async (examId) => {
    error.value = null
    try {
      await examService.routines.cancel(examId)
    } catch (e) {
      error.value = e.response?.data?.message || 'Cancel failed'
      throw e
    }
  }

  /**
   * Fetch conflicts for an exam.
   */
  const fetchConflicts = async (examId, params = {}) => {
    loading.value = true
    error.value = null
    try {
      const res = await examService.routines.conflicts(examId, params)
      const payload = res.data?.data
      return payload?.conflicts ?? (Array.isArray(payload) ? payload : [])
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to fetch conflicts'
      return []
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch calendar data for an exam.
   */
  const fetchCalendar = async (examId, params = {}) => {
    loading.value = true
    error.value = null
    try {
      const res = await examService.routines.calendar(examId, params)
      return res.data?.data || {}
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to fetch calendar'
      return {}
    } finally {
      loading.value = false
    }
  }

  /**
   * Clear routines.
   */
  const clearRoutines = () => {
    routines.value = []
    error.value = null
  }

  return {
    // State
    routines, exams, loading, error,
    selectedExam, selectedLevel, selectedLevelId, viewMode,
    // Computed
    totalSlots, publishedCount, draftCount, completedCount, cancelledCount,
    groupedByDate, subjectColorMap, stats,
    // Actions
    fetchAllExams, fetchExams, fetchRoutines,
    fetchByExam, fetchByBatch, fetchByCourse, fetchByClass,
    createRoutine, updateRoutine, deleteRoutine,
    bulkStore, generate,
    publishRoutines, completeRoutines, cancelRoutines,
    fetchConflicts, fetchCalendar, clearRoutines,
  }
})

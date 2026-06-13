import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import classRoutineService from '@/services/class-routine.service'

// Subject color palette for color-coding
const SUBJECT_COLORS = [
  '#6366F1', '#8B5CF6', '#EC4899', '#EF4444', '#F97316',
  '#EAB308', '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
  '#7C3AED', '#DB2777', '#DC2626', '#EA580C', '#D97706',
  '#65A30D', '#059669', '#0D9488', '#0284C7', '#4F46E5',
]

// Batch color palette for multi-batch display
const BATCH_COLORS = [
  '#6366F1', '#EC4899', '#14B8A6', '#F97316',
  '#84CC16', '#8B5CF6', '#06B6D4', '#F43F5E',
  '#0EA5E9', '#D946EF', '#10B981', '#F59E0B',
]

export const useClassRoutineStore = defineStore('classRoutine', () => {
  // ===== STATE =====
  const routines = ref([])
  const loading = ref(false)
  const error = ref(null)
  const selectedLevel = ref('class')
  const selectedLevelId = ref(null)
  const selectedSessionId = ref(null)
  const swapMode = ref(false)

  // Multi-batch state
  const selectedBatchIds = ref([])
  const multiBatchGrid = ref(null)
  const multiBatchStats = ref(null)
  const conflicts = ref([])
  const viewMode = ref('grid') // grid, teacher, room, batch

  // Day helpers
  const days = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri']
  const dayNames = {
    sat: 'Saturday', sun: 'Sunday', mon: 'Monday', tue: 'Tuesday',
    wed: 'Wednesday', thu: 'Thursday', fri: 'Friday',
  }
  const today = new Date().toLocaleDateString('en-US', { weekday: 'short' }).toLowerCase()
  const dayMap = { sat: 'sat', sun: 'sun', mon: 'mon', tue: 'tue', wed: 'wed', thu: 'thu', fri: 'fri' }
  const todayKey = dayMap[today] || 'mon'

  // Get the current week's dates for each day (Saturday-based week)
  const weekDates = computed(() => {
    const now = new Date()
    const currentDay = now.getDay()
    const satOffset = (currentDay + 1) % 7
    const saturday = new Date(now)
    saturday.setDate(now.getDate() - satOffset)
    
    const dates = {}
    const dayOrder = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri']
    dayOrder.forEach((key, index) => {
      const d = new Date(saturday)
      d.setDate(saturday.getDate() + index)
      dates[key] = {
        date: d,
        formatted: d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
        fullDate: d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
        isPast: d < new Date(now.setHours(0, 0, 0, 0)),
        isFuture: d > new Date(now.setHours(23, 59, 59, 999)),
      }
    })
    return dates
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

  // Batch color mapping
  const batchColorMap = computed(() => {
    const map = {}
    const allRoutines = multiBatchGrid.value?.time_slots?.flatMap(slot =>
      Object.values(slot.cells).flatMap(cell => cell.routines)
    ) || routines.value
    let colorIndex = 0
    const seen = new Set()
    allRoutines.forEach(r => {
      const batchId = r.batch_id || r.batch?.id
      if (batchId && !seen.has(batchId)) {
        seen.add(batchId)
        map[batchId] = BATCH_COLORS[colorIndex % BATCH_COLORS.length]
        colorIndex++
      }
    })
    return map
  })

  // ===== COMPUTED =====
  const weeklyGrid = computed(() => {
    const grid = {}
    days.forEach(d => { grid[d] = [] })
    routines.value.forEach(r => {
      if (grid[r.day_of_week]) grid[r.day_of_week].push(r)
    })
    days.forEach(d => {
      grid[d].sort((a, b) => (a.start_time || '00:00').localeCompare(b.start_time || '00:00'))
    })
    return grid
  })

  const todayClasses = computed(() => weeklyGrid.value[todayKey] || [])

  const liveNowClass = computed(() => {
    const now = new Date()
    const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
    return todayClasses.value.find(r => {
      if (!r.start_time || !r.end_time) return false
      return currentTime >= r.start_time && currentTime <= r.end_time
    }) || null
  })

  const upcomingClasses = computed(() => {
    const now = new Date()
    const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
    return todayClasses.value.filter(r => {
      if (!r.start_time) return false
      return r.start_time > currentTime
    })
  })

  const totalSlots = computed(() => routines.value.length)
  const publishedCount = computed(() => routines.value.filter(r => r.status === 'published').length)
  const draftCount = computed(() => routines.value.filter(r => r.status === 'draft').length)

  // Weekly Stats
  const weeklyStats = computed(() => {
    const total = routines.value.length
    const published = routines.value.filter(r => r.status === 'published').length
    const drafts = routines.value.filter(r => r.status === 'draft').length
    const archived = routines.value.filter(r => r.status === 'archived').length
    const uniqueSubjects = new Set(routines.value.map(r => r.subject_id || r.subject?.id).filter(Boolean)).size
    const uniqueTeachers = new Set(routines.value.map(r => r.teacher_id || r.teacher?.id).filter(Boolean)).size
    const uniqueRooms = new Set(routines.value.map(r => r.room_id || r.room?.id).filter(Boolean)).size
    const todayCount = todayClasses.value.length
    const liveCount = liveNowClass.value ? 1 : 0
    const upcomingCount = upcomingClasses.value.length
    const busyDays = days.filter(d => (weeklyGrid.value[d] || []).length > 0).length

    return {
      total, published, drafts, archived,
      uniqueSubjects, uniqueTeachers, uniqueRooms,
      todayCount, liveCount, upcomingCount, busyDays,
    }
  })

  // ===== ACTIONS =====
  const fetchRoutines = async (params = {}) => {
    loading.value = true
    error.value = null
    try {
      const res = await classRoutineService.getRoutines(params)
      routines.value = res.data?.data || []
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load routines'
    } finally {
      loading.value = false
    }
  }

  const fetchByBatch = async (batchId) => {
    loading.value = true
    error.value = null
    try {
      const res = await classRoutineService.getByBatch(batchId)
      routines.value = res.data?.data || []
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load'
    } finally {
      loading.value = false
    }
  }

  const fetchByCourse = async (courseId) => {
    loading.value = true
    error.value = null
    try {
      const res = await classRoutineService.getByCourse(courseId)
      routines.value = res.data?.data || []
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load'
    } finally {
      loading.value = false
    }
  }

  const fetchByClass = async (classId) => {
    loading.value = true
    error.value = null
    try {
      const res = await classRoutineService.getByClass(classId)
      routines.value = res.data?.data || []
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load'
    } finally {
      loading.value = false
    }
  }

  // ===== Multi-Batch Actions =====
  const fetchMultiBatchGrid = async (batchIds, filters = {}) => {
    loading.value = true
    error.value = null
    try {
      const params = { batch_ids: batchIds, ...filters }
      const res = await classRoutineService.getMultiBatchGrid(params)
      multiBatchGrid.value = res.data?.data || null
      selectedBatchIds.value = batchIds
      return multiBatchGrid.value
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load multi-batch grid'
      return null
    } finally {
      loading.value = false
    }
  }

  const fetchMultiBatchStats = async (batchIds) => {
    try {
      const params = { batch_ids: batchIds }
      const res = await classRoutineService.getMultiBatchStats(params)
      multiBatchStats.value = res.data?.data || null
      return multiBatchStats.value
    } catch (e) {
      return null
    }
  }

  const fetchMultiBatchConflicts = async (batchIds) => {
    loading.value = true
    error.value = null
    try {
      const params = { batch_ids: batchIds }
      const res = await classRoutineService.getMultiBatchConflicts(params)
      conflicts.value = res.data?.data?.conflicts || []
      return conflicts.value
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to fetch conflicts'
      return []
    } finally {
      loading.value = false
    }
  }

  const createRoutine = async (data) => {
    error.value = null
    try {
      const res = await classRoutineService.createRoutine(data)
      return res.data?.data || res.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to create routine'
      throw e
    }
  }

  const updateRoutine = async (id, data) => {
    error.value = null
    try {
      const res = await classRoutineService.updateRoutine(id, data)
      return res.data?.data || res.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to update routine'
      throw e
    }
  }

  const deleteRoutine = async (id) => {
    error.value = null
    try {
      await classRoutineService.deleteRoutine(id)
      routines.value = routines.value.filter(r => r.id !== id)
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to delete routine'
      throw e
    }
  }

  const generate = async (data) => {
    loading.value = true
    error.value = null
    try {
      const res = await classRoutineService.generate(data)
      return res.data?.data || res.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Generation failed'
      throw e
    } finally {
      loading.value = false
    }
  }

  const swapSlots = async (slot1Id, slot2Id) => {
    error.value = null
    try {
      await classRoutineService.swap({ slot1_id: slot1Id, slot2_id: slot2Id })
    } catch (e) {
      error.value = e.response?.data?.message || 'Swap failed'
      throw e
    }
  }

  const publishRoutine = async (level, levelId) => {
    error.value = null
    try {
      await classRoutineService.publish({ level, level_id: levelId })
    } catch (e) {
      error.value = e.response?.data?.message || 'Publish failed'
      throw e
    }
  }

  const publishMultiBatch = async (batchIds) => {
    error.value = null
    try {
      await classRoutineService.publishMultiBatch({ batch_ids: batchIds })
    } catch (e) {
      error.value = e.response?.data?.message || 'Publish failed'
      throw e
    }
  }

  const archiveRoutine = async (level, levelId) => {
    error.value = null
    try {
      await classRoutineService.archive({ level, level_id: levelId })
    } catch (e) {
      error.value = e.response?.data?.message || 'Archive failed'
      throw e
    }
  }

  const archiveMultiBatch = async (batchIds) => {
    error.value = null
    try {
      await classRoutineService.archiveMultiBatch({ batch_ids: batchIds })
    } catch (e) {
      error.value = e.response?.data?.message || 'Archive failed'
      throw e
    }
  }

  const fetchConflicts = async (params) => {
    loading.value = true
    error.value = null
    try {
      const res = await classRoutineService.getConflicts(params)
      return res.data?.data || []
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to fetch conflicts'
      return []
    } finally {
      loading.value = false
    }
  }

  const bulkStore = async (data) => {
    error.value = null
    try {
      const res = await classRoutineService.bulkStore(data)
      return res.data?.data || res.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Bulk store failed'
      throw e
    }
  }

  const setLunchBreak = async (data) => {
    error.value = null
    try {
      const res = await classRoutineService.setLunchBreak(data)
      return res.data?.data || res.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to set lunch break'
      throw e
    }
  }

  const setOffDay = async (data) => {
    error.value = null
    try {
      const res = await classRoutineService.setOffDay(data)
      return res.data?.data || res.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to set off day'
      throw e
    }
  }

  const clearRoutines = () => {
    routines.value = []
    multiBatchGrid.value = null
    multiBatchStats.value = null
    conflicts.value = []
    selectedBatchIds.value = []
    error.value = null
  }

  return {
    // State
    routines, loading, error,
    selectedLevel, selectedLevelId, selectedSessionId,
    swapMode,
    // Multi-batch state
    selectedBatchIds, multiBatchGrid, multiBatchStats,
    conflicts, viewMode,
    // Computed
    weeklyGrid, todayClasses, liveNowClass, upcomingClasses,
    totalSlots, publishedCount, draftCount,
    weeklyStats, subjectColorMap, batchColorMap, weekDates,
    days, dayNames, todayKey,
    // Actions
    fetchRoutines, fetchByBatch, fetchByCourse, fetchByClass,
    fetchMultiBatchGrid, fetchMultiBatchStats, fetchMultiBatchConflicts,
    createRoutine, updateRoutine, deleteRoutine,
    generate, swapSlots, publishRoutine, publishMultiBatch,
    archiveRoutine, archiveMultiBatch,
    fetchConflicts, clearRoutines, bulkStore,
    setLunchBreak, setOffDay,
  }
})

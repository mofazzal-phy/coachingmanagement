import { routineMarksLockedByLifecycle } from '@/utils/routineLifecycle.utils'

/** Whether marks can be entered (published + exam slot end time has passed). */
export function isRoutineOpenForMarks(routine) {
  if (!routine) return false
  if (!['published', 'completed'].includes(routine.status)) return false

  const dateStr = typeof routine.exam_date === 'string'
    ? routine.exam_date.substring(0, 10)
    : routine.exam_date
  let endTime = routine.end_time || '23:59:59'
  if (typeof endTime === 'string' && endTime.length >= 5) {
    endTime = endTime.substring(0, 5)
  }
  const slotEnd = new Date(`${dateStr}T${endTime}:00`)
  return !Number.isNaN(slotEnd.getTime()) && new Date() >= slotEnd
}

/** Teachers enter marks only after the routine slot end date/time (same slot rule as admin). */
export function isRoutineOpenForTeacherMarks(routine, meta = {}) {
  if (!routine) return false
  if (routine.status === 'cancelled') return false
  if (!['published', 'completed'].includes(routine.status || '')) return false
  if (!isRoutineOpenForMarks(routine)) return false
  if (routineMarksLockedByLifecycle(routine, meta)) return false
  return true
}

export function teacherMarksClosedMessage(routine, meta = {}) {
  if (!routine) return 'Routine not found'
  if (routine.status === 'cancelled') return 'This exam slot was cancelled.'
  if (!['published', 'completed'].includes(routine.status || '')) {
    return 'This routine is not published yet. Marks entry opens after admin publishes the schedule.'
  }
  if (!isRoutineOpenForMarks(routine)) {
    return routineMarksClosedMessage(routine)
  }
  if (routineMarksLockedByLifecycle(routine, meta)) {
    return 'Results are already published to students. Use Pending subjects to edit unpublished marks.'
  }
  return ''
}

export function teacherMarksEntryStatus(routine, meta = {}) {
  if (!routine) return 'unavailable'
  if (isRoutineOpenForTeacherMarks(routine, meta)) return 'open'
  if (routineMarksLockedByLifecycle(routine, meta)) return 'locked'
  if (!['published', 'completed'].includes(routine.status || '')) return 'scheduled'
  if (!isRoutineOpenForMarks(routine)) return 'waiting'
  return 'unavailable'
}

export function routineMarksClosedMessage(routine) {
  if (!routine) return 'Routine not found'
  if (!['published', 'completed'].includes(routine.status)) {
    return 'Publish this routine first. Marks entry opens after the exam slot ends.'
  }
  const dateStr = typeof routine.exam_date === 'string' ? routine.exam_date.substring(0, 10) : routine.exam_date
  const endTime = (routine.end_time || '23:59').toString().substring(0, 5)
  return `Exam slot ends ${dateStr} at ${endTime}. Marks entry opens after that time.`
}

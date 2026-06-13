/** Realistic per-slot exam routine lifecycle (not whole-exam "completed"). */

export const ROUTINE_LIFECYCLE = {
  cancelled: { label: 'Cancelled', short: 'Cancelled', css: 'lifecycle-cancelled' },
  draft: { label: 'Draft schedule', short: 'Draft', css: 'lifecycle-draft' },
  scheduled: { label: 'Scheduled', short: 'Scheduled', css: 'lifecycle-scheduled' },
  awaiting_marks: { label: 'Awaiting marks', short: 'Marks due', css: 'lifecycle-awaiting-marks' },
  awaiting_publish: { label: 'Marks submitted', short: 'Pending publish', css: 'lifecycle-awaiting-publish' },
  results_out: { label: 'Results published', short: 'Results out', css: 'lifecycle-results-out' },
  completed: { label: 'Routine completed', short: 'Done', css: 'lifecycle-completed' },
}

export function isExamSlotEnded(routine) {
  if (!routine?.exam_date) return false
  const dateStr = String(routine.exam_date).substring(0, 10)
  let endTime = routine.end_time || '23:59:59'
  if (typeof endTime === 'string' && endTime.length >= 5) {
    endTime = endTime.substring(0, 5)
  }
  const slotEnd = new Date(`${dateStr}T${endTime}:00`)
  return !Number.isNaN(slotEnd.getTime()) && new Date() >= slotEnd
}

/**
 * @param {object} routine
 * @param {{ results_published_count?: number, results_pending_count?: number, lifecycle?: string }} [meta]
 */
export function resolveRoutineLifecycle(routine, meta = {}) {
  if (meta.lifecycle) return meta.lifecycle

  const status = routine?.status || 'draft'
  if (status === 'cancelled') return 'cancelled'
  if (status === 'draft') return 'draft'
  if (status === 'completed') return 'completed'

  const published = Number(meta.results_published_count ?? routine.results_published_count ?? 0)
  const pending = Number(meta.results_pending_count ?? routine.results_pending_count ?? 0)

  if (!isExamSlotEnded(routine)) {
    return 'scheduled'
  }

  if (published > 0 && pending === 0) {
    return 'results_out'
  }
  if (pending > 0) {
    return 'awaiting_publish'
  }
  if (published > 0) {
    return 'results_out'
  }

  return 'awaiting_marks'
}

export function routineLifecycleMeta(phase) {
  return ROUTINE_LIFECYCLE[phase] || ROUTINE_LIFECYCLE.scheduled
}

export function routineMarksLockedByLifecycle(routine, meta = {}) {
  const phase = resolveRoutineLifecycle(routine, meta)
  return phase === 'results_out' || phase === 'completed' || phase === 'cancelled'
}

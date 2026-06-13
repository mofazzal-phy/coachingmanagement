import { DEFAULT_MARK_CONFIG, parseMarkConfig, configTotalMarks } from '@/utils/markConfig.utils'

/** Suggested exam names (frequency / category — not paper format). */
export const EXAM_NAME_PRESETS = [
  'Daily Exam',
  'Weekly Exam',
  'Monthly Exam',
  'Yearly Exam',
  'Model Test',
  'Mock Test',
  'Mid Term Exam',
  'Final Exam',
  'Selection Test',
]

/**
 * Build mark_config from exam type (paper format: MCQ, CQ, BOTH).
 */
export function markConfigFromExamType(examType) {
  if (!examType) return null

  const code = String(examType.code || '').toUpperCase()
  const name = String(examType.name || '').toUpperCase()

  if (code === 'MCQ' || name === 'MCQ') {
    return {
      mcq: { enabled: true, max_marks: 50, pass_marks: 20, evaluation: 'auto' },
      cq: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'manual' },
      written: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'manual' },
      practical: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'manual' },
    }
  }

  if (code === 'CQ' || name === 'CQ') {
    return {
      mcq: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'auto' },
      cq: { enabled: true, max_marks: 70, pass_marks: 28, evaluation: 'manual' },
      written: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'manual' },
      practical: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'manual' },
    }
  }

  if (code === 'BOTH' || name.includes('MCQ') || name.includes('BOTH')) {
    return { ...DEFAULT_MARK_CONFIG }
  }

  return null
}

export function resolveMarkConfig(routine, exam = null) {
  const parsed = parseMarkConfig(routine?.mark_config)
  if (parsed && configTotalMarks(parsed) > 0) {
    return parsed
  }

  // Legacy: single total on routine without component breakdown
  const total = Number(routine?.total_marks) || 0
  if (total > 0 && routine) {
    return {
      total: { enabled: true, max_marks: total, pass_marks: Number(routine.pass_marks) || 0, evaluation: 'manual' },
    }
  }

  const fromExam = markConfigFromExamType(exam?.exam_type || exam?.examType)
  return fromExam || DEFAULT_MARK_CONFIG
}

export function paperFormatLabel(examType) {
  if (!examType?.name) return '—'
  return examType.name
}

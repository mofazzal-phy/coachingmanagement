export const COMPONENT_KEYS = ['mcq', 'cq', 'written', 'practical', 'total']

export const COMPONENT_LABELS = {
  mcq: 'MCQ',
  cq: 'CQ',
  written: 'Written',
  practical: 'Practical',
  total: 'Marks',
}

export const DEFAULT_MARK_CONFIG = {
  mcq: { enabled: true, max_marks: 30, pass_marks: 12, evaluation: 'auto' },
  cq: { enabled: true, max_marks: 50, pass_marks: 20, evaluation: 'manual' },
  practical: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'manual' },
  written: { enabled: false, max_marks: 0, pass_marks: 0, evaluation: 'manual' },
}

/** Parse mark_config from API (object or JSON string). */
export function parseMarkConfig(markConfig) {
  if (!markConfig) return null
  if (typeof markConfig === 'string') {
    try {
      markConfig = JSON.parse(markConfig)
    } catch {
      return null
    }
  }
  if (typeof markConfig !== 'object') return null
  return markConfig
}

export function normalizeMarkConfig(markConfig) {
  markConfig = parseMarkConfig(markConfig)
  if (!markConfig) {
    return null
  }

  const active = COMPONENT_KEYS.filter((key) => {
    const item = markConfig[key]
    return item && (item.enabled !== false) && Number(item.max_marks) > 0
  })

  return active.length ? markConfig : null
}

export function getActiveComponents(markConfig) {
  const normalized = normalizeMarkConfig(markConfig)
  if (!normalized) {
    return [{ key: 'total', label: 'Marks', max_marks: null, evaluation: 'manual' }]
  }

  return COMPONENT_KEYS
    .filter((key) => normalized[key] && normalized[key].enabled !== false && Number(normalized[key].max_marks) > 0)
    .map((key) => ({
      key,
      label: COMPONENT_LABELS[key] || key,
      max_marks: Number(normalized[key].max_marks) || 0,
      pass_marks: Number(normalized[key].pass_marks) || 0,
      evaluation: normalized[key].evaluation || 'manual',
    }))
}

export function sumBreakdown(breakdown = {}, components = null) {
  let sum = 0
  let hasValue = false
  const keys = components?.length
    ? components.map((c) => c.key)
    : Object.keys(breakdown || {})

  keys.forEach((key) => {
    const value = breakdown?.[key]
    if (value === null || value === '' || value === undefined) return
    sum += Number(value) || 0
    hasValue = true
  })

  return hasValue ? Math.round(sum * 100) / 100 : 0
}

export function createEmptyBreakdown(components) {
  const breakdown = {}
  components.forEach((c) => {
    breakdown[c.key] = null
  })
  return breakdown
}

export function configTotalMarks(markConfig) {
  return getActiveComponents(markConfig).reduce((sum, c) => sum + (c.max_marks || 0), 0)
}

export function evaluationStatusLabel(status) {
  if (status === 'complete') return 'Complete'
  if (status === 'partial') return 'Partial'
  return 'Pending'
}

export function evaluationStatusClass(status) {
  if (status === 'complete') return 'complete'
  if (status === 'partial') return 'partial'
  return 'pending'
}

/** True when multiple components each have their own pass threshold (e.g. MCQ + CQ). */
export function usesPerComponentPass(markConfig) {
  const cols = getActiveComponents(markConfig).filter((c) => c.key !== 'total')
  if (cols.length < 2) return false
  return cols.some((c) => Number(c.pass_marks) > 0)
}

/** Whether a single component score meets its pass_marks (null = not entered yet). */
export function componentMarkPasses(marks, component) {
  if (marks === null || marks === '' || marks === undefined) return null
  const pass = Number(component?.pass_marks) || 0
  if (pass <= 0) return true
  return Number(marks) >= pass
}

/**
 * Subject pass: every enabled component with pass_marks must be met (MCQ pass AND CQ pass).
 * @returns {{ passed: boolean|null, evaluated: boolean, components: Array, percentage: number }}
 */
export function evaluateSubjectPass(breakdown = {}, markConfig, marksObtained = null) {
  const components = getActiveComponents(markConfig).filter((c) => c.key !== 'total')
  const total = Number(marksObtained) || sumBreakdown(breakdown, components)
  const maxTotal = components.reduce((s, c) => s + (c.max_marks || 0), 0)
  const percentage = maxTotal > 0 ? Math.round((total / maxTotal) * 10000) / 100 : 0

  if (!usesPerComponentPass(markConfig)) {
    return { passed: null, evaluated: false, components: [], percentage }
  }

  const results = components.map((c) => ({
    key: c.key,
    label: c.label,
    marks: breakdown[c.key],
    pass_marks: c.pass_marks,
    max_marks: c.max_marks,
    passes: componentMarkPasses(breakdown[c.key], c),
  }))

  const withPass = results.filter((r) => Number(r.pass_marks) > 0)
  const decided = withPass.filter((r) => r.passes !== null)

  if (!decided.length) {
    return { passed: null, evaluated: false, components: results, percentage }
  }

  if (decided.length < withPass.length) {
    const okSoFar = decided.every((r) => r.passes)
    return { passed: okSoFar ? null : false, evaluated: true, components: results, percentage }
  }

  const passed = withPass.every((r) => r.passes === true)
  return { passed, evaluated: true, components: results, percentage }
}

export function formatPassCriteria(markConfig) {
  const cols = getActiveComponents(markConfig).filter((c) => c.key !== 'total' && Number(c.pass_marks) > 0)
  if (cols.length < 2) return ''
  return cols.map((c) => `${c.label} ≥ ${c.pass_marks}`).join(' and ') + ' to pass the subject'
}

export function formatBreakdownSummary(breakdown, markConfig) {
  if (!breakdown || typeof breakdown !== 'object') return '—'

  const components = getActiveComponents(markConfig)
  if (components.length === 1 && components[0].key === 'total') {
    return breakdown.total ?? breakdown[components[0].key] ?? '—'
  }

  return components
    .map((c) => {
      const val = breakdown[c.key]
      if (val === null || val === undefined || val === '') return null
      return `${c.label}: ${val}`
    })
    .filter(Boolean)
    .join(' · ') || '—'
}

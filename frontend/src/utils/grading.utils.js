import { evaluateSubjectPass } from '@/utils/markConfig.utils'

/**
 * Default grading rules — mirrors Modules\Core\app\Services\GradingService::defaultRules()
 * Prefer loading from GET /settings/grading_rules when available.
 */
export const DEFAULT_GRADING_RULES = [
  { min_percent: 80, grade: 'A+', grade_point: 5.0 },
  { min_percent: 70, grade: 'A', grade_point: 4.0 },
  { min_percent: 60, grade: 'A-', grade_point: 3.5 },
  { min_percent: 50, grade: 'B', grade_point: 3.0 },
  { min_percent: 40, grade: 'C', grade_point: 2.0 },
  { min_percent: 33, grade: 'D', grade_point: 1.0 },
  { min_percent: 0, grade: 'F', grade_point: 0.0 },
]

let cachedRules = null

export function setGradingRules(rules) {
  cachedRules = Array.isArray(rules) && rules.length ? [...rules] : null
}

export function getGradingRules() {
  return cachedRules || DEFAULT_GRADING_RULES
}

function sortedRuleForPercentage(percentage, rules) {
  const pct = Math.max(0, Math.min(100, Number(percentage) || 0))
  const sorted = [...rules].sort((a, b) => b.min_percent - a.min_percent)

  for (const rule of sorted) {
    if (pct >= rule.min_percent) {
      return rule
    }
  }

  return sorted[sorted.length - 1]
}

export function calculateGradeFromPercentage(percentage, rules = getGradingRules()) {
  return sortedRuleForPercentage(percentage, rules)?.grade ?? 'F'
}

export function calculateGradeFromMarks(marksObtained, totalMarks, rules = getGradingRules()) {
  const total = Number(totalMarks) || 0
  const obtained = Number(marksObtained) || 0
  const percentage = total > 0 ? (obtained / total) * 100 : 0

  const rule = sortedRuleForPercentage(percentage, rules)

  return {
    grade: rule?.grade ?? 'F',
    grade_point: rule?.grade_point ?? 0,
    percentage: Math.round(percentage * 100) / 100,
    passed: null,
  }
}

/**
 * Letter grade from total marks; if mark_config has MCQ+CQ pass marks, fail (F) when any component is below its pass.
 */
export function calculateResultGrade(marksObtained, totalMarks, breakdown = {}, markConfig = null, rules = getGradingRules()) {
  const subjectPass = evaluateSubjectPass(breakdown, markConfig, marksObtained)

  if (subjectPass.evaluated && subjectPass.passed === false) {
    return {
      grade: 'F',
      grade_point: 0,
      percentage: subjectPass.percentage,
      passed: false,
      component_results: subjectPass.components,
    }
  }

  const base = calculateGradeFromMarks(marksObtained, totalMarks, rules)
  return {
    ...base,
    passed: subjectPass.evaluated ? subjectPass.passed : null,
    component_results: subjectPass.components,
  }
}

export function getGradeCssClass(grade) {
  const map = {
    'A+': 'a-plus',
    A: 'a',
    'A-': 'a-minus',
    B: 'b',
    C: 'c',
    D: 'd',
    F: 'f',
  }

  return map[grade] || 'f'
}

/**
 * Load grading rules from API into module cache.
 */
export async function loadGradingRules(apiClient) {
  try {
    const res = await apiClient.get('/settings/grading-rules')
    const rules = res.data?.data?.rules
    if (Array.isArray(rules) && rules.length) {
      setGradingRules(rules)
      return rules
    }
  } catch {
    // fallback below
  }

  try {
    const res = await apiClient.get('/settings/grading_rules')
    const setting = res.data?.data
    if (setting?.value) {
      const rules = typeof setting.value === 'string'
        ? JSON.parse(setting.value)
        : setting.value
      if (Array.isArray(rules)) {
        setGradingRules(rules)
        return rules
      }
    }
  } catch {
    // use defaults
  }

  return getGradingRules()
}

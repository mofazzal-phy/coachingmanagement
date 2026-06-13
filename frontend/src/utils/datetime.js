/** Bangladesh locale + 12-hour clock helpers for consistent time display. */

export const BD_TIMEZONE = 'Asia/Dhaka'
export const BD_LOCALE = 'en-BD'

const TIME_ONLY = /^\d{1,2}:\d{2}(:\d{2})?$/

/** Normalize HH:mm or HH:mm:ss to HH:mm */
export function normalizeWallClockTime(value) {
  if (!value) return ''
  const str = String(value).trim()
  if (str.includes('T')) return str.substring(11, 19).substring(0, 5)
  return str.substring(0, 5)
}

/** Convert wall-clock HH:mm to minutes (0–1439). */
export function timeToMinutes(value) {
  const normalized = normalizeWallClockTime(value)
  if (!normalized) return 0
  const [h, m] = normalized.split(':').map(Number)
  return (h || 0) * 60 + (m || 0)
}

/**
 * Format a wall-clock time string (from routine / API H:i) as 12-hour — no timezone shift.
 */
export function formatWallClockTime12(value) {
  const normalized = normalizeWallClockTime(value)
  if (!normalized) return '—'

  const [h24, m] = normalized.split(':').map(Number)
  const h12 = h24 % 12 || 12
  const ampm = h24 >= 12 ? 'PM' : 'AM'
  return `${h12}:${String(m).padStart(2, '0')} ${ampm}`
}

/**
 * Parse a full datetime (ISO / timestamp) for BD display. Wall-clock HH:mm strings skip TZ conversion.
 */
export function parseToDate(value, referenceDate = new Date()) {
  if (!value) return null

  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    return value
  }

  const str = String(value).trim()
  if (!str) return null

  // Wall-clock times from API/routine — never apply timezone conversion
  if (TIME_ONLY.test(str)) {
    return null
  }

  const iso = str.includes('T') ? str : str.replace(' ', 'T')
  const parsed = new Date(iso)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

/**
 * Format any time/datetime as 12-hour clock (e.g. 10:50 PM).
 * HH:mm strings are treated as Bangladesh wall-clock (routine / check-in fields).
 */
export function formatTime12(value, options = {}) {
  if (!value) return '—'

  const str = String(value).trim()
  if (TIME_ONLY.test(str)) {
    return formatWallClockTime12(str)
  }

  const { withSeconds = false } = options
  const date = value instanceof Date ? value : parseToDate(value)
  if (!date) return formatWallClockTime12(normalizeWallClockTime(str))

  return date.toLocaleTimeString(BD_LOCALE, {
    timeZone: BD_TIMEZONE,
    hour: 'numeric',
    minute: '2-digit',
    second: withSeconds ? '2-digit' : undefined,
    hour12: true,
  })
}

/**
 * Format datetime as date + 12-hour time in Bangladesh.
 */
export function formatDateTime12(value) {
  const str = String(value ?? '').trim()
  if (TIME_ONLY.test(str)) {
    return formatWallClockTime12(str)
  }

  const date = value instanceof Date ? value : parseToDate(value)
  if (!date) return '—'

  return date.toLocaleString(BD_LOCALE, {
    timeZone: BD_TIMEZONE,
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })
}

/** Current time in 12-hour BD format. */
export function nowTime12(withSeconds = false) {
  return formatTime12(new Date(), { withSeconds })
}

/** Normalize to HH:mm for <input type="time"> using Bangladesh wall clock. */
export function toTimeInputValue(value) {
  if (!value) return ''

  const str = String(value).trim()
  if (TIME_ONLY.test(str)) {
    return normalizeWallClockTime(str)
  }

  const date = value instanceof Date ? value : parseToDate(value)
  if (!date) return normalizeWallClockTime(str)

  return date.toLocaleTimeString('en-GB', {
    timeZone: BD_TIMEZONE,
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  })
}

/**
 * Minutes after scheduled start (handles evening classes crossing midnight).
 */
export function minutesAfterWallClockStart(checkIn, scheduledStart) {
  const checkMinutes = timeToMinutes(checkIn)
  let startMinutes = timeToMinutes(scheduledStart)
  let diff = checkMinutes - startMinutes

  // Check-in after midnight for a late-evening class start
  if (diff < -12 * 60) {
    diff += 24 * 60
  }

  return diff
}

/**
 * Smart attendance status from check-in vs scheduled class start (wall-clock, BD).
 */
export function calcAttendanceStatus(checkIn, scheduledStart, presentGrace = 10, lateGrace = 20) {
  if (!checkIn || !scheduledStart) {
    return { status: 'present', late_minutes: 0, minutes_after_start: 0 }
  }

  const minutesAfterStart = minutesAfterWallClockStart(checkIn, scheduledStart)
  const lateMinutes = Math.max(0, minutesAfterStart)

  if (minutesAfterStart <= presentGrace) {
    return { status: 'present', late_minutes: 0, minutes_after_start: minutesAfterStart }
  }

  if (minutesAfterStart <= lateGrace) {
    return { status: 'late', late_minutes: lateMinutes, minutes_after_start: minutesAfterStart }
  }

  return { status: 'absent', late_minutes: lateMinutes, minutes_after_start: minutesAfterStart }
}

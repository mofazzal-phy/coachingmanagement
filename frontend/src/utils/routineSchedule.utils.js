/**
 * Build staggered exam routine rows: one time slot per subject (all selected batches share that slot).
 */

const FRIDAY = 5 // JS getDay(): 0=Sun … 5=Fri

export function timeToMinutes(time) {
  if (!time) return 0
  const parts = String(time).slice(0, 5).split(':').map(Number)
  return (parts[0] || 0) * 60 + (parts[1] || 0)
}

export function minutesToTime(minutes) {
  const m = Math.max(0, Math.min(24 * 60 - 1, Math.floor(minutes)))
  const h = Math.floor(m / 60)
  const min = m % 60
  return `${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`
}

export function addDaysToDate(dateStr, days, skipFriday = true) {
  const d = new Date(`${dateStr}T12:00:00`)
  let added = 0
  while (added < days) {
    d.setDate(d.getDate() + 1)
    if (skipFriday && d.getDay() === FRIDAY) continue
    added++
  }
  return d.toISOString().slice(0, 10)
}

/**
 * @param {object} opts
 * @param {Array} opts.subjects - { id, name }
 * @param {Array} opts.batches - { id, name }
 * @param {string} opts.startDate - Y-m-d
 * @param {string} opts.startTime - HH:mm
 * @param {number} opts.slotMinutes
 * @param {number} opts.gapMinutes
 * @param {string} opts.dayEndTime - HH:mm, roll to next day if exceeded
 * @param {'same_day'|'next_day_per_subject'} opts.dayMode
 * @param {boolean} opts.skipFriday
 */
export function buildStaggeredRoutineRows(opts) {
  const {
    subjects,
    batches,
    startDate,
    startTime,
    slotMinutes = 90,
    gapMinutes = 15,
    dayEndTime = '17:00',
    dayMode = 'same_day',
    skipFriday = true,
    sharedFields = {},
  } = opts

  if (!subjects?.length || !batches?.length || !startDate || !startTime) return []

  const dayEndMin = timeToMinutes(dayEndTime)
  let currentDate = startDate
  if (skipFriday && new Date(`${currentDate}T12:00:00`).getDay() === FRIDAY) {
    currentDate = addDaysToDate(currentDate, 1, skipFriday)
  }

  let slotStartMin = timeToMinutes(startTime)
  const rows = []

  subjects.forEach((subject, subjectIndex) => {
    if (dayMode === 'next_day_per_subject' && subjectIndex > 0) {
      currentDate = addDaysToDate(currentDate, 1, skipFriday)
      slotStartMin = timeToMinutes(startTime)
    }

    const slotEndMin = slotStartMin + slotMinutes
    if (slotEndMin > dayEndMin && dayMode === 'same_day') {
      currentDate = addDaysToDate(currentDate, 1, skipFriday)
      slotStartMin = timeToMinutes(startTime)
    }

    const start = minutesToTime(slotStartMin)
    const end = minutesToTime(slotStartMin + slotMinutes)

    batches.forEach((batch) => {
      rows.push({
        batch_id: batch.id,
        batch_name: batch.name,
        subject_id: subject.id,
        subject_name: subject.name,
        exam_date: currentDate,
        start_time: start,
        end_time: end,
        ...sharedFields,
      })
    })

    slotStartMin += slotMinutes + gapMinutes
    if (slotStartMin + slotMinutes > dayEndMin && dayMode === 'same_day') {
      currentDate = addDaysToDate(currentDate, 1, skipFriday)
      slotStartMin = timeToMinutes(startTime)
    }
  })

  return rows
}

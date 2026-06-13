import { defineStore } from 'pinia'

const STORAGE_KEY = 'student_attendance_page_v1'
const CACHE_TTL_MS = 15 * 60 * 1000

function readRaw() {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return null
    return JSON.parse(raw)
  } catch {
    return null
  }
}

export const useStudentAttendanceStore = defineStore('studentAttendance', {
  state: () => ({
    savedAt: null,
    cacheKey: null,
    snapshot: null,
  }),

  actions: {
    buildCacheKey({ date, subjectId, slot, batchIds, searchQuery, statusFilter }) {
      const batches = [...(batchIds || [])].map(String).sort().join(',')
      return [
        date || '',
        subjectId || '',
        slot || '',
        batches,
        searchQuery || '',
        statusFilter || '',
      ].join('|')
    },

    hydrate() {
      const stored = readRaw()
      if (!stored?.snapshot || !stored.savedAt) return null
      if (Date.now() - stored.savedAt > CACHE_TTL_MS) {
        this.invalidate()
        return null
      }
      this.savedAt = stored.savedAt
      this.cacheKey = stored.cacheKey
      this.snapshot = stored.snapshot
      return stored.snapshot
    },

    persist(cacheKey, snapshot) {
      this.cacheKey = cacheKey
      this.snapshot = snapshot
      this.savedAt = Date.now()
      sessionStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
          savedAt: this.savedAt,
          cacheKey: this.cacheKey,
          snapshot,
        })
      )
    },

    invalidate() {
      this.savedAt = null
      this.cacheKey = null
      this.snapshot = null
      sessionStorage.removeItem(STORAGE_KEY)
    },
  },
})

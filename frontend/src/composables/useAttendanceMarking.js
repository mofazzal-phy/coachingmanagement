import { ref, computed, onMounted, onUnmounted } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'

/**
 * Shared dirty-state + polling for attendance marking pages.
 */
export function useAttendanceMarking(loadFn, options = {}) {
  const pollMs = options.pollMs ?? 15000
  const dirtyIds = ref(new Set())
  const saving = ref(false)
  const loading = ref(false)

  const markDirty = (id) => {
    const next = new Set(dirtyIds.value)
    next.add(id)
    dirtyIds.value = next
  }

  const clearDirty = () => {
    dirtyIds.value = new Set()
  }

  const hasUnsavedChanges = computed(() => dirtyIds.value.size > 0)

  let pollTimer = null

  const refresh = async (background = false) => {
    if (saving.value) return
    if (background && hasUnsavedChanges.value) return
    await loadFn(background)
  }

  onMounted(() => {
    pollTimer = setInterval(() => refresh(true), pollMs)
    window.addEventListener('beforeunload', handleBeforeUnload)
  })

  onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer)
    window.removeEventListener('beforeunload', handleBeforeUnload)
  })

  onBeforeRouteLeave((_to, _from, next) => {
    if (!hasUnsavedChanges.value) {
      next()
      return
    }
    next(window.confirm('You have unsaved attendance changes. Leave anyway?'))
  })

  const handleBeforeUnload = (e) => {
    if (!hasUnsavedChanges.value) return
    e.preventDefault()
    e.returnValue = ''
  }

  return {
    dirtyIds,
    saving,
    loading,
    markDirty,
    clearDirty,
    hasUnsavedChanges,
    refresh,
  }
}

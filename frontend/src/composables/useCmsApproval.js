import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth.store'

export function useCmsApproval(apiModule) {
  const authStore = useAuthStore()
  const resolveApi = () => (apiModule?.value ?? apiModule)

  const canApprove = computed(() =>
    authStore.hasPermission('approve cms content') ||
    authStore.hasPermission('approve notice board')
  )

  const showRejectDialog = ref(false)
  const rejectTarget = ref(null)
  const rejectReason = ref('')
  const rejectLoading = ref(false)
  const rejectError = ref(null)

  const openReject = (item) => {
    rejectTarget.value = item
    rejectReason.value = ''
    rejectError.value = null
    showRejectDialog.value = true
  }

  const closeReject = () => {
    showRejectDialog.value = false
    rejectTarget.value = null
  }

  const confirmReject = async (onDone) => {
    if (!rejectReason.value.trim()) {
      rejectError.value = 'Rejection reason is required.'
      return
    }
    rejectLoading.value = true
    rejectError.value = null
    try {
      await resolveApi().reject(rejectTarget.value.id, rejectReason.value)
      closeReject()
      if (onDone) await onDone()
    } catch (err) {
      rejectError.value = err.response?.data?.message || 'Failed to reject content.'
    } finally {
      rejectLoading.value = false
    }
  }

  return {
    canApprove,
    showRejectDialog,
    rejectTarget,
    rejectReason,
    rejectLoading,
    rejectError,
    openReject,
    closeReject,
    confirmReject,
  }
}

import { defineStore } from 'pinia'
import enrollmentService from '@/services/enrollment.service'

export const useEnrollmentStore = defineStore('enrollment', {
  state: () => ({
    sessionId: null,
    currentStep: 0,
    enrollmentType: null,
    studentId: null,
    isDirty: false,
    loading: false,
    error: null,
    stepErrors: {},

    stepData: {
      student_info: null,
      academic_info: null,
      course_batch: null,
      documents: [],
    },

    feeCalculation: null,
    referenceData: { classes: [], groups: [], sessions: [], subjects: [] },
  }),

  getters: {
    totalSteps: () => 6,
    completedSteps(state) {
      const done = []
      if (state.enrollmentType) done.push(0)
      if (state.stepData.student_info || state.studentId) done.push(1)
      if (state.stepData.academic_info) done.push(2)
      if (state.stepData.course_batch) done.push(3)
      if (state.stepData.documents?.length > 0) done.push(4)
      return done
    },
    canProceed(state) {
      switch (state.currentStep) {
        case 0: return !!state.enrollmentType
        case 1: return !!state.studentId || !!state.stepData.student_info
        case 2: return !!state.stepData.academic_info
        case 3: return !!state.stepData.course_batch
        case 4: return true
        case 5: return true
        default: return false
      }
    },
    progressPercent(state) {
      return Math.round((state.currentStep / 5) * 100)
    },
  },

  actions: {
    async initSession(type, studentId = null) {
      this.loading = true
      this.error = null
      try {
        const res = await enrollmentService.initSession({ enrollment_type: type, student_id: studentId })
        const data = res.data?.data || res.data
        this.sessionId = data.id
        this.enrollmentType = type
        this.studentId = studentId
        this.currentStep = data.current_step || 0
        this.stepData = data.step_data || {}
        return data
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to start session'
        throw e
      } finally { this.loading = false }
    },

    async saveStep(step, data) {
      if (!this.sessionId) return
      this.loading = true
      this.error = null
      this.stepErrors[step] = null
      try {
        const res = await enrollmentService.saveSessionStep(this.sessionId, step, data)
        const session = res.data?.data || res.data
        this.currentStep = session.current_step
        this.stepData = session.step_data || {}
        this.isDirty = false
        return session
      } catch (e) {
        this.stepErrors[step] = e.response?.data?.message || 'Save failed'
        throw e
      } finally { this.loading = false }
    },

    async finalizeEnrollment(paymentData) {
      if (!this.sessionId) return
      this.loading = true
      this.error = null
      try {
        const res = await enrollmentService.finalizeSession(this.sessionId, paymentData)
        return res.data?.data || res.data
      } catch (e) {
        this.error = e.response?.data?.message || 'Enrollment failed'
        throw e
      } finally { this.loading = false }
    },

    setStepData(step, data) {
      this.stepData[step] = data
      this.isDirty = true
    },

    async fetchReferenceData() {
      try {
        const [classesRes, groupsRes, sessionsRes] = await Promise.all([
          enrollmentService.getSuggestedCourses({}),
          import('@/services/academic.service').then(m => m.default.getGroups({ per_page: 100 })),
          import('@/services/academic.service').then(m => m.default.getSessions({ per_page: 100 })),
        ])
        this.referenceData.classes = classesRes.data?.data || []
        this.referenceData.groups = groupsRes.data?.data || []
        this.referenceData.sessions = sessionsRes.data?.data || []
      } catch (e) { /* non-critical */ }
    },

    resetState() {
      this.$reset()
    },
  },
})

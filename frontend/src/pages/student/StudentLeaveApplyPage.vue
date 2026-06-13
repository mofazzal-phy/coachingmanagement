<template>
  <div class="page-container">
    <div class="page-header">
      <h1>📋 Leave Application</h1>
      <p class="text-muted">Apply for leave and view your leave history</p>
    </div>

    <div class="two-column-layout">
      <!-- Leave Application Form -->
      <div class="form-section">
        <div class="section-card">
          <h2 class="section-title">Apply for Leave</h2>

          <div v-if="submitError" class="mb-3">
            <Message severity="error" :closable="true" @close="submitError = ''">{{ submitError }}</Message>
          </div>
          <div v-if="submitSuccess" class="mb-3">
            <Message severity="success" :closable="true" @close="submitSuccess = ''">{{ submitSuccess }}</Message>
          </div>

          <form @submit.prevent="handleSubmit" class="leave-form">
            <div class="field">
              <label for="leave_type">Leave Type <span class="required">*</span></label>
              <Dropdown
                id="leave_type"
                v-model="form.leave_type"
                :options="leaveTypes"
                optionLabel="label"
                optionValue="value"
                placeholder="Select leave type"
                :class="{ 'p-invalid': errors.leave_type }"
              />
              <small v-if="errors.leave_type" class="p-error">{{ errors.leave_type }}</small>
            </div>

            <div class="field-row">
              <div class="field">
                <label for="start_date">Start Date <span class="required">*</span></label>
                <Calendar
                  id="start_date"
                  v-model="form.start_date"
                  :minDate="minDate"
                  dateFormat="dd/mm/yy"
                  placeholder="Select start date"
                  :class="{ 'p-invalid': errors.start_date }"
                  @date-select="onDateChange"
                />
                <small v-if="errors.start_date" class="p-error">{{ errors.start_date }}</small>
              </div>
              <div class="field">
                <label for="end_date">End Date <span class="required">*</span></label>
                <Calendar
                  id="end_date"
                  v-model="form.end_date"
                  :minDate="form.start_date || minDate"
                  dateFormat="dd/mm/yy"
                  placeholder="Select end date"
                  :class="{ 'p-invalid': errors.end_date }"
                  @date-select="onDateChange"
                />
                <small v-if="errors.end_date" class="p-error">{{ errors.end_date }}</small>
              </div>
            </div>

            <div class="field" v-if="totalDays > 0">
              <div class="total-days-badge">
                <i class="pi pi-calendar"></i> Total: <strong>{{ totalDays }}</strong> day{{ totalDays > 1 ? 's' : '' }}
              </div>
            </div>

            <div class="field">
              <label for="reason">Reason <span class="required">*</span></label>
              <Textarea
                id="reason"
                v-model="form.reason"
                rows="4"
                placeholder="Please describe the reason for your leave..."
                :class="{ 'p-invalid': errors.reason }"
                :maxlength="500"
              />
              <div class="field-footer">
                <small v-if="errors.reason" class="p-error">{{ errors.reason }}</small>
                <small class="char-count">{{ form.reason.length }}/500</small>
              </div>
            </div>

            <Button
              type="submit"
              label="Submit Application"
              icon="pi pi-send"
              :loading="submitting"
              class="p-button-primary submit-btn"
            />
          </form>
        </div>
      </div>

      <!-- Leave History -->
      <div class="history-section">
        <div class="section-card">
          <h2 class="section-title">Leave History</h2>

          <div v-if="loadingHistory" class="loading-state-sm">
            <ProgressSpinner style="width: 30px; height: 30px" />
          </div>

          <div v-else-if="leaveApplications.length === 0" class="empty-history">
            <i class="pi pi-calendar-plus"></i>
            <p>No leave applications yet.</p>
          </div>

          <div v-else class="leave-list">
            <div v-for="leave in leaveApplications" :key="leave.id" class="leave-item">
              <div class="leave-item-header">
                <span class="leave-type-badge">{{ leave.leave_type }}</span>
                <Tag
                  :value="leave.status"
                  :severity="getStatusSeverity(leave.status)"
                />
              </div>
              <div class="leave-dates">
                <i class="pi pi-calendar"></i>
                {{ formatDate(leave.start_date) }} — {{ formatDate(leave.end_date) }}
                <span class="leave-days">({{ leave.total_days }} day{{ leave.total_days > 1 ? 's' : '' }})</span>
              </div>
              <p class="leave-reason">{{ leave.reason }}</p>
              <div v-if="leave.rejection_reason" class="rejection-reason">
                <strong>Rejection reason:</strong> {{ leave.rejection_reason }}
              </div>
              <div class="leave-date-meta">
                Applied on {{ formatDate(leave.created_at) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import studentPortalService from '@/services/student-portal.service'

export default {
  name: 'StudentLeaveApplyPage',
  setup() {
    const loadingHistory = ref(false)
    const submitting = ref(false)
    const submitError = ref('')
    const submitSuccess = ref('')
    const leaveApplications = ref([])
    const errors = reactive({})

    const minDate = new Date()

    const leaveTypes = [
      { label: 'Sick Leave', value: 'sick' },
      { label: 'Personal Leave', value: 'personal' },
      { label: 'Emergency Leave', value: 'emergency' },
      { label: 'Other', value: 'other' },
    ]

    const form = reactive({
      leave_type: null,
      start_date: null,
      end_date: null,
      reason: '',
    })

    const totalDays = computed(() => {
      if (!form.start_date || !form.end_date) return 0
      const start = new Date(form.start_date)
      const end = new Date(form.end_date)
      const diffTime = Math.abs(end - start)
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
      return diffDays
    })

    const onDateChange = () => {
      // Auto-clear end date if it's before start date
      if (form.start_date && form.end_date) {
        if (new Date(form.end_date) < new Date(form.start_date)) {
          form.end_date = null
        }
      }
    }

    const validate = () => {
      Object.keys(errors).forEach(k => delete errors[k])
      let valid = true

      if (!form.leave_type) {
        errors.leave_type = 'Please select a leave type.'
        valid = false
      }
      if (!form.start_date) {
        errors.start_date = 'Please select a start date.'
        valid = false
      }
      if (!form.end_date) {
        errors.end_date = 'Please select an end date.'
        valid = false
      }
      if (form.start_date && form.end_date) {
        if (new Date(form.end_date) < new Date(form.start_date)) {
          errors.end_date = 'End date cannot be before start date.'
          valid = false
        }
      }
      if (!form.reason.trim()) {
        errors.reason = 'Please provide a reason for your leave.'
        valid = false
      }

      return valid
    }

    const handleSubmit = async () => {
      if (!validate()) return

      submitting.value = true
      submitError.value = ''
      submitSuccess.value = ''

      try {
        const payload = {
          leave_type: form.leave_type,
          start_date: form.start_date.toISOString().split('T')[0],
          end_date: form.end_date.toISOString().split('T')[0],
          reason: form.reason.trim(),
        }

        await studentPortalService.applyLeave(payload)
        submitSuccess.value = 'Leave application submitted successfully!'
        
        // Reset form
        form.leave_type = null
        form.start_date = null
        form.end_date = null
        form.reason = ''

        // Refresh leave history
        loadLeaveApplications()
      } catch (err) {
        submitError.value = err.response?.data?.message || 'Failed to submit leave application.'
      } finally {
        submitting.value = false
      }
    }

    const loadLeaveApplications = async () => {
      loadingHistory.value = true
      try {
        const res = await studentPortalService.leaveApplications()
        leaveApplications.value = res.data?.data || []
      } catch (err) {
        console.error('Failed to load leave applications:', err)
      } finally {
        loadingHistory.value = false
      }
    }

    const getStatusSeverity = (status) => {
      if (status === 'approved') return 'success'
      if (status === 'rejected') return 'danger'
      return 'warning'
    }

    const formatDate = (date) => {
      if (!date) return ''
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric'
      })
    }

    onMounted(() => {
      loadLeaveApplications()
    })

    return {
      loadingHistory, submitting, submitError, submitSuccess,
      leaveApplications, errors, minDate, leaveTypes, form, totalDays,
      onDateChange, handleSubmit, getStatusSeverity, formatDate,
    }
  }
}
</script>

<style scoped>
.page-container {
  max-width: 1100px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 1.5rem;
}

.page-header h1 {
  margin: 0 0 0.25rem;
  font-size: 1.5rem;
}

.text-muted { color: var(--text-muted); font-size: 0.9rem; margin: 0; }

.two-column-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  align-items: start;
}

@media (max-width: 768px) {
  .two-column-layout {
    grid-template-columns: 1fr;
  }
}

.section-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.section-title {
  font-size: 1.1rem;
  margin: 0 0 1.25rem;
  color: var(--text-secondary);
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--border-light);
}

/* Form Styles */
.leave-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.field label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-secondary);
}

.required { color: #dc2626; }

.field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 480px) {
  .field-row {
    grid-template-columns: 1fr;
  }
}

.total-days-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: #e0e7ff;
  color: #4f46e5;
  padding: 0.4rem 0.75rem;
  border-radius: 8px;
  font-size: 0.85rem;
}

.field-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.char-count {
  color: var(--text-muted);
  font-size: 0.75rem;
}

.submit-btn {
  margin-top: 0.5rem;
  width: 100%;
}

.mb-3 { margin-bottom: 1rem; }

/* History Styles */
.loading-state-sm {
  text-align: center;
  padding: 2rem;
}

.empty-history {
  text-align: center;
  padding: 2rem;
  color: var(--text-muted);
}

.empty-history i {
  font-size: 2rem;
  margin-bottom: 0.5rem;
  display: block;
}

.empty-history p {
  margin: 0;
  font-size: 0.9rem;
}

.leave-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.leave-item {
  padding: 1rem;
  background: var(--bg-accent);
  border-radius: 8px;
  border-left: 3px solid #e5e7eb;
}

.leave-item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.leave-type-badge {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: capitalize;
  background: #e0e7ff;
  color: #4f46e5;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
}

.leave-dates {
  font-size: 0.85rem;
  color: var(--text-secondary);
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin-bottom: 0.35rem;
}

.leave-days {
  color: var(--text-muted);
  font-size: 0.8rem;
}

.leave-reason {
  font-size: 0.85rem;
  color: var(--text-muted);
  margin: 0.35rem 0;
  line-height: 1.4;
}

.rejection-reason {
  font-size: 0.8rem;
  color: #dc2626;
  margin-top: 0.35rem;
  padding: 0.35rem 0.5rem;
  background: #fee2e2;
  border-radius: 4px;
}

.leave-date-meta {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin-top: 0.4rem;
}
</style>

<template>
  <div class="success-overlay">
    <div class="success-modal">
      <div class="success-icon">🎉</div>
      <h2>Enrollment Complete!</h2>

      <div class="result-cards">
        <div class="result-card">
          <span class="r-label">Enrollment No</span>
          <span class="r-value mono">{{ enrollment?.enrollment_no || 'N/A' }}</span>
        </div>
        <div class="result-card">
          <span class="r-label">Student ID</span>
          <span class="r-value mono">{{ student?.student_id || 'N/A' }}</span>
        </div>
        <div class="result-card" v-if="payment?.receipt_no">
          <span class="r-label">Receipt No</span>
          <span class="r-value mono">{{ payment?.receipt_no }}</span>
        </div>
      </div>

      <div class="result-info">
        <p><strong>{{ student?.first_name }} {{ student?.last_name }}</strong></p>
        <p>{{ enrollment?.batch?.course?.name }} · {{ enrollment?.batch?.name }}</p>
        <p :class="enrollment?.status === 'active' ? 'text-success' : 'text-warning'">
          {{ enrollment?.status === 'active' ? '✅ Confirmed' : '⚠️ Pending Payment' }}
        </p>
        <p v-if="idCardMessage" class="id-card-msg">{{ idCardMessage }}</p>
      </div>

      <div class="success-actions">
        <button class="btn btn-primary" :disabled="idCardLoading" @click="downloadIdCard">
          {{ idCardLoading ? 'Generating...' : '🪪 Download ID Card' }}
        </button>
        <button class="btn btn-primary" @click="$emit('new')">🔄 New Enrollment</button>
        <router-link to="/dashboard/enrollment/enrollments" class="btn btn-outline">📋 All Enrollments</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import studentService from '@/services/student.service'
import { downloadBlobFile } from '@/utils/photo.utils'

const props = defineProps({
  enrollment: Object,
  student: Object,
  payment: Object,
})

defineEmits(['new'])

const idCardLoading = ref(false)
const idCardMessage = ref('')

const downloadIdCard = async () => {
  const studentId = props.student?.id
  if (!studentId) return
  idCardLoading.value = true
  idCardMessage.value = ''
  try {
    const res = await studentService.downloadIdCard(studentId, props.enrollment?.id)
    downloadBlobFile(res, `student-id-${props.student?.student_id || studentId}.pdf`)
    idCardMessage.value = 'Student ID card downloaded.'
  } catch (e) {
    idCardMessage.value = e.response?.data?.message || 'Could not generate ID card.'
  } finally {
    idCardLoading.value = false
  }
}

onMounted(() => {
  if (props.student?.id) downloadIdCard()
})
</script>

<style scoped>
.success-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.5);
  display: flex; align-items: center; justify-content: center; z-index: 2000;
}
.success-modal {
  background: var(--bg-card); border-radius: 20px; padding: 2.5rem;
  text-align: center; max-width: 520px; width: 90%;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.success-icon { font-size: 3.5rem; margin-bottom: 0.5rem; }
.success-modal h2 { margin: 0 0 1rem 0; }

.result-cards { display: flex; gap: 0.75rem; justify-content: center; margin-bottom: 1rem; flex-wrap: wrap; }
.result-card {
  background: #f0f4ff; border-radius: 10px; padding: 0.75rem 1rem;
  display: flex; flex-direction: column; align-items: center;
}
.r-label { font-size: 0.7rem; color: #888; text-transform: uppercase; }
.r-value { font-size: 1rem; font-weight: 700; color: #4a90d9; }
.mono { font-family: monospace; }

.result-info { margin-bottom: 1.5rem; }
.result-info p { margin: 0.2rem 0; font-size: 0.9rem; }
.id-card-msg { font-size: 0.8rem; color: var(--text-muted); }

.success-actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
.btn-primary { background: #4a90d9; color: #fff; border: none; padding: 0.7rem 1.5rem; border-radius: 10px; cursor: pointer; font-size: 0.9rem; font-weight: 600; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-outline { border: 1px solid #4a90d9; color: #4a90d9; background: none; padding: 0.7rem 1.5rem; border-radius: 10px; cursor: pointer; font-size: 0.9rem; text-decoration: none; }
.text-success { color: #27ae60; font-weight: 600; }
.text-warning { color: #f39c12; font-weight: 600; }
</style>

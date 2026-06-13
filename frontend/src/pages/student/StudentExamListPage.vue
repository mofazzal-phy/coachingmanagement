<template>
  <div class="page-container">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="header-text">
          <h1>📝 Exam Schedule</h1>
          <p class="text-muted">View your upcoming and past exams</p>
        </div>
        <div class="header-stats" v-if="!loading && !error && exams.length">
          <div class="stat-pill">
            <i class="pi pi-calendar"></i>
            <span>{{ exams.length }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-navigation">
      <div class="tab-bar">
        <button 
          class="tab-btn" 
          :class="{ active: tab === 'official' }" 
          @click="switchTab('official')"
        >
          <i class="pi pi-building"></i>
          <span>Official</span>
        </button>
        <button 
          class="tab-btn" 
          :class="{ active: tab === 'live' }" 
          @click="switchTab('live')"
        >
          <i class="pi pi-globe"></i>
          <span>Live</span>
        </button>
        <button 
          class="tab-btn" 
          :class="{ active: tab === 'practice' }" 
          @click="switchTab('practice')"
        >
          <i class="pi pi-pencil"></i>
          <span>Practice</span>
        </button>
      </div>
    </div>

    <!-- Info Banner - Ultra Compact -->
    <div v-if="tab !== 'official'" class="info-bar">
      <i class="pi pi-info-circle"></i>
      <span v-if="tab === 'practice'">
        <router-link to="/student/practice">Practice Center →</router-link>
      </span>
      <span v-else>Timed auto-save exams</span>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="state-container">
      <ProgressSpinner style="width: 35px; height: 35px" />
      <span>Loading...</span>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="state-container">
      <Message severity="error" :closable="false">{{ error }}</Message>
    </div>

    <!-- Empty State -->
    <div v-else-if="exams.length === 0" class="state-container">
      <i class="pi pi-inbox"></i>
      <p>No exams found</p>
    </div>

    <!-- Exam Cards Grid -->
    <div v-else class="exam-grid">
      <div v-for="exam in exams" :key="exam.id || exam.exam_id" class="exam-card">
        <!-- Card Header - Single Line -->
        <div class="card-header">
          <h3 class="exam-title">{{ exam.name }}</h3>
          <div class="header-badges">
            <span class="type-dot" :class="exam.is_practice ? 'dot-practice' : 'dot-official'"></span>
            <ExamEligibilityBadge
              v-if="!exam.is_practice && exam.exam_eligibility"
              :eligibility="exam.exam_eligibility"
              size="xs"
            />
          </div>
        </div>

        <!-- Details Chips Row -->
        <div class="details-chips">
          <span v-if="examMeta(exam).examType" class="chip">
            <i class="pi pi-tag"></i>
            {{ examMeta(exam).examType }}
          </span>
          <span v-if="examMeta(exam).className" class="chip">
            <i class="pi pi-book"></i>
            {{ examMeta(exam).className }}
          </span>
          <span v-if="examMeta(exam).courseName" class="chip">
            <i class="pi pi-bookmark"></i>
            {{ examMeta(exam).courseName }}
          </span>
          <span v-if="examMeta(exam).batchName" class="chip">
            <i class="pi pi-users"></i>
            {{ examMeta(exam).batchName }}
          </span>
          <span v-if="examMeta(exam).subjectName" class="chip">
            <i class="pi pi-list"></i>
            {{ examMeta(exam).subjectName }}
          </span>
          <span v-if="examMeta(exam).startDate" class="chip">
            <i class="pi pi-calendar"></i>
            {{ formatDate(examMeta(exam).startDate) }}
          </span>
          <span v-if="examMeta(exam).endDate && tab !== 'live'" class="chip">
            <i class="pi pi-calendar-times"></i>
            {{ formatDate(examMeta(exam).endDate) }}
          </span>
          <span v-if="tab === 'live' && exam.time_slot" class="chip">
            <i class="pi pi-clock"></i>
            {{ exam.time_slot }}
          </span>
          <span v-if="tab === 'live'" class="chip">
            <i class="pi pi-question-circle"></i>
            {{ exam.question_count || 0 }} Qs
          </span>
          <span v-if="tab === 'live' && exam.duration_minutes" class="chip">
            <i class="pi pi-hourglass"></i>
            {{ exam.duration_minutes }}m
          </span>
          <span v-if="tab === 'official' && exam.routine_count" class="chip highlight">
            <i class="pi pi-calendar"></i>
            {{ exam.routine_count }} slots
          </span>
          <span v-if="tab === 'practice' && exam._routine_count" class="chip highlight">
            <i class="pi pi-copy"></i>
            {{ exam._routine_count }} sets
          </span>
        </div>

        <!-- Action Buttons -->
        <div class="card-actions">
          <template v-if="tab === 'official' && !exam.is_practice">
            <button class="btn btn-ghost" @click="viewRoutine(exam.id)">
              <i class="pi pi-calendar"></i>
              Routine
            </button>
            <button 
              v-if="exam.status === 'published'"
              class="btn btn-solid"
              :class="{ 'btn-muted': isAdmitBlocked(exam) }"
              :disabled="isAdmitBlocked(exam)"
              @click="viewAdmitCard(exam.id)"
            >
              <i class="pi pi-id-card"></i>
              Admit
            </button>
          </template>
          
          <template v-else-if="tab === 'practice'">
            <button class="btn btn-accent btn-full" @click="goPractice">
              <i class="pi pi-play-circle"></i>
              Practice Now
            </button>
          </template>
          
          <template v-else-if="tab === 'live'">
            <button 
              v-if="exam.can_start"
              class="btn btn-accent btn-full"
              @click="startLiveExam(exam)"
            >
              <i class="pi pi-play-circle"></i>
              {{ exam.can_resume ? 'Resume' : 'Start' }}
            </button>
            
            <span v-else-if="exam.is_submitted" class="status done">
              <i class="pi pi-check"></i> Done
            </span>
            
            <span v-else class="status locked">
              <i class="pi pi-lock"></i> {{ exam.block_reason || 'Closed' }}
            </span>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import studentPortalService from '@/services/student-portal.service'
import examService from '@/services/exam.service'
import { extractData } from '@/utils/api.utils'
import ExamEligibilityBadge from '@/components/exam/ExamEligibilityBadge.vue'

export default {
  name: 'StudentExamListPage',
  components: { ExamEligibilityBadge },
  setup() {
    const router = useRouter()
    const route = useRoute()
    const loading = ref(false)
    const error = ref(null)
    const exams = ref([])
    const tab = ref(route.query.tab || 'official')

    const normalizeExamList = (payload) => {
      if (Array.isArray(payload)) return payload
      if (payload && Array.isArray(payload.exams)) return payload.exams
      if (payload && Array.isArray(payload.data)) return payload.data
      return []
    }

    const pickName = (...values) => {
      for (const value of values) {
        if (typeof value === 'string' && value.trim()) return value.trim()
        if (value && typeof value === 'object' && typeof value.name === 'string' && value.name.trim()) {
          return value.name.trim()
        }
      }
      return ''
    }

    const examMeta = (exam) => ({
      examType: pickName(exam.exam_type_name, exam.exam_type, exam._exam_type),
      className: pickName(
        exam.class_name,
        tab.value === 'official' ? '' : exam.class,
        exam._class_name,
      ),
      courseName: pickName(exam.course_name, exam.course, exam._course_name),
      batchName: pickName(exam.batch_name, exam.batch, exam._batch_name),
      subjectName: pickName(exam.subject_name, exam._subject_name),
      startDate: exam.start_date || exam.first_routine_date || exam.exam_date || exam._start_date || '',
      endDate: exam.end_date || exam.last_routine_date || exam._end_date || '',
    })

    const loadExams = async () => {
      loading.value = true
      error.value = null
      try {
        if (tab.value === 'official') {
          const res = await studentPortalService.exams()
          exams.value = normalizeExamList(extractData(res, []))
        } else if (tab.value === 'live') {
          const res = await examService.student.liveExams()
          const data = extractData(res, {})
          const windowOrder = { live: 0, upcoming: 1, ended: 2 }
          exams.value = (data.routines || []).map(r => ({
            id: r.id,
            exam_id: r.exam_id,
            name: r.exam_name,
            is_practice: false,
            exam_type_name: r.exam_type,
            class_name: r.class_name,
            course_name: r.course_name,
            batch_name: r.batch_name,
            subject_name: r.subject_name,
            status: r.window_status,
            start_date: r.exam_date,
            end_date: r.exam_date,
            time_slot: r.start_time && r.end_time ? `${r.start_time} – ${r.end_time}` : r.start_time,
            question_count: r.question_count,
            duration_minutes: r.duration_minutes,
            can_start: !!r.can_start,
            can_resume: !!r.can_resume,
            block_reason: r.block_reason,
            is_submitted: r.is_submitted,
            in_progress_attempt_id: r.in_progress_attempt_id,
            window_status: r.window_status,
            exam_eligibility: r.exam_eligibility,
            delivery_mode: r.delivery_mode,
          })).sort((a, b) => {
            const wa = windowOrder[a.window_status] ?? 3
            const wb = windowOrder[b.window_status] ?? 3
            if (wa !== wb) return wa - wb
            const da = a.start_date ? new Date(a.start_date).getTime() : 0
            const db = b.start_date ? new Date(b.start_date).getTime() : 0
            return da - db
          })
        } else {
          const res = await examService.student.practiceRoutines()
          const data = extractData(res, {})
          const routines = data.routines || []
          const byExam = {}
          for (const r of routines) {
            if (!byExam[r.exam_id]) {
              byExam[r.exam_id] = {
                id: r.exam_id,
                name: r.exam_name,
                is_practice: true,
                exam_type_name: r.exam_type,
                class_name: r.class_name,
                course_name: r.course_name,
                batch_name: r.batch_name,
                status: 'published',
                start_date: r.exam_date,
                end_date: r.exam_date,
                _routine_count: 0,
                _subjects: new Set(),
              }
            }
            const group = byExam[r.exam_id]
            group._routine_count++
            if (r.subject_name) group._subjects.add(r.subject_name)
            if (r.exam_date) {
              if (!group.start_date || r.exam_date < group.start_date) group.start_date = r.exam_date
              if (!group.end_date || r.exam_date > group.end_date) group.end_date = r.exam_date
            }
            group.class_name = group.class_name || r.class_name
            group.course_name = group.course_name || r.course_name
            group.batch_name = group.batch_name || r.batch_name
            group.exam_type_name = group.exam_type_name || r.exam_type
          }
          exams.value = Object.values(byExam).map(group => ({
            ...group,
            subject_name: [...group._subjects].slice(0, 2).join(', ') + (group._subjects.size > 2 ? ` +${group._subjects.size - 2}` : ''),
          }))
        }
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load exams.'
      } finally {
        loading.value = false
      }
    }

    const switchTab = (t) => {
      tab.value = t
      loadExams()
    }

    const viewRoutine = (examId) => {
      router.push({ name: 'StudentExamRoutines', query: examId ? { exam_id: examId } : {} })
    }

    const viewAdmitCard = (examId) => {
      router.push({
        name: 'StudentExamAdmit',
        params: { examId },
        query: { from: 'exams' },
      })
    }

    const isAdmitBlocked = (exam) => {
      const e = exam?.exam_eligibility
      return !!(e?.check_enabled && !e?.can_download_admit)
    }

    const admitBlockedReason = (exam) => {
      if (!isAdmitBlocked(exam)) return ''
      return exam.exam_eligibility?.message || 'Not eligible'
    }

    const goPractice = () => {
      router.push({ name: 'StudentPractice' })
    }

    const startLiveExam = (exam) => {
      router.push({ name: 'StudentLiveExam', params: { routineId: exam.id } })
    }

    const formatDate = (date) => {
      if (!date) return ''
      return new Date(date).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric'
      })
    }

    onMounted(loadExams)

    return {
      loading, error, exams, tab, switchTab, viewRoutine, viewAdmitCard,
      isAdmitBlocked, admitBlockedReason, goPractice, startLiveExam, formatDate, examMeta,
    }
  }
}
</script>

<style scoped>
/* Page Container */
.page-container {
  max-width: 1100px;
  margin: 0 auto;
  padding: 1.25rem;
}

/* Header */
.page-header {
  margin-bottom: 1rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.header-text h1 {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
}

.text-muted {
  color: var(--text-muted);
  margin: 0.15rem 0 0;
  font-size: 0.8rem;
}

.header-stats {
  display: flex;
}

.stat-pill {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  background: #eef2ff;
  color: #4f46e5;
  border-radius: 50%;
  font-size: 0.85rem;
  font-weight: 700;
}

.stat-pill i {
  font-size: 0.85rem;
}

/* Tab Navigation */
.tab-navigation {
  margin-bottom: 0.85rem;
}

.tab-bar {
  display: flex;
  gap: 0.25rem;
  background: #f3f4f6;
  padding: 0.25rem;
  border-radius: 8px;
}

.tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.3rem;
  padding: 0.45rem 0.75rem;
  border: none;
  border-radius: 6px;
  background: transparent;
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-muted);
  transition: all 0.15s ease;
}

.tab-btn:hover:not(.active) {
  color: var(--text-secondary);
}

.tab-btn.active {
  background: var(--bg-card);
  color: #4f46e5;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}

.tab-btn i {
  font-size: 0.8rem;
}

/* Info Bar */
.info-bar {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.45rem 0.75rem;
  background: var(--bg-accent);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  margin-bottom: 0.85rem;
  font-size: 0.78rem;
  color: var(--text-muted);
}

.info-bar i {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.info-bar a {
  color: #4f46e5;
  font-weight: 600;
  text-decoration: none;
}

/* State Container */
.state-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2.5rem 1rem;
  text-align: center;
  color: var(--text-muted);
  gap: 0.5rem;
  font-size: 0.9rem;
}

.state-container i {
  font-size: 2rem;
}

/* Exam Grid */
.exam-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 0.75rem;
}

/* Ultra Compact Card */
.exam-card {
  background: var(--bg-card);
  border-radius: 10px;
  padding: 0.85rem;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
  border: 1px solid var(--border-color);
  transition: all 0.15s ease;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.exam-card:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  border-color: #d1d5db;
}

/* Card Header */
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
}

.exam-title {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-primary);
  line-height: 1.3;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex: 1;
}

.header-badges {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-shrink: 0;
}

.type-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.dot-practice {
  background: #8b5cf6;
}

.dot-official {
  background: #3b82f6;
}

/* Details Chips */
.details-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  background: var(--bg-accent);
  padding: 0.2rem 0.5rem;
  border-radius: 5px;
  font-size: 0.73rem;
  color: var(--text-muted);
  border: 1px solid #f3f4f6;
  white-space: nowrap;
}

.chip i {
  font-size: 0.7rem;
  color: var(--text-muted);
}

.chip.highlight {
  background: #eef2ff;
  color: #4f46e5;
  border-color: #c7d2fe;
  font-weight: 600;
}

.chip.highlight i {
  color: #4f46e5;
}

/* Card Actions */
.card-actions {
  display: flex;
  gap: 0.4rem;
  margin-top: auto;
}

/* Buttons - Premium Micro */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.3rem;
  padding: 0.4rem 0.7rem;
  border-radius: 6px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.12s ease;
  border: none;
  text-decoration: none;
  line-height: 1;
  white-space: nowrap;
}

.btn i {
  font-size: 0.8rem;
}

.btn-full {
  width: 100%;
  flex: none;
}

/* Ghost Button */
.btn-ghost {
  background: transparent;
  color: #4f46e5;
  border: 1px solid var(--border-color);
  flex: 1;
}

.btn-ghost:hover {
  background: var(--bg-accent);
  border-color: #4f46e5;
}

/* Solid Button */
.btn-solid {
  background: #4f46e5;
  color: white;
  border: 1px solid #4f46e5;
  flex: 1;
}

.btn-solid:hover:not(:disabled) {
  background: #4338ca;
  border-color: #4338ca;
}

.btn-muted,
.btn-solid:disabled {
  background: #e5e7eb;
  border-color: #e5e7eb;
  color: var(--text-muted);
  cursor: not-allowed;
}

/* Accent Button */
.btn-accent {
  background: #059669;
  color: white;
  border: 1px solid #059669;
}

.btn-accent:hover {
  background: #047857;
  border-color: #047857;
}

/* Status Indicators */
.status {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.35rem 0.6rem;
  border-radius: 5px;
  font-size: 0.75rem;
  font-weight: 600;
  width: 100%;
}

.status.done {
  background: #ecfdf5;
  color: #059669;
}

.status.locked {
  background: #fef2f2;
  color: #dc2626;
}

.status i {
  font-size: 0.75rem;
}

/* Responsive */
@media (max-width: 768px) {
  .page-container {
    padding: 0.85rem;
  }
  
  .exam-grid {
    grid-template-columns: 1fr;
  }
  
  .tab-btn span {
    display: none;
  }
  
  .tab-btn {
    padding: 0.45rem;
  }
  
  .tab-btn i {
    font-size: 0.9rem;
  }
}

@media (max-width: 480px) {
  .header-text h1 {
    font-size: 1.3rem;
  }
  
  .exam-card {
    padding: 0.7rem;
  }
  
  .exam-title {
    font-size: 0.85rem;
  }
  
  .chip {
    font-size: 0.7rem;
    padding: 0.15rem 0.4rem;
  }
  
  .btn {
    font-size: 0.73rem;
    padding: 0.35rem 0.6rem;
  }
}
</style>
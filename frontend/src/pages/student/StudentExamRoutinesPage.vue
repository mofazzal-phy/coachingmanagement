<template>
  <div class="page-container" ref="pageContainerRef">
    <!-- Page Header (hidden during print) -->
    <div class="page-header no-print">
      <div class="header-left">
        <h1>My Exam Routine</h1>
        <span class="badge-count">{{ routines.length }} exams</span>
      </div>
      <div class="header-right no-print">
        <!-- View Toggle -->
        <div class="view-toggle">
          <button class="btn btn-sm" :class="displayMode === 'grid' ? 'btn-primary' : 'btn-outline'" @click="displayMode = 'grid'" title="Table grid view">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            Grid
          </button>
          <button class="btn btn-sm" :class="displayMode === 'cards' ? 'btn-primary' : 'btn-outline'" @click="displayMode = 'cards'" title="Card view">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
            </svg>
            Cards
          </button>
        </div>
        <!-- Export Dropdown -->
        <div class="export-dropdown">
          <button class="btn btn-sm btn-outline" @click="showExportMenu = !showExportMenu" title="Export options">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Export
          </button>
          <div v-if="showExportMenu" class="export-menu">
            <button class="export-option" @click="exportAsPDF">
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
              </svg>
              Download PDF
            </button>
            <button class="export-option" @click="exportAsImage">
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              Download Image
            </button>
            <button class="export-option" @click="printRoutine">
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
              </svg>
              Print
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Print Header (visible only during print) -->
    <div class="print-header">
      <h2 class="print-exam-name">{{ printHeader.examName }}</h2>
      <p class="print-exam-meta">
        <span class="print-exam-type">{{ printHeader.examType }}</span>
        <span class="print-date-range">{{ printHeader.dateRange }}</span>
      </p>
    </div>

    <!-- Exam eligibility (compact) -->
    <div v-if="eligibilityExamTabs.length && primaryExamEligibility" class="elig-compact no-print">
      <div v-if="eligibilityExamTabs.length > 1" class="elig-tabs">
        <button
          v-for="tab in eligibilityExamTabs"
          :key="tab.id"
          type="button"
          class="elig-tab"
          :class="{ active: selectedEligibilityExamId === tab.id }"
          @click="selectEligibilityExam(tab.id)"
        >
          {{ tab.name }}
        </button>
      </div>
      <div class="elig-card" :class="eligibilityBannerClass">
        <div class="elig-row">
          <div class="elig-info">
            <span class="elig-name">{{ selectedEligibilityExamName }}</span>
            <ExamEligibilityBadge :eligibility="primaryExamEligibility" size="sm" />
            <span v-if="examFeeInfoDisplay" class="elig-fee">
              Fee ৳{{ formatExamFeeAmount(examFeeInfoDisplay.amount) }}
              <em v-if="examFeeInfoDisplay.status === 'paid'">· Paid</em>
              <em v-else-if="examFeeInfoDisplay.due_date">· Due {{ formatExamFeeDate(examFeeInfoDisplay.due_date) }}</em>
            </span>
          </div>
          <div class="elig-actions">
            <router-link v-if="canViewAdmitCard" :to="admitCardRoute" class="elig-btn primary">Admit card</router-link>
            <router-link v-if="showPayExamFeeLink" :to="examFeePayRoute" class="elig-btn outline">Pay fee</router-link>
          </div>
        </div>
        <p v-if="eligibilityShortText" class="elig-note">{{ eligibilityShortText }}</p>
      </div>
    </div>

    <!-- Stats Cards (hidden during print) -->
    <div class="stats-row no-print">
      <div class="stat-card">
        <div class="stat-icon" style="background: #eef2ff;">
          <svg width="18" height="18" fill="none" stroke="var(--primary-color)" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
        </div>
        <div>
          <div class="stat-val">{{ totalExams }}</div>
          <div class="stat-lbl">Total Exams</div>
        </div>
      </div>
      <div class="stat-card teal">
        <div class="stat-icon" style="background: #ccfbf1;">
          <svg width="18" height="18" fill="none" stroke="#14b8a6" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <div class="stat-val">{{ upcomingCount }}</div>
          <div class="stat-lbl">Upcoming</div>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon" style="background: #d4edda;">
          <svg width="18" height="18" fill="none" stroke="var(--secondary-color)" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <div class="stat-val">{{ completedCount }}</div>
          <div class="stat-lbl">Completed</div>
        </div>
      </div>
      <div class="stat-card purple">
        <div class="stat-icon" style="background: #f3e8ff;">
          <svg width="18" height="18" fill="none" stroke="#7c3aed" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
        </div>
        <div>
          <div class="stat-val">{{ subjectsCount }}</div>
          <div class="stat-lbl">Subjects</div>
        </div>
      </div>
    </div>

    <!-- Next Exam Countdown -->
    <div v-if="nextExam" class="next-exam-card no-print">
      <div class="next-exam-main">
        <div>
          <p class="next-label">Next exam</p>
          <h3>{{ nextExam.subject_name || nextExam.subject?.name || 'Subject' }} · {{ nextExam.exam_name || nextExam.exam?.name || 'Exam' }}</h3>
          <p class="next-meta">{{ formatDate(nextExam.exam_date) }} · {{ formatTime(nextExam.start_time) }} · {{ nextExam.total_marks || '—' }} marks</p>
        </div>
        <div v-if="countdownActive" class="next-timer">
          <div class="timer-unit"><span class="t-val">{{ padTime(countdown.days) }}</span><span class="t-lbl">Days</span></div>
          <span class="t-sep">:</span>
          <div class="timer-unit"><span class="t-val">{{ padTime(countdown.hours) }}</span><span class="t-lbl">Hrs</span></div>
          <span class="t-sep">:</span>
          <div class="timer-unit"><span class="t-val">{{ padTime(countdown.minutes) }}</span><span class="t-lbl">Min</span></div>
          <span class="t-sep">:</span>
          <div class="timer-unit"><span class="t-val">{{ padTime(countdown.seconds) }}</span><span class="t-lbl">Sec</span></div>
        </div>
        <div v-else class="next-live">In progress</div>
      </div>
    </div>

    <!-- Filter Tabs (hidden during print) -->
    <div class="filter-tabs no-print">
      <button
        v-for="tab in filterTabs"
        :key="tab.key"
        class="filter-tab"
        :class="{ active: viewMode === tab.key }"
        @click="viewMode = tab.key"
      >
        {{ tab.label }}
        <span class="filter-count">{{ tab.count }}</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading exam routine...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p>⚠️ {{ error }}</p>
      <button class="btn btn-outline" @click="fetchRoutines">Try Again</button>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredRoutines.length === 0" class="empty-state">
      <svg width="48" height="48" fill="none" stroke="#d1d5db" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      <h3>No Exam Routine Available</h3>
      <p>Exam routines have not been published yet for your class.</p>
    </div>

    <!-- ===== GRID VIEW (admin-style: time slots × days with exam cards in cells) ===== -->
    <div v-if="displayMode === 'grid' && filteredRoutines.length" class="routine-grid-wrapper">
      <div class="grid-scroll">
        <table class="routine-table">
          <thead>
            <tr>
              <th class="th-time">Time</th>
              <th v-for="day in studentGridData.days" :key="day" class="th-day" :class="'th-day-' + day.toLowerCase()">
                <span class="day-name" :style="{ color: getDayColor(day) }">{{ day.substring(0, 3) }}</span>
                <span class="day-date">{{ getDayDate(day) }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="slot in studentGridData.time_slots" :key="slot.key">
              <tr v-if="!isSlotEmpty(slot)" :class="{ 'lunch-row': slot.is_lunch }">
                <td class="td-time">
                  <div class="time-slot-label">
                    <span class="slot-range" v-if="slot.start">
                      {{ formatTimeDisplay(slot.start) }} — {{ formatTimeDisplay(slot.end) }}
                    </span>
                    <span class="slot-range lunch-range" v-else>Lunch</span>
                    <span class="slot-duration" v-if="slot.start">{{ formatDuration(slot.start, slot.end) }}</span>
                  </div>
                </td>
                <td
                  v-for="day in studentGridData.days"
                  :key="day + '-' + slot.key"
                  :class="getDayCellClass(day, slot)"
                >
                  <!-- Friday OFF DAY -->
                  <div v-if="day === 'Friday' && !slot.is_lunch" class="off-day-cell">
                    <span class="off-text">OFF</span>
                  </div>
                  <!-- Lunch Break -->
                  <div v-else-if="slot.is_lunch" class="lunch-cell">
                    <span class="lunch-text">LUNCH</span>
                  </div>
                  <!-- Exam Cards -->
                  <div v-else class="cell-exams">
                    <template v-if="getCellRoutines(day, slot.key).length">
                      <template v-for="(batch, bIdx) in getCellBatches(day, slot.key)" :key="batch.batch_id">
                        <div v-if="bIdx > 0" class="batch-sep"></div>
                        <div
                          v-for="(routine, rIdx) in batch.routines"
                          :key="routine.id"
                          class="exam-card"
                          :style="{
                            background: routine.batch_color || '#F3F4F6',
                            borderLeftColor: routine.batch_text_color || '#94A3B8',
                            color: routine.batch_text_color || '#4B5563',
                          }"
                        >
                          <div class="card-batch">
                            <span class="batch-dot" :style="{ background: routine.batch_text_color || '#64748B' }"></span>
                            <span class="card-batch-name">{{ batch.batch_name }}</span>
                            <span v-if="isToday(routine.exam_date)" class="status-badge status-today" title="Today">T</span>
                            <span v-else-if="isPast(routine.exam_date, routine.end_time)" class="status-badge status-completed" title="Completed">&#10004;</span>
                            <span v-else class="status-badge status-published" title="Upcoming">&#9654;</span>
                          </div>
                          <div class="card-subject">{{ routine.subject_name || routine.subject?.name || 'N/A' }}</div>
                          <div class="card-exam-type">{{ routine.exam_name || routine.exam?.name || 'N/A' }}</div>
                          <div v-if="(routine.delivery_mode || 'offline') === 'offline' && routine.room_name && routine.room_name !== 'TBD'" class="card-room">
                            Room: {{ routine.room_name }}
                          </div>
                        </div>
                      </template>
                    </template>
                    <div v-else class="cell-empty">
                      <span class="empty-dash">—</span>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ===== CARD VIEW ===== -->
    <div v-else-if="displayMode === 'cards' && filteredRoutines.length" class="routine-grid">
      <div
        v-for="routine in filteredRoutines"
        :key="routine.id"
        class="routine-card"
        :class="{ 'is-past': isPast(routine.exam_date, routine.end_time), 'is-today': isToday(routine.exam_date) }"
      >
        <!-- Date Column -->
        <div class="card-date-col">
          <div class="card-date-badge">
            <div class="card-date-day">{{ getDayName(routine.exam_date) }}</div>
            <div class="card-date-num">{{ getDayNumber(routine.exam_date) }}</div>
            <div class="card-date-month">{{ getMonthName(routine.exam_date) }}</div>
          </div>
          <div v-if="isToday(routine.exam_date)" class="today-indicator">Today</div>
          <div v-else-if="isPast(routine.exam_date, routine.end_time)" class="past-indicator">Done</div>
        </div>

        <!-- Info Column -->
        <div class="card-info-col">
          <div class="card-top-row">
            <span
              class="subject-badge"
              :style="{ background: getSubjectColor(routine.subject_name || routine.subject?.name || '') + '20', color: getSubjectColor(routine.subject_name || routine.subject?.name || '') }"
            >
              {{ routine.subject_name || routine.subject?.name || 'N/A' }}
            </span>
            <span class="exam-name">{{ routine.exam_name || routine.exam?.name || 'N/A' }}</span>
          </div>
          <div class="card-meta-row">
            <span class="meta-item">
              <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              {{ formatTime(routine.start_time) }} - {{ formatTime(routine.end_time) }}
            </span>
            <span class="meta-item">
              <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
              {{ routine.room_name || routine.room?.name || routine.room?.room_number || 'N/A' }}
            </span>
            <span class="meta-item">
              <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
              {{ routine.total_marks || 'N/A' }} marks
            </span>
            <span v-if="routine.pass_marks" class="meta-item">
              <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
              Pass: {{ routine.pass_marks }}
            </span>
            <span v-if="routine.batch_name" class="meta-item">
              <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              {{ routine.batch_name }}
            </span>
          </div>
        </div>

        <!-- Action Column -->
        <div class="card-action-col">
          <button
            class="btn-remind"
            @click="setReminder(routine)"
            :disabled="isPast(routine.exam_date, routine.end_time)"
            :title="isPast(routine.exam_date, routine.end_time) ? 'Exam already completed' : 'Set reminder'"
          >
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            Remind
          </button>
        </div>
      </div>
    </div>

    <!-- Reminder Modal -->
    <div v-if="showReminderModal" class="modal-overlay" @click.self="showReminderModal = false">
      <div class="modal-container">
        <div class="modal-header">
          <h3>Set Reminder</h3>
          <button class="modal-close" @click="showReminderModal = false">&times;</button>
        </div>
        <div class="modal-body">
          <p style="font-size:0.8125rem; color:var(--text-muted); margin-bottom:1rem;">
            Get reminded before {{ reminderSlot?.subject_name || reminderSlot?.subject?.name || 'the exam' }} exam
          </p>
          <div class="reminder-options">
            <label
              v-for="option in reminderOptions"
              :key="option.value"
              class="reminder-option"
              :class="{ active: selectedReminder === option.value }"
            >
              <input type="radio" v-model="selectedReminder" :value="option.value" />
              <span class="reminder-option-label">{{ option.label }}</span>
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showReminderModal = false">Cancel</button>
          <button class="btn btn-primary" @click="submitReminder" :disabled="reminderSubmitting">
            <span v-if="reminderSubmitting" class="spinner-sm"></span>
            {{ reminderSubmitting ? 'Setting...' : 'Set Reminder' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Success Toast -->
    <div v-if="showToast" class="toast" :class="toastType">
      {{ toastMessage }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import examService from '@/services/exam.service'
import { extractData } from '@/utils/api.utils'
import ExamEligibilityBadge from '@/components/exam/ExamEligibilityBadge.vue'

const route = useRoute()
const router = useRouter()
const eligibilityByExam = ref({})
const selectedEligibilityExamId = ref('')

const viewMode = ref('all')
const displayMode = ref('grid') // 'grid' | 'cards' — default grid

// ===== DAY ORDER (Bangladesh: Sat-Fri) =====
const DAY_ORDER = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']

// ===== GRID DATA (computed from flat routines) =====
const studentGridData = computed(() => {
  const routines = filteredRoutines.value
  if (!routines.length) {
    return { days: DAY_ORDER, day_dates: {}, time_slots: [], grid: {} }
  }

  // 1. Build day_dates from actual routine dates per weekday
  const dayDatesMap = {}
  for (const r of routines) {
    const d = normalizeDate(r.exam_date)
    if (!d) continue
    const parts = d.split('-')
    const dt = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]))
    const dayName = dt.toLocaleDateString('en-US', { weekday: 'long' })
    if (!dayDatesMap[dayName] || d < dayDatesMap[dayName]) {
      dayDatesMap[dayName] = d
    }
  }
  // 2. Build time slots from unique start/end combinations
  const slotMap = {}
  for (const r of routines) {
    const key = `${r.start_time || '00:00'}-${r.end_time || '00:00'}`
    if (!slotMap[key]) {
      slotMap[key] = {
        key,
        start: r.start_time || '00:00',
        end: r.end_time || '00:00',
        is_lunch: false,
      }
    }
  }
  const timeSlots = Object.values(slotMap).sort((a, b) => a.start.localeCompare(b.start))

  // Insert lunch breaks between slots with >= 45min gap
  const enrichedSlots = []
  let prevEnd = null
  for (const slot of timeSlots) {
    if (prevEnd !== null) {
      const gapMinutes = (timeToMinutes(slot.start) - timeToMinutes(prevEnd))
      if (gapMinutes >= 45) {
        enrichedSlots.push({
          key: 'lunch_' + enrichedSlots.length,
          start: null,
          end: null,
          is_lunch: true,
        })
      }
    }
    enrichedSlots.push(slot)
    prevEnd = slot.end
  }

  // 3. Build grid: grid[dayName][slotKey] = { routines: [...] }
  // Always show ALL 7 days (Saturday through Friday) like admin ExamRoutinePage.vue
  const days = [...DAY_ORDER]

  const grid = {}
  for (const day of days) {
    grid[day] = {}
    for (const slot of enrichedSlots) {
      grid[day][slot.key] = { routines: [], start: slot.start, end: slot.end, is_lunch: !!slot.is_lunch }
    }
  }

  // 4. Place routines into grid cells
  for (const r of routines) {
    const d = normalizeDate(r.exam_date)
    if (!d) continue
    const parts = d.split('-')
    const dt = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]))
    const dayName = dt.toLocaleDateString('en-US', { weekday: 'long' })
    const slotKey = `${r.start_time || '00:00'}-${r.end_time || '00:00'}`
    if (grid[dayName] && grid[dayName][slotKey]) {
      grid[dayName][slotKey].routines.push(r)
    }
  }

  return {
    days,
    day_dates: dayDatesMap,
    time_slots: enrichedSlots,
    grid,
  }
})

function timeToMinutes(t) {
  if (!t) return 0
  const p = t.split(':')
  return parseInt(p[0]) * 60 + parseInt(p[1] || 0)
}

// ===== GRID HELPER FUNCTIONS =====
function getCellRoutines(day, slotKey) {
  return studentGridData.value.grid?.[day]?.[slotKey]?.routines || []
}

function getCellBatches(day, slotKey) {
  const routines = getCellRoutines(day, slotKey)
  const batchMap = {}
  for (const r of routines) {
    const bid = r.batch_id || 'unknown'
    if (!batchMap[bid]) {
      batchMap[bid] = { batch_id: bid, batch_name: r.batch_name || 'My Batch', routines: [] }
    }
    batchMap[bid].routines.push(r)
  }
  return Object.values(batchMap)
}

function isSlotEmpty(slot) {
  if (slot.is_lunch) return false
  for (const day of studentGridData.value.days) {
    const routines = studentGridData.value.grid?.[day]?.[slot.key]?.routines || []
    if (routines.length > 0) return false
  }
  return true
}

function getDayCellClass(day, slot) {
  const cls = {}
  cls['td-day-' + day.toLowerCase()] = true
  if (day === 'Friday' && !slot.is_lunch) cls['td-friday'] = true
  if (slot.is_lunch) cls['td-lunch'] = true
  return cls
}

function getDayDate(dayName) {
  const d = studentGridData.value.day_dates?.[dayName]
  if (!d) return ''
  const parts = d.split('-')
  const dt = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]))
  return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function getDayColor(day) {
  const colors = {
    Saturday: '#3B82F6',
    Sunday: '#10B981',
    Monday: '#F97316',
    Tuesday: '#8B5CF6',
    Wednesday: '#EC4899',
    Thursday: '#06B6D4',
    Friday: '#EF4444',
  }
  return colors[day] || '#64748b'
}

function getBatchBgColor(batchColor) {
  if (!batchColor) return 'rgba(226, 232, 240, 0.3)'
  const hex = batchColor.replace('#', '')
  const r = parseInt(hex.substring(0, 2), 16)
  const g = parseInt(hex.substring(2, 4), 16)
  const b = parseInt(hex.substring(4, 6), 16)
  return `rgba(${r}, ${g}, ${b}, 0.12)`
}

function formatTimeDisplay(timeStr) {
  if (!timeStr) return ''
  const parts = timeStr.split(':')
  let h = parseInt(parts[0])
  const m = parts[1]
  const ampm = h >= 12 ? 'PM' : 'AM'
  h = h % 12 || 12
  return `${h}:${m} ${ampm}`
}

function formatDuration(start, end) {
  if (!start || !end) return ''
  const startTs = new Date(`2000-01-01T${start}`).getTime()
  const endTs = new Date(`2000-01-01T${end}`).getTime()
  if (isNaN(startTs) || isNaN(endTs)) return ''
  const diffMs = endTs - startTs
  if (diffMs <= 0) return ''
  const totalMinutes = Math.round(diffMs / 60000)
  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60
  if (hours > 0 && minutes > 0) return `${hours}h ${minutes}m`
  if (hours > 0) return `${hours}h`
  return `${minutes}m`
}
const routines = ref([])
const loading = ref(true)
const error = ref(null)
const showReminderModal = ref(false)
const reminderSlot = ref(null)
const selectedReminder = ref(30)
const reminderSubmitting = ref(false)
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')
const countdown = ref({ days: 0, hours: 0, minutes: 0, seconds: 0 })
const countdownActive = ref(true)
let countdownInterval = null

// ===== EXPORT STATE =====
const showExportMenu = ref(false)
const pageContainerRef = ref(null)

// Close export menu on click outside
function closeExportMenu(e) {
  if (!e.target.closest('.export-dropdown')) {
    showExportMenu.value = false
  }
}
if (typeof document !== 'undefined') {
  document.addEventListener('click', closeExportMenu)
}

async function exportAsPDF() {
  showExportMenu.value = false
  try {
    const { default: html2canvas } = await import('html2canvas')
    const { default: jsPDF } = await import('jspdf')
    const el = pageContainerRef.value
    if (!el) return
    // Temporarily add print-preview class to hide UI controls for capture
    el.classList.add('print-preview')
    const canvas = await html2canvas(el, {
      scale: 2,
      useCORS: true,
      backgroundColor: '#ffffff',
      width: el.scrollWidth,
      height: el.scrollHeight,
      windowWidth: el.scrollWidth,
    })
    el.classList.remove('print-preview')
    const imgData = canvas.toDataURL('image/jpeg', 0.95)
    const imgWidth = canvas.width
    const imgHeight = canvas.height
    // Use A4 page size (210mm x 297mm) and center the image within it
    const pdf = new jsPDF({
      orientation: imgWidth > imgHeight ? 'landscape' : 'portrait',
      unit: 'mm',
      format: 'a4',
    })
    const pageWidth = pdf.internal.pageSize.getWidth()
    const pageHeight = pdf.internal.pageSize.getHeight()
    const margin = 10 // 10mm margin on each side
    const maxWidth = pageWidth - margin * 2
    const maxHeight = pageHeight - margin * 2
    // Calculate scaling to fit within the page with margins
    const scaleRatio = Math.min(maxWidth / imgWidth, maxHeight / imgHeight)
    const finalWidth = imgWidth * scaleRatio
    const finalHeight = imgHeight * scaleRatio
    // Center the image on the page
    const xOffset = (pageWidth - finalWidth) / 2
    const yOffset = (pageHeight - finalHeight) / 2
    pdf.addImage(imgData, 'JPEG', xOffset, yOffset, finalWidth, finalHeight)
    pdf.save(`Exam_Routine_${new Date().toISOString().split('T')[0]}.pdf`)
    showToastMessage('PDF downloaded!')
  } catch (e) {
    showToastMessage('PDF export failed. Use Print button instead.', 'error')
  }
}

async function exportAsImage() {
  showExportMenu.value = false
  try {
    const { default: html2canvas } = await import('html2canvas')
    // Capture the grid wrapper specifically for a cleaner image
    const el = document.querySelector('.routine-grid-wrapper') || pageContainerRef.value
    if (!el) return
    const canvas = await html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff' })
    const link = document.createElement('a')
    link.download = `My_Exam_Routine_${new Date().toISOString().split('T')[0]}.png`
    link.href = canvas.toDataURL('image/png')
    link.click()
    showToastMessage('Image downloaded!')
  } catch (e) {
    showToastMessage('Image export requires html2canvas. Install: npm install html2canvas', 'error')
  }
}

function printRoutine() {
  showExportMenu.value = false
  window.print()
}

const SUBJECT_COLORS = [
  '#4f46e5', '#0891b2', '#059669', '#d97706', '#dc2626',
  '#7c3aed', '#db2777', '#2563eb', '#16a34a', '#ca8a04',
  '#9333ea', '#e11d48', '#0d9488', '#65a30d', '#f97315',
]

const reminderOptions = [
  { value: 15, label: '15 minutes before' },
  { value: 30, label: '30 minutes before' },
  { value: 60, label: '1 hour before' },
  { value: 120, label: '2 hours before' },
  { value: 1440, label: '1 day before' },
]

/** Parse a date value (ISO string, Y-m-d, etc.) to YYYY-MM-DD */
function normalizeDate(val) {
  if (!val) return ''
  if (typeof val === 'string' && val.includes('T')) {
    return val.split('T')[0]
  }
  return val
}

const todayDate = () => {
  const now = new Date()
  const y = now.getFullYear()
  const m = String(now.getMonth() + 1).padStart(2, '0')
  const d = String(now.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

const filteredRoutines = computed(() => {
  let list = [...routines.value]

  if (viewMode.value === 'upcoming') {
    list = list.filter(r => !isPast(r.exam_date, r.end_time))
  } else if (viewMode.value === 'past') {
    list = list.filter(r => isPast(r.exam_date, r.end_time))
  }

  return list.sort((a, b) => {
    const dateA = normalizeDate(a.exam_date)
    const dateB = normalizeDate(b.exam_date)
    const cmp = dateA.localeCompare(dateB)
    if (cmp !== 0) return cmp
    return (a.start_time || '00:00').localeCompare(b.start_time || '00:00')
  })
})

const eligibilityExamTabs = computed(() => {
  const map = new Map()
  for (const r of routines.value) {
    if (!r.exam_id) continue
    const id = String(r.exam_id)
    if (!map.has(id)) {
      map.set(id, {
        id,
        name: r.exam_name || r.exam?.name || 'Exam',
      })
    }
  }
  return [...map.values()]
})

const selectedEligibilityExamName = computed(() => {
  const tab = eligibilityExamTabs.value.find((t) => t.id === selectedEligibilityExamId.value)
  return tab?.name || 'Exam'
})

const primaryExamEligibility = computed(() => {
  const id = selectedEligibilityExamId.value
  return id ? eligibilityByExam.value[id] : null
})

const eligibilityBannerClass = computed(() => {
  const e = primaryExamEligibility.value
  if (!e?.check_enabled) return 'banner-off'
  return `banner-${e.status || 'eligible'}`
})

const eligibilityShortText = computed(() => {
  const e = primaryExamEligibility.value
  if (!e) return ''
  if (!e.check_enabled) {
    if (e.exam_fee_unpaid) return e.message || 'Pay exam fee to unlock admit card.'
    return ''
  }
  const min = e.thresholds?.eligible_min ?? 75
  const pct = e.attendance_percent != null ? `${Number(e.attendance_percent).toFixed(0)}%` : '—'
  if (e.status === 'blocked') return `Attendance ${pct} (need ${min}%).`
  if (e.exam_fee_unpaid) return 'Pay exam fee to unlock admit card.'
  if (!canViewAdmitCard.value && e.message) return e.message
  return ''
})

const canViewAdmitCard = computed(() => {
  const e = primaryExamEligibility.value
  if (!selectedEligibilityExamId.value || !e) return false
  return e.can_download_admit !== false
})

const showPayExamFeeLink = computed(() => !!primaryExamEligibility.value?.exam_fee_unpaid)

const admitCardRoute = computed(() => ({
  name: 'StudentExamAdmit',
  params: { examId: selectedEligibilityExamId.value },
  query: { from: 'routines', exam_id: selectedEligibilityExamId.value },
}))

const examFeeInfoDisplay = computed(() => {
  const e = primaryExamEligibility.value
  if (!e?.exam_fee_applicable) return null
  return e.exam_fee_info || e.unpaid_exam_fee || null
})

const formatExamFeeAmount = (amount) => {
  const n = Number(amount || 0)
  return new Intl.NumberFormat('en-BD', { maximumFractionDigits: 0 }).format(n)
}

const formatExamFeeDate = (dateStr) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (Number.isNaN(d.getTime())) return String(dateStr).slice(0, 10)
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

const examFeePayRoute = computed(() => {
  const e = primaryExamEligibility.value
  const enrollmentId = e?.enrollment_id || null
  const query = {
    category: 'event_based',
    exam_id: selectedEligibilityExamId.value,
    view: 'detail',
  }
  if (e?.unpaid_exam_fee?.notification_id) {
    query.notify_fee_id = `notif_${e.unpaid_exam_fee.notification_id}`
  }
  if (enrollmentId) {
    return { name: 'StudentFeePaymentDetail', params: { enrollmentId }, query }
  }
  return { name: 'StudentFeePayment', query }
})

function pickDefaultEligibilityExam() {
  const q = route.query.exam_id ? String(route.query.exam_id) : ''
  if (q && eligibilityExamTabs.value.some((t) => t.id === q)) {
    selectedEligibilityExamId.value = q
    return
  }
  const withCheck = eligibilityExamTabs.value.find((t) => eligibilityByExam.value[t.id]?.check_enabled)
  if (withCheck) {
    selectedEligibilityExamId.value = withCheck.id
    return
  }
  if (eligibilityExamTabs.value.length) {
    selectedEligibilityExamId.value = eligibilityExamTabs.value[0].id
  }
}

function selectEligibilityExam(examId) {
  selectedEligibilityExamId.value = String(examId)
  router.replace({ query: { ...route.query, exam_id: selectedEligibilityExamId.value } })
}

const totalExams = computed(() => routines.value.length)

const upcomingCount = computed(() => {
  return routines.value.filter(r => !isPast(r.exam_date, r.end_time)).length
})

const completedCount = computed(() => {
  return routines.value.filter(r => isPast(r.exam_date, r.end_time)).length
})

const subjectsCount = computed(() => {
  const subjects = new Set(routines.value.map(r => r.subject_id || r.subject?.id).filter(Boolean))
  return subjects.size
})

function examStartMs(routine) {
  const d = normalizeDate(routine.exam_date)
  if (!d) return 0
  return new Date(`${d}T${routine.start_time || '00:00'}`).getTime()
}

const nextExam = computed(() => {
  const now = Date.now()
  return routines.value
    .filter(r => examStartMs(r) > now || (!isPast(r.exam_date, r.end_time) && examStartMs(r) <= now))
    .sort((a, b) => {
      const dateA = normalizeDate(a.exam_date)
      const dateB = normalizeDate(b.exam_date)
      const cmp = dateA.localeCompare(dateB)
      if (cmp !== 0) return cmp
      return (a.start_time || '00:00').localeCompare(b.start_time || '00:00')
    })[0] || null
})

const filterTabs = computed(() => [
  { key: 'all', label: 'All Exams', count: routines.value.length },
  { key: 'upcoming', label: 'Upcoming', count: upcomingCount.value },
  { key: 'past', label: 'Past', count: completedCount.value },
])

// ===== PRINT HEADER DATA =====
const printHeader = computed(() => {
  const list = routines.value
  if (!list.length) {
    return { examName: 'Exam Routine', examType: '', dateRange: '' }
  }
  // Get exam name and type from first routine
  const first = list[0]
  const examName = first.exam_name || first.exam?.name || 'Exam Routine'
  const examType = first.exam_type_name || first.exam?.exam_type?.name || ''
  // Find earliest and latest exam dates
  let earliest = null
  let latest = null
  for (const r of list) {
    const d = normalizeDate(r.exam_date)
    if (!d) continue
    if (!earliest || d < earliest) earliest = d
    if (!latest || d > latest) latest = d
  }
  let dateRange = ''
  if (earliest && latest) {
    const fmt = (d) => {
      const p = d.split('-')
      const dt = new Date(parseInt(p[0]), parseInt(p[1]) - 1, parseInt(p[2]))
      return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
    }
    dateRange = earliest === latest ? fmt(earliest) : `${fmt(earliest)} — ${fmt(latest)}`
  }
  return { examName, examType, dateRange }
})

function formatTime(time) {
  if (!time) return '--:--'
  const parts = time.split(':')
  const h = parseInt(parts[0])
  const m = parts[1] || '00'
  const ampm = h >= 12 ? 'PM' : 'AM'
  const hour12 = h % 12 || 12
  return `${hour12}:${m} ${ampm}`
}

function formatDate(date) {
  const d = normalizeDate(date)
  if (!d) return ''
  const parts = d.split('-')
  const dt = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]))
  return dt.toLocaleDateString('en-US', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
  })
}

function getDayName(date) {
  const d = normalizeDate(date)
  if (!d) return ''
  const parts = d.split('-')
  const dt = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]))
  return dt.toLocaleDateString('en-US', { weekday: 'short' })
}

function getDayNumber(date) {
  const d = normalizeDate(date)
  if (!d) return ''
  const parts = d.split('-')
  return parseInt(parts[2])
}

function getMonthName(date) {
  const d = normalizeDate(date)
  if (!d) return ''
  const parts = d.split('-')
  const dt = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]))
  return dt.toLocaleDateString('en-US', { month: 'short' })
}

function isToday(date) {
  return normalizeDate(date) === todayDate
}

function normalizeTime(time) {
  if (!time) return '23:59:59'
  const t = String(time)
  if (t.length === 5) return `${t}:00`
  return t.substring(0, 8)
}

function isPast(date, endTime) {
  const d = normalizeDate(date)
  if (!d) return false
  const today = todayDate()
  if (d < today) return true
  if (d === today) {
    const now = new Date()
    const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:00`
    return currentTime > normalizeTime(endTime)
  }
  return false
}

function getSubjectColor(subjectName) {
  if (!subjectName) return SUBJECT_COLORS[0]
  let hash = 0
  for (let i = 0; i < subjectName.length; i++) {
    hash = subjectName.charCodeAt(i) + ((hash << 5) - hash)
  }
  return SUBJECT_COLORS[Math.abs(hash) % SUBJECT_COLORS.length]
}

function padTime(n) {
  return String(n ?? 0).padStart(2, '0')
}

function updateCountdown() {
  if (!nextExam.value) {
    countdownActive.value = false
    return
  }
  const d = normalizeDate(nextExam.value.exam_date)
  const examDate = new Date(`${d}T${nextExam.value.start_time || '00:00'}`)
  const diff = examDate.getTime() - Date.now()

  if (diff <= 0) {
    countdownActive.value = false
    countdown.value = { days: 0, hours: 0, minutes: 0, seconds: 0 }
    return
  }

  countdownActive.value = true
  countdown.value = {
    days: Math.floor(diff / 86400000),
    hours: Math.floor((diff % 86400000) / 3600000),
    minutes: Math.floor((diff % 3600000) / 60000),
    seconds: Math.floor((diff % 60000) / 1000),
  }
}

function showToastMessage(message, type = 'success') {
  toastMessage.value = message
  toastType.value = type
  showToast.value = true
  setTimeout(() => { showToast.value = false }, 3000)
}

function setReminder(routine) {
  reminderSlot.value = routine
  selectedReminder.value = 30
  showReminderModal.value = true
}

async function submitReminder() {
  if (!reminderSlot.value) return
  reminderSubmitting.value = true
  try {
    await examService.student.setReminder(reminderSlot.value.id, {
      reminder_minutes: selectedReminder.value,
    })
    showReminderModal.value = false
    showToastMessage('Reminder set successfully!')
  } catch (e) {
    showToastMessage(e.response?.data?.message || 'Failed to set reminder', 'error')
  } finally {
    reminderSubmitting.value = false
  }
}

async function loadExamEligibilities() {
  const ids = [...new Set(routines.value.map((r) => r.exam_id).filter(Boolean))]
  const map = {}
  await Promise.all(
    ids.map(async (id) => {
      try {
        const res = await examService.student.eligibilityMe(id)
        map[id] = extractData(res, {})
      } catch {
        map[id] = { check_enabled: false }
      }
    })
  )
  eligibilityByExam.value = map
  pickDefaultEligibilityExam()
}

watch(() => route.query.exam_id, (id) => {
  if (!id) return
  const sid = String(id)
  if (eligibilityExamTabs.value.some((t) => t.id === sid)) {
    selectedEligibilityExamId.value = sid
  }
})

async function fetchRoutines() {
  loading.value = true
  error.value = null
  try {
    const res = await examService.student.routines()
    const data = extractData(res, null)
    if (Array.isArray(data)) {
      routines.value = data
    } else if (data?.flat && Array.isArray(data.flat)) {
      routines.value = data.flat
    } else if (data?.routines && Array.isArray(data.routines)) {
      routines.value = data.routines
    } else {
      routines.value = []
    }
    await loadExamEligibilities()
    updateCountdown()
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load exam routines'
  } finally {
    loading.value = false
  }
}

watch(nextExam, () => updateCountdown())

onMounted(() => {
  fetchRoutines()
  updateCountdown()
  countdownInterval = setInterval(updateCountdown, 1000)
})

onUnmounted(() => {
  if (countdownInterval) clearInterval(countdownInterval)
})
</script>

<style scoped>
.page-container {
  max-width: 960px;
  margin: 0 auto;
}

.elig-compact { margin-bottom: 1rem; }
.elig-tabs { display: flex; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.5rem; }
.elig-tab {
  border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-muted);
  padding: 0.3rem 0.65rem; border-radius: 999px; font-size: 0.75rem; cursor: pointer;
}
.elig-tab.active { background: #4f46e5; border-color: #4f46e5; color: #fff; }
.elig-card {
  border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-card); padding: 0.7rem 0.85rem;
}
.elig-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; }
.elig-info { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; min-width: 0; }
.elig-name { font-size: 0.88rem; font-weight: 700; color: var(--text-dark); }
.elig-fee { font-size: 0.75rem; color: var(--text-muted); }
.elig-fee em { font-style: normal; color: #b45309; }
.elig-actions { display: flex; gap: 0.4rem; flex-shrink: 0; }
.elig-btn {
  text-decoration: none; font-size: 0.75rem; font-weight: 600;
  padding: 0.35rem 0.7rem; border-radius: 6px; display: inline-flex; align-items: center;
}
.elig-btn.primary { background: #4f46e5; color: #fff; }
.elig-btn.outline { border: 1px solid var(--border-color); color: var(--text-secondary); background: var(--bg-card); }
.elig-note { margin: 0.45rem 0 0; font-size: 0.75rem; color: var(--text-muted); }
.elig-card.banner-blocked { border-color: #fecaca; background: #fffafb; }
.elig-card.banner-warning { border-color: #fde68a; background: #fffef7; }
.elig-card.banner-eligible,
.elig-card.banner-overridden,
.elig-card.banner-off { border-color: #bbf7d0; background: #f8fffb; }

/* ── Header ── */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.header-left h1 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-dark);
}

.badge-count {
  background: #eef2ff;
  color: var(--primary-color);
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.625rem;
  border-radius: 999px;
}

/* ── Stats ── */
.stats-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

@media (min-width: 640px) {
  .stats-row {
    grid-template-columns: repeat(4, 1fr);
  }
}

.stat-card {
  background: var(--bg-card);
  border-radius: 0.75rem;
  padding: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.625rem;
  border: 1px solid var(--border-color);
  transition: box-shadow 0.2s;
}

.stat-card:hover {
  box-shadow: var(--shadow-md);
}

.stat-icon {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-val {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--text-dark);
  line-height: 1.2;
}

.stat-lbl {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin-top: 0.125rem;
}

/* ── Next exam countdown ── */
.next-exam-card {
  background: linear-gradient(135deg, #312e81 0%, #4f46e5 55%, #6366f1 100%);
  border-radius: 12px;
  padding: 1rem 1.15rem;
  margin-bottom: 1.25rem;
  color: #fff;
  box-shadow: 0 8px 24px rgba(79, 70, 229, 0.22);
}
.next-exam-main {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}
.next-label {
  margin: 0 0 0.15rem;
  font-size: 0.68rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  opacity: 0.8;
}
.next-exam-card h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
}
.next-meta {
  margin: 0.25rem 0 0;
  font-size: 0.78rem;
  opacity: 0.85;
}
.next-timer {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 10px;
  padding: 0.55rem 0.75rem;
}
.timer-unit { text-align: center; min-width: 42px; }
.t-val { display: block; font-size: 1.35rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
.t-lbl { display: block; margin-top: 0.15rem; font-size: 0.58rem; text-transform: uppercase; opacity: 0.75; }
.t-sep { font-size: 1.1rem; font-weight: 700; opacity: 0.65; padding-bottom: 0.55rem; }
.next-live {
  font-size: 0.85rem;
  font-weight: 700;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 999px;
  padding: 0.45rem 0.85rem;
}

.countdown-sep {
  font-size: 1.5rem;
  font-weight: 700;
  opacity: 0.5;
  margin-top: -0.5rem;
}

.eligibility-exam-section {
  margin-bottom: 1rem;
}
.eligibility-exam-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-bottom: 0.5rem;
}
.eligibility-exam-tab {
  padding: 0.35rem 0.65rem;
  border-radius: 999px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-secondary);
  cursor: pointer;
}
.eligibility-exam-tab.active {
  background: var(--primary-color, #4f46e5);
  border-color: var(--primary-color, #4f46e5);
  color: #fff;
}
.student-eligibility-banner.banner-off {
  background: var(--bg-surface-muted);
  border-color: #e2e8f0;
  color: var(--text-secondary);
}

/* ── Filter Tabs ── */
.filter-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  background: #f3f4f6;
  border-radius: 0.5rem;
  padding: 0.25rem;
}

.filter-tab {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  padding: 0.5rem 0.75rem;
  border: none;
  background: transparent;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--text-muted);
  cursor: pointer;
  transition: all 0.2s;
}

.filter-tab.active {
  background: var(--bg-card);
  color: var(--text-dark);
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.filter-tab:hover:not(.active) {
  color: var(--text-dark);
}

.filter-count {
  font-size: 0.6875rem;
  background: #e5e7eb;
  color: var(--text-muted);
  padding: 0.0625rem 0.4375rem;
  border-radius: 999px;
  min-width: 1.25rem;
  text-align: center;
}

.filter-tab.active .filter-count {
  background: #eef2ff;
  color: var(--primary-color);
}

/* ── Routine Grid ── */
.routine-grid {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.routine-card {
  display: flex;
  gap: 1rem;
  background: var(--bg-card);
  border-radius: 0.75rem;
  padding: 1rem;
  border: 1px solid var(--border-color);
  transition: box-shadow 0.2s, opacity 0.2s;
  align-items: center;
}

.routine-card:hover {
  box-shadow: var(--shadow-md);
}

.routine-card.is-past {
  opacity: 0.75;
}

.routine-card.is-past:hover {
  opacity: 1;
}

.routine-card.is-today {
  border-color: var(--primary-color);
  background: #fafaff;
}

/* Date Column */
.card-date-col {
  text-align: center;
  min-width: 64px;
  padding: 0.375rem;
  background: var(--bg-accent);
  border-radius: 0.5rem;
  flex-shrink: 0;
}

.card-date-day {
  font-size: 0.625rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
}

.card-date-num {
  font-size: 1.375rem;
  font-weight: 700;
  color: var(--text-dark);
  line-height: 1.2;
}

.card-date-month {
  font-size: 0.625rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
}

.today-indicator {
  margin-top: 0.25rem;
  font-size: 0.5625rem;
  font-weight: 700;
  color: #d97706;
  background: #fef3c7;
  padding: 0.0625rem 0.375rem;
  border-radius: 999px;
}

.past-indicator {
  margin-top: 0.25rem;
  font-size: 0.5625rem;
  font-weight: 600;
  color: var(--text-muted);
  background: #f3f4f6;
  padding: 0.0625rem 0.375rem;
  border-radius: 999px;
}

/* Info Column */
.card-info-col {
  flex: 1;
  min-width: 0;
}

.card-top-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 0.5rem;
}

.subject-badge {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.1875rem 0.5rem;
  border-radius: 0.375rem;
  white-space: nowrap;
}

.exam-name {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 500;
}

.card-meta-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.6875rem;
  color: var(--text-muted);
}

.meta-item svg {
  flex-shrink: 0;
}

/* Action Column */
.card-action-col {
  flex-shrink: 0;
}

.btn-remind {
  display: inline-flex;
  align-items: center;
  gap: 0.3125rem;
  padding: 0.375rem 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-dark);
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-remind:hover:not(:disabled) {
  background: var(--bg-accent);
  border-color: #d1d5db;
}

.btn-remind:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* ── Buttons ── */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 600;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-dark);
}

.btn-outline {
  background: var(--bg-card);
  color: var(--text-dark);
  border-color: var(--border-color);
}

.btn-outline:hover {
  background: var(--bg-accent);
  border-color: #d1d5db;
}

/* ── Modal ── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-container {
  background: var(--bg-card);
  border-radius: 0.75rem;
  width: 100%;
  max-width: 420px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--text-muted);
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.modal-body {
  padding: 1.25rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  border-top: 1px solid var(--border-color);
}

.reminder-options {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.reminder-option {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.75rem 1rem;
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;
}

.reminder-option:hover {
  border-color: var(--primary-color);
}

.reminder-option.active {
  border-color: var(--primary-color);
  background: #eef2ff;
}

.reminder-option input[type="radio"] {
  accent-color: var(--primary-color);
}

.reminder-option-label {
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--text-dark);
}

/* ── States ── */
.loading-state, .error-state, .empty-state {
  text-align: center;
  padding: 3rem;
  background: var(--bg-card);
  border-radius: 0.75rem;
  border: 1px solid var(--border-color);
}

.loading-state p, .error-state p, .empty-state p {
  margin: 0.75rem 0 0;
  color: var(--text-muted);
  font-size: 0.875rem;
}

.empty-state h3 {
  margin: 0.75rem 0 0.25rem;
  font-size: 1rem;
  color: var(--text-dark);
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--border-color);
  border-top-color: var(--primary-color);
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  margin: 0 auto;
}

.spinner-sm {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ── Toast ── */
.toast {
  position: fixed;
  bottom: 1.5rem;
  right: 1.5rem;
  padding: 0.75rem 1.25rem;
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 600;
  color: white;
  z-index: 2000;
  animation: slideIn 0.3s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.toast.success {
  background: var(--secondary-color);
}

.toast.error {
  background: #dc2626;
}

@keyframes slideIn {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

/* ── Header Right / Export Dropdown ── */
.header-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.export-dropdown {
  position: relative;
  display: inline-block;
}

.export-menu {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  box-shadow: 0 8px 30px rgba(0,0,0,0.12);
  min-width: 180px;
  z-index: 100;
  overflow: hidden;
}

.export-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.55rem 0.85rem;
  border: none;
  background: none;
  font-size: 0.75rem;
  color: var(--text-dark);
  cursor: pointer;
  transition: background 0.15s;
  font-family: inherit;
  text-align: left;
}

.export-option:hover {
  background: var(--bg-accent);
}

.export-option svg {
  flex-shrink: 0;
  color: var(--text-muted);
}

.btn-sm {
  padding: 0.35rem 0.65rem;
  font-size: 0.75rem;
}

/* ── View Toggle ── */
.view-toggle {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.2rem;
  background: #f3f4f6;
  border-radius: 0.5rem;
}
.view-toggle .btn {
  border-radius: 0.375rem;
  padding: 0.3rem 0.55rem;
  font-size: 0.7rem;
  border: 1px solid transparent;
}
.view-toggle .btn-primary {
  background: var(--bg-card);
  color: var(--text-dark);
  border-color: #e2e8f0;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.view-toggle .btn-outline {
  background: transparent;
  color: var(--text-muted);
  border-color: transparent;
}
.view-toggle .btn-outline:hover {
  color: var(--text-dark);
  background: rgba(0,0,0,0.04);
}

/* ── Grid View (admin-style: time slots × days with exam cards in cells) ── */
.routine-grid-wrapper {
  background: var(--bg-card);
  border-radius: 0.75rem;
  border: 1px solid var(--border-color);
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.grid-scroll { overflow-x: auto; }
.routine-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 850px;
  table-layout: fixed;
}
.routine-table th,
.routine-table td {
  border: 1px solid var(--border-color);
  padding: 0;
  vertical-align: top;
}
.routine-table thead th {
  background: var(--bg-surface-muted);
  padding: 0.5rem 0.4rem;
  text-align: center;
  font-weight: 600;
  font-size: 0.7rem;
  color: var(--text-secondary);
  position: sticky;
  top: 0;
  z-index: 10;
}
.th-time { width: 90px; min-width: 90px; }
.routine-table .th-day { width: calc((100% - 90px) / 7); }

/* Day-specific header tints */
.th-day-saturday { background: #eff6ff; }
.th-day-sunday { background: #ecfdf5; }
.th-day-monday { background: #fff7ed; }
.th-day-tuesday { background: #f5f3ff; }
.th-day-wednesday { background: #fdf2f8; }
.th-day-thursday { background: #ecfeff; }
.th-day-friday { background: #fef2f2; }

.day-name { display: block; font-size: 0.8rem; font-weight: 700; }
.day-date { display: block; font-size: 0.55rem; font-weight: 400; color: var(--text-muted); margin-top: 0.05rem; }

/* Time Slot Column */
.routine-table .td-time {
  background: var(--bg-surface-muted);
  padding: 0.25rem 0.35rem;
  vertical-align: middle !important;
}
.time-slot-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.05rem;
}
.slot-range {
  font-size: 0.55rem;
  color: var(--text-muted);
  text-align: center;
  line-height: 1.2;
}
.lunch-range { color: #d97706; font-weight: 600; }
.slot-duration {
  font-size: 0.5rem;
  color: var(--text-muted);
  text-align: center;
  line-height: 1.1;
  margin-top: 0.1rem;
  font-weight: 500;
}

/* Friday OFF */
.td-friday { background: #fef2f2; }
.off-day-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem 0.5rem;
  min-height: 70px;
}
.off-text { font-size: 0.65rem; font-weight: 700; color: #ef4444; letter-spacing: 0.1em; }

/* Lunch */
.lunch-row .td-time { background: #fffbeb; }
.lunch-row td { background: #fffbeb; }
.td-lunch { background: #fffbeb !important; }
.lunch-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.5rem;
  min-height: 40px;
}
.lunch-text { font-size: 0.65rem; font-weight: 700; color: #d97706; letter-spacing: 0.15em; }

/* Exam Cards */
.cell-exams {
  padding: 0.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-height: 60px;
}
.exam-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-left: 3px solid #3B82F6;
  border-radius: 4px;
  padding: 0.25rem 0.35rem;
}
.card-batch {
  display: flex;
  align-items: center;
  gap: 0.2rem;
  margin-bottom: 0.05rem;
}
.batch-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.card-batch-name { font-size: 0.5rem; font-weight: 700; color: var(--text-secondary); }
.batch-sep { border: none; border-top: 1px dashed #cbd5e1; margin: 0.15rem 0; }
.card-subject { font-size: 0.65rem; font-weight: 600; color: var(--text-dark); line-height: 1.3; }
.card-exam-type { font-size: 0.55rem; color: var(--text-muted); line-height: 1.3; font-style: italic; }
.card-room { font-size: 0.55rem; font-weight: 600; opacity: 0.9; margin-top: 0.1rem; }

/* Status Badges */
.status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  margin-left: auto;
  flex-shrink: 0;
  cursor: default;
  font-size: 11px;
  font-weight: 700;
  line-height: 1;
  transition: transform 0.15s ease;
}
.status-badge:hover {
  transform: scale(1.2);
}
.status-today {
  background: #fef3c7;
  color: #92400e;
  border: 1.5px solid #fbbf24;
}
.status-published {
  background: #d1fae5;
  color: #065f46;
  border: 1.5px solid #34d399;
}
.status-completed {
  background: #dbeafe;
  color: #1e40af;
  border: 1.5px solid #60a5fa;
}

.cell-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 50px;
}
.empty-dash { color: #cbd5e1; font-size: 0.85rem; }

/* ── Print Header ── */
.print-header {
  display: none;
}
@media print {
  .print-header {
    display: block !important;
    text-align: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #1e293b;
  }
  .print-exam-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 0.35rem 0;
  }
  .print-exam-meta {
    font-size: 0.8125rem;
    color: var(--text-secondary);
    margin: 0;
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
  }
  .print-exam-type {
    font-weight: 600;
    color: #3B82F6;
  }
  .print-date-range {
    color: var(--text-muted);
  }
}

/* ── Print Preview class (used by exportAsPDF for html2canvas capture) ── */
.print-preview .no-print {
  display: none !important;
}
.print-preview .print-header {
  display: block !important;
  text-align: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #1e293b;
}
.print-preview .page-container {
  max-width: 100%;
  padding: 0.3in;
  margin: 0 auto;
}
.print-preview .routine-grid-wrapper {
  break-inside: avoid;
  box-shadow: none;
  border: 1px solid var(--border-color);
}
.print-preview .exam-card:hover { box-shadow: none; }
.print-preview .routine-table th,
.print-preview .routine-table td {
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}
.print-preview .exam-card {
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

/* ── Print Styles ── */
@media print {
  /* Hide layout chrome (sidebar, header, navbar) */
  :global(.dashboard-layout > .sidebar),
  :global(.dashboard-layout > .sidebar-nav),
  :global(.sidebar),
  :global(.header),
  :global(.header-bar),
  :global(.main-wrapper > .header),
  :global(.main-wrapper > .header-bar),
  :global(footer),
  :global(.footer),
  :global(.footer-bar) {
    display: none !important;
  }
  :global(.main-wrapper) {
    margin-left: 0 !important;
    padding-left: 0 !important;
    width: 100% !important;
  }
  :global(.main-content) {
    padding: 0 !important;
    margin: 0 !important;
  }
  :global(.dashboard-layout) {
    display: block !important;
  }
  .no-print {
    display: none !important;
  }
  .page-container {
    max-width: 100%;
    padding: 0.3in;
    margin: 0 auto;
  }
  .view-toggle { display: none !important; }
  /* Hide all UI controls during print */
  .stats-row,
  .countdown-card,
  .filter-tabs,
  .header-right,
  .export-dropdown,
  .export-menu,
  .view-toggle,
  .btn-remind,
  .card-action-col,
  .toast,
  .modal-overlay,
  .modal-container {
    display: none !important;
  }
  .routine-card {
    break-inside: avoid;
    border: 1px solid var(--border-color) !important;
    box-shadow: none !important;
    opacity: 1 !important;
  }
  .routine-card.is-past {
    opacity: 0.6 !important;
  }
  .routine-grid-wrapper {
    break-inside: avoid;
    box-shadow: none;
    border: 1px solid var(--border-color);
  }
  .exam-card:hover { box-shadow: none; }
  /* Ensure grid table prints with colors */
  .routine-table th,
  .routine-table td {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .exam-card {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}
</style>

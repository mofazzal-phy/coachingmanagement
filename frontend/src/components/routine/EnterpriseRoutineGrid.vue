<template>
  <div class="enterprise-routine-grid" :class="{ 'is-print-mode': printMode }">
    <!-- ===== COMPACT HEADER ===== -->
    <div class="grid-header">
      <div class="header-left">
        <div class="header-brand">
          <div class="brand-icon">
            <svg viewBox="0 0 40 40" width="32" height="32" fill="none">
              <rect width="40" height="40" rx="8" fill="#6366F1"/>
              <text x="20" y="26" text-anchor="middle" fill="white" font-size="18" font-weight="bold">C</text>
            </svg>
          </div>
          <div class="brand-info">
            <strong class="brand-name">{{ coachingName || 'Coaching Center' }}</strong>
            <span class="brand-meta">{{ metaInfo.class_name || '—' }} · {{ metaInfo.course_name || '—' }} · {{ effectiveDate }}</span>
          </div>
        </div>
      </div>
      <div class="header-right">
        <!-- Batch Selector (multi-batch) -->
        <div v-if="multiBatchEnabled && batches.length" class="batch-selector" ref="batchSelectorRef">
          <button class="header-btn batch-select-btn" @click="showBatchDropdown = !showBatchDropdown">
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
            <span>{{ selectedBatchIds.length }} batch{{ selectedBatchIds.length !== 1 ? 'es' : '' }}</span>
          </button>
          <div v-if="showBatchDropdown" class="batch-dropdown">
            <div
              v-for="batch in batches"
              :key="batch.id"
              class="batch-option"
              :class="{ selected: selectedBatchIds.includes(batch.id) }"
              @click="toggleBatch(batch.id)"
            >
              <span class="batch-opt-check">
                <svg v-if="selectedBatchIds.includes(batch.id)" viewBox="0 0 24 24" width="12" height="12" fill="currentColor">
                  <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
              </span>
              <span>{{ batch.name || batch.title }}</span>
            </div>
          </div>
        </div>

        <!-- Legend Toggle -->
        <button
          class="header-btn legend-toggle"
          @click="showLegend = !showLegend"
          :title="showLegend ? 'Hide legend' : 'Show legend'"
        >
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
          </svg>
          <span>Legend</span>
        </button>

        <!-- More Actions Dropdown -->
        <div class="more-actions" ref="moreActionsRef">
          <button class="header-btn more-btn" @click="showMoreMenu = !showMoreMenu" title="More actions">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
            </svg>
          </button>
          <div v-if="showMoreMenu" class="more-dropdown">
            <button class="more-dropdown-item" :disabled="!hasSelection" @click="emit('publish-all'); showMoreMenu = false">
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              Publish All
            </button>
            <button class="more-dropdown-item" :disabled="!hasSelection" @click="emit('archive-all'); showMoreMenu = false">
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
              Archive All
            </button>
            <button class="more-dropdown-item" :disabled="!hasSelection" @click="emit('check-conflicts'); showMoreMenu = false">
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
              Conflicts
            </button>
            <button class="more-dropdown-item" :disabled="!hasSelection" @click="emit('set-lunch-break'); showMoreMenu = false">
              <span style="margin-right:4px;">🍽</span>
              Lunch Break
            </button>
            <button class="more-dropdown-item" :disabled="!hasSelection" @click="emit('set-off-day'); showMoreMenu = false">
              <span style="margin-right:4px;">🚫</span>
              Off Day
            </button>
            <div class="more-dropdown-divider"></div>
            <button class="more-dropdown-item" :disabled="!hasRoutines" @click="emit('toggle-swap'); showMoreMenu = false">
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
              {{ store?.swapMode ? 'Swap: ON' : 'Swap Mode' }}
            </button>
            <button class="more-dropdown-item" :disabled="!hasRoutines" @click="emit('export-pdf'); showMoreMenu = false">
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              Export PDF
            </button>
          </div>
        </div>

        <!-- Add Routine -->
        <button class="header-btn add-btn" @click="showCreateWizard = true" title="Create New Routine">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14"/>
          </svg>
          <span>Add Routine</span>
        </button>
      </div>
    </div>

    <!-- ===== COLLAPSIBLE LEGEND BAR ===== -->
    <div v-if="showLegend" class="legend-bar">
      <div class="legend-section">
        <span class="legend-label">Subjects:</span>
        <span
          v-for="(color, subj) in subjectLegend"
          :key="subj"
          class="legend-chip"
        >
          <span class="legend-dot" :style="{ background: color }"></span>
          {{ subj }}
        </span>
        <span v-if="Object.keys(subjectLegend).length === 0" class="legend-empty">No subjects</span>
      </div>
      <div class="legend-section">
        <span class="legend-label">Stats:</span>
        <span class="legend-chip outlined">{{ regularSlots.length }} slots</span>
        <span class="legend-chip outlined">{{ legendStats.teacherCount }} teachers</span>
        <span class="legend-chip outlined">{{ legendStats.roomCount }} rooms</span>
        <span class="legend-chip outlined">{{ legendStats.batchCount }} batches</span>
        <span v-if="legendStats.lunchCount" class="legend-chip outlined">🍽 {{ legendStats.lunchCount }} lunch</span>
        <span v-if="legendStats.offDayCount" class="legend-chip outlined">🚫 {{ legendStats.offDayCount }} off</span>
      </div>
    </div>

    <!-- ===== TIMETABLE GRID ===== -->
    <div class="grid-scroll-container" ref="scrollContainer">
      <div class="grid-wrapper">
        <!-- Grid Header: Day Columns -->
        <div class="grid-header-row">
          <div class="time-col-header">Time / Day</div>
          <div
            v-for="day in columnDays"
            :key="day.key"
            class="day-col-header"
            :class="{ 'is-today': day.isToday }"
          >
            <div class="day-name-full">{{ day.labelFull }}</div>
            <div class="day-name-short">{{ day.label }}</div>
            <div class="day-date">{{ day.date }}</div>
          </div>
        </div>

        <!-- Grid Body: Time Slot Rows -->
        <div class="grid-body" :key="'grid-' + liveTick">
          <template v-for="(slot, slotIdx) in allSlots" :key="slotIdx">
            <!-- Lunch Break Row (merged across all days) -->
            <div v-if="slot.is_lunch_break" class="grid-row lunch-break-row">
              <div class="time-cell lunch-time-cell">
                <div class="lunch-time-label">
                  <span class="lunch-clock-icon">⏰</span>
                  <span>{{ formatTime(slot.start_time, slot.end_time) }}</span>
                </div>
              </div>
              <div
                v-for="day in columnDays"
                :key="day.key"
                class="day-cell lunch-day-cell"
                :class="{ 'is-today': day.isToday }"
              >
                <div class="lunch-content-wrapper">
                  <div class="lunch-divider-line"></div>
                  <div class="lunch-text-block">
                    <span class="lunch-icon-big">🍽</span>
                    <span class="lunch-text-main">L U N C H &nbsp; B R E A K</span>
                    <span class="lunch-text-sub">({{ formatTime(slot.start_time, slot.end_time) }})</span>
                  </div>
                  <div class="lunch-divider-line"></div>
                </div>
              </div>
            </div>

            <!-- Friday off-day slots are rendered as a single full-width bar below. -->
            <template v-else-if="slot.is_off_day"></template>

            <!-- Regular Slot Row -->
            <div v-else class="grid-row" :class="{ 'is-live-row': isSlotLive(slot) }">
              <!-- Time Column (sticky) -->
              <div class="time-cell" :class="{ 'is-live-time': isSlotLive(slot) }">
                <div class="time-slot-name">{{ slot.slot_name || slot.name || 'Slot' }}</div>
                <div class="time-slot-range">{{ formatTime(slot.start_time, slot.end_time) }}</div>
              </div>

              <!-- Day Cells -->
              <div
                v-for="day in columnDays"
                :key="day.key"
                class="day-cell"
                :class="{
                  'is-today': day.isToday,
                  'is-empty-cell': getCellRoutines(slot, day.key).length === 0,
                }"
              >
                <!-- Empty Cell -->
                <div v-if="getCellRoutines(slot, day.key).length === 0" class="empty-cell">
                  <span class="empty-dash">—</span>
                </div>

                <!-- Stacked Batch Cards -->
                <div v-else class="routine-stack">
                  <div
                    v-for="routine in getCellRoutines(slot, day.key)"
                    :key="routine.id"
                    class="routine-card"
                    :class="{
                      'is-live': isSlotLive(slot),
                      'is-draft': routine.status === 'draft',
                      'is-swap-target': swapMode,
                    }"
                    :style="getCardStyle(routine)"
                    :draggable="swapMode"
                    @dragstart="onDragStart($event, routine)"
                    @dragover.prevent="onDragOver($event, routine)"
                    @drop="onDrop($event, routine)"
                    @click="$emit('edit-slot', routine)"
                  >
                    <!-- Batch Name Row -->
                    <div class="card-batch-row">
                      <span
                        class="card-batch-name"
                        :style="{ color: getBatchColor(routine.batch_id || routine.batch?.id) }"
                      >
                        <span
                          class="batch-dot-sm"
                          :style="{ background: getBatchColor(routine.batch_id || routine.batch?.id) }"
                        ></span>
                        {{ routine.batch_name || routine.batch?.name || 'Batch' }}
                      </span>
                    </div>

                    <!-- Subject (own line) -->
                    <div class="card-detail-line card-detail-subject">{{ routine.subject_name || routine.subject?.name || 'Subject' }}</div>

                    <!-- Teacher (own line) -->
                    <div class="card-detail-line card-detail-teacher">
                      <svg viewBox="0 0 24 24" width="9" height="9" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                      </svg>
                      <span>{{ (routine.teacher_name || 'N/A') + ' Sir' }}</span>
                    </div>

                    <!-- Live Badge -->
                    <div v-if="isSlotLive(slot)" class="card-live-badge">LIVE</div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- Friday OFF bar (full width, like the exam routine) -->
        <div v-if="fridayDay" class="friday-off-bar">
          <span class="friday-off-label">OFF</span>
          <span class="friday-off-date">Friday, {{ fridayDay.date }}</span>
        </div>
      </div>
    </div>

    <!-- ===== FOOTER SECTION ===== -->
    <div class="grid-footer-section">
      <div class="footer-columns">
        <!-- Important Instructions -->
        <div class="footer-col instructions-col">
          <h4 class="footer-title">📋 Important Instructions</h4>
          <ul class="instructions-list">
            <li>All classes must start and end on time as per the schedule.</li>
            <li>Teachers must mark attendance at the beginning of each class.</li>
            <li>Any schedule changes must be approved by the academic coordinator.</li>
            <li>Students should report to class 5 minutes before the scheduled time.</li>
          </ul>
        </div>

        <!-- Dynamic Legend -->
        <div class="footer-col legend-col">
          <h4 class="footer-title">📌 Legend</h4>
          <div class="legend-grid">
            <div class="legend-row">
              <span class="legend-icon">👨‍🏫</span>
              <span class="legend-label">Teachers ({{ legendStats.teacherCount }})</span>
            </div>
            <div class="legend-row">
              <span class="legend-icon">🏠</span>
              <span class="legend-label">Rooms ({{ legendStats.roomCount }})</span>
            </div>
            <div class="legend-row">
              <span class="legend-icon">📚</span>
              <span class="legend-label">Subjects ({{ legendStats.subjectCount }})</span>
            </div>
            <div class="legend-row">
              <span class="legend-icon">👥</span>
              <span class="legend-label">Batches ({{ legendStats.batchCount }})</span>
            </div>
            <div class="legend-row">
              <span class="legend-icon">🍽</span>
              <span class="legend-label">Lunch Breaks ({{ legendStats.lunchCount }})</span>
            </div>
            <div class="legend-row">
              <span class="legend-icon">🚫</span>
              <span class="legend-label">Off Days ({{ legendStats.offDayCount }})</span>
            </div>
          </div>
        </div>

        <!-- Signature Section -->
        <div class="footer-col signature-col">
          <h4 class="footer-title">✍️ Signatures</h4>
          <div class="signature-block">
            <div class="signature-item">
              <div class="sig-line"></div>
              <span class="sig-label">Prepared By</span>
            </div>
            <div class="signature-item">
              <div class="sig-line"></div>
              <span class="sig-label">Checked By</span>
            </div>
            <div class="signature-item">
              <div class="sig-line"></div>
              <span class="sig-label">Approved By</span>
            </div>
          </div>
        </div>
      </div>
      <div class="footer-bottom">
        <span class="footer-copyright">© {{ new Date().getFullYear() }} {{ coachingName || 'Coaching Center' }}. All rights reserved.</span>
        <span class="footer-generated">Generated on {{ generatedDate }}</span>
      </div>
    </div>

    <!-- ===== EMPTY STATE ===== -->
    <div v-if="!loading && allSlots.length === 0" class="empty-state">
      <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.3">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
        <line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
      <h3>No Routines Found</h3>
      <p>Select batches and generate or add routines manually to get started.</p>
      <button class="empty-add-btn" @click="showCreateWizard = true">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14"/>
        </svg>
        Add Your First Routine
      </button>
    </div>

    <!-- ===== LOADING OVERLAY ===== -->
    <div v-if="loading" class="loading-overlay">
      <div class="spinner"></div>
      <span>Loading timetable...</span>
    </div>

    <!-- ===== CREATE ROUTINE WIZARD MODAL ===== -->
    <div v-if="showCreateWizard" class="modal-overlay" @click.self="closeCreateWizard">
      <div class="modal-dialog" style="max-width: 600px;">
        <!-- Header -->
        <div class="modal-header">
          <h3>Create Class Routine</h3>
          <button class="modal-close" @click="closeCreateWizard">✕</button>
        </div>

        <!-- Step Indicator -->
        <div class="wizard-steps">
          <div
            v-for="(step, idx) in wizardSteps"
            :key="idx"
            class="wizard-step"
          >
            <div
              class="wizard-step-circle"
              :class="wizardStep > idx ? 'completed' : wizardStep === idx ? 'active' : 'pending'"
            >
              <svg v-if="wizardStep > idx" class="wizard-step-check" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              <span v-else>{{ idx + 1 }}</span>
            </div>
            <span class="wizard-step-label" :class="wizardStep >= idx ? 'active' : ''">
              {{ step }}
            </span>
            <svg v-if="idx < wizardSteps.length - 1" class="wizard-step-arrow" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </div>
        </div>

        <!-- Step Content -->
        <div class="modal-body">
          <!-- Step 1: Class → Course → Batch (Cascading) -->
          <div v-if="wizardStep === 0">
            <!-- Class Select -->
            <div class="form-group">
              <label>Class <span class="required">*</span></label>
              <div v-if="wizLoading.classes" class="text-center py-2">
                <div class="spinner" style="margin: 0 auto 0.3rem; width:20px; height:20px;"></div>
                <p class="text-muted">Loading classes...</p>
              </div>
              <select
                v-else
                v-model="wizForm.class_id"
                class="form-control"
                required
              >
                <option value="" disabled>Select class</option>
                <option v-for="c in wizData.classes" :key="c.id" :value="c.id">
                  {{ c.name || c.title }}
                </option>
              </select>
            </div>

            <!-- Course Select (filtered by class) -->
            <div v-if="wizForm.class_id" class="form-group">
              <label>Course <span class="required">*</span></label>
              <div v-if="wizLoading.courses" class="text-center py-2">
                <div class="spinner" style="margin: 0 auto 0.3rem; width:20px; height:20px;"></div>
                <p class="text-muted">Loading courses...</p>
              </div>
              <select
                v-else
                v-model="wizForm.course_id"
                class="form-control"
                required
              >
                <option value="" disabled>Select course</option>
                <option v-for="co in wizFiltered.courses" :key="co.id" :value="co.id">
                  {{ co.name || co.title }}
                </option>
              </select>
              <p v-if="!wizLoading.courses && wizFiltered.courses.length === 0" class="text-muted" style="font-size:0.8rem; margin-top:0.25rem;">
                No courses available for this class
              </p>
            </div>

            <!-- Batch Select (filtered by course) -->
            <div v-if="wizForm.course_id" class="form-group">
              <label>Batch <span class="required">*</span></label>
              <div v-if="wizLoading.batches" class="text-center py-2">
                <div class="spinner" style="margin: 0 auto 0.3rem; width:20px; height:20px;"></div>
                <p class="text-muted">Loading batches...</p>
              </div>
              <select
                v-else
                v-model="wizForm.batch_id"
                class="form-control"
                required
              >
                <option value="" disabled>Select batch</option>
                <option v-for="b in wizFiltered.batches" :key="b.id" :value="b.id">
                  {{ b.name || b.title }}
                </option>
              </select>
              <p v-if="!wizLoading.batches && wizFiltered.batches.length === 0" class="text-muted" style="font-size:0.8rem; margin-top:0.25rem;">
                No batches available for this course
              </p>
            </div>
          </div>

          <!-- Step 2: Select Subject -->
          <div v-if="wizardStep === 1">
            <div class="form-group">
              <label>Select Subject <span class="required">*</span></label>
              <div v-if="wizLoading.subjects" class="text-center py-4">
                <div class="spinner" style="margin: 0 auto 0.5rem; width:24px; height:24px;"></div>
                <p class="text-muted">Loading subjects...</p>
              </div>
              <div v-else class="subject-list">
                <label
                  v-for="subj in wizFiltered.subjects"
                  :key="subj.id"
                  class="subject-item"
                  :class="wizForm.subject_id === subj.id ? 'selected' : ''"
                >
                  <input
                    type="radio"
                    :value="subj.id"
                    v-model="wizForm.subject_id"
                    name="wizard_subject_select"
                  />
                  <span class="subject-item-name">{{ subj.name }}</span>
                  <span v-if="subj.code" class="subject-item-code">({{ subj.code }})</span>
                </label>
                <div v-if="!wizFiltered.subjects.length" class="empty-mini">
                  No subjects available for this batch
                </div>
              </div>
            </div>
          </div>

          <!-- Step 3: Slot Details -->
          <div v-if="wizardStep === 2">
            <!-- Teacher -->
            <div class="form-group">
              <label>Teacher <span class="required">*</span></label>
              <div v-if="wizLoading.teachers" class="text-center py-4">
                <div class="spinner" style="margin: 0 auto 0.5rem; width:24px; height:24px;"></div>
                <p class="text-muted">Loading teachers...</p>
              </div>
              <select
                v-else
                v-model="wizForm.teacher_id"
                class="form-control"
                required
              >
                <option value="" disabled>Select teacher</option>
                <option v-for="t in wizFiltered.teachers" :key="t.id" :value="t.id">
                  {{ t.name || (t.first_name && t.last_name ? t.first_name + ' ' + t.last_name : '') || t.full_name || t.user?.name || t.teacher_id || 'Unknown' }}
                </option>
              </select>
              <p v-if="!wizLoading.teachers && wizFiltered.teachers.length === 0" class="text-muted" style="font-size:0.8rem; margin-top:0.25rem;">
                No teachers found for this subject
              </p>
            </div>

            <!-- Day of Week -->
            <div class="form-group">
              <label>Day <span class="required">*</span></label>
              <select v-model="wizForm.day_of_week" class="form-control" required>
                <option value="" disabled>Select day</option>
                <option value="sat">Saturday</option>
                <option value="sun">Sunday</option>
                <option value="mon">Monday</option>
                <option value="tue">Tuesday</option>
                <option value="wed">Wednesday</option>
                <option value="thu">Thursday</option>
                <option value="fri">Friday</option>
              </select>
            </div>

            <!-- Time Range -->
            <div class="form-row">
              <div class="form-group">
                <label>Start Time <span class="required">*</span></label>
                <input v-model="wizForm.start_time" type="time" class="form-control" required />
              </div>
              <div class="form-group">
                <label>End Time <span class="required">*</span></label>
                <input v-model="wizForm.end_time" type="time" class="form-control" required />
              </div>
            </div>

            <!-- Room -->
            <div class="form-group">
              <label>Room</label>
              <select v-model="wizForm.room_id" class="form-control">
                <option value="">No room</option>
                <option v-for="r in wizData.rooms" :key="r.id" :value="r.id">
                  {{ r.name || r.room_number }}
                </option>
              </select>
            </div>

            <!-- Status -->
            <div class="form-group">
              <label>Status</label>
              <select v-model="wizForm.status" class="form-control">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
              </select>
            </div>
          </div>

          <!-- Step 3: Preview & Confirm -->
          <div v-if="wizardStep === 3">
            <div class="preview-card">
              <div class="preview-section">
                <div class="preview-label">Class</div>
                <div class="preview-value">{{ wizDisplay.className }}</div>
              </div>
              <div class="preview-section">
                <div class="preview-label">Course</div>
                <div class="preview-value">{{ wizDisplay.courseName }}</div>
              </div>
              <div class="preview-section">
                <div class="preview-label">Batch</div>
                <div class="preview-value">{{ wizDisplay.batchName }}</div>
              </div>
              <div class="preview-section">
                <div class="preview-label">Subject</div>
                <div class="preview-value">{{ wizDisplay.subjectName }}</div>
              </div>
              <div class="preview-section">
                <div class="preview-label">Teacher</div>
                <div class="preview-value">{{ wizDisplay.teacherName }}</div>
              </div>
              <div class="preview-section">
                <div class="preview-label">Day</div>
                <div class="preview-value">{{ wizDayLabels[wizForm.day_of_week] || wizForm.day_of_week }}</div>
              </div>
              <div class="preview-section">
                <div class="preview-label">Time</div>
                <div class="preview-value">{{ wizForm.start_time }} - {{ wizForm.end_time }}</div>
              </div>
              <div class="preview-section" v-if="wizForm.room_id">
                <div class="preview-label">Room</div>
                <div class="preview-value">{{ wizDisplay.roomName }}</div>
              </div>
              <div class="preview-section">
                <div class="preview-label">Status</div>
                <div class="preview-value">{{ wizCapitalize(wizForm.status) }}</div>
              </div>
            </div>

            <div v-if="wizError" class="alert alert-danger" style="margin-top: 1rem;">
              {{ wizError }}
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer">
          <button
            v-if="wizardStep > 0"
            type="button"
            class="btn btn-outline"
            @click="wizardStep--"
          >
            Back
          </button>
          <div v-else></div>
          <div style="display:flex; gap: 0.5rem;">
            <button
              type="button"
              class="btn btn-outline"
              @click="closeCreateWizard"
            >
              Cancel
            </button>
            <button
              v-if="wizardStep < 3"
              type="button"
              class="btn btn-primary"
              :disabled="!wizCanProceed"
              @click="wizardNextStep"
            >
              Next
            </button>
            <button
              v-if="wizardStep === 3"
              type="button"
              class="btn btn-primary"
              :disabled="wizSaving"
              @click="wizardSave"
            >
              {{ wizSaving ? 'Saving...' : 'Save Routine' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue'
import academicService from '@/services/academic.service'
import enrollmentService from '@/services/enrollment.service'
import teacherService from '@/services/teacher.service'
import classRoutineService from '@/services/class-routine.service'

const props = defineProps({
  gridData: { type: Object, default: null },
  selectedBatches: { type: Array, default: () => [] },
  swapMode: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  printMode: { type: Boolean, default: false },
  coachingName: { type: String, default: '' },
  metaInfo: { type: Object, default: () => ({}) },
  batchStudentCounts: { type: Object, default: () => ({}) },
  // New props for consolidated controls
  batches: { type: Array, default: () => [] },
  selectedBatchIds: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  hasSelection: { type: Boolean, default: false },
  hasRoutines: { type: Boolean, default: false },
  multiBatchEnabled: { type: Boolean, default: false },
  store: { type: Object, default: null },
})

const emit = defineEmits([
  'edit-slot', 'refresh', 'swap-slots', 'routine-created',
  'toggle-batch', 'publish-all', 'archive-all', 'check-conflicts',
  'set-lunch-break', 'set-off-day', 'toggle-swap', 'export-pdf',
  'filter-change', 'reset-filters',
])

// ===== UI State =====
const showLegend = ref(false)
const showMoreMenu = ref(false)
const showBatchDropdown = ref(false)
const showCreateWizard = ref(false)
const batchSelectorRef = ref(null)
const moreActionsRef = ref(null)

// Close dropdowns on outside click
function onDocumentClick(e) {
  if (batchSelectorRef.value && !batchSelectorRef.value.contains(e.target)) {
    showBatchDropdown.value = false
  }
  if (moreActionsRef.value && !moreActionsRef.value.contains(e.target)) {
    showMoreMenu.value = false
  }
}
onMounted(() => document.addEventListener('click', onDocumentClick))
onUnmounted(() => document.removeEventListener('click', onDocumentClick))

// ===== Live tick: force re-evaluation of isSlotLive() every 30s =====
const liveTick = ref(0)
let liveTimer = null
onMounted(() => {
  liveTimer = setInterval(() => { liveTick.value++ }, 30000)
})
onUnmounted(() => {
  if (liveTimer) clearInterval(liveTimer)
})

function toggleBatch(batchId) {
  emit('toggle-batch', batchId)
}
const wizardStep = ref(0)
const wizardSteps = ['Class → Batch', 'Subject', 'Details', 'Confirm']
const wizSaving = ref(false)
const wizError = ref('')

const wizForm = reactive({
  class_id: '',
  course_id: '',
  batch_id: '',
  subject_id: '',
  teacher_id: '',
  day_of_week: '',
  start_time: '',
  end_time: '',
  room_id: '',
  status: 'draft',
})

const wizLoading = reactive({
  classes: false,
  courses: false,
  batches: false,
  subjects: false,
  teachers: false,
  rooms: false,
})

const wizData = reactive({
  classes: [],
  rooms: [],
})

const wizFiltered = reactive({
  courses: [],
  batches: [],
  subjects: [],
  teachers: [],
})

// Display names for preview
const wizDisplay = computed(() => {
  const cls = wizData.classes.find(c => c.id === wizForm.class_id)
  const co = wizFiltered.courses.find(c => c.id === wizForm.course_id)
  const ba = wizFiltered.batches.find(b => b.id === wizForm.batch_id)
  const su = wizFiltered.subjects.find(s => s.id === wizForm.subject_id)
  const te = wizFiltered.teachers.find(t => t.id === wizForm.teacher_id)
  const ro = wizData.rooms.find(r => r.id === wizForm.room_id)
  return {
    className: cls ? (cls.name || cls.title) : '',
    courseName: co ? (co.name || co.title) : '',
    batchName: ba ? (ba.name || ba.title) : '',
    subjectName: su ? su.name : '',
    teacherName: te ? (te.name || (te.first_name && te.last_name ? te.first_name + ' ' + te.last_name : '') || te.full_name || te.user?.name || te.teacher_id || '') : '',
    roomName: ro ? (ro.name || ro.room_number) : '',
  }
})

const wizDayLabels = {
  sat: 'Saturday', sun: 'Sunday', mon: 'Monday', tue: 'Tuesday',
  wed: 'Wednesday', thu: 'Thursday', fri: 'Friday',
}

const wizCanProceed = computed(() => {
  if (wizardStep.value === 0) return !!wizForm.batch_id
  if (wizardStep.value === 1) return !!wizForm.subject_id
  if (wizardStep.value === 2) {
    return wizForm.teacher_id && wizForm.day_of_week && wizForm.start_time && wizForm.end_time
  }
  return false
})

function wizCapitalize(str) {
  if (!str) return ''
  return str.charAt(0).toUpperCase() + str.slice(1)
}

// ===== Wizard: Load initial data =====
async function loadWizardData() {
  wizLoading.classes = true
  wizLoading.rooms = true
  try {
    const [clsRes, roomRes] = await Promise.all([
      academicService.classes.list(),
      academicService.rooms.listAll(),
    ])
    wizData.classes = clsRes?.data?.data || clsRes?.data || []
    const roomBody = roomRes?.data?.data || roomRes?.data || []
    wizData.rooms = Array.isArray(roomBody) ? roomBody : []
  } catch (e) {
    console.warn('Failed to load wizard data', e)
  } finally {
    wizLoading.classes = false
    wizLoading.rooms = false
  }
}

function resetWizardForm() {
  wizardStep.value = 0
  wizError.value = ''
  wizSaving.value = false
  wizForm.class_id = ''
  wizForm.course_id = ''
  wizForm.batch_id = ''
  wizForm.subject_id = ''
  wizForm.teacher_id = ''
  wizForm.day_of_week = ''
  wizForm.start_time = ''
  wizForm.end_time = ''
  wizForm.room_id = ''
  wizForm.status = 'draft'
  wizFiltered.courses = []
  wizFiltered.batches = []
  wizFiltered.subjects = []
  wizFiltered.teachers = []
}

function closeCreateWizard() {
  showCreateWizard.value = false
  resetWizardForm()
}

function wizardNextStep() {
  if (wizCanProceed.value) {
    wizardStep.value++
  }
}

async function wizardSave() {
  wizSaving.value = true
  wizError.value = ''
  try {
    const payload = {
      batch_id: wizForm.batch_id,
      subject_id: wizForm.subject_id,
      teacher_id: wizForm.teacher_id,
      day_of_week: wizForm.day_of_week,
      start_time: wizForm.start_time,
      end_time: wizForm.end_time,
      room_id: wizForm.room_id || null,
      status: wizForm.status,
    }
    await classRoutineService.createRoutine(payload)
    closeCreateWizard()
    emit('routine-created')
  } catch (e) {
    wizError.value = e.response?.data?.message || e.message || 'Failed to create routine'
  } finally {
    wizSaving.value = false
  }
}

// ===== Cascading Watchers =====

// When class_id changes → fetch courses
watch(() => wizForm.class_id, async (newVal) => {
  wizForm.course_id = ''
  wizForm.batch_id = ''
  wizForm.subject_id = ''
  wizForm.teacher_id = ''
  wizFiltered.courses = []
  wizFiltered.batches = []
  wizFiltered.subjects = []
  wizFiltered.teachers = []
  if (!newVal) return
  wizLoading.courses = true
  try {
    const res = await enrollmentService.getCourses({ class_id: newVal })
    wizFiltered.courses = res?.data?.data || res?.data || []
  } catch (e) {
    console.warn('Failed to fetch courses for class:', newVal, e)
    wizFiltered.courses = []
  } finally {
    wizLoading.courses = false
  }
})

// When course_id changes → fetch batches
watch(() => wizForm.course_id, async (newVal) => {
  wizForm.batch_id = ''
  wizForm.subject_id = ''
  wizForm.teacher_id = ''
  wizFiltered.batches = []
  wizFiltered.subjects = []
  wizFiltered.teachers = []
  if (!newVal) return
  wizLoading.batches = true
  try {
    const res = await enrollmentService.getBatchesByCourse(newVal)
    wizFiltered.batches = res?.data?.data || res?.data || []
  } catch (e) {
    console.warn('Failed to fetch batches for course:', newVal, e)
    wizFiltered.batches = []
  } finally {
    wizLoading.batches = false
  }
})

// When batch_id changes → fetch subjects via batch's course
watch(() => wizForm.batch_id, async (newVal) => {
  wizForm.subject_id = ''
  wizForm.teacher_id = ''
  wizFiltered.subjects = []
  wizFiltered.teachers = []
  if (!newVal) return
  wizLoading.subjects = true
  try {
    const res = await enrollmentService.getBatch(newVal)
    const batch = res?.data?.data || res?.data
    if (batch?.course_id) {
      const courseRes = await enrollmentService.getCourse(batch.course_id)
      const course = courseRes?.data?.data || courseRes?.data
      wizFiltered.subjects = course?.subjects || []
    } else {
      wizFiltered.subjects = []
    }
  } catch (e) {
    console.warn('Failed to fetch subjects for batch:', newVal, e)
    wizFiltered.subjects = []
  } finally {
    wizLoading.subjects = false
  }
})

// When subject_id changes → fetch teachers
watch(() => wizForm.subject_id, async (newVal) => {
  wizForm.teacher_id = ''
  wizFiltered.teachers = []
  if (!newVal || !wizForm.batch_id) return
  wizLoading.teachers = true
  try {
    const res = await teacherService.bySubject(newVal)
    const data = res?.data?.data || res?.data || []
    wizFiltered.teachers = Array.isArray(data) ? data : []
  } catch (e) {
    console.warn('Failed to fetch teachers for subject:', newVal, e)
    wizFiltered.teachers = []
  } finally {
    wizLoading.teachers = false
  }
})

// Reset wizard when opened
watch(() => showCreateWizard.value, (val) => {
  if (val) {
    resetWizardForm()
    loadWizardData()
  }
})

// ===== Existing Grid Logic =====

const scrollContainer = ref(null)

// ===== Day Configuration =====
const days = computed(() => {
  const dayConfig = [
    { key: 'sat', label: 'Sat', labelFull: 'Saturday', date: '', isToday: false, isWeekend: false },
    { key: 'sun', label: 'Sun', labelFull: 'Sunday', date: '', isToday: false, isWeekend: false },
    { key: 'mon', label: 'Mon', labelFull: 'Monday', date: '', isToday: false, isWeekend: false },
    { key: 'tue', label: 'Tue', labelFull: 'Tuesday', date: '', isToday: false, isWeekend: false },
    { key: 'wed', label: 'Wed', labelFull: 'Wednesday', date: '', isToday: false, isWeekend: false },
    { key: 'thu', label: 'Thu', labelFull: 'Thursday', date: '', isToday: false, isWeekend: false },
    { key: 'fri', label: 'Fri', labelFull: 'Friday', date: '', isToday: false, isWeekend: true },
  ]

  const now = new Date()
  const currentDay = now.getDay()
  const satOffset = (currentDay + 1) % 7
  const saturday = new Date(now)
  saturday.setDate(now.getDate() - satOffset)

  const todayStr = now.toDateString()

  dayConfig.forEach((day, index) => {
    const d = new Date(saturday)
    d.setDate(saturday.getDate() + index)
    day.date = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
    day.isToday = d.toDateString() === todayStr
    day.isWeekend = day.key === 'fri'
  })

  return dayConfig
})

// Friday is rendered as a single full-width OFF bar (like the exam routine),
// so it is excluded from the regular day columns.
const columnDays = computed(() => days.value.filter(d => d.key !== 'fri'))
const fridayDay = computed(() => days.value.find(d => d.key === 'fri'))

// ===== Effective Date =====
const effectiveDate = computed(() => {
  if (days.value.length < 2) return ''
  return `${days.value[0].date} - ${days.value[6].date}`
})

const generatedDate = computed(() => {
  return new Date().toLocaleDateString('en-US', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
  })
})

// ===== Subject Legend =====
const subjectLegend = computed(() => {
  const legend = {}
  if (!props.gridData?.time_slots) return legend
  props.gridData.time_slots.forEach(slot => {
    Object.values(slot.cells || {}).forEach(cell => {
      ;(cell.routines || []).forEach(r => {
        const subjName = r.subject_name || r.subject?.name
        if (subjName && !legend[subjName]) {
          legend[subjName] = r.subject_color || getBatchColor(r.subject_id || subjName)
        }
      })
    })
  })
  return legend
})

// ===== Dynamic Legend Stats =====
const legendStats = computed(() => {
  const teachers = new Set()
  const rooms = new Set()
  const subjects = new Set()
  const batches = new Set()
  let lunchCount = 0
  let offDayCount = 0

  if (!props.gridData?.time_slots) {
    return { teacherCount: 0, roomCount: 0, subjectCount: 0, batchCount: 0, lunchCount: 0, offDayCount: 0 }
  }

  props.gridData.time_slots.forEach(slot => {
    if (slot.is_lunch_break) lunchCount++
    if (slot.is_off_day) offDayCount++
    Object.values(slot.cells || {}).forEach(cell => {
      ;(cell.routines || []).forEach(r => {
        const tName = r.teacher_name || r.teacher?.name || r.teacher?.full_name || (r.teacher?.first_name && r.teacher?.last_name ? r.teacher.first_name + ' ' + r.teacher.last_name : '') || r.teacher?.user?.name
        if (tName) teachers.add(tName)
        const rName = r.room_name || r.room?.name || r.room?.room_number
        if (rName) rooms.add(rName)
        const sName = r.subject_name || r.subject?.name
        if (sName) subjects.add(sName)
        const bName = r.batch_name || r.batch?.name
        if (bName) batches.add(bName)
      })
    })
  })

  return {
    teacherCount: teachers.size,
    roomCount: rooms.size,
    subjectCount: subjects.size,
    batchCount: batches.size,
    lunchCount,
    offDayCount,
  }
})

// ===== Time Slots from Grid Data =====
const regularSlots = computed(() => {
  return props.gridData?.time_slots?.filter(s => !s.is_lunch_break && !s.is_off_day) || []
})

const lunchSlots = computed(() => {
  return props.gridData?.time_slots?.filter(s => s.is_lunch_break) || []
})

const offDaySlots = computed(() => {
  return props.gridData?.time_slots?.filter(s => s.is_off_day) || []
})

// All slots in display order: regular, lunch, off-day
const allSlots = computed(() => {
  const slots = [...regularSlots.value]
  // Insert lunch breaks after their position
  lunchSlots.value.forEach(ls => {
    const insertAfter = slots.findIndex(s => s.display_order > ls.display_order)
    if (insertAfter >= 0) {
      slots.splice(insertAfter, 0, ls)
    } else {
      slots.push(ls)
    }
  })
  // Append off-day slots at the end
  offDaySlots.value.forEach(os => slots.push(os))
  return slots
})

// ===== Get routines for a specific cell =====
function getCellRoutines(slot, dayKey) {
  return slot.cells?.[dayKey]?.routines || []
}

// ===== Check if a slot is currently live =====
function isSlotLive(slot) {
  if (!slot.start_time || !slot.end_time) return false
  // Get current time in Bangladesh (Asia/Dhaka, UTC+6)
  const now = new Date()
  const bdNow = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Dhaka' }))
  const currentMinutes = bdNow.getHours() * 60 + bdNow.getMinutes()
  // Parse slot times to minutes for numeric comparison
  // Handles both "HH:MM:SS" and "H:MM:SS" formats from backend
  const parseToMinutes = (t) => {
    const parts = t.split(':')
    return parseInt(parts[0]) * 60 + parseInt(parts[1])
  }
  const startMinutes = parseToMinutes(slot.start_time)
  const endMinutes = parseToMinutes(slot.end_time)
  return currentMinutes >= startMinutes && currentMinutes <= endMinutes
}

// ===== Get card styling based on subject color =====
function getCardStyle(routine) {
  const color = routine.subject_color || routine._color || '#6366F1'
  return {
    '--card-color': color,
    '--card-color-light': color + '15',
    '--card-color-border': color + '30',
  }
}

// ===== Get batch color =====
function getBatchColor(batchId) {
  if (!batchId) return '#6366F1'
  const colors = ['#6366F1', '#EC4899', '#14B8A6', '#F97316', '#84CC16', '#8B5CF6', '#06B6D4', '#F43F5E']
  const index = batchId.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0) % colors.length
  return colors[index]
}

// ===== Get batch student count =====
function getBatchStudentCount(batchId) {
  if (!batchId) return '—'
  // Try direct key match first
  let count = props.batchStudentCounts?.[batchId]
  if (count !== undefined && count !== null) return count
  // Try string conversion (in case of type mismatch between string/number IDs)
  count = props.batchStudentCounts?.[String(batchId)]
  if (count !== undefined && count !== null) return count
  // Try from grid data _stats
  count = props.gridData?._stats?.batch_students?.[batchId]
  if (count !== undefined && count !== null) return count
  count = props.gridData?._stats?.batch_students?.[String(batchId)]
  if (count !== undefined && count !== null) return count
  return '—'
}

// ===== Get batch slot count =====
function getBatchSlotCount(batchId) {
  if (!props.gridData?.time_slots) return 0
  let count = 0
  props.gridData.time_slots.forEach(slot => {
    Object.values(slot.cells || {}).forEach(cell => {
      ;(cell.routines || []).forEach(r => {
        if ((r.batch_id || r.batch?.id) === batchId) count++
      })
    })
  })
  return count
}

// ===== Format time with duration =====
function formatTime(start, end) {
  if (!start || !end) return ''
  const fmt = (t) => {
    const [h, m] = t.split(':')
    const hour = parseInt(h)
    const ampm = hour >= 12 ? 'PM' : 'AM'
    return `${hour % 12 || 12}:${m} ${ampm}`
  }
  // Calculate duration
  const [sh, sm] = start.split(':').map(Number)
  const [eh, em] = end.split(':').map(Number)
  let totalMinutes = (eh * 60 + em) - (sh * 60 + sm)
  if (totalMinutes < 0) totalMinutes += 1440 // handle overnight slots
  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60
  let durationStr = ''
  if (hours > 0) durationStr += `${hours}h `
  if (minutes > 0) durationStr += `${minutes}m`
  if (!durationStr) durationStr = '0m'
  return `${fmt(start)} - ${fmt(end)} (${durationStr.trim()})`
}

// ===== Drag & Drop =====
let dragSource = null

function onDragStart(event, routine) {
  dragSource = routine
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', routine.id)
}

function onDragOver(event, routine) {
  if (dragSource && dragSource.id !== routine.id) {
    event.dataTransfer.dropEffect = 'move'
  }
}

function onDrop(event, routine) {
  if (dragSource && dragSource.id !== routine.id) {
    emit('swap-slots', dragSource.id, routine.id)
  }
  dragSource = null
}

</script>

<style scoped>
/* ===== ADD ROUTINE BUTTON ===== */
.add-routine-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 1rem;
  background: linear-gradient(135deg, #6366F1, #8B5CF6);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}
.add-routine-btn:hover {
  background: linear-gradient(135deg, #4F46E5, #7C3AED);
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
  transform: translateY(-1px);
}
.add-routine-btn:active {
  transform: translateY(0);
}

/* ===== WIZARD MODAL ===== */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 1rem;
}
.modal-dialog {
  background: var(--bg-card);
  border-radius: 12px;
  width: 100%;
  max-width: 520px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}
.modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text-primary);
}
.modal-close {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: var(--text-muted);
  cursor: pointer;
  padding: 0.25rem;
  line-height: 1;
  border-radius: 4px;
  transition: all 0.2s;
}
.modal-close:hover {
  background: #f3f4f6;
  color: var(--text-secondary);
}
.modal-body {
  padding: 1.25rem 1.5rem;
}
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border-color);
  gap: 0.5rem;
}

/* ===== WIZARD STEPS ===== */
.wizard-steps {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem 1.5rem 0;
}
.wizard-step {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.wizard-step-circle {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 600;
  transition: all 0.2s;
  flex-shrink: 0;
}
.wizard-step-circle.completed {
  background: #6366F1;
  color: #fff;
}
.wizard-step-circle.active {
  background: #eef2ff;
  color: #6366F1;
  box-shadow: 0 0 0 2px #a5b4fc;
}
.wizard-step-circle.pending {
  background: #f3f4f6;
  color: var(--text-muted);
}
.wizard-step-check {
  width: 16px;
  height: 16px;
}
.wizard-step-label {
  font-size: 0.75rem;
  font-weight: 500;
  white-space: nowrap;
}
.wizard-step-label.active {
  color: var(--text-primary);
}
.wizard-step-label:not(.active) {
  color: var(--text-muted);
}
.wizard-step-arrow {
  width: 16px;
  height: 16px;
  color: #d1d5db;
  margin: 0 0.25rem;
  flex-shrink: 0;
}

/* ===== FORM CONTROLS ===== */
.form-group {
  margin-bottom: 1rem;
}
.form-group label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 0.35rem;
}
.form-group .required {
  color: #ef4444;
}
.form-control {
  width: 100%;
  padding: 0.55rem 0.75rem;
  border: 1.5px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.875rem;
  color: var(--text-primary);
  background: var(--bg-card);
  transition: border-color 0.2s, box-shadow 0.2s;
  outline: none;
}
.form-control:focus {
  border-color: #6366F1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}
.form-row {
  display: flex;
  gap: 0.75rem;
}
.form-row .form-group {
  flex: 1;
}

/* ===== SUBJECT LIST ===== */
.subject-list {
  max-height: 16rem;
  overflow-y: auto;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 0.75rem;
}
.subject-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.2s;
}
.subject-item:hover {
  background: var(--bg-accent);
}
.subject-item.selected {
  background: #eef2ff;
}
.subject-item input[type="radio"] {
  width: 1rem;
  height: 1rem;
  accent-color: #6366F1;
}
.subject-item-name {
  font-size: 0.875rem;
  color: var(--text-primary);
}
.subject-item-code {
  font-size: 0.75rem;
  color: var(--text-muted);
}
.empty-mini {
  text-align: center;
  padding: 1.5rem 0;
  font-size: 0.875rem;
  color: var(--text-muted);
}

/* ===== PREVIEW CARD ===== */
.preview-card {
  background: var(--bg-accent);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 1rem;
}
.preview-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-color);
}
.preview-section:last-child {
  border-bottom: none;
}
.preview-label {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.preview-value {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary);
  text-align: right;
}

/* ===== BUTTONS ===== */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.55rem 1.25rem;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  line-height: 1.4;
}
.btn-primary {
  background: #6366F1;
  color: #fff;
}
.btn-primary:hover:not(:disabled) {
  background: #4F46E5;
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
}
.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.btn-outline {
  background: var(--bg-card);
  color: var(--text-secondary);
  border: 1.5px solid #d1d5db;
}
.btn-outline:hover {
  background: var(--bg-accent);
  border-color: var(--text-muted);
}

/* ===== ALERT ===== */
.alert {
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-size: 0.85rem;
}
.alert-danger {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

/* ===== UTILITY ===== */
.text-center { text-align: center; }
.text-muted { color: var(--text-muted); }
.py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
.py-4 { padding-top: 1rem; padding-bottom: 1rem; }

/* ===== BASE ===== */
.enterprise-routine-grid {
  position: relative;
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
  overflow: hidden;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  box-sizing: border-box;
}

.enterprise-routine-grid *,
.enterprise-routine-grid *::before,
.enterprise-routine-grid *::after {
  box-sizing: border-box;
}

/* ===== COMPACT HEADER ===== */
.grid-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  background: var(--bg-card);
  border-bottom: 1px solid var(--border-color);
  gap: 12px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.header-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.brand-icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
}

.brand-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.brand-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-dark);
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.brand-meta {
  font-size: 11px;
  color: var(--text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.header-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--bg-card);
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.header-btn:hover {
  background: var(--bg-surface-muted);
  border-color: #cbd5e1;
}

.header-btn.legend-toggle {
  color: var(--text-muted);
}

.header-btn.legend-toggle:hover {
  background: var(--bg-accent);
  color: var(--text-secondary);
}

.header-btn.add-btn {
  background: #6366F1;
  color: #fff;
  border-color: #6366F1;
}

.header-btn.add-btn:hover {
  background: #4f46e5;
  border-color: #4f46e5;
}

/* ===== BATCH SELECTOR DROPDOWN ===== */
.batch-selector {
  position: relative;
}

.batch-select-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 10px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--bg-card);
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.batch-select-btn:hover {
  background: var(--bg-surface-muted);
  border-color: #cbd5e1;
}

.batch-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  min-width: 200px;
  max-height: 260px;
  overflow-y: auto;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 10px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.1);
  z-index: 100;
  padding: 6px;
}

.batch-option {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 10px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
  transition: all 0.1s ease;
}

.batch-option:hover {
  background: var(--bg-accent);
}

.batch-option.selected {
  background: #eef2ff;
  color: #4338ca;
}

.batch-opt-check {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  border: 2px solid #cbd5e1;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.1s ease;
}

.batch-option.selected .batch-opt-check {
  border-color: #6366F1;
  background: #6366F1;
  color: #fff;
}

/* ===== MORE ACTIONS DROPDOWN ===== */
.more-actions {
  position: relative;
}

.more-btn {
  padding: 6px 8px;
}

.more-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  min-width: 190px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 10px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.1);
  z-index: 100;
  padding: 6px;
}

.more-dropdown-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 10px;
  border: none;
  background: transparent;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
  text-align: left;
  transition: all 0.1s ease;
  font-family: inherit;
}

.more-dropdown-item:hover:not(:disabled) {
  background: var(--bg-accent);
  color: var(--text-dark);
}

.more-dropdown-item:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.more-dropdown-item svg {
  flex-shrink: 0;
  color: var(--text-muted);
}

.more-dropdown-divider {
  height: 1px;
  background: #e2e8f0;
  margin: 4px 0;
}

/* ===== LEGEND BAR ===== */
.legend-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 16px 24px;
  padding: 10px 16px;
  background: var(--bg-surface-muted);
  border-bottom: 1px solid var(--border-color);
}

.legend-section {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.legend-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-muted);
  white-space: nowrap;
}

.legend-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--text-secondary);
  background: var(--bg-card);
  padding: 2px 8px;
  border-radius: 6px;
  border: 1px solid var(--border-color);
  white-space: nowrap;
}

.legend-chip.outlined {
  background: transparent;
  border-color: #e2e8f0;
  color: var(--text-muted);
}

.legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.legend-empty {
  font-size: 11px;
  color: var(--text-muted);
  font-style: italic;
}

/* ===== SCROLL CONTAINER ===== */
.grid-scroll-container {
  overflow-x: auto;
  overflow-y: hidden;
}

.grid-scroll-container::-webkit-scrollbar {
  width: 8px;
  height: 10px;
}

.grid-scroll-container::-webkit-scrollbar-track {
  background: #f1f1f1;
}

.grid-scroll-container::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 4px;
}

.grid-wrapper {
  min-width: 1100px;
  width: 100%;
}

/* ===== GRID HEADER ROW ===== */
.grid-header-row {
  display: grid;
  grid-template-columns: 120px repeat(6, minmax(0, 1fr));
  position: sticky;
  top: 0;
  z-index: 20;
  background: var(--bg-card);
  border-bottom: 2px solid var(--border-color);
}

.time-col-header {
  padding: 12px 8px;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  text-align: center;
  background: var(--bg-surface-muted);
  border-right: 1px solid var(--border-color);
  box-sizing: border-box;
}

.day-col-header {
  padding: 10px 6px;
  text-align: center;
  background: var(--bg-surface-muted);
  border-right: 1px solid var(--border-color);
  transition: background 0.15s;
  min-width: 0;
  box-sizing: border-box;
}

.day-col-header:last-child {
  border-right: none;
}

.day-col-header.is-today {
  background: color-mix(in srgb, #3b82f6 18%, var(--bg-card));
  box-shadow: inset 0 -2px 0 #3b82f6;
}

.day-col-header.is-off-day {
  background: color-mix(in srgb, #ef4444 14%, var(--bg-card));
}

.day-name-full {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
}

.day-name-short {
  display: none;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
}

.day-date {
  font-size: 10px;
  color: var(--text-muted);
  margin-top: 1px;
}

.day-col-header.is-today .day-name-full,
.day-col-header.is-today .day-name-short {
  color: color-mix(in srgb, #3b82f6 65%, var(--text-primary));
}

.day-col-header.is-off-day .day-name-full,
.day-col-header.is-off-day .day-name-short {
  color: color-mix(in srgb, #ef4444 65%, var(--text-primary));
}

/* ===== GRID BODY ===== */
.grid-body {
  position: relative;
}

/* ===== GRID ROW ===== */
.grid-row {
  display: grid;
  grid-template-columns: 120px repeat(6, minmax(0, 1fr));
  border-bottom: 1px solid var(--border-color);
  transition: background 0.15s;
  height: auto;
  width: auto;
}

.grid-row:hover {
  background: color-mix(in srgb, var(--text-primary) 6%, var(--bg-card));
}

.grid-row.is-live-row {
  background: color-mix(in srgb, #22c55e 10%, var(--bg-card));
}

/* ===== TIME CELL (Sticky) ===== */
.time-cell {
  position: sticky;
  left: 0;
  z-index: 10;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 8px 4px;
  background: var(--bg-card);
  border-right: 1px solid var(--border-color);
  gap: 2px;
  min-height: 0;
  height: auto;
  box-sizing: border-box;
}

.grid-row:hover .time-cell {
  background: color-mix(in srgb, var(--text-primary) 6%, var(--bg-card));
}

.time-cell.is-live-time {
  background: color-mix(in srgb, #22c55e 10%, var(--bg-card));
}

.time-slot-name {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.time-slot-range {
  font-size: 10px;
  font-weight: 500;
  color: var(--text-muted);
}

/* ===== DAY CELL ===== */
.day-cell {
  min-height: 0;
  height: auto;
  padding: 3px;
  border-right: 1px solid var(--border-color);
  transition: background 0.15s;
  min-width: 0;
  box-sizing: border-box;
}

.day-cell:last-child {
  border-right: none;
}

.day-cell.is-today {
  background: color-mix(in srgb, #3b82f6 7%, var(--bg-card));
}

.day-cell.is-off-day-col {
  background: color-mix(in srgb, #ef4444 8%, var(--bg-card));
}

.day-cell.is-empty-cell {
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-cell {
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-dash {
  color: #e2e8f0;
  font-size: 16px;
  font-weight: 300;
}

/* ===== ROUTINE STACK ===== */
.routine-stack {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

/* ===== ROUTINE CARD ===== */
.routine-card {
  position: relative;
  padding: 4px 6px;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.12s;
  background: var(--card-color-light, #f0f0ff);
  border-left: 3px solid var(--card-color, #6366F1);
  border: 1px solid var(--card-color-border, #e0e0ff);
  min-width: 0;
  width: 100%;
  height: auto;
  overflow: hidden;
}

.routine-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

.routine-card.is-live {
  border-color: #22c55e;
  background: color-mix(in srgb, #22c55e 14%, var(--bg-card));
  box-shadow: 0 0 0 1px #22c55e;
}

.routine-card.is-draft {
  opacity: 0.75;
  border-style: dashed;
}

.routine-card.is-swap-target {
  cursor: grab;
}

.routine-card.is-swap-target:active {
  cursor: grabbing;
}

/* Card Batch Row — Line 1 */
.card-batch-row {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-bottom: 2px;
}

.card-batch-name {
  font-size: 10px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 3px;
  white-space: nowrap;
  line-height: 1.25;
  max-width: 100%;
  min-width: 0;
}

.batch-dot-sm {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}

/* Card detail lines — each field on its own line, fully shown (wraps if long) */
.card-detail-line {
  display: flex;
  align-items: flex-start;
  gap: 3px;
  width: 100%;
  font-size: 9px;
  line-height: 1.3;
  margin-top: 1px;
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.card-detail-line svg {
  flex-shrink: 0;
  margin-top: 1px;
}

.card-detail-line > span {
  min-width: 0;
}

.card-detail-subject {
  font-weight: 600;
  color: var(--text-dark);
}

.card-detail-teacher {
  color: var(--text-muted);
}

.card-detail-sep {
  font-size: 8px;
  color: var(--text-muted);
  flex-shrink: 0;
  line-height: 1;
}

/* Card Live Badge */
.card-live-badge {
  position: absolute;
  top: 1px;
  right: 1px;
  font-size: 7px;
  font-weight: 700;
  color: #fff;
  background: #22c55e;
  padding: 1px 4px;
  border-radius: 3px;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

/* ===== LUNCH BREAK ROW ===== */
.lunch-break-row {
  background: #fffbeb;
}

.lunch-time-cell {
  background: #fffbeb !important;
}

.lunch-time-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  font-size: 10px;
  font-weight: 600;
  color: #92400e;
}

.lunch-clock-icon {
  font-size: 14px;
}

.lunch-day-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 0;
  height: auto;
  padding: 6px 3px;
  min-width: 0;
  box-sizing: border-box;
}

.lunch-content-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 0 8px;
}

.lunch-divider-line {
  flex: 1;
  height: 1px;
  background: linear-gradient(to right, transparent, #fcd34d, transparent);
}

.lunch-text-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  white-space: nowrap;
}

.lunch-icon-big {
  font-size: 18px;
}

.lunch-text-main {
  font-size: 11px;
  font-weight: 700;
  color: #92400e;
  letter-spacing: 0.15em;
}

.lunch-text-sub {
  font-size: 9px;
  color: #b45309;
}

/* ===== OFF DAY ROW ===== */
.off-day-row {
  background: color-mix(in srgb, #ef4444 8%, var(--bg-card));
}

.off-day-time-cell {
  background: color-mix(in srgb, #ef4444 12%, var(--bg-card)) !important;
}

.off-day-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 0;
  height: auto;
  padding: 6px 3px;
  min-width: 0;
  box-sizing: border-box;
}

.off-day-content-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
}

.off-day-icon {
  font-size: 20px;
}

.off-day-text-block {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1px;
}

.off-day-title {
  font-size: 12px;
  font-weight: 700;
  color: color-mix(in srgb, #ef4444 70%, var(--text-primary));
  letter-spacing: 0.1em;
}

.off-day-sub {
  font-size: 9px;
  color: color-mix(in srgb, #ef4444 45%, var(--text-primary));
}

/* Friday OFF bar (full width, mirrors the exam routine) */
.friday-off-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: color-mix(in srgb, #ef4444 10%, var(--bg-card));
  border-top: 1px solid color-mix(in srgb, #ef4444 30%, var(--bg-card));
}

.friday-off-label {
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #fff;
  background: #ef4444;
  padding: 0.1rem 0.5rem;
  border-radius: 4px;
}

.friday-off-date {
  font-size: 0.8rem;
  font-weight: 700;
  color: color-mix(in srgb, #ef4444 55%, var(--text-primary));
}

/* ===== FOOTER SECTION ===== */
.grid-footer-section {
  background: var(--bg-surface-muted);
  border-top: 2px solid #e2e8f0;
  padding: 16px 20px;
}

.footer-columns {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: 24px;
  margin-bottom: 12px;
}

.footer-title {
  font-size: 12px;
  font-weight: 700;
  color: var(--text-secondary);
  margin: 0 0 8px;
  padding-bottom: 6px;
  border-bottom: 1px solid var(--border-color);
}

/* Instructions */
.instructions-list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.instructions-list li {
  font-size: 10px;
  color: var(--text-muted);
  padding: 2px 0;
  padding-left: 12px;
  position: relative;
  line-height: 1.4;
}

.instructions-list li::before {
  content: '•';
  position: absolute;
  left: 0;
  color: #6366F1;
  font-weight: bold;
}

/* Legend Grid */
.legend-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4px 12px;
}

.legend-row {
  display: flex;
  align-items: center;
  gap: 4px;
}

.legend-icon {
  font-size: 12px;
  width: 18px;
  text-align: center;
}

.legend-label {
  font-size: 10px;
  color: var(--text-muted);
}

/* Signature */
.signature-block {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.signature-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sig-line {
  width: 140px;
  height: 1px;
  border-top: 1px solid #94a3b8;
}

.sig-label {
  font-size: 10px;
  color: var(--text-muted);
  font-style: italic;
}

/* Footer Bottom */
.footer-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 10px;
  border-top: 1px solid #e2e8f0;
}

.footer-copyright {
  font-size: 9px;
  color: var(--text-muted);
}

.footer-generated {
  font-size: 9px;
  color: var(--text-muted);
}

/* ===== EMPTY STATE ===== */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  color: var(--text-muted);
}

.empty-state h3 {
  margin: 16px 0 8px;
  font-size: 18px;
  color: var(--text-muted);
}

.empty-state p {
  font-size: 14px;
  max-width: 400px;
}

/* ===== LOADING OVERLAY ===== */
.loading-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  background: rgba(255,255,255,0.85);
  z-index: 50;
  font-size: 14px;
  color: var(--text-muted);
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #6366F1;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ===== PRINT MODE ===== */
.is-print-mode .grid-scroll-container {
  max-height: none;
  overflow: visible;
}

.is-print-mode .grid-header-row {
  position: static;
}

.is-print-mode .time-cell {
  position: static;
}

.is-print-mode .grid-footer-section {
  break-inside: avoid;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
  .grid-header-row {
    grid-template-columns: 90px repeat(6, 1fr);
  }
  .grid-row {
    grid-template-columns: 90px repeat(6, 1fr);
  }
  .time-cell {
    width: 90px;
  }
  .footer-columns {
    grid-template-columns: 1fr 1fr;
  }
  .signature-col {
    grid-column: span 2;
  }
}

@media (max-width: 768px) {
  .enterprise-routine-grid {
    border-radius: 0;
  }
  .grid-header {
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
  }
  .header-left {
    width: 100%;
  }
  .header-right {
    width: 100%;
    justify-content: flex-end;
  }
  .brand-meta {
    font-size: 10px;
  }
  .header-btn {
    font-size: 11px;
    padding: 5px 10px;
  }
  .legend-bar {
    flex-direction: column;
    gap: 8px;
  }
  .day-name-full {
    display: none;
  }
  .day-name-short {
    display: block;
  }
  .footer-columns {
    grid-template-columns: 1fr;
  }
  .signature-col {
    grid-column: span 1;
  }
  .footer-bottom {
    flex-direction: column;
    gap: 4px;
    text-align: center;
  }
}
</style>

<template>
  <div class="erp-exam-routine">
    <!-- ===== TOP ACTION BAR (hidden when printing) ===== -->
    <div class="top-action-bar no-print">
      <!-- Row 1: Exam selector + management buttons -->
      <div class="action-row">
        <div class="action-left">
          <select v-model="selectedExamId" class="exam-select" @change="onExamSelectChange">
            <option value="" disabled>-- Select Exam --</option>
            <option v-for="exam in store.exams" :key="exam.id" :value="exam.id">
              {{ exam.name }}
            </option>
          </select>
          <button class="btn btn-sm btn-outline" @click="refreshExams" title="Refresh exams">
            <svg class="ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/></svg>
          </button>
          <!-- Filter Button -->
          <button class="btn btn-sm btn-filter" @click="showFilterPanel = !showFilterPanel" title="Toggle filters">
            <svg class="ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/></svg>
            Filter
          </button>
        </div>
        <div class="action-right">
          <button class="btn btn-primary btn-sm" @click="openAddRoutine">
            <svg class="ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
            Add
          </button>
          <button class="btn btn-secondary btn-sm" @click="openBulkStore">
            <svg class="ico" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"/></svg>
            Bulk
          </button>
          <button class="btn btn-secondary btn-sm" @click="openGenerate" :disabled="!gridData.exam?.id">
            <svg class="ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
            Generate
          </button>
          <div class="btn-separator"></div>
          <button class="btn btn-success btn-sm" @click="publishRoutines" :disabled="!gridData.exam?.id">
            <svg class="ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            Publish
          </button>
          <button class="btn btn-warning btn-sm" @click="checkConflicts" :disabled="!gridData.exam?.id">
            <svg class="ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            Conflicts
          </button>
          <button class="btn btn-danger btn-sm" @click="cancelRoutines" :disabled="!gridData.exam?.id">
            <svg class="ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            Cancel
          </button>
          <div class="btn-separator"></div>
          <!-- View Toggle -->
          <button class="btn btn-sm" :class="viewMode === 'grid' ? 'btn-primary' : 'btn-outline'" @click="viewMode = 'grid'" title="Table grid view">
            <svg class="ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/></svg>
            Grid
          </button>
          <button class="btn btn-sm" :class="viewMode === 'cards' ? 'btn-primary' : 'btn-outline'" @click="viewMode = 'cards'" title="Card view">
            <svg class="ico" viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
            Cards
          </button>
          <div class="btn-separator"></div>
          <!-- Export Dropdown -->
          <div class="export-dropdown">
            <button class="btn btn-outline btn-sm" @click="showExportMenu = !showExportMenu" title="Export options">
              <svg class="ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
              Export
            </button>
            <div v-if="showExportMenu" class="export-menu">
              <button class="export-option" @click="exportPDF">
                <svg class="ico-sm" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
                Download PDF
              </button>
              <button class="export-option" @click="downloadImage">
                <svg class="ico-sm" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
                Download Image
              </button>
              <button class="export-option" @click="windowPrint">
                <svg class="ico-sm" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd"/></svg>
                Print
              </button>
            </div>
          </div>
        </div>
      </div>
      <!-- Filter Panel -->
      <div v-if="showFilterPanel" class="filter-panel">
        <div class="filter-group">
          <label>Class</label>
          <select v-model="filterClass" class="filter-select" @change="applyFilters">
            <option value="">All Classes</option>
            <option v-for="c in gridData.filter_classes" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Course</label>
          <select v-model="filterCourse" class="filter-select" @change="applyFilters">
            <option value="">All Courses</option>
            <option v-for="c in gridData.filter_courses" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Batch</label>
          <select v-model="filterBatch" class="filter-select" @change="applyFilters">
            <option value="">All Batches</option>
            <option v-for="b in gridData.batches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Exam Type</label>
          <select v-model="filterExamType" class="filter-select" @change="applyFilters">
            <option value="">All Types</option>
            <option v-for="t in gridData.filter_exam_types" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Status</label>
          <select v-model="filterStatus" class="filter-select" @change="applyFilters">
            <option value="">All Status</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
            <option value="completed">Completed</option>
          </select>
        </div>
        <button class="btn btn-sm btn-outline" @click="clearFilters">Clear</button>
      </div>
    </div>

    <!-- ===== EXAM HEADER (visible in print) ===== -->
    <div class="exam-header" v-if="gridData.exam">
      <div class="header-brand">
        <div class="header-brand-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <div class="header-body">
          <h1 class="header-title">{{ gridData.exam.name }}</h1>
          <div class="header-meta">
            <span class="meta-item">
              <svg class="meta-ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
              {{ gridData.exam.first_date || gridData.exam.start_date }} — {{ gridData.exam.last_date || gridData.exam.end_date }}
            </span>
            <span class="meta-sep">•</span>
            <span class="meta-item">{{ formatExamTypeLabel(gridData.exam) }}</span>
            <span v-if="gridData.exam.result_status === 'published'" class="meta-item meta-results-out">This exam cycle: results published</span>
            <template v-if="channelPolicyMeta.scope === 'batch'">
              <span class="meta-sep">•</span>
              <span class="meta-item">Batch-wise rules</span>
            </template>
            <template v-if="channelEligibilityEnabled">
              <span class="meta-sep">•</span>
              <span class="meta-item meta-eligibility-on">{{ gridChannel === 'online' ? 'Online' : 'Offline' }} attendance ON</span>
            </template>
            <template v-if="channelExamFeeEnabled">
              <span class="meta-sep">•</span>
              <span class="meta-item meta-fee-on">{{ gridChannel === 'online' ? 'Online' : 'Offline' }} exam fee ON</span>
            </template>
          </div>
        </div>
      </div>
    </div>

    <div v-if="selectedExamId && !loading" class="grid-channel-bar no-print">
      <span class="grid-channel-label">Routine schedule view</span>
      <label class="grid-channel-option">
        <input v-model="gridChannel" type="radio" value="offline" @change="onGridChannelChange" />
        <span>Offline exam routine grid</span>
      </label>
      <label class="grid-channel-option">
        <input v-model="gridChannel" type="radio" value="online" @change="onGridChannelChange" />
        <span>Online exam routine grid</span>
      </label>
      <span class="grid-channel-hint">
        {{ gridChannel === 'online'
          ? 'Online only. To add another time row on the same date, open an empty cell and set a different start/end time in the wizard.'
          : 'Offline exam routines only (separate schedule)' }}
      </span>
    </div>

    <ExamEligibilityPanel
      v-if="selectedExamId && !loading"
      :key="`${selectedExamId}-${gridChannel}`"
      class="no-print"
      :exam-id="selectedExamId"
      :delivery-channel="gridChannel"
      :grid-month="gridData.month"
      :grid-year="gridData.year"
      @updated="onChannelPolicyUpdated"
      @published="onChannelPublished"
    />

    <!-- ===== MONTH CALENDAR GRID ===== -->
    <div class="routine-grid-wrapper exam-routine-grid" v-if="viewMode === 'grid' && gridData.weeks?.length">
      <div class="month-toolbar no-print">
        <button type="button" class="btn btn-sm btn-outline" @click="shiftGridMonth(-1)" :disabled="loading">← Prev</button>
        <h2 class="month-title">{{ gridData.month_label }}</h2>
        <button type="button" class="btn btn-sm btn-outline" @click="shiftGridMonth(1)" :disabled="loading">Next →</button>
        <label class="lunch-toggle">
          <input type="checkbox" v-model="showLunchBreaks" @change="applyFilters" />
          Show lunch breaks
        </label>
        <button
          type="button"
          class="btn btn-sm"
          :class="swapMode ? 'btn-warning' : 'btn-outline'"
          @click="toggleSwapMode"
        >
          {{ swapMode ? 'Swap: pick destination cell' : 'Swap mode' }}
        </button>
      </div>
      <p v-if="swapMode" class="swap-mode-hint no-print">
        <template v-if="swapSource">
          Selected <strong>{{ swapSource.routine.subject_name }}</strong> — click another card to swap, or an empty cell to move.
          <button type="button" class="swap-cancel" @click="swapSource = null">Cancel</button>
        </template>
        <template v-else>Click a card, then click another card or empty cell to swap/move.</template>
      </p>

      <div v-for="week in gridData.weeks" :key="week.week_start" class="week-block">
        <div class="grid-scroll">
          <table class="routine-table">
            <thead>
              <tr>
                <th class="th-time">Time</th>
                <th
                  v-for="col in week.columns"
                  :key="col.date"
                  class="th-day"
                  :class="['th-day-' + col.day_name.toLowerCase(), { 'th-today': isToday(col.date) }]"
                >
                  <span class="day-name" :style="{ color: getDayColor(col.day_name) }">{{ col.day_name.substring(0, 3) }}</span>
                  <span class="day-date">{{ formatHeaderDate(col.date) }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <template v-for="slot in visibleTimeSlots" :key="week.week_start + '-' + slot.key">
                <tr :class="{ 'lunch-row': slot.is_lunch }">
                  <td class="td-time">
                    <div class="time-slot-label" :class="{ 'time-slot-label--lunch': slot.is_lunch }">
                      <span class="slot-clock" aria-hidden="true">
                        <svg v-if="!slot.is_lunch" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="12" cy="12" r="9"/>
                          <path stroke-linecap="round" d="M12 7v5l3 2"/>
                        </svg>
                        <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" d="M12 3v2M12 19v2M5 12H3M21 12h-2"/>
                        </svg>
                      </span>
                      <div class="slot-times">
                        <template v-if="slot.start">
                          <span class="slot-range slot-range-line">
                            {{ formatTimeDisplay(slot.start) }} — {{ formatTimeDisplay(slot.end) }}
                          </span>
                          <span class="slot-duration">{{ formatDuration(slot.start, slot.end) }}</span>
                        </template>
                        <span class="slot-range lunch-range" v-else>Lunch</span>
                      </div>
                    </div>
                  </td>
                  <td
                    v-for="col in week.columns"
                    :key="col.date + '-' + slot.key"
                    class="td-day-cell"
                    :class="[
                      getWeekCellClass(col, slot),
                      {
                        'td-day-cell--addable': isCellAddable(col, slot) && !swapMode,
                        'td-day-cell--swap-target': swapMode && swapSource && isCellAddable(col, slot),
                      },
                    ]"
                    @click="onGridCellClick(col, slot, $event)"
                  >
                    <div v-if="slot.is_lunch" class="lunch-cell">
                      <span class="lunch-text">LUNCH</span>
                    </div>
                    <div v-else class="cell-exams">
                      <template v-if="getWeekCellRoutines(week, col.date, slot.key).length">
                        <div class="cell-cards-grid">
                          <template v-for="(batch, bIdx) in getWeekCellBatches(week, col.date, slot.key)" :key="batch.batch_id">
                            <div
                              v-if="batch.routines.length > 1"
                              class="batch-conflict-banner cell-span-full"
                              :title="batch.batch_name + ' has overlapping exams'"
                            >
                              Conflict: {{ batch.batch_name }}
                            </div>
                            <div
                              v-for="routine in batch.routines"
                              :key="routine.id"
                              class="exam-card exam-card--compact"
                              :class="{
                                'exam-card--conflict': batch.routines.length > 1,
                                'exam-card--swap-selected': swapSource?.routine?.id === routine.id,
                                'exam-card--swap-pickable': swapMode,
                              }"
                              :style="routineCardStyle(routine)"
                              @click="onSwapCardClick(routine, $event)"
                            >
                              <button
                                type="button"
                                class="card-delete-btn no-print"
                                title="Delete this slot"
                                @click.stop="confirmDeleteRoutine(routine)"
                              >×</button>
                              <div class="card-line card-field" :title="`Batch: ${batch.batch_name}`">
                                <span class="card-lbl">Batch:</span>
                                <span class="card-val card-batch" :style="{ color: routineBatchColor(routine) }">{{ batch.batch_name }}</span>
                              </div>
                              <div class="card-line card-field" :title="`Subject: ${routine.subject_name}`">
                                <span class="card-lbl">Subject:</span>
                                <span class="card-val card-subject" :style="{ color: routineSubjectColor(routine) }">{{ routine.subject_name }}</span>
                              </div>
                              <div class="card-line card-field" :title="`Type: ${routine.exam_type_name || '—'}`">
                                <span class="card-lbl">Type:</span>
                                <span class="card-val card-type">{{ routine.exam_type_name || '—' }}</span>
                              </div>
                              <div class="card-line card-field" :title="cardVenueTitle(routine)">
                                <span v-if="isOfflineDelivery(routine)" class="card-lbl">Room:</span>
                                <span class="card-val" :class="{ 'card-val--online': !isOfflineDelivery(routine) }">{{ cardVenueText(routine) }}</span>
                              </div>
                              <span
                                v-if="showDeliveryBadge(routine)"
                                class="delivery-pill"
                                :class="'dm-' + (routine.delivery_mode || 'offline')"
                              >{{ routine.delivery_label || 'Online' }}</span>
                              <span class="status-pill" :class="'st-' + (routine.status || 'draft')">{{ routineStatusLabel(routine.status) }}</span>
                            </div>
                          </template>
                        </div>
                        <button
                          type="button"
                          class="cell-add-batch-btn no-print"
                          title="Add another batch to this time slot"
                          @click.stop="openAddRoutineFromCell(col, slot)"
                        >
                          + Add batch
                        </button>
                      </template>
                      <div v-else class="cell-empty">
                        <span class="empty-dash">—</span>
                        <span class="cell-add-hint">Click to add routine</span>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <div v-if="week.friday_off?.in_month" class="friday-off-bar">
          <span class="friday-off-label">OFF</span>
          <span class="friday-off-date">Friday, {{ formatHeaderDate(week.friday_off.date) }}</span>
        </div>
      </div>
    </div>

    <!-- ===== CARD VIEW (student-style) ===== -->
    <div v-else-if="viewMode === 'cards' && cardRoutines.length" class="card-view-wrapper">
      <!-- Batch filter tabs for card view -->
      <div class="card-filter-tabs" v-if="cardBatches.length > 1">
        <button
          v-for="b in cardBatches"
          :key="b.id"
          class="card-filter-tab"
          :class="{ active: selectedCardBatch === b.id }"
          @click="selectedCardBatch = b.id"
        >
          <span class="card-filter-dot" :style="{ background: b.color }"></span>
          {{ b.name }}
          <span class="card-filter-count">{{ b.count }}</span>
        </button>
        <button
          class="card-filter-tab"
          :class="{ active: selectedCardBatch === 'all' }"
          @click="selectedCardBatch = 'all'"
        >
          All Batches
          <span class="card-filter-count">{{ cardRoutines.length }}</span>
        </button>
      </div>

      <div class="card-view-list">
        <div
          v-for="routine in filteredCardRoutines"
          :key="routine.id"
          class="card-view-item card-view-item--compact"
        >
          <div class="card-item-date">
            <div class="card-item-day">{{ getDayShort(routine._day_date) }}</div>
            <div class="card-item-num">{{ getDayNum(routine._day_date) }}</div>
            <div class="card-item-month">{{ getMonthShort(routine._day_date) }}</div>
          </div>
          <div class="card-item-body card-item-body--compact">
            <div class="exam-card exam-card--compact card-view-inner" :style="routineCardStyle(routine)">
              <div class="card-line card-field" :title="`Batch: ${routine.batch_name}`">
                <span class="card-lbl">Batch:</span>
                <span class="card-val card-batch" :style="{ color: routineBatchColor(routine) }">{{ routine.batch_name }}</span>
              </div>
              <div class="card-line card-field" :title="`Subject: ${routine.subject_name}`">
                <span class="card-lbl">Subject:</span>
                <span class="card-val card-subject" :style="{ color: routineSubjectColor(routine) }">{{ routine.subject_name }}</span>
              </div>
              <div class="card-line card-field" :title="`Type: ${routine.exam_type_name || '—'}`">
                <span class="card-lbl">Type:</span>
                <span class="card-val card-type">{{ routine.exam_type_name || '—' }}</span>
              </div>
              <div class="card-line card-field" :title="cardVenueTitle(routine)">
                <span v-if="isOfflineDelivery(routine)" class="card-lbl">Room:</span>
                <span class="card-val" :class="{ 'card-val--online': !isOfflineDelivery(routine) }">{{ cardVenueText(routine) }}</span>
              </div>
              <span
                v-if="showDeliveryBadge(routine)"
                class="delivery-pill"
                :class="'dm-' + (routine.delivery_mode || 'offline')"
              >{{ routine.delivery_label || 'Online' }}</span>
              <span class="status-pill" :class="'st-' + (routine.status || 'draft')">{{ routineStatusLabel(routine.status) }}</span>
            </div>
            <div class="card-item-actions no-print">
              <button class="btn btn-xs btn-outline" @click="openQuestionPicker(routine)">Questions</button>
              <button class="btn btn-xs btn-outline" @click="downloadQuestionPaper(routine.id, 'student')">Paper PDF</button>
              <button class="btn btn-xs btn-danger-outline" @click="confirmDeleteRoutine(routine)">Delete</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== EMPTY STATE (no routines in channel) ===== -->
    <div v-else-if="!loading && selectedExamId && viewMode === 'grid'" class="empty-state">
      <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      <h3>No {{ gridChannel === 'online' ? 'online' : 'offline' }} routines</h3>
      <p>
        {{ gridChannel === 'online'
          ? 'Click Add or an empty cell to open Exam Setup Wizard (same step-by-step flow as Exams menu).'
          : 'Click Add or an empty grid cell to create an offline exam slot.' }}
      </p>
      <button type="button" class="btn btn-sm btn-primary" @click="openAddRoutine">
        {{ gridChannel === 'online' ? 'Open Setup Wizard' : 'Add Offline Slot' }}
      </button>
      <button type="button" class="btn btn-sm btn-outline" @click="gridChannel = gridChannel === 'online' ? 'offline' : 'online'; onGridChannelChange()">
        Switch to {{ gridChannel === 'online' ? 'offline' : 'online' }} grid
      </button>
    </div>

    <!-- ===== EMPTY STATE ===== -->
    <div v-else-if="!loading && !selectedExamId" class="empty-state">
      <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      <h3>No Exam Selected</h3>
      <p>Select an exam to view the monthly routine calendar.</p>
    </div>

    <!-- ===== LOADING ===== -->
    <div v-if="loading" class="loading-overlay">
      <div class="loading-spinner"></div>
      <span>Loading...</span>
    </div>

    <!-- ===== TOAST ===== -->
    <Transition name="toast-slide">
      <div v-if="showToast" class="toast">
        <svg class="toast-icon" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <span>{{ toastMessage }}</span>
        <button class="toast-close" @click="showToast = false">&times;</button>
      </div>
    </Transition>

    <!-- ===== MODALS ===== -->
    <ExamRoutineFormModal
      v-if="showFormModal"
      :routine="null"
      :exams="store.exams"
      :classes="classes"
      :teachers="teachers"
      :rooms="rooms"
      :exam-types="examTypes"
      :selected-exam="gridData.exam"
      :initial-slot="formInitialSlot"
      :default-delivery-mode="defaultDeliveryModeForChannel"
      :lock-delivery-mode="true"
      @close="closeFormModal"
      @save="onFormSave"
    />
    <ExamRoutineBulkModal
      v-if="showBulkModal"
      :exams="store.exams"
      :subjects="[]"
      :selected-exam="gridData.exam"
      :classes="classes"
      :teachers="teachers"
      :rooms="rooms"
      :exam-types="examTypes"
      @close="showBulkModal = false"
      @save="onBulkSave"
    />
    <ExamRoutineGenerateWizard
      v-if="showGenerateModal"
      :exams="store.exams"
      :subjects="[]"
      :selected-exam="gridData.exam"
      :classes="classes"
      :teachers="teachers"
      :rooms="rooms"
      :exam-types="examTypes"
      :delivery-mode="defaultDeliveryModeForChannel"
      @close="showGenerateModal = false"
      @generate="onGenerateSave"
    />
    <RoutineQuestionPickerModal
      v-if="pickerRoutine"
      :routine="pickerRoutine"
      @close="pickerRoutine = null"
      @saved="onQuestionsSaved"
    />
  </div>
</template>

<script setup>
import '@/styles/exam-routine-grid.css'
import { ref, reactive, computed, onMounted, onActivated, watch } from 'vue'
import { useRouter } from 'vue-router'
import examService from '@/services/exam.service'
import academicService from '@/services/academic.service'
import enrollmentService from '@/services/enrollment.service'
import teacherService from '@/services/teacher.service'
import { useExamRoutineStore } from '@/stores/exam-routine.store'
import ExamRoutineFormModal from '@/components/exam/ExamRoutineFormModal.vue'
import ExamRoutineBulkModal from '@/components/exam/ExamRoutineBulkModal.vue'
import ExamRoutineGenerateWizard from '@/components/exam/ExamRoutineGenerateWizard.vue'
import ExamEligibilityPanel from '@/components/exam/ExamEligibilityPanel.vue'
import RoutineQuestionPickerModal from '@/components/exam/RoutineQuestionPickerModal.vue'

const router = useRouter()
const store = useExamRoutineStore()
const loading = ref(false)
const viewMode = ref('grid') // 'grid' | 'cards'

// ===== CARD VIEW (flatten month weeks into card list) =====
const cardRoutines = computed(() => {
  const items = []
  for (const week of gridData.weeks || []) {
    for (const col of week.columns || []) {
      const daySlots = week.grid?.[col.date]
      if (!daySlots) continue
      for (const slotKey of Object.keys(daySlots)) {
        const slot = daySlots[slotKey]
        if (!slot || slot.is_lunch) continue
        for (const r of (slot.routines || []).filter(routineMatchesGridChannel)) {
          items.push({
            ...r,
            _day: col.day_name,
            _slot_key: slotKey,
            _slot_start: slot.start,
            _slot_end: slot.end,
            _day_date: r.exam_date || col.date,
            _week_label: week.label,
          })
        }
      }
    }
  }

  items.sort((a, b) => {
    const dateCmp = (a._day_date || '').localeCompare(b._day_date || '')
    if (dateCmp !== 0) return dateCmp
    return (a.start_time || '').localeCompare(b.start_time || '')
  })

  return items
})

const showLunchBreaks = ref(false)

const visibleTimeSlots = computed(() =>
  (gridData.time_slots || []).filter((s) => showLunchBreaks.value || !s.is_lunch)
)

// Card view batch filter
const selectedCardBatch = ref('all')

const cardBatches = computed(() => {
  const map = {}
  for (const r of cardRoutines.value) {
    const bid = r.batch_id || 'unknown'
    if (!map[bid]) {
      map[bid] = { id: bid, name: r.batch_name || 'Unknown', color: r.batch_color || '#3B82F6', count: 0 }
    }
    map[bid].count++
  }
  return Object.values(map)
})

const filteredCardRoutines = computed(() => {
  if (selectedCardBatch.value === 'all') return cardRoutines.value
  return cardRoutines.value.filter(r => (r.batch_id || 'unknown') === selectedCardBatch.value)
})

// Date helpers for card view
function getDayShort(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('en-US', { weekday: 'short' })
}
function getDayNum(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr + 'T00:00:00')
  return d.getDate()
}
function getMonthShort(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('en-US', { month: 'short' })
}

// ===== REFERENCE DATA =====
const classes = ref([])
const teachers = ref([])
const rooms = ref([])
const examTypes = ref([])

async function loadReferenceData() {
  try {
    const [classesRes, teachersRes, roomsRes, typesRes] = await Promise.all([
      academicService.classes.listAll(),
      teacherService.listAll(),
      academicService.rooms.listAll(),
      examService.types.list(),
    ])
    classes.value = classesRes.data?.data || classesRes.data || []
    teachers.value = teachersRes.data?.data || teachersRes.data || teachersRes || []
    rooms.value = roomsRes.data?.data || roomsRes.data || []
    examTypes.value = typesRes.data?.data || typesRes.data || []
  } catch (e) {
    console.error('Failed to load reference data:', e)
  }
}

// ===== EXAM SELECTOR =====
const selectedExamId = ref('')

function onExamSelectChange() {
  channelPolicyMeta.value = {
    scope: 'all',
    eligibilityEnabled: false,
    examFeeEnabled: false,
  }
  if (selectedExamId.value) {
    const exam = store.exams.find(e => e.id === selectedExamId.value)
    if (exam) {
      store.selectedExam = exam
      loadGrid(exam.id)
    }
  }
}

async function refreshExams() {
  await store.fetchAllExams()
  triggerToast('Exam list refreshed!')
}

// ===== EXPORT STATE =====
const showExportMenu = ref(false)

// Close export menu on click outside
function closeExportMenu(e) {
  if (!e.target.closest('.export-dropdown')) {
    showExportMenu.value = false
  }
}
if (typeof document !== 'undefined') {
  document.addEventListener('click', closeExportMenu)
}

async function exportPDF() {
  showExportMenu.value = false
  if (!gridData.exam?.id) { triggerToast('No exam selected.'); return }
  try {
    const res = await examService.routines.exportPdf(gridData.exam.id)
    const blob = new Blob([res.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `Exam_Routine_${gridData.exam.name || gridData.exam.id}.pdf`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
    triggerToast('PDF downloaded!')
  } catch (e) {
    triggerToast('PDF export failed.')
  }
}

async function downloadImage() {
  showExportMenu.value = false
  triggerToast('Taking screenshot...')
  try {
    const { default: html2canvas } = await import('html2canvas')
    const el = document.querySelector('.erp-exam-routine')
    if (!el) return
    const canvas = await html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff' })
    const link = document.createElement('a')
    link.download = `Exam_Routine_${gridData.exam?.name || 'export'}.png`
    link.href = canvas.toDataURL('image/png')
    link.click()
    triggerToast('Image downloaded!')
  } catch (e) {
    triggerToast('Image export requires html2canvas. Install: npm install html2canvas')
  }
}

function windowPrint() {
  showExportMenu.value = false
  window.print()
}

// ===== GRID CHANNEL (offline vs online schedules) =====
const GRID_CHANNEL_KEY = 'exam_routine_grid_channel'
const savedGridChannel = typeof sessionStorage !== 'undefined'
  ? sessionStorage.getItem(GRID_CHANNEL_KEY)
  : null
const gridChannel = ref(savedGridChannel === 'online' ? 'online' : 'offline')

const defaultDeliveryModeForChannel = computed(() =>
  gridChannel.value === 'online' ? 'online' : 'offline',
)

const channelPolicyMeta = ref({
  scope: 'all',
  eligibilityEnabled: false,
  examFeeEnabled: false,
})

const channelEligibilityEnabled = computed(() => channelPolicyMeta.value.eligibilityEnabled)

const channelExamFeeEnabled = computed(() => channelPolicyMeta.value.examFeeEnabled)

function onChannelPolicyUpdated(meta) {
  channelPolicyMeta.value = {
    scope: meta?.scope || 'all',
    eligibilityEnabled: !!meta?.eligibilityEnabled,
    examFeeEnabled: !!meta?.examFeeEnabled,
  }
}

async function onChannelPublished() {
  if (gridData.exam?.id) {
    await loadGrid(gridData.exam.id, getFilterParams())
  }
}

function onGridChannelChange() {
  swapMode.value = false
  swapSource.value = null
  if (typeof sessionStorage !== 'undefined') {
    sessionStorage.setItem(GRID_CHANNEL_KEY, gridChannel.value)
  }
  if (gridData.exam?.id) {
    loadGrid(gridData.exam.id)
  }
}

// ===== FILTER STATE =====
const showFilterPanel = ref(false)
const filterClass = ref('')
const filterCourse = ref('')
const filterBatch = ref('')
const filterExamType = ref('')
const filterStatus = ref('')

function applyFilters() {
  if (gridData.exam?.id) {
    loadGrid(gridData.exam.id, getFilterParams())
  }
}

function getFilterParams() {
  const params = {}
  if (filterClass.value) params.class_id = filterClass.value
  if (filterCourse.value) params.course_id = filterCourse.value
  if (filterBatch.value) params.batch_id = filterBatch.value
  if (filterExamType.value) params.exam_type_id = filterExamType.value
  if (filterStatus.value) params.status = filterStatus.value
  if (gridData.month) params.month = gridData.month
  if (gridData.year) params.year = gridData.year
  params.include_lunch = showLunchBreaks.value ? 1 : 0
  params.delivery_channel = gridChannel.value
  return params
}

function clearFilters() {
  filterClass.value = ''
  filterCourse.value = ''
  filterBatch.value = ''
  filterExamType.value = ''
  filterStatus.value = ''
  if (gridData.exam?.id) {
    loadGrid(gridData.exam.id, getFilterParams())
  }
}

// ===== SWAP MODE =====
const swapMode = ref(false)
const swapSource = ref(null)

// ===== MODAL STATE =====
const showFormModal = ref(false)
const formInitialSlot = ref(null)
const showBulkModal = ref(false)
const showGenerateModal = ref(false)
const pickerRoutine = ref(null)
const toastMessage = ref('')
const showToast = ref(false)
let toastTimer = null

function triggerToast(msg) {
  toastMessage.value = msg
  showToast.value = true
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { showToast.value = false }, 4000)
}

// ===== GRID DATA =====
const gridData = reactive({
  grid: {},
  weeks: [],
  days: ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
  day_dates: {},
  month: null,
  year: null,
  month_label: '',
  time_slots: [],
  batches: [],
  batch_colors: {},
  filter_classes: [],
  filter_courses: [],
  filter_exam_types: [],
  exam: null,
})
function formatExamTypeLabel(exam) {
  if (!exam) return 'N/A'
  const t = exam.exam_type ?? exam.examType
  if (typeof t === 'string') return t
  if (t && typeof t === 'object' && t.name) return t.name
  return exam.exam_type_name || 'N/A'
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

/**
 * Calculate and format the duration between start and end time.
 * Returns e.g. "1h 30m" or "45m".
 */
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


function formatShortDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function formatHeaderDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function getWeekCellRoutines(week, dateStr, slotKey) {
  const routines = week?.grid?.[dateStr]?.[slotKey]?.routines || []
  return routines.filter(routineMatchesGridChannel)
}

function getWeekCellBatches(week, dateStr, slotKey) {
  const routines = getWeekCellRoutines(week, dateStr, slotKey)
  const batchMap = {}
  for (const r of routines) {
    const bid = r.batch_id || 'unknown'
    if (!batchMap[bid]) {
      batchMap[bid] = { batch_id: bid, batch_name: r.batch_name || 'Unknown', routines: [] }
    }
    batchMap[bid].routines.push(r)
  }
  return Object.values(batchMap)
}

function routineStatusLabel(status) {
  const map = { draft: 'Draft', published: 'Published', completed: 'Done', cancelled: 'Off' }
  return map[status] || status || 'Draft'
}

function routineBatchColor(routine) {
  return routine.batch_text_color || '#4B5563'
}

function routineSubjectColor(routine) {
  return routine.batch_text_color || '#374151'
}

function routineDeliveryChannel(routine) {
  if (routine?.delivery_channel === 'online' || routine?.delivery_channel === 'offline') {
    return routine.delivery_channel
  }
  const mode = routine.delivery_mode || 'offline'
  return mode === 'online' || mode === 'hybrid' ? 'online' : 'offline'
}

function routineMatchesGridChannel(routine) {
  return routineDeliveryChannel(routine) === gridChannel.value
}

function routineDeliveryMode(routine) {
  return routine.delivery_mode || 'offline'
}

function isOfflineDelivery(routine) {
  return routineDeliveryChannel(routine) === 'offline'
}

function showDeliveryBadge(routine) {
  const mode = routineDeliveryMode(routine)
  return mode === 'online' || mode === 'hybrid'
}

function formatRoomNumber(routine) {
  const raw = routine.room_name || routine.room?.room_number || routine.room?.name
  if (!raw || raw === 'TBD') return '—'
  return String(raw).replace(/^room\s*/i, '').trim() || String(raw)
}

function cardVenueText(routine) {
  if (!isOfflineDelivery(routine)) return 'Online player'
  return formatRoomNumber(routine)
}

function cardVenueTitle(routine) {
  if (!isOfflineDelivery(routine)) return 'Online player'
  return `Room: ${formatRoomNumber(routine)}`
}

function toggleSwapMode() {
  swapMode.value = !swapMode.value
  swapSource.value = null
}

function onSwapCardClick(routine, event) {
  if (!swapMode.value) return
  event.stopPropagation()
  if (!swapSource.value) {
    swapSource.value = { routine }
    return
  }
  if (swapSource.value.routine?.id === routine.id) {
    swapSource.value = null
    return
  }
  swapTwoRoutines(swapSource.value.routine, routine)
}

async function swapTwoRoutines(source, target) {
  if (!gridData.exam?.id) return
  const aDate = (source.exam_date || '').toString().substring(0, 10)
  const bDate = (target.exam_date || '').toString().substring(0, 10)
  const aStart = normalizeSlotTime(source.start_time)
  const aEnd = normalizeSlotTime(source.end_time)
  const bStart = normalizeSlotTime(target.start_time)
  const bEnd = normalizeSlotTime(target.end_time)
  try {
    await store.updateRoutine(source.id, {
      exam_date: bDate,
      start_time: bStart,
      end_time: bEnd,
    })
    await store.updateRoutine(target.id, {
      exam_date: aDate,
      start_time: aStart,
      end_time: aEnd,
    })
    swapSource.value = null
    triggerToast(`Swapped ${source.subject_name || 'routine'} with ${target.subject_name || 'routine'}`)
    await loadGrid(gridData.exam.id, getFilterParams())
  } catch (e) {
    triggerToast('Swap failed: ' + (store.error || 'Unknown error'))
  }
}

async function moveRoutineToCell(routine, col, slot) {
  if (!gridData.exam?.id) return
  const start = normalizeSlotTime(slot.start)
  const end = normalizeSlotTime(slot.end)
  if (!start || !end) {
    triggerToast('Invalid time slot.')
    return
  }
  try {
    await store.updateRoutine(routine.id, {
      exam_date: col.date,
      start_time: start,
      end_time: end,
    })
    swapSource.value = null
    triggerToast(`Moved ${routine.subject_name || 'routine'} to ${col.day_name}, ${formatHeaderDate(col.date)}`)
    await loadGrid(gridData.exam.id, getFilterParams())
  } catch (e) {
    triggerToast('Move failed: ' + (store.error || 'Unknown error'))
  }
}

async function onSwapTargetCell(col, slot, event) {
  if (!swapMode.value || !swapSource.value?.routine) return
  if (!isCellAddable(col, slot)) return
  if (event.target.closest('.card-delete-btn')) return
  const routine = swapSource.value.routine
  const currentDate = (routine.exam_date || '').toString().substring(0, 10)
  const currentStart = normalizeSlotTime(routine.start_time)
  const targetStart = normalizeSlotTime(slot.start)
  if (currentDate === col.date && currentStart === targetStart) {
    swapSource.value = null
    triggerToast('Same slot — selection cleared.')
    return
  }
  await moveRoutineToCell(routine, col, slot)
}

function hexToRgba(hex, alpha) {
  const h = String(hex || '#F3F4F6').replace('#', '')
  if (h.length < 6) return `rgba(243, 244, 246, ${alpha})`
  const r = parseInt(h.slice(0, 2), 16)
  const g = parseInt(h.slice(2, 4), 16)
  const b = parseInt(h.slice(4, 6), 16)
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

function routineCardStyle(routine) {
  const bg = routine.batch_color || '#F3F4F6'
  const text = routine.batch_text_color || '#4B5563'
  return {
    backgroundColor: bg,
    border: `1px solid ${hexToRgba(bg, 0.85)}`,
    color: text,
  }
}

async function confirmDeleteRoutine(routine) {
  const label = `${routine.subject_name || 'Subject'} · ${routine.exam_date || routine._day_date || 'date'}`
  if (!confirm(`Delete routine slot "${label}"? This cannot be undone.`)) return
  try {
    await examService.routines.delete(routine.id)
    if (gridData.exam?.id) {
      await loadGrid(gridData.exam.id, getFilterParams())
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to delete routine slot')
  }
}

function isToday(date) {
  if (!date) return false
  const today = new Date()
  const y = today.getFullYear()
  const m = String(today.getMonth() + 1).padStart(2, '0')
  const d = String(today.getDate()).padStart(2, '0')
  return date.toString().substring(0, 10) === `${y}-${m}-${d}`
}

function getWeekCellClass(col, slot) {
  const cls = {}
  cls['td-day-' + col.day_name.toLowerCase()] = true
  if (!col.in_month) cls['td-out-month'] = true
  if (slot.is_lunch) cls['td-lunch'] = true
  if (isToday(col.date)) cls['td-today'] = true
  return cls
}

function shiftGridMonth(delta) {
  let m = gridData.month || new Date().getMonth() + 1
  let y = gridData.year || new Date().getFullYear()
  m += delta
  if (m < 1) {
    m = 12
    y--
  } else if (m > 12) {
    m = 1
    y++
  }
  gridData.month = m
  gridData.year = y
  if (gridData.exam?.id) {
    loadGrid(gridData.exam.id, getFilterParams())
  }
}

async function loadGrid(examId, extraParams = {}) {
  if (!examId) return
  loading.value = true
  try {
    const params = { ...getFilterParams(), ...extraParams }
    const res = await examService.routines.grid(examId, params)
    const data = res.data?.data || res.data
    if (data) {
      gridData.grid = data.grid || {}
      gridData.weeks = data.weeks || []
      gridData.days = data.days || ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
      gridData.day_dates = data.day_dates || {}
      gridData.month = data.month
      gridData.year = data.year
      gridData.month_label = data.month_label || ''
      gridData.time_slots = data.time_slots || []
      gridData.batches = data.batches || []
      gridData.batch_colors = data.batch_colors || {}
      gridData.filter_classes = data.filter_classes || []
      gridData.filter_courses = data.filter_courses || []
      gridData.filter_exam_types = data.filter_exam_types || []
      gridData.exam = data.exam || null
      if (gridData.exam && typeof gridData.exam.exam_type === 'object') {
        const rel = gridData.exam.exam_type
        gridData.exam = {
          ...gridData.exam,
          exam_type_name: rel?.name ?? gridData.exam.exam_type_name,
          exam_type: rel?.name ?? gridData.exam.exam_type_name,
        }
      }
      if (gridData.exam?.id) {
        try {
          const examRes = await examService.exams.get(gridData.exam.id)
          const full = examRes.data?.data || examRes.data
          if (full) {
            // Only merge fields needed for UI — do not overwrite grid exam_type string with relation object
            gridData.exam = {
              ...gridData.exam,
              eligibility_check_enabled: !!full.eligibility_check_enabled,
              exam_fee_applicable: !!full.exam_fee_applicable,
              online_eligibility_check_enabled: !!full.online_eligibility_check_enabled,
              online_exam_fee_applicable: !!full.online_exam_fee_applicable,
              online_min_attendance_percent: full.online_min_attendance_percent ?? null,
              min_attendance_percent: full.min_attendance_percent ?? null,
              offline_policy_scope: full.offline_policy_scope || 'all',
              online_policy_scope: full.online_policy_scope || 'all',
              result_status: full.result_status ?? gridData.exam.result_status,
              result_publish_at: full.result_publish_at ?? gridData.exam.result_publish_at,
            }
          }
        } catch { /* keep grid exam snapshot */ }
      }
      if (typeof data.include_lunch === 'boolean') {
        showLunchBreaks.value = data.include_lunch
      }
    }
  } catch (e) {
    console.error('Failed to load exam routine grid:', e)
  } finally {
    loading.value = false
  }
}

function getBatchBgColor(batchColor) {
  if (!batchColor) return 'rgba(226, 232, 240, 0.3)'
  const hex = batchColor.replace('#', '')
  const r = parseInt(hex.substring(0, 2), 16)
  const g = parseInt(hex.substring(2, 4), 16)
  const b = parseInt(hex.substring(4, 6), 16)
  return `rgba(${r}, ${g}, ${b}, 0.12)`
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


/**
 * Get human-readable label for routine status.
 */
function getStatusLabel(status) {
  const labels = {
    draft: 'Draft',
    published: 'Published',
    completed: 'Completed',
    cancelled: 'Cancelled',
  }
  return labels[status] || status
}

// ===== MANAGEMENT ACTIONS =====
function normalizeSlotTime(timeStr) {
  if (!timeStr) return ''
  const str = String(timeStr)
  return str.length >= 5 ? str.substring(0, 5) : str
}

function isCellAddable(col, slot) {
  if (slot?.is_lunch || !col?.in_month) return false
  if (col.day_name === 'Friday') return false
  return !!gridData.exam?.id
}

function openOnlineSetupWizard(scheduleDate = null, slot = null) {
  if (!gridData.exam?.id) {
    triggerToast('Please select an exam first.')
    return
  }
  const query = {
    exam_id: gridData.exam.id,
    delivery_mode: 'online',
    from: 'grid',
  }
  if (scheduleDate) {
    query.schedule_date = String(scheduleDate).slice(0, 10)
  }
  if (slot?.start) {
    query.slot_start = normalizeSlotTime(slot.start)
  }
  if (slot?.end) {
    query.slot_end = normalizeSlotTime(slot.end)
  }
  router.push({ name: 'ExamSetupWizard', query })
}

function openAddRoutineFromCell(col, slot) {
  if (!isCellAddable(col, slot)) {
    if (!gridData.exam?.id) triggerToast('Please select an exam first.')
    return
  }
  if (gridChannel.value === 'online') {
    // Empty cell — let the wizard pick a new time so the grid gains another row.
    openOnlineSetupWizard(col.date)
    return
  }
  formInitialSlot.value = {
    exam_date: col.date,
    day_name: col.day_name,
    start_time: normalizeSlotTime(slot.start),
    end_time: normalizeSlotTime(slot.end),
    delivery_mode: defaultDeliveryModeForChannel.value,
  }
  showFormModal.value = true
}

function onGridCellClick(col, slot, event) {
  if (swapMode.value) {
    onSwapTargetCell(col, slot, event)
    return
  }
  if (!isCellAddable(col, slot)) return
  if (event.target.closest('.card-delete-btn')) return
  if (event.target.closest('.exam-card') && !swapMode.value) return
  if (event.target.closest('.cell-add-batch-btn')) return
  openAddRoutineFromCell(col, slot)
}

function closeFormModal() {
  showFormModal.value = false
  formInitialSlot.value = null
}

function openAddRoutine() {
  if (gridChannel.value === 'online') {
    openOnlineSetupWizard()
    return
  }
  formInitialSlot.value = null
  showFormModal.value = true
}
function openBulkStore() { showBulkModal.value = true }
function openGenerate() {
  if (!gridData.exam?.id) { triggerToast('Please select an exam first.'); return }
  showGenerateModal.value = true
}

function openQuestionPicker(routine) {
  pickerRoutine.value = {
    id: routine.id,
    subject_id: routine.subject_id,
    subject_name: routine.subject_name,
    batch_name: routine.batch_name,
  }
}

function onQuestionsSaved() {
  triggerToast('Questions saved successfully!')
}

async function downloadQuestionPaper(routineId, variant = 'student') {
  try {
    const res = await examService.routines.exportQuestionPaper(routineId, variant)
    const blob = new Blob([res.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `question-paper-${routineId}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    triggerToast('Failed to download question paper PDF')
  }
}

async function publishRoutines() {
  if (!gridData.exam?.id) return
  const channelLabel = gridChannel.value === 'online' ? 'online' : 'offline'
  if (!confirm(`Publish ${channelLabel} routines for "${gridData.exam.name || 'this exam'}"?`)) return
  try {
    await store.publishRoutines(gridData.exam.id, getFilterParams())
    triggerToast('Routines published successfully!')
    await loadGrid(gridData.exam.id, getFilterParams())
  } catch (e) {
    triggerToast('Failed to publish: ' + (store.error || 'Unknown error'))
  }
}

async function checkConflicts() {
  if (!gridData.exam?.id) return
  try {
    const conflicts = await store.fetchConflicts(gridData.exam.id, { delivery_channel: gridChannel.value })
    if (!conflicts || conflicts.length === 0) {
      triggerToast('No scheduling conflicts found!')
    } else {
      triggerToast('Found ' + conflicts.length + ' conflict(s).')
      console.warn('[Conflicts]', conflicts)
    }
  } catch (e) {
    triggerToast('Failed to check conflicts.')
  }
}

async function cancelRoutines() {
  if (!gridData.exam?.id) return
  if (!confirm('Cancel ALL routines for "' + (gridData.exam.name || 'this exam') + '"?')) return
  try {
    await store.cancelRoutines(gridData.exam.id)
    triggerToast('Routines cancelled.')
    await loadGrid(gridData.exam.id)
  } catch (e) {
    triggerToast('Failed to cancel: ' + (store.error || 'Unknown error'))
  }
}

// ===== MODAL SAVE HANDLERS =====
async function onFormSave(data) {
  try {
    await store.createRoutine(data)
    closeFormModal()
    triggerToast('Routine added!')
    if (gridData.exam?.id) await loadGrid(gridData.exam.id)
  } catch (e) {
    triggerToast('Failed: ' + (store.error || 'Unknown error'))
  }
}

async function onBulkSave(data) {
  try {
    await store.bulkStore(data)
    showBulkModal.value = false
    triggerToast('Routines saved!')
    if (gridData.exam?.id) await loadGrid(gridData.exam.id)
  } catch (e) {
    triggerToast('Bulk save failed: ' + (store.error || 'Unknown error'))
  }
}

async function onGenerateSave(data) {
  try {
    const result = await store.generate({
      ...data,
      merge_mode: data.merge_mode || 'append',
      delivery_mode: data.delivery_mode || defaultDeliveryModeForChannel.value,
    })
    showGenerateModal.value = false
    const added = result?.total_slots ?? result?.generated?.length ?? 0
    const skipped = result?.skipped_existing ?? 0
    if (added === 0 && skipped > 0) {
      triggerToast(`All ${skipped} subject(s) already scheduled — existing routines kept.`)
    } else {
      triggerToast(added ? `Added ${added} routine(s). Existing schedules kept.` : 'Routines generated!')
    }
    if (gridData.exam?.id) await loadGrid(gridData.exam.id)
  } catch (e) {
    triggerToast('Generation failed: ' + (store.error || 'Unknown error'))
  }
}

watch(() => store.selectedExam, (exam) => {
  if (exam?.id) {
    selectedExamId.value = exam.id
    loadGrid(exam.id)
  }
}, { immediate: true })

onMounted(async () => {
  loadReferenceData()
  await store.fetchAllExams()
  if (!store.selectedExam && store.exams.length > 0) {
    store.selectedExam = store.exams[0]
    selectedExamId.value = store.exams[0].id
    loadGrid(store.exams[0].id)
  } else if (store.selectedExam?.id) {
    selectedExamId.value = store.selectedExam.id
    loadGrid(store.selectedExam.id)
  }
})

onActivated(() => {
  if (gridData.exam?.id) {
    loadGrid(gridData.exam.id, getFilterParams())
  }
})
</script>

<style scoped>
/* ===== BASE ===== */
.erp-exam-routine {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  color: var(--text-dark);
  max-width: 1400px;
  margin: 0 auto;
  padding: 1.5rem;
  background: var(--bg-card);
  min-height: 100vh;
}

/* ===== TOP ACTION BAR ===== */
.top-action-bar {
  margin-bottom: 1rem;
}
.action-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.5rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 0.5rem 0.75rem;
}
.action-left {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.action-right {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-wrap: wrap;
}
.exam-select {
  padding: 0.35rem 0.6rem;
  border: 1px solid var(--border-strong);
  border-radius: 6px;
  font-size: 0.8rem;
  color: var(--text-dark);
  background: var(--bg-surface-muted);
  min-width: 200px;
  cursor: pointer;
}
.exam-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
}
.btn-separator {
  width: 1px;
  height: 22px;
  background: #e2e8f0;
  margin: 0 0.15rem;
}

/* ===== BUTTONS ===== */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  border: 1px solid transparent;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease, opacity 0.15s ease;
  font-family: inherit;
  white-space: nowrap;
  padding: 0.35rem 0.65rem;
  flex-shrink: 0;
}
.btn:active:not(:disabled) { transform: none; opacity: 0.92; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn .ico { width: 14px; height: 14px; flex-shrink: 0; }
.btn-sm { padding: 0.3rem 0.55rem; font-size: 0.7rem; }
.btn-primary { background: #1e3a5f; color: #fff; border-color: #1e3a5f; }
.btn-primary:hover:not(:disabled) { background: #152d4a; }
.btn-secondary { background: #f3f4f6; color: var(--text-secondary); border-color: #d1d5db; }
.btn-secondary:hover:not(:disabled) { background: #e5e7eb; }
.btn-success { background: #059669; color: #fff; border-color: #059669; }
.btn-success:hover:not(:disabled) { background: #047857; }
.btn-warning { background: #d97706; color: #fff; border-color: #d97706; }
.btn-warning:hover:not(:disabled) { background: #b45309; }
.btn-danger { background: #dc2626; color: #fff; border-color: #dc2626; }
.btn-danger:hover:not(:disabled) { background: #b91c1c; }
.btn-outline { background: var(--bg-card); color: var(--text-secondary); border-color: #d1d5db; }
.btn-outline:hover:not(:disabled) { background: var(--bg-accent); border-color: var(--text-muted); }
.btn-filter { background: #f0f9ff; color: #0369a1; border-color: #bae6fd; }
.btn-filter:hover:not(:disabled) { background: #e0f2fe; }

/* ===== FILTER PANEL ===== */
.filter-panel {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: var(--bg-surface-muted);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  flex-wrap: wrap;
}
.filter-group {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}
.filter-group label {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-secondary);
}
.filter-select {
  padding: 0.25rem 0.5rem;
  border: 1px solid var(--border-strong);
  border-radius: 5px;
  font-size: 0.75rem;
  color: var(--text-dark);
  background: var(--bg-card);
  min-width: 140px;
  cursor: pointer;
}
.filter-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59,130,246,0.12);
}

/* ===== EXAM HEADER ===== */
.exam-header {
  background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
  border-radius: 10px;
  padding: 0.85rem 1.25rem;
  margin-bottom: 1rem;
  color: white;
}
.header-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.header-brand-icon {
  width: 36px;
  height: 36px;
  background: rgba(255,255,255,0.1);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.header-brand-icon svg {
  width: 20px;
  height: 20px;
  color: #facc15;
}
.header-body { flex: 1; }
.header-title {
  font-size: 1.05rem;
  font-weight: 700;
  margin: 0;
  color: #facc15;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  line-height: 1.3;
}
.header-meta {
  font-size: 0.65rem;
  color: var(--text-muted);
  margin-top: 0.15rem;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.25rem;
}
.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
}
.meta-ico {
  width: 12px;
  height: 12px;
  opacity: 0.6;
}
.meta-sep { color: var(--text-secondary); font-size: 0.5rem; }

/* ===== ROUTINE GRID ===== */
.routine-grid-wrapper {
  width: 100%;
  background: var(--bg-card);
  border-radius: 10px;
  border: 1px solid var(--border-color);
  overflow: visible;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.grid-scroll {
  width: 100%;
  overflow-x: hidden;
}

/* Day-specific header tints (theme-aware so they read in dark + light) */
.th-day-saturday { background: color-mix(in srgb, #3b82f6 11%, var(--bg-card)); }
.th-day-sunday { background: color-mix(in srgb, #10b981 11%, var(--bg-card)); }
.th-day-monday { background: color-mix(in srgb, #f97316 11%, var(--bg-card)); }
.th-day-tuesday { background: color-mix(in srgb, #8b5cf6 11%, var(--bg-card)); }
.th-day-wednesday { background: color-mix(in srgb, #ec4899 11%, var(--bg-card)); }
.th-day-thursday { background: color-mix(in srgb, #06b6d4 11%, var(--bg-card)); }
.th-day-friday { background: color-mix(in srgb, #ef4444 12%, var(--bg-card)); }

/* Day column body tints (match headers, subtler) */
.td-day-saturday { background: color-mix(in srgb, #3b82f6 5%, var(--bg-card)); }
.td-day-sunday { background: color-mix(in srgb, #10b981 5%, var(--bg-card)); }
.td-day-monday { background: color-mix(in srgb, #f97316 5%, var(--bg-card)); }
.td-day-tuesday { background: color-mix(in srgb, #8b5cf6 5%, var(--bg-card)); }
.td-day-wednesday { background: color-mix(in srgb, #ec4899 5%, var(--bg-card)); }
.td-day-thursday { background: color-mix(in srgb, #06b6d4 5%, var(--bg-card)); }
.td-day-friday { background: color-mix(in srgb, #ef4444 6%, var(--bg-card)); }

/* Current date (today) highlight — clearly visible in both modes */
.routine-table .th-today {
  background: color-mix(in srgb, var(--primary-color, #6366f1) 22%, var(--bg-card)) !important;
  box-shadow: inset 0 -3px 0 var(--primary-color, #6366f1);
}
.routine-table .td-today {
  background: color-mix(in srgb, var(--primary-color, #6366f1) 9%, var(--bg-card)) !important;
  box-shadow: inset 2px 0 0 color-mix(in srgb, var(--primary-color, #6366f1) 45%, transparent),
              inset -2px 0 0 color-mix(in srgb, var(--primary-color, #6366f1) 45%, transparent);
}
.routine-table .th-today .day-date {
  color: color-mix(in srgb, var(--primary-color, #6366f1) 55%, var(--text-primary));
  font-weight: 800;
}

.month-toolbar { display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem; flex-wrap: wrap; }
.month-title { margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-dark); flex: 1; text-align: center; }
.lunch-toggle { display: flex; align-items: center; gap: 0.35rem; font-size: 0.85rem; color: var(--text-secondary); }
.grid-channel-bar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.65rem 1.25rem;
  margin-bottom: 0.85rem;
  padding: 0.55rem 0.85rem;
  background: var(--bg-surface-muted);
  border: 1px solid var(--border-color);
  border-radius: 8px;
}
.grid-channel-label {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-secondary);
}
.grid-channel-option {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-secondary);
  cursor: pointer;
}
.grid-channel-option input {
  accent-color: #4f46e5;
}
.grid-channel-hint {
  font-size: 0.72rem;
  color: var(--text-muted);
  margin-left: auto;
}
.swap-mode-hint {
  margin: 0 0 0.85rem;
  padding: 0.45rem 0.75rem;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 8px;
  font-size: 0.8rem;
  color: #92400e;
}
.swap-cancel {
  margin-left: 0.5rem;
  border: none;
  background: transparent;
  color: #b45309;
  font-weight: 700;
  cursor: pointer;
  text-decoration: underline;
}
.exam-card--swap-pickable { cursor: pointer; }
.exam-card--swap-selected {
  outline: 2px solid #f59e0b;
  outline-offset: 1px;
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25);
}
.td-day-cell--swap-target {
  background: #fffbeb !important;
  box-shadow: inset 0 0 0 2px #fcd34d;
}
.week-block { margin-bottom: 1.75rem; width: 100%; }
.friday-off-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.4rem 0.65rem;
  margin-top: 0.35rem;
  background: color-mix(in srgb, #ef4444 10%, var(--bg-card));
  border: 1px solid color-mix(in srgb, #ef4444 30%, var(--bg-card));
  border-radius: 6px;
}
.friday-off-label {
  font-size: 0.68rem;
  font-weight: 800;
  color: #dc2626;
  letter-spacing: 0.08em;
}
.friday-off-date {
  font-size: 0.75rem;
  font-weight: 700;
  color: color-mix(in srgb, #ef4444 55%, var(--text-primary));
}
.td-out-month { background: var(--bg-surface-muted); opacity: 0.55; }
.meta-results-out { color: #0369a1; font-weight: 600; font-size: 0.8rem; }
.meta-eligibility-on { color: #b45309; font-weight: 700; font-size: 0.8rem; }
.meta-fee-on { color: #0369a1; font-weight: 700; font-size: 0.8rem; }
.lifecycle-badge { font-size: 0.5rem; padding: 0.1rem 0.35rem; border-radius: 4px; font-weight: 700; }
.lifecycle-draft { background: var(--bg-accent); color: var(--text-muted); }
.lifecycle-scheduled { background: #dbeafe; color: #1d4ed8; }
.lifecycle-awaiting-marks { background: #fef3c7; color: #b45309; }
.lifecycle-awaiting-publish { background: #ede9fe; color: #6d28d9; }
.lifecycle-results-out { background: #d1fae5; color: #047857; }
.lifecycle-completed { background: #e0e7ff; color: #4338ca; }
.lifecycle-cancelled { background: #fee2e2; color: #b91c1c; }
.card-meta { display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; font-size: 0.55rem; }
.card-status { font-size: 0.65rem; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 600; }

/* Time slot UI: exam-routine-grid.css */

/* Friday OFF */
.td-friday { background: color-mix(in srgb, #ef4444 6%, var(--bg-card)); }
.off-day-cell {
  padding: 0.5rem;
}
.off-text { font-size: 0.65rem; font-weight: 700; color: #ef4444; letter-spacing: 0.1em; }

/* Lunch */
.lunch-row .td-time { background: color-mix(in srgb, #f59e0b 12%, var(--bg-card)); }
.lunch-row td { background: color-mix(in srgb, #f59e0b 10%, var(--bg-card)); }
.td-lunch { background: color-mix(in srgb, #f59e0b 12%, var(--bg-card)) !important; }
.lunch-cell {
  padding: 0.5rem;
}
.lunch-text { font-size: 0.95rem; font-weight: 700; color: #d97706; letter-spacing: 0.15em; }

/* Compact card typography: exam-routine-grid.css */
.status-pill {
  align-self: flex-start;
  font-size: 0.45rem;
  font-weight: 700;
  padding: 0.04rem 0.2rem;
  border-radius: 3px;
  text-transform: uppercase;
  margin: 0.04rem 0 0;
  line-height: 1;
}
.batch-sep { border: none; border-top: 1px dashed #cbd5e1; margin: 0; padding: 0; height: 0; line-height: 0; }
.batch-conflict-banner {
  font-size: 0.55rem;
  font-weight: 700;
  color: #991b1b;
  background: #fee2e2;
  border: 1px solid #fca5a5;
  border-radius: 3px;
  padding: 0.08rem 0.2rem;
  margin-bottom: 0.08rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.exam-card--conflict {
  border-color: #f87171 !important;
  box-shadow: inset 0 0 0 1px #fecaca;
}
.delivery-pill {
  display: inline-block;
  font-size: 0.45rem;
  font-weight: 700;
  padding: 0.04rem 0.22rem;
  border-radius: 999px;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  margin: 0.04rem 0 0;
}
.delivery-pill.dm-online { background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd; }
.delivery-pill.dm-offline { background: #f3f4f6; color: var(--text-secondary); border: 1px solid var(--border-color); }
.delivery-pill.dm-hybrid { background: #ede9fe; color: #6d28d9; border: 1px solid #c4b5fd; }
.card-room { font-size: 0.62rem; font-weight: 600; opacity: 0.9; }
.exam-card--compact { position: relative; }
.card-delete-btn {
  position: absolute;
  top: 2px;
  right: 2px;
  width: 18px;
  height: 18px;
  border: none;
  border-radius: 4px;
  background: rgba(255,255,255,0.92);
  color: #dc2626;
  font-size: 0.95rem;
  line-height: 1;
  cursor: pointer;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.15s ease, background 0.15s ease;
  z-index: 2;
}
.exam-card--compact:hover .card-delete-btn {
  opacity: 0.9;
  pointer-events: auto;
}
.card-delete-btn:hover { background: #fee2e2; opacity: 1; }
.btn-danger-outline {
  border: 1px solid #fca5a5;
  color: #dc2626;
  background: var(--bg-card);
}
.status-pill.st-draft { background: #fde68a; color: #92400e; border: 1px solid #fcd34d; }
.status-pill.st-published { background: #6ee7b7; color: #065f46; border: 1px solid #34d399; }
.status-pill.st-completed { background: #93c5fd; color: #1e3a8a; border: 1px solid #60a5fa; }
.status-pill.st-cancelled { background: #fca5a5; color: #991b1b; border: 1px solid #f87171; }

/* Status Badges (Unicode icons) */
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
.status-draft {
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
.status-cancelled {
  background: #fee2e2;
  color: #991b1b;
  border: 1.5px solid #f87171;
}

.td-day-cell--addable {
  cursor: pointer;
  transition: background 0.15s ease;
}
.td-day-cell--addable:hover {
  background: var(--bg-surface-muted);
}
.cell-exams {
  position: relative;
  min-height: 0;
  gap: 0.08rem;
}
.cell-empty {
  padding: 0.2rem 0.25rem;
  gap: 0.08rem;
  min-height: 1.75rem;
}
.empty-dash { color: #cbd5e1; font-size: 0.85rem; }
.cell-add-hint {
  font-size: 0.62rem;
  font-weight: 600;
  color: var(--text-muted);
  opacity: 0;
  transition: opacity 0.15s ease;
}
.td-day-cell--addable:hover .cell-add-hint {
  opacity: 1;
}
.cell-add-batch-btn {
  align-self: stretch;
  margin-top: 0.05rem;
  padding: 0.1rem 0.2rem;
  border: 1px dashed #cbd5e1;
  border-radius: 4px;
  background: rgba(255, 255, 255, 0.7);
  color: #4f46e5;
  font-size: 0.58rem;
  font-weight: 700;
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.15s ease, background 0.15s ease;
}
.td-day-cell--addable:hover .cell-add-batch-btn {
  opacity: 1;
}
.cell-add-batch-btn:hover {
  background: #eef2ff;
  border-color: #a5b4fc;
}

/* ===== CARD VIEW (student-style) ===== */
.card-view-wrapper {
  padding: 0.5rem 0;
}
.card-filter-tabs {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}
.card-filter-tab {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.7rem;
  border: 1px solid var(--border-color);
  border-radius: 20px;
  background: var(--bg-card);
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.15s ease;
  font-family: inherit;
}
.card-filter-tab:hover {
  background: var(--bg-accent);
  border-color: #cbd5e1;
}
.card-filter-tab.active {
  background: #1e3a5f;
  color: #fff;
  border-color: #1e3a5f;
}
.card-filter-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.card-filter-count {
  font-size: 0.6rem;
  font-weight: 600;
  background: rgba(0,0,0,0.08);
  padding: 0.05rem 0.4rem;
  border-radius: 10px;
  margin-left: 0.1rem;
}
.card-filter-tab.active .card-filter-count {
  background: rgba(255,255,255,0.2);
  color: #fff;
}

.card-view-list {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}
.card-view-item {
  display: flex;
  gap: 0.85rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 0.75rem 0.9rem;
  transition: box-shadow 0.15s ease, transform 0.15s ease;
}
.card-view-item:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.06);
  transform: translateY(-1px);
}
.card-view-item--compact { align-items: stretch; padding: 0.6rem 0.75rem; }
.card-item-body--compact { flex: 1; display: flex; flex-direction: column; gap: 0.45rem; min-width: 0; }
.card-view-inner { flex: 1; }

.card-item-date {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-width: 52px;
  padding-right: 0.75rem;
  border-right: 1px solid var(--border-color);
  flex-shrink: 0;
}
.card-item-day {
  font-size: 0.6rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.card-item-num {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--text-dark);
  line-height: 1.2;
  margin-top: 0.05rem;
}
.card-item-month {
  font-size: 0.6rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
}

.card-item-body {
  flex: 1;
  min-width: 0;
}
.card-item-top {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 0.35rem;
}
.card-item-subject {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-dark);
}
.card-item-batch {
  font-size: 0.6rem;
  font-weight: 600;
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
  white-space: nowrap;
}

/* Card Status Badges */
.card-status {
  font-size: 0.55rem;
  font-weight: 700;
  padding: 0.1rem 0.45rem;
  border-radius: 10px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-left: auto;
}
.card-status.draft {
  background: #fef3c7;
  color: #92400e;
}
.card-status.published {
  background: #d1fae5;
  color: #065f46;
}
.card-status.completed {
  background: #dbeafe;
  color: #1e40af;
}
.card-status.cancelled {
  background: #fee2e2;
  color: #991b1b;
}

.card-item-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: 0.25rem;
}
.card-item-actions {
  display: flex;
  gap: 0.35rem;
  margin-top: 0.5rem;
  flex-wrap: wrap;
}
.btn-xs {
  padding: 0.2rem 0.5rem;
  font-size: 0.7rem;
  border-radius: 5px;
}
.card-meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.65rem;
  color: var(--text-muted);
  background: var(--bg-surface-muted);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 0.15rem 0.45rem;
  white-space: nowrap;
}
.card-meta-chip svg {
  width: 11px;
  height: 11px;
  flex-shrink: 0;
  color: var(--text-muted);
}

/* ===== EMPTY STATE ===== */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  background: var(--bg-card);
  border-radius: 10px;
  border: 1px solid var(--border-color);
  text-align: center;
}
.empty-icon { width: 40px; height: 40px; color: var(--text-muted); margin-bottom: 0.75rem; }
.empty-state h3 { font-size: 0.95rem; font-weight: 600; color: var(--text-secondary); margin: 0 0 0.2rem; }
.empty-state p { font-size: 0.8rem; color: var(--text-muted); margin: 0; }

/* ===== LOADING ===== */
.loading-overlay {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2.5rem;
  gap: 0.75rem;
  color: var(--text-muted);
  font-size: 0.8rem;
}
.loading-spinner {
  width: 32px; height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #3B82F6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ===== TOAST ===== */
.toast {
  position: fixed;
  bottom: 2rem;
  right: 2rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem 1.25rem;
  background: #1e293b;
  color: #fff;
  border-radius: 10px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.2);
  z-index: 9999;
  font-size: 0.85rem;
  font-weight: 500;
  min-width: 260px;
  max-width: 400px;
}
.toast-icon { width: 18px; height: 18px; color: #22c55e; flex-shrink: 0; }
.toast-text { flex: 1; line-height: 1.4; }
.toast-close {
  background: none;
  border: none;
  color: var(--text-muted);
  font-size: 1.2rem;
  cursor: pointer;
  padding: 0 0.2rem;
  line-height: 1;
  transition: color 0.2s;
}
.toast-close:hover { color: #fff; }
.toast-slide-enter-active { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.toast-slide-leave-active { transition: all 0.3s ease-in; }
.toast-slide-enter-from { transform: translateX(100%); opacity: 0; }
.toast-slide-leave-to { transform: translateX(100%); opacity: 0; }

/* ===== EXPORT DROPDOWN ===== */
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
  border-radius: 8px;
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
  color: var(--text-secondary);
  cursor: pointer;
  transition: background 0.15s;
  font-family: inherit;
  text-align: left;
}
.export-option:hover {
  background: var(--bg-accent);
}
.ico-sm {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
  color: var(--text-muted);
}

/* ===== PRINT STYLES ===== */
@media print {
  /* Hide layout chrome outside this component */
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
  .erp-exam-routine {
    padding: 0.3in;
    max-width: 100%;
    margin: 0 auto;
    box-shadow: none;
    border: none;
  }
  .no-print { display: none !important; }
  .exam-header { break-inside: avoid; }
  .routine-grid-wrapper {
    break-inside: avoid;
    box-shadow: none;
    border: 1px solid var(--border-color);
  }
  .exam-card:hover { box-shadow: none; }
  .card-view-item:hover { box-shadow: none; transform: none; }
  .card-filter-tabs { display: none !important; }
  .toast { display: none !important; }
  .export-dropdown { display: none !important; }
  .filter-panel { display: none !important; }
  .loading-overlay { display: none !important; }
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
  .action-row { flex-direction: column; align-items: stretch; }
  .action-left, .action-right { justify-content: center; }
}
@media (max-width: 768px) {
  .erp-exam-routine { padding: 1rem; }
  .card-view-item { flex-direction: column; gap: 0.5rem; }
  .card-item-date {
    flex-direction: row;
    gap: 0.4rem;
    min-width: auto;
    padding-right: 0;
    padding-bottom: 0.5rem;
    border-right: none;
    border-bottom: 1px solid var(--border-color);
    justify-content: flex-start;
  }
  .card-item-day { font-size: 0.65rem; }
  .card-item-num { font-size: 1rem; }
  .card-item-month { font-size: 0.65rem; }
  .card-item-top { flex-wrap: wrap; }
  .card-status { margin-left: 0; }
}

</style>

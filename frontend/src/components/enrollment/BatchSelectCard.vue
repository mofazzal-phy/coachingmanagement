<template>
  <div class="batch-select-card" :class="{ compact, embedded }">
    <h3 v-if="!compact" class="step-title">
      <span class="step-badge">3</span>
      Select Batch
    </h3>

    <!-- Mode Tabs -->
    <div class="mode-tabs" v-if="batches.length > 0">
      <button
        v-for="mode in modes"
        :key="mode.key"
        class="mode-tab"
        :class="{ active: activeMode === mode.key }"
        @click="activeMode = mode.key"
      >
        {{ mode.icon }} {{ mode.label }}
        <span class="mode-count">{{ modeCounts[mode.key] || 0 }}</span>
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading batches...</p>
    </div>

    <!-- No Course Selected -->
    <div v-else-if="!courseId" class="empty-state">
      <p class="text-muted">👈 Select a course first</p>
    </div>

    <!-- No Batches -->
    <div v-else-if="batches.length === 0" class="empty-state">
      <div class="empty-icon">📦</div>
      <p>No batches available for this course.</p>
    </div>

    <!-- Compact list -->
    <div v-else-if="compact" class="batch-list-compact">
      <div
        v-for="batch in filteredBatches"
        :key="batch.id"
        class="batch-row"
        :class="{
          selected: selectedBatch?.id === batch.id,
          full: batch.is_full,
          upcoming: batch.status === 'upcoming',
        }"
        @click="selectBatch(batch)"
      >
        <div class="row-accent" :class="batch.mode">
          {{ batch.mode === 'online' ? '🖥' : batch.mode === 'offline' ? '🏫' : '🔄' }}
        </div>
        <div class="row-content">
          <div class="row-top">
            <span class="row-name">{{ batch.name }}</span>
            <span class="row-code">{{ batch.code }}</span>
            <span :class="['mode-pill', batch.mode]">{{ batch.mode }}</span>
            <span v-if="batch.is_full" class="status-pill full">🔴 Full</span>
            <span v-else-if="batch.seat_percentage >= 80" class="status-pill warn">🟡 {{ batch.available_seats }} left</span>
            <span v-else class="status-pill ok">🟢 Available</span>
          </div>
          <div class="row-bottom">
            <span class="meta-pill schedule">📅 {{ batch.days?.join(', ') || 'TBA' }}</span>
            <span class="meta-pill time">⏰ {{ batch.start_time || '?' }} – {{ batch.end_time || '?' }}</span>
            <span v-if="batch.teacher" class="meta-pill teacher">👨‍🏫 {{ batch.teacher.first_name }} {{ batch.teacher.last_name }}</span>
          </div>
          <div class="seat-row">
            <div class="seat-bar">
              <div
                class="seat-fill"
                :class="{ danger: batch.seat_percentage >= 90, warning: batch.seat_percentage >= 70 && batch.seat_percentage < 90 }"
                :style="{ width: (batch.seat_percentage || 0) + '%' }"
              ></div>
            </div>
            <span class="seat-label">🪑 {{ batch.enrolled_count }}/{{ batch.capacity }}</span>
          </div>
        </div>
        <div class="row-actions">
          <button v-if="batch.is_full" type="button" class="btn-waitlist" @click.stop="joinWaitlist(batch)">Waitlist</button>
          <span v-else class="select-ring" :class="{ on: selectedBatch?.id === batch.id }">
            {{ selectedBatch?.id === batch.id ? '✓' : '' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Batch Cards -->
    <div v-else class="batch-list">
      <div
        v-for="batch in filteredBatches"
        :key="batch.id"
        class="batch-card"
        :class="{
          selected: selectedBatch?.id === batch.id,
          full: batch.is_full,
          upcoming: batch.status === 'upcoming',
        }"
        @click="selectBatch(batch)"
      >
        <!-- Mode Badge -->
        <span :class="['mode-badge', batch.mode]">
          {{ batch.mode === 'online' ? '🖥 Online' : batch.mode === 'offline' ? '🏫 Offline' : '🔄 Hybrid' }}
        </span>

        <!-- Capacity Badge -->
        <span v-if="batch.is_full" class="full-badge">🔴 Full</span>
        <span v-else-if="batch.seat_percentage >= 80" class="warning-badge">🟡 {{ batch.available_seats }} left</span>

        <div class="batch-body">
          <h4 class="batch-name">{{ batch.name }}</h4>
          <span class="batch-code">{{ batch.code }}</span>

          <!-- Schedule -->
          <div class="batch-schedule">
            <div class="schedule-item">
              <span class="s-label">📅 Days:</span>
              <span>{{ batch.days?.join(', ') || 'TBA' }}</span>
            </div>
            <div class="schedule-item">
              <span class="s-label">⏰ Time:</span>
              <span>{{ batch.start_time || '?' }} – {{ batch.end_time || '?' }}</span>
            </div>
          </div>

          <!-- Teacher -->
          <div v-if="batch.teacher" class="batch-teacher">
            👨‍🏫 {{ batch.teacher.first_name }} {{ batch.teacher.last_name }}
          </div>

          <!-- Room / Platform -->
          <div class="batch-location">
            <span v-if="batch.room">🏢 {{ batch.room.name }}</span>
            <span v-else-if="batch.campus_location">📍 {{ batch.campus_location }}</span>
            <span v-if="batch.platform" class="platform">| 💻 {{ batch.platform }}</span>
          </div>

          <!-- Seat Bar -->
          <div class="seat-bar-container">
            <div class="seat-bar">
              <div
                class="seat-fill"
                :class="{ danger: batch.seat_percentage >= 90, warning: batch.seat_percentage >= 70 }"
                :style="{ width: batch.seat_percentage + '%' }"
              ></div>
            </div>
            <span class="seat-text">{{ batch.enrolled_count }}/{{ batch.capacity }} seats</span>
          </div>
        </div>

        <!-- Select / Waitlist footer -->
        <div class="batch-footer">
          <template v-if="batch.is_full">
            <button class="btn btn-warning btn-sm" @click.stop="joinWaitlist(batch)">
              ⏳ Join Waitlist
            </button>
          </template>
          <template v-else>
            <span class="select-indicator">
              {{ selectedBatch?.id === batch.id ? '✓ Selected' : 'Click to select' }}
            </span>
          </template>
        </div>
      </div>
    </div>

    <!-- Waitlist Modal (simple inline) -->
    <div v-if="showWaitlistConfirm" class="waitlist-confirm">
      <p><strong>Join waitlist for {{ waitlistBatch?.name }}?</strong></p>
      <p class="text-muted">You'll be notified when a seat becomes available.</p>
      <button class="btn btn-primary btn-sm" @click="confirmWaitlist">Yes, Join</button>
      <button class="btn btn-secondary btn-sm" @click="showWaitlistConfirm = false">Cancel</button>
    </div>

    <div v-if="errorMsg" class="alert alert-danger mt-2">{{ errorMsg }}</div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import enrollmentService from '@/services/enrollment.service'

const props = defineProps({
  courseId: { type: String, default: null },
  studentId: { type: String, default: null },
  compact: { type: Boolean, default: false },
  embedded: { type: Boolean, default: false },
})
const emit = defineEmits(['batch-selected', 'waitlist-requested'])

const batches = ref([])
const selectedBatch = ref(null)
const loading = ref(false)
const errorMsg = ref('')
const activeMode = ref('all')
const showWaitlistConfirm = ref(false)
const waitlistBatch = ref(null)

const modes = [
  { key: 'all', label: 'All', icon: '📋' },
  { key: 'online', label: 'Online', icon: '🖥' },
  { key: 'offline', label: 'Offline', icon: '🏫' },
  { key: 'hybrid', label: 'Hybrid', icon: '🔄' },
]

const modeCounts = computed(() => {
  const counts = { all: batches.value.length }
  for (const b of batches.value) {
    counts[b.mode] = (counts[b.mode] || 0) + 1
  }
  return counts
})

const filteredBatches = computed(() => {
  if (activeMode.value === 'all') return batches.value
  return batches.value.filter(b => b.mode === activeMode.value)
})

const selectBatch = (batch) => {
  if (batch.is_full) return
  selectedBatch.value = batch
  emit('batch-selected', batch)
}

const joinWaitlist = (batch) => {
  waitlistBatch.value = batch
  showWaitlistConfirm.value = true
}

const confirmWaitlist = () => {
  emit('waitlist-requested', waitlistBatch.value)
  showWaitlistConfirm.value = false
}

const loadBatches = async () => {
  if (!props.courseId) {
    batches.value = []
    return
  }
  loading.value = true
  errorMsg.value = ''
  selectedBatch.value = null
  try {
    const res = await enrollmentService.getSuggestedBatches(props.courseId)
    let data = res.data?.data || res.data || []
    batches.value = Array.isArray(data) ? data : []
    console.log(`[BatchSelectCard] Loaded ${batches.value.length} batches for course ${props.courseId}`)

    // Fallback: if suggested-batches returns empty, try getBatchesByCourse
    if (batches.value.length === 0) {
      console.log('[BatchSelectCard] No batches from suggested, trying getBatchesByCourse fallback...')
      try {
        const fallbackRes = await enrollmentService.getBatchesByCourse(props.courseId)
        const fallbackData = fallbackRes.data?.data || fallbackRes.data || []
        batches.value = Array.isArray(fallbackData) ? fallbackData : []
        console.log(`[BatchSelectCard] Fallback loaded ${batches.value.length} batches`)
      } catch (fallbackErr) {
        console.warn('[BatchSelectCard] Fallback also failed:', fallbackErr)
      }
    }
  } catch (e) {
    console.error('[BatchSelectCard] Failed to load batches:', e)
    errorMsg.value = 'Failed to load batches'
  } finally {
    loading.value = false
  }
}

watch(() => props.courseId, (newId) => {
  loadBatches()
}, { immediate: true })
</script>

<style scoped>
.batch-select-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.batch-select-card.embedded {
  background: transparent;
  padding: 0;
  box-shadow: none;
  border-radius: 0;
}
.batch-select-card.compact .mode-tabs { margin-bottom: 0.75rem; }
.batch-select-card.compact .mode-tab {
  padding: 0.4rem 0.85rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text-secondary);
  border-color: #cbd5e1;
  background: var(--bg-card);
}
.batch-select-card.compact .mode-tab.active {
  background: #0891b2;
  border-color: #0891b2;
  color: #fff;
  box-shadow: 0 2px 8px rgba(8, 145, 178, 0.3);
}

.batch-list-compact {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-height: 340px;
  overflow-y: auto;
}
.batch-row {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.65rem 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.18s;
  background: var(--bg-card);
}
.batch-row:hover:not(.full) {
  border-color: #22d3ee;
  background: #f0fdfa;
  box-shadow: 0 3px 10px rgba(8, 145, 178, 0.12);
}
.batch-row.selected {
  border-color: #0891b2;
  background: linear-gradient(135deg, #f0fdfa 0%, #ecfeff 100%);
  box-shadow: 0 4px 14px rgba(8, 145, 178, 0.2);
}
.batch-row.full {
  background: #fef2f2;
  border-color: #fecaca;
  cursor: default;
}
.batch-row.upcoming { background: #fffbeb; border-color: #fde68a; }
.row-accent {
  width: 40px; height: 40px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 10px;
  font-size: 1.1rem;
  flex-shrink: 0;
}
.row-accent.online { background: #cffafe; border: 1px solid #67e8f9; }
.row-accent.offline { background: #d1fae5; border: 1px solid #6ee7b7; }
.row-accent.hybrid { background: #ede9fe; border: 1px solid #c4b5fd; }
.row-content { flex: 1; min-width: 0; }
.row-top { display: flex; align-items: center; flex-wrap: wrap; gap: 0.35rem 0.5rem; }
.row-name { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); }
.row-code {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-secondary);
  font-family: ui-monospace, monospace;
  padding: 0.1rem 0.4rem;
  background: var(--bg-accent);
  border-radius: 4px;
}
.mode-pill {
  padding: 0.15rem 0.45rem;
  border-radius: 6px;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: capitalize;
  border: 1px solid transparent;
}
.mode-pill.online { background: #cffafe; color: #0e7490; border-color: #67e8f9; }
.mode-pill.offline { background: #d1fae5; color: #047857; border-color: #6ee7b7; }
.mode-pill.hybrid { background: #ede9fe; color: #6d28d9; border-color: #c4b5fd; }
.status-pill { font-size: 0.68rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 6px; border: 1px solid transparent; }
.status-pill.full { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
.status-pill.warn { background: #fef3c7; color: #b45309; border-color: #fcd34d; }
.status-pill.ok { background: #d1fae5; color: #047857; border-color: #6ee7b7; }
.row-bottom { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.4rem; }
.meta-pill {
  padding: 0.18rem 0.5rem;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  border: 1px solid transparent;
}
.meta-pill.schedule { background: #fef3c7; color: #92400e; border-color: #fde68a; }
.meta-pill.time { background: #fce7f3; color: #9d174d; border-color: #fbcfe8; }
.meta-pill.teacher { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
.seat-row { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.45rem; }
.seat-bar { flex: 1; height: 7px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
.seat-fill { height: 100%; background: #10b981; border-radius: 4px; transition: width 0.3s; }
.seat-fill.warning { background: #f59e0b; }
.seat-fill.danger { background: #ef4444; }
.seat-label { font-size: 0.72rem; font-weight: 700; color: var(--text-secondary); white-space: nowrap; }
.row-actions { display: flex; align-items: center; flex-shrink: 0; }
.select-ring {
  width: 26px; height: 26px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 50%;
  border: 2px solid #cbd5e1;
  color: transparent;
  font-weight: 700;
  font-size: 0.8rem;
  transition: all 0.15s;
}
.select-ring.on {
  background: #0891b2;
  border-color: #0891b2;
  color: #fff;
  box-shadow: 0 2px 6px rgba(8, 145, 178, 0.4);
}
.btn-waitlist {
  padding: 0.3rem 0.6rem;
  font-size: 0.72rem;
  font-weight: 700;
  border: 1px solid #f59e0b;
  background: #fffbeb;
  color: #b45309;
  border-radius: 8px;
  cursor: pointer;
}
.btn-waitlist:hover { background: #f59e0b; color: #fff; }

.step-title { font-size: 1.1rem; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem; }
.step-badge { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #4a90d9; color: white; font-size: 0.85rem; font-weight: 700; }

.mode-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.mode-tab {
  padding: 0.4rem 0.9rem;
  border: 1px solid #e0e0e0;
  border-radius: 16px;
  background: #f9f9f9;
  cursor: pointer;
  font-size: 0.8rem;
  transition: all 0.2s;
}
.mode-tab:hover { border-color: #4a90d9; }
.mode-tab.active { background: #4a90d9; color: white; border-color: #4a90d9; }
.mode-count { font-size: 0.65rem; background: rgba(0,0,0,0.1); padding: 0.1rem 0.35rem; border-radius: 8px; margin-left: 0.25rem; }

.batch-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 0.75rem; }

.batch-card {
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  padding: 1rem;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
}
.batch-card:hover:not(.full) { border-color: #4a90d9; box-shadow: 0 2px 10px rgba(74,144,217,0.1); }
.batch-card.selected { border-color: #4a90d9; background: #f8faff; box-shadow: 0 4px 16px rgba(74,144,217,0.2); }
.batch-card.full { opacity: 0.75; cursor: default; background: #fdf2f2; border-color: #f5c6cb; }
.batch-card.upcoming { background: #fffbeb; border-color: #fde68a; }

.mode-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  padding: 0.15rem 0.5rem;
  border-radius: 10px;
  font-size: 0.7rem;
  font-weight: 600;
}
.mode-badge.online { background: #eef4ff; color: #4a90d9; }
.mode-badge.offline { background: #eafaf1; color: #27ae60; }
.mode-badge.hybrid { background: #fff3cd; color: #f39c12; }

.full-badge { position: absolute; top: 8px; left: 8px; padding: 0.15rem 0.5rem; border-radius: 8px; font-size: 0.7rem; font-weight: 600; background: #fdeaea; color: #e74c3c; }
.warning-badge { position: absolute; top: 8px; left: 8px; padding: 0.15rem 0.5rem; border-radius: 8px; font-size: 0.7rem; font-weight: 600; background: #fff8e1; color: #e67e22; }

.batch-body { margin-top: 0.25rem; }
.batch-name { margin: 0; font-size: 0.95rem; }
.batch-code { font-size: 0.75rem; color: var(--text-muted); font-family: monospace; }

.batch-schedule { margin-top: 0.5rem; font-size: 0.8rem; }
.schedule-item { display: flex; gap: 0.3rem; }
.s-label { color: var(--text-muted); min-width: 60px; }

.batch-teacher { font-size: 0.8rem; color: #4a90d9; margin-top: 0.3rem; }
.batch-location { font-size: 0.78rem; color: #777; margin-top: 0.25rem; }
.platform { margin-left: 0.5rem; color: #4a90d9; }

.seat-bar-container { margin-top: 0.6rem; display: flex; align-items: center; gap: 0.5rem; }
.seat-bar { flex: 1; height: 6px; background: #eee; border-radius: 3px; overflow: hidden; }
.seat-fill { height: 100%; background: #2ecc71; border-radius: 3px; transition: width 0.3s; }
.seat-fill.warning { background: #f39c12; }
.seat-fill.danger { background: #e74c3c; }
.seat-text { font-size: 0.7rem; color: #888; white-space: nowrap; }

.batch-footer { text-align: center; margin-top: 0.75rem; padding-top: 0.5rem; border-top: 1px solid var(--border-light); }
.select-indicator { font-size: 0.8rem; }
.batch-card.selected .select-indicator { color: #4a90d9; font-weight: 600; }

.waitlist-confirm {
  margin-top: 1rem;
  padding: 1rem;
  background: #fff8e1;
  border: 1px solid #fde68a;
  border-radius: 10px;
  text-align: center;
}

.loading-state, .empty-state { text-align: center; padding: 1.5rem; }
.spinner { width: 32px; height: 32px; border: 3px solid #eee; border-top-color: #4a90d9; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 0.5rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.empty-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
.alert-danger { background: #fdeaea; color: #e74c3c; padding: 0.75rem; border-radius: 8px; font-size: 0.85rem; }
.text-muted { color: #888; }
.mt-2 { margin-top: 0.5rem; }

.btn-warning { background: #f39c12; color: white; border: none; border-radius: 6px; cursor: pointer; }
.btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; }
</style>

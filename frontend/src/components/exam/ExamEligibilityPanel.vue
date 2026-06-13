<template>

  <div class="eligibility-panel" v-if="examId">

    <div class="panel-bar">

      <span class="panel-title">{{ panelTitle }}</span>

      <button class="btn btn-sm btn-primary" :disabled="savingSettings" @click="saveSettings">

        {{ savingSettings ? 'Saving...' : 'Save' }}

      </button>

    </div>



    <div v-if="loadError" class="alert alert-danger">{{ loadError }}</div>

    <div v-if="saveMsg" class="alert alert-success">{{ saveMsg }}</div>



    <p v-if="isOnlineChannel" class="channel-hint">

      These rules apply only to the <strong>online exam player</strong>. Offline admit cards use separate settings on the Offline grid.

    </p>



    <div class="scope-row">

      <span class="scope-label">Apply rules to:</span>

      <label class="scope-option">

        <input v-model="scopeMode" type="radio" value="all" :disabled="savingSettings" />

        <span>All batches (same for every student)</span>

      </label>

      <label class="scope-option">

        <input v-model="scopeMode" type="radio" value="batch" :disabled="savingSettings" />

        <span>Batch-wise (per batch)</span>

      </label>

    </div>



    <!-- All batches mode -->

    <template v-if="scopeMode === 'all'">

      <div class="rules-row">

        <label class="rule-chip">

          <input type="checkbox" v-model="settings.enabled" :disabled="savingSettings" />

          <span>Attendance check</span>

        </label>

        <input

          v-model.number="settings.minPercent"

          type="number"

          min="0"

          max="100"

          step="0.1"

          class="form-control pct-input"

          placeholder="Min %"

          :disabled="!settings.enabled || savingSettings"

        />

        <label class="rule-chip">

          <input type="checkbox" v-model="settings.examFeeApplicable" :disabled="savingSettings" />

          <span>{{ isOnlineChannel ? 'Exam fee required (unlock player)' : 'Exam fee required' }}</span>

        </label>

      </div>



      <div class="publish-row">

        <span class="publish-label">Routine publish:</span>

        <span class="publish-stat">{{ allBatchPublishSummary }}</span>

        <button

          type="button"

          class="btn btn-xs btn-success"

          :disabled="publishingAll || !hasDraftRoutines"

          @click="publishAllBatches"

        >

          {{ publishingAll ? 'Publishing...' : 'Publish all batches' }}

        </button>

      </div>



      <div v-if="settings.enabled" class="summary-inline">

        <span class="pill sum-eligible">{{ summary.eligible }} OK</span>

        <span class="pill sum-warning">{{ summary.warning }} warn</span>

        <span class="pill sum-blocked">{{ summary.blocked }} blocked</span>

        <span class="pill sum-overridden">{{ summary.overridden }} override</span>

        <button type="button" class="btn btn-xs btn-outline" :disabled="loading" @click="toggleAttendanceList">

          {{ showAttendanceList ? 'Hide list' : 'Students' }}

        </button>

        <button class="btn btn-xs btn-outline" :disabled="loading" @click="syncAll">

          {{ loading ? '...' : 'Recalc' }}

        </button>

      </div>



      <div v-if="settings.enabled && showAttendanceList" class="list-wrap">

        <div v-if="loading" class="muted">Loading...</div>

        <table v-else-if="rows.length" class="data-table compact">

          <thead>

            <tr>

              <th>Student</th>

              <th>%</th>

              <th>Status</th>

              <th></th>

            </tr>

          </thead>

          <tbody>

            <tr v-for="row in rows" :key="row.student_id">

              <td>{{ row.student?.name || '—' }}</td>

              <td>{{ formatPct(row.attendance_percent) }}</td>

              <td><span class="elig-badge" :class="'elig-' + (row.status || 'eligible')">{{ statusLabel(row) }}</span></td>

              <td>

                <button

                  v-if="row.status === 'blocked' || row.status === 'warning'"

                  class="btn btn-xs btn-outline"

                  @click="openOverride(row)"

                >Override</button>

              </td>

            </tr>

          </tbody>

        </table>

        <p v-else class="muted">No students in scope.</p>

      </div>

    </template>



    <!-- Batch-wise mode -->

    <template v-else>

      <p v-if="!batchRows.length" class="muted">No batches with {{ isOnlineChannel ? 'online' : 'offline' }} routines yet. Create routines first.</p>

      <div v-else class="batch-table-wrap">

        <table class="data-table batch-table">

          <thead>

            <tr>

              <th>Batch</th>

              <th>Routines</th>

              <th>Attendance</th>

              <th>Min %</th>

              <th>Exam fee</th>

              <th>Publish</th>

              <th>Attendance status</th>

            </tr>

          </thead>

          <tbody>

            <tr v-for="row in batchRows" :key="row.batch_id">

              <td><strong>{{ row.batch_name }}</strong></td>

              <td>

                <span class="routine-stat">{{ row.published_count }}/{{ row.total_routines }} published</span>

                <span v-if="row.draft_count" class="draft-badge">{{ row.draft_count }} draft</span>

              </td>

              <td>

                <input

                  v-model="row.eligibility_check_enabled"

                  type="checkbox"

                  :disabled="savingSettings"

                />

              </td>

              <td>

                <input

                  v-model.number="row.min_attendance_percent"

                  type="number"

                  min="0"

                  max="100"

                  step="0.1"

                  class="form-control pct-input"

                  :disabled="!row.eligibility_check_enabled || savingSettings"

                />

              </td>

              <td>

                <input

                  v-model="row.exam_fee_applicable"

                  type="checkbox"

                  :disabled="savingSettings"

                />

              </td>

              <td>

                <button

                  type="button"

                  class="btn btn-xs btn-success"

                  :disabled="publishingBatchId === row.batch_id || row.draft_count === 0"

                  @click="publishBatch(row)"

                >

                  {{ publishingBatchId === row.batch_id ? '...' : (row.is_fully_published ? 'Republish' : 'Publish') }}

                </button>

              </td>

              <td class="batch-status-cell">
                <template v-if="row.eligibility_check_enabled">
                  <div v-if="row.summary" class="batch-summary-pills">
                    <span class="pill sum-eligible">{{ row.summary.eligible }} OK</span>
                    <span class="pill sum-warning">{{ row.summary.warning }} warn</span>
                    <span class="pill sum-blocked">{{ row.summary.blocked }} blocked</span>
                  </div>
                  <div class="batch-status-actions">
                    <button type="button" class="btn btn-xs btn-outline" @click="toggleBatchStudents(row)">
                      {{ activeBatchId === row.batch_id && showBatchStudents ? 'Hide' : 'Students' }}
                    </button>
                    <button type="button" class="btn btn-xs btn-outline" :disabled="loading" @click="syncBatchAttendance(row)">
                      {{ loading && activeBatchId === row.batch_id ? '...' : 'Recalc' }}
                    </button>
                  </div>
                </template>
                <span v-else class="muted">Off</span>
              </td>

            </tr>

          </tbody>

        </table>

        <div v-if="showBatchStudents && activeBatchId" class="list-wrap">
          <p class="batch-list-title">{{ activeBatchName }} — students</p>
          <div v-if="loading" class="muted">Loading...</div>
          <table v-else-if="batchStudentRows.length" class="data-table compact">
            <thead>
              <tr>
                <th>Student</th>
                <th>%</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in batchStudentRows" :key="row.student_id">
                <td>{{ row.student?.name || '—' }}</td>
                <td>{{ formatPct(row.attendance_percent) }}</td>
                <td><span class="elig-badge" :class="'elig-' + (row.status || 'eligible')">{{ statusLabel(row) }}</span></td>
                <td>
                  <button
                    v-if="row.status === 'blocked' || row.status === 'warning'"
                    class="btn btn-xs btn-outline"
                    @click="openOverride(row)"
                  >Override</button>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-else class="muted">No students in this batch.</p>
        </div>

      </div>

    </template>



    <div v-if="showOverride" class="modal-overlay" @click.self="showOverride = false">

      <div class="modal-dialog modal-sm">

        <div class="modal-header">

          <h3>Override</h3>

          <button class="modal-close" @click="showOverride = false">✕</button>

        </div>

        <div class="modal-body">

          <p><strong>{{ overrideTarget?.student?.name }}</strong></p>

          <textarea v-model="overrideReason" class="form-control" rows="3" placeholder="Reason (required)" />

          <div v-if="overrideError" class="alert alert-danger">{{ overrideError }}</div>

        </div>

        <div class="modal-footer">

          <button class="btn btn-outline" @click="showOverride = false">Cancel</button>

          <button class="btn btn-primary" :disabled="overrideLoading || !overrideReason.trim()" @click="submitOverride">

            {{ overrideLoading ? 'Saving...' : 'Save' }}

          </button>

        </div>

      </div>

    </div>

  </div>

</template>



<script setup>

import { ref, watch, onMounted, computed } from 'vue'

import examService from '@/services/exam.service'

import smartFeeService from '@/services/smart-fee.service'

import { extractData } from '@/utils/api.utils'



const props = defineProps({

  examId: { type: String, default: '' },

  deliveryChannel: { type: String, default: 'offline' },

  gridMonth: { type: [Number, String], default: null },

  gridYear: { type: [Number, String], default: null },

})



const emit = defineEmits(['updated', 'published'])



const panelTitle = computed(() =>

  props.deliveryChannel === 'online' ? 'Online exam access & publish' : 'Offline admit rules & publish',

)



const isOnlineChannel = computed(() => props.deliveryChannel === 'online')



const scopeMode = ref('all')

const settings = ref({ enabled: false, minPercent: null, examFeeApplicable: false })

const prevExamFeeApplicable = ref(false)

const batchRows = ref([])

const dispatchMsg = ref('')

const rows = ref([])

const meta = ref({})

const loading = ref(false)

const loadError = ref('')

const savingSettings = ref(false)

const saveMsg = ref('')

const showAttendanceList = ref(false)

const listLoaded = ref(false)

const showOverride = ref(false)

const overrideTarget = ref(null)

const overrideReason = ref('')

const overrideLoading = ref(false)

const overrideError = ref('')

const publishingAll = ref(false)

const publishingBatchId = ref('')

const prevBatchFeeState = ref({})

const activeBatchId = ref('')

const activeBatchName = ref('')

const showBatchStudents = ref(false)

const batchStudentRows = ref([])



function summarizeFromRows(list) {

  const counts = { eligible: 0, warning: 0, blocked: 0, overridden: 0 }

  for (const row of list) {

    if (row.is_override || row.status === 'overridden') {

      counts.overridden++

      continue

    }

    const s = String(row.status || 'eligible').toLowerCase()

    if (s === 'blocked' || s === 'not_eligible') counts.blocked++

    else if (s === 'warning') counts.warning++

    else counts.eligible++

  }

  return counts

}



const summary = computed(() => {

  const fromApi = meta.value.summary

  if (fromApi) {

    return {

      eligible: fromApi.eligible ?? 0,

      warning: fromApi.warning ?? 0,

      blocked: fromApi.blocked ?? 0,

      overridden: fromApi.overridden ?? 0,

    }

  }

  return summarizeFromRows(rows.value)

})



const allBatchPublishSummary = computed(() => {

  const total = batchRows.value.reduce((n, r) => n + (r.total_routines || 0), 0)

  const published = batchRows.value.reduce((n, r) => n + (r.published_count || 0), 0)

  const draft = batchRows.value.reduce((n, r) => n + (r.draft_count || 0), 0)

  return `${published}/${total} published${draft ? ` · ${draft} draft` : ''}`

})



const hasDraftRoutines = computed(() => batchRows.value.some(r => (r.draft_count || 0) > 0))



function emitChannelMeta() {

  if (scopeMode.value === 'all') {

    emit('updated', {

      scope: 'all',

      eligibilityEnabled: !!settings.value.enabled,

      examFeeEnabled: !!settings.value.examFeeApplicable,

    })

    return

  }

  emit('updated', {

    scope: 'batch',

    eligibilityEnabled: batchRows.value.some(r => r.eligibility_check_enabled),

    examFeeEnabled: batchRows.value.some(r => r.exam_fee_applicable),

  })

}



async function loadChannelPolicies() {

  if (!props.examId) return

  const res = await examService.exams.channelPolicies(props.examId, {

    delivery_channel: props.deliveryChannel,

  })

  const data = extractData(res, {})

  scopeMode.value = data.policy_scope === 'batch' ? 'batch' : 'all'

  const global = data.global || {}

  settings.value.enabled = !!global.eligibility_check_enabled

  settings.value.minPercent = global.min_attendance_percent ?? null

  settings.value.examFeeApplicable = !!global.exam_fee_applicable

  prevExamFeeApplicable.value = !!global.exam_fee_applicable

  batchRows.value = (data.batches || []).map(b => ({
    ...b,
    summary: b.summary || null,
  }))

  prevBatchFeeState.value = Object.fromEntries(
    batchRows.value.map(r => [r.batch_id, !!r.exam_fee_applicable]),
  )

  emitChannelMeta()

}



async function loadSummary() {

  if (!props.examId || !settings.value.enabled || scopeMode.value !== 'all') {

    meta.value = {}

    return

  }

  try {

    const res = await examService.exams.eligibilitySummary(props.examId, {

      delivery_channel: props.deliveryChannel,

    })

    const data = extractData(res, {})

    meta.value = {

      summary: data.summary || { eligible: 0, warning: 0, blocked: 0, overridden: 0 },

    }

  } catch (e) {

    loadError.value = e.response?.data?.message || 'Failed to load summary'

  }

}



async function loadEligibility(refresh = false) {

  if (!props.examId || !settings.value.enabled || scopeMode.value !== 'all') {

    rows.value = []

    return

  }

  loading.value = true

  loadError.value = ''

  try {

    const res = await examService.exams.eligibility(props.examId, {

      refresh: refresh ? 1 : 0,

      delivery_channel: props.deliveryChannel,

    })

    const data = extractData(res, {})

    rows.value = data.students || []

    meta.value = {

      summary: data.summary || summarizeFromRows(rows.value),

    }

    listLoaded.value = true

  } catch (e) {

    loadError.value = e.response?.data?.message || 'Failed to load list'

  } finally {

    loading.value = false

  }

}



async function toggleAttendanceList() {

  showAttendanceList.value = !showAttendanceList.value

  if (showAttendanceList.value && !listLoaded.value) {

    await loadEligibility(false)

  }

}



function publishParams(batchId = null) {

  const params = { delivery_channel: props.deliveryChannel }

  if (batchId) params.batch_id = batchId

  if (props.gridMonth) params.month = props.gridMonth

  if (props.gridYear) params.year = props.gridYear

  return params

}



async function publishAllBatches() {

  if (!props.examId) return

  if (!confirm(`Publish all ${props.deliveryChannel} routines for all batches?`)) return

  publishingAll.value = true

  loadError.value = ''

  try {

    await examService.routines.publish(props.examId, publishParams())

    await loadChannelPolicies()

    emit('published')

    saveMsg.value = 'All batch routines published.'

  } catch (e) {

    loadError.value = e.response?.data?.message || 'Publish failed'

  } finally {

    publishingAll.value = false

  }

}



async function publishBatch(row) {

  if (!props.examId || !row?.batch_id) return

  if (!confirm(`Publish ${props.deliveryChannel} routines for batch "${row.batch_name}"?`)) return

  publishingBatchId.value = row.batch_id

  loadError.value = ''

  try {

    await examService.routines.publish(props.examId, publishParams(row.batch_id))

    await loadChannelPolicies()

    emit('published')

    saveMsg.value = `Published routines for ${row.batch_name}.`

  } catch (e) {

    loadError.value = e.response?.data?.message || 'Publish failed'

  } finally {

    publishingBatchId.value = ''

  }

}



async function saveSettings() {

  if (!props.examId) return

  savingSettings.value = true

  saveMsg.value = ''

  dispatchMsg.value = ''

  loadError.value = ''

  try {

    const turningOnExamFee = scopeMode.value === 'all'

      && settings.value.examFeeApplicable

      && !prevExamFeeApplicable.value



    const payload = {

      delivery_channel: props.deliveryChannel,

      policy_scope: scopeMode.value,

    }



    if (scopeMode.value === 'all') {

      payload.global = {

        eligibility_check_enabled: settings.value.enabled,

        min_attendance_percent: settings.value.minPercent === '' || settings.value.minPercent == null

          ? null

          : Number(settings.value.minPercent),

        exam_fee_applicable: settings.value.examFeeApplicable,

      }

    } else {

      payload.batches = batchRows.value.map(row => ({

        batch_id: row.batch_id,

        eligibility_check_enabled: !!row.eligibility_check_enabled,

        min_attendance_percent: row.min_attendance_percent === '' || row.min_attendance_percent == null

          ? null

          : Number(row.min_attendance_percent),

        exam_fee_applicable: !!row.exam_fee_applicable,

      }))

    }



    await examService.exams.saveChannelPolicies(props.examId, payload)

    const postSaveMessages = []

    if (scopeMode.value === 'all') {

      if (turningOnExamFee) {

        try {

          const dispatchRes = await smartFeeService.admin.dispatchExamFeeNotifications(props.examId, {
            delivery_channel: props.deliveryChannel,
          })

          postSaveMessages.push(extractData(dispatchRes, {}).message || 'Notifications sent.')

        } catch (dispatchErr) {

          postSaveMessages.push(dispatchErr.response?.data?.message || 'Saved. Configure fees in Finance first.')

        }

      }

    } else {

      const feeBatchesToNotify = batchRows.value

        .filter(r => r.exam_fee_applicable && !prevBatchFeeState.value[r.batch_id])

        .map(r => r.batch_id)

      if (feeBatchesToNotify.length) {

        postSaveMessages.push(...await dispatchExamFeeForBatches(feeBatchesToNotify))

      }

    }

    dispatchMsg.value = postSaveMessages.filter(Boolean).join(' ')

    prevExamFeeApplicable.value = scopeMode.value === 'all' ? settings.value.examFeeApplicable : false

    saveMsg.value = dispatchMsg.value || 'Saved.'

    showAttendanceList.value = false

    listLoaded.value = false

    rows.value = []

    showBatchStudents.value = false

    activeBatchId.value = ''

    const attendanceBatches = scopeMode.value === 'batch'

      ? batchRows.value.filter(r => r.eligibility_check_enabled).map(r => r.batch_id)

      : []

    await loadChannelPolicies()

    if (scopeMode.value === 'all' && settings.value.enabled) {

      await syncAll()

    } else if (scopeMode.value === 'batch') {

      meta.value = {}

      for (const batchId of attendanceBatches) {

        const batchRow = batchRows.value.find(r => r.batch_id === batchId)

        if (batchRow) await syncBatchAttendance(batchRow, false)

      }

    } else {

      meta.value = {}

    }

    emitChannelMeta()

    emit('updated')

  } catch (e) {

    loadError.value = e.response?.data?.message || 'Save failed'

  } finally {

    savingSettings.value = false

  }

}



async function syncAll() {

  if (!props.examId) return

  loading.value = true

  loadError.value = ''

  try {

    const syncRes = await examService.exams.syncEligibility(props.examId, {

      delivery_channel: props.deliveryChannel,

    })

    const synced = extractData(syncRes, {})

    meta.value = { summary: synced.summary || summarizeFromRows(synced.students || []) }

    if (showAttendanceList.value) {

      rows.value = synced.students || []

      listLoaded.value = true

    }

  } catch (e) {

    loadError.value = e.response?.data?.message || 'Sync failed'

  } finally {

    loading.value = false

  }

}



async function syncBatchAttendance(batchRow, showList = false) {

  if (!props.examId || !batchRow?.batch_id) return

  loading.value = true

  activeBatchId.value = batchRow.batch_id

  activeBatchName.value = batchRow.batch_name || 'Batch'

  loadError.value = ''

  try {

    const syncRes = await examService.exams.syncEligibility(props.examId, {

      delivery_channel: props.deliveryChannel,

      batch_id: batchRow.batch_id,

    })

    const synced = extractData(syncRes, {})

    const summary = synced.summary || summarizeFromRows(synced.students || [])

    const idx = batchRows.value.findIndex(r => r.batch_id === batchRow.batch_id)

    if (idx >= 0) {

      batchRows.value[idx] = {

        ...batchRows.value[idx],

        summary,

      }

    }

    batchStudentRows.value = (synced.students || []).map(s => ({

      ...s,

      student: s.student || { name: s.student_name },

    }))

    if (showList) {

      showBatchStudents.value = true

    }

  } catch (e) {

    loadError.value = e.response?.data?.message || 'Sync failed'

  } finally {

    loading.value = false

  }

}



async function toggleBatchStudents(batchRow) {

  if (activeBatchId.value === batchRow.batch_id && showBatchStudents.value) {

    showBatchStudents.value = false

    return

  }

  activeBatchId.value = batchRow.batch_id

  activeBatchName.value = batchRow.batch_name || 'Batch'

  showBatchStudents.value = true

  if (batchRow.summary) {

    await loadBatchStudents(batchRow.batch_id)

    return

  }

  await syncBatchAttendance(batchRow, true)

}



async function loadBatchStudents(batchId) {

  if (!props.examId) return

  loading.value = true

  loadError.value = ''

  try {

    const res = await examService.exams.eligibility(props.examId, {

      delivery_channel: props.deliveryChannel,

      batch_id: batchId,

    })

    const data = extractData(res, {})

    batchStudentRows.value = data.students || []

  } catch (e) {

    loadError.value = e.response?.data?.message || 'Failed to load students'

  } finally {

    loading.value = false

  }

}



async function dispatchExamFeeForBatches(batchIds) {

  const messages = []

  for (const batchId of batchIds) {

    try {

      const dispatchRes = await smartFeeService.admin.dispatchExamFeeNotifications(props.examId, {

        batch_id: batchId,

        delivery_channel: props.deliveryChannel,

      })

      const result = extractData(dispatchRes, {})

      if (result?.message) messages.push(result.message)

    } catch (dispatchErr) {

      const batchName = batchRows.value.find(r => r.batch_id === batchId)?.batch_name || 'batch'

      messages.push(

        dispatchErr.response?.data?.message || `Fee notifications failed for ${batchName}.`,

      )

    }

  }

  return messages

}



function openOverride(row) {

  overrideTarget.value = row

  overrideReason.value = ''

  overrideError.value = ''

  showOverride.value = true

}



async function submitOverride() {

  if (!overrideTarget.value || !props.examId) return

  overrideLoading.value = true

  overrideError.value = ''

  try {

    await examService.exams.overrideEligibility(props.examId, {

      student_id: overrideTarget.value.student_id,

      reason: overrideReason.value.trim(),

    })

    showOverride.value = false

    await loadSummary()

    if (showAttendanceList.value) await loadEligibility(false)

  } catch (e) {

    overrideError.value = e.response?.data?.message || 'Override failed'

  } finally {

    overrideLoading.value = false

  }

}



function formatPct(v) {

  if (v == null || v === '') return '—'

  return `${Number(v).toFixed(1)}%`

}



function statusLabel(row) {

  if (row.is_override || row.status === 'overridden') return 'Overridden'

  const map = { eligible: 'Eligible', warning: 'Warning', blocked: 'Blocked' }

  return map[row.status] || row.label || row.status

}



async function initPanel() {

  showAttendanceList.value = false

  listLoaded.value = false

  rows.value = []

  loadError.value = ''

  saveMsg.value = ''

  dispatchMsg.value = ''

  meta.value = {}

  showBatchStudents.value = false

  activeBatchId.value = ''

  prevExamFeeApplicable.value = false

  prevBatchFeeState.value = {}

  batchRows.value = []

  await loadChannelPolicies()

  if (scopeMode.value === 'all' && settings.value.enabled) await loadSummary()

  else meta.value = {}

}



watch(() => [props.examId, props.deliveryChannel], ([id]) => { if (id) initPanel() })

onMounted(() => { if (props.examId) initPanel() })

</script>



<style scoped>

.eligibility-panel {

  margin: 0.75rem 0 1rem;

  padding: 0.65rem 0.85rem;

  background: var(--bg-surface-muted);

  border: 1px solid var(--border-color);

  border-radius: 8px;

}

.panel-bar {

  display: flex;

  justify-content: space-between;

  align-items: center;

  margin-bottom: 0.5rem;

}

.panel-title { font-size: 0.82rem; font-weight: 700; color: var(--text-secondary); }

.channel-hint {

  font-size: 0.75rem;

  color: var(--text-muted);

  margin: 0 0 0.5rem;

  line-height: 1.4;

}

.scope-row {

  display: flex;

  flex-wrap: wrap;

  align-items: center;

  gap: 0.5rem 1rem;

  margin-bottom: 0.65rem;

  padding-bottom: 0.55rem;

  border-bottom: 1px dashed #e2e8f0;

}

.scope-label { font-size: 0.78rem; font-weight: 700; color: var(--text-secondary); }

.scope-option {

  display: inline-flex;

  align-items: center;

  gap: 0.35rem;

  font-size: 0.78rem;

  color: var(--text-secondary);

  cursor: pointer;

}

.rules-row {

  display: flex;

  flex-wrap: wrap;

  align-items: center;

  gap: 0.5rem 0.75rem;

}

.publish-row {

  display: flex;

  flex-wrap: wrap;

  align-items: center;

  gap: 0.45rem 0.65rem;

  margin-top: 0.55rem;

  padding: 0.45rem 0.55rem;

  background: var(--bg-card);

  border: 1px solid var(--border-color);

  border-radius: 6px;

}

.publish-label { font-size: 0.76rem; font-weight: 700; color: var(--text-secondary); }

.publish-stat { font-size: 0.75rem; color: var(--text-muted); }

.rule-chip {

  display: inline-flex;

  align-items: center;

  gap: 0.35rem;

  font-size: 0.8rem;

  color: var(--text-secondary);

  cursor: pointer;

  user-select: none;

  min-height: 1.75rem;

  flex-shrink: 0;

}

.rule-chip input[type="checkbox"] { flex-shrink: 0; }

.pct-input {

  width: 4.5rem;

  padding: 0.3rem 0.45rem;

  font-size: 0.78rem;

  flex-shrink: 0;

}

.pct-input:disabled { opacity: 0.45; }

.summary-inline {

  display: flex;

  flex-wrap: wrap;

  align-items: center;

  gap: 0.35rem;

  margin-top: 0.5rem;

  min-height: 1.75rem;

}

.pill {

  font-size: 0.68rem;

  font-weight: 700;

  padding: 0.15rem 0.45rem;

  border-radius: 999px;

}

.sum-eligible { background: #d1fae5; color: #065f46; }

.sum-warning { background: #fef3c7; color: #92400e; }

.sum-blocked { background: #fee2e2; color: #991b1b; }

.sum-overridden { background: #e0e7ff; color: #3730a3; }

.batch-table-wrap { overflow-x: auto; margin-top: 0.35rem; }

.batch-table { width: 100%; font-size: 0.76rem; border-collapse: collapse; }

.batch-table th, .batch-table td {

  padding: 0.4rem 0.5rem;

  border-bottom: 1px solid var(--border-color);

  text-align: left;

  vertical-align: middle;

}

.batch-status-cell { min-width: 9rem; }

.batch-summary-pills {

  display: flex;

  flex-wrap: wrap;

  gap: 0.2rem;

  margin-bottom: 0.25rem;

}

.batch-status-actions {

  display: flex;

  gap: 0.25rem;

  flex-wrap: wrap;

}

.batch-list-title {

  font-size: 0.78rem;

  font-weight: 700;

  color: var(--text-secondary);

  margin: 0.5rem 0 0.35rem;

}

.routine-stat { display: block; color: var(--text-secondary); }

.draft-badge {

  display: inline-block;

  margin-top: 0.15rem;

  font-size: 0.65rem;

  font-weight: 700;

  color: #b45309;

  background: #fef3c7;

  padding: 0.1rem 0.35rem;

  border-radius: 4px;

}

.list-wrap { margin-top: 0.5rem; overflow-x: auto; }

.data-table.compact { width: 100%; font-size: 0.75rem; border-collapse: collapse; }

.data-table.compact th, .data-table.compact td { padding: 0.35rem 0.5rem; border-bottom: 1px solid var(--border-light); text-align: left; }

.elig-badge {

  display: inline-block;

  padding: 0.08rem 0.35rem;

  border-radius: 4px;

  font-size: 0.62rem;

  font-weight: 700;

  text-transform: uppercase;

}

.elig-eligible { background: #d1fae5; color: #065f46; }

.elig-warning { background: #fef3c7; color: #92400e; }

.elig-blocked { background: #fee2e2; color: #991b1b; }

.elig-overridden { background: #e0e7ff; color: #3730a3; }

.muted { font-size: 0.75rem; color: var(--text-muted); margin: 0.25rem 0; }

.alert { padding: 0.4rem 0.6rem; border-radius: 6px; font-size: 0.75rem; margin-top: 0.35rem; }

.alert-danger { background: #fee2e2; color: #991b1b; }

.alert-success { background: #d1fae5; color: #065f46; }

.btn-xs { font-size: 0.65rem; padding: 0.12rem 0.4rem; }

</style>



<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>Device Simulator</h1>
        <p class="header-subtitle">Simulate biometric device scans for testing and demonstration</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="refreshAll" :disabled="loading">🔄 Refresh</button>
      </div>
    </div>

    <div v-if="devices.length === 0 && deviceError" class="error-banner">
      <p>⚠️ {{ deviceError }}</p>
    </div>

    <div class="simulator-grid">
      <!-- Simulate Single Scan -->
      <div class="card">
        <div class="card-header-row">
          <h3>📋 Simulate Single Scan</h3>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label>Device <span class="required">*</span></label>
            <select v-model="scanForm.device_id" class="form-control" required>
              <option value="">Select device...</option>
              <option v-for="d in devices" :key="d.id" :value="d.id">{{ d.device_name }} ({{ d.driver }})</option>
            </select>
          </div>
          <div class="form-group">
            <label>User Type <span class="required">*</span></label>
            <select v-model="scanForm.user_type" class="form-control" required>
              <option value="">Select type...</option>
              <option value="student">Student</option>
              <option value="teacher">Teacher</option>
              <option value="employee">Employee</option>
            </select>
          </div>
          <div class="form-group">
            <label>User <span class="required">*</span></label>
            <select
              v-model="scanForm.user_id"
              class="form-control"
              required
              :disabled="!scanForm.user_type || loadingUsers"
            >
              <option value="">{{ loadingUsers ? 'Loading users...' : 'Select user...' }}</option>
              <option v-for="u in simulatorUsers" :key="u.id" :value="u.id">
                {{ u.name }}{{ u.display_id ? ` (${u.display_id})` : '' }}
              </option>
            </select>
            <p v-if="scanForm.user_type && !loadingUsers && simulatorUsers.length === 0" class="field-hint field-hint-warn">
              No {{ scanForm.user_type }}s found in the system.
            </p>
            <p v-else-if="scanForm.user_type" class="field-hint">
              Select a registered {{ scanForm.user_type }} — profile UUID is used automatically.
            </p>
          </div>
          <div class="form-group">
            <label>Scan Time <span class="optional">(optional — defaults to now)</span></label>
            <input v-model="scanForm.scan_time" type="datetime-local" class="form-control" />
            <p class="field-hint">
              Status is auto-calculated: ≤10 min after class start → Present, 10–20 min → Late, &gt;20 min → Absent.
            </p>
          </div>
          <div class="form-group">
            <select v-model="scanForm.mode" class="form-control">
              <option value="fingerprint">Fingerprint</option>
              <option value="card">RFID Card</option>
              <option value="pin">PIN Code</option>
            </select>
          </div>
          <button class="btn btn-primary btn-block" @click="simulateScan" :disabled="simulating">
            {{ simulating ? 'Scanning...' : '🔍 Simulate Scan' }}
          </button>
        </div>
      </div>

      <!-- Generate Random Events -->
      <div class="card">
        <div class="card-header-row">
          <h3>🎲 Generate Random Events</h3>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label>Device <span class="required">*</span></label>
            <select v-model="randomForm.device_id" class="form-control" required>
              <option value="">Select device...</option>
              <option v-for="d in devices" :key="d.id" :value="d.id">{{ d.device_name }} ({{ d.driver }})</option>
            </select>
          </div>
          <div class="form-group">
            <label>Number of Events <span class="required">*</span></label>
            <input v-model.number="randomForm.count" type="number" class="form-control" min="1" max="100" />
          </div>
          <div class="form-group">
            <label>User Type</label>
            <select v-model="randomForm.user_type" class="form-control">
              <option value="">Mixed (All Types)</option>
              <option value="student">Students Only</option>
              <option value="teacher">Teachers Only</option>
              <option value="employee">Employees Only</option>
            </select>
          </div>
          <button class="btn btn-warning btn-block" @click="generateRandomEvents" :disabled="generating">
            {{ generating ? 'Generating...' : '🎲 Generate Events' }}
          </button>
        </div>
      </div>

      <!-- Recent Scans -->
      <div class="card recent-scans-card">
        <div class="card-header-row">
          <h3>🕐 Recent Simulated Scans</h3>
          <span class="badge badge-info">{{ recentScans.length }}</span>
        </div>
        <div class="card-body">
          <div v-if="recentScans.length === 0" class="empty-list">No scans yet. Simulate one above.</div>
          <div v-else class="scans-list">
            <div v-for="(scan, idx) in recentScans" :key="idx" class="scan-item">
              <div class="scan-icon" :class="'scan-' + (scan.user_type || 'unknown')">
                {{ scan.user_type === 'student' ? '👨‍🎓' : scan.user_type === 'teacher' ? '👨‍🏫' : scan.user_type === 'employee' ? '👥' : '❓' }}
              </div>
              <div class="scan-info">
                <strong>{{ scan.name || scan.user_id || 'Unknown' }}</strong>
                <span class="scan-detail">{{ scan.user_type }} · {{ scan.mode || 'fingerprint' }} · {{ scan.device_name || '—' }}</span>
              </div>
              <div class="scan-time">
                <span v-if="scan.attendance_status" class="scan-status" :class="'status-' + scan.attendance_status">
                  {{ scan.attendance_status }}{{ scan.late_minutes > 0 ? ` (${scan.late_minutes}m)` : '' }}
                </span>
                {{ scan.scan_time_display || (scan.scan_time ? formatTime12(scan.scan_time) : '—') }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="successMsg" class="toast toast-success">{{ successMsg }}</div>
    <div v-if="error" class="toast toast-error">{{ error }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import attendanceService from '@/services/attendance.service'
import { formatTime12 } from '@/utils/datetime'

const devices = ref([])
const recentScans = ref([])
const simulatorUsers = ref([])
const loading = ref(false)
const loadingUsers = ref(false)
const simulating = ref(false)
const generating = ref(false)
const error = ref(null)
const deviceError = ref(null)
const successMsg = ref(null)

const scanForm = ref({
  device_id: '',
  user_type: '',
  user_id: '',
  mode: 'fingerprint',
  scan_time: '',
})

const randomForm = ref({
  device_id: '',
  count: 10,
  user_type: '',
})

onMounted(async () => {
  await refreshAll()
})

watch(() => scanForm.value.user_type, async (type) => {
  scanForm.value.user_id = ''
  simulatorUsers.value = []
  if (!type) return
  await fetchSimulatorUsers(type)
})

const fetchSimulatorUsers = async (userType) => {
  loadingUsers.value = true
  try {
    const res = await attendanceService.getSimulatorUsers({ user_type: userType })
    simulatorUsers.value = res.data?.data || []
  } catch {
    simulatorUsers.value = []
  } finally {
    loadingUsers.value = false
  }
}

const selectDefaultDevice = () => {
  if (devices.value.length === 0) return
  if (!scanForm.value.device_id) scanForm.value.device_id = devices.value[0].id
  if (!randomForm.value.device_id) randomForm.value.device_id = devices.value[0].id
}

const fetchDevices = async () => {
  deviceError.value = null
  try {
    const res = await attendanceService.getSimulatorDevices()
    let list = res.data?.data || []

    if (list.length === 0) {
      const allRes = await attendanceService.getDevices({ per_page: 100 })
      const allDevices = allRes.data?.data?.data || allRes.data?.data || []
      list = allDevices.filter(d => ['simulator', 'fake'].includes(d.driver))
    }

    devices.value = list
    selectDefaultDevice()

    if (list.length === 0) {
      deviceError.value = 'No simulator device found. Refresh the page to auto-create one.'
    }
  } catch (err) {
    devices.value = []
    deviceError.value = err.response?.data?.message || 'Failed to load simulator devices'
  }
}

const refreshAll = async () => {
  await Promise.all([fetchDevices(), fetchRecentScans()])
}

const fetchRecentScans = async () => {
  loading.value = true
  try {
    const res = await attendanceService.getRecentSimulatorScans({ limit: 20 })
    recentScans.value = res.data.data || []
  } catch { recentScans.value = [] }
  finally { loading.value = false }
}

const simulateScan = async () => {
  if (!scanForm.value.device_id || !scanForm.value.user_type || !scanForm.value.user_id) {
    error.value = 'Please fill in all required fields'
    setTimeout(() => { error.value = null }, 3000)
    return
  }
  simulating.value = true; error.value = null
  try {
    const res = await attendanceService.simulateScan(scanForm.value)
    const log = res.data?.data
    const status = log?.attendance_status || 'recorded'
    const checkIn = log?.check_in_display || (log?.check_in ? formatTime12(log.check_in) : '—')
    const lateNote = log?.late_minutes > 0 ? ` (${log.late_minutes} min late)` : ''
    successMsg.value = `${res.data?.message || 'Scan simulated'} — ${status.toUpperCase()} (check-in ${checkIn}${lateNote})`
    await fetchRecentScans()
    setTimeout(() => { successMsg.value = null }, 3000)
  } catch (err) {
    const data = err.response?.data
    const userErr = data?.errors?.user_id?.[0]
    error.value = userErr || data?.message || 'Failed to simulate scan'
    setTimeout(() => { error.value = null }, 5000)
  } finally { simulating.value = false }
}

const generateRandomEvents = async () => {
  if (!randomForm.value.device_id) {
    error.value = 'Please select a device'
    setTimeout(() => { error.value = null }, 3000)
    return
  }
  generating.value = true; error.value = null
  try {
    const res = await attendanceService.generateRandomEvents(randomForm.value)
    successMsg.value = `Generated ${res.data?.data?.count || randomForm.value.count} random events`
    await fetchRecentScans()
    setTimeout(() => { successMsg.value = null }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to generate events'
    setTimeout(() => { error.value = null }, 3000)
  } finally { generating.value = false }
}
</script>

<style scoped>
.page-container { max-width: 1200px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem; }
.header-left h1 { font-size: 1.5rem; color: var(--text-primary); margin: 0; }
.header-subtitle { font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0; }
.header-actions { display: flex; gap: 0.5rem; }
.btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; gap: 0.4rem; }
.btn-block { width: 100%; justify-content: center; }
.btn-primary { background: #4f46e5; color: white; }
.btn-primary:hover { background: #4338ca; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-warning { background: #d97706; color: white; }
.btn-warning:hover { background: #b45309; }
.btn-warning:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-outline { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-color); }
.btn-outline:hover { background: var(--bg-accent); border-color: var(--text-muted); }
.btn-outline:disabled { opacity: 0.5; cursor: not-allowed; }
.error-banner { margin-bottom: 1rem; padding: 0.75rem 1rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #dc2626; font-size: 0.85rem; }
.error-banner p { margin: 0; }

/* Grid */
.simulator-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 900px) { .simulator-grid { grid-template-columns: 1fr; } }

/* Cards */
.card { background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); overflow: hidden; }
.card-header-row { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-light); }
.card-header-row h3 { margin: 0; font-size: 0.95rem; color: var(--text-primary); }
.card-body { padding: 1.25rem; }
.recent-scans-card { grid-column: 1 / -1; }

/* Form */
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem; }
.required { color: #ef4444; }
.optional { color: var(--text-muted); font-weight: 400; font-size: 0.75rem; }
.form-control { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: var(--bg-card); box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
.field-hint { margin: 0.35rem 0 0; font-size: 0.75rem; color: var(--text-muted); }
.field-hint-warn { color: #d97706; }

/* Badge */
.badge { padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
.badge-info { background: #e0e7ff; color: #4f46e5; }

/* Scans List */
.empty-list { text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.9rem; }
.scans-list { max-height: 400px; overflow-y: auto; }
.scan-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0; border-bottom: 1px solid #f9fafb; }
.scan-item:last-child { border-bottom: none; }
.scan-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
.scan-student { background: #e0e7ff; }
.scan-teacher { background: #cffafe; }
.scan-employee { background: #ede9fe; }
.scan-unknown { background: #f3f4f6; }
.scan-info { flex: 1; }
.scan-info strong { display: block; font-size: 0.85rem; color: var(--text-primary); }
.scan-detail { font-size: 0.75rem; color: var(--text-muted); }
.scan-time { font-size: 0.75rem; color: var(--text-muted); white-space: nowrap; text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 0.15rem; }
.scan-status { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 0.1rem 0.4rem; border-radius: 4px; }
.status-present { background: #d1fae5; color: #059669; }
.status-late { background: #fef3c7; color: #d97706; }
.status-absent { background: #fee2e2; color: #dc2626; }

/* Toast */
.toast { position: fixed; bottom: 1.5rem; right: 1.5rem; padding: 0.75rem 1.25rem; border-radius: 8px; font-size: 0.85rem; font-weight: 500; z-index: 2000; animation: slideIn 0.3s ease; }
.toast-success { background: #059669; color: white; }
.toast-error { background: #dc2626; color: white; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>

<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>Biometric Devices</h1>
        <p class="header-subtitle">Manage fingerprint/biometric attendance devices</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary" @click="showCreateModal = true">➕ Add Device</button>
        <button class="btn btn-outline" @click="fetchDevices" :disabled="loading">🔄 Refresh</button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading devices...</p></div>

    <!-- Error -->
    <div v-else-if="error" class="error-state">
      <p>⚠️ {{ error }}</p>
      <button class="btn btn-outline btn-sm" @click="fetchDevices" style="margin-top: 0.75rem;">🔄 Retry</button>
    </div>

    <!-- Device Grid -->
    <div v-else-if="devices.length > 0" class="device-grid">
      <div v-for="device in devices" :key="device.id" class="device-card" :class="'device-' + device.status">
        <div class="card-header">
          <div class="device-icon">📟</div>
          <div class="device-info">
            <h3>{{ device.device_name }}</h3>
            <span class="device-type">{{ device.device_type }}</span>
          </div>
          <div class="device-status">
            <span class="status-badge" :class="'status-' + device.status">{{ device.status }}</span>
          </div>
        </div>
        <div class="card-body">
          <div class="info-row"><span class="label">Driver:</span><span>{{ device.driver }}</span></div>
          <div class="info-row"><span class="label">IP:</span><span>{{ device.ip_address || '—' }}</span></div>
          <div class="info-row"><span class="label">Port:</span><span>{{ device.port || '—' }}</span></div>
          <div class="info-row"><span class="label">Serial:</span><span>{{ device.serial_no || '—' }}</span></div>
          <div class="info-row"><span class="label">Active:</span><span :class="device.is_active ? 'text-success' : 'text-muted'">{{ device.is_active ? 'Yes' : 'No' }}</span></div>
          <div class="info-row" v-if="device.last_sync_at"><span class="label">Last Sync:</span><span>{{ new Date(device.last_sync_at).toLocaleString() }}</span></div>
        </div>
        <div class="card-actions">
          <button class="btn btn-sm btn-outline" @click="testConnection(device.id)" :disabled="testingId === device.id">
            {{ testingId === device.id ? 'Testing...' : '🔌 Test' }}
          </button>
          <button class="btn btn-sm btn-outline" @click="pullAttendance(device.id)" :disabled="pullingId === device.id">
            {{ pullingId === device.id ? 'Pulling...' : '📥 Pull' }}
          </button>
          <button class="btn btn-sm btn-outline" @click="editDevice(device)">✏️ Edit</button>
          <button class="btn btn-sm btn-danger" @click="confirmDelete(device)">🗑️</button>
        </div>
      </div>
    </div>

    <div v-else class="empty-state">
      <div class="empty-icon">📟</div>
      <h3>No Devices</h3>
      <p>Add your first biometric device to get started</p>
      <button class="btn btn-primary" @click="showCreateModal = true">➕ Add Device</button>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showCreateModal || showEditModal" class="modal-overlay" @click.self="closeModals">
      <div class="modal">
        <div class="modal-header">
          <h2>{{ showEditModal ? 'Edit Device' : 'Add Biometric Device' }}</h2>
          <button class="modal-close" @click="closeModals">✕</button>
        </div>
        <form @submit.prevent="saveDevice" class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label>Device Name <span class="required">*</span></label>
              <input v-model="form.device_name" class="form-control" required placeholder="e.g. Main Gate Scanner" />
            </div>
            <div class="form-group">
              <label>Device Type <span class="required">*</span></label>
              <input v-model="form.device_type" class="form-control" required placeholder="e.g. ZKTeco, ESSL" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Driver <span class="required">*</span></label>
              <select v-model="form.driver" class="form-control" required>
                <option value="">Select driver...</option>
                <option v-for="d in drivers" :key="d.name" :value="d.name">{{ d.label }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>IP Address</label>
              <input v-model="form.ip_address" class="form-control" placeholder="e.g. 192.168.1.100" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Port</label>
              <input v-model.number="form.port" type="number" class="form-control" placeholder="e.g. 4370" />
            </div>
            <div class="form-group">
              <label>Serial No.</label>
              <input v-model="form.serial_no" class="form-control" placeholder="Device serial number" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="form.is_active" />
                <span>Active</span>
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline" @click="closeModals">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Saving...' : (showEditModal ? 'Update Device' : 'Create Device') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal modal-sm">
        <div class="modal-header">
          <h2>Delete Device</h2>
          <button class="modal-close" @click="deleteTarget = null">✕</button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete <strong>{{ deleteTarget.device_name }}</strong>?</p>
          <p class="text-muted">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="deleteTarget = null">Cancel</button>
          <button class="btn btn-danger" @click="deleteDevice" :disabled="deleting">
            {{ deleting ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="successMsg" class="toast toast-success">{{ successMsg }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import attendanceService from '@/services/attendance.service'

const devices = ref([])
const drivers = ref([])
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const testingId = ref(null)
const pullingId = ref(null)
const error = ref(null)
const successMsg = ref(null)
const showCreateModal = ref(false)
const showEditModal = ref(false)
const deleteTarget = ref(null)
const editingId = ref(null)

const form = ref({
  device_name: '',
  device_type: '',
  driver: '',
  ip_address: '',
  port: null,
  serial_no: '',
  is_active: true,
})

onMounted(async () => {
  await Promise.all([fetchDevices(), fetchDrivers()])
})

const fetchDevices = async () => {
  loading.value = true; error.value = null
  try {
    const res = await attendanceService.getDevices({ per_page: 50 })
    devices.value = res.data.data || []
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load devices'
  } finally { loading.value = false }
}

const fetchDrivers = async () => {
  try {
    const res = await attendanceService.getDeviceDrivers()
    drivers.value = res.data.data || []
  } catch {}
}

const closeModals = () => {
  showCreateModal.value = false
  showEditModal.value = false
  editingId.value = null
  form.value = { device_name: '', device_type: '', driver: '', ip_address: '', port: null, serial_no: '', is_active: true }
}

const editDevice = (device) => {
  editingId.value = device.id
  form.value = {
    device_name: device.device_name,
    device_type: device.device_type,
    driver: device.driver,
    ip_address: device.ip_address || '',
    port: device.port,
    serial_no: device.serial_no || '',
    is_active: device.is_active,
  }
  showEditModal.value = true
}

const saveDevice = async () => {
  saving.value = true; error.value = null
  try {
    if (editingId.value) {
      await attendanceService.updateDevice(editingId.value, form.value)
      successMsg.value = 'Device updated successfully'
    } else {
      await attendanceService.createDevice(form.value)
      successMsg.value = 'Device created successfully'
    }
    closeModals()
    await fetchDevices()
    setTimeout(() => { successMsg.value = null }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save device'
  } finally { saving.value = false }
}

const confirmDelete = (device) => {
  deleteTarget.value = device
}

const deleteDevice = async () => {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await attendanceService.deleteDevice(deleteTarget.value.id)
    successMsg.value = 'Device deleted successfully'
    deleteTarget.value = null
    await fetchDevices()
    setTimeout(() => { successMsg.value = null }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to delete device'
  } finally { deleting.value = false }
}

const testConnection = async (id) => {
  testingId.value = id; error.value = null
  try {
    const res = await attendanceService.testDeviceConnection(id)
    successMsg.value = res.data?.message || 'Connection successful!'
    await fetchDevices()
    setTimeout(() => { successMsg.value = null }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Connection failed'
  } finally { testingId.value = null }
}

const pullAttendance = async (id) => {
  pullingId.value = id; error.value = null
  try {
    const res = await attendanceService.pullAttendanceFromDevice(id)
    successMsg.value = `Pulled ${res.data?.data?.records_count || 0} attendance records`
    await fetchDevices()
    setTimeout(() => { successMsg.value = null }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to pull attendance'
  } finally { pullingId.value = null }
}
</script>

<style scoped>
.page-container { max-width: 1200px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; }
.header-left h1 { font-size: 1.5rem; color: var(--text-primary); margin: 0; }
.header-subtitle { font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0; }
.header-actions { display: flex; gap: 0.5rem; }
.btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; gap: 0.4rem; }
.btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem; }
.btn-primary { background: #4f46e5; color: white; }
.btn-primary:hover { background: #4338ca; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-outline { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-color); }
.btn-outline:hover { background: var(--bg-accent); border-color: var(--text-muted); }
.btn-danger { background: #dc2626; color: white; }
.btn-danger:hover { background: #b91c1c; }
.loading-state { text-align: center; padding: 3rem; color: var(--text-muted); }
.spinner { width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.error-state { text-align: center; padding: 2rem; background: #fef2f2; border-radius: 12px; color: #dc2626; }
.empty-state { text-align: center; padding: 3rem; background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); }
.empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.empty-state h3 { color: var(--text-primary); margin: 0 0 0.5rem; }
.empty-state p { color: var(--text-muted); margin: 0 0 1rem; }
.device-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem; }
.device-card { background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); overflow: hidden; border: 1px solid var(--border-color); }
.device-online { border-left: 4px solid #059669; }
.device-offline { border-left: 4px solid #6b7280; }
.device-error { border-left: 4px solid #dc2626; }
.card-header { display: flex; align-items: center; gap: 0.75rem; padding: 1rem; border-bottom: 1px solid var(--border-light); }
.device-icon { font-size: 2rem; }
.device-info { flex: 1; }
.device-info h3 { margin: 0; font-size: 1rem; color: var(--text-primary); }
.device-type { font-size: 0.8rem; color: var(--text-muted); }
.status-badge { padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.status-online { background: #d1fae5; color: #059669; }
.status-offline { background: #f3f4f6; color: var(--text-muted); }
.status-error { background: #fee2e2; color: #dc2626; }
.card-body { padding: 0.75rem 1rem; }
.info-row { display: flex; justify-content: space-between; padding: 0.3rem 0; font-size: 0.85rem; }
.info-row .label { color: var(--text-muted); font-weight: 500; }
.text-success { color: #059669; font-weight: 600; }
.text-muted { color: var(--text-muted); }
.card-actions { display: flex; gap: 0.4rem; padding: 0.75rem 1rem; border-top: 1px solid #f3f4f6; flex-wrap: wrap; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: var(--bg-card); border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
.modal-sm { max-width: 420px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem; border-bottom: 1px solid var(--border-color); }
.modal-header h2 { margin: 0; font-size: 1.2rem; color: var(--text-primary); }
.modal-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-muted); padding: 0.25rem; }
.modal-body { padding: 1.25rem; }
.form-row { display: flex; gap: 1rem; margin-bottom: 1rem; }
.form-row:last-child { margin-bottom: 0; }
.form-group { flex: 1; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem; }
.required { color: #ef4444; }
.form-control { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: var(--bg-card); box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
.checkbox-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
.checkbox-label input { width: auto; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); }
.toast { position: fixed; bottom: 1.5rem; right: 1.5rem; padding: 0.75rem 1.25rem; border-radius: 8px; font-size: 0.85rem; font-weight: 500; z-index: 2000; animation: slideIn 0.3s ease; }
.toast-success { background: #059669; color: white; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>
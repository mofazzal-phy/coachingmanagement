<template>
  <div class="room-page">
    <div class="page-header">
      <h1 class="page-title">Room Management</h1>
      <button class="btn btn-primary" @click="openCreateModal">
        <i class="fas fa-plus"></i> Add Room
      </button>
    </div>

    <!-- Filters -->
    <div class="filters card">
      <div class="filter-row">
        <div class="form-group">
          <label>Search</label>
          <input type="text" v-model="filters.search" class="form-control" placeholder="Search by name, code, building..." @input="debouncedSearch" />
        </div>
        <div class="form-group">
          <label>Building</label>
          <input type="text" v-model="filters.building" class="form-control" placeholder="Filter by building" @input="debouncedSearch" />
        </div>
        <div class="form-group">
          <label>Status</label>
          <select v-model="filters.status" class="form-control" @change="loadRooms">
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner"></div>
      <p>Loading rooms...</p>
    </div>

    <!-- Room Table -->
    <div v-else class="card">
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Code</th>
              <th>Capacity</th>
              <th>Building</th>
              <th>Floor</th>
              <th>Multimedia</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rooms.length === 0">
              <td colspan="8" class="text-center">No rooms found. Create your first room!</td>
            </tr>
            <tr v-for="room in rooms" :key="room.id">
              <td><strong>{{ room.name }}</strong></td>
              <td><code>{{ room.code || '—' }}</code></td>
              <td>{{ room.capacity || '—' }}</td>
              <td>{{ room.building || '—' }}</td>
              <td>{{ room.floor || '—' }}</td>
              <td>
                <span :class="['badge', room.has_multimedia ? 'badge-success' : 'badge-secondary']">
                  {{ room.has_multimedia ? 'Yes' : 'No' }}
                </span>
              </td>
              <td>
                <span :class="['badge', room.status === 'active' ? 'badge-success' : 'badge-danger']">
                  {{ room.status }}
                </span>
              </td>
              <td>
                <button class="btn btn-sm btn-outline" @click="openEditModal(room)" title="Edit">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(room)" title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination" class="pagination-wrapper">
        <div class="pagination-info">
          Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} rooms
        </div>
        <div class="pagination-buttons">
          <button class="btn btn-sm btn-outline" :disabled="!pagination.prev_page_url" @click="goToPage(pagination.current_page - 1)">
            Previous
          </button>
          <span class="page-indicator">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <button class="btn btn-sm btn-outline" :disabled="!pagination.next_page_url" @click="goToPage(pagination.current_page + 1)">
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ editingRoom ? 'Edit Room' : 'Add New Room' }}</h3>
          <button class="btn-close" @click="closeModal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label>Room Name <span class="required">*</span></label>
              <input type="text" v-model="form.name" class="form-control" placeholder="e.g. Class Room 1" required />
            </div>
            <div class="form-group">
              <label>Room Code</label>
              <input type="text" v-model="form.code" class="form-control" placeholder="e.g. CR-101" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Capacity</label>
              <input type="number" v-model="form.capacity" class="form-control" placeholder="e.g. 40" min="1" />
            </div>
            <div class="form-group">
              <label>Building</label>
              <input type="text" v-model="form.building" class="form-control" placeholder="e.g. Main Building" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Floor</label>
              <input type="text" v-model="form.floor" class="form-control" placeholder="e.g. 2nd Floor" />
            </div>
            <div class="form-group">
              <label>&nbsp;</label>
              <label class="checkbox-label">
                <input type="checkbox" v-model="form.has_multimedia" />
                <span>Has Multimedia Projector</span>
              </label>
            </div>
          </div>
          <div class="form-group" v-if="editingRoom">
            <label>Status</label>
            <select v-model="form.status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeModal">Cancel</button>
          <button class="btn btn-primary" @click="saveRoom" :disabled="saving">
            {{ saving ? 'Saving...' : (editingRoom ? 'Update Room' : 'Create Room') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation -->
    <div v-if="showDeleteConfirm" class="modal-overlay" @click.self="showDeleteConfirm = false">
      <div class="modal modal-sm">
        <div class="modal-header">
          <h3>Delete Room</h3>
          <button class="btn-close" @click="showDeleteConfirm = false">&times;</button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete <strong>{{ deletingRoom?.name }}</strong>?</p>
          <p class="text-danger">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showDeleteConfirm = false">Cancel</button>
          <button class="btn btn-danger" @click="deleteRoom" :disabled="saving">
            {{ saving ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import academicService from '@/services/academic.service'

export default {
  name: 'RoomListPage',
  data() {
    return {
      loading: false,
      saving: false,
      rooms: [],
      pagination: null,
      filters: {
        search: '',
        building: '',
        status: '',
        per_page: 15,
      },
      showModal: false,
      editingRoom: null,
      showDeleteConfirm: false,
      deletingRoom: null,
      form: {
        name: '',
        code: '',
        capacity: '',
        building: '',
        floor: '',
        has_multimedia: false,
        status: 'active',
      },
      searchTimeout: null,
    }
  },
  mounted() {
    this.loadRooms()
  },
  methods: {
    debouncedSearch() {
      clearTimeout(this.searchTimeout)
      this.searchTimeout = setTimeout(() => this.loadRooms(), 300)
    },
    async loadRooms(page = 1) {
      this.loading = true
      try {
        const params = { ...this.filters, page }
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
        const res = await academicService.rooms.list(params)
        const data = res.data?.data || {}
        this.rooms = data.data || []
        this.pagination = {
          current_page: data.current_page,
          last_page: data.last_page,
          from: data.from,
          to: data.to,
          total: data.total,
          prev_page_url: data.prev_page_url,
          next_page_url: data.next_page_url,
        }
      } catch (e) {
        console.error('Failed to load rooms:', e)
        this.$toast?.error('Failed to load rooms')
      } finally {
        this.loading = false
      }
    },
    goToPage(page) {
      if (page >= 1 && page <= (this.pagination?.last_page || 1)) {
        this.loadRooms(page)
      }
    },
    openCreateModal() {
      this.editingRoom = null
      this.form = { name: '', code: '', capacity: '', building: '', floor: '', has_multimedia: false, status: 'active' }
      this.showModal = true
    },
    openEditModal(room) {
      this.editingRoom = room
      this.form = {
        name: room.name,
        code: room.code || '',
        capacity: room.capacity || '',
        building: room.building || '',
        floor: room.floor || '',
        has_multimedia: room.has_multimedia || false,
        status: room.status || 'active',
      }
      this.showModal = true
    },
    closeModal() {
      this.showModal = false
      this.editingRoom = null
    },
    async saveRoom() {
      if (!this.form.name) {
        this.$toast?.error('Room name is required')
        return
      }
      this.saving = true
      try {
        const payload = { ...this.form }
        payload.capacity = payload.capacity ? parseInt(payload.capacity) : null
        payload.code = payload.code || null

        if (this.editingRoom) {
          await academicService.rooms.update(this.editingRoom.id, payload)
          this.$toast?.success('Room updated successfully')
        } else {
          await academicService.rooms.create(payload)
          this.$toast?.success('Room created successfully')
        }
        this.closeModal()
        await this.loadRooms()
      } catch (e) {
        const msg = e.response?.data?.message || 'Failed to save room'
        this.$toast?.error(msg)
      } finally {
        this.saving = false
      }
    },
    confirmDelete(room) {
      this.deletingRoom = room
      this.showDeleteConfirm = true
    },
    async deleteRoom() {
      if (!this.deletingRoom) return
      this.saving = true
      try {
        await academicService.rooms.delete(this.deletingRoom.id)
        this.$toast?.success('Room deleted successfully')
        this.showDeleteConfirm = false
        this.deletingRoom = null
        await this.loadRooms()
      } catch (e) {
        const msg = e.response?.data?.message || 'Failed to delete room'
        this.$toast?.error(msg)
      } finally {
        this.saving = false
      }
    },
  },
}
</script>

<style scoped>
.room-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.page-title { font-size: 24px; font-weight: 600; color: #1a202c; }
.filters { margin-bottom: 20px; padding: 20px; }
.filter-row { display: flex; gap: 20px; flex-wrap: wrap; }
.filter-row .form-group { flex: 1; min-width: 200px; }
.table-wrapper { overflow-x: auto; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-color); }
.table th { background: #f7fafc; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #4a5568; }
.table tbody tr:hover { background: #f7fafc; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.badge-success { background: #c6f6d5; color: #276749; }
.badge-secondary { background: #edf2f7; color: #4a5568; }
.badge-danger { background: #fed7d7; color: #9b2c2c; }
code { background: #edf2f7; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
.pagination-wrapper { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-top: 1px solid #e2e8f0; flex-wrap: wrap; gap: 10px; }
.pagination-info { font-size: 13px; color: #718096; }
.pagination-buttons { display: flex; align-items: center; gap: 8px; }
.page-indicator { font-size: 13px; color: #4a5568; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: var(--bg-card); border-radius: 12px; width: 550px; max-width: 90vw; max-height: 90vh; overflow-y: auto; }
.modal-sm { width: 400px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--border-color); }
.modal-header h3 { margin: 0; font-size: 18px; }
.btn-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #718096; }
.modal-body { padding: 20px; }
.modal-body .form-group { margin-bottom: 16px; }
.form-row { display: flex; gap: 16px; }
.form-row .form-group { flex: 1; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 20px; border-top: 1px solid #e2e8f0; }
.checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; padding-top: 8px; }
.checkbox-label input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
.required { color: #e53e3e; }
.text-center { text-align: center; }
.text-danger { color: #e53e3e; }
.py-5 { padding: 40px 0; }
.spinner { width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #4299e1; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 12px; }
@keyframes spin { to { transform: rotate(360deg); } }
.btn { padding: 8px 16px; border-radius: 8px; font-size: 14px; cursor: pointer; border: 1px solid transparent; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: #4299e1; color: white; border-color: #4299e1; }
.btn-primary:hover { background: #3182ce; }
.btn-secondary { background: #edf2f7; color: #4a5568; border-color: #e2e8f0; }
.btn-secondary:hover { background: #e2e8f0; }
.btn-danger { background: #e53e3e; color: white; border-color: #e53e3e; }
.btn-danger:hover { background: #c53030; }
.btn-outline { background: var(--bg-card); color: #4a5568; border-color: #e2e8f0; }
.btn-outline:hover { background: #f7fafc; }
.btn-outline-danger { background: var(--bg-card); color: #e53e3e; border-color: #fed7d7; }
.btn-outline-danger:hover { background: #fff5f5; }
.btn-sm { padding: 4px 10px; font-size: 12px; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.card { background: var(--bg-card); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.form-control { width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; transition: border-color 0.2s; box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #4299e1; box-shadow: 0 0 0 3px rgba(66,153,225,0.15); }
label { display: block; font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 6px; }
</style>

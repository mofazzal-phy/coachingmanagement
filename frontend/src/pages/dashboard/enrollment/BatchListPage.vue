<template>
  <div class="batch-list-page">
    <div class="top-bar">
      <div class="top-left">
        <h1>📦 Batches <span class="count-chip">{{ pagination?.total || batches.length }}</span></h1>
      </div>
      <div class="top-actions">
        <button class="btn btn-outline" @click="exportExcel">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right:4px"><path d="M2 3L14 3M14 3L14 13C14 13.5523 13.5523 14 13 14L3 14C2.44772 14 2 13.5523 2 13L2 3M14 3L12 1L4 1L2 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 8L8 11L11 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 5V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Excel
        </button>
        <button class="btn btn-outline" @click="exportPDF">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right:4px"><path d="M2 3L14 3M14 3L14 13C14 13.5523 13.5523 14 13 14L3 14C2.44772 14 2 13.5523 2 13L2 3M14 3L12 1L4 1L2 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 8L8 11L11 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 5V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          PDF
        </button>
        <button class="btn btn-outline" @click="printList">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right:4px"><path d="M4 4V1H12V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 9H4V15H12V9Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 6H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="11" cy="11" r="1" fill="currentColor"/></svg>
          Print
        </button>
        <router-link to="/dashboard/enrollment/batches/create" class="btn-primary">+ Add Batch</router-link>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-row" v-if="stats">
      <div class="stat-card"><span class="sv">{{ stats.total_batches }}</span><span class="sl">Total</span></div>
      <div class="stat-card green"><span class="sv">{{ stats.open_batches }}</span><span class="sl">Open</span></div>
      <div class="stat-card red"><span class="sv">{{ stats.full_batches }}</span><span class="sl">Full</span></div>
      <div class="stat-card blue"><span class="sv">{{ stats.avg_occupancy }}%</span><span class="sl">Occupancy</span></div>
    </div>

    <!-- Bulk Bar -->
    <div v-if="selectedIds.length" class="bulk-bar">
      <span>{{ selectedIds.length }} selected</span>
      <button class="btn btn-sm btn-success" @click="bulkAction('reopen')">🔄 Reopen</button>
      <button class="btn btn-sm btn-outline" @click="bulkAction('close')">⚫ Close</button>
      <button class="btn btn-sm btn-danger" @click="bulkAction('delete')">🗑 Delete</button>
      <button class="btn btn-sm" @click="selectedIds=[]">✕ Clear</button>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <input v-model="searchQuery" placeholder="🔍 Search batches..." @input="debouncedSearch" />
      <select v-model="filters.course_id" @change="loadBatches(1)"><option value="">All Courses</option><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.name }}</option></select>
      <select v-model="filters.mode" @change="loadBatches(1)"><option value="">All Modes</option><option value="online">Online</option><option value="offline">Offline</option><option value="hybrid">Hybrid</option></select>
      <select v-model="filters.status" @change="loadBatches(1)"><option value="">All Status</option><option value="open">Open</option><option value="upcoming">Upcoming</option><option value="full">Full</option><option value="closed">Closed</option></select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="skeleton-wrap">
      <div v-for="n in 5" :key="n" class="sk-row"><span class="sk w40"/><span class="sk w120"/><span class="sk w100"/><span class="sk w60"/><span class="sk w100"/><span class="sk w80"/><span class="sk w60"/><span class="sk w60"/><span class="sk w60"/></div>
    </div>

    <!-- Empty -->
    <div v-else-if="batches.length===0" class="empty-state"><div class="empty-icon">📦</div><h3>No batches found</h3></div>

    <!-- List -->
    <div v-else class="batch-list">
      <div class="list-header">
        <span class="col-chk"><input type="checkbox" :checked="allSelected" @change="toggleAll"/></span>
        <span class="col-code">Code</span>
        <span class="col-name">Batch Name</span>
        <span class="col-course">Course</span>
        <span class="col-mode">Mode</span>
        <span class="col-sched">Schedule</span>
        <span class="col-cap">Seats</span>
        <span class="col-teacher">Teacher</span>
        <span class="col-status">Status</span>
        <span class="col-acts">Actions</span>
      </div>

      <div v-for="b in batches" :key="b.id" class="list-row" :class="{ 'is-closed': b.status==='closed'||b.deleted_at }" @click="$router.push(`/dashboard/enrollment/batches/${b.id}`)">
        <span class="col-chk" @click.stop><input type="checkbox" :checked="selectedIds.includes(b.id)" @change="toggleSelect(b.id)"/></span>
        <span class="col-code"><span class="code-badge">{{ b.code }}</span></span>
        <span class="col-name"><strong>{{ b.name }}</strong></span>
        <span class="col-course">{{ b.course?.name || '—' }}</span>
        <span class="col-mode"><span :class="['mode-tag', b.mode]">{{ b.mode }}</span></span>
        <span class="col-sched">
          <span class="sched-days">{{ (b.days||[]).slice(0,3).join(', ') || '—' }}</span>
          <span v-if="b.start_time" class="sched-time">{{ b.start_time }}–{{ b.end_time }}</span>
        </span>
        <span class="col-cap">
          <div class="seat-bar"><div :class="['seat-fill', seatClass(b)]" :style="{width: seatPercent(b)+'%'}"></div></div>
          <span class="seat-text">{{ b.enrolled_count }}/{{ b.capacity }}</span>
        </span>
        <span class="col-teacher">{{ b.teacher ? b.teacher.first_name+' '+b.teacher.last_name : '—' }}</span>
        <span class="col-status"><span :class="['dot', b.status]"></span><span :class="['st-text', b.status]">{{ b.status }}</span></span>
        <span class="col-acts" @click.stop>
          <div class="dropdown" v-click-outside="()=>open=null">
            <button class="btn-icon" @click="open = open===b.id ? null : b.id">⋮</button>
            <div v-if="open===b.id" class="dropdown-menu">
              <router-link :to="`/dashboard/enrollment/batches/${b.id}`" class="dm-item">👁 View</router-link>
              <router-link :to="`/dashboard/enrollment/batches/${b.id}/edit`" class="dm-item">✏️ Edit</router-link>
              <button v-if="!b.deleted_at" class="dm-item" @click="duplicateBatch(b)">📋 Duplicate</button>
              <button v-if="!b.deleted_at" class="dm-item" @click="closeBatch(b)">⚫ Close</button>
              <button v-if="b.deleted_at" class="dm-item" @click="restoreBatch(b)">🔄 Restore</button>
              <button class="dm-item danger" @click="confirmDelete(b)">🗑 Delete</button>
            </div>
          </div>
        </span>
      </div>
    </div>

    <div v-if="pagination" class="pagination-footer">
      <pagination
        :totalRecords="pagination.total"
        :rows="pagination.per_page"
        :first="(pagination.current_page - 1) * pagination.per_page"
        @page="onPageChange"
      />
    </div>
  </div>
</template>

<script>
import enrollmentService from '@/services/enrollment.service';
import { debounce } from '@/utils/api.utils';
import { exportToExcel, exportToPDF, printTable } from '@/utils/export.utils';

export default {
  name: 'BatchListPage',
  data() {
    return {
      batches: [], courses: [], loading: false, searchQuery: '', stats: null,
      filters: { course_id: '', mode: '', status: '' }, pagination: null,
      selectedIds: [], open: null,
    };
  },
  computed: { allSelected() { return this.batches.length>0 && this.selectedIds.length===this.batches.length; } },
  created() { this.loadStats(); this.loadCourses(); this.loadBatches(); this.debouncedSearch = debounce(()=>this.loadBatches(),300); },
  methods: {
    onPageChange(event) { this.loadBatches(event.page + 1); },
    seatPercent(b) { return b.capacity>0 ? Math.round(b.enrolled_count/b.capacity*100) : 0; },
    seatClass(b) { const p=this.seatPercent(b); return p>=90?'danger':p>=70?'warning':'ok'; },
    toggleSelect(id) { const i=this.selectedIds.indexOf(id); i>-1?this.selectedIds.splice(i,1):this.selectedIds.push(id); },
    toggleAll() { this.selectedIds = this.allSelected ? [] : this.batches.map(b=>b.id); },
    async loadStats() {
      try {
        const r = await enrollmentService.getBatchStatistics();
        const data = r.data?.data;
        this.stats = data && typeof data === 'object' && !Array.isArray(data)
          ? data
          : { total_batches: 0, open_batches: 0, full_batches: 0, closed_batches: 0, upcoming_batches: 0, total_enrolled: 0, total_capacity: 0, avg_occupancy: 0 };
      } catch(e) {
        console.error('Failed to load batch stats:', e);
        this.stats = { total_batches: 0, open_batches: 0, full_batches: 0, closed_batches: 0, upcoming_batches: 0, total_enrolled: 0, total_capacity: 0, avg_occupancy: 0 };
      }
    },
    async loadCourses() { try { const r=await enrollmentService.listAllCourses(); this.courses=r.data?.data||r.data||[]; } catch{} },
    async loadBatches(page=1) { this.loading=true; try { const params={page,per_page:20,...this.filters}; if(this.searchQuery)params.search=this.searchQuery; const r=await enrollmentService.getBatches(params); this.batches=r.data?.data||r.data||[]; this.pagination=r.data?.meta||null; } catch{} finally { this.loading=false; } },
    async bulkAction(a) { if(!confirm(`${a} ${this.selectedIds.length} batches?`))return; try{await enrollmentService.bulkActionBatches({ids:this.selectedIds,action:a});this.selectedIds=[];this.loadBatches();this.loadStats();}catch{} },
    async exportCsv() { try{const r=await enrollmentService.exportBatches();const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([r.data]));a.download='batches.csv';a.click()}catch{} },
    exportExcel() {
      const headers = ['Code', 'Batch Name', 'Course', 'Mode', 'Schedule', 'Seats', 'Enrolled', 'Teacher', 'Status'];
      const rows = this.batches.map(b => [
        b.code || '',
        b.name || '',
        b.course?.name || '—',
        b.mode || '',
        (b.days||[]).slice(0,3).join(', ') + (b.start_time ? ` ${b.start_time}–${b.end_time}` : ''),
        b.capacity || 0,
        b.enrolled_count || 0,
        b.teacher ? `${b.teacher.first_name||''} ${b.teacher.last_name||''}`.trim() : '—',
        b.deleted_at ? 'archived' : b.status || '',
      ]);
      exportToExcel(headers, rows, `batches-${new Date().toISOString().slice(0, 10)}`);
    },
    exportPDF() {
      const headers = ['Code', 'Batch Name', 'Course', 'Mode', 'Schedule', 'Capacity', 'Enrolled', 'Teacher', 'Status'];
      const rows = this.batches.map(b => [
        b.code || '',
        b.name || '',
        b.course?.name || '—',
        b.mode || '',
        (b.days||[]).slice(0,3).join(', ') + (b.start_time ? ` ${b.start_time}–${b.end_time}` : ''),
        String(b.capacity || 0),
        String(b.enrolled_count || 0),
        b.teacher ? `${b.teacher.first_name||''} ${b.teacher.last_name||''}`.trim() : '—',
        b.deleted_at ? 'archived' : b.status || '',
      ]);
      exportToPDF('Batches List', headers, rows, `batches-${new Date().toISOString().slice(0, 10)}`);
    },
    printList() {
      const headers = ['Code', 'Batch Name', 'Course', 'Mode', 'Schedule', 'Capacity', 'Enrolled', 'Teacher', 'Status'];
      const rows = this.batches.map(b => [
        b.code || '',
        b.name || '',
        b.course?.name || '—',
        b.mode || '',
        (b.days||[]).slice(0,3).join(', ') + (b.start_time ? ` ${b.start_time}–${b.end_time}` : ''),
        String(b.capacity || 0),
        String(b.enrolled_count || 0),
        b.teacher ? `${b.teacher.first_name||''} ${b.teacher.last_name||''}`.trim() : '—',
        b.deleted_at ? 'archived' : b.status || '',
      ]);
      printTable('Batches List', headers, rows);
    },
    async closeBatch(b) { await enrollmentService.bulkActionBatches({ids:[b.id],action:'close'}); this.loadBatches(); this.loadStats(); },
    async restoreBatch(b) { await enrollmentService.restoreBatch(b.id); this.loadBatches(); this.loadStats(); },
    async duplicateBatch(b) { try{await enrollmentService.duplicateBatch(b.id);this.loadBatches();this.loadStats();}catch{} },
    confirmDelete(b) { if(confirm(`Delete "${b.name}"?`)){ enrollmentService.deleteBatch(b.id).then(()=>{this.loadBatches();this.loadStats();}); } },
  },
  directives: { 'click-outside': { mounted(el,b){el._co=e=>{if(!el.contains(e.target))b.value()};document.addEventListener('click',el._co)}, unmounted(el){document.removeEventListener('click',el._co)} } },
};
</script>

<style scoped>
.batch-list-page { max-width: 1200px; }
.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
.top-bar h1 { margin: 0; font-size: 1.35rem; }
.count-chip { background: #4a90d9; color: #fff; font-size: 0.75rem; padding: 0.1rem 0.5rem; border-radius: 20px; }
.top-actions { display: flex; gap: 0.5rem; align-items: center; }
.btn-primary { background: #4a90d9; color: #fff; padding: 0.55rem 1.1rem; border-radius: 10px; font-weight: 600; text-decoration: none; font-size: 0.88rem; display: inline-block; }
.btn-outline { border: 1px solid #d0d5dd; background: var(--bg-card); color: #344054; padding: 0.5rem 1rem; border-radius: 10px; cursor: pointer; font-size: 0.85rem; }

.stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 0.75rem; margin-bottom: 1rem; }
.stat-card { background: var(--bg-card); border: 1px solid #e8eaed; border-radius: 12px; padding: 0.8rem 1rem; text-align: center; }
.stat-card.green { border-left: 4px solid #12b76a; } .stat-card.red { border-left: 4px solid #f04438; } .stat-card.blue { border-left: 4px solid #4a90d9; }
.sv { display: block; font-size: 1.35rem; font-weight: 700; color: var(--text-primary); }
.sl { font-size: 0.68rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

.bulk-bar { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: #eff8ff; border: 1px solid #bcd4f0; border-radius: 10px; margin-bottom: 0.75rem; font-size: 0.85rem; }
.btn-sm { padding: 0.3rem 0.7rem; font-size: 0.78rem; border-radius: 6px; cursor: pointer; border: none; }
.btn-success { background: #12b76a; color: #fff; } .btn-danger { background: #f04438; color: #fff; }

.filters-bar { display: flex; gap: 0.65rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
.filters-bar input { flex: 1; min-width: 200px; padding: 0.5rem 0.9rem; border: 1px solid #d0d5dd; border-radius: 8px; font-size: 0.85rem; }
.filters-bar select { padding: 0.5rem 0.9rem; border: 1px solid #d0d5dd; border-radius: 8px; font-size: 0.82rem; background: var(--bg-card); cursor: pointer; }

/* List */
.list-header, .list-row { display: grid; grid-template-columns: 36px 100px 1fr 120px 70px 130px 90px 110px 65px 46px; align-items: center; gap: 0.4rem; padding: 0.55rem 0.75rem; font-size: 0.84rem; }
.list-header { background: #f2f4f7; border-radius: 10px 10px 0 0; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--text-label); }
.list-row { border-bottom: 1px solid #f0f1f3; cursor: pointer; transition: background 0.12s; }
.list-row:last-child { border-bottom: none; }
.list-row:hover { background: var(--bg-surface-muted); }
.list-row.is-closed { opacity: 0.6; }

.code-badge { font-family: monospace; font-size: 0.7rem; background: #f2f4f7; padding: 0.12rem 0.4rem; border-radius: 5px; color: #475467; }
.col-name strong { color: var(--text-primary); font-size: 0.85rem; }
.col-name strong:hover { color: #4a90d9; }

.mode-tag { padding: 0.1rem 0.45rem; border-radius: 5px; font-size: 0.68rem; font-weight: 600; text-transform: capitalize; }
.mode-tag.online { background: #eff8ff; color: #1d7fc8; } .mode-tag.offline { background: #eafaf1; color: #12b76a; } .mode-tag.hybrid { background: #fff5e6; color: #d97706; }

.col-sched { display: flex; flex-direction: column; gap: 1px; }
.sched-days { font-size: 0.78rem; color: #344054; } .sched-time { font-size: 0.7rem; color: var(--text-muted); }

.col-cap { display: flex; align-items: center; gap: 0.4rem; }
.seat-bar { width: 55px; height: 6px; background: #f2f4f7; border-radius: 3px; overflow: hidden; }
.seat-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }
.seat-fill.ok { background: #12b76a; } .seat-fill.warning { background: #f79009; } .seat-fill.danger { background: #f04438; }
.seat-text { font-size: 0.73rem; color: var(--text-label); white-space: nowrap; }

.dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 5px; }
.dot.open { background: #12b76a; } .dot.upcoming { background: #f79009; } .dot.full { background: #f04438; } .dot.closed { background: #d0d5dd; }
.st-text { font-size: 0.76rem; text-transform: capitalize; }
.st-text.open { color: #12b76a; font-weight: 600; } .st-text.upcoming { color: #f79009; } .st-text.full { color: #f04438; } .st-text.closed { color: var(--text-muted); }

.col-acts { display: flex; gap: 0.2rem; }
.dropdown { position: relative; }
.btn-icon { background: none; border: none; cursor: pointer; font-size: 1rem; padding: 0.2rem 0.35rem; border-radius: 6px; color: var(--text-label); }
.btn-icon:hover { background: #f2f4f7; }
.dropdown-menu { position: absolute; right: 0; top: 100%; z-index: 20; background: var(--bg-card); border: 1px solid #e8eaed; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); min-width: 150px; padding: 0.3rem 0; margin-top: 4px; }
.dm-item { display: block; width: 100%; padding: 0.45rem 0.9rem; text-align: left; border: none; background: none; cursor: pointer; font-size: 0.8rem; color: #344054; text-decoration: none; }
.dm-item:hover { background: var(--bg-accent); } .dm-item.danger { color: #f04438; }

.skeleton-wrap { display: flex; flex-direction: column; gap: 0.5rem; }
.sk-row { display: flex; gap: 0.5rem; padding: 0.7rem; background: var(--bg-card); border-radius: 8px; }
.sk { height: 14px; background: linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 4px; display: inline-block; }
.w40{width:40px}.w60{width:60px}.w80{width:80px}.w100{width:100px}.w120{width:120px}
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0}}

.empty-state { text-align: center; padding: 3rem; } .empty-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
.pagination-footer { margin-top: 1rem; display: flex; justify-content: center; }
</style>

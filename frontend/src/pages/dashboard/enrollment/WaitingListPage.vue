<template>
  <div class="waiting-page">
    <div class="top-bar">
      <div>
        <h1>⏳ Waiting List</h1>
        <p class="text-muted" v-if="stats">{{ stats.total_waiting }} students waiting</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-row" v-if="stats">
      <div class="stat-card red"><span class="stat-val">{{ stats.by_priority?.find(p=>p.priority==='urgent')?.count || 0 }}</span><span class="stat-lbl">🔴 Urgent</span></div>
      <div class="stat-card amber"><span class="stat-val">{{ stats.by_priority?.find(p=>p.priority==='high')?.count || 0 }}</span><span class="stat-lbl">🟡 High</span></div>
      <div class="stat-card"><span class="stat-val">{{ stats.by_priority?.find(p=>p.priority==='normal')?.count || 0 }}</span><span class="stat-lbl">⚪ Normal</span></div>
    </div>

    <!-- Bulk bar -->
    <div v-if="selectedIds.length" class="bulk-bar">
      <span>{{ selectedIds.length }} selected</span>
      <button class="btn btn-sm btn-success" @click="bulkApprove">✅ Approve All</button>
      <button class="btn btn-sm" @click="selectedIds=[]">✕ Clear</button>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <input v-model="searchQuery" placeholder="🔍 Search student..." @input="debouncedSearch" />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state"><div class="spinner"></div></div>

    <!-- Empty -->
    <div v-else-if="waitingList.length === 0" class="empty-state">
      <div class="empty-icon">✅</div>
      <h3>No one waiting!</h3>
    </div>

    <!-- List -->
    <div v-else class="wl-list">
      <div class="wl-row header">
        <span class="col-chk"><input type="checkbox" :checked="allSelected" @change="toggleAll" /></span>
        <span class="col-pos">#</span>
        <span class="col-name">Student</span>
        <span class="col-prio">Priority</span>
        <span class="col-batch">Batch</span>
        <span class="col-date">Added</span>
        <span class="col-acts">Actions</span>
      </div>

      <div v-for="enr in waitingList" :key="enr.id" class="wl-row" :class="'row-'+enr.priority">
        <span class="col-chk" @click.stop><input type="checkbox" :checked="selectedIds.includes(enr.id)" @change="toggleSelect(enr.id)" /></span>
        <span class="col-pos"><span :class="['pos-badge', enr.priority]">{{ enr.waiting_position || '—' }}</span></span>
        <span class="col-name">
          <strong>{{ enr.student?.first_name }} {{ enr.student?.last_name }}</strong>
          <small>{{ enr.student?.phone }}</small>
        </span>
        <span class="col-prio"><span :class="['prio-tag', enr.priority]">{{ enr.priority }}</span></span>
        <span class="col-batch">{{ enr.batch?.name || '—' }}</span>
        <span class="col-date">{{ enr.created_at?.slice(0,10) }}</span>
        <span class="col-acts">
          <button class="btn btn-sm btn-success" @click="approve(enr)">✅ Approve</button>
        </span>
      </div>
    </div>

    <div v-if="pagination" class="pagination-footer"><pagination :data="pagination" @change="loadList" /></div>
  </div>
</template>

<script>
import enrollmentService from '@/services/enrollment.service';
import { debounce } from '@/utils/api.utils';

export default {
  name: 'WaitingListPage',
  data() {
    return {
      waitingList: [], stats: null, loading: false, searchQuery: '', pagination: null, selectedIds: [],
    };
  },
  computed: {
    allSelected() { return this.waitingList.length > 0 && this.selectedIds.length === this.waitingList.length; },
  },
  created() {
    this.loadStats();
    this.loadList();
    this.debouncedSearch = debounce(() => this.loadList(), 300);
  },
  methods: {
    toggleSelect(id) { const i = this.selectedIds.indexOf(id); i > -1 ? this.selectedIds.splice(i,1) : this.selectedIds.push(id); },
    toggleAll() { this.selectedIds = this.allSelected ? [] : this.waitingList.map(e => e.id); },
    async loadStats() { try { const r = await enrollmentService.getWaitingListStats(); this.stats = r.data?.data || r.data; } catch {} },
    async loadList(page = 1) {
      this.loading = true;
      try {
        const params = { page, per_page: 20 };
        if (this.searchQuery) params.search = this.searchQuery;
        const r = await enrollmentService.getWaitingList(params);
        this.waitingList = r.data?.data || r.data || [];
        this.pagination = r.data?.meta || null;
      } catch {} finally { this.loading = false; }
    },
    async approve(enr) {
      if (!confirm(`Approve ${enr.student?.first_name} from waiting list?`)) return;
      try { await enrollmentService.approveFromWaitingList(enr.id); this.loadList(); this.loadStats(); } catch {}
    },
    async bulkApprove() {
      if (!confirm(`Approve ${this.selectedIds.length} students?`)) return;
      try { await enrollmentService.bulkApproveWaitingList(this.selectedIds); this.selectedIds = []; this.loadList(); this.loadStats(); } catch {}
    },
  },
};
</script>

<style scoped>
.waiting-page { max-width: 1000px; }
.top-bar { margin-bottom: 1rem; }
.top-bar h1 { margin: 0; font-size: 1.35rem; }
.text-muted { color: #888; font-size: 0.85rem; margin: 0.2rem 0 0; }

.stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1rem; }
.stat-card { background: var(--bg-card); border: 1px solid #e8eaed; border-radius: 12px; padding: 0.8rem 1rem; text-align: center; }
.stat-card.red { border-left: 4px solid #f04438; }
.stat-card.amber { border-left: 4px solid #f79009; }
.stat-val { display: block; font-size: 1.4rem; font-weight: 700; }
.stat-lbl { font-size: 0.72rem; color: var(--text-muted); }

.bulk-bar { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: #eff8ff; border: 1px solid #bcd4f0; border-radius: 10px; margin-bottom: 0.75rem; font-size: 0.85rem; }

.filters-bar { margin-bottom: 1rem; }
.filters-bar input { width: 100%; padding: 0.55rem 0.9rem; border: 1px solid #d0d5dd; border-radius: 8px; font-size: 0.88rem; }

.wl-list { background: var(--bg-card); border-radius: 14px; box-shadow: 0 1px 6px rgba(0,0,0,0.04); overflow: hidden; }
.wl-row { display: grid; grid-template-columns: 36px 50px 1fr 80px 130px 100px 90px; align-items: center; gap: 0.5rem; padding: 0.6rem 0.85rem; border-bottom: 1px solid #f0f1f3; font-size: 0.85rem; }
.wl-row.header { background: #f2f4f7; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--text-label); }
.wl-row.row-urgent { background: #fffbfb; border-left: 3px solid #f04438; }
.wl-row.row-high { border-left: 3px solid #f79009; }

.pos-badge { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #f2f4f7; font-weight: 700; font-size: 0.8rem; }
.pos-badge.urgent { background: #fef2f2; color: #dc2626; }
.pos-badge.high { background: #fffcf0; color: #d97706; }

.prio-tag { padding: 0.15rem 0.5rem; border-radius: 5px; font-size: 0.7rem; font-weight: 600; text-transform: capitalize; }
.prio-tag.urgent { background: #fef2f2; color: #dc2626; }
.prio-tag.high { background: #fffcf0; color: #d97706; }
.prio-tag.normal { background: #f2f4f7; color: var(--text-label); }
.col-name strong { display: block; font-size: 0.87rem; }
.col-name small { color: var(--text-muted); font-size: 0.75rem; }

.loading-state, .empty-state { text-align: center; padding: 3rem; }
.spinner { width: 32px; height: 32px; border: 3px solid #eee; border-top-color: #4a90d9; border-radius: 50%; animation: spin 0.7s linear infinite; margin: 0 auto; }
@keyframes spin { to { transform: rotate(360deg); } }
.empty-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }

.btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 6px; cursor: pointer; border: none; }
.btn-success { background: #12b76a; color: #fff; }
.pagination-footer { margin-top: 1rem; display: flex; justify-content: center; }
</style>

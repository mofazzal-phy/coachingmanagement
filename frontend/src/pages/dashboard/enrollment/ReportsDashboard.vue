<template>
  <div class="reports-page">
    <h1>📊 Reports & Analytics</h1>

    <!-- Stats Cards -->
    <div class="stats-row" v-if="overview">
      <div class="stat-card"><span class="sv">{{ overview.total_students }}</span><span class="sl">Total Students</span></div>
      <div class="stat-card green"><span class="sv">{{ overview.active_enrollments }}</span><span class="sl">Active Enrolled</span></div>
      <div class="stat-card gold"><span class="sv">৳{{ (overview.total_revenue||0).toLocaleString() }}</span><span class="sl">Total Revenue</span></div>
      <div class="stat-card blue"><span class="sv">{{ overview.avg_occupancy }}%</span><span class="sl">Avg Occupancy</span></div>
    </div>

    <!-- Tabs -->
    <div class="tab-nav">
      <button :class="['tab',{active:tab==='trend'}]" @click="tab='trend'">📈 Trend</button>
      <button :class="['tab',{active:tab==='revenue'}]" @click="tab='revenue'">💰 Revenue</button>
      <button :class="['tab',{active:tab==='occupancy'}]" @click="tab='occupancy'">🏫 Occupancy</button>
      <button :class="['tab',{active:tab==='teacher'}]" @click="switchTab('teacher')">👨‍🏫 Teacher</button>
      <button :class="['tab',{active:tab==='batch'}]" @click="switchTab('batch')">📦 Batch</button>
      <button :class="['tab',{active:tab==='course'}]" @click="switchTab('course')">📚 Course</button>
    </div>

    <!-- Date Filter + Export Bar -->
    <div class="date-filter" v-if="['trend','revenue','teacher','batch','course'].includes(tab)">
      <label>From: <input type="date" v-model="dateFrom" @change="loadTabData" /></label>
      <label>To: <input type="date" v-model="dateTo" @change="loadTabData" /></label>
      <div class="export-btns">
        <button class="btn btn-sm btn-outline" @click="exportFormat('csv')">📥 CSV</button>
        <button class="btn btn-sm btn-outline" @click="exportFormat('excel')">📊 Excel</button>
        <button class="btn btn-sm btn-outline" @click="exportFormat('pdf')">📄 PDF</button>
        <button class="btn btn-sm btn-outline" @click="printReport">🖨 Print</button>
      </div>
    </div>

    <!-- TAB: Enrollment Trend -->
    <div v-show="tab==='trend'" class="tab-panel">
      <div class="tab-header"><h3>Monthly Enrollments ({{ trendData.length }} months)</h3></div>
      <div class="chart-wrap" v-if="trendData.length">
        <div class="bar-chart">
          <div v-for="t in trendData" :key="t.month" class="bar-col">
            <div class="bar" :style="{height: (t.enrollments/maxT*120)+'px'}"></div>
            <span class="bl">{{ t.month }}</span>
            <span class="bv">{{ t.enrollments }}</span>
          </div>
        </div>
      </div>
      <div v-else class="empty">No data</div>
    </div>

    <!-- TAB: Revenue -->
    <div v-show="tab==='revenue'" class="tab-panel">
      <div class="tab-header">
        <h3>Revenue by Month</h3>
        <button class="btn btn-outline btn-sm" @click="exportCsv('/reports/enrollment/revenue-trend')">📥 CSV</button>
      </div>
      <div class="chart-wrap" v-if="trendData.length">
        <div class="bar-chart">
          <div v-for="t in trendData" :key="t.month" class="bar-col">
            <div class="bar bar-rev" :style="{height: (t.revenue/maxR*120)+'px'}"></div>
            <span class="bl">{{ t.month }}</span>
            <span class="bv">{{ (t.revenue/1000).toFixed(0) }}k</span>
          </div>
        </div>
      </div>
      <table class="data-table" v-if="trendData.length">
        <thead><tr><th>Month</th><th>Enrollments</th><th>Revenue</th></tr></thead>
        <tbody>
          <tr v-for="t in trendData" :key="t.month">
            <td>{{ t.month }}</td><td>{{ t.enrollments }}</td><td>৳{{ t.revenue.toLocaleString() }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- TAB: Occupancy -->
    <div v-show="tab==='occupancy'" class="tab-panel">
      <div class="tab-header">
        <h3>Batch Occupancy</h3>
        <button class="btn btn-outline btn-sm" @click="exportCsv('/reports/enrollment/occupancy')">📥 CSV</button>
      </div>
      <div v-if="occupancyData.length" class="occ-list">
        <div v-for="o in occupancyData" :key="o.id" class="occ-row">
          <div class="occ-info">
            <strong>{{ o.name }}</strong>
            <small>{{ o.course }}</small>
          </div>
          <div class="occ-bar-wrap">
            <div class="occ-bar"><div :class="['occ-fill', o.occupancy>=90?'danger':o.occupancy>=70?'warn':'ok']" :style="{width:o.occupancy+'%'}"></div></div>
            <span class="occ-txt">{{ o.enrolled }}/{{ o.capacity }} ({{ o.occupancy }}%)</span>
          </div>
          <span :class="['occ-status', o.status]">{{ o.status }}</span>
        </div>
      </div>
      <div v-else class="empty">No data</div>
    </div>

    <!-- TAB: Teacher Performance -->
    <div v-show="tab==='teacher'" class="tab-panel">
      <div class="tab-header"><h3>Teacher Performance</h3></div>
      <table class="data-table" v-if="teacherData.length">
        <thead><tr><th>Teacher</th><th>Batches</th><th>Enrolled</th><th>Active</th><th>Revenue</th></tr></thead>
        <tbody>
          <tr v-for="t in teacherData" :key="t.id">
            <td><strong>{{ t.name }}</strong></td>
            <td>{{ t.batches }}</td>
            <td>{{ t.total_enrolled }}</td>
            <td>{{ t.active_students }}</td>
            <td>৳{{ t.revenue.toLocaleString() }}</td>
          </tr>
        </tbody>
      </table>
      <div v-else class="empty">No data</div>
    </div>

    <!-- TAB: Batch Performance -->
    <div v-show="tab==='batch'" class="tab-panel">
      <div class="tab-header"><h3>Batch Performance</h3></div>
      <table class="data-table" v-if="batchData.length">
        <thead><tr><th>Batch</th><th>Course</th><th>Fill</th><th>Enrolled</th><th>Active</th><th>Dropped</th><th>Revenue</th></tr></thead>
        <tbody>
          <tr v-for="b in batchData" :key="b.id">
            <td><strong>{{ b.name }}</strong></td>
            <td>{{ b.course }}</td>
            <td><span :class="b.fill_rate>=90?'text-red':b.fill_rate>=70?'text-amber':'text-green'">{{ b.fill_rate }}%</span></td>
            <td>{{ b.enrolled }}</td>
            <td>{{ b.active }}</td>
            <td>{{ b.dropped }}</td>
            <td>৳{{ b.revenue.toLocaleString() }}</td>
          </tr>
        </tbody>
      </table>
      <div v-else class="empty">No data</div>
    </div>

    <!-- TAB: Course Performance -->
    <div v-show="tab==='course'" class="tab-panel">
      <div class="tab-header"><h3>Course Performance</h3></div>
      <table class="data-table" v-if="courseData.length">
        <thead><tr><th>Course</th><th>Cat</th><th>Batches</th><th>Enrolled</th><th>Active</th><th>Revenue</th></tr></thead>
        <tbody>
          <tr v-for="c in courseData" :key="c.id">
            <td><strong>{{ c.name }}</strong><br/><small>{{ c.code }}</small></td>
            <td>{{ c.category }}</td>
            <td>{{ c.batches }}</td>
            <td>{{ c.total_enrolled }}</td>
            <td>{{ c.active }}</td>
            <td>৳{{ c.revenue.toLocaleString() }}</td>
          </tr>
        </tbody>
      </table>
      <div v-else class="empty">No data</div>
    </div>
  </div>
</template>

<script>
import enrollmentService from '@/services/enrollment.service';

export default {
  name: 'ReportsDashboard',
  data() {
    return {
      tab: 'trend',
      overview: null, trendData: [], occupancyData: [],
      teacherData: [], batchData: [], courseData: [],
      dateFrom: '', dateTo: '',
    };
  },
  computed: {
    maxT() { return Math.max(...this.trendData.map(t=>t.enrollments), 1); },
    maxR() { return Math.max(...this.trendData.map(t=>t.revenue), 1); },
    dateParams() { const p = {}; if (this.dateFrom) p.from_date = this.dateFrom; if (this.dateTo) p.to_date = this.dateTo; return p; },
  },
  async created() {
    try { const r = await enrollmentService.getReportOverview(); this.overview = r.data?.data || r.data; } catch {}
    try { const r = await enrollmentService.getReportRevenueTrend(); this.trendData = r.data?.data || r.data || []; } catch {}
    try { const r = await enrollmentService.getReportOccupancy(); this.occupancyData = r.data?.data || r.data || []; } catch {}
  },
  methods: {
    async switchTab(t) {
      this.tab = t;
      await this.loadTabData();
    },
    async loadTabData() {
      const p = this.dateParams;
      try {
        if (this.tab === 'teacher') { const r = await enrollmentService.getReportTeacherPerformance(p); this.teacherData = r.data?.data || r.data || []; }
        if (this.tab === 'batch') { const r = await enrollmentService.getReportBatchPerformance(p); this.batchData = r.data?.data || r.data || []; }
        if (this.tab === 'course') { const r = await enrollmentService.getReportCoursePerformance(p); this.courseData = r.data?.data || r.data || []; }
        if (this.tab === 'trend') { const r = await enrollmentService.getReportRevenueTrend(6); this.trendData = r.data?.data || r.data || []; }
        if (this.tab === 'revenue') { const r = await enrollmentService.getReportRevenueTrend(6); this.trendData = r.data?.data || r.data || []; }
      } catch {}
    },
    async exportFormat(format) {
      const type = this.tab === 'trend' ? 'revenue' : this.tab;
      const baseUrl = `/reports/enrollment/export/${format === 'csv' ? 'revenue-trend' : format}`;
      const url = format === 'csv'
        ? `/reports/enrollment/${type === 'revenue' ? 'revenue-trend' : type}`
        : `/reports/enrollment/export/${format}?type=${type}`;

      try {
        const r = await enrollmentService.exportCsvData(url);
        const ext = format === 'excel' ? '.csv' : format === 'pdf' ? '.html' : '.csv';
        const blob = new Blob([r.data]);
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `report-${type}${ext}`;
        a.click();
        URL.revokeObjectURL(a.href);
      } catch {}
    },
    async printReport() {
      const type = this.tab === 'trend' ? 'revenue' : this.tab;
      const url = `/reports/enrollment/export/pdf?type=${type}`;
      try {
        const r = await enrollmentService.exportCsvData(url);
        const w = window.open('', '_blank');
        w.document.write(r.data);
        w.document.close();
        setTimeout(() => w.print(), 500);
      } catch {}
    },
  },
};
</script>

<style scoped>
.reports-page { max-width: 1100px; }
.reports-page h1 { font-size: 1.35rem; margin: 0 0 1rem 0; }

.stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 0.75rem; margin-bottom: 1.25rem; }
.stat-card { background: var(--bg-card); border: 1px solid #e8eaed; border-radius: 12px; padding: 1rem; text-align: center; }
.stat-card.green { border-left: 4px solid #12b76a; }
.stat-card.gold { border-left: 4px solid #f79009; }
.stat-card.blue { border-left: 4px solid #4a90d9; }
.sv { display: block; font-size: 1.5rem; font-weight: 700; color: var(--text-primary); }
.sl { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

.tab-nav { display: flex; gap: 0.4rem; margin-bottom: 1rem; }
.tab { padding: 0.5rem 1.2rem; border: 1px solid #e0e0e0; border-radius: 8px; background: var(--bg-card); cursor: pointer; font-size: 0.83rem; font-weight: 600; color: var(--text-label); }
.tab.active { background: #4a90d9; color: #fff; border-color: #4a90d9; }

.tab-panel { background: var(--bg-card); border-radius: 14px; padding: 1.5rem; box-shadow: 0 1px 6px rgba(0,0,0,0.04); }
.tab-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.tab-header h3 { margin: 0; font-size: 1rem; }

.chart-wrap { margin: 1rem 0; }
.bar-chart { display: flex; align-items: flex-end; gap: 0.5rem; height: 140px; justify-content: center; }
.bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; max-width: 60px; }
.bar { width: 24px; background: #4a90d9; border-radius: 4px 4px 0 0; transition: height 0.4s; min-height: 4px; }
.bar-rev { background: #12b76a; }
.bl { font-size: 0.65rem; color: var(--text-muted); }
.bv { font-size: 0.62rem; color: var(--text-label); font-weight: 600; }

.data-table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #f0f0f0; }
.data-table th { background: var(--bg-accent); color: var(--text-label); text-transform: uppercase; font-size: 0.72rem; text-align: left; }

.occ-list { display: flex; flex-direction: column; gap: 0.5rem; }
.occ-row { display: flex; align-items: center; gap: 1rem; padding: 0.65rem; border: 1px solid #e8eaed; border-radius: 10px; }
.occ-info { min-width: 140px; display: flex; flex-direction: column; }
.occ-info strong { font-size: 0.85rem; }
.occ-info small { font-size: 0.72rem; color: var(--text-muted); }
.occ-bar-wrap { flex: 1; display: flex; align-items: center; gap: 0.5rem; }
.occ-bar { flex: 1; height: 8px; background: #f2f4f7; border-radius: 4px; overflow: hidden; max-width: 200px; }
.occ-fill { height: 100%; border-radius: 4px; }
.occ-fill.ok { background: #12b76a; }
.occ-fill.warn { background: #f79009; }
.occ-fill.danger { background: #f04438; }
.occ-txt { font-size: 0.75rem; color: var(--text-label); white-space: nowrap; }
.occ-status { font-size: 0.68rem; padding: 0.15rem 0.5rem; border-radius: 4px; font-weight: 600; text-transform: capitalize; }
.occ-status.open { background: #ecfdf5; color: #12b76a; }
.occ-status.full { background: #fef2f2; color: #dc2626; }
.occ-status.closed { background: var(--bg-accent); color: var(--text-label); }

.btn-outline { border: 1px solid #d0d5dd; background: var(--bg-card); color: #344054; padding: 0.4rem 0.8rem; border-radius: 8px; cursor: pointer; font-size: 0.8rem; }
.btn-sm { font-size: 0.78rem; }
.empty { text-align: center; padding: 2rem; color: var(--text-muted); }

.date-filter { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; padding: 0.5rem 1rem; background: var(--bg-accent); border-radius: 10px; flex-wrap: wrap; }
.date-filter label { font-size: 0.8rem; color: #556; display: flex; align-items: center; gap: 0.3rem; }
.date-filter input { padding: 0.3rem 0.5rem; border: 1px solid #d0d5dd; border-radius: 6px; font-size: 0.82rem; }
.export-btns { display: flex; gap: 0.35rem; margin-left: auto; flex-wrap: wrap; }

.text-red { color: #f04438; font-weight: 600; }
.text-amber { color: #f79009; font-weight: 600; }
.text-green { color: #12b76a; font-weight: 600; }
</style>

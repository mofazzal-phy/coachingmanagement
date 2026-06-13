<template>
  <div class="course-details-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <router-link to="/dashboard/enrollment/courses" class="btn-back">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Back
        </router-link>
        <div class="header-info">
          <div class="header-title-row">
            <h1 class="page-title">{{ course.name || 'Course Details' }}</h1>
            <span :class="['status-badge', course.status]">{{ course.status }}</span>
          </div>
          <p class="page-subtitle">{{ course.code }} · {{ course.category === 'academic' ? '📖 Academic' : '🎯 Admission' }}</p>
        </div>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="exportPDF">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 10.5V13C3 13.5523 3.44772 14 4 14H12C12.5523 14 13 13.5523 13 13V10.5M8 2V10M8 10L11 7M8 10L5 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          PDF
        </button>
        <button class="btn btn-outline" @click="printDetails">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M4 5V2.5C4 2.22386 4.22386 2 4.5 2H11.5C11.7761 2 12 2.22386 12 2.5V5M3 5H13C13.5523 5 14 5.44772 14 6V11C14 11.5523 13.5523 12 13 12H3C2.44772 12 2 11.5523 2 11V6C2 5.44772 2.44772 5 3 5ZM5 8H11M5 10H9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Print
        </button>
        <router-link :to="`/dashboard/enrollment/courses/${course.id}/edit`" class="btn btn-primary">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M11.5 1.5L14.5 4.5L5.5 13.5L2 14L2.5 10.5L11.5 1.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Edit
        </router-link>
      </div>
    </div>
    <div v-if="loading" class="loading-container">
      <div class="spinner"></div>
      <p class="loading-text">Loading course details...</p>
    </div>
    <div v-else-if="error" class="error-container">
      <svg width="48" height="48" viewBox="0 0 48 48" fill="none"><path d="M24 44C35.0457 44 44 35.0457 44 24C44 12.9543 35.0457 4 24 4C12.9543 4 4 12.9543 4 24C4 35.0457 12.9543 44 24 44Z" stroke="#f04438" stroke-width="2.5"/><path d="M24 16V26M24 32H24.01" stroke="#f04438" stroke-width="2.5" stroke-linecap="round"/></svg>
      <h3>Failed to load course</h3>
      <p>{{ error }}</p>
      <button class="btn btn-primary" @click="loadCourse">Try Again</button>
    </div>
    <template v-else-if="course">
      <!-- Tabs -->
      <div class="tab-nav">
        <button v-for="t in tabs" :key="t.id" :class="['tab', { active: activeTab === t.id }]" @click="activeTab = t.id">
          <span class="tab-icon">{{ t.icon }}</span>
          <span class="tab-label">{{ t.label }}</span>
        </button>
      </div>

      <!-- TAB: Overview -->
      <div v-if="activeTab === 'overview'" class="tab-panel">
        <!-- Stats -->
        <div class="stats-row" v-if="analytics">
          <div class="stat-card">
            <span class="stat-icon">👥</span>
            <span class="stat-val">{{ analytics.total_students }}</span>
            <span class="stat-lbl">Total Students</span>
          </div>
          <div class="stat-card green">
            <span class="stat-icon">✅</span>
            <span class="stat-val">{{ analytics.active_students }}</span>
            <span class="stat-lbl">Active</span>
          </div>
          <div class="stat-card blue">
            <span class="stat-icon">📦</span>
            <span class="stat-val">{{ analytics.total_batches }}</span>
            <span class="stat-lbl">Batches</span>
          </div>
          <div class="stat-card purple">
            <span class="stat-icon">💰</span>
            <span class="stat-val">৳{{ (analytics.total_revenue||0).toLocaleString() }}</span>
            <span class="stat-lbl">Revenue</span>
          </div>
        </div>

        <!-- Info Grid -->
        <div class="section-card">
          <h4 class="section-title">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 16.5C13.1421 16.5 16.5 13.1421 16.5 9C16.5 4.85786 13.1421 1.5 9 1.5C4.85786 1.5 1.5 4.85786 1.5 9C1.5 13.1421 4.85786 16.5 9 16.5Z" stroke="currentColor" stroke-width="1.5"/><path d="M9 5.25V9.75M9 9.75L11.25 12M9 9.75L6.75 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Basic Information
          </h4>
          <div class="info-grid">
            <div class="info-item">
              <span class="info-label">Code</span>
              <span class="code-badge">{{ course.code }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Category</span>
              <span :class="['tag', course.category === 'academic' ? 'tag-academic' : 'tag-admission']">{{ course.category === 'academic' ? '📖 Academic' : '🎯 Admission' }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Duration</span>
              <span class="info-value">{{ course.duration_label || '—' }}</span>
            </div>
            <div class="info-item" v-if="course.class">
              <span class="info-label">Class</span>
              <span class="info-value">{{ course.class?.name }}{{ course.group ? ' · ' + course.group?.name : '' }}</span>
            </div>
            <div class="info-item" v-if="course.target">
              <span class="info-label">Target</span>
              <span class="info-value">{{ course.target }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Modes</span>
              <div class="mode-chips">
                <span v-if="course.has_online" class="mode-chip online">Online</span>
                <span v-if="course.has_offline" class="mode-chip offline">Offline</span>
              </div>
            </div>
            <div class="info-item">
              <span class="info-label">Sort Order</span>
              <span class="info-value">{{ course.sort_order ?? '—' }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Created</span>
              <span class="info-value">{{ course.created_at ? new Date(course.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—' }}</span>
            </div>
          </div>
        </div>

        <div v-if="course.description" class="section-card">
          <h4 class="section-title">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3 3H15M3 9H15M3 15H11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            Description
          </h4>
          <p class="section-text">{{ course.description }}</p>
        </div>

        <div v-if="course.learning_outcomes" class="section-card">
          <h4 class="section-title">🎯 Learning Outcomes</h4>
          <p class="section-text">{{ course.learning_outcomes }}</p>
        </div>

        <div v-if="course.syllabus" class="section-card">
          <h4 class="section-title">📋 Syllabus</h4>
          <p class="section-text">{{ course.syllabus }}</p>
        </div>
      </div>

      <!-- TAB: Fees -->
      <div v-if="activeTab === 'fees'" key="fees" class="tab-panel">
        <div class="section-card">
          <h4 class="section-title">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 1.5V16.5M13.5 4.5C13.5 4.5 11.5 3 9 3C6.5 3 4.5 4.5 4.5 4.5M4.5 13.5C4.5 13.5 6.5 15 9 15C11.5 15 13.5 13.5 13.5 13.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            Subjects & Fees
          </h4>
          <div v-if="course.subjects?.length" class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Subject</th>
                  <th class="text-right">Fee</th>
                  <th class="text-right">Monthly Fee</th>
                  <th class="text-center">Mandatory</th>
                  <th class="text-center">Optional</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(s, i) in course.subjects" :key="s.id">
                  <td class="text-muted">{{ i + 1 }}</td>
                  <td><strong>{{ s.name }}</strong></td>
                  <td class="text-right fee-cell">৳{{ (s.pivot?.fee||0).toLocaleString() }}</td>
                  <td class="text-right fee-cell">৳{{ (s.pivot?.monthly_fee||0).toLocaleString() }}</td>
                  <td class="text-center">{{ s.pivot?.is_mandatory ? '✅' : '—' }}</td>
                  <td class="text-center">{{ s.pivot?.is_optional ? '✅' : '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="empty-section">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none"><path d="M20 35C28.2843 35 35 28.2843 35 20C35 11.7157 28.2843 5 20 5C11.7157 5 5 11.7157 5 20C5 28.2843 11.7157 35 20 35Z" stroke="#D0D5DD" stroke-width="2.5"/><path d="M20 15V25M15 20H25" stroke="#D0D5DD" stroke-width="2.5" stroke-linecap="round"/></svg>
            <p>No subjects assigned to this course yet.</p>
          </div>
        </div>
      </div>

      <!-- TAB: Batches -->
      <div v-if="activeTab === 'batches'" key="batches" class="tab-panel">
        <div class="section-card">
          <div class="section-header-row">
            <h4 class="section-title">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3 3H15M3 7H15M3 11H11M3 15H7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              Batches
              <span class="count-badge">{{ course.batches?.length || 0 }}</span>
            </h4>
            <router-link :to="`/dashboard/enrollment/batches/create?course_id=${course.id}`" class="btn btn-sm btn-primary">+ Add Batch</router-link>
          </div>
          <div v-if="course.batches?.length" class="batch-list">
            <div v-for="b in course.batches" :key="b.id" class="batch-row" @click="$router.push(`/dashboard/enrollment/batches/${b.id}`)">
              <div class="batch-left">
                <span :class="['batch-mode-dot', b.mode]"></span>
              </div>
              <div class="batch-body">
                <strong class="batch-name">{{ b.name }}</strong>
                <span class="batch-meta">{{ b.days?.slice(0,3).join(', ') }} · {{ b.start_time }}–{{ b.end_time }}</span>
              </div>
              <div class="batch-right">
                <div class="batch-seat-bar">
                  <div class="batch-seat-fill" :class="seatClass(b)" :style="{width: seatPct(b)+'%'}"></div>
                </div>
                <span class="batch-cap">{{ b.enrolled_count }}/{{ b.capacity }}</span>
                <span :class="['batch-status', b.status]">{{ b.status }}</span>
              </div>
            </div>
          </div>
          <div v-else class="empty-section">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none"><path d="M5 10H35V30C35 31.1046 34.1046 32 33 32H7C5.89543 32 5 31.1046 5 30V10Z" stroke="#D0D5DD" stroke-width="2.5"/><path d="M5 10L20 20L35 10" stroke="#D0D5DD" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <p>No batches created for this course yet.</p>
            <router-link :to="`/dashboard/enrollment/batches/create?course_id=${course.id}`" class="btn btn-primary btn-sm">Create First Batch</router-link>
          </div>
        </div>
      </div>

      <!-- TAB: Analytics -->
      <div v-if="activeTab === 'analytics'" key="analytics" class="tab-panel">
        <div class="section-card">
          <h4 class="section-title">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3 15H15M3 15L3 9M3 15L7 11L10 14L15 8M15 15V5M15 8L12 5L9 8L6 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Enrollment Trend
          </h4>
          <div class="chart-container" v-if="analytics?.trend?.length">
            <div class="bar-chart">
              <div v-for="t in analytics.trend" :key="t.month" class="bar-col">
                <div class="bar-group">
                  <div
                    class="bar bar-enroll"
                    :style="{height: barHeight(t.enrollments, maxEnroll)+'px'}"
                    :title="t.enrollments+' enrollments'"
                  ></div>
                  <div
                    class="bar bar-rev"
                    :style="{height: barHeight(t.revenue, maxRev)+'px'}"
                    :title="'৳'+t.revenue.toLocaleString()"
                  ></div>
                </div>
                <span class="bar-label">{{ t.month }}</span>
              </div>
            </div>
            <div class="chart-legend">
              <span><span class="legend-dot enroll"></span> Enrollments</span>
              <span><span class="legend-dot rev"></span> Revenue</span>
            </div>
          </div>
          <div v-else class="empty-section">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none"><path d="M5 35H35M5 35L5 25M5 35L12 28L17 32L25 24M25 24L30 28L35 22M25 24V15" stroke="#D0D5DD" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <p>No analytics data available yet.</p>
          </div>
        </div>

        <div class="section-card" v-if="analytics">
          <h4 class="section-title">📊 Summary</h4>
          <div class="summary-grid">
            <div class="summary-item">
              <span class="summary-num">{{ analytics.avg_occupancy }}%</span>
              <span class="summary-lbl">Avg Occupancy</span>
            </div>
            <div class="summary-item">
              <span class="summary-num">{{ analytics.open_batches }}</span>
              <span class="summary-lbl">Open Batches</span>
            </div>
            <div class="summary-item">
              <span class="summary-num">{{ analytics.full_batches }}</span>
              <span class="summary-lbl">Full Batches</span>
            </div>
            <div class="summary-item">
              <span class="summary-num">৳{{ (analytics.total_revenue||0).toLocaleString() }}</span>
              <span class="summary-lbl">Total Revenue</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import enrollmentService from '@/services/enrollment.service';
import { exportToPDF, printTable } from '@/utils/export.utils';

export default {
  name: 'CourseDetailsPage',
  data() {
    return {
      course: {},
      analytics: null,
      loading: false,
      error: null,
      activeTab: 'overview',
      tabs: [
        { id: 'overview', icon: '📋', label: 'Overview' },
        { id: 'fees', icon: '💰', label: 'Fees' },
        { id: 'batches', icon: '📦', label: 'Batches' },
        { id: 'analytics', icon: '📊', label: 'Analytics' },
      ],
    };
  },
  computed: {
    maxEnroll() {
      return Math.max(...(this.analytics?.trend?.map(t => t.enrollments) || [1]), 1);
    },
    maxRev() {
      return Math.max(...(this.analytics?.trend?.map(t => t.revenue) || [1]), 1);
    },
  },
  created() {
    this.loadCourse();
  },
  methods: {
    seatPct(b) {
      return b.capacity > 0 ? (b.enrolled_count / b.capacity) * 100 : 0;
    },
    seatClass(b) {
      const p = this.seatPct(b);
      return p >= 90 ? 'danger' : p >= 70 ? 'warning' : 'ok';
    },
    barHeight(v, max) {
      return max > 0 ? Math.max(4, (v / max) * 120) : 4;
    },
    async loadCourse() {
      this.loading = true;
      this.error = null;
      try {
        const r = await enrollmentService.getCourse(this.$route.params.id);
        this.course = r.data?.data || r.data;
        try {
          const a = await enrollmentService.getCourseAnalytics(this.$route.params.id);
          this.analytics = a.data?.data || a.data;
        } catch (e) {
          console.warn('Analytics load failed:', e);
        }
      } catch (e) {
        console.error(e);
        this.error = e.response?.data?.message || e.message || 'An unexpected error occurred.';
      } finally {
        this.loading = false;
      }
    },
    exportPDF() {
      const c = this.course;
      const headers = ['Property', 'Value'];
      const rows = [
        ['Name', c.name],
        ['Code', c.code],
        ['Category', c.category],
        ['Status', c.status],
        ['Duration', c.duration_label || '—'],
        ['Class', c.class?.name || '—'],
        ['Group', c.group?.name || c.target || '—'],
        ['Description', c.description || '—'],
        ['Learning Outcomes', c.learning_outcomes || '—'],
      ];
      if (c.subjects?.length) {
        rows.push(['Subjects', c.subjects.map(s => `${s.name} (৳${s.pivot?.fee||0})`).join(', ')]);
      }
      exportToPDF(`Course: ${c.name}`, headers, rows, `course-${c.code || c.id}`, {
        tableOptions: { tableWidth: 'auto' },
      });
    },
    printDetails() {
      const c = this.course;
      const headers = ['Property', 'Value'];
      const rows = [
        ['Name', c.name],
        ['Code', c.code],
        ['Category', c.category],
        ['Status', c.status],
        ['Duration', c.duration_label || '—'],
        ['Class', c.class?.name || '—'],
        ['Group', c.group?.name || c.target || '—'],
        ['Description', c.description || '—'],
      ];
      if (c.subjects?.length) {
        rows.push(['Subjects', c.subjects.map(s => `${s.name} (৳${s.pivot?.fee||0})`).join(', ')]);
      }
      if (c.batches?.length) {
        rows.push(['Batches', c.batches.map(b => `${b.name} (${b.enrolled_count}/${b.capacity})`).join(', ')]);
      }
      printTable(`Course: ${c.name}`, headers, rows);
    },
  },
};
</script>

<style scoped>
/* ===== Layout ===== */
.course-details-page {
  max-width: 1000px;
}

/* ===== Header ===== */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.25rem;
  gap: 1rem;
  flex-wrap: wrap;
}
.header-left {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  min-width: 0;
}
.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #475467;
  background: var(--bg-card);
  border: 1px solid #d0d5dd;
  text-decoration: none;
  transition: all 0.2s;
  white-space: nowrap;
  flex-shrink: 0;
}
.btn-back:hover {
  background: var(--bg-accent);
  border-color: var(--text-muted);
  color: #1d2939;
}
.header-info {
  min-width: 0;
}
.header-title-row {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  flex-wrap: wrap;
}
.page-title {
  font-size: 1.375rem;
  font-weight: 700;
  color: #1d2939;
  margin: 0;
  line-height: 1.3;
}
.page-subtitle {
  font-size: 0.8125rem;
  color: var(--text-label);
  margin: 0.125rem 0 0;
}
.header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

/* ===== Buttons ===== */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  border: 1px solid transparent;
  line-height: 1.4;
}
.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
}
.btn-primary {
  background: #2563eb;
  color: #fff;
  border-color: #2563eb;
}
.btn-primary:hover {
  background: #1d4ed8;
  border-color: #1d4ed8;
}
.btn-outline {
  background: var(--bg-card);
  color: #344054;
  border-color: #d0d5dd;
}
.btn-outline:hover {
  background: var(--bg-accent);
  border-color: var(--text-muted);
}

/* ===== Status Badge ===== */
.status-badge {
  display: inline-block;
  padding: 0.1875rem 0.625rem;
  border-radius: 999px;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  line-height: 1.4;
}
.status-badge.active,
.status-badge.open {
  background: #ecfdf3;
  color: #067647;
}
.status-badge.inactive,
.status-badge.closed {
  background: #fef3f2;
  color: #b42318;
}
.status-badge.draft {
  background: #fefcbf;
  color: #92400e;
}

/* ===== Loading ===== */
.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  color: var(--text-label);
}
.spinner {
  width: 2.5rem;
  height: 2.5rem;
  border: 3px solid #e4e7ec;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
.loading-text {
  margin-top: 1rem;
  font-size: 0.875rem;
}

/* ===== Error ===== */
.error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 3rem 2rem;
  background: var(--bg-card);
  border: 1px solid #fecdca;
  border-radius: 12px;
}
.error-container h3 {
  margin: 1rem 0 0.25rem;
  font-size: 1.125rem;
  color: #b42318;
}
.error-container p {
  margin: 0 0 1.25rem;
  font-size: 0.8125rem;
  color: #912018;
}

/* ===== Tab Nav ===== */
.tab-nav {
  display: flex;
  gap: 0.25rem;
  margin-bottom: 1.25rem;
  background: var(--bg-card);
  border: 1px solid #e4e7ec;
  border-radius: 10px;
  padding: 0.25rem;
  overflow-x: auto;
}
.tab {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 1rem;
  border: none;
  background: transparent;
  border-radius: 8px;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--text-label);
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}
.tab:hover {
  color: #344054;
  background: var(--bg-accent);
}
.tab.active {
  color: #1d2939;
  background: #f0f5ff;
  font-weight: 600;
}
.tab-icon {
  font-size: 1rem;
  line-height: 1;
}

/* ===== Tab Panel ===== */
.tab-panel {
  animation: fadeIn 0.25s ease;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ===== Stats Row ===== */
.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}
.stat-card {
  background: var(--bg-card);
  border: 1px solid #e4e7ec;
  border-radius: 12px;
  padding: 1rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  transition: all 0.2s;
  border-left: 3px solid #667085;
}
.stat-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.06);
  transform: translateY(-1px);
}
.stat-card.green { border-left-color: #12b76a; }
.stat-card.blue { border-left-color: #2563eb; }
.stat-card.purple { border-left-color: #7c3aed; }
.stat-icon {
  font-size: 1.25rem;
  line-height: 1;
}
.stat-val {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1d2939;
  line-height: 1.2;
}
.stat-lbl {
  font-size: 0.75rem;
  color: var(--text-label);
  font-weight: 500;
}

/* ===== Section Card ===== */
.section-card {
  background: var(--bg-card);
  border: 1px solid #e4e7ec;
  border-radius: 12px;
  padding: 1.25rem 1.5rem;
  margin-bottom: 1rem;
}
.section-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9375rem;
  font-weight: 600;
  color: #1d2939;
  margin: 0 0 1rem;
}
.section-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.75rem;
}
.section-header-row .section-title {
  margin-bottom: 0;
}
.section-text {
  font-size: 0.8125rem;
  color: #475467;
  line-height: 1.6;
  margin: 0;
}
.count-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.375rem;
  border-radius: 999px;
  background: #f0f5ff;
  color: #2563eb;
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1;
}

/* ===== Info Grid ===== */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}
.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.info-label {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.info-value {
  font-size: 0.8125rem;
  color: #1d2939;
  font-weight: 500;
}
.code-badge {
  display: inline-block;
  padding: 0.125rem 0.5rem;
  border-radius: 4px;
  background: #f2f4f7;
  color: #475467;
  font-size: 0.75rem;
  font-weight: 600;
  font-family: 'Courier New', monospace;
  width: fit-content;
}
.tag {
  display: inline-block;
  padding: 0.125rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 500;
  width: fit-content;
}
.tag-academic {
  background: #ecfdf3;
  color: #067647;
}
.tag-admission {
  background: #fefcbf;
  color: #92400e;
}
.mode-chips {
  display: flex;
  gap: 0.375rem;
  flex-wrap: wrap;
}
.mode-chip {
  display: inline-block;
  padding: 0.125rem 0.5rem;
  border-radius: 4px;
  font-size: 0.6875rem;
  font-weight: 600;
}
.mode-chip.online {
  background: #e0f2fe;
  color: #0369a1;
}
.mode-chip.offline {
  background: #fef3c7;
  color: #92400e;
}

/* ===== Table ===== */
.table-wrap {
  overflow-x: auto;
}
.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}
.table th {
  text-align: left;
  padding: 0.625rem 0.75rem;
  font-weight: 600;
  color: var(--text-label);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  border-bottom: 1px solid #e4e7ec;
  background: var(--bg-accent);
}
.table td {
  padding: 0.625rem 0.75rem;
  border-bottom: 1px solid #f2f4f7;
  color: #344054;
}
.table tr:last-child td {
  border-bottom: none;
}
.text-right {
  text-align: right;
}
.text-center {
  text-align: center;
}
.text-muted {
  color: var(--text-muted);
}
.fee-cell {
  font-weight: 600;
  color: #1d2939;
}

/* ===== Empty Section ===== */
.empty-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 2rem;
  text-align: center;
  color: var(--text-muted);
  font-size: 0.8125rem;
}

/* ===== Batch List ===== */
.batch-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.batch-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border: 1px solid #f2f4f7;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
}
.batch-row:hover {
  background: var(--bg-accent);
  border-color: #e4e7ec;
}
.batch-left {
  flex-shrink: 0;
}
.batch-mode-dot {
  display: block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
}
.batch-mode-dot.online {
  background: #2563eb;
}
.batch-mode-dot.offline {
  background: #d97706;
}
.batch-mode-dot.hybrid {
  background: #7c3aed;
}
.batch-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}
.batch-name {
  font-size: 0.8125rem;
  color: #1d2939;
}
.batch-meta {
  font-size: 0.6875rem;
  color: var(--text-label);
}
.batch-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
}
.batch-seat-bar {
  width: 60px;
  height: 6px;
  background: #f2f4f7;
  border-radius: 999px;
  overflow: hidden;
}
.batch-seat-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.3s ease;
}
.batch-seat-fill.ok {
  background: #12b76a;
}
.batch-seat-fill.warning {
  background: #f59e0b;
}
.batch-seat-fill.danger {
  background: #f04438;
}
.batch-cap {
  font-size: 0.75rem;
  font-weight: 600;
  color: #344054;
  white-space: nowrap;
}
.batch-status {
  display: inline-block;
  padding: 0.125rem 0.5rem;
  border-radius: 999px;
  font-size: 0.625rem;
  font-weight: 600;
  text-transform: uppercase;
}
.batch-status.open {
  background: #ecfdf3;
  color: #067647;
}
.batch-status.closed {
  background: #fef3f2;
  color: #b42318;
}
.batch-status.full {
  background: #fefcbf;
  color: #92400e;
}

/* ===== Chart ===== */
.chart-container {
  padding-top: 0.5rem;
}
.bar-chart {
  display: flex;
  align-items: flex-end;
  gap: 0.75rem;
  height: 150px;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid #e4e7ec;
}
.bar-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
  height: 100%;
  justify-content: flex-end;
}
.bar-group {
  display: flex;
  align-items: flex-end;
  gap: 2px;
  height: 100%;
}
.bar {
  width: 12px;
  border-radius: 3px 3px 0 0;
  min-height: 4px;
  transition: height 0.4s ease;
}
.bar-enroll {
  background: #2563eb;
}
.bar-rev {
  background: #7c3aed;
}
.bar-label {
  font-size: 0.625rem;
  color: var(--text-muted);
  text-align: center;
}
.chart-legend {
  display: flex;
  justify-content: center;
  gap: 1.5rem;
  margin-top: 0.75rem;
  font-size: 0.75rem;
  color: var(--text-label);
}
.legend-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 0.375rem;
}
.legend-dot.enroll {
  background: #2563eb;
}
.legend-dot.rev {
  background: #7c3aed;
}

/* ===== Summary Grid ===== */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 0.75rem;
}
.summary-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  padding: 1rem;
  background: var(--bg-accent);
  border-radius: 8px;
}
.summary-num {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1d2939;
}
.summary-lbl {
  font-size: 0.6875rem;
  color: var(--text-label);
  font-weight: 500;
}

/* ===== Responsive ===== */
@media (max-width: 640px) {
  .page-header {
    flex-direction: column;
  }
  .header-actions {
    width: 100%;
    justify-content: flex-end;
  }
  .stats-row {
    grid-template-columns: repeat(2, 1fr);
  }
  .info-grid {
    grid-template-columns: 1fr;
  }
  .tab-nav {
    flex-wrap: nowrap;
  }
  .batch-right {
    flex-wrap: wrap;
    gap: 0.375rem;
  }
}
</style>

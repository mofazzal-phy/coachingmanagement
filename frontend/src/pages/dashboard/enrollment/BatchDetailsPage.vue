<template>
  <div class="batch-details-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <router-link to="/dashboard/enrollment/batches" class="btn-back">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Back
        </router-link>
        <div class="header-info">
          <h1 class="page-title">{{ batch.name || 'Batch Details' }}</h1>
          <span class="page-subtitle">
            <span class="code-badge">{{ batch.code }}</span>
            <span class="sep">·</span>
            {{ batch.course?.name || '—' }}
          </span>
        </div>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="exportExcel">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 3L14 3M14 3L14 13C14 13.5523 13.5523 14 13 14L3 14C2.44772 14 2 13.5523 2 13L2 3M14 3L12 1L4 1L2 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 8L8 11L11 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 5V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Excel
        </button>
        <button class="btn btn-outline" @click="exportPDF">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 3L14 3M14 3L14 13C14 13.5523 13.5523 14 13 14L3 14C2.44772 14 2 13.5523 2 13L2 3M14 3L12 1L4 1L2 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 8L8 11L11 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 5V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          PDF
        </button>
        <button class="btn btn-outline" @click="printDetails">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M4 4V1H12V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 9H4V15H12V9Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 6H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="11" cy="11" r="1" fill="currentColor"/></svg>
          Print
        </button>
        <router-link :to="`/dashboard/enrollment/batches/${batch.id}/edit`" class="btn btn-outline">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M11.5 1.5L14.5 4.5L5.5 13.5L2 14L2.5 10.5L11.5 1.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Edit
        </router-link>
        <button @click="confirmDelete" class="btn btn-danger-outline">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 4H14M5.5 4V2.5C5.5 2.22386 5.72386 2 6 2H10C10.2761 2 10.5 2.22386 10.5 2.5V4M12.5 4V13C12.5 13.5523 12.0523 14 11.5 14H4.5C3.94772 14 3.5 13.5523 3.5 13V4H12.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Delete
        </button>
      </div>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div></div>

    <template v-else-if="batch">
      <!-- Status Banner -->
      <div :class="['status-banner', batch.status]">
        <div class="banner-icon">
          <svg v-if="batch.status === 'open'" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 18C14.4183 18 18 14.4183 18 10C18 5.58172 14.4183 2 10 2C5.58172 2 2 5.58172 2 10C2 14.4183 5.58172 18 10 18Z" stroke="currentColor" stroke-width="1.5"/><path d="M7 10L9 12L13 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <svg v-else-if="batch.status === 'full'" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 18C14.4183 18 18 14.4183 18 10C18 5.58172 14.4183 2 10 2C5.58172 2 2 5.58172 2 10C2 14.4183 5.58172 18 10 18Z" stroke="currentColor" stroke-width="1.5"/><path d="M7 7L13 13M13 7L7 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          <svg v-else width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 18C14.4183 18 18 14.4183 18 10C18 5.58172 14.4183 2 10 2C5.58172 2 2 5.58172 2 10C2 14.4183 5.58172 18 10 18Z" stroke="currentColor" stroke-width="1.5"/><path d="M10 6V10M10 14H10.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </div>
        <div class="banner-text">
          <strong>{{ batch.status === 'open' ? 'Open for Enrollment' : batch.status === 'full' ? 'Batch Full' : batch.status === 'upcoming' ? 'Upcoming Batch' : 'Closed' }}</strong>
          <span>{{ batch.enrolled_count }}/{{ batch.capacity }} seats filled</span>
        </div>
        <div class="banner-seats">
          <div class="seat-progress">
            <div class="seat-fill" :style="{ width: capacityPercent + '%' }"></div>
          </div>
        </div>
      </div>

      <!-- Content Grid -->
      <div class="content-grid">
        <!-- Left Column -->
        <div class="col-main">
          <!-- Overview Card -->
          <div class="card">
            <div class="card-header">
              <h3><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 16.5C13.1421 16.5 16.5 13.1421 16.5 9C16.5 4.85786 13.1421 1.5 9 1.5C4.85786 1.5 1.5 4.85786 1.5 9C1.5 13.1421 4.85786 16.5 9 16.5Z" stroke="currentColor" stroke-width="1.5"/><path d="M9 5.25V9.75M9 9.75L11.25 12M9 9.75L6.75 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Overview</h3>
            </div>
            <div class="card-body">
              <div class="info-grid">
                <div class="info-item">
                  <span class="info-label">Mode</span>
                  <span :class="['mode-tag', batch.mode]">
                    <span class="mode-dot"></span>
                    {{ batch.mode === 'online' ? 'Online' : batch.mode === 'offline' ? 'Offline' : 'Hybrid' }}
                  </span>
                </div>
                <div class="info-item">
                  <span class="info-label">Academic Session</span>
                  <span class="info-value">{{ batch.academic_session?.name || '—' }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Shift</span>
                  <span class="info-value">{{ batch.shift ? batch.shift.charAt(0).toUpperCase() + batch.shift.slice(1) : '—' }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Capacity</span>
                  <span class="info-value">{{ batch.capacity || '—' }} seats</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Schedule Card -->
          <div class="card">
            <div class="card-header">
              <h3><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M15 9C15 12.3137 12.3137 15 9 15C5.68629 15 3 12.3137 3 9C3 5.68629 5.68629 3 9 3C12.3137 3 15 5.68629 15 9Z" stroke="currentColor" stroke-width="1.5"/><path d="M9 5.5V9L11 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Schedule</h3>
            </div>
            <div class="card-body">
              <div class="schedule-display">
                <div class="schedule-days">
                  <span v-for="day in allDays" :key="day.key" :class="['day-chip', { active: batch.days?.includes(day.key) }]">
                    {{ day.label }}
                  </span>
                </div>
                <div class="schedule-time" v-if="batch.start_time || batch.end_time">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 14C11.3137 14 14 11.3137 14 8C14 4.68629 11.3137 2 8 2C4.68629 2 2 4.68629 2 8C2 11.3137 4.68629 14 8 14Z" stroke="currentColor" stroke-width="1.5"/><path d="M8 5V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  <span>{{ batch.start_time }} — {{ batch.end_time }}</span>
                </div>
              </div>
              <div class="date-range" v-if="batch.start_date || batch.end_date">
                <span class="date-item">
                  <span class="date-lbl">Start</span>
                  <span class="date-val">{{ batch.start_date ? new Date(batch.start_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—' }}</span>
                </span>
                <span class="date-arrow">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="date-item">
                  <span class="date-lbl">End</span>
                  <span class="date-val">{{ batch.end_date ? new Date(batch.end_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—' }}</span>
                </span>
              </div>
            </div>
          </div>

          <!-- Location / Online Card -->
          <div class="card">
            <div class="card-header">
              <h3>
                <svg v-if="batch.mode === 'online'" width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M2 5C2 3.89543 2.89543 3 4 3H14C15.1046 3 16 3.89543 16 5V11C16 12.1046 15.1046 13 14 13H4C2.89543 13 2 12.1046 2 11V5Z" stroke="currentColor" stroke-width="1.5"/><path d="M6 16H12M9 13V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <svg v-else width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M15 11.5C15 13.9853 12.3137 16 9 16C5.68629 16 3 13.9853 3 11.5C3 9.01472 5.68629 7 9 7C12.3137 7 15 9.01472 15 11.5Z" stroke="currentColor" stroke-width="1.5"/><path d="M9 7V2M9 2L11 4M9 2L7 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ batch.mode === 'online' ? 'Online Details' : 'Location Details' }}
              </h3>
            </div>
            <div class="card-body">
              <div class="info-grid">
                <template v-if="batch.mode === 'offline' || batch.mode === 'hybrid'">
                  <div class="info-item">
                    <span class="info-label">Room</span>
                    <span class="info-value">{{ batch.room?.name || '—' }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Campus</span>
                    <span class="info-value">{{ batch.campus_location || '—' }}</span>
                  </div>
                </template>
                <template v-if="batch.mode === 'online' || batch.mode === 'hybrid'">
                  <div class="info-item">
                    <span class="info-label">Platform</span>
                    <span class="info-value">{{ batch.platform || '—' }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Meeting Link</span>
                    <span class="info-value">
                      <a v-if="batch.meeting_link" :href="batch.meeting_link" target="_blank" class="link">{{ batch.meeting_link }}</a>
                      <span v-else>—</span>
                    </span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Recording</span>
                    <span :class="['status-indicator', batch.recording_available ? 'yes' : 'no']">
                      <span class="indicator-dot"></span>
                      {{ batch.recording_available ? 'Available' : 'Not Available' }}
                    </span>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="col-side">
          <!-- Teacher Card -->
          <div class="card">
            <div class="card-header">
              <h3><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 9C11.2091 9 13 7.20914 13 5C13 2.79086 11.2091 1 9 1C6.79086 1 5 2.79086 5 5C5 7.20914 6.79086 9 9 9Z" stroke="currentColor" stroke-width="1.5"/><path d="M2 17C2 13.6863 5.13401 11 9 11C12.866 11 16 13.6863 16 17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              Teacher</h3>
            </div>
            <div class="card-body">
              <div v-if="batch.teacher" class="teacher-info">
                <div class="teacher-avatar">{{ teacherInitials }}</div>
                <div class="teacher-details">
                  <span class="teacher-name">{{ teacherName }}</span>
                  <span class="teacher-meta" v-if="batch.teacher.qualification">{{ batch.teacher.qualification }}</span>
                </div>
              </div>
              <div v-else class="empty-teacher">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none"><path d="M16 16C19.3137 16 22 13.3137 22 10C22 6.68629 19.3137 4 16 4C12.6863 4 10 6.68629 10 10C10 13.3137 12.6863 16 16 16Z" stroke="#D0D5DD" stroke-width="2"/><path d="M4 28C4 23.5817 9.37258 20 16 20C22.6274 20 28 23.5817 28 28" stroke="#D0D5DD" stroke-width="2" stroke-linecap="round"/></svg>
                <span class="empty-label">Not Assigned</span>
              </div>
            </div>
          </div>

          <!-- Created By Card -->
          <div class="card">
            <div class="card-header">
              <h3><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 9C11.2091 9 13 7.20914 13 5C13 2.79086 11.2091 1 9 1C6.79086 1 5 2.79086 5 5C5 7.20914 6.79086 9 9 9Z" stroke="currentColor" stroke-width="1.5"/><path d="M2 17C2 13.6863 5.13401 11 9 11C12.866 11 16 13.6863 16 17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              Created By</h3>
            </div>
            <div class="card-body">
              <div class="creator-info">
                <div class="creator-avatar">{{ creatorInitials }}</div>
                <div class="creator-details">
                  <span class="creator-name">{{ batch.created_by?.name || 'System' }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick Stats Card -->
          <div class="card">
            <div class="card-header">
              <h3><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3 15H15M3 15L3 9M3 15L7 11L10 14L15 8M15 15V5M15 8L12 5L9 8L6 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Statistics</h3>
            </div>
            <div class="card-body">
              <div class="stats-list">
                <div class="stat-row">
                  <span class="stat-key">Enrolled</span>
                  <span class="stat-num">{{ batch.enrolled_count || 0 }}</span>
                </div>
                <div class="stat-row">
                  <span class="stat-key">Available Seats</span>
                  <span class="stat-num">{{ (batch.capacity || 0) - (batch.enrolled_count || 0) }}</span>
                </div>
                <div class="stat-row">
                  <span class="stat-key">Occupancy</span>
                  <span class="stat-num">{{ capacityPercent }}%</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Enrolled Students Section -->
      <div class="card full-width">
        <div class="card-header">
          <h3>
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M12 6C12 8.20914 10.2091 10 8 10C5.79086 10 4 8.20914 4 6C4 3.79086 5.79086 2 8 2C10.2091 2 12 3.79086 12 6Z" stroke="currentColor" stroke-width="1.5"/><path d="M15 6C15 7.65685 13.6569 9 12 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M2 16C2 13.2386 4.68629 11 8 11C11.3137 11 14 13.2386 14 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14 11C16.2091 11 18 12.7909 18 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            Enrolled Students
            <span class="count-badge">{{ students.length }}</span>
          </h3>
        </div>
        <div class="card-body p-0">
          <div v-if="students.length === 0" class="empty-state">
            <svg width="48" height="48" viewBox="0 0 48 48" fill="none"><path d="M32 16C32 21.5228 27.5228 26 22 26C16.4772 26 12 21.5228 12 16C12 10.4772 16.4772 6 22 6C27.5228 6 32 10.4772 32 16Z" stroke="#D0D5DD" stroke-width="2.5"/><path d="M6 42C6 35.3726 13.1634 30 22 30C30.8366 30 38 35.3726 38 42" stroke="#D0D5DD" stroke-width="2.5" stroke-linecap="round"/><path d="M36 22H44M40 18V26" stroke="#D0D5DD" stroke-width="2.5" stroke-linecap="round"/></svg>
            <p>No students enrolled in this batch</p>
          </div>
          <div v-else class="table-wrapper">
            <table class="table">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Enrollment No</th>
                  <th>Status</th>
                  <th>Enrolled At</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="student in students" :key="student.id">
                  <td>
                    <div class="student-cell">
                      <div class="student-avatar">{{ studentInitials(student) }}</div>
                      <span>{{ studentName(student) }}</span>
                    </div>
                  </td>
                  <td><span class="code-badge">{{ student.enrollment_no }}</span></td>
                  <td>
                    <span :class="['status-tag', student.status]">
                      <span class="tag-dot"></span>
                      {{ student.status }}
                    </span>
                  </td>
                  <td class="text-muted">{{ student.created_at ? new Date(student.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import enrollmentService from '@/services/enrollment.service';
import { exportToExcel, exportToPDF, printTable } from '@/utils/export.utils';

export default {
  name: 'BatchDetailsPage',
  data() {
    return {
      batch: {},
      students: [],
      loading: true,
      allDays: [
        { key: 'Sat', label: 'Sat' },
        { key: 'Sun', label: 'Sun' },
        { key: 'Mon', label: 'Mon' },
        { key: 'Tue', label: 'Tue' },
        { key: 'Wed', label: 'Wed' },
        { key: 'Thu', label: 'Thu' },
        { key: 'Fri', label: 'Fri' },
      ],
    };
  },
  computed: {
    capacityPercent() {
      if (!this.batch.capacity) return 0;
      return Math.min(Math.round((this.batch.enrolled_count / this.batch.capacity) * 100), 100);
    },
    teacherName() {
      const t = this.batch.teacher;
      if (!t) return 'Not Assigned';
      if (t.user?.name) return t.user.name;
      if (t.first_name || t.last_name) return `${t.first_name || ''} ${t.last_name || ''}`.trim();
      return 'Not Assigned';
    },
    teacherInitials() {
      const t = this.batch.teacher;
      if (!t) return '?';
      const name = this.teacherName;
      if (name === 'Not Assigned') return '?';
      const parts = name.split(' ');
      return parts.map(p => p[0]).join('').toUpperCase().slice(0, 2);
    },
    creatorInitials() {
      const name = this.batch.created_by?.name;
      if (!name) return 'S';
      const parts = name.split(' ');
      return parts.map(p => p[0]).join('').toUpperCase().slice(0, 2);
    },
  },
  async created() {
    await this.loadBatch();
  },
  methods: {
    async loadBatch() {
      this.loading = true;
      try {
        const res = await enrollmentService.getBatch(this.$route.params.id);
        this.batch = res.data.data;
        const studentsRes = await enrollmentService.getBatchStudents(this.$route.params.id);
        this.students = studentsRes.data.data?.enrollments || [];
      } catch (e) {
        console.error(e);
        this.$toast?.error('Failed to load batch details');
      } finally {
        this.loading = false;
      }
    },
    studentName(enrollment) {
      const s = enrollment.student;
      if (!s) return '-';
      if (s.user?.name) return s.user.name;
      if (s.first_name || s.last_name) return `${s.first_name || ''} ${s.last_name || ''}`.trim();
      return s.name || '-';
    },
    studentInitials(enrollment) {
      const name = this.studentName(enrollment);
      if (name === '-' || !name) return '?';
      const parts = name.split(' ');
      return parts.map(p => p[0]).join('').toUpperCase().slice(0, 2);
    },
    confirmDelete() {
      if (confirm(`Delete batch "${this.batch.name}"?`)) {
        enrollmentService.deleteBatch(this.batch.id).then(() => {
          this.$toast?.success('Batch deleted successfully');
          this.$router.push('/dashboard/enrollment/batches');
        });
      }
    },
    exportExcel() {
      const b = this.batch;
      // Batch info sheet
      const infoHeaders = ['Property', 'Value'];
      const infoRows = [
        ['Name', b.name || ''],
        ['Code', b.code || ''],
        ['Course', b.course?.name || '—'],
        ['Mode', b.mode || ''],
        ['Status', b.status || ''],
        ['Capacity', String(b.capacity || 0)],
        ['Enrolled', String(b.enrolled_count || 0)],
        ['Available Seats', String((b.capacity || 0) - (b.enrolled_count || 0))],
        ['Occupancy', `${this.capacityPercent}%`],
        ['Teacher', this.teacherName],
        ['Academic Session', b.academic_session?.name || '—'],
        ['Shift', b.shift ? b.shift.charAt(0).toUpperCase() + b.shift.slice(1) : '—'],
        ['Schedule', (b.days||[]).join(', ') + (b.start_time ? ` ${b.start_time}–${b.end_time}` : '')],
        ['Start Date', b.start_date || '—'],
        ['End Date', b.end_date || '—'],
        ['Room', b.room?.name || '—'],
        ['Campus', b.campus_location || '—'],
        ['Platform', b.platform || '—'],
        ['Meeting Link', b.meeting_link || '—'],
      ];
      exportToExcel(infoHeaders, infoRows, `batch-${b.code || b.id}-info`);

      // Students sheet
      if (this.students.length > 0) {
        const stuHeaders = ['Student Name', 'Enrollment No', 'Status', 'Enrolled At'];
        const stuRows = this.students.map(s => [
          this.studentName(s),
          s.enrollment_no || '',
          s.status || '',
          s.created_at ? new Date(s.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—',
        ]);
        exportToExcel(stuHeaders, stuRows, `batch-${b.code || b.id}-students`);
      }
    },
    exportPDF() {
      const b = this.batch;
      const headers = ['Property', 'Value'];
      const rows = [
        ['Name', b.name || ''],
        ['Code', b.code || ''],
        ['Course', b.course?.name || '—'],
        ['Mode', b.mode || ''],
        ['Status', b.status || ''],
        ['Capacity', String(b.capacity || 0)],
        ['Enrolled', String(b.enrolled_count || 0)],
        ['Available Seats', String((b.capacity || 0) - (b.enrolled_count || 0))],
        ['Occupancy', `${this.capacityPercent}%`],
        ['Teacher', this.teacherName],
        ['Academic Session', b.academic_session?.name || '—'],
        ['Shift', b.shift ? b.shift.charAt(0).toUpperCase() + b.shift.slice(1) : '—'],
        ['Schedule', (b.days||[]).join(', ') + (b.start_time ? ` ${b.start_time}–${b.end_time}` : '')],
        ['Start Date', b.start_date || '—'],
        ['End Date', b.end_date || '—'],
        ['Room', b.room?.name || '—'],
        ['Campus', b.campus_location || '—'],
        ['Platform', b.platform || '—'],
        ['Meeting Link', b.meeting_link || '—'],
      ];
      if (this.students.length > 0) {
        rows.push(['—', '—']);
        rows.push(['ENROLLED STUDENTS', '']);
        this.students.forEach(s => {
          rows.push([
            this.studentName(s),
            s.enrollment_no || '',
            s.status || '',
            s.created_at ? new Date(s.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—',
          ]);
        });
      }
      exportToPDF(`Batch: ${b.name}`, headers, rows, `batch-${b.code || b.id}`);
    },
    printDetails() {
      const b = this.batch;
      const headers = ['Property', 'Value'];
      const rows = [
        ['Name', b.name || ''],
        ['Code', b.code || ''],
        ['Course', b.course?.name || '—'],
        ['Mode', b.mode || ''],
        ['Status', b.status || ''],
        ['Capacity', String(b.capacity || 0)],
        ['Enrolled', String(b.enrolled_count || 0)],
        ['Available Seats', String((b.capacity || 0) - (b.enrolled_count || 0))],
        ['Occupancy', `${this.capacityPercent}%`],
        ['Teacher', this.teacherName],
        ['Academic Session', b.academic_session?.name || '—'],
        ['Shift', b.shift ? b.shift.charAt(0).toUpperCase() + b.shift.slice(1) : '—'],
        ['Schedule', (b.days||[]).join(', ') + (b.start_time ? ` ${b.start_time}–${b.end_time}` : '')],
        ['Start Date', b.start_date || '—'],
        ['End Date', b.end_date || '—'],
        ['Room', b.room?.name || '—'],
        ['Campus', b.campus_location || '—'],
        ['Platform', b.platform || '—'],
        ['Meeting Link', b.meeting_link || '—'],
      ];
      if (this.students.length > 0) {
        rows.push(['—', '—']);
        rows.push(['ENROLLED STUDENTS', '']);
        this.students.forEach(s => {
          rows.push([
            this.studentName(s),
            s.enrollment_no || '',
            s.status || '',
            s.created_at ? new Date(s.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—',
          ]);
        });
      }
      printTable(`Batch: ${b.name}`, headers, rows);
    },
  },
};
</script>

<style scoped>
/* ===== Layout ===== */
.batch-details-page {
  max-width: 1100px;
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
}
.header-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.page-title {
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
  line-height: 1.3;
}
.page-subtitle {
  font-size: 0.82rem;
  color: var(--text-label);
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.sep { color: #d0d5dd; }
.header-actions {
  display: flex;
  gap: 0.5rem;
  flex-shrink: 0;
}

/* ===== Buttons ===== */
.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 0.4rem 0.75rem;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 500;
  color: var(--text-label);
  text-decoration: none;
  transition: all 0.15s;
  margin-top: 2px;
}
.btn-back:hover {
  background: #f2f4f7;
  color: #344054;
}
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
  text-decoration: none;
  border: none;
}
.btn-outline {
  border: 1px solid #d0d5dd;
  background: var(--bg-card);
  color: #344054;
}
.btn-outline:hover {
  border-color: #4a90d9;
  background: #f8faff;
}
.btn-danger-outline {
  border: 1px solid #fecdca;
  background: var(--bg-card);
  color: #dc2626;
}
.btn-danger-outline:hover {
  background: #fef2f2;
  border-color: #fca5a5;
}

/* ===== Loading ===== */
.loading-state {
  text-align: center;
  padding: 4rem 0;
}
.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e9eaed;
  border-top-color: #4a90d9;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  margin: 0 auto;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ===== Status Banner ===== */
.status-banner {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border-radius: 12px;
  margin-bottom: 1.25rem;
  font-size: 0.85rem;
}
.status-banner.open {
  background: #ecfdf5;
  color: #067647;
}
.status-banner.full {
  background: #fef2f2;
  color: #b42318;
}
.status-banner.closed {
  background: #f2f4f7;
  color: #475467;
}
.status-banner.upcoming {
  background: #fffbeb;
  color: #b54708;
}
.banner-icon {
  display: flex;
  align-items: center;
  flex-shrink: 0;
}
.banner-text {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.banner-text strong {
  font-size: 0.88rem;
  font-weight: 600;
}
.banner-text span {
  font-size: 0.75rem;
  opacity: 0.8;
}
.banner-seats {
  margin-left: auto;
  width: 120px;
  flex-shrink: 0;
}
.seat-progress {
  height: 6px;
  background: rgba(0,0,0,0.08);
  border-radius: 3px;
  overflow: hidden;
}
.seat-fill {
  height: 100%;
  border-radius: 3px;
  background: currentColor;
  transition: width 0.4s ease;
}

/* ===== Content Grid ===== */
.content-grid {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 1.25rem;
  margin-bottom: 1.25rem;
}
@media (max-width: 768px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
}

/* ===== Cards ===== */
.card {
  background: var(--bg-card);
  border: 1px solid #e8eaed;
  border-radius: 14px;
  overflow: hidden;
  transition: box-shadow 0.2s;
}
.card:hover {
  box-shadow: 0 2px 12px rgba(0,0,0,0.04);
}
.card.full-width {
  grid-column: 1 / -1;
}
.card-header {
  display: flex;
  align-items: center;
  padding: 0.9rem 1.15rem;
  border-bottom: 1px solid #f0f1f3;
}
.card-header h3 {
  font-size: 0.85rem;
  font-weight: 600;
  color: #344054;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.card-header h3 svg {
  color: var(--text-label);
  flex-shrink: 0;
}
.card-body {
  padding: 1.15rem;
}
.card-body.p-0 {
  padding: 0;
}

/* ===== Info Grid ===== */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 0.75rem;
}
.info-item {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.info-label {
  font-size: 0.68rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
.info-value {
  font-size: 0.88rem;
  font-weight: 500;
  color: #344054;
}

/* ===== Mode Tag ===== */
.mode-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.8rem;
  font-weight: 600;
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
}
.mode-tag.online {
  background: #eff8ff;
  color: #1d7fc8;
}
.mode-tag.offline {
  background: #ecfdf5;
  color: #12b76a;
}
.mode-tag.hybrid {
  background: #fffbeb;
  color: #d97706;
}
.mode-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
}

/* ===== Schedule ===== */
.schedule-display {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.schedule-days {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}
.day-chip {
  padding: 0.2rem 0.55rem;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-muted);
  background: #f2f4f7;
  transition: all 0.15s;
}
.day-chip.active {
  background: #eff8ff;
  color: #1d7fc8;
}
.schedule-time {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.82rem;
  color: #475467;
}
.schedule-time svg {
  color: var(--text-muted);
  flex-shrink: 0;
}

/* ===== Date Range ===== */
.date-range {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px solid #f0f1f3;
}
.date-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.date-lbl {
  font-size: 0.65rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.date-val {
  font-size: 0.82rem;
  font-weight: 500;
  color: #344054;
}
.date-arrow {
  color: #d0d5dd;
  display: flex;
}

/* ===== Links ===== */
.link {
  color: #4a90d9;
  text-decoration: none;
  font-weight: 500;
  word-break: break-all;
}
.link:hover {
  text-decoration: underline;
}

/* ===== Status Indicator ===== */
.status-indicator {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.8rem;
  font-weight: 500;
}
.status-indicator.yes { color: #12b76a; }
.status-indicator.no { color: var(--text-muted); }
.indicator-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
}

/* ===== Teacher ===== */
.teacher-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.teacher-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #4a90d9, #357abd);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  font-weight: 700;
  flex-shrink: 0;
}
.teacher-details {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.teacher-name {
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--text-primary);
}
.teacher-meta {
  font-size: 0.75rem;
  color: var(--text-label);
}
.empty-teacher {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0;
}
.empty-label {
  font-size: 0.82rem;
  color: var(--text-muted);
  font-weight: 500;
}

/* ===== Created By ===== */
.creator-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.creator-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #12b76a, #059669);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  font-weight: 700;
  flex-shrink: 0;
}
.creator-details {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.creator-name {
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--text-primary);
}

/* ===== Statistics ===== */
.stats-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.stat-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.35rem 0;
}
.stat-row + .stat-row {
  border-top: 1px solid #f0f1f3;
}
.stat-key {
  font-size: 0.82rem;
  color: var(--text-label);
  font-weight: 500;
}
.stat-num {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--text-primary);
}

/* ===== Count Badge ===== */
.count-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 10px;
  background: #4a90d9;
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  margin-left: 0.25rem;
}

/* ===== Empty State ===== */
.empty-state {
  text-align: center;
  padding: 2.5rem 1rem;
  color: var(--text-muted);
}
.empty-state p {
  margin: 0.75rem 0 0;
  font-size: 0.88rem;
}

/* ===== Table ===== */
.table-wrapper {
  overflow-x: auto;
}
.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
}
.table thead {
  background: var(--bg-accent);
}
.table th {
  text-align: left;
  padding: 0.65rem 1rem;
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-label);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  border-bottom: 1px solid #e8eaed;
}
.table td {
  padding: 0.65rem 1rem;
  color: #344054;
  border-bottom: 1px solid #f0f1f3;
}
.table tbody tr:last-child td {
  border-bottom: none;
}
.table tbody tr:hover {
  background: var(--bg-accent);
}

/* ===== Student Cell ===== */
.student-cell {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}
.student-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #f2f4f7;
  color: var(--text-label);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.72rem;
  font-weight: 700;
  flex-shrink: 0;
}

/* ===== Status Tag ===== */
.status-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0.15rem 0.55rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
}
.status-tag.active {
  background: #ecfdf5;
  color: #067647;
}
.status-tag.pending {
  background: #fffbeb;
  color: #b54708;
}
.status-tag.inactive {
  background: #f2f4f7;
  color: #475467;
}
.tag-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

/* ===== Text Muted ===== */
.text-muted {
  color: var(--text-label);
}

/* ===== Code Badge ===== */
.code-badge {
  display: inline-block;
  padding: 0.1rem 0.45rem;
  border-radius: 5px;
  background: #f2f4f7;
  color: #475467;
  font-size: 0.75rem;
  font-weight: 600;
  font-family: 'SF Mono', 'Consolas', monospace;
}
</style>

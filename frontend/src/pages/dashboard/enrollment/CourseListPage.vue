<template>
  <div class="course-list-page">
    <!-- Header -->
    <div class="top-bar">
      <div>
        <h1>📚 Courses</h1>
        <p class="text-muted">{{ pagination?.total || courses.length }} courses</p>
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
        <router-link to="/dashboard/enrollment/courses/create" class="btn btn-primary">+ Add Course</router-link>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-row" v-if="stats">
      <div class="stat-card"><span class="stat-val">{{ stats.total_courses }}</span><span class="stat-lbl">Total</span></div>
      <div class="stat-card green"><span class="stat-val">{{ stats.active_courses }}</span><span class="stat-lbl">Active</span></div>
      <div class="stat-card blue"><span class="stat-val">{{ stats.total_batches }}</span><span class="stat-lbl">Batches</span></div>
      <div class="stat-card purple"><span class="stat-val">{{ stats.total_students }}</span><span class="stat-lbl">Students</span></div>
    </div>

    <!-- Filters + Bulk Bar -->
    <div class="filters-bar">
      <input v-model="searchQuery" placeholder="🔍 Search by name or code..." @input="debouncedSearch" />
      <select v-model="filters.category" @change="loadCourses(1)">
        <option value="">All Categories</option>
        <option value="academic">📖 Academic</option>
        <option value="admission_coaching">🎯 Admission</option>
      </select>
      <select v-model="filters.status" @change="loadCourses(1)">
        <option value="">All Status</option>
        <option value="active">✅ Active</option>
        <option value="inactive">❌ Inactive</option>
      </select>
      <label class="archived-toggle">
        <input type="checkbox" v-model="filters.archived" @change="loadCourses(1)" />
        <span>📦 Show Archived</span>
      </label>
    </div>

    <!-- Bulk Actions -->
    <div v-if="selectedIds.length > 0" class="bulk-bar">
      <span>{{ selectedIds.length }} selected</span>
      <button class="btn btn-sm btn-success" @click="bulkAction('activate')">✅ Activate</button>
      <button class="btn btn-sm btn-outline" @click="bulkAction('archive')">📦 Archive</button>
      <button class="btn btn-sm btn-danger" @click="bulkAction('delete')">🗑 Delete</button>
      <button class="btn btn-sm" @click="selectedIds = []">✕ Clear</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="skeleton-wrap">
      <div v-for="n in 5" :key="n" class="sk-row"><span class="sk w40"/><span class="sk w200"/><span class="sk w80"/><span class="sk w100"/><span class="sk w80"/><span class="sk w40"/><span class="sk w40"/><span class="sk w50"/><span class="sk w60"/></div>
    </div>

    <!-- Empty -->
    <div v-else-if="courses.length === 0" class="empty-state">
      <div class="empty-icon">📭</div><h3>No courses found</h3>
    </div>

    <!-- List -->
    <div v-else class="course-list">
      <div class="list-header">
        <span class="col-chk"><input type="checkbox" :checked="allSelected" @change="toggleAll" /></span>
        <span class="col-thumb"></span>
        <span class="col-code">Code</span>
        <span class="col-name">Course Name</span>
        <span class="col-cat">Category</span>
        <span class="col-class">Class</span>
        <span class="col-duration">Duration</span>
        <span class="col-subs">Subs</span>
        <span class="col-batches">Batch</span>
        <span class="col-status">Status</span>
        <span class="col-actions">Actions</span>
      </div>

      <div v-for="course in courses" :key="course.id" class="list-row" :class="{ 'is-archived': course.status === 'inactive' || course.deleted_at }">
        <span class="col-chk" @click.stop><input type="checkbox" :checked="selectedIds.includes(course.id)" @change="toggleSelect(course.id)" /></span>
        <span class="col-thumb">
          <div class="course-thumb">
            <img v-if="getCourseThumb(course)" :src="getCourseThumb(course)" :alt="course.name" />
            <span v-else class="thumb-fallback">{{ getCourseInitial(course) }}</span>
          </div>
        </span>
        <span class="col-code"><span class="code-badge">{{ course.code }}</span></span>
        <span class="col-name" @click="$router.push(`/dashboard/enrollment/courses/${course.id}`)">
          <strong>{{ course.name }}</strong>
          <small v-if="course.short_description" class="row-desc">{{ course.short_description }}</small>
        </span>
        <span class="col-cat"><span :class="['tag', course.category === 'academic' ? 'tag-academic' : 'tag-admission']">{{ course.category === 'academic' ? 'Academic' : 'Admission' }}</span></span>
        <span class="col-class">{{ course.class?.name || course.target || '—' }}{{ course.group ? ' · '+course.group.name : '' }}</span>
        <span class="col-duration">{{ course.duration_label || '—' }}</span>
        <span class="col-subs">{{ course.subjects?.length || 0 }}</span>
        <span class="col-batches">{{ course.batches_count ?? course.batches?.length ?? 0 }}</span>
        <span class="col-status">
          <span :class="['status-dot', course.deleted_at ? 'inactive' : course.status]"></span>
          <span :class="['status-text', course.deleted_at ? 'inactive' : course.status]">{{ course.deleted_at ? 'archived' : course.status }}</span>
        </span>
        <span class="col-actions" @click.stop>
          <div class="dropdown" v-click-outside="() => openMenuId = null">
            <button class="btn-icon" @click="openMenuId = openMenuId === course.id ? null : course.id">⋮</button>
            <div v-if="openMenuId === course.id" class="dropdown-menu">
              <router-link :to="`/dashboard/enrollment/courses/${course.id}`" class="dm-item">👁 View</router-link>
              <router-link :to="`/dashboard/enrollment/courses/${course.id}/edit`" class="dm-item">✏️ Edit</router-link>
              <button v-if="!course.deleted_at" class="dm-item" @click="duplicateCourse(course)">📋 Duplicate</button>
              <button v-if="!course.deleted_at" class="dm-item" @click="archiveCourse(course)">📦 Archive</button>
              <button v-if="course.deleted_at" class="dm-item" @click="restoreCourse(course)">🔄 Restore</button>
              <button class="dm-item danger" @click="confirmDelete(course)">🗑 {{ course.deleted_at ? 'Permanent Delete' : 'Delete' }}</button>
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

    <!-- Delete Modal -->
    <modal v-if="showDeleteModal" @close="showDeleteModal = false">
      <template #header>Delete Course</template>
      <template #body><p>Delete <strong>{{ deletingCourse?.name }}</strong>?</p></template>
      <template #footer>
        <button @click="showDeleteModal=false" class="btn btn-secondary">Cancel</button>
        <button @click="deleteCourse" class="btn btn-danger" :disabled="deleting">{{ deleting?'Deleting...':'Delete' }}</button>
      </template>
    </modal>
  </div>
</template>

<script>
import enrollmentService from '@/services/enrollment.service';
import { debounce } from '@/utils/api.utils';
import { exportToExcel, exportToPDF, printTable } from '@/utils/export.utils';
import { getPhotoUrl } from '@/utils/photo.utils';

export default {
  name: 'CourseListPage',
  data() {
    return {
      courses: [], loading: false, searchQuery: '',
      filters: { category: '', status: '', archived: false },
      pagination: null, stats: null,
      selectedIds: [], openMenuId: null,
      showDeleteModal: false, deletingCourse: null, deleting: false,
    };
  },
  computed: {
    allSelected() { return this.courses.length > 0 && this.selectedIds.length === this.courses.length; },
  },
  created() {
    this.loadStats();
    this.loadCourses();
    this.debouncedSearch = debounce(() => this.loadCourses(), 300);
  },
  methods: {
    getCourseThumb(course) {
      return getPhotoUrl(course?.cover_image_url || course?.cover_image);
    },
    getCourseInitial(course) {
      return (course?.name?.charAt(0) || 'C').toUpperCase();
    },
    onPageChange(event) { this.loadCourses(event.page + 1); },
    async loadStats() {
      try { const r = await enrollmentService.getCourseStatistics(); this.stats = r.data?.data || r.data; } catch {}
    },
    async loadCourses(page = 1) {
      this.loading = true;
      try {
        const params = { page, per_page: 20, ...this.filters };
        if (!this.filters.archived) params.status = this.filters.status || 'active';
        if (this.searchQuery) params.search = this.searchQuery;
        if (this.filters.archived) params.trashed = 'with';
        const r = await enrollmentService.getCourses(params);
        this.courses = r.data?.data || r.data || [];
        this.pagination = r.data?.meta || null;
      } catch (e) { console.error(e); }
      finally { this.loading = false; }
    },
    toggleAll() {
      this.selectedIds = this.allSelected ? [] : this.courses.map(c => c.id);
    },
    toggleSelect(id) {
      const i = this.selectedIds.indexOf(id);
      i > -1 ? this.selectedIds.splice(i, 1) : this.selectedIds.push(id);
    },
    async bulkAction(action) {
      if (!confirm(`${action} ${this.selectedIds.length} courses?`)) return;
      try {
        await enrollmentService.bulkActionCourses({ ids: this.selectedIds, action });
        this.selectedIds = [];
        this.loadCourses(); this.loadStats();
      } catch (e) { alert('Bulk action failed'); }
    },
    async exportCsv() {
      try {
        const r = await enrollmentService.exportCourses();
        const url = window.URL.createObjectURL(new Blob([r.data]));
        const a = document.createElement('a'); a.href = url; a.download = 'courses-export.csv'; a.click();
        window.URL.revokeObjectURL(url);
      } catch { alert('Export failed'); }
    },
    exportExcel() {
      const headers = ['Code', 'Course Name', 'Category', 'Class', 'Duration', 'Subjects', 'Batches', 'Status'];
      const rows = this.courses.map(c => [
        c.code || '',
        c.name || '',
        c.category === 'academic' ? 'Academic' : 'Admission',
        c.class?.name || c.target || '—',
        c.duration_label || '—',
        c.subjects?.length || 0,
        c.batches_count ?? c.batches?.length ?? 0,
        c.deleted_at ? 'archived' : c.status || '',
      ]);
      exportToExcel(headers, rows, `courses-${new Date().toISOString().slice(0, 10)}`);
    },
    exportPDF() {
      const headers = ['Code', 'Course Name', 'Category', 'Class', 'Duration', 'Subjects', 'Batches', 'Status'];
      const rows = this.courses.map(c => [
        c.code || '',
        c.name || '',
        c.category === 'academic' ? 'Academic' : 'Admission',
        c.class?.name || c.target || '—',
        c.duration_label || '—',
        c.subjects?.length || 0,
        c.batches_count ?? c.batches?.length ?? 0,
        c.deleted_at ? 'archived' : c.status || '',
      ]);
      exportToPDF('Courses List', headers, rows, `courses-${new Date().toISOString().slice(0, 10)}`);
    },
    printList() {
      const headers = ['Code', 'Course Name', 'Category', 'Class', 'Duration', 'Subjects', 'Batches', 'Status'];
      const rows = this.courses.map(c => [
        c.code || '',
        c.name || '',
        c.category === 'academic' ? 'Academic' : 'Admission',
        c.class?.name || c.target || '—',
        c.duration_label || '—',
        c.subjects?.length || 0,
        c.batches_count ?? c.batches?.length ?? 0,
        c.deleted_at ? 'archived' : c.status || '',
      ]);
      printTable('Courses List', headers, rows);
    },
    async archiveCourse(c) {
      await enrollmentService.bulkActionCourses({ ids: [c.id], action: 'archive' });
      this.loadCourses(); this.loadStats();
    },
    async restoreCourse(c) {
      await enrollmentService.restoreCourse(c.id);
      this.loadCourses(); this.loadStats();
    },
    async duplicateCourse(c) {
      try {
        await enrollmentService.duplicateCourse(c.id);
        this.loadCourses(); this.loadStats();
      } catch (e) { alert('Duplicate failed'); }
    },
    confirmDelete(c) { this.deletingCourse = c; this.showDeleteModal = true; },
    async deleteCourse() {
      this.deleting = true;
      try {
        await enrollmentService.deleteCourse(this.deletingCourse.id);
        this.showDeleteModal = false;
        this.loadCourses(); this.loadStats();
      } catch (e) { alert('Delete failed'); }
      finally { this.deleting = false; }
    },
  },
  directives: {
    'click-outside': {
      mounted(el, binding) {
        el._clickOutside = (e) => { if (!el.contains(e.target)) binding.value(); };
        document.addEventListener('click', el._clickOutside);
      },
      unmounted(el) { document.removeEventListener('click', el._clickOutside); },
    },
  },
};
</script>

<style scoped>
.course-list-page { max-width: 1200px; }

.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
.top-bar h1 { margin: 0; font-size: 1.35rem; }
.top-actions { display: flex; gap: 0.5rem; }
.text-muted { color: #888; font-size: 0.85rem; }

.btn-primary { background: #4a90d9; color: #fff; border: none; padding: 0.6rem 1.2rem; border-radius: 10px; cursor: pointer; font-size: 0.9rem; font-weight: 600; text-decoration: none; display: inline-block; }
.btn-outline { border: 1px solid #d0d5dd; background: var(--bg-card); color: #344054; padding: 0.6rem 1.1rem; border-radius: 10px; cursor: pointer; font-size: 0.88rem; font-weight: 500; }
.btn-outline:hover { border-color: #4a90d9; color: #4a90d9; }
.btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 6px; cursor: pointer; border: none; }
.btn-success { background: #12b76a; color: #fff; }
.btn-danger { background: #f04438; color: #fff; }

/* Stats */
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; margin-bottom: 1rem; }
.stat-card { background: var(--bg-card); border: 1px solid #e8eaed; border-radius: 12px; padding: 0.9rem 1rem; text-align: center; }
.stat-card.green { border-left: 4px solid #12b76a; }
.stat-card.blue { border-left: 4px solid #4a90d9; }
.stat-card.purple { border-left: 4px solid #7c3aed; }
.stat-val { display: block; font-size: 1.5rem; font-weight: 700; color: var(--text-primary); }
.stat-lbl { font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

/* Filters */
.filters-bar { display: flex; gap: 0.65rem; margin-bottom: 0.75rem; flex-wrap: wrap; align-items: center; }
.filters-bar input { flex: 1; min-width: 200px; padding: 0.55rem 0.9rem; border: 1px solid #d0d5dd; border-radius: 8px; font-size: 0.88rem; }
.filters-bar select { padding: 0.55rem 0.9rem; border: 1px solid #d0d5dd; border-radius: 8px; font-size: 0.85rem; background: var(--bg-card); cursor: pointer; }
.archived-toggle { display: flex; align-items: center; gap: 0.35rem; font-size: 0.82rem; cursor: pointer; color: var(--text-label); }

/* Bulk bar */
.bulk-bar { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: #eff8ff; border: 1px solid #bcd4f0; border-radius: 10px; margin-bottom: 0.75rem; font-size: 0.85rem; }

/* List */
.list-header, .list-row {
  display: grid;
  grid-template-columns: 36px 52px 100px 1fr 90px 110px 80px 50px 50px 70px 46px;
  align-items: center; gap: 0.4rem; padding: 0.6rem 0.75rem; font-size: 0.85rem;
}
.course-thumb {
  width: 44px; height: 32px; border-radius: 6px; overflow: hidden;
  background: linear-gradient(135deg, #dbeafe, #e0e7ff);
  border: 1px solid var(--border-color); flex-shrink: 0;
}
.course-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.thumb-fallback {
  width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
  font-size: 0.75rem; font-weight: 700; color: #4f46e5;
}
.list-header { background: #f2f4f7; border-radius: 10px 10px 0 0; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--text-label); }
.list-row { border-bottom: 1px solid #f0f1f3; cursor: pointer; transition: background 0.12s; }
.list-row:last-child { border-bottom: none; }
.list-row:hover { background: var(--bg-surface-muted); }
.list-row.is-archived { opacity: 0.65; background: #fefbf6; }

.code-badge { font-family: monospace; font-size: 0.72rem; background: #f2f4f7; padding: 0.15rem 0.45rem; border-radius: 5px; color: #475467; }
.col-name strong { display: block; color: var(--text-primary); font-size: 0.88rem; cursor: pointer; }
.col-name strong:hover { color: #4a90d9; }
.row-desc { color: var(--text-muted); font-size: 0.72rem; }

.tag { display: inline-block; padding: 0.12rem 0.5rem; border-radius: 5px; font-size: 0.7rem; font-weight: 600; }
.tag-academic { background: #eff8ff; color: #1d7fc8; }
.tag-admission { background: #fff5e6; color: #d97706; }

.status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 5px; }
.status-dot.active { background: #12b76a; }
.status-dot.inactive { background: #f04438; }
.status-text { font-size: 0.78rem; text-transform: capitalize; }
.status-text.active { color: #12b76a; }
.status-text.inactive { color: #f04438; }

/* Dropdown menu */
.dropdown { position: relative; display: inline-block; }
.btn-icon { background: none; border: none; cursor: pointer; font-size: 1.1rem; padding: 0.25rem 0.4rem; border-radius: 6px; color: var(--text-label); }
.btn-icon:hover { background: #f2f4f7; }
.dropdown-menu { position: absolute; right: 0; top: 100%; z-index: 20; background: var(--bg-card); border: 1px solid #e8eaed; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); min-width: 160px; padding: 0.3rem 0; margin-top: 4px; }
.dm-item { display: block; width: 100%; padding: 0.5rem 1rem; text-align: left; border: none; background: none; cursor: pointer; font-size: 0.82rem; color: #344054; text-decoration: none; }
.dm-item:hover { background: var(--bg-accent); }
.dm-item.danger { color: #f04438; }

/* Skeleton */
.skeleton-wrap { display: flex; flex-direction: column; gap: 0.5rem; }
.sk-row { display: flex; gap: 0.5rem; padding: 0.7rem; background: var(--bg-card); border-radius: 8px; }
.sk { height: 14px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 4px; display: inline-block; }
.w40{width:40px}.w50{width:50px}.w60{width:60px}.w80{width:80px}.w100{width:100px}.w200{width:200px}
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0}}

.empty-state, .loading-state { text-align: center; padding: 3rem; }
.empty-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
.pagination-footer { margin-top: 1rem; display: flex; justify-content: center; }
</style>

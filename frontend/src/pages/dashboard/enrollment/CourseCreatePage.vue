<template>
  <div class="course-form-page">
    <div class="page-header">
      <h1>{{ isEdit ? 'Edit Course' : 'Create Course' }}</h1>
    </div>

    <!-- Tabs -->
    <div class="tab-nav">
      <button :class="['tab', { active: tab === 1 }]" @click="tab = 1">1. Basic Info</button>
      <button :class="['tab', { active: tab === 2 }]" @click="tab = 2">2. Subjects & Content</button>
      <button :class="['tab', { active: tab === 3 }]" @click="tab = 3">3. Media & SEO</button>
    </div>

    <form @submit.prevent="submitForm">
      <!-- TAB 1: Basic Info -->
      <div v-show="tab === 1" class="tab-panel">
        <div class="form-row">
          <div class="form-group col-6"><label class="form-label">Course Name <span class="req">*</span></label><input v-model="form.name" class="form-input" required /></div>
          <div class="form-group col-6"><label class="form-label">Category <span class="req">*</span></label><select v-model="form.category" class="form-select" required><option value="">Select</option><option value="academic">Academic</option><option value="admission_coaching">Admission Coaching</option></select></div>
        </div>

        <div class="form-row" v-if="form.category === 'academic'">
          <div class="form-group col-4"><label class="form-label">Class</label><select v-model="form.class_id" class="form-select" @change="onClassChange"><option value="">Select</option><option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
          <div class="form-group col-4" v-if="showGroup"><label class="form-label">Group</label><select v-model="form.group_id" class="form-select" @change="onGroupChange"><option value="">Select</option><option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option></select></div>
          <div class="form-group col-4" v-if="showGroup"><label class="form-label">Session (Education Year)</label><select v-model="form.session_id" class="form-select"><option value="">Select</option><option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
          <div class="form-group col-4" v-if="!showGroup"><label class="form-label">Level</label><select v-model="form.level" class="form-select"><option value="">Select</option><option value="beginner">Beginner</option><option value="intermediate">Intermediate</option><option value="advanced">Advanced</option></select></div>
        </div>

        <div class="form-group" v-if="form.category === 'admission_coaching'">
          <label class="form-label">Target</label><input v-model="form.target" class="form-input" placeholder="e.g. Medical (MBBS), Engineering (BUET)" />
        </div>

        <div class="form-row">
          <div class="form-group col-6"><label class="form-label">Duration Label</label><input v-model="form.duration_label" class="form-input" placeholder="e.g. 1 Year, 6 Months" /></div>
          <div class="form-group col-6"><label class="form-label">Duration (Days)</label><input v-model.number="form.duration_days" type="number" class="form-input" /></div>
        </div>

        <div class="form-row">
          <div class="form-group col-6">
            <label class="form-check"><input v-model="form.has_online" type="checkbox" /> Has Online</label>
          </div>
          <div class="form-group col-6">
            <label class="form-check"><input v-model="form.has_offline" type="checkbox" /> Has Offline</label>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Status</label>
          <select v-model="form.status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>

        <div class="tab-actions">
          <button type="button" class="btn btn-primary" @click="tab = 2">Continue →</button>
        </div>
      </div>

      <!-- TAB 2: Subjects & Content -->
      <div v-show="tab === 2" class="tab-panel">
        <div class="form-section">
          <h4>💰 Fee Type</h4>
          <div class="form-row">
            <div class="form-group col-6"><label class="form-label">Fee Type</label><select v-model="form.fee_type" class="form-select" @change="onFeeTypeChange"><option value="monthly">Monthly</option><option value="one_time">One-Time</option></select></div>
            <div class="form-group col-6"><label class="form-label">Enrollment Fee / Admission Fee (৳)</label><input v-model.number="form.enrollment_fee" type="number" min="0" class="form-input" placeholder="e.g., 2000" /></div>
          </div>
          <div class="form-row" v-if="form.fee_type === 'one_time'">
            <div class="form-group col-6"><label class="form-label">Total Course Fee (৳) <span class="req">*</span></label><input v-model.number="form.one_time_fee" type="number" min="0" class="form-input" placeholder="e.g., 40000" /></div>
          </div>
        </div>

        <div class="form-section">
          <h4>📚 Subjects & Teachers <span v-if="form.group_id" class="text-muted">— Group-wise</span></h4>
          <div v-for="(item, idx) in form.subjects" :key="idx" class="subject-row">
            <div style="flex:2"><select v-model="item.subject_id" class="form-select" required @change="onSubjectChange(idx)"><option value="">Subject</option><option v-for="s in availableSubjects" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
            <div style="flex:1" v-if="form.fee_type === 'monthly'"><input v-model.number="item.monthly_fee" type="number" class="form-input" placeholder="Monthly Fee (৳)" /></div>
            <div style="flex:1"><select v-model="item.teacher_id" class="form-select"><option value="">Teacher</option><option v-for="t in getTeachersForRow(item, idx)" :key="t.id" :value="t.id">{{ t.first_name }} {{ t.last_name }}</option></select></div>
            <div style="flex:0 0 auto"><label class="form-check"><input v-model="item.is_mandatory" type="checkbox" /> Mandatory</label></div>
            <button type="button" class="btn-remove" @click="form.subjects.splice(idx,1)">✕</button>
          </div>
          <button type="button" class="btn-add" @click="addSubjectRow">+ Add Subject</button>
        </div>

        <div class="form-section">
          <h4>📝 Description</h4>
          <div class="form-group"><label class="form-label">Short Description</label><input v-model="form.short_description" class="form-input" /></div>
          <div class="form-group"><label class="form-label">Full Description</label><textarea v-model="form.description" class="form-textarea" rows="3"></textarea></div>
        </div>

        <div class="form-section">
          <h4>🎯 Learning Outcomes</h4>
          <textarea v-model="form.learning_outcomes" class="form-textarea" rows="2" placeholder="What students will learn..."></textarea>
        </div>
        <div class="form-section">
          <h4>📋 Syllabus</h4>
          <textarea v-model="form.syllabus" class="form-textarea" rows="2" placeholder="Course syllabus overview..."></textarea>
        </div>

        <div class="tab-actions">
          <button type="button" class="btn btn-outline" @click="tab = 1">← Back</button>
          <button type="button" class="btn btn-primary" @click="tab = 3">Continue →</button>
        </div>
      </div>

      <!-- TAB 3: Media & SEO -->
      <div v-show="tab === 3" class="tab-panel">
        <div class="form-section">
          <h4>🖼 Course Thumbnail</h4>
          <p class="field-hint">Upload an image for landing page course cards. Recommended: 16:9, at least 800×450px.</p>
          <div class="thumbnail-upload">
            <div class="thumbnail-preview" @click="$refs.coverImageInput.click()">
              <img v-if="coverImagePreview" :src="coverImagePreview" alt="Course thumbnail preview" />
              <div v-else class="thumbnail-placeholder">
                <span class="thumb-icon">🖼️</span>
                <span>Click to upload thumbnail</span>
              </div>
            </div>
            <input
              ref="coverImageInput"
              type="file"
              accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
              style="display: none"
              @change="onCoverImageChange"
            />
            <button v-if="coverImagePreview" type="button" class="btn-remove-thumb" @click="removeCoverImage">Remove image</button>
          </div>
          <div class="form-group"><label class="form-check"><input v-model="form.is_featured" type="checkbox" /> Featured Course</label></div>
          <div class="form-group"><label class="form-label">Sort Order</label><input v-model.number="form.sort_order" type="number" class="form-input" /></div>
        </div>

        <div class="form-section">
          <h4>🔍 SEO</h4>
          <div class="form-group"><label class="form-label">Meta Title</label><input v-model="form.meta_title" class="form-input" placeholder="Browser tab title" /></div>
          <div class="form-group"><label class="form-label">Meta Description</label><textarea v-model="form.meta_description" class="form-textarea" rows="2" placeholder="Search engine description"></textarea></div>
        </div>

        <div v-if="errorMsg" class="alert-danger">{{ errorMsg }}</div>

        <div class="tab-actions">
          <button type="button" class="btn btn-outline" @click="tab = 2">← Back</button>
          <button type="submit" class="btn btn-primary btn-lg" :disabled="submitting">{{ submitting ? 'Saving...' : (isEdit ? 'Update Course' : 'Create Course') }}</button>
        </div>
      </div>
    </form>
  </div>
</template>

<script>
import enrollmentService from '@/services/enrollment.service';
import academicService from '@/services/academic.service';
import teacherService from '@/services/teacher.service';
import { extractData } from '@/utils/api.utils';
import { getPhotoUrl } from '@/utils/photo.utils';

export default {
  name: 'CourseCreatePage',
  data() {
    return {
      isEdit: false, courseId: null, tab: 1, submitting: false, errorMsg: '',
      coverImageFile: null, coverImagePreview: null, removeCoverImageFlag: false,
      classes: [], groups: [], groupSubjects: [], allTeachers: [], sessions: [],
      /** @type {Record<number, Array<{id:string,first_name:string,last_name:string}>>} */
      teachersByRow: {},
      form: {
        name: '', category: '', level: '', class_id: '', group_id: '', session_id: '', target: '',
        fee_type: 'monthly', one_time_fee: null, enrollment_fee: 0,
        has_online: true, has_offline: true, duration_days: null, duration_label: '',
        cover_image: '',
        description: '', short_description: '', meta_title: '', meta_description: '',
        learning_outcomes: '', syllabus: '', is_featured: false, sort_order: 0, status: 'active', subjects: [],
      },
    };
  },
  computed: {
    showGroup() {
      if (!this.form.class_id) return false;
      const c = this.classes.find(cl => cl.id === this.form.class_id);
      return parseInt(c?.numeric_value || c?.name?.match(/\d+/)?.[0] || 0) >= 9;
    },
    availableSubjects() {
      // If a class is selected, use groupSubjects (populated by onClassChange or onGroupChange)
      if (this.form.class_id) return this.groupSubjects;
      return [];
    },
  },
  watch: { 'form.class_id'() { if (!this.showGroup) this.form.group_id = ''; } },
  async created() {
    this.courseId = this.$route.params.id;
    if (this.courseId) { this.isEdit = true; await this.loadCourse(); }
    await this.loadRefData();
  },
  methods: {
    /**
     * Returns the list of teachers for a given subject row.
     * - If no subject selected → show all teachers (so user can pick one)
     * - If subject selected AND filtered teachers exist → show only those
     * - If subject selected BUT no filtered teachers → show empty (no match)
     */
    getTeachersForRow(item, idx) {
      if (!item.subject_id) return this.allTeachers;
      return this.teachersByRow[idx] || [];
    },
    async loadRefData() {
      try {
        const cRes = await academicService.getClasses({ per_page: 100 }).catch(()=>null);
        const gRes = await academicService.getGroups({ per_page: 100 }).catch(()=>null);
        const sRes = await academicService.sessions.list({ per_page: 50 }).catch(()=>null);
        const tRes = await teacherService.listAll({ per_page: 200 }).catch(()=>null);
        this.classes = cRes?.data?.data || cRes?.data || [];
        this.groups = gRes?.data?.data || gRes?.data || [];
        this.sessions = sRes?.data?.data || sRes?.data || [];
        const tBody = tRes?.data;
        this.allTeachers = tBody?.data || tBody || [];
      } catch(e) { console.error('loadRefData error:', e); }
    },
    async loadCourse() {
      try {
        const r = await enrollmentService.getCourse(this.courseId);
        const c = r.data?.data || r.data;
        Object.keys(this.form).forEach(k => { if (c[k] !== undefined) this.form[k] = c[k] || (typeof this.form[k] === 'number' ? null : this.form[k]); });
        this.form.subjects = (c.subjects || []).map(s => ({ subject_id: s.id, fee: s.pivot?.fee || 0, monthly_fee: s.pivot?.monthly_fee || 0, teacher_id: s.pivot?.teacher_id || '', is_mandatory: s.pivot?.is_mandatory || false }));
        // Load subjects for the course's class (and group if set)
        if (this.form.class_id) {
          const params = { per_page: 200 };
          if (this.form.group_id) params.group_id = this.form.group_id;
          const sRes = await academicService.subjects.byClass(this.form.class_id, params);
          this.groupSubjects = sRes.data?.data || sRes.data || [];
        }
        const thumb = c.cover_image_url || c.cover_image;
        this.coverImagePreview = thumb ? getPhotoUrl(thumb) : null;
        this.coverImageFile = null;
        this.removeCoverImageFlag = false;
      } catch (e) { console.error(e); }
    },
    onCoverImageChange(event) {
      const file = event.target.files?.[0];
      if (!file) return;
      this.coverImageFile = file;
      this.removeCoverImageFlag = false;
      const reader = new FileReader();
      reader.onload = (e) => { this.coverImagePreview = e.target.result; };
      reader.readAsDataURL(file);
    },
    removeCoverImage() {
      this.coverImageFile = null;
      this.coverImagePreview = null;
      this.form.cover_image = '';
      this.removeCoverImageFlag = true;
      if (this.$refs.coverImageInput) this.$refs.coverImageInput.value = '';
    },
    buildCoursePayload() {
      const payload = { ...this.form };
      const subjects = payload.subjects || [];
      delete payload.subjects;
      ['class_id', 'group_id', 'session_id', 'target', 'description', 'short_description', 'meta_title', 'meta_description', 'learning_outcomes', 'syllabus', 'duration_days', 'duration_label', 'level'].forEach((f) => {
        if (payload[f] === '') payload[f] = null;
      });
      if (payload.fee_type !== 'one_time') {
        delete payload.one_time_fee;
      }
      if (this.removeCoverImageFlag) {
        payload.remove_cover_image = true;
      }
      delete payload.cover_image;
      return { payload, subjects };
    },
    appendCourseFormData(payload) {
      const fd = new FormData();
      Object.entries(payload).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') return;
        if (typeof value === 'boolean') {
          fd.append(key, value ? '1' : '0');
        } else if (key === 'remove_cover_image' && value) {
          fd.append(key, '1');
        } else if (key !== 'remove_cover_image') {
          fd.append(key, value);
        }
      });
      if (this.coverImageFile) {
        fd.append('cover_image', this.coverImageFile);
      }
      return fd;
    },
    async onClassChange() {
      this.form.group_id = '';
      this.groupSubjects = [];
      this.form.subjects = [];
      this.teachersByRow = {};
      // Load subjects for this class (without group filter)
      if (this.form.class_id) {
        try {
          const r = await academicService.subjects.byClass(this.form.class_id, { per_page: 200 });
          this.groupSubjects = r.data?.data || r.data || [];
        } catch {}
      }
    },
    async onGroupChange() {
      this.form.subjects = [];
      this.teachersByRow = {};
      this.groupSubjects = [];
      if (this.form.group_id && this.form.class_id) {
        try {
          const r = await academicService.subjects.byClass(this.form.class_id, { group_id: this.form.group_id, per_page: 200 });
          this.groupSubjects = r.data?.data || r.data || [];
        } catch {}
      }
    },
    onFeeTypeChange() {
      // Keep subjects for both fee types; just reset fee values on switch
    },
    addSubjectRow() {
      this.form.subjects.push({ subject_id: '', fee: 0, monthly_fee: 0, teacher_id: '', is_mandatory: false });
    },
    /**
     * When a subject is selected in a row, filter teachers client-side
     * using the subjects array loaded with allTeachers (from listAll endpoint).
     */
    onSubjectChange(idx) {
      const item = this.form.subjects[idx];
      item.teacher_id = '';
      if (!item.subject_id) {
        this.teachersByRow[idx] = [];
        return;
      }
      teacherService.bySubject(item.subject_id).then(res => {
        this.teachersByRow[idx] = extractData(res, []);
      }).catch(e => {
        console.error('onSubjectChange failed:', e);
        this.teachersByRow[idx] = [];
      });
    },
    async submitForm() {
      this.submitting = true; this.errorMsg = '';
      try {
        const { payload, subjects } = this.buildCoursePayload();
        const useFormData = !!this.coverImageFile;

        let courseId = this.courseId;
        if (this.isEdit) {
          const body = useFormData ? this.appendCourseFormData(payload) : payload;
          await enrollmentService.updateCourse(courseId, body);
        } else {
          const body = useFormData ? this.appendCourseFormData(payload) : payload;
          const r = await enrollmentService.createCourse(body);
          courseId = r.data?.data?.id || r.data?.id;
        }

        if (subjects.length && courseId) { await enrollmentService.assignSubjects(courseId, subjects); }
        this.$toast?.success(this.isEdit ? 'Course updated' : 'Course created');
        this.$router.push('/dashboard/enrollment/courses');
      } catch (e) {
        this.errorMsg = e.response?.data?.message || e.message || 'Failed to save course';
      } finally { this.submitting = false; }
    },
  },
};
</script>

<style scoped>
.course-form-page { max-width: 850px; }
.page-header { margin-bottom: 1rem; }
.page-header h1 { font-size: 1.3rem; margin: 0; }

.tab-nav { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; }
.tab { flex: 1; padding: 0.65rem; border: 1px solid #e0e0e0; border-radius: 10px; background: var(--bg-accent); cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--text-label); transition: all 0.2s; }
.tab:hover { border-color: #4a90d9; color: #4a90d9; }
.tab.active { background: #4a90d9; color: #fff; border-color: #4a90d9; }

.tab-panel { background: var(--bg-card); border-radius: 14px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }

.form-row { display: flex; gap: 1rem; margin-bottom: 0.75rem; }
.form-group { flex: 1; margin-bottom: 0.75rem; }
.form-label { display: block; font-size: 0.8rem; font-weight: 600; color: #555; margin-bottom: 0.25rem; }
.form-input, .form-select, .form-textarea { width: 100%; padding: 0.55rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; }
.form-textarea { resize: vertical; }
.req { color: #e74c3c; }
.form-check { display: flex; align-items: center; gap: 0.35rem; font-size: 0.88rem; cursor: pointer; }

.form-section { margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid #f0f0f0; }
.form-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.form-section h4 { font-size: 0.95rem; margin: 0 0 0.5rem 0; }
.text-muted { color: #888; font-size: 0.8rem; }

.subject-row { display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem; }
.btn-remove { background: none; border: 1px solid #e74c3c; color: #e74c3c; border-radius: 6px; cursor: pointer; padding: 0.3rem 0.5rem; font-size: 0.8rem; }
.btn-add { border: 1px dashed #4a90d9; background: none; color: #4a90d9; padding: 0.4rem 0.75rem; border-radius: 6px; cursor: pointer; font-size: 0.8rem; margin-top: 0.25rem; }

.tab-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
.btn-outline { border: 1px solid #d0d5dd; background: var(--bg-card); color: #344054; padding: 0.6rem 1.2rem; border-radius: 10px; cursor: pointer; font-weight: 600; }
.btn-primary { background: #4a90d9; color: #fff; border: none; padding: 0.65rem 1.5rem; border-radius: 10px; cursor: pointer; font-weight: 600; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-lg { padding: 0.85rem 2rem; font-size: 1rem; }
.alert-danger { background: #fef2f2; color: #dc2626; padding: 0.75rem; border-radius: 8px; margin-top: 0.75rem; font-size: 0.85rem; }
.col-4 { flex: 0 0 calc(33.33% - 0.67rem); }
.col-6 { flex: 0 0 calc(50% - 0.5rem); }

.field-hint { margin: 0 0 0.75rem; font-size: 0.78rem; color: var(--text-label); }
.thumbnail-upload { display: flex; flex-direction: column; align-items: flex-start; gap: 0.5rem; margin-bottom: 1rem; }
.thumbnail-preview {
  width: 100%; max-width: 360px; aspect-ratio: 16 / 9;
  border: 2px dashed #cbd5e1; border-radius: 12px; overflow: hidden;
  cursor: pointer; background: var(--bg-surface-muted);
}
.thumbnail-preview img { width: 100%; height: 100%; object-fit: cover; }
.thumbnail-placeholder {
  width: 100%; height: 100%; display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 0.4rem;
  color: var(--text-muted); font-size: 0.82rem;
}
.thumb-icon { font-size: 1.75rem; }
.btn-remove-thumb {
  border: 1px solid #e74c3c; background: var(--bg-card); color: #e74c3c;
  padding: 0.35rem 0.75rem; border-radius: 6px; cursor: pointer; font-size: 0.8rem;
}
</style>

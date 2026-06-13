<template>
  <div class="batch-form-page">
    <div class="page-header"><h1>{{ isEdit ? 'Edit Batch' : 'Create Batch' }}</h1></div>

    <!-- Loading overlay -->
    <div v-if="loading" class="loading-overlay">
      <div class="loading-spinner"></div>
      <p>Loading form data...</p>
    </div>

    <template v-if="!loading">
      <!-- Tabs -->
      <div class="tab-nav">
        <button :class="['tab', { active: step === 1 }]" type="button" @click="goToStep(1)">1. Basic</button>
        <button :class="['tab', { active: step === 2 }]" type="button" @click="goToStep(2)">2. Schedule</button>
        <button :class="['tab', { active: step === 3 }]" type="button" @click="goToStep(3)">3. Seats</button>
        <button :class="['tab', { active: step === 4 }]" type="button" @click="goToStep(4)">4. Fee</button>
      </div>

      <div v-if="errorMsg" class="alert-danger" style="margin-bottom: 1rem;">{{ errorMsg }}</div>

      <form @submit.prevent="submitForm">
        <!-- STEP 1: Basic -->
        <div v-show="step === 1" class="tab-panel">
          <div class="form-row">
            <div class="form-group col-6"><label>Course <span class="req">*</span></label><select v-model="form.course_id" class="form-select" required @change="onCourseChange"><option value="">Select</option><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option></select></div>
            <div class="form-group col-6"><label>Batch Name <span class="req">*</span></label><input v-model="form.name" class="form-input" required /></div>
          </div>
          <div class="form-row">
            <div class="form-group col-4"><label>Mode <span class="req">*</span></label><select v-model="form.mode" class="form-select" required><option value="online">Online</option><option value="offline">Offline</option><option value="hybrid">Hybrid</option></select></div>
            <div class="form-group col-4"><label>Shift</label><select v-model="form.shift" class="form-select"><option value="">Select</option><option value="morning">Morning</option><option value="afternoon">Afternoon</option><option value="evening">Evening</option></select></div>
            <div class="form-group col-4"><label>Teacher <span class="text-muted">(course-wise)</span></label><select v-model="form.teacher_id" class="form-select"><option value="">Select</option><option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.first_name }} {{ t.last_name }}</option></select></div>
          </div>
          <div class="form-row">
            <div class="form-group col-6"><label>Session <span class="text-muted">(auto from course)</span></label><select v-model="form.academic_session_id" class="form-select"><option value="">Select session...</option><option v-for="s in academicSessions" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
            <div class="form-group col-6"><label>Status</label><select v-model="form.status" class="form-select"><option value="open">Open</option><option value="upcoming">Upcoming</option><option value="closed">Closed</option></select></div>
          </div>
          <div class="tab-actions"><button type="button" class="btn btn-primary" @click.stop="goToStep(2)">Continue →</button></div>
        </div>

        <!-- STEP 2: Schedule -->
        <div v-show="step === 2" class="tab-panel">
          <h4>📅 Class Days</h4>
          <div class="day-chips">
            <button v-for="d in dayOptions" :key="d" type="button" :class="['day-chip', { active: (form.days||[]).includes(d) }]" @click="toggleDay(d)">{{ d.slice(0,3) }}</button>
          </div>
          <div class="form-row mt">
            <div class="form-group col-4"><label>Start Time</label><input v-model="form.start_time" type="time" class="form-input" /></div>
            <div class="form-group col-4"><label>End Time</label><input v-model="form.end_time" type="time" class="form-input" /></div>
          </div>
          <div class="form-row">
            <div class="form-group col-4"><label>Start Date</label><input v-model="form.start_date" type="date" class="form-input" /></div>
            <div class="form-group col-4"><label>End Date</label><input v-model="form.end_date" type="date" class="form-input" /></div>
          </div>

          <!-- Offline mode -->
          <template v-if="form.mode === 'offline' || form.mode === 'hybrid'">
            <h4>🏫 Location</h4>
            <div class="form-row">
              <div class="form-group col-6"><label>Room</label><select v-model="form.room_id" class="form-select"><option value="">Select</option><option v-for="r in (rooms||[]).filter(Boolean)" :key="r.id" :value="r.id">{{ r.name }}</option></select></div>
              <div class="form-group col-6"><label>Campus Location</label><input v-model="form.campus_location" class="form-input" /></div>
            </div>
          </template>

          <!-- Online mode -->
          <template v-if="form.mode === 'online' || form.mode === 'hybrid'">
            <h4>🖥 Online Setup</h4>
            <div class="form-row">
              <div class="form-group col-6"><label>Platform</label><input v-model="form.platform" class="form-input" placeholder="Zoom, Meet..." /></div>
              <div class="form-group col-6"><label>Meeting Link</label><input v-model="form.meeting_link" class="form-input" /></div>
            </div>
            <label class="form-check"><input v-model="form.recording_available" type="checkbox" /> Recording Available</label>
          </template>

          <div class="tab-actions"><button type="button" class="btn btn-outline" @click.stop="goToStep(1)">← Back</button><button type="button" class="btn btn-primary" @click.stop="goToStep(3)">Continue →</button></div>
        </div>

        <!-- STEP 3: Seats -->
        <div v-show="step === 3" class="tab-panel">
          <h4>👥 Seat Management</h4>
          <div class="form-row">
            <div class="form-group col-4"><label>Total Seats <span class="req">*</span></label><input v-model.number="form.capacity" type="number" class="form-input" min="1" required /></div>
            <div class="form-group col-4"><label>Current Enrolled</label><input :value="form.enrolled_count" type="number" class="form-input" disabled /></div>
            <div class="form-group col-4"><label>Waiting Limit</label><input v-model.number="form.waiting_limit" type="number" class="form-input" min="0" /></div>
          </div>
          <div class="seat-preview" v-if="form.capacity">
            <div class="seat-label">Available: <strong>{{ (form.capacity||0) - (form.enrolled_count||0) }}</strong> / {{ form.capacity }}</div>
            <div class="seat-bar"><div class="seat-fill" :style="{width: ((form.enrolled_count||0)/(form.capacity||1)*100)+'%'}"></div></div>
          </div>

          <div class="tab-actions"><button type="button" class="btn btn-outline" @click.stop="goToStep(2)">← Back</button><button type="button" class="btn btn-primary" @click.stop="goToStep(4)">Continue →</button></div>
        </div>

        <!-- STEP 4: Fee & Submit -->
        <div v-show="step === 4" class="tab-panel">
          <p class="text-muted">Course subjects already have fees assigned. Custom per-batch fees can be added later from the Fee module.</p>
          <div class="tab-actions"><button type="button" class="btn btn-outline" @click.stop="goToStep(3)">← Back</button><button type="submit" class="btn btn-primary btn-lg" :disabled="submitting">{{ submitting ? 'Saving...' : (isEdit ? 'Update Batch' : 'Create Batch') }}</button></div>
        </div>
      </form>
    </template>
  </div>
</template>

<script>
import enrollmentService from '@/services/enrollment.service';
import academicService from '@/services/academic.service';
import teacherService from '@/services/teacher.service';

export default {
  name: 'BatchCreatePage',
  data() {
    return {
      isEdit: false, batchId: null, step: 1, submitting: false, loading: true, errorMsg: '',
      courses: [], rooms: [], academicSessions: [], teachers: [],
      dayOptions: ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'],
      form: {
        course_id:'', name:'', mode:'offline', shift:'', teacher_id:'', academic_session_id:'',
        status:'open', days:[], start_time:'', end_time:'', start_date:'', end_date:'',
        room_id:'', campus_location:'', platform:'', meeting_link:'', recording_available:true,
        capacity:30, enrolled_count:0, waiting_limit:5,
      },
    };
  },
  async created() {
    console.log('[BatchCreate] Component created, route:', this.$route?.fullPath);
    this.batchId = this.$route?.params?.id;
    const dupId = this.$route?.query?.duplicate_from;
    try {
      if (this.batchId) { this.isEdit = true; await this.loadBatch(); }
      else if (dupId) { this.batchId = dupId; await this.loadBatch(); }
      await Promise.all([this.loadCourses(), this.loadRooms(), this.loadSessions(), this.loadTeachers()]);
    } catch (e) {
      console.error('[BatchCreate] Initialization error:', e);
      this.errorMsg = 'Failed to load form data. Please check console for details.';
    } finally {
      this.loading = false;
    }
  },
  methods: {
    goToStep(s) {
      console.log('[BatchCreate] goToStep:', s, 'current step:', this.step);
      this.step = s;
    },
    async onCourseChange() {
      this.form.teacher_id = '';
      this.form.academic_session_id = '';
      this.teachers = [];
      if (!this.form.course_id) return;
      try {
        // Load course details to get subjects and session
        const res = await enrollmentService.getCourse(this.form.course_id);
        const course = res.data?.data || res.data || {};
        // Auto-select current active academic session
        const currentSession = this.academicSessions.find(s => s.is_current);
        if (currentSession) {
          this.form.academic_session_id = currentSession.id;
        }
        // Load teachers for this course's subjects
        const subjects = course.subjects || [];
        if (subjects.length > 0) {
          const teacherMap = new Map();
          const teacherPromises = subjects.map(s =>
            teacherService.bySubject(s.id).then(r => {
              const data = r.data?.data || r.data || [];
              (Array.isArray(data) ? data : []).forEach(t => teacherMap.set(t.id, t));
            }).catch(() => {})
          );
          await Promise.all(teacherPromises);
          this.teachers = Array.from(teacherMap.values());
        }
      } catch (e) {
        console.error('[BatchCreate] onCourseChange error:', e);
      }
    },
    toggleDay(d) {
      const arr = this.form.days || []; const i = arr.indexOf(d);
      i > -1 ? arr.splice(i,1) : arr.push(d); this.form.days = [...arr];
    },
    async loadCourses() {
      try {
        const r = await enrollmentService.listAllCourses();
        // listAllCourses -> CourseController::listAll() -> $this->success($courses)
        // Response: { status, message, data: [courses...] }
        const body = r?.data;
        const data = body?.data || body || [];
        this.courses = Array.isArray(data) ? data : [];
        console.log('[BatchCreate] Courses loaded:', this.courses.length);
      } catch (e) { console.error('[BatchCreate] loadCourses error:', e); }
    },
    async loadRooms() {
      try {
        const r = await academicService.getRooms({ per_page: 100 });
        // RoomController::index() uses $this->success($rooms) where $rooms is a paginator.
        // Laravel serializes paginator as: { current_page, data: [...], total, ... }
        // So response is: { status, message, data: { current_page, data: [...], ... } }
        const body = r?.data;
        const paginatorObj = body?.data || body || [];
        const items = paginatorObj?.data || paginatorObj || [];
        this.rooms = Array.isArray(items) ? items : [];
        console.log('[BatchCreate] Rooms loaded:', this.rooms.length);
      } catch (e) { console.error('[BatchCreate] loadRooms error:', e); }
    },
    async loadSessions() {
      try {
        const r = await academicService.sessions.list({ per_page: 20 });
        const body = r?.data;
        const items = body?.data || body || [];
        this.academicSessions = Array.isArray(items) ? items : [];
        console.log('[BatchCreate] Sessions loaded:', this.academicSessions.length);
      } catch (e) { console.error('[BatchCreate] loadSessions error:', e); }
    },
    async loadTeachers() {
      try {
        let teachers = [];
        try {
          // teacherService.getTeachers() -> getAll() -> returns body?.data || body || []
          // body.data is the paginator items array (from paginatedResponse)
          const r = await teacherService.getTeachers({ per_page: 200 });
          teachers = Array.isArray(r) ? r : (r?.data || []);
        } catch (e1) {
          console.warn('[BatchCreate] getTeachers failed, trying listAll:', e1);
          // Fallback: teacherService.listAll() returns raw axios response
          // TeacherController::listAll() -> $this->collectionResponse($teachers)
          // Response: { status, message, data: [teachers...] }
          const r2 = await teacherService.listAll();
          const body = r2?.data;
          teachers = body?.data || body || [];
        }
        this.teachers = Array.isArray(teachers) ? teachers : [];
        console.log('[BatchCreate] Teachers loaded:', this.teachers.length);
      } catch (e) { console.error('[BatchCreate] loadTeachers error:', e); }
    },
    async loadBatch() {
      try {
        const r = await enrollmentService.getBatch(this.batchId);
        const b = r?.data?.data || r?.data || {};
        Object.keys(this.form).forEach(k => {
          if (b[k] !== undefined && b[k] !== null) this.form[k] = b[k];
        });
        if (typeof this.form.days === 'string') this.form.days = JSON.parse(this.form.days || '[]');
        console.log('[BatchCreate] Batch loaded for editing:', this.batchId);
      } catch (e) {
        console.error('[BatchCreate] loadBatch error:', e);
      }
    },
    async submitForm() {
      this.submitting = true; this.errorMsg = '';
      try {
        const payload = { ...this.form };
        const nullableFields = ['shift','campus_location','platform','meeting_link','start_time','end_time',
          'start_date','end_date','teacher_id','room_id','academic_session_id','waiting_limit'];
        nullableFields.forEach(f => { if (payload[f] === '' || payload[f] === undefined) payload[f] = null; });
        if (!Array.isArray(payload.days)) payload.days = [];
        delete payload.enrolled_count;

        console.log('[BatchCreate] Submitting payload:', JSON.stringify(payload, null, 2));

        if (this.isEdit) { await enrollmentService.updateBatch(this.batchId, payload); }
        else { await enrollmentService.createBatch(payload); }
        this.$toast?.success(this.isEdit ? 'Batch updated' : 'Batch created');
        this.$router.push('/dashboard/enrollment/batches');
      } catch (e) {
        console.error('[BatchCreate] Save failed:', e);
        console.error('[BatchCreate] Response data:', JSON.stringify(e.response?.data, null, 2));
        console.error('[BatchCreate] Response status:', e.response?.status);
        const errData = e.response?.data;
        if (errData?.errors) {
          const msgs = Object.values(errData.errors).flat().join(', ');
          this.errorMsg = msgs;
        } else {
          this.errorMsg = errData?.message || e.message || 'Failed to save batch';
        }
      } finally { this.submitting = false; }
    },
  },
};
</script>

<style scoped>
.batch-form-page { max-width: 850px; }
.page-header { margin-bottom: 1rem; }
.page-header h1 { font-size: 1.3rem; margin: 0; }

/* Loading overlay */
.loading-overlay {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  background: var(--bg-card);
  border-radius: 14px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.loading-overlay p { color: #888; font-size: 0.9rem; margin-top: 1rem; }
.loading-spinner {
  width: 36px; height: 36px;
  border: 3px solid #e0e0e0;
  border-top-color: #4a90d9;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.tab-nav { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; }
.tab { flex: 1; padding: 0.6rem; border: 1px solid #e0e0e0; border-radius: 10px; background: var(--bg-accent); cursor: pointer; font-size: 0.83rem; font-weight: 600; color: var(--text-label); }
.tab:hover { border-color: #4a90d9; }
.tab.active { background: #4a90d9; color: #fff; border-color: #4a90d9; }

.tab-panel { background: var(--bg-card); border-radius: 14px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }

.form-row { display: flex; gap: 1rem; margin-bottom: 0.75rem; }
.form-group { flex: 1; margin-bottom: 0.75rem; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: #555; margin-bottom: 0.25rem; }
.form-input, .form-select { width: 100%; padding: 0.55rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; }
.req { color: #e74c3c; }

.day-chips { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 0.75rem; }
.day-chip { padding: 0.4rem 0.7rem; border: 1px solid #d0d5dd; border-radius: 8px; background: var(--bg-card); cursor: pointer; font-size: 0.8rem; font-weight: 600; }
.day-chip:hover { border-color: #4a90d9; }
.day-chip.active { background: #4a90d9; color: #fff; border-color: #4a90d9; }
.mt { margin-top: 0.5rem; }

.form-check { display: flex; align-items: center; gap: 0.35rem; font-size: 0.88rem; cursor: pointer; margin-top: 0.5rem; }

.seat-preview { margin-top: 0.75rem; }
.seat-bar { height: 8px; background: #f2f4f7; border-radius: 4px; overflow: hidden; margin-top: 0.25rem; }
.seat-fill { height: 100%; background: #4a90d9; border-radius: 4px; transition: width 0.3s; }
.seat-label { font-size: 0.85rem; }

.tab-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.25rem; }
.btn-outline { border: 1px solid #d0d5dd; background: var(--bg-card); color: #344054; padding: 0.55rem 1.2rem; border-radius: 10px; cursor: pointer; font-weight: 600; }
.btn-primary { background: #4a90d9; color: #fff; border: none; padding: 0.6rem 1.3rem; border-radius: 10px; cursor: pointer; font-weight: 600; }
.btn-primary:disabled { opacity: 0.5; }
.btn-lg { padding: 0.8rem 2rem; font-size: 1rem; }
.alert-danger { background: #fef2f2; color: #dc2626; padding: 0.75rem; border-radius: 8px; margin-bottom: 0.75rem; font-size: 0.85rem; }
.text-muted { color: #888; font-size: 0.85rem; }
.col-4 { flex: 0 0 calc(33.33% - 0.67rem); }
.col-6 { flex: 0 0 calc(50% - 0.5rem); }
h4 { font-size: 0.9rem; margin: 0 0 0.5rem 0; }
</style>

<template>
  <div class="admit-card-container">
    <div v-if="loading" class="admit-loading">
      <div class="loading-spinner"></div>
      <p>Loading admit card...</p>
    </div>

    <div v-else-if="error" class="admit-error">
      <p>{{ error }}</p>
    </div>

    <div v-else-if="admitData" class="admit-card" ref="admitCardRef">
      <!-- Decorative corners -->
      <div class="corner corner-tl"></div>
      <div class="corner corner-tr"></div>
      <div class="corner corner-bl"></div>
      <div class="corner corner-br"></div>

      <header class="card-hero">
        <div class="hero-pattern"></div>
        <div class="hero-content">
          <p class="institution-name">{{ institutionName }}</p>
          <p v-if="institutionAddress" class="institution-address">{{ institutionAddress }}</p>
          <div class="admit-badge">
            <span class="badge-icon">✦</span>
            ADMIT CARD
            <span class="badge-icon">✦</span>
          </div>
          <h1 class="exam-title">{{ admitData.exam?.name || 'Examination' }}</h1>
          <div class="exam-tags">
            <span v-if="examTypeLabel" class="tag tag-type">{{ examTypeLabel }}</span>
            <span v-if="admitData.exam?.session" class="tag tag-session">{{ admitData.exam.session }}</span>
          </div>
        </div>
      </header>

      <section class="card-body">
        <div class="student-panel">
          <div class="photo-frame">
            <div class="photo-ring">
              <img
                v-if="admitData.student?.photo_url"
                :src="admitData.student.photo_url"
                alt="Student"
                class="photo-img"
              />
              <div v-else class="photo-placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
            </div>
          </div>

          <div class="info-grid">
            <div class="info-card">
              <span class="info-label">Student Name</span>
              <span class="info-value">{{ studentName }}</span>
            </div>
            <div class="info-card">
              <span class="info-label">Student ID</span>
              <span class="info-value accent">{{ admitData.student?.student_id || '—' }}</span>
            </div>
            <div v-if="admitData.student?.roll_no" class="info-card">
              <span class="info-label">Roll No</span>
              <span class="info-value">{{ admitData.student.roll_no }}</span>
            </div>
            <div v-if="admitData.student?.batch?.name" class="info-card">
              <span class="info-label">Batch</span>
              <span class="info-value">{{ admitData.student.batch.name }}</span>
            </div>
            <div v-if="admitData.student?.course?.name" class="info-card">
              <span class="info-label">Course</span>
              <span class="info-value">{{ admitData.student.course.name }}</span>
            </div>
            <div v-if="admitData.student?.class?.name" class="info-card">
              <span class="info-label">Class</span>
              <span class="info-value">{{ admitData.student.class.name }}</span>
            </div>
          </div>
        </div>

        <div class="exam-highlight">
          <div class="highlight-icon">📅</div>
          <div>
            <span class="highlight-label">Examination Period</span>
            <span class="highlight-value">{{ examPeriod }}</span>
          </div>
        </div>

        <div class="validity-strip">
          <span class="validity-dot"></span>
          Valid for <strong>{{ admitData.exam?.name }}</strong> only
          <span class="validity-dot"></span>
        </div>

        <div class="instructions">
          <h3>Before you go to the exam hall</h3>
          <ul>
            <li>Carry this admit card and a valid photo ID.</li>
            <li>Arrive at least 15 minutes before your paper starts.</li>
            <li>Check <strong>My Exam Routine</strong> on the portal for your subject schedule.</li>
            <li>Mobile phones and unauthorized materials are not allowed.</li>
          </ul>
        </div>

        <div class="signatures">
          <div class="sig-block">
            <div class="sig-line"></div>
            <span>Exam Controller</span>
          </div>
          <div class="sig-block">
            <div class="sig-line"></div>
            <span>Principal / Director</span>
          </div>
          <div class="sig-block">
            <div class="sig-line"></div>
            <span>Student's Signature</span>
          </div>
        </div>
      </section>

      <footer class="card-footer">
        <span>Issued {{ admitData.generated_at || '—' }}</span>
        <span class="footer-sep">•</span>
        <span>Official examination document</span>
      </footer>

      <div v-if="showDownload" class="card-actions no-print">
        <button type="button" class="btn-download" :disabled="downloading" @click="$emit('download')">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          {{ downloading ? 'Preparing PDF...' : 'Download PDF' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  admitData: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
  downloading: { type: Boolean, default: false },
  showDownload: { type: Boolean, default: true },
})

defineEmits(['download'])

const institutionName = computed(() => props.admitData?.institution?.name || 'Coaching Management System')
const institutionAddress = computed(() => props.admitData?.institution?.address || '')
const examTypeLabel = computed(() => props.admitData?.exam?.exam_type?.name || props.admitData?.exam?.type || '')

const studentName = computed(() => {
  const s = props.admitData?.student
  if (!s) return '—'
  return s.name || `${s.first_name || ''} ${s.last_name || ''}`.trim() || '—'
})

const examPeriod = computed(() => {
  const start = props.admitData?.exam?.start_date
  const end = props.admitData?.exam?.end_date
  if (!start) return 'To be announced'
  if (!end || start === end) return formatDate(start)
  return `${formatDate(start)} — ${formatDate(end)}`
})

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
}
</script>

<style scoped>
.admit-card-container {
  max-width: 520px;
  margin: 0 auto;
}

.admit-loading,
.admit-error {
  text-align: center;
  padding: 2.5rem;
  color: var(--text-muted);
}

.admit-error { color: #dc2626; }

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e9d5ff;
  border-top-color: #7c3aed;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  margin: 0 auto 0.75rem;
}

@keyframes spin { to { transform: rotate(360deg); } }

.admit-card {
  position: relative;
  border-radius: 20px;
  overflow: hidden;
  box-shadow:
    0 25px 50px -12px rgba(79, 70, 229, 0.35),
    0 0 0 1px rgba(139, 92, 246, 0.2);
  background: var(--bg-card);
}

.corner {
  position: absolute;
  width: 48px;
  height: 48px;
  z-index: 2;
  pointer-events: none;
}
.corner-tl { top: 0; left: 0; border-top: 4px solid #fbbf24; border-left: 4px solid #fbbf24; border-radius: 20px 0 0 0; }
.corner-tr { top: 0; right: 0; border-top: 4px solid #ec4899; border-right: 4px solid #ec4899; border-radius: 0 20px 0 0; }
.corner-bl { bottom: 0; left: 0; border-bottom: 4px solid #22d3ee; border-left: 4px solid #22d3ee; border-radius: 0 0 0 20px; }
.corner-br { bottom: 0; right: 0; border-bottom: 4px solid #a78bfa; border-right: 4px solid #a78bfa; border-radius: 0 0 20px 0; }

.card-hero {
  position: relative;
  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 45%, #db2777 100%);
  color: #fff;
  padding: 1.75rem 1.5rem 1.5rem;
  text-align: center;
  overflow: hidden;
}

.hero-pattern {
  position: absolute;
  inset: 0;
  opacity: 0.15;
  background-image:
    radial-gradient(circle at 20% 50%, #fff 2px, transparent 2px),
    radial-gradient(circle at 80% 30%, #fff 1px, transparent 1px);
  background-size: 24px 24px;
}

.hero-content { position: relative; z-index: 1; }

.institution-name {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.institution-address {
  margin: 0.2rem 0 0.75rem;
  font-size: 0.72rem;
  opacity: 0.9;
}

.admit-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: linear-gradient(180deg, #fde047 0%, #fbbf24 100%);
  color: #78350f;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.2em;
  padding: 0.4rem 1.1rem;
  border-radius: 999px;
  box-shadow: 0 4px 14px rgba(251, 191, 36, 0.5);
  margin-bottom: 0.65rem;
}

.badge-icon { font-size: 0.55rem; opacity: 0.8; }

.exam-title {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 800;
  line-height: 1.25;
  text-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.exam-tags {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.35rem;
  margin-top: 0.6rem;
}

.tag {
  font-size: 0.68rem;
  font-weight: 600;
  padding: 0.2rem 0.55rem;
  border-radius: 6px;
  background: rgba(255,255,255,0.2);
  backdrop-filter: blur(4px);
}

.tag-type { border: 1px solid rgba(255,255,255,0.35); }
.tag-session { background: rgba(34, 211, 238, 0.35); }

.card-body {
  padding: 1.25rem 1.35rem 1rem;
  background: linear-gradient(180deg, #faf5ff 0%, #fff 40%);
}

.student-panel {
  display: flex;
  gap: 1.25rem;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.photo-frame { flex-shrink: 0; }

.photo-ring {
  width: 108px;
  height: 128px;
  border-radius: 14px;
  padding: 4px;
  background: linear-gradient(135deg, #a78bfa, #ec4899, #22d3ee);
  box-shadow: 0 8px 24px rgba(124, 58, 237, 0.3);
}

.photo-img,
.photo-placeholder {
  width: 100%;
  height: 100%;
  border-radius: 10px;
  object-fit: cover;
  background: #f5f3ff;
}

.photo-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #a78bfa;
}

.photo-placeholder svg { width: 48px; height: 48px; }

.info-grid {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  min-width: 0;
}

.info-card {
  background: var(--bg-card);
  border-radius: 10px;
  padding: 0.45rem 0.65rem;
  border-left: 4px solid #8b5cf6;
  box-shadow: 0 1px 4px rgba(139, 92, 246, 0.12);
}

.info-label {
  display: block;
  font-size: 0.62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #7c3aed;
}

.info-value {
  display: block;
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--text-dark);
  margin-top: 0.1rem;
  word-break: break-word;
}

.info-value.accent {
  color: #5b21b6;
  font-family: ui-monospace, monospace;
  letter-spacing: 0.03em;
}

.exam-highlight {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.85rem 1rem;
  border-radius: 14px;
  background: linear-gradient(90deg, #ede9fe 0%, #fce7f3 100%);
  border: 1px solid #c4b5fd;
  margin-bottom: 0.75rem;
}

.highlight-icon { font-size: 1.5rem; }

.highlight-label {
  display: block;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #6d28d9;
}

.highlight-value {
  display: block;
  font-size: 0.95rem;
  font-weight: 800;
  color: #4c1d95;
  margin-top: 0.15rem;
}

.validity-strip {
  text-align: center;
  font-size: 0.72rem;
  color: #6d28d9;
  padding: 0.4rem;
  margin-bottom: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.validity-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #a78bfa;
}

.instructions {
  background: var(--bg-card);
  border: 2px dashed #c4b5fd;
  border-radius: 12px;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
}

.instructions h3 {
  margin: 0 0 0.4rem;
  font-size: 0.75rem;
  font-weight: 800;
  color: #6d28d9;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.instructions ul {
  margin: 0;
  padding-left: 1.1rem;
  font-size: 0.75rem;
  color: var(--text-secondary);
  line-height: 1.55;
}

.instructions strong { color: #7c3aed; }

.signatures {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.65rem;
}

.sig-block {
  text-align: center;
  font-size: 0.62rem;
  font-weight: 600;
  color: var(--text-muted);
}

.sig-line {
  height: 2.25rem;
  border-bottom: 1px solid #94a3b8;
  margin-bottom: 0.3rem;
}

.card-footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  padding: 0.55rem 1rem;
  background: linear-gradient(90deg, #4f46e5, #7c3aed);
  color: #e0e7ff;
  font-size: 0.68rem;
  font-weight: 500;
}

.footer-sep { opacity: 0.6; }

.card-actions {
  padding: 1rem 1.35rem 1.25rem;
  text-align: center;
  background: #faf5ff;
}

.btn-download {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.7rem 1.75rem;
  border: none;
  border-radius: 999px;
  font-size: 0.9rem;
  font-weight: 700;
  color: #fff;
  cursor: pointer;
  background: linear-gradient(135deg, #4f46e5 0%, #db2777 100%);
  box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
  transition: transform 0.15s, box-shadow 0.15s;
}

.btn-download:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(79, 70, 229, 0.45);
}

.btn-download:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

@media (max-width: 480px) {
  .student-panel {
    flex-direction: column;
    align-items: center;
  }
  .info-grid { width: 100%; }
}

@media print {
  .no-print { display: none !important; }
  .admit-card { box-shadow: none; }
  .card-actions { display: none; }
}
</style>

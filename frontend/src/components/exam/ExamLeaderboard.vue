<template>
  <div class="exam-leaderboard" :class="'mode-' + mode">
    <div v-if="loading" class="lb-loading">
      <span class="lb-spinner"></span>
      <span>Loading leaderboard…</span>
    </div>

    <div v-else-if="error" class="lb-error">{{ error }}</div>

    <div v-else-if="!rows.length" class="lb-empty">No leaderboard data available yet.</div>

    <template v-else>
      <p v-if="isProvisional" class="lb-provisional-banner">
        Unofficial MCQ standings — not final until results are officially published.
      </p>
      <div v-if="myStanding" class="lb-my-standing">
        <span class="lb-my-label">Your position</span>
        <span class="lb-my-rank">#{{ myStanding.position }}</span>
        <span class="lb-my-meta">
          of {{ myStanding.total_students || totalStudents }}
          · {{ myStanding.total_marks }}/{{ myStanding.total_possible }}
          · {{ myStanding.percentage }}%
          <template v-if="myStanding.overall_grade"> · {{ myStanding.overall_grade }}</template>
        </span>
      </div>

      <p v-if="disclaimer" class="lb-disclaimer">{{ disclaimer }}</p>

      <div class="lb-table-wrap">
        <table class="lb-table">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Roll</th>
              <th>Student</th>
              <th>Marks</th>
              <th>%</th>
              <th>GPA</th>
              <th>Grade</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, idx) in rows"
              :key="row.student_id + '-' + idx"
              :class="{
                'lb-row-me': row.is_me,
                'lb-row-top1': row.rank === 1,
                'lb-row-top2': row.rank === 2,
                'lb-row-top3': row.rank === 3,
                'lb-row-sep': row._separator,
              }"
            >
              <td v-if="row._separator" colspan="7" class="lb-separator">···</td>
              <template v-else>
                <td><strong>{{ row.rank }}</strong></td>
                <td>{{ row.roll_no || '—' }}</td>
                <td>
                  {{ row.student_name }}
                  <span v-if="row.is_me" class="lb-you-badge">You</span>
                </td>
                <td>{{ row.total_marks }}/{{ row.total_possible }}</td>
                <td>{{ row.percentage }}%</td>
                <td>{{ row.gpa }}</td>
                <td>{{ row.overall_grade }}</td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="scopeLabel" class="lb-scope">
        Scope: <strong>{{ scopeLabel }}</strong>
        <span v-if="totalStudents"> · {{ totalStudents }} students ranked</span>
      </p>

      <ExamSubjectToppers
        v-if="showSubjectToppers && subjectToppers.length"
        :toppers="subjectToppers"
      />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import examService from '@/services/exam.service'
import reportService from '@/services/report.service'
import ExamSubjectToppers from '@/components/exam/ExamSubjectToppers.vue'

const props = defineProps({
  mode: {
    type: String,
    default: 'student',
    validator: (v) => ['student', 'admin', 'teacher', 'provisional'].includes(v),
  },
  examId: { type: String, required: true },
  deliveryChannel: { type: String, default: 'offline' },
  scopeType: { type: String, default: '' },
  scopeId: { type: String, default: '' },
  top: { type: Number, default: 50 },
  autoLoad: { type: Boolean, default: true },
  showSubjectToppers: { type: Boolean, default: false },
})

const loading = ref(false)
const error = ref(null)
const payload = ref(null)

const myStanding = computed(() => payload.value?.my_standing ?? null)
const disclaimer = computed(() => payload.value?.disclaimer ?? '')
const totalStudents = computed(() => payload.value?.total_students ?? 0)
const scopeLabel = computed(() => payload.value?.merit_scope?.label ?? payload.value?.scope?.label ?? '')
const subjectToppers = computed(() => payload.value?.subject_toppers ?? [])
const isProvisional = computed(() => !!payload.value?.is_provisional || props.mode === 'provisional')

const rows = computed(() => {
  const list = payload.value?.leaderboard ?? payload.value?.merit_list ?? []
  if (!list.length) return []

  const hasSelfOutsideTop = list.some((row) => row.is_me)
    && myStanding.value
    && !myStanding.value.in_top_list

  if (!hasSelfOutsideTop) {
    return list
  }

  const topRows = list.filter((row) => !row.is_me)
  return [
    ...topRows,
    { _separator: true, student_id: 'sep' },
    ...list.filter((row) => row.is_me),
  ]
})

async function load() {
  if (!props.examId) return
  loading.value = true
  error.value = null

  try {
    if (props.mode === 'provisional') {
      const res = await examService.student.provisionalLeaderboard(props.examId, {
        top: props.top,
      })
      payload.value = res.data?.data || res.data
    } else if (props.mode === 'student') {
      const res = await examService.student.leaderboard(props.examId, {
        delivery_channel: props.deliveryChannel,
        top: props.top,
      })
      payload.value = res.data?.data || res.data
    } else if (props.mode === 'teacher' || props.mode === 'admin') {
      const params = {
        delivery_channel: props.deliveryChannel,
        top: props.top === 0 ? 0 : (props.top || 50),
      }
      if (props.scopeType && props.scopeType !== 'all') {
        params.scope = props.scopeType
        if (props.scopeId) {
          params.scope_id = props.scopeId
        }
      } else {
        params.scope = 'all'
      }
      const res = await reportService.examMerit(props.examId, params)
      const data = res.data?.data || res.data
      payload.value = {
        ...data,
        leaderboard: data?.leaderboard ?? data?.merit_list ?? [],
      }
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load leaderboard'
    payload.value = null
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.examId, props.deliveryChannel, props.scopeType, props.scopeId, props.top, props.mode],
  () => {
    if (props.autoLoad) load()
  },
)

onMounted(() => {
  if (props.autoLoad) load()
})

defineExpose({ reload: load })
</script>

<style scoped>
.exam-leaderboard {
  margin-top: 0.75rem;
}

.lb-loading,
.lb-error,
.lb-empty {
  padding: 0.85rem 1rem;
  border-radius: 8px;
  font-size: 0.84rem;
}

.lb-loading {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-muted);
  background: var(--bg-surface-muted);
}

.lb-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid #e2e8f0;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: lb-spin 0.7s linear infinite;
}

@keyframes lb-spin {
  to { transform: rotate(360deg); }
}

.lb-error {
  background: #fef2f2;
  color: #b91c1c;
}

.lb-empty {
  background: var(--bg-surface-muted);
  color: var(--text-muted);
}

.lb-my-standing {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.35rem 0.65rem;
  padding: 0.75rem 1rem;
  margin-bottom: 0.65rem;
  border-radius: 8px;
  background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%);
  border: 1px solid #c7d2fe;
}

.lb-my-label {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6366f1;
}

.lb-my-rank {
  font-size: 1.35rem;
  font-weight: 800;
  color: #312e81;
  line-height: 1;
}

.lb-my-meta {
  font-size: 0.82rem;
  color: var(--text-secondary);
}

.lb-disclaimer {
  margin: 0 0 0.65rem;
  font-size: 0.76rem;
  color: var(--text-muted);
}

.lb-provisional-banner {
  margin: 0 0 0.65rem;
  padding: 0.55rem 0.75rem;
  border-radius: 8px;
  background: #fff7ed;
  border: 1px solid #fed7aa;
  color: #9a3412;
  font-size: 0.78rem;
  font-weight: 600;
}

.lb-table-wrap {
  overflow-x: auto;
  border: 1px solid var(--border-color);
  border-radius: 8px;
}

.lb-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
}

.lb-table th {
  background: var(--bg-accent);
  color: var(--text-secondary);
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 0.5rem 0.65rem;
  text-align: left;
  white-space: nowrap;
}

.lb-table td {
  padding: 0.5rem 0.65rem;
  border-top: 1px solid var(--border-light);
  white-space: nowrap;
}

.lb-row-me {
  background: #eef2ff !important;
  font-weight: 600;
}

.lb-row-top1 { background: #fef3c7; }
.lb-row-top2 { background: var(--bg-surface-muted); }
.lb-row-top3 { background: #fffbeb; }

.lb-you-badge {
  margin-left: 0.35rem;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
  background: #4f46e5;
  color: #fff;
  vertical-align: middle;
}

.lb-separator {
  text-align: center;
  color: var(--text-muted);
  letter-spacing: 0.2em;
  padding: 0.25rem !important;
  background: var(--bg-surface-muted);
}

.lb-scope {
  margin: 0.55rem 0 0;
  font-size: 0.76rem;
  color: var(--text-muted);
}
</style>

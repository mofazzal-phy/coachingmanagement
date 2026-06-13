<template>
  <div class="course-select-card" :class="{ compact, embedded }">
    <h3 v-if="!compact" class="step-title">
      <span class="step-badge">2</span>
      Select Course
    </h3>

    <!-- Category Tabs -->
    <div class="category-tabs" v-if="!loading">
      <button
        v-for="cat in categories"
        :key="cat.key"
        class="cat-tab"
        :class="{ active: activeCategory === cat.key }"
        @click="activeCategory = cat.key"
      >
        {{ cat.icon }} {{ cat.label }}
        <span class="cat-count">{{ categoryCounts[cat.key] || 0 }}</span>
      </button>
    </div>

    <!-- Mode Filter -->
    <div class="mode-filter" v-if="courses.length > 0">
      <label class="filter-label">Batch Mode:</label>
      <button class="mode-chip" :class="{ active: !modeFilter }" @click="modeFilter = null">All</button>
      <button class="mode-chip online" :class="{ active: modeFilter === 'online' }" @click="modeFilter = 'online'">🖥 Online</button>
      <button class="mode-chip offline" :class="{ active: modeFilter === 'offline' }" @click="modeFilter = 'offline'">🏫 Offline</button>
      <button class="mode-chip hybrid" :class="{ active: modeFilter === 'hybrid' }" @click="modeFilter = 'hybrid'">🔄 Hybrid</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading courses...</p>
    </div>

    <!-- No Courses -->
    <div v-else-if="courses.length === 0" class="empty-state">
      <div class="empty-icon">📚</div>
      <p>No courses available for this criteria.</p>
      <p class="text-muted">Try selecting a different category or class.</p>
    </div>

    <!-- Compact list -->
    <div v-else-if="compact" class="course-list-compact">
      <div
        v-for="course in filteredCourses"
        :key="course.id"
        class="course-row"
        :class="{ selected: selectedCourse?.id === course.id, featured: course.is_featured }"
        @click="selectCourse(course)"
      >
        <div class="row-accent" :class="course.category === 'academic' ? 'academic' : 'admission'">
          {{ course.category === 'academic' ? '📖' : '🎯' }}
        </div>
        <div class="row-content">
          <div class="row-main">
            <span class="row-name">{{ course.name }}</span>
            <span class="row-code">{{ course.code }}</span>
            <span v-if="course.is_featured" class="row-featured">⭐ Featured</span>
          </div>
          <div class="row-meta">
            <span :class="['meta-pill', 'cat', course.category === 'academic' ? 'academic' : 'admission']">
              {{ course.category === 'academic' ? 'Academic' : 'Admission' }}
            </span>
            <span v-if="course.class" class="meta-pill class">🏫 Class {{ course.class.name }}</span>
            <span v-if="course.duration_label" class="meta-pill duration">⏱ {{ course.duration_label }}</span>
            <span class="meta-pill open">✅ {{ course.batch_summary?.open || 0 }} open</span>
          </div>
        </div>
        <span class="select-ring" :class="{ on: selectedCourse?.id === course.id }">
          {{ selectedCourse?.id === course.id ? '✓' : '' }}
        </span>
      </div>
    </div>

    <!-- Course Cards Grid -->
    <div v-else class="course-grid">
      <div
        v-for="course in filteredCourses"
        :key="course.id"
        class="course-card"
        :class="{ selected: selectedCourse?.id === course.id, featured: course.is_featured }"
        @click="selectCourse(course)"
      >
        <!-- Featured Badge -->
        <span v-if="course.is_featured" class="featured-badge">⭐ Featured</span>

        <!-- Cover Image -->
        <div class="course-cover">
          <img v-if="course.cover_image" :src="course.cover_image" :alt="course.name" />
          <div v-else class="cover-placeholder">
            {{ course.category === 'academic' ? '📖' : '🎯' }}
          </div>
        </div>

        <div class="course-body">
          <div class="course-header">
            <span :class="['cat-badge', course.category === 'academic' ? 'academic' : 'admission']">
              {{ course.category === 'academic' ? 'Academic' : 'Admission' }}
            </span>
            <span class="course-code">{{ course.code }}</span>
          </div>

          <h4 class="course-name">{{ course.name }}</h4>

          <!-- Class / Target -->
          <div class="course-meta">
            <span v-if="course.class" class="meta-item">🏫 Class {{ course.class.name }}</span>
            <span v-if="course.group" class="meta-item">📐 {{ course.group.name }}</span>
            <span v-if="course.target" class="meta-item">🎯 {{ course.target }}</span>
          </div>

          <!-- Duration -->
          <div class="course-meta">
            <span v-if="course.duration_label" class="meta-item">⏱ {{ course.duration_label }}</span>
            <span v-if="course.duration_days" class="meta-item">{{ course.duration_days }} days</span>
          </div>

          <!-- Subjects Preview -->
          <div v-if="course.subjects?.length" class="subjects-preview">
            <span v-for="s in course.subjects.slice(0, 4)" :key="s.id" class="subject-chip">
              {{ s.name }}
            </span>
            <span v-if="course.subjects.length > 4" class="subject-chip more">+{{ course.subjects.length - 4 }}</span>
          </div>

          <!-- Batch Summary -->
          <div class="batch-summary">
            <div class="summary-row">
              <span>{{ course.batch_summary?.total || 0 }} Batches</span>
              <span class="text-success">{{ course.batch_summary?.open || 0 }} Open</span>
            </div>
            <div class="mode-indicators">
              <span v-if="course.has_online" class="mode-dot online">🖥</span>
              <span v-if="course.has_offline" class="mode-dot offline">🏫</span>
            </div>
          </div>

          <!-- Description -->
          <p v-if="course.description" class="course-desc">{{ course.description }}</p>
        </div>

        <div class="course-footer">
          <span class="select-indicator">
            {{ selectedCourse?.id === course.id ? '✓ Selected' : 'Click to select' }}
          </span>
        </div>
      </div>
    </div>

    <div v-if="errorMsg" class="alert alert-danger mt-2">{{ errorMsg }}</div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import enrollmentService from '@/services/enrollment.service'

const props = defineProps({
  classId: { type: String, default: null },
  groupId: { type: [String, Number], default: null },
  target: { type: String, default: null },
  student: { type: Object, default: null },
  compact: { type: Boolean, default: false },
  embedded: { type: Boolean, default: false },
})

const emit = defineEmits(['course-selected'])

const courses = ref([])
const selectedCourse = ref(null)
const loading = ref(false)
const errorMsg = ref('')
const activeCategory = ref('all')
const modeFilter = ref(null)

const categories = [
  { key: 'all', label: 'All', icon: '📋' },
  { key: 'academic', label: 'Academic', icon: '📖' },
  { key: 'admission_coaching', label: 'Admission Coaching', icon: '🎯' },
]

const categoryCounts = computed(() => {
  const counts = { all: courses.value.length }
  for (const c of courses.value) {
    counts[c.category] = (counts[c.category] || 0) + 1
  }
  return counts
})

const filteredCourses = computed(() => {
  let list = courses.value
  if (activeCategory.value !== 'all') {
    list = list.filter(c => c.category === activeCategory.value)
  }
  if (modeFilter.value) {
    list = list.filter(c => {
      if (modeFilter.value === 'online') return c.has_online
      if (modeFilter.value === 'offline') return c.has_offline
      return true
    })
  }
  return list
})

const selectCourse = (course) => {
  selectedCourse.value = course
  emit('course-selected', course)
}

const loadCourses = async () => {
  loading.value = true
  errorMsg.value = ''
  // Reset selection when reloading (e.g. student changed)
  selectedCourse.value = null
  try {
    const params = {}
    if (props.classId) params.class_id = props.classId
    if (props.groupId) params.group_id = props.groupId
    if (props.target) params.target = props.target
    const res = await enrollmentService.getSuggestedCourses(params)
    let data = res.data?.data || res.data || []
    courses.value = Array.isArray(data) ? data : []
    console.log(`[CourseSelectCard] Loaded ${courses.value.length} courses (classId: ${props.classId}, groupId: ${props.groupId})`)

    // Fallback: if suggested-courses returns empty, try listAllCourses
    if (courses.value.length === 0 && !props.classId && !props.target) {
      console.log('[CourseSelectCard] No courses from suggested, trying listAllCourses fallback...')
      try {
        const fallbackRes = await enrollmentService.listAllCourses()
        const fallbackData = fallbackRes.data?.data || fallbackRes.data || []
        courses.value = Array.isArray(fallbackData) ? fallbackData : []
        console.log(`[CourseSelectCard] Fallback loaded ${courses.value.length} courses`)
      } catch (fallbackErr) {
        console.warn('[CourseSelectCard] Fallback also failed:', fallbackErr)
      }
    }
  } catch (e) {
    console.error('[CourseSelectCard] Failed to load courses:', e)
    errorMsg.value = 'Failed to load courses'
  } finally {
    loading.value = false
  }
}

// Reload when props change (e.g. student selected with class info)
watch(() => [props.classId, props.groupId, props.target], () => {
  loadCourses()
}, { immediate: true })
</script>

<style scoped>
.course-select-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.course-select-card.embedded {
  background: transparent;
  padding: 0;
  box-shadow: none;
  border-radius: 0;
}
.course-select-card.compact .category-tabs { margin-bottom: 0.75rem; }
.course-select-card.compact .cat-tab {
  padding: 0.45rem 0.9rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-secondary);
  border-color: #cbd5e1;
  background: var(--bg-card);
}
.course-select-card.compact .cat-tab.active {
  background: #4f46e5;
  border-color: #4f46e5;
  color: #fff;
  box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
}
.course-select-card.compact .mode-filter { margin-bottom: 0.75rem; }
.course-select-card.compact .filter-label { color: var(--text-secondary); font-weight: 700; }
.course-select-card.compact .mode-chip {
  padding: 0.3rem 0.65rem;
  font-size: 0.76rem;
  font-weight: 600;
  color: var(--text-secondary);
  border-color: #cbd5e1;
}
.course-select-card.compact .mode-chip.active {
  background: #eef2ff;
  border-color: #4f46e5;
  color: #4338ca;
}

.course-list-compact {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-height: 340px;
  overflow-y: auto;
  padding-right: 2px;
}
.course-row {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.65rem 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.18s;
  background: var(--bg-card);
}
.course-row:hover {
  border-color: #818cf8;
  background: #fafaff;
  box-shadow: 0 3px 10px rgba(79, 70, 229, 0.12);
}
.course-row.selected {
  border-color: #4f46e5;
  background: linear-gradient(135deg, #fafaff 0%, #eef2ff 100%);
  box-shadow: 0 4px 14px rgba(79, 70, 229, 0.2);
}
.course-row.featured { border-color: #f59e0b; }
.row-accent {
  width: 40px; height: 40px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 10px;
  font-size: 1.15rem;
  flex-shrink: 0;
}
.row-accent.academic { background: #dbeafe; border: 1px solid #93c5fd; }
.row-accent.admission { background: #fef3c7; border: 1px solid #fcd34d; }
.row-content { flex: 1; min-width: 0; }
.row-main { display: flex; align-items: center; flex-wrap: wrap; gap: 0.4rem 0.55rem; }
.row-name {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}
.row-code {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-secondary);
  font-family: ui-monospace, monospace;
  padding: 0.1rem 0.4rem;
  background: var(--bg-accent);
  border-radius: 4px;
}
.row-featured {
  font-size: 0.68rem;
  font-weight: 700;
  color: #b45309;
  background: #fffbeb;
  padding: 0.12rem 0.4rem;
  border-radius: 6px;
  border: 1px solid #fde68a;
}
.row-meta { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.4rem; }
.meta-pill {
  padding: 0.18rem 0.5rem;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  border: 1px solid transparent;
}
.meta-pill.cat.academic { background: #dbeafe; color: #1d4ed8; border-color: #93c5fd; }
.meta-pill.cat.admission { background: #fef3c7; color: #b45309; border-color: #fcd34d; }
.meta-pill.class { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
.meta-pill.duration { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
.meta-pill.open { background: #ecfdf5; color: #047857; border-color: #6ee7b7; }
.select-ring {
  width: 26px; height: 26px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 50%;
  border: 2px solid #cbd5e1;
  color: transparent;
  font-weight: 700;
  font-size: 0.8rem;
  flex-shrink: 0;
  transition: all 0.15s;
}
.select-ring.on {
  background: #4f46e5;
  border-color: #4f46e5;
  color: #fff;
  box-shadow: 0 2px 6px rgba(79, 70, 229, 0.4);
}

.step-title { font-size: 1.1rem; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem; }
.step-badge { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #4a90d9; color: white; font-size: 0.85rem; font-weight: 700; }

/* Category Tabs */
.category-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
.cat-tab {
  padding: 0.5rem 1rem;
  border: 1px solid #e0e0e0;
  border-radius: 20px;
  background: #f9f9f9;
  cursor: pointer;
  font-size: 0.85rem;
  transition: all 0.2s;
}
.cat-tab:hover { border-color: #4a90d9; }
.cat-tab.active { background: #4a90d9; color: white; border-color: #4a90d9; }
.cat-count { font-size: 0.7rem; background: rgba(0,0,0,0.1); padding: 0.1rem 0.4rem; border-radius: 10px; margin-left: 0.3rem; }

/* Mode Filter */
.mode-filter { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
.filter-label { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }
.mode-chip {
  padding: 0.3rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 16px;
  background: var(--bg-card);
  font-size: 0.8rem;
  cursor: pointer;
  transition: all 0.2s;
}
.mode-chip:hover { border-color: #4a90d9; }
.mode-chip.active { background: #eef4ff; border-color: #4a90d9; color: #4a90d9; font-weight: 600; }

/* Course Grid */
.course-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }

.course-card {
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  display: flex;
  flex-direction: column;
}
.course-card:hover { border-color: #4a90d9; box-shadow: 0 4px 16px rgba(74,144,217,0.15); transform: translateY(-2px); }
.course-card.selected { border-color: #4a90d9; box-shadow: 0 4px 20px rgba(74,144,217,0.25); background: #f8faff; }
.course-card.featured { border-color: #f39c12; }

.featured-badge {
  position: absolute;
  top: 10px;
  right: 10px;
  background: #f39c12;
  color: white;
  padding: 0.2rem 0.6rem;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
  z-index: 1;
}

.course-cover {
  height: 120px;
  background: linear-gradient(135deg, #e0e7ff, #f0f4ff);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.course-cover img { width: 100%; height: 100%; object-fit: cover; }
.cover-placeholder { font-size: 2.5rem; }

.course-body { padding: 1rem; flex: 1; display: flex; flex-direction: column; gap: 0.4rem; }

.course-header { display: flex; justify-content: space-between; align-items: center; }
.cat-badge { padding: 0.15rem 0.5rem; border-radius: 10px; font-size: 0.7rem; font-weight: 600; }
.cat-badge.academic { background: #eef4ff; color: #4a90d9; }
.cat-badge.admission { background: #fff3cd; color: #f39c12; }
.course-code { font-size: 0.75rem; color: var(--text-muted); font-family: monospace; }

.course-name { margin: 0; font-size: 0.95rem; color: #1a1a1a; }

.course-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.meta-item { font-size: 0.75rem; color: var(--text-muted); }

.subjects-preview { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.25rem; }
.subject-chip { padding: 0.15rem 0.4rem; background: #f0f4ff; color: #4a90d9; border-radius: 8px; font-size: 0.7rem; }
.subject-chip.more { background: #ddd; color: var(--text-muted); }

.batch-summary { display: flex; justify-content: space-between; align-items: center; margin-top: 0.25rem; font-size: 0.8rem; color: var(--text-muted); }

.course-desc { font-size: 0.78rem; color: #888; margin: 0.25rem 0 0 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.course-footer {
  padding: 0.6rem 1rem;
  background: var(--bg-accent);
  border-top: 1px solid var(--border-light);
  text-align: center;
}
.select-indicator { font-size: 0.8rem; }
.course-card.selected .select-indicator { color: #4a90d9; font-weight: 600; }
.course-card:not(.selected) .select-indicator { color: #aaa; }

.loading-state, .empty-state { text-align: center; padding: 2rem; }
.spinner { width: 32px; height: 32px; border: 3px solid #eee; border-top-color: #4a90d9; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 0.5rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.empty-icon { font-size: 3rem; margin-bottom: 0.5rem; }
.alert-danger { background: #fdeaea; color: #e74c3c; padding: 0.75rem; border-radius: 8px; font-size: 0.85rem; }
.text-muted { color: #888; }
.text-success { color: #27ae60; }
.mt-2 { margin-top: 0.5rem; }
</style>

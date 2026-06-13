<template>
  <div>
    <PublicHero :title="t('nav.courses')" :subtitle="t('section.featuredCourses.sub')" />

    <section class="pub-section pub-pat pat-course">
      <div class="pub-container">
        <!-- Filters -->
        <div class="filters">
          <div class="filter-pills">
            <button
              v-for="cat in categories"
              :key="cat.key"
              class="fpill"
              :class="{ active: activeCat === cat.key }"
              @click="setCat(cat.key)"
            >
              {{ isBn() ? cat.bn : cat.en }}
            </button>
          </div>
          <div class="filter-modes">
            <button class="fpill" :class="{ active: mode === '' }" @click="setMode('')">{{ isBn() ? 'সব' : 'All' }}</button>
            <button class="fpill" :class="{ active: mode === 'online' }" @click="setMode('online')">{{ t('course.online') }}</button>
            <button class="fpill" :class="{ active: mode === 'offline' }" @click="setMode('offline')">{{ t('course.offline') }}</button>
          </div>
        </div>

        <ContentState :loading="loading" :error="error" :empty="!loading && !error && !filtered.length" :on-retry="load" />

        <div v-if="!loading && filtered.length" class="course-grid">
          <CourseCard v-for="c in filtered" :key="c.id" :course="c" />
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import enrollmentService from '@/services/enrollment.service'
import cmsPublicService from '@/services/cms-public.service'
import { useLang } from '@/composables/useLang'
import PublicHero from '@/components/public/PublicHero.vue'
import CourseCard from '@/components/public/CourseCard.vue'
import ContentState from '@/components/public/ContentState.vue'

const { t, isBn } = useLang()
const route = useRoute()
const router = useRouter()

const courses = ref([])
const loading = ref(true)
const error = ref(false)
const activeCat = ref(route.query.category || 'all')
const mode = ref(route.query.mode || '')

const categories = [
  { key: 'all', bn: 'সব কোর্স', en: 'All' },
  { key: 'class-6-8', bn: '৬ষ্ঠ–৮ম', en: 'Class 6–8' },
  { key: 'ssc', bn: 'এসএসসি', en: 'SSC' },
  { key: 'hsc', bn: 'এইচএসসি', en: 'HSC' },
  { key: 'admission', bn: 'ভর্তি', en: 'Admission' },
  { key: 'skills', bn: 'স্কিল', en: 'Skills' },
  { key: 'language', bn: 'ভাষা', en: 'Language' },
]

// Heuristic matcher: real course data uses broad categories (academic/admission),
// while the navigation chips are class/exam based. Match against several signals
// (category, course name, level, target, class & group names) so chips actually work.
const CAT_TESTS = {
  'class-6-8': /(class\s*-?\s*[678]\b|grade\s*[678]|\b[678]th\b|six|seven|eight|৬ষ্ঠ|সপ্তম|অষ্টম|৬|৭|৮)/i,
  ssc: /(ssc|dakhil|class\s*-?\s*(9|10)|nine|ten|নবম|দশম|৯ম|১০ম|এসএসসি)/i,
  hsc: /(hsc|alim|inter|class\s*-?\s*(11|12)|eleven|twelve|একাদশ|দ্বাদশ|এইচএসসি)/i,
  admission: /(admission|varsity|university|medical|mbbs|buet|du\b|engineering|ভর্তি|admisson)/i,
  skills: /(skill|programming|coding|computer|freelanc|graphic|design|spoken|ক্যারিয়ার|স্কিল)/i,
  language: /(language|english|spoken|ielts|arabic|ভাষা|ইংরেজি|আরবি)/i,
}

function matchesCat(c, key) {
  if (key === 'all') return true
  if ((c.category || '').toLowerCase() === key) return true
  const hay = [c.category, c.name, c.level, c.target, c.short_description, c.class?.name, c.group?.name]
    .filter(Boolean).join(' ')
  const re = CAT_TESTS[key]
  return re ? re.test(hay) : false
}

const filtered = computed(() => {
  return courses.value.filter((c) => {
    const catOk = matchesCat(c, activeCat.value)
    const modeOk = !mode.value
      || (mode.value === 'online' && c.has_online)
      || (mode.value === 'offline' && c.has_offline)
    return catOk && modeOk
  })
})

function setCat(key) {
  activeCat.value = key
  router.replace({ query: { ...route.query, category: key === 'all' ? undefined : key } })
}
function setMode(m) {
  mode.value = m
  router.replace({ query: { ...route.query, mode: m || undefined } })
}

const load = async () => {
  loading.value = true
  error.value = false
  try {
    const res = await enrollmentService.getPublicCourses()
    courses.value = cmsPublicService.extractList(res)
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

watch(() => route.query, (q) => {
  activeCat.value = q.category || 'all'
  mode.value = q.mode || ''
})

onMounted(load)
</script>

<style scoped>
.filters { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem; }
.filter-pills, .filter-modes { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.fpill {
  padding: 0.5rem 1rem; border-radius: var(--pub-radius-pill);
  border: 1.5px solid var(--pub-border); background: var(--pub-surface);
  color: var(--pub-text-soft); font-weight: 700; font-size: 0.84rem; cursor: pointer;
  font-family: inherit; transition: all 0.16s;
}
.fpill:hover { border-color: var(--pub-accent); color: var(--pub-accent); }
.fpill.active { background: var(--pub-accent); border-color: var(--pub-accent); color: #fff; }
.course-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
@media (max-width: 980px) { .course-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .course-grid { grid-template-columns: 1fr; } }
</style>

<template>
  <router-link :to="`/site/courses/${course.id}`" class="cc pub-card" :class="{ 'cc--featured': featured }">
    <div class="cc-media">
      <img v-if="course.cover_image_url && !imgError" :src="course.cover_image_url" :alt="course.name" loading="lazy" @error="imgError = true" />
      <div v-else class="cc-media-fallback" :style="{ background: catStyle.grad }">
        <span class="cc-fb-pattern"></span>
        <span class="cc-fb-icon">{{ catStyle.icon }}</span>
        <span class="cc-fb-code">{{ initials }}</span>
      </div>
      <div class="cc-badges">
        <span v-if="course.has_online" class="pub-chip pub-chip--green">{{ t('course.online') }}</span>
        <span v-if="course.has_offline" class="pub-chip">{{ t('course.offline') }}</span>
      </div>
      <span v-if="course.is_featured" class="cc-feat pub-chip pub-chip--gold">★</span>
    </div>
    <div class="cc-body">
      <span v-if="course.category" class="cc-cat">{{ course.category }}</span>
      <h3 class="cc-title">{{ course.name }}</h3>
      <p v-if="course.short_description" class="cc-desc">{{ course.short_description }}</p>
      <div class="cc-meta">
        <span v-if="course.duration_label" class="cc-meta-item">🕒 {{ course.duration_label }}</span>
        <span v-if="subjectCount" class="cc-meta-item">📚 {{ subjectCount }} {{ isBn() ? 'বিষয়' : 'subjects' }}</span>
      </div>
      <div class="cc-foot">
        <div class="cc-fee">
          <span v-if="fee" class="cc-fee-amt">৳{{ fee }}</span>
          <span v-else class="cc-fee-amt cc-fee-free">{{ isBn() ? 'যোগাযোগ করুন' : 'Contact us' }}</span>
        </div>
        <span class="cc-cta">{{ t('action.viewDetails') }} →</span>
      </div>
    </div>
  </router-link>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useLang } from '@/composables/useLang'

const props = defineProps({
  course: { type: Object, required: true },
  featured: { type: Boolean, default: false },
})

const { t, isBn } = useLang()
const imgError = ref(false)

const initials = computed(() => (props.course.name || '?').slice(0, 2).toUpperCase())
const subjectCount = computed(() => props.course.subjects?.length || 0)

const CAT_STYLES = {
  'class-6-8': { grad: 'linear-gradient(135deg,#6366f1,#8b5cf6)', icon: '📗' },
  ssc: { grad: 'linear-gradient(135deg,#0ea5c4,#22d3ee)', icon: '📘' },
  hsc: { grad: 'linear-gradient(135deg,#0ea882,#34d399)', icon: '📕' },
  admission: { grad: 'linear-gradient(135deg,#f59e0b,#ef4444)', icon: '🎯' },
  skills: { grad: 'linear-gradient(135deg,#ec4899,#f472b6)', icon: '💡' },
  language: { grad: 'linear-gradient(135deg,#8b5cf6,#6366f1)', icon: '🗣️' },
}
const FALLBACK_GRADS = [
  'linear-gradient(135deg,#6366f1,#8b5cf6)',
  'linear-gradient(135deg,#0ea5c4,#22d3ee)',
  'linear-gradient(135deg,#0ea882,#34d399)',
  'linear-gradient(135deg,#f59e0b,#ef4444)',
  'linear-gradient(135deg,#ec4899,#f472b6)',
  'linear-gradient(135deg,#8b5cf6,#0ea5c4)',
]
function hashStr(s) {
  let h = 0
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0
  return h
}
// Subject-group themed covers (science / arts / commerce)
const GROUP_STYLES = [
  { re: /(science|বিজ্ঞান)/i, grad: 'linear-gradient(135deg,#0ea882,#22d3ee)', icon: '🔬' },
  { re: /(commerce|business|ব্যবসায়|বাণিজ্য)/i, grad: 'linear-gradient(135deg,#f59e0b,#f97316)', icon: '📊' },
  { re: /(arts|humanit|মানবিক|কলা)/i, grad: 'linear-gradient(135deg,#ec4899,#a855f7)', icon: '🎨' },
]
const catStyle = computed(() => {
  const key = (props.course.category || '').toLowerCase()
  if (CAT_STYLES[key]) return CAT_STYLES[key]
  const hay = [props.course.group?.name, props.course.target, props.course.name].filter(Boolean).join(' ')
  const g = GROUP_STYLES.find((x) => x.re.test(hay))
  if (g) return g
  const seed = props.course.id || props.course.name || ''
  const idx = hashStr(String(seed)) % FALLBACK_GRADS.length
  return { grad: FALLBACK_GRADS[idx], icon: '📚' }
})
const fee = computed(() => {
  const f = Number(props.course.one_time_fee || 0)
  return f > 0 ? f.toLocaleString('en-US') : null
})
</script>

<style scoped>
.cc { display: flex; flex-direction: column; overflow: hidden; text-decoration: none; color: inherit; height: 100%; }
.cc-media { position: relative; aspect-ratio: 16 / 9; background: var(--pub-accent-soft); overflow: hidden; }
.cc-media img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s; }
.cc:hover .cc-media img { transform: scale(1.06); }
.cc-media-fallback {
  position: relative; width: 100%; height: 100%; display: grid; place-items: center;
  color: #fff; overflow: hidden;
}
.cc-fb-pattern {
  position: absolute; inset: 0;
  background-image: radial-gradient(rgba(255,255,255,0.35) 1.5px, transparent 1.5px);
  background-size: 18px 18px; opacity: 0.35;
}
.cc-fb-icon { font-size: 2.6rem; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2)); z-index: 1; }
.cc-fb-code {
  position: absolute; bottom: 0.6rem; right: 0.8rem; z-index: 1;
  font-size: 0.95rem; font-weight: 800; letter-spacing: 1px; opacity: 0.85;
  background: rgba(255,255,255,0.18); padding: 0.15rem 0.5rem; border-radius: 8px; backdrop-filter: blur(4px);
}
.cc-badges { position: absolute; top: 0.6rem; left: 0.6rem; display: flex; gap: 0.35rem; }
.cc-feat { position: absolute; top: 0.6rem; right: 0.6rem; }
.cc-body { padding: 1rem 1.1rem 1.15rem; display: flex; flex-direction: column; flex: 1; }
.cc-cat { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--pub-accent); }
.cc-title { font-size: 1.05rem; font-weight: 800; margin: 0.3rem 0; color: var(--pub-text); line-height: 1.3; }
.cc-desc { font-size: 0.86rem; color: var(--pub-text-muted); line-height: 1.5; margin: 0 0 0.75rem; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.cc-meta { display: flex; flex-wrap: wrap; gap: 0.75rem; font-size: 0.78rem; color: var(--pub-text-soft); margin-bottom: 0.85rem; }
.cc-foot { margin-top: auto; display: flex; align-items: center; justify-content: space-between; padding-top: 0.85rem; border-top: 1px solid var(--pub-border); }
.cc-fee-amt { font-size: 1.1rem; font-weight: 800; color: var(--pub-text); }
.cc-fee-free { font-size: 0.85rem; color: var(--pub-text-muted); }
.cc-cta { font-size: 0.82rem; font-weight: 700; color: var(--pub-accent); }

.cc--featured { flex-direction: row; grid-column: span 2; }
.cc--featured .cc-media { aspect-ratio: auto; width: 46%; }
.cc--featured .cc-title { font-size: 1.35rem; }
.cc--featured .cc-desc { -webkit-line-clamp: 3; line-clamp: 3; }
@media (max-width: 720px) {
  .cc--featured { flex-direction: column; grid-column: span 1; }
  .cc--featured .cc-media { width: 100%; aspect-ratio: 16 / 9; }
}
</style>

<template>
  <div class="mh">
    <!-- ===== HERO ===== -->
    <section class="hero">
      <div class="hero-bg">
        <transition-group name="slide-fade">
          <div
            v-for="(slide, idx) in slides"
            v-show="idx === activeSlide"
            :key="slide.key"
            class="hero-slide"
            :style="slide.image ? { backgroundImage: `url(${slide.image})` } : {}"
            :class="{ 'no-image': !slide.image }"
          ></div>
        </transition-group>
        <div class="hero-overlay"></div>
        <div class="hero-mesh"></div>
      </div>

      <div class="pub-container hero-inner">
        <div class="hero-content">
          <span class="hero-eyebrow">✨ {{ activeSlideData.eyebrow || t('hero.eyebrow') }}</span>
          <h1 class="hero-title">{{ activeSlideData.title || t('hero.title') }}</h1>
          <p class="hero-sub">{{ activeSlideData.subtitle || t('hero.subtitle') }}</p>
          <div class="hero-actions">
            <router-link to="/site/enroll" class="pub-btn pub-btn--primary">{{ t('hero.ctaPrimary') }}</router-link>
            <router-link to="/site/courses" class="pub-btn pub-btn--light">{{ t('hero.ctaSecondary') }}</router-link>
          </div>

          <div class="hero-trust">
            <div class="hero-avatars">
              <span v-for="n in 4" :key="n" class="hero-av" :style="{ background: avatarColors[n - 1] }">{{ avatarLetters[n - 1] }}</span>
            </div>
            <div class="hero-trust-text">
              <div class="hero-rating">★★★★★ <b>4.9</b></div>
              <span>{{ isBn() ? '৫,০০০+ শিক্ষার্থীর আস্থা' : 'Trusted by 5,000+ students' }}</span>
            </div>
          </div>

          <div class="hero-stats">
            <div class="hero-stat">
              <StatCounter :value="stats.students" :label="t('stats.students')" suffix="+" />
            </div>
            <div class="hero-stat">
              <StatCounter :value="stats.batches" :label="t('stats.batches')" suffix="+" />
            </div>
            <div class="hero-stat">
              <StatCounter :value="stats.successRate" :label="t('stats.successRate')" suffix="%" />
            </div>
          </div>
        </div>

        <div class="hero-visual" aria-hidden="true">
          <div class="hv-glow"></div>
          <div class="hv-ring hv-ring-1"></div>
          <div class="hv-ring hv-ring-2"></div>

          <div class="hv-card hv-main">
            <div class="hv-main-top">
              <span class="hv-main-ic">🎓</span>
              <span class="hv-live"><i></i>{{ isBn() ? 'লাইভ ক্লাস' : 'Live class' }}</span>
            </div>
            <div class="hv-main-title">{{ isBn() ? 'পদার্থবিজ্ঞান — অধ্যায় ৫' : 'Physics — Chapter 5' }}</div>
            <div class="hv-progress"><span :style="{ width: '72%' }"></span></div>
            <div class="hv-main-foot">
              <div class="hv-mini-avatars"><span></span><span></span><span></span></div>
              <span class="hv-foot-text">{{ isBn() ? '১২৪ জন শিখছে' : '124 learning now' }}</span>
            </div>
          </div>

          <div class="hv-card hv-float hv-result">
            <div class="hv-result-ring">
              <svg viewBox="0 0 44 44"><circle cx="22" cy="22" r="18" /><circle cx="22" cy="22" r="18" class="hv-result-fill" /></svg>
              <b>95%</b>
            </div>
            <div><strong>{{ isBn() ? 'সাফল্যের হার' : 'Success rate' }}</strong><span>{{ isBn() ? 'এ+ অর্জন' : 'A+ achievers' }}</span></div>
          </div>

          <div class="hv-card hv-float hv-streak">
            <span class="hv-streak-ic">🔥</span>
            <div><strong>২১ {{ isBn() ? 'দিন' : 'days' }}</strong><span>{{ isBn() ? 'স্ট্রিক' : 'streak' }}</span></div>
          </div>
        </div>
      </div>

      <div v-if="slides.length > 1" class="hero-dots">
        <button
          v-for="(_, idx) in slides"
          :key="idx"
          :class="{ active: idx === activeSlide }"
          :aria-label="`Slide ${idx + 1}`"
          @click="goSlide(idx)"
        ></button>
      </div>
    </section>

    <!-- ===== CLASS NAVIGATOR ===== -->
    <section class="pub-section class-nav-sec">
      <div class="pub-container">
        <div class="class-nav" v-reveal>
          <router-link
            v-for="c in classNav"
            :key="c.key"
            :to="`/site/courses?category=${c.key}`"
            class="class-pill"
          >
            <span class="class-pill-ic">{{ c.icon }}</span>
            <span>{{ isBn() ? c.bn : c.en }}</span>
          </router-link>
        </div>
      </div>
    </section>

    <!-- ===== FEATURED COURSES ===== -->
    <section class="pub-section" v-if="courses.length">
      <div class="pub-container">
        <div class="pub-section-head" v-reveal>
          <div>
            <span class="pub-eyebrow">{{ t('nav.courses') }}</span>
            <h2 class="pub-title">{{ t('section.featuredCourses') }}</h2>
            <p class="pub-sub">{{ t('section.featuredCourses.sub') }}</p>
          </div>
          <router-link to="/site/courses" class="pub-btn pub-btn--ghost">{{ t('action.viewAll') }}</router-link>
        </div>
        <div class="course-bento" v-reveal>
          <CourseCard
            v-for="(course, i) in featuredCourses"
            :key="course.id"
            :course="course"
            :featured="i === 0 && featuredCourses.length > 2"
          />
        </div>
      </div>
    </section>

    <!-- ===== WHY US ===== -->
    <section class="pub-section pub-section--alt">
      <div class="pub-container">
        <div class="pub-section-head pub-section-head--center" v-reveal>
          <div class="center-head">
            <span class="pub-eyebrow">{{ t('brand.name') }}</span>
            <h2 class="pub-title">{{ t('section.whyUs') }}</h2>
            <p class="pub-sub">{{ t('section.whyUs.sub') }}</p>
          </div>
        </div>
        <div class="why-grid">
          <div v-for="(f, i) in whyFeatures" :key="f.key" class="why-card pub-card" v-reveal="i * 80">
            <div class="why-ic" :style="{ background: f.bg }">{{ f.icon }}</div>
            <h3>{{ t(f.titleKey) }}</h3>
            <p>{{ t(f.descKey) }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== LEARNING MODES ===== -->
    <section class="pub-section">
      <div class="pub-container">
        <div class="modes-grid">
          <div class="mode-panel mode-online" v-reveal>
            <span class="mode-tag">{{ t('course.online') }}</span>
            <h3>{{ t('mode.online.title') }}</h3>
            <p>{{ t('mode.online.desc') }}</p>
            <router-link to="/site/courses?mode=online" class="mode-link">{{ t('action.learnMore') }} →</router-link>
          </div>
          <div class="mode-panel mode-offline" v-reveal="120">
            <span class="mode-tag mode-tag--gold">{{ t('course.offline') }}</span>
            <h3>{{ t('mode.offline.title') }}</h3>
            <p>{{ t('mode.offline.desc') }}</p>
            <router-link to="/site/courses?mode=offline" class="mode-link">{{ t('action.learnMore') }} →</router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== UPCOMING BATCHES ===== -->
    <section class="pub-section pub-section--alt" v-if="home.events?.length">
      <div class="pub-container">
        <div class="pub-section-head" v-reveal>
          <div>
            <span class="pub-eyebrow">{{ t('nav.events') }}</span>
            <h2 class="pub-title">{{ t('section.events') }}</h2>
          </div>
          <router-link to="/site/events" class="pub-btn pub-btn--ghost">{{ t('action.viewAll') }}</router-link>
        </div>
        <div class="event-strip" v-reveal>
          <article v-for="ev in home.events.slice(0, 6)" :key="ev.id" class="event-card pub-card">
            <div class="event-date">
              <strong>{{ eventDay(ev) }}</strong>
              <span>{{ eventMonth(ev) }}</span>
            </div>
            <div class="event-body">
              <h3>{{ ev.title }}</h3>
              <p v-if="ev.location">📍 {{ ev.location }}</p>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- ===== STATS BAND ===== -->
    <section class="stats-band">
      <div class="pub-container stats-band-inner">
        <StatCounter :value="stats.students" :label="t('stats.students')" suffix="+" />
        <StatCounter :value="stats.teachers" :label="t('stats.teachers')" suffix="+" />
        <StatCounter :value="stats.courses" :label="t('stats.courses')" suffix="+" />
        <StatCounter :value="stats.successRate" :label="t('stats.successRate')" suffix="%" />
      </div>
    </section>

    <!-- ===== SUCCESS STORIES ===== -->
    <section class="pub-section" v-if="home.success_stories?.length">
      <div class="pub-container">
        <div class="pub-section-head" v-reveal>
          <div>
            <span class="pub-eyebrow">🏆 {{ t('nav.successStories') }}</span>
            <h2 class="pub-title">{{ t('section.successStories') }}</h2>
          </div>
          <router-link to="/site/success-stories" class="pub-btn pub-btn--ghost">{{ t('action.viewAll') }}</router-link>
        </div>
        <div class="story-grid" v-reveal>
          <router-link
            v-for="s in home.success_stories.slice(0, 4)"
            :key="s.id"
            :to="`/site/success-stories/${s.slug}`"
            class="story-card pub-card"
          >
            <img v-if="s.image_url || s.photo_url" :src="s.image_url || s.photo_url" :alt="s.title" class="story-img" loading="lazy" />
            <div v-else class="story-img story-img--ph">🎓</div>
            <div class="story-body">
              <h3>{{ s.student_name || s.title }}</h3>
              <p v-if="s.achievement">{{ s.achievement }}</p>
              <p v-else-if="s.excerpt">{{ truncate(s.excerpt, 80) }}</p>
            </div>
          </router-link>
        </div>
      </div>
    </section>

    <!-- ===== TESTIMONIALS ===== -->
    <section class="pub-section pub-section--alt" v-if="home.testimonials?.length">
      <div class="pub-container">
        <div class="pub-section-head pub-section-head--center" v-reveal>
          <div class="center-head">
            <span class="pub-eyebrow">💬</span>
            <h2 class="pub-title">{{ t('section.testimonials') }}</h2>
          </div>
        </div>
        <div class="testi-track" v-reveal>
          <blockquote v-for="ti in home.testimonials" :key="ti.id" class="testi-card pub-card">
            <div class="testi-stars">★★★★★</div>
            <p class="testi-text">“{{ ti.content }}”</p>
            <footer>
              <div class="testi-avatar">{{ (ti.name || '?').slice(0, 1) }}</div>
              <div>
                <strong>{{ ti.name }}</strong>
                <span v-if="ti.organization || ti.designation">{{ ti.designation || ti.organization }}</span>
              </div>
            </footer>
          </blockquote>
        </div>
      </div>
    </section>

    <!-- ===== GALLERY ===== -->
    <section class="pub-section" v-if="home.galleries?.length">
      <div class="pub-container">
        <div class="pub-section-head" v-reveal>
          <div>
            <span class="pub-eyebrow">🖼️</span>
            <h2 class="pub-title">{{ t('section.gallery') }}</h2>
          </div>
          <router-link to="/site/gallery" class="pub-btn pub-btn--ghost">{{ t('action.viewAll') }}</router-link>
        </div>
        <div class="gallery-masonry" v-reveal>
          <figure v-for="(g, i) in home.galleries.slice(0, 7)" :key="g.id" class="gal-item" :class="`gal-${i % 5}`">
            <img v-if="g.thumbnail_url || g.file_url" :src="g.thumbnail_url || g.file_url" :alt="g.title" loading="lazy" />
            <figcaption>{{ g.title }}</figcaption>
          </figure>
        </div>
      </div>
    </section>

    <!-- ===== TEACHERS ===== -->
    <section class="pub-section pub-section--alt" v-if="teachers.length">
      <div class="pub-container">
        <div class="pub-section-head" v-reveal>
          <div>
            <span class="pub-eyebrow">👩‍🏫</span>
            <h2 class="pub-title">{{ t('section.teachers') }}</h2>
            <p class="pub-sub">{{ t('section.teachers.sub') }}</p>
          </div>
          <router-link to="/site/teachers" class="pub-btn pub-btn--ghost">{{ t('action.viewAll') }}</router-link>
        </div>
        <div class="teacher-row" v-reveal>
          <router-link
            v-for="tch in teachers.slice(0, 4)"
            :key="tch.id"
            :to="`/site/teachers/${tch.id}`"
            class="teacher-card pub-card"
          >
            <img v-if="tch.photo_url || tch.avatar" :src="tch.photo_url || tch.avatar" :alt="tch.name" class="teacher-img" loading="lazy" />
            <div v-else class="teacher-img teacher-img--ph">{{ (tch.name || '?').slice(0, 1) }}</div>
            <h3>{{ tch.name }}</h3>
            <p>{{ tch.designation || tch.subject || '' }}</p>
          </router-link>
        </div>
      </div>
    </section>

    <!-- ===== BLOG + NOTICES ===== -->
    <section class="pub-section" v-if="home.blog?.length || home.notices?.length">
      <div class="pub-container blog-notice-grid">
        <div v-if="home.blog?.length" class="bn-blog">
          <div class="pub-section-head" v-reveal>
            <div><span class="pub-eyebrow">✍️</span><h2 class="pub-title">{{ t('section.blog') }}</h2></div>
            <router-link to="/site/blog" class="pub-btn pub-btn--ghost">{{ t('action.viewAll') }}</router-link>
          </div>
          <div class="blog-list" v-reveal>
            <router-link v-for="post in home.blog.slice(0, 3)" :key="post.id" :to="`/site/blog/${post.slug}`" class="blog-row pub-card">
              <img v-if="post.featured_image_url" :src="post.featured_image_url" :alt="post.title" class="blog-thumb" loading="lazy" />
              <div v-else class="blog-thumb blog-thumb--ph">📝</div>
              <div class="blog-row-body">
                <h3>{{ post.title }}</h3>
                <p>{{ post.excerpt || truncate(post.content, 80) }}</p>
              </div>
            </router-link>
          </div>
        </div>

        <aside v-if="home.notices?.length" class="bn-notices" v-reveal="120">
          <div class="pub-section-head"><div><span class="pub-eyebrow">📢</span><h2 class="pub-title">{{ t('section.notices') }}</h2></div></div>
          <div class="notice-list">
            <router-link v-for="n in home.notices.slice(0, 5)" :key="n.id" to="/site/notices" class="notice-row">
              <span class="notice-dot"></span>
              <div>
                <strong>{{ n.title }}</strong>
                <small>{{ formatDate(n.publish_date || n.created_at) }}</small>
              </div>
            </router-link>
          </div>
        </aside>
      </div>
    </section>

    <!-- ===== DOWNLOADS ===== -->
    <section class="pub-section pub-section--alt" v-if="home.downloads?.length">
      <div class="pub-container">
        <div class="downloads-cta" v-reveal>
          <div>
            <span class="pub-eyebrow">📥</span>
            <h2 class="pub-title">{{ t('section.downloads') }}</h2>
          </div>
          <router-link to="/site/downloads" class="pub-btn pub-btn--primary">{{ t('action.viewAll') }}</router-link>
        </div>
      </div>
    </section>

    <!-- ===== FINAL CTA ===== -->
    <section class="final-cta">
      <div class="pub-container final-cta-inner" v-reveal>
        <h2>{{ t('cta.title') }}</h2>
        <p>{{ t('cta.subtitle') }}</p>
        <div class="final-cta-actions">
          <router-link to="/site/enroll" class="pub-btn pub-btn--light">{{ t('hero.ctaPrimary') }}</router-link>
          <a :href="telLink()" class="pub-btn pub-btn--accent">📞 {{ t('action.callNow') }}</a>
          <a :href="waLink" target="_blank" rel="noopener" class="pub-btn pub-btn--light">💬 {{ t('action.whatsapp') }}</a>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import cmsPublicService from '@/services/cms-public.service'
import enrollmentService from '@/services/enrollment.service'
import { useLang } from '@/composables/useLang'
import { telLink, whatsappLink } from '@/config/siteConfig'
import StatCounter from '@/components/public/StatCounter.vue'
import CourseCard from '@/components/public/CourseCard.vue'

const { t, isBn } = useLang()

const home = ref({})
const courses = ref([])
const teachers = ref([])
const activeSlide = ref(0)
let slideTimer = null

const waLink = whatsappLink()

const stats = ref({ students: 5000, batches: 40, courses: 25, teachers: 30, successRate: 95 })

const avatarColors = ['linear-gradient(135deg,#6366f1,#8b5cf6)', 'linear-gradient(135deg,#0ea5c4,#22d3ee)', 'linear-gradient(135deg,#0ea882,#34d399)', 'linear-gradient(135deg,#ec4899,#f472b6)']
const avatarLetters = ['র', 'আ', 'স', 'ম']

const classNav = [
  { key: 'class-6-8', icon: '📗', bn: '৬ষ্ঠ–৮ম', en: 'Class 6–8' },
  { key: 'ssc', icon: '📘', bn: 'এসএসসি', en: 'SSC' },
  { key: 'hsc', icon: '📕', bn: 'এইচএসসি', en: 'HSC' },
  { key: 'admission', icon: '🎯', bn: 'ভর্তি পরীক্ষা', en: 'Admission' },
  { key: 'skills', icon: '💡', bn: 'স্কিল ডেভেলপমেন্ট', en: 'Skills' },
  { key: 'language', icon: '🗣️', bn: 'ভাষা শিক্ষা', en: 'Language' },
]

const whyFeatures = [
  { key: 'faculty', icon: '👨‍🏫', titleKey: 'why.faculty.title', descKey: 'why.faculty.desc', bg: 'rgba(79,70,229,0.12)' },
  { key: 'live', icon: '🎥', titleKey: 'why.live.title', descKey: 'why.live.desc', bg: 'rgba(16,185,129,0.12)' },
  { key: 'tracking', icon: '📊', titleKey: 'why.tracking.title', descKey: 'why.tracking.desc', bg: 'rgba(245,158,11,0.14)' },
  { key: 'smartfee', icon: '💳', titleKey: 'why.smartfee.title', descKey: 'why.smartfee.desc', bg: 'rgba(6,182,212,0.14)' },
]

const slides = computed(() => {
  const list = (home.value.sliders || []).map((s) => ({
    key: `s-${s.id}`,
    image: s.image_url || s.image || '',
    title: s.title,
    subtitle: s.description,
    eyebrow: s.subtitle || '',
  }))
  if (!list.length) {
    return [{ key: 'fallback', image: '', title: '', subtitle: '', eyebrow: '' }]
  }
  return list
})

const activeSlideData = computed(() => slides.value[activeSlide.value] || {})

const featuredCourses = computed(() => {
  const feat = courses.value.filter((c) => c.is_featured)
  const rest = courses.value.filter((c) => !c.is_featured)
  return [...feat, ...rest].slice(0, 6)
})

const goSlide = (idx) => { activeSlide.value = idx; restartTimer() }
const nextSlide = () => { activeSlide.value = (activeSlide.value + 1) % slides.value.length }

const restartTimer = () => {
  clearInterval(slideTimer)
  if (slides.value.length > 1) slideTimer = setInterval(nextSlide, 6000)
}

const truncate = (text, n) => {
  if (!text) return ''
  const clean = String(text).replace(/<[^>]*>/g, '')
  return clean.length > n ? clean.slice(0, n) + '…' : clean
}

const formatDate = (d) => {
  if (!d) return ''
  return new Date(d).toLocaleDateString(isBn() ? 'bn-BD' : 'en-US', { day: 'numeric', month: 'short', year: 'numeric' })
}

const eventDay = (ev) => {
  const d = ev.start_date || ev.event_date || ev.date
  return d ? new Date(d).getDate() : '•'
}
const eventMonth = (ev) => {
  const d = ev.start_date || ev.event_date || ev.date
  return d ? new Date(d).toLocaleDateString(isBn() ? 'bn-BD' : 'en-US', { month: 'short' }) : ''
}

const loadHome = async () => {
  try {
    const res = await cmsPublicService.home()
    home.value = cmsPublicService.extractData(res) || {}
  } catch { home.value = {} }
}

const loadCourses = async () => {
  try {
    const res = await enrollmentService.getPublicCourses()
    courses.value = cmsPublicService.extractList(res)
    if (courses.value.length) stats.value.courses = courses.value.length
  } catch { courses.value = [] }
}

const loadTeachers = async () => {
  try {
    const res = await cmsPublicService.teachers({ limit: 8 })
    teachers.value = cmsPublicService.extractList(res)
    if (teachers.value.length) stats.value.teachers = Math.max(stats.value.teachers, teachers.value.length)
  } catch { teachers.value = [] }
}

onMounted(async () => {
  await Promise.allSettled([loadHome(), loadCourses(), loadTeachers()])
  restartTimer()
})
onUnmounted(() => clearInterval(slideTimer))
</script>

<style scoped>
/* ===== HERO ===== */
.hero {
  position: relative;
  min-height: clamp(540px, 80vh, 720px);
  display: flex;
  align-items: center;
  overflow: hidden;
}
.hero-bg { position: absolute; inset: 0; background: var(--pub-hero-grad); }
.hero-slide {
  position: absolute; inset: 0;
  background-size: cover; background-position: center;
}
.hero-slide.no-image { background: var(--pub-hero-grad); }
.hero-overlay { position: absolute; inset: 0; background: var(--pub-hero-overlay); }
.hero-mesh {
  position: absolute; inset: 0;
  background:
    radial-gradient(700px circle at 80% 20%, rgba(99,102,241,0.4), transparent 50%),
    radial-gradient(600px circle at 15% 85%, rgba(16,185,129,0.3), transparent 50%);
  mix-blend-mode: screen;
}
.hero-inner {
  position: relative; z-index: 2; color: #fff;
  display: grid; grid-template-columns: 1.05fr 0.95fr; gap: 2rem; align-items: center;
}
.hero-content { max-width: 640px; }
.hero-eyebrow {
  display: inline-flex; align-items: center; gap: 0.4rem;
  background: rgba(255,255,255,0.16); backdrop-filter: blur(6px);
  padding: 0.4rem 0.9rem; border-radius: var(--pub-radius-pill);
  font-size: 0.82rem; font-weight: 700; margin-bottom: 1rem;
  border: 1px solid rgba(255,255,255,0.2);
}
.hero-title {
  font-size: clamp(2rem, 5.5vw, 3.5rem);
  font-weight: 800; line-height: 1.15; margin: 0;
  white-space: pre-line; letter-spacing: -0.01em;
  text-shadow: 0 2px 20px rgba(0,0,0,0.2);
}
.hero-sub { font-size: clamp(1rem, 2vw, 1.18rem); opacity: 0.94; margin: 1.1rem 0 0; line-height: 1.6; max-width: 620px; }
.hero-actions { display: flex; flex-wrap: wrap; gap: 0.8rem; margin-top: 1.8rem; }
.hero-stats {
  display: flex; gap: 2.5rem; margin-top: 2rem;
  padding-top: 1.6rem; border-top: 1px solid rgba(255,255,255,0.22);
}
.hero-stat :deep(.sc-value) { color: #fff; }
.hero-stat :deep(.sc-label) { color: rgba(255,255,255,0.8); }

/* trust row */
.hero-trust { display: flex; align-items: center; gap: 0.9rem; margin-top: 1.8rem; }
.hero-avatars { display: flex; }
.hero-av {
  width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center;
  color: #fff; font-weight: 800; font-size: 0.95rem; border: 2.5px solid rgba(255,255,255,0.85);
  margin-left: -12px; box-shadow: 0 4px 10px rgba(0,0,0,0.18);
}
.hero-av:first-child { margin-left: 0; }
.hero-trust-text { display: flex; flex-direction: column; line-height: 1.2; }
.hero-rating { color: #fbbf24; font-size: 0.95rem; letter-spacing: 1px; }
.hero-rating b { color: #fff; margin-left: 0.3rem; }
.hero-trust-text span { font-size: 0.82rem; opacity: 0.9; }

/* hero floating visual */
.hero-visual { position: relative; height: 440px; display: block; }
.hv-glow { position: absolute; inset: 8% 6%; background: radial-gradient(circle at 50% 40%, rgba(129,140,248,0.55), rgba(34,211,238,0.25) 55%, transparent 72%); filter: blur(20px); border-radius: 50%; }
.hv-ring { position: absolute; border-radius: 50%; border: 1.5px dashed rgba(255,255,255,0.25); }
.hv-ring-1 { inset: 4%; animation: hv-spin 26s linear infinite; }
.hv-ring-2 { inset: 18%; border-style: solid; border-color: rgba(255,255,255,0.12); animation: hv-spin 18s linear infinite reverse; }
@keyframes hv-spin { to { transform: rotate(360deg); } }

.hv-card {
  position: absolute; background: rgba(255,255,255,0.95); color: #1a1f37;
  border-radius: 18px; box-shadow: 0 24px 60px rgba(10,12,40,0.35);
  border: 1px solid rgba(255,255,255,0.6);
}
[data-theme="dark"] .hv-card { background: rgba(30,38,60,0.92); color: #f1f5f9; border-color: rgba(255,255,255,0.08); }

.hv-main { top: 28%; left: 6%; width: 270px; padding: 1.1rem 1.2rem; backdrop-filter: blur(8px); animation: hv-bob 5s ease-in-out infinite; }
.hv-main-top { display: flex; align-items: center; justify-content: space-between; }
.hv-main-ic { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; font-size: 1.2rem; background: linear-gradient(135deg,#6366f1,#22d3ee); }
.hv-live { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.72rem; font-weight: 800; color: #ef4444; }
.hv-live i { width: 7px; height: 7px; border-radius: 50%; background: #ef4444; animation: hv-pulse 1.4s ease-in-out infinite; }
@keyframes hv-pulse { 50% { opacity: 0.3; } }
.hv-main-title { font-weight: 800; font-size: 0.98rem; margin: 0.85rem 0 0.7rem; }
.hv-progress { height: 7px; border-radius: 4px; background: rgba(99,102,241,0.18); overflow: hidden; }
.hv-progress span { display: block; height: 100%; border-radius: 4px; background: linear-gradient(90deg,#6366f1,#22d3ee); }
.hv-main-foot { display: flex; align-items: center; gap: 0.6rem; margin-top: 0.8rem; }
.hv-mini-avatars { display: flex; }
.hv-mini-avatars span { width: 22px; height: 22px; border-radius: 50%; margin-left: -7px; border: 2px solid #fff; background: linear-gradient(135deg,#0ea882,#34d399); }
.hv-mini-avatars span:first-child { margin-left: 0; background: linear-gradient(135deg,#ec4899,#f472b6); }
.hv-mini-avatars span:nth-child(2) { background: linear-gradient(135deg,#f59e0b,#fbbf24); }
.hv-foot-text { font-size: 0.76rem; color: var(--pub-text-muted); }

.hv-float { padding: 0.8rem 0.95rem; display: flex; align-items: center; gap: 0.7rem; }
.hv-float strong { display: block; font-size: 0.98rem; font-weight: 800; }
.hv-float span { font-size: 0.72rem; color: var(--pub-text-muted); }
.hv-result { top: 8%; right: 4%; animation: hv-bob 6s ease-in-out infinite 0.5s; }
.hv-result-ring { position: relative; width: 44px; height: 44px; }
.hv-result-ring svg { width: 44px; height: 44px; transform: rotate(-90deg); }
.hv-result-ring circle { fill: none; stroke: rgba(99,102,241,0.18); stroke-width: 5; }
.hv-result-ring .hv-result-fill { stroke: #0ea882; stroke-width: 5; stroke-linecap: round; stroke-dasharray: 113; stroke-dashoffset: 6; }
.hv-result-ring b { position: absolute; inset: 0; display: grid; place-items: center; font-size: 0.7rem; font-weight: 800; color: #0ea882; }
.hv-streak { bottom: 10%; right: 12%; animation: hv-bob 5.5s ease-in-out infinite 1s; }
.hv-streak-ic { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; font-size: 1.2rem; background: linear-gradient(135deg,#f59e0b,#ef4444); }
@keyframes hv-bob { 50% { transform: translateY(-12px); } }

@media (max-width: 960px) {
  .hero-inner { grid-template-columns: 1fr; }
  .hero-visual { display: none; }
  .hero-content { max-width: 720px; }
}
.hero-dots { position: absolute; bottom: 1.5rem; left: 50%; transform: translateX(-50%); z-index: 3; display: flex; gap: 0.5rem; }
.hero-dots button {
  width: 10px; height: 10px; border-radius: 50%; border: none; cursor: pointer;
  background: rgba(255,255,255,0.45); transition: all 0.25s;
}
.hero-dots button.active { background: #fff; width: 28px; border-radius: 6px; }

.slide-fade-enter-active, .slide-fade-leave-active { transition: opacity 1s ease; }
.slide-fade-enter-from, .slide-fade-leave-to { opacity: 0; }

/* ===== CLASS NAV ===== */
.class-nav-sec { padding: 2rem 0; }
.class-nav { display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center; }
.class-pill {
  display: inline-flex; align-items: center; gap: 0.5rem;
  padding: 0.7rem 1.3rem; border-radius: var(--pub-radius-pill);
  background: var(--pub-surface); border: 1.5px solid var(--pub-border);
  color: var(--pub-text); font-weight: 700; font-size: 0.92rem; text-decoration: none;
  transition: all 0.18s; box-shadow: var(--pub-shadow-sm);
}
.class-pill:hover { border-color: var(--pub-accent); color: var(--pub-accent); transform: translateY(-2px); }
.class-pill-ic { font-size: 1.2rem; }

/* ===== SECTION HEAD ===== */
.pub-section-head--center { justify-content: center; text-align: center; }
.center-head { max-width: 620px; }
.center-head .pub-sub { margin-left: auto; margin-right: auto; }

/* ===== COURSE BENTO ===== */
.course-bento {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.25rem;
}
@media (max-width: 980px) { .course-bento { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .course-bento { grid-template-columns: 1fr; } }

/* ===== WHY ===== */
.why-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; }
.why-card { padding: 1.6rem 1.4rem; text-align: left; }
.why-ic { width: 54px; height: 54px; border-radius: 16px; display: grid; place-items: center; font-size: 1.6rem; margin-bottom: 1rem; }
.why-card h3 { font-size: 1.05rem; font-weight: 800; margin: 0 0 0.4rem; color: var(--pub-text); }
.why-card p { font-size: 0.88rem; color: var(--pub-text-muted); line-height: 1.55; margin: 0; }
@media (max-width: 900px) { .why-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .why-grid { grid-template-columns: 1fr; } }

/* ===== MODES ===== */
.modes-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.mode-panel {
  position: relative; padding: 2.5rem 2rem; border-radius: var(--pub-radius-lg);
  color: #fff; overflow: hidden; min-height: 240px;
  display: flex; flex-direction: column; justify-content: flex-end;
}
.mode-online { background: linear-gradient(135deg, #4f46e5, #06b6d4); }
.mode-offline { background: linear-gradient(135deg, #0f766e, #10b981); }
.mode-tag {
  align-self: flex-start; background: rgba(255,255,255,0.2); backdrop-filter: blur(4px);
  padding: 0.3rem 0.85rem; border-radius: var(--pub-radius-pill); font-size: 0.75rem; font-weight: 800;
  margin-bottom: auto;
}
.mode-tag--gold { background: rgba(255,255,255,0.25); }
.mode-panel h3 { font-size: 1.5rem; font-weight: 800; margin: 1rem 0 0.5rem; }
.mode-panel p { opacity: 0.92; line-height: 1.55; margin: 0 0 1rem; }
.mode-link { color: #fff; font-weight: 800; text-decoration: none; }
@media (max-width: 760px) { .modes-grid { grid-template-columns: 1fr; } }

/* ===== EVENT STRIP ===== */
.event-strip { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
.event-card { display: flex; gap: 1rem; padding: 1.1rem; align-items: center; }
.event-date {
  flex-shrink: 0; width: 60px; height: 60px; border-radius: 14px;
  background: var(--pub-accent-soft); color: var(--pub-accent);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.event-date strong { font-size: 1.4rem; line-height: 1; }
.event-date span { font-size: 0.7rem; text-transform: uppercase; font-weight: 700; }
.event-body h3 { font-size: 1rem; font-weight: 700; margin: 0 0 0.25rem; color: var(--pub-text); }
.event-body p { font-size: 0.8rem; color: var(--pub-text-muted); margin: 0; }
@media (max-width: 860px) { .event-strip { grid-template-columns: 1fr; } }

/* ===== STATS BAND ===== */
.stats-band { background: var(--pub-hero-grad); padding: 3rem 0; }
.stats-band-inner { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; }
.stats-band :deep(.sc-value) { color: #fff; }
.stats-band :deep(.sc-label) { color: rgba(255,255,255,0.82); }
@media (max-width: 560px) { .stats-band-inner { grid-template-columns: repeat(2, 1fr); gap: 2rem; } }

/* ===== STORIES ===== */
.story-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; }
.story-card { overflow: hidden; text-decoration: none; color: inherit; }
.story-img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; }
.story-img--ph { display: grid; place-items: center; font-size: 2.5rem; background: var(--pub-accent-soft); }
.story-body { padding: 1rem; }
.story-body h3 { font-size: 1rem; font-weight: 800; margin: 0 0 0.3rem; color: var(--pub-text); }
.story-body p { font-size: 0.82rem; color: var(--pub-text-muted); margin: 0; }
@media (max-width: 900px) { .story-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .story-grid { grid-template-columns: 1fr; } }

/* ===== TESTIMONIALS ===== */
.testi-track { display: flex; gap: 1.25rem; overflow-x: auto; padding: 0.5rem 0.25rem 1.25rem; scroll-snap-type: x mandatory; }
.testi-track::-webkit-scrollbar { height: 6px; }
.testi-track::-webkit-scrollbar-thumb { background: var(--pub-border-strong); border-radius: 3px; }
.testi-card { flex: 0 0 340px; padding: 1.5rem; scroll-snap-align: start; display: flex; flex-direction: column; }
.testi-stars { color: #f59e0b; letter-spacing: 2px; margin-bottom: 0.75rem; }
.testi-text { font-size: 0.92rem; line-height: 1.65; color: var(--pub-text-soft); margin: 0 0 1rem; flex: 1; }
.testi-card footer { display: flex; align-items: center; gap: 0.75rem; }
.testi-avatar { width: 42px; height: 42px; border-radius: 50%; background: var(--pub-hero-grad); color: #fff; display: grid; place-items: center; font-weight: 800; }
.testi-card footer strong { display: block; font-size: 0.9rem; color: var(--pub-text); }
.testi-card footer span { font-size: 0.78rem; color: var(--pub-text-muted); }

/* ===== GALLERY ===== */
.gallery-masonry { display: grid; grid-template-columns: repeat(4, 1fr); grid-auto-rows: 160px; gap: 0.85rem; }
.gal-item { position: relative; overflow: hidden; border-radius: var(--pub-radius); margin: 0; }
.gal-item img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s; }
.gal-item:hover img { transform: scale(1.08); }
.gal-item figcaption {
  position: absolute; inset: auto 0 0 0; padding: 0.6rem 0.8rem;
  background: linear-gradient(transparent, rgba(0,0,0,0.7)); color: #fff;
  font-size: 0.8rem; font-weight: 600;
}
.gal-0 { grid-column: span 2; grid-row: span 2; }
.gal-3 { grid-column: span 2; }
@media (max-width: 700px) {
  .gallery-masonry { grid-template-columns: repeat(2, 1fr); }
  .gal-0 { grid-column: span 2; }
  .gal-3 { grid-column: span 1; }
}

/* ===== TEACHERS ===== */
.teacher-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; }
.teacher-card { text-align: center; padding: 1.5rem 1rem; text-decoration: none; color: inherit; }
.teacher-img { width: 96px; height: 96px; border-radius: 50%; object-fit: cover; margin: 0 auto 0.85rem; }
.teacher-img--ph { display: grid; place-items: center; font-size: 2.2rem; background: var(--pub-hero-grad); color: #fff; font-weight: 800; }
.teacher-card h3 { font-size: 1rem; font-weight: 800; margin: 0 0 0.2rem; color: var(--pub-text); }
.teacher-card p { font-size: 0.82rem; color: var(--pub-text-muted); margin: 0; }
@media (max-width: 900px) { .teacher-row { grid-template-columns: repeat(2, 1fr); } }

/* ===== BLOG + NOTICES ===== */
.blog-notice-grid { display: grid; grid-template-columns: 1.7fr 1fr; gap: 2rem; }
.blog-list { display: flex; flex-direction: column; gap: 1rem; }
.blog-row { display: flex; gap: 1rem; padding: 0.85rem; text-decoration: none; color: inherit; }
.blog-thumb { width: 110px; height: 80px; border-radius: 12px; object-fit: cover; flex-shrink: 0; }
.blog-thumb--ph { display: grid; place-items: center; font-size: 1.6rem; background: var(--pub-accent-soft); }
.blog-row-body h3 { font-size: 1rem; font-weight: 700; margin: 0 0 0.3rem; color: var(--pub-text); }
.blog-row-body p { font-size: 0.84rem; color: var(--pub-text-muted); margin: 0; line-height: 1.5; }
.bn-notices .pub-section-head { margin-bottom: 1rem; }
.notice-list { display: flex; flex-direction: column; gap: 0.25rem; }
.notice-row { display: flex; gap: 0.7rem; padding: 0.8rem; border-radius: 12px; text-decoration: none; color: inherit; transition: background 0.15s; }
.notice-row:hover { background: var(--pub-surface-2); }
.notice-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--pub-accent); margin-top: 0.45rem; flex-shrink: 0; }
.notice-row strong { display: block; font-size: 0.9rem; color: var(--pub-text); }
.notice-row small { font-size: 0.75rem; color: var(--pub-text-muted); }
@media (max-width: 800px) { .blog-notice-grid { grid-template-columns: 1fr; } }

/* ===== DOWNLOADS ===== */
.downloads-cta { display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap; }

/* ===== FINAL CTA ===== */
.final-cta { background: var(--pub-hero-grad); padding: clamp(3rem, 8vw, 5rem) 0; text-align: center; }
.final-cta-inner { color: #fff; max-width: 720px; margin: 0 auto; }
.final-cta-inner h2 { font-size: clamp(1.6rem, 4vw, 2.4rem); font-weight: 800; margin: 0 0 0.75rem; }
.final-cta-inner p { font-size: 1.05rem; opacity: 0.92; margin: 0 0 1.8rem; }
.final-cta-actions { display: flex; flex-wrap: wrap; gap: 0.8rem; justify-content: center; }
</style>

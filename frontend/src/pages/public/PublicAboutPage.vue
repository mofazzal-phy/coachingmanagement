<template>
  <div>
    <PublicHero :title="t('nav.about')" :subtitle="t('brand.tagline')" />

    <section class="pub-section">
      <div class="pub-container about-intro">
        <div v-reveal>
          <span class="pub-eyebrow">{{ t('brand.name') }}</span>
          <h2 class="pub-title">{{ isBn() ? 'আমাদের গল্প' : 'Our story' }}</h2>
          <p class="about-lead">
            {{ isBn()
              ? 'পড়ালেখা একটি আধুনিক কোচিং সেন্টার, যেখানে প্রযুক্তি ও অভিজ্ঞ শিক্ষকদের সমন্বয়ে প্রতিটি শিক্ষার্থীর জন্য তৈরি হয় সেরা শেখার পরিবেশ। আমরা বিশ্বাস করি — সঠিক দিকনির্দেশনা পেলে প্রতিটি শিক্ষার্থীই পারে অসাধারণ কিছু করতে।'
              : 'Poralekha is a modern coaching center where technology meets experienced teachers to create the best learning environment for every student. We believe every learner can achieve something extraordinary with the right guidance.' }}
          </p>
        </div>
      </div>
    </section>

    <section class="pub-section pub-section--alt">
      <div class="pub-container mv-grid">
        <div class="mv-card pub-card" v-reveal>
          <div class="mv-ic">🎯</div>
          <h3>{{ isBn() ? 'আমাদের লক্ষ্য' : 'Our Mission' }}</h3>
          <p>{{ isBn() ? 'মানসম্মত শিক্ষা সবার নাগালে পৌঁছে দেওয়া এবং প্রতিটি শিক্ষার্থীর সম্ভাবনার সর্বোচ্চ বিকাশ নিশ্চিত করা।' : 'To make quality education accessible to all and unlock the full potential of every student.' }}</p>
        </div>
        <div class="mv-card pub-card" v-reveal="100">
          <div class="mv-ic">🌟</div>
          <h3>{{ isBn() ? 'আমাদের দৃষ্টিভঙ্গি' : 'Our Vision' }}</h3>
          <p>{{ isBn() ? 'দেশের সবচেয়ে বিশ্বস্ত ও উদ্ভাবনী শিক্ষা প্রতিষ্ঠান হিসেবে গড়ে ওঠা।' : 'To become the most trusted and innovative education institution in the country.' }}</p>
        </div>
      </div>
    </section>

    <section class="stats-band">
      <div class="pub-container stats-band-inner">
        <StatCounter :value="5000" :label="t('stats.students')" suffix="+" />
        <StatCounter :value="30" :label="t('stats.teachers')" suffix="+" />
        <StatCounter :value="25" :label="t('stats.courses')" suffix="+" />
        <StatCounter :value="95" :label="t('stats.successRate')" suffix="%" />
      </div>
    </section>

    <section class="pub-section">
      <div class="pub-container">
        <div class="pub-section-head pub-section-head--center" v-reveal>
          <div class="center-head">
            <h2 class="pub-title">{{ t('section.whyUs') }}</h2>
            <p class="pub-sub">{{ t('section.whyUs.sub') }}</p>
          </div>
        </div>
        <div class="val-grid">
          <div v-for="(f, i) in features" :key="f.icon" class="val-card pub-card" v-reveal="i * 70">
            <div class="val-ic">{{ f.icon }}</div>
            <h4>{{ isBn() ? f.bn : f.en }}</h4>
            <p>{{ isBn() ? f.bnd : f.end }}</p>
          </div>
        </div>
      </div>
    </section>

    <section class="final-cta">
      <div class="pub-container final-cta-inner" v-reveal>
        <h2>{{ t('cta.title') }}</h2>
        <div class="final-cta-actions">
          <router-link to="/site/enroll" class="pub-btn pub-btn--light">{{ t('hero.ctaPrimary') }}</router-link>
          <router-link to="/site/contact" class="pub-btn pub-btn--accent">{{ t('nav.contact') }}</router-link>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { useLang } from '@/composables/useLang'
import PublicHero from '@/components/public/PublicHero.vue'
import StatCounter from '@/components/public/StatCounter.vue'

const { t, isBn } = useLang()

const features = [
  { icon: '👨‍🏫', bn: 'অভিজ্ঞ শিক্ষক', en: 'Expert teachers', bnd: 'দেশসেরা ও অভিজ্ঞ শিক্ষকমণ্ডলী।', end: 'A panel of the country’s best teachers.' },
  { icon: '🎥', bn: 'লাইভ ও রেকর্ডেড', en: 'Live & recorded', bnd: 'যেকোনো সময় ক্লাস দেখার সুবিধা।', end: 'Watch classes anytime, anywhere.' },
  { icon: '📊', bn: 'নিয়মিত মূল্যায়ন', en: 'Regular assessment', bnd: 'পরীক্ষা ও বিস্তারিত পারফরম্যান্স রিপোর্ট।', end: 'Tests with detailed performance reports.' },
  { icon: '💳', bn: 'সহজ পেমেন্ট', en: 'Easy payment', bnd: 'কিস্তি ও স্বচ্ছ ফি ব্যবস্থা।', end: 'Installments and transparent fees.' },
  { icon: '🏫', bn: 'আধুনিক ক্যাম্পাস', en: 'Modern campus', bnd: 'অত্যাধুনিক মাল্টিমিডিয়া ক্লাসরুম।', end: 'State-of-the-art multimedia classrooms.' },
  { icon: '🤝', bn: 'অভিভাবক সংযোগ', en: 'Guardian connect', bnd: 'নিয়মিত অগ্রগতি জানার ব্যবস্থা।', end: 'Stay updated on your child’s progress.' },
]
</script>

<style scoped>
.about-intro { max-width: 760px; }
.about-lead { font-size: 1.1rem; line-height: 1.75; color: var(--pub-text-soft); margin-top: 1rem; }
.mv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.mv-card { padding: 2rem; }
.mv-ic { font-size: 2.2rem; margin-bottom: 0.75rem; }
.mv-card h3 { font-size: 1.25rem; font-weight: 800; margin: 0 0 0.5rem; color: var(--pub-text); }
.mv-card p { color: var(--pub-text-muted); line-height: 1.65; margin: 0; }
.stats-band { background: var(--pub-hero-grad); padding: 3rem 0; }
.stats-band-inner { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; }
.stats-band :deep(.sc-value) { color: #fff; }
.stats-band :deep(.sc-label) { color: rgba(255,255,255,0.82); }
.pub-section-head--center { justify-content: center; text-align: center; }
.center-head { max-width: 620px; }
.center-head .pub-sub { margin: 0.4rem auto 0; }
.val-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
.val-card { padding: 1.6rem; }
.val-ic { font-size: 1.9rem; margin-bottom: 0.6rem; }
.val-card h4 { font-size: 1.05rem; font-weight: 800; margin: 0 0 0.4rem; color: var(--pub-text); }
.val-card p { font-size: 0.88rem; color: var(--pub-text-muted); line-height: 1.55; margin: 0; }
.final-cta { background: var(--pub-hero-grad); padding: clamp(3rem, 8vw, 5rem) 0; text-align: center; }
.final-cta-inner { color: #fff; max-width: 720px; margin: 0 auto; }
.final-cta-inner h2 { font-size: clamp(1.6rem, 4vw, 2.4rem); font-weight: 800; margin: 0 0 1.5rem; }
.final-cta-actions { display: flex; flex-wrap: wrap; gap: 0.8rem; justify-content: center; }
@media (max-width: 760px) { .mv-grid, .val-grid { grid-template-columns: 1fr; } .stats-band-inner { grid-template-columns: repeat(2, 1fr); } }
</style>

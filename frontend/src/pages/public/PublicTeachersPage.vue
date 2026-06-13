<template>
  <div>
    <PublicHero :title="t('section.teachers')" :subtitle="t('section.teachers.sub')" />
    <section class="pub-section">
      <div class="pub-container">
        <ContentState :loading="loading" :error="error" :empty="!loading && !error && !items.length" :on-retry="load"
          :empty-text="isBn() ? 'শিক্ষকদের তথ্য শীঘ্রই যুক্ত হবে।' : 'Teacher profiles coming soon.'" />
        <div v-if="!loading && items.length" class="tch-grid">
          <router-link v-for="tch in items" :key="tch.id" :to="`/site/teachers/${tch.id}`" class="tch-card pub-card" v-reveal>
            <img v-if="tch.photo_url || tch.avatar_url || tch.image_url" :src="tch.photo_url || tch.avatar_url || tch.image_url" :alt="tch.name" class="tch-img" loading="lazy" />
            <div v-else class="tch-img tch-img--ph">{{ (tch.name || '?').slice(0, 1) }}</div>
            <h3>{{ tch.name }}</h3>
            <p class="tch-role">{{ tch.designation || tch.subject || '' }}</p>
            <p v-if="tch.qualification" class="tch-qual">{{ tch.qualification }}</p>
          </router-link>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import cmsPublicService from '@/services/cms-public.service'
import { useLang } from '@/composables/useLang'
import PublicHero from '@/components/public/PublicHero.vue'
import ContentState from '@/components/public/ContentState.vue'

const { t, isBn } = useLang()
const items = ref([])
const loading = ref(true)
const error = ref(false)

const load = async () => {
  loading.value = true; error.value = false
  try { items.value = cmsPublicService.extractList(await cmsPublicService.teachers()) }
  catch { error.value = true } finally { loading.value = false }
}
onMounted(load)
</script>

<style scoped>
.tch-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; }
.tch-card { text-align: center; padding: 1.8rem 1.2rem; text-decoration: none; color: inherit; }
.tch-img { width: 110px; height: 110px; border-radius: 50%; object-fit: cover; margin: 0 auto 1rem; }
.tch-img--ph { display: grid; place-items: center; font-size: 2.6rem; background: var(--pub-hero-grad); color: #fff; font-weight: 800; }
.tch-card h3 { font-size: 1.1rem; font-weight: 800; margin: 0 0 0.25rem; color: var(--pub-text); }
.tch-role { font-size: 0.85rem; color: var(--pub-accent); font-weight: 600; margin: 0 0 0.3rem; }
.tch-qual { font-size: 0.8rem; color: var(--pub-text-muted); margin: 0; }
@media (max-width: 900px) { .tch-grid { grid-template-columns: repeat(2, 1fr); } }
</style>

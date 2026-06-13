<template>
  <footer class="pf">
    <div class="pf-top pub-container">
      <div class="pf-brand-col">
        <router-link to="/site" class="pf-brand">
          <span class="pf-logo">প</span>
          <strong>{{ t('brand.name') }}</strong>
        </router-link>
        <p class="pf-about">{{ t('footer.about') }}</p>
        <div class="pf-social">
          <a :href="social.facebook" target="_blank" rel="noopener" aria-label="Facebook">f</a>
          <a :href="social.youtube" target="_blank" rel="noopener" aria-label="YouTube">▶</a>
          <a :href="social.instagram" target="_blank" rel="noopener" aria-label="Instagram">◎</a>
          <a :href="social.linkedin" target="_blank" rel="noopener" aria-label="LinkedIn">in</a>
        </div>
      </div>

      <div class="pf-col">
        <h4>{{ t('footer.quickLinks') }}</h4>
        <router-link to="/site/courses">{{ t('nav.courses') }}</router-link>
        <router-link to="/site/batches">{{ t('nav.batches') }}</router-link>
        <router-link to="/site/enroll">{{ t('nav.apply') }}</router-link>
        <router-link to="/site/teachers">{{ t('nav.teachers') }}</router-link>
        <router-link to="/site/about">{{ t('nav.about') }}</router-link>
      </div>

      <div class="pf-col">
        <h4>{{ t('footer.resources') }}</h4>
        <router-link to="/site/blog">{{ t('nav.blog') }}</router-link>
        <router-link to="/site/notices">{{ t('nav.notices') }}</router-link>
        <router-link to="/site/events">{{ t('nav.events') }}</router-link>
        <router-link to="/site/gallery">{{ t('nav.gallery') }}</router-link>
        <router-link to="/site/downloads">{{ t('nav.downloads') }}</router-link>
      </div>

      <div class="pf-col">
        <h4>{{ t('footer.contact') }}</h4>
        <a :href="telLink()" class="pf-contact"><span>📞</span> {{ contact.phoneDisplay }}</a>
        <a :href="mailtoLink()" class="pf-contact"><span>✉️</span> {{ contact.email }}</a>
        <a :href="whatsappLink()" target="_blank" rel="noopener" class="pf-contact"><span>💬</span> WhatsApp</a>
        <span class="pf-contact"><span>📍</span> {{ tf(addressObj, 'line') }}</span>
      </div>
    </div>

    <div class="pf-bottom">
      <div class="pub-container pf-bottom-inner">
        <span>© {{ year }} {{ t('brand.name') }}. {{ t('footer.rights') }}</span>
        <div class="pf-bottom-links">
          <router-link to="/site/pages/privacy">Privacy</router-link>
          <router-link to="/site/pages/terms">Terms</router-link>
          <router-link to="/site/contact">{{ t('nav.contact') }}</router-link>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { useLang } from '@/composables/useLang'
import { siteConfig, whatsappLink, telLink, mailtoLink } from '@/config/siteConfig'

const { t, tf } = useLang()
const year = new Date().getFullYear()
const contact = siteConfig.contact
const social = siteConfig.social
const addressObj = siteConfig.address
</script>

<style scoped>
.pf {
  margin-top: auto;
  background: var(--pub-bg-alt);
  border-top: 1px solid var(--pub-border);
}
.pf-top {
  display: grid;
  grid-template-columns: 1.6fr 1fr 1fr 1.3fr;
  gap: 2rem;
  padding: 3rem 1.25rem 2.5rem;
}
.pf-brand { display: flex; align-items: center; gap: 0.6rem; text-decoration: none; color: var(--pub-text); }
.pf-logo {
  width: 38px; height: 38px; display: grid; place-items: center;
  border-radius: 11px; background: var(--pub-hero-grad); color: #fff; font-weight: 800; font-size: 1.15rem;
}
.pf-brand strong { font-size: 1.15rem; }
.pf-about { color: var(--pub-text-muted); font-size: 0.9rem; line-height: 1.6; margin: 1rem 0; max-width: 300px; }
.pf-social { display: flex; gap: 0.5rem; }
.pf-social a {
  width: 36px; height: 36px; display: grid; place-items: center;
  border-radius: 50%; background: var(--pub-surface); border: 1px solid var(--pub-border);
  color: var(--pub-text-soft); text-decoration: none; font-weight: 800; font-size: 0.85rem;
  transition: all 0.18s;
}
.pf-social a:hover { background: var(--pub-accent); color: #fff; border-color: var(--pub-accent); transform: translateY(-2px); }

.pf-col { display: flex; flex-direction: column; gap: 0.6rem; }
.pf-col h4 { font-size: 0.95rem; font-weight: 800; margin-bottom: 0.4rem; color: var(--pub-text); }
.pf-col a { color: var(--pub-text-muted); text-decoration: none; font-size: 0.9rem; transition: color 0.15s; }
.pf-col a:hover { color: var(--pub-accent); }
.pf-contact { display: flex; align-items: center; gap: 0.5rem; }

.pf-bottom { border-top: 1px solid var(--pub-border); }
.pf-bottom-inner {
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  padding: 1.1rem 1.25rem; flex-wrap: wrap;
  font-size: 0.84rem; color: var(--pub-text-muted);
}
.pf-bottom-links { display: flex; gap: 1.1rem; }
.pf-bottom-links a { color: var(--pub-text-muted); text-decoration: none; }
.pf-bottom-links a:hover { color: var(--pub-accent); }

@media (max-width: 860px) {
  .pf-top { grid-template-columns: 1fr 1fr; gap: 1.5rem; }
}
@media (max-width: 520px) {
  .pf-top { grid-template-columns: 1fr; }
}
</style>

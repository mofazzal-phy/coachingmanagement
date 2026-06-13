<template>
  <div>
    <PublicHero :title="t('section.contact')" :subtitle="isBn() ? 'যেকোনো প্রশ্নে আমরা আছি আপনার পাশে' : 'We are here to help with any question'" />

    <section class="pub-section pub-pat pat-contact">
      <div class="pub-container ct-grid">
        <!-- Contact info + CTAs -->
        <aside class="ct-info">
          <a :href="telLink()" class="ct-tile pub-card">
            <span class="ct-ic ct-ic--blue">📞</span>
            <div><small>{{ t('contact.callUs') }}</small><strong>{{ contact.phoneDisplay }}</strong></div>
          </a>
          <a :href="wa" target="_blank" rel="noopener" class="ct-tile pub-card">
            <span class="ct-ic ct-ic--green">💬</span>
            <div><small>{{ t('action.whatsapp') }}</small><strong>{{ contact.whatsapp }}</strong></div>
          </a>
          <a :href="mailtoLink()" class="ct-tile pub-card">
            <span class="ct-ic ct-ic--violet">✉️</span>
            <div><small>{{ t('contact.emailUs') }}</small><strong>{{ contact.email }}</strong></div>
          </a>
          <div class="ct-tile pub-card">
            <span class="ct-ic ct-ic--amber">📍</span>
            <div><small>{{ t('contact.visitUs') }}</small><strong>{{ tf(address, 'line') }}</strong></div>
          </div>
          <div class="ct-tile pub-card">
            <span class="ct-ic ct-ic--blue">🕒</span>
            <div><small>{{ t('contact.hours') }}</small><strong>{{ tf(contactObj, 'hours') }}</strong></div>
          </div>
        </aside>

        <!-- Form -->
        <div class="ct-form-wrap pub-card">
          <h2>{{ isBn() ? 'বার্তা পাঠান' : 'Send us a message' }}</h2>
          <transition name="ct-ok">
            <div v-if="sent" class="ct-success">✓ {{ t('contact.success') }}</div>
          </transition>
          <form class="ct-form" @submit.prevent="submit" autocomplete="off">
            <input v-model="form.website" class="ct-hp" type="text" tabindex="-1" autocomplete="off" aria-hidden="true" />
            <div class="ct-row">
              <div class="ct-field">
                <label>{{ t('contact.name') }} *</label>
                <input v-model.trim="form.name" type="text" required />
              </div>
              <div class="ct-field">
                <label>{{ t('contact.phone') }} *</label>
                <input v-model.trim="form.phone" type="tel" required />
              </div>
            </div>
            <div class="ct-row">
              <div class="ct-field">
                <label>{{ t('contact.email') }}</label>
                <input v-model.trim="form.email" type="email" />
              </div>
              <div class="ct-field">
                <label>{{ t('contact.subject') }}</label>
                <input v-model.trim="form.subject" type="text" />
              </div>
            </div>
            <div class="ct-field">
              <label>{{ t('contact.message') }} *</label>
              <textarea v-model.trim="form.message" rows="5" required></textarea>
            </div>
            <p v-if="error" class="ct-error">{{ error }}</p>
            <button type="submit" class="pub-btn pub-btn--primary ct-submit" :disabled="loading">
              {{ loading ? t('action.sending') : t('contact.send') }}
            </button>
          </form>
        </div>
      </div>
    </section>

    <section class="ct-map-sec">
      <iframe
        :src="address.mapEmbed"
        class="ct-map"
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        title="map"
      ></iframe>
    </section>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import cmsPublicService from '@/services/cms-public.service'
import { useLang } from '@/composables/useLang'
import { siteConfig, telLink, mailtoLink, whatsappLink } from '@/config/siteConfig'
import PublicHero from '@/components/public/PublicHero.vue'

const { t, tf, isBn } = useLang()

const contact = siteConfig.contact
const contactObj = siteConfig.contact
const address = siteConfig.address
const wa = whatsappLink(isBn() ? 'আমি তথ্য জানতে চাই' : 'I would like more information')

const form = reactive({ name: '', phone: '', email: '', subject: '', message: '', website: '' })
const loading = ref(false)
const sent = ref(false)
const error = ref('')

const submit = async () => {
  loading.value = true; error.value = ''; sent.value = false
  try {
    await cmsPublicService.contact({ ...form })
    sent.value = true
    Object.keys(form).forEach((k) => (form[k] = ''))
  } catch (e) {
    error.value = e?.response?.data?.message || (isBn() ? 'বার্তা পাঠানো যায়নি, আবার চেষ্টা করুন।' : 'Could not send message, please try again.')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.ct-grid { display: grid; grid-template-columns: 340px 1fr; gap: 1.75rem; align-items: start; }
.ct-info { display: flex; flex-direction: column; gap: 0.85rem; }
.ct-tile { display: flex; align-items: center; gap: 0.9rem; padding: 1rem 1.1rem; text-decoration: none; color: inherit; }
.ct-ic { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; font-size: 1.3rem; flex-shrink: 0; }
.ct-ic--blue { background: rgba(79,70,229,0.12); }
.ct-ic--green { background: rgba(16,185,129,0.14); }
.ct-ic--violet { background: rgba(139,92,246,0.14); }
.ct-ic--amber { background: rgba(245,158,11,0.16); }
.ct-tile small { display: block; color: var(--pub-text-muted); font-size: 0.76rem; }
.ct-tile strong { color: var(--pub-text); font-size: 0.92rem; }

.ct-form-wrap { padding: 1.85rem; }
.ct-form-wrap h2 { font-size: 1.3rem; font-weight: 800; margin: 0 0 1.2rem; color: var(--pub-text); }
.ct-form { display: flex; flex-direction: column; gap: 1rem; }
.ct-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.ct-field { display: flex; flex-direction: column; gap: 0.4rem; }
.ct-field label { font-size: 0.84rem; font-weight: 700; color: var(--pub-text-soft); }
.ct-field input, .ct-field textarea {
  padding: 0.75rem 0.9rem; border-radius: var(--pub-radius-sm);
  border: 1.5px solid var(--pub-border); background: var(--pub-surface-2);
  color: var(--pub-text); font-size: 0.92rem; font-family: inherit; resize: vertical;
}
.ct-field input:focus, .ct-field textarea:focus { outline: none; border-color: var(--pub-accent); }
.ct-hp { position: absolute !important; left: -9999px !important; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.ct-submit { align-self: flex-start; }
.ct-error { color: #ef4444; font-size: 0.86rem; margin: 0; }
.ct-success { background: var(--pub-accent-2-soft); color: var(--pub-accent-2); padding: 0.8rem 1rem; border-radius: var(--pub-radius-sm); font-weight: 700; margin-bottom: 1rem; }
.ct-ok-enter-active { transition: all 0.3s; }
.ct-ok-enter-from { opacity: 0; transform: translateY(-6px); }

.ct-map-sec { height: 360px; }
.ct-map { width: 100%; height: 100%; border: 0; filter: grayscale(0.1); }
[data-theme="dark"] .ct-map { filter: grayscale(0.3) invert(0.9) hue-rotate(180deg); }

@media (max-width: 820px) { .ct-grid { grid-template-columns: 1fr; } .ct-row { grid-template-columns: 1fr; } }
</style>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import { AuraButton } from '@/shared/components'
import { resolveLocalizedText } from '@/i18n'
import type { AdminVenueItem } from '../../composables/useAdminVenues'

const props = defineProps<{
  show: boolean
  venue: AdminVenueItem | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const { t } = useI18n()

const qrDataUrl = ref('')
const copied = ref(false)
const generating = ref(false)

function getShareUrl(): string {
  if (!props.venue) return ''
  if (props.venue.share_url) return props.venue.share_url
  const origin = typeof window !== 'undefined' ? window.location.origin : 'https://auracourt.vn'
  return `${origin}/courts/${props.venue.slug}/directions`
}

async function generateQr() {
  if (!props.venue) return
  generating.value = true
  try {
    const url = getShareUrl()
    qrDataUrl.value = await QRCode.toDataURL(url, {
      width: 600,
      margin: 2,
      errorCorrectionLevel: 'H',
      color: {
        dark: '#0f172a',
        light: '#ffffff',
      },
    })
  } catch (err) {
    console.error('Failed to generate QR:', err)
  } finally {
    generating.value = false
  }
}

watch(
  () => [props.show, props.venue],
  ([show, venue]) => {
    if (show && venue) {
      copied.value = false
      generateQr()
    }
  },
  { immediate: true },
)

async function handleCopyLink() {
  const url = getShareUrl()
  if (!url) return
  try {
    await navigator.clipboard.writeText(url)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 3000)
  } catch (err) {
    console.error('Copy failed:', err)
  }
}

function handleDownloadQr() {
  if (!qrDataUrl.value || !props.venue) return
  const link = document.createElement('a')
  link.download = `${props.venue.slug}-directions-qr.png`
  link.href = qrDataUrl.value
  link.click()
}
</script>

<template>
  <div
    v-if="show && venue"
    class="modal-backdrop modal-overlay-backdrop"
    @click.self="emit('close')"
  >
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">qr_code_2</span>
          <h3 class="modal-title">
            {{ t('admin.venues.qrModalTitle', { name: resolveLocalizedText(venue.name) }) }}
          </h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body qr-body">
        <p class="qr-sub">{{ t('admin.venues.qrModalSub') }}</p>

        <!-- QR Display -->
        <div class="qr-box-wrap">
          <div v-if="generating" class="qr-loading">
            <span class="material-symbols-outlined spinner-icon">progress_activity</span>
          </div>
          <img v-else-if="qrDataUrl" :src="qrDataUrl" alt="Venue QR Code" class="qr-image" />
        </div>

        <!-- Share URL display with copy button -->
        <div class="share-url-container">
          <span class="share-label">{{ t('admin.venues.shareLinkNote') }}</span>
          <div class="url-copy-box">
            <code class="url-text font-mono">{{ getShareUrl() }}</code>
            <button
              type="button"
              class="btn-copy-icon"
              :title="t('admin.venues.copyLinkBtn')"
              @click="handleCopyLink"
            >
              <span class="material-symbols-outlined icon-16">
                {{ copied ? 'check' : 'content_copy' }}
              </span>
            </button>
          </div>
          <span v-if="copied" class="copied-feedback text-green">
            {{ t('admin.venues.copiedLink') }}
          </span>
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="secondary" @click="emit('close')">
          {{ t('common.close') }}
        </AuraButton>
        <AuraButton variant="primary" :disabled="!qrDataUrl" @click="handleDownloadQr">
          <span class="material-symbols-outlined icon-18">download</span>
          <span>{{ t('admin.venues.downloadQrBtn') }}</span>
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 18px;
  width: 100%;
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.28);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalPop {
  from {
    opacity: 0;
    transform: scale(0.96) translateY(8px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.modal-sm {
  max-width: 440px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 20px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.modal-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 18px;
  cursor: pointer;
  color: var(--muted);
  padding: 4px 8px;
  border-radius: 6px;
  transition: all 0.15s ease;
}

.modal-close-btn:hover {
  color: var(--ink);
  background: rgba(0, 0, 0, 0.06);
}

.qr-body {
  padding: 24px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  text-align: center;
}

.qr-sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
  line-height: 1.4;
}

.qr-box-wrap {
  width: 200px;
  height: 200px;
  padding: 10px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: #ffffff;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
  display: flex;
  align-items: center;
  justify-content: center;
}

.qr-image {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.qr-loading {
  display: flex;
  align-items: center;
  justify-content: center;
}

.spinner-icon {
  font-size: 28px;
  color: var(--pink-dark, #db2777);
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.share-url-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.share-label {
  font-size: 11px;
  color: var(--muted);
  text-align: left;
}

.url-copy-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  gap: 8px;
}

.url-text {
  font-size: 11px;
  color: var(--pink-dark, #db2777);
  word-break: break-all;
  text-align: left;
  flex: 1;
}

.btn-copy-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: none;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.15s ease;
}

.btn-copy-icon:hover {
  background: rgba(0, 0, 0, 0.05);
  color: var(--ink);
}

.copied-feedback {
  font-size: 11px;
  font-weight: 700;
  text-align: left;
}

.text-green {
  color: #10b981;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  padding: 16px 20px;
  border-top: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.icon-16 {
  font-size: 16px;
}
.icon-18 {
  font-size: 18px;
}
</style>

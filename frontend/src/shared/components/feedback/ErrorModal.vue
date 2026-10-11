<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  isRawInfrastructureMessage,
  resolveErrorDescriptor,
  translateMessage,
} from '@/core/utils/errorMessage'

const { t, locale } = useI18n()

const props = withDefaults(
  defineProps<{
    modelValue?: boolean
    open?: boolean
    title?: string
    message?: string
    error?: unknown
    details?: string | string[] | Record<string, string[]> | null
    errorCode?: string | number | null
    confirmText?: string
    retryText?: string
    showRetry?: boolean
  }>(),
  {
    modelValue: undefined,
    open: undefined,
    title: undefined,
    message: '',
    error: undefined,
    details: null,
    errorCode: null,
    confirmText: undefined,
    retryText: undefined,
    showRetry: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'update:open': [value: boolean]
  close: []
  retry: []
}>()

const modalCardRef = ref<HTMLElement | null>(null)
let originatingElement: HTMLElement | null = null

const isVisible = computed(() => {
  if (props.open !== undefined) return props.open
  if (props.modelValue !== undefined) return props.modelValue
  return false
})

// Structured error descriptor resolved reactively whenever error or locale changes
const descriptor = computed(() => {
  if (props.error) {
    return resolveErrorDescriptor(props.error, props.message, locale.value)
  }
  return null
})

const displayTitle = computed(() => {
  if (props.title) return props.title
  return t('errorModal.defaultTitle')
})

const displayConfirmText = computed(() => {
  if (props.confirmText) return props.confirmText
  return t('errorModal.confirm')
})

const displayRetryText = computed(() => {
  if (props.retryText) return props.retryText
  return t('errorModal.retry')
})
const displayErrorCode = computed(() => {
  if (props.errorCode) return props.errorCode
  if (descriptor.value?.supportCode) return descriptor.value.supportCode
  if (descriptor.value?.code) return descriptor.value.code
  return null
})

const displayMessage = computed(() => {
  if (descriptor.value?.message) {
    return descriptor.value.message
  }
  if (props.message) {
    if (isRawInfrastructureMessage(props.message)) {
      return locale.value === 'en'
        ? 'The server encountered an issue. Please try again later.'
        : 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.'
    }
    return translateMessage(props.message, locale.value)
  }
  return ''
})

const formattedDetails = computed<string[]>(() => {
  if (descriptor.value && descriptor.value.details.length > 0) {
    return descriptor.value.details
  }
  if (!props.details) return []
  if (typeof props.details === 'string') return [props.details]
  if (Array.isArray(props.details)) return props.details.filter(Boolean)
  if (typeof props.details === 'object') {
    const list: string[] = []
    for (const [key, val] of Object.entries(props.details)) {
      if (Array.isArray(val)) {
        list.push(...val)
      } else if (typeof val === 'string') {
        list.push(`${key}: ${val}`)
      }
    }
    return list
  }
  return []
})

function close() {
  emit('update:modelValue', false)
  emit('update:open', false)
  emit('close')
}

function handleRetry() {
  emit('retry')
}

// Focus Trap & Keyboard Navigation (Task 4.4)
function getFocusableElements(): HTMLElement[] {
  if (!modalCardRef.value) return []
  const selector =
    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
  return Array.from(modalCardRef.value.querySelectorAll<HTMLElement>(selector))
}

function handleKeydown(event: KeyboardEvent) {
  if (!isVisible.value) return

  if (event.key === 'Escape') {
    event.preventDefault()
    close()
    return
  }

  if (event.key === 'Tab') {
    const focusable = getFocusableElements()
    if (focusable.length === 0) return

    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (!first || !last) return
    if (event.shiftKey) {
      if (
        document.activeElement === first ||
        !modalCardRef.value?.contains(document.activeElement)
      ) {
        event.preventDefault()
        last.focus()
      }
    } else {
      if (
        document.activeElement === last ||
        !modalCardRef.value?.contains(document.activeElement)
      ) {
        event.preventDefault()
        first.focus()
      }
    }
  }
}

async function setupFocus() {
  await nextTick()
  const focusable = getFocusableElements()
  if (focusable.length > 0) {
    const confirmBtn = modalCardRef.value?.querySelector<HTMLElement>('.btn-confirm-close')
    if (confirmBtn) {
      confirmBtn.focus()
    } else {
      focusable[0]?.focus()
    }
  }
}

watch(isVisible, async (val) => {
  if (typeof document !== 'undefined') {
    if (val) {
      document.body.style.overflow = 'hidden'
      originatingElement = document.activeElement as HTMLElement | null
      await setupFocus()
    } else {
      document.body.style.overflow = ''
      if (originatingElement && typeof originatingElement.focus === 'function') {
        originatingElement.focus()
      }
    }
  }
})

onMounted(async () => {
  if (typeof document !== 'undefined') {
    document.addEventListener('keydown', handleKeydown)
    if (isVisible.value) {
      document.body.style.overflow = 'hidden'
      originatingElement = document.activeElement as HTMLElement | null
      await setupFocus()
    }
  }
})

onBeforeUnmount(() => {
  if (typeof document !== 'undefined') {
    document.removeEventListener('keydown', handleKeydown)
    document.body.style.overflow = ''
    if (originatingElement && typeof originatingElement.focus === 'function') {
      originatingElement.focus()
    }
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition name="error-modal-fade">
      <div
        v-if="isVisible"
        class="error-modal-overlay"
        role="dialog"
        aria-modal="true"
        aria-labelledby="error-modal-title"
        aria-describedby="error-modal-body"
      >
        <!-- Backdrop -->
        <div class="error-modal-backdrop" @click="close"></div>

        <!-- Container -->
        <div class="error-modal-container">
          <div ref="modalCardRef" class="error-modal-card">
            <!-- Close Button -->
            <button
              type="button"
              class="error-modal-close-btn"
              :aria-label="t('errorModal.closeButtonLabel')"
              :title="t('errorModal.closeButtonTitle')"
              @click="close"
            >
              <span class="material-symbols-outlined">close</span>
            </button>

            <!-- Error Icon Badge with Subtle Pulse -->
            <div class="error-icon-badge">
              <span class="material-symbols-outlined error-icon">error</span>
            </div>

            <!-- Error Tag / Code -->
            <div class="error-tag">
              <span v-if="displayErrorCode"
                >{{ t('errorModal.errorCodePrefix') }}: {{ displayErrorCode }}</span
              >
              <span v-else>{{ t('errorModal.systemNotice') }}</span>
            </div>

            <!-- Title -->
            <h2 id="error-modal-title" class="error-modal-title">
              {{ displayTitle }}
            </h2>

            <!-- Message Body -->
            <div id="error-modal-body" class="error-modal-body">
              <p v-if="displayMessage" class="error-main-message">
                {{ displayMessage }}
              </p>

              <!-- Detailed Error List (e.g. Validation errors) -->
              <div v-if="formattedDetails.length > 0" class="error-details-box">
                <div class="error-details-header">
                  <span class="material-symbols-outlined">info</span>
                  <span>{{ t('errorModal.detailsTitle') }}</span>
                </div>
                <ul class="error-details-list">
                  <li v-for="(detail, idx) in formattedDetails" :key="idx">
                    {{ detail }}
                  </li>
                </ul>
              </div>
            </div>

            <!-- Actions Row -->
            <div class="error-modal-actions">
              <button
                v-if="showRetry || $slots.retry"
                type="button"
                class="btn-retry-action"
                @click="handleRetry"
              >
                <span class="material-symbols-outlined">refresh</span>
                <span>{{ displayRetryText }}</span>
              </button>

              <button type="button" class="btn-confirm-close" @click="close">
                <span>{{ displayConfirmText }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.error-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.error-modal-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(15, 20, 25, 0.6);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}

.error-modal-container {
  position: relative;
  z-index: 10;
  width: 100%;
  max-width: 460px;
}

.error-modal-card {
  position: relative;
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, rgba(225, 227, 228, 0.8));
  border-radius: 24px;
  padding: 36px 28px 28px;
  box-shadow:
    0 24px 60px rgba(0, 0, 0, 0.35),
    0 8px 24px rgba(198, 40, 40, 0.12);
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  color: var(--ink, #191c1d);
}

.error-modal-close-btn {
  position: absolute;
  top: 16px;
  right: 16px;
  border: 0;
  background: var(--soft, #f4f5f6);
  color: var(--muted, #5d5f5f);
  width: 32px;
  height: 32px;
  border-radius: 50%;
  cursor: pointer;
  display: grid;
  place-items: center;
  transition: all 0.2s ease;
}

.error-modal-close-btn:hover {
  background: rgba(198, 40, 40, 0.15);
  color: #c62828;
}

.error-modal-close-btn .material-symbols-outlined {
  font-size: 18px;
}

.error-icon-badge {
  width: 64px;
  height: 64px;
  border-radius: 20px;
  background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
  display: grid;
  place-items: center;
  color: #c62828;
  box-shadow: 0 8px 24px rgba(198, 40, 40, 0.25);
  margin-bottom: 16px;
  animation: pulse-soft 2s infinite ease-in-out;
}

.error-icon {
  font-size: 36px;
}

@keyframes pulse-soft {
  0%,
  100% {
    transform: scale(1);
    box-shadow: 0 8px 24px rgba(198, 40, 40, 0.25);
  }
  50% {
    transform: scale(1.05);
    box-shadow: 0 12px 30px rgba(198, 40, 40, 0.4);
  }
}

.error-tag {
  display: inline-block;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.1em;
  color: #c62828;
  background: rgba(198, 40, 40, 0.08);
  padding: 4px 12px;
  border-radius: 999px;
  margin-bottom: 10px;
  text-transform: uppercase;
}

.error-modal-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  letter-spacing: -0.02em;
  margin: 0 0 12px;
  line-height: 1.3;
}

.error-modal-body {
  width: 100%;
  margin-bottom: 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.error-main-message {
  font-size: 14px;
  line-height: 1.6;
  color: var(--muted, #5d5f5f);
  margin: 0;
}

.error-details-box {
  background: var(--surface-subtle, rgba(0, 0, 0, 0.03));
  border: 1px solid rgba(198, 40, 40, 0.2);
  border-radius: 12px;
  padding: 12px 14px;
  text-align: left;
  max-height: 160px;
  overflow-y: auto;
}

.error-details-header {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  color: #c62828;
  margin-bottom: 6px;
}

.error-details-header .material-symbols-outlined {
  font-size: 16px;
}

.error-details-list {
  margin: 0;
  padding-left: 18px;
  font-size: 13px;
  color: var(--ink, #191c1d);
  line-height: 1.5;
}

.error-details-list li {
  margin-bottom: 4px;
}

.error-details-list li:last-child {
  margin-bottom: 0;
}

.error-modal-actions {
  width: 100%;
  display: flex;
  gap: 10px;
}

.btn-confirm-close {
  flex: 1;
  border: 0;
  border-radius: 12px;
  padding: 13px 20px;
  background: #c62828;
  color: #ffffff;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 14px rgba(198, 40, 40, 0.3);
}

.btn-confirm-close:hover {
  background: #b71c1c;
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(198, 40, 40, 0.4);
}

.btn-retry-action {
  flex: 1;
  border: 1px solid var(--border, #e0e0e0);
  border-radius: 12px;
  padding: 13px 20px;
  background: transparent;
  color: var(--ink, #191c1d);
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: all 0.2s ease;
}

.btn-retry-action:hover {
  background: var(--soft, #f4f5f6);
}

.btn-retry-action .material-symbols-outlined {
  font-size: 18px;
}

/* Modal Transition */
.error-modal-fade-enter-active,
.error-modal-fade-leave-active {
  transition: opacity 0.22s ease;
}

.error-modal-fade-enter-active .error-modal-card,
.error-modal-fade-leave-active .error-modal-card {
  transition:
    transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1),
    opacity 0.22s ease;
}

.error-modal-fade-enter-from,
.error-modal-fade-leave-to {
  opacity: 0;
}

.error-modal-fade-enter-from .error-modal-card,
.error-modal-fade-leave-to .error-modal-card {
  opacity: 0;
  transform: scale(0.92) translateY(12px);
}
</style>

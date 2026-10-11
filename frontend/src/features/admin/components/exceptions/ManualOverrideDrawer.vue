<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency, resolveLocalizedText } from '@/i18n'
import { SelectDropdown, AuraButton } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { FinancialException, ManualOverridePayload } from '../../types/exceptions'

const props = defineProps<{
  show: boolean
  item: FinancialException | null
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: ManualOverridePayload): void
}>()

const { t, te } = useI18n()

function localizeDescription(item?: FinancialException | null): string {
  if (!item) return ''
  if (item.type && te(`admin.exceptions.descriptions.${item.type}`)) {
    return t(`admin.exceptions.descriptions.${item.type}`)
  }
  const desc = resolveLocalizedText(item.description)
  if (desc.includes('Vượt hạn mức') || desc.includes('Deposit limit'))
    return t('admin.exceptions.descriptions.limit_exceeded')
  if (desc.includes('Lệch đối soát') || desc.includes('settlement mismatch'))
    return t('admin.exceptions.descriptions.settlement_mismatch')
  if (desc.includes('Hoàn tiền hủy') || desc.includes('cancellation refund'))
    return t('admin.exceptions.descriptions.late_refund')
  if (desc.includes('săn voucher') || desc.includes('voucher hunting'))
    return t('admin.exceptions.descriptions.voucher_hunting')
  if (desc.includes('Lệch cọc') || desc.includes('deposit hold mismatch'))
    return t('admin.exceptions.descriptions.deposit_hold_mismatch')
  if (desc.includes('Trùng lặp') || desc.includes('Duplicate QR'))
    return t('admin.exceptions.descriptions.duplicate_charge')
  if (desc.includes('ngập sân') || desc.includes('court flooding'))
    return t('admin.exceptions.descriptions.court_flooded_refund')
  return desc
}

const actionType = ref<string>('refund')
const reason = ref('')
const agreed = ref(false)
const proofFile = ref<File | null>(null)
const validationError = ref('')

// Signature Canvas State
const canvasRef = ref<HTMLCanvasElement | null>(null)
const isDrawing = ref(false)
const hasSigned = ref(false)

const actionOptions = computed<DropdownOption[]>(() => [
  { value: 'refund', label: t('admin.exceptions.optManualRefund'), icon: 'currency_exchange' },
  {
    value: 'rebalance',
    label: t('admin.exceptions.optWalletRebalance'),
    icon: 'account_balance_wallet',
  },
  { value: 'unflag', label: t('admin.exceptions.optUnflagWithdrawal'), icon: 'lock_open' },
  { value: 'cancel_lock', label: t('admin.exceptions.optCancelAndLock'), icon: 'block' },
])

watch(
  () => props.show,
  async (val) => {
    if (val) {
      reason.value = ''
      agreed.value = false
      proofFile.value = null
      validationError.value = ''
      actionType.value = 'refund'
      hasSigned.value = false
      await nextTick()
      initCanvas()
    }
  },
)

function initCanvas() {
  if (!canvasRef.value) return
  const canvas = canvasRef.value
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  // Set display size vs internal size for retina resolution
  const rect = canvas.getBoundingClientRect()
  canvas.width = rect.width * 2
  canvas.height = rect.height * 2
  ctx.scale(2, 2)

  ctx.strokeStyle = '#78555e'
  ctx.lineWidth = 2.5
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.clearRect(0, 0, rect.width, rect.height)
}

function getEventCoords(e: MouseEvent | TouchEvent, rect: DOMRect): { x: number; y: number } {
  let clientX = 0
  let clientY = 0
  if ('touches' in e && e.touches.length > 0 && e.touches[0]) {
    clientX = e.touches[0].clientX
    clientY = e.touches[0].clientY
  } else if ('clientX' in e) {
    clientX = e.clientX
    clientY = e.clientY
  }
  return {
    x: clientX - rect.left,
    y: clientY - rect.top,
  }
}

function startDrawing(e: MouseEvent | TouchEvent) {
  if (!canvasRef.value) return
  isDrawing.value = true
  hasSigned.value = true
  const ctx = canvasRef.value.getContext('2d')
  if (!ctx) return

  const rect = canvasRef.value.getBoundingClientRect()
  const { x, y } = getEventCoords(e, rect)

  ctx.beginPath()
  ctx.moveTo(x, y)
}

function draw(e: MouseEvent | TouchEvent) {
  if (!isDrawing.value || !canvasRef.value) return
  const ctx = canvasRef.value.getContext('2d')
  if (!ctx) return

  const rect = canvasRef.value.getBoundingClientRect()
  const { x, y } = getEventCoords(e, rect)

  ctx.lineTo(x, y)
  ctx.stroke()
}

function stopDrawing() {
  isDrawing.value = false
}

function clearSignature() {
  hasSigned.value = false
  initCanvas()
}

function autoSign() {
  if (!canvasRef.value) return
  initCanvas()
  const ctx = canvasRef.value.getContext('2d')
  if (!ctx) return

  const rect = canvasRef.value.getBoundingClientRect()
  ctx.strokeStyle = '#78555e'
  ctx.lineWidth = 2.5
  ctx.beginPath()
  // Draw an elegant simulated signature curve
  ctx.moveTo(30, rect.height / 2 + 10)
  ctx.bezierCurveTo(70, 10, 100, rect.height - 10, 150, rect.height / 2 - 10)
  ctx.bezierCurveTo(180, 10, 210, rect.height - 5, 270, rect.height / 2)
  ctx.stroke()

  ctx.beginPath()
  ctx.moveTo(110, rect.height / 2 + 15)
  ctx.lineTo(240, rect.height / 2 + 15)
  ctx.stroke()

  hasSigned.value = true
}

function handleFileUpload(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files && target.files[0]) {
    proofFile.value = target.files[0]
  }
}

function removeFile() {
  proofFile.value = null
}

function handleSubmit() {
  validationError.value = ''
  if (reason.value.trim().length < 20) {
    validationError.value = t('admin.exceptions.auditReasonPlaceholder')
    return
  }
  if (!hasSigned.value) {
    validationError.value = t('admin.exceptions.signatureSubtitle')
    return
  }
  if (!agreed.value) {
    validationError.value = t('admin.exceptions.legalCommitment')
    return
  }

  const selectedOpt = actionOptions.value.find((opt) => opt.value === actionType.value)
  const actionLabel = selectedOpt?.label || t('admin.exceptions.optManualRefund')

  emit('submit', {
    actionType: actionLabel,
    reason: reason.value.trim(),
    proofFileName: proofFile.value?.name,
    signatureData: hasSigned.value ? 'valid_e_sign_hash' : undefined,
  })
}
</script>

<template>
  <div>
    <!-- Backdrop -->
    <transition name="fade">
      <div v-if="show" class="drawer-backdrop" @click="emit('close')"></div>
    </transition>

    <!-- Slide-over Drawer -->
    <div class="override-drawer" :class="{ 'is-open': show }">
      <!-- Header -->
      <div class="drawer-header">
        <div>
          <h3 class="drawer-title">{{ t('admin.exceptions.overrideDrawerTitle') }}</h3>
          <p class="drawer-sub">{{ t('admin.exceptions.overrideDrawerSub') }}</p>
        </div>
        <button class="btn-close-drawer" type="button" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Drawer Body -->
      <div v-if="item" class="drawer-body">
        <!-- Error Banner -->
        <div v-if="validationError" class="validation-banner">
          <span class="material-symbols-outlined icon-18">error</span>
          <span>{{ validationError }}</span>
        </div>

        <!-- Target Summary Box -->
        <div class="target-summary-box">
          <div class="summary-row">
            <span class="summary-label">{{ t('admin.exceptions.txCodeLabel') }}</span>
            <span class="font-mono font-bold text-ink">{{ item.reference }}</span>
          </div>
          <div class="summary-row">
            <span class="summary-label">{{ t('admin.exceptions.entityLabel') }}</span>
            <span class="font-medium text-ink">
              {{
                resolveLocalizedText(
                  item.metadata?.user?.name ||
                    item.resolver?.name ||
                    t('admin.schedule.systemUser'),
                )
              }}
              <span class="text-muted font-mono"
                >({{
                  item.metadata?.user?.id ||
                  (item.resolver?.id ? `UID-${item.resolver.id}` : 'SYS')
                }})</span
              >
            </span>
          </div>
          <div class="summary-row">
            <span class="summary-label">{{ t('admin.exceptions.requestedAmountLabel') }}</span>
            <span class="font-mono font-bold text-critical amount-large">{{
              formatCurrency(item.amount)
            }}</span>
          </div>
          <div class="summary-row">
            <span class="summary-label">{{ t('admin.exceptions.alertReasonLabel') }}</span>
            <span class="reason-pill">{{ localizeDescription(item) }}</span>
          </div>
        </div>

        <!-- Form Fields -->
        <!-- 1. Action Type -->
        <div class="form-group">
          <label class="form-label">
            <span>{{ t('admin.exceptions.actionTypeLabel') }}</span>
            <span class="text-critical">*</span>
          </label>
          <SelectDropdown v-model="actionType" :options="actionOptions" variant="bordered" />
        </div>

        <!-- 2. Audit Reason Textarea -->
        <div class="form-group">
          <label class="form-label">
            <span>{{ t('admin.exceptions.auditReasonLabel') }}</span>
            <span class="text-critical">*</span>
          </label>
          <textarea
            v-model="reason"
            :placeholder="t('admin.exceptions.auditReasonPlaceholder')"
            class="form-textarea"
            rows="3"
          ></textarea>
          <div class="textarea-footer">
            <span class="char-count font-mono" :class="{ 'text-success': reason.length >= 20 }">
              {{ t('admin.exceptions.minCharsCount', { count: reason.length }) }}
            </span>
          </div>
        </div>

        <!-- 3. Document Proof Upload -->
        <div class="form-group">
          <label class="form-label">{{ t('admin.exceptions.uploadProofTitle') }}</label>
          <div v-if="!proofFile" class="file-dropzone">
            <input
              id="file-proof"
              type="file"
              class="file-input"
              accept=".pdf,.png,.jpg,.jpeg"
              @change="handleFileUpload"
            />
            <label for="file-proof" class="dropzone-label">
              <span class="material-symbols-outlined icon-24 text-muted">cloud_upload</span>
              <span class="dropzone-text">{{ t('admin.exceptions.uploadProofPlaceholder') }}</span>
              <span class="dropzone-hint">{{ t('admin.exceptions.uploadProofHint') }}</span>
            </label>
          </div>
          <div v-else class="file-preview-card">
            <div class="file-info">
              <span class="material-symbols-outlined text-primary">description</span>
              <div class="file-details">
                <span class="file-name font-mono">{{ proofFile.name }}</span>
                <span class="file-size">{{ (proofFile.size / 1024).toFixed(1) }} KB</span>
              </div>
            </div>
            <button
              type="button"
              class="btn-remove-file"
              :title="t('admin.exceptions.removeFile')"
              @click="removeFile"
            >
              <span class="material-symbols-outlined icon-18">delete</span>
            </button>
          </div>
        </div>

        <!-- 4. E-Signature Pad -->
        <div class="form-group">
          <div class="sign-header-row">
            <label class="form-label">
              <span class="material-symbols-outlined icon-16 text-primary">draw</span>
              <span>{{ t('admin.exceptions.signatureTitle') }}</span>
              <span class="text-critical">*</span>
            </label>
            <div class="sign-actions">
              <button v-if="hasSigned" type="button" class="btn-sign-tool" @click="clearSignature">
                {{ t('admin.exceptions.clearSign') }}
              </button>
              <button type="button" class="btn-sign-tool btn-sign-auto" @click="autoSign">
                {{ t('admin.exceptions.autoSign') }}
              </button>
            </div>
          </div>

          <div class="signature-canvas-container">
            <canvas
              ref="canvasRef"
              class="signature-canvas"
              @mousedown="startDrawing"
              @mousemove="draw"
              @mouseup="stopDrawing"
              @mouseleave="stopDrawing"
              @touchstart.passive="startDrawing"
              @touchmove.passive="draw"
              @touchend.passive="stopDrawing"
            ></canvas>
            <div v-if="!hasSigned" class="signature-placeholder pointer-events-none">
              <span class="material-symbols-outlined icon-18 text-muted">gesture</span>
              <span>{{ t('admin.exceptions.drawSignPlaceholder') }}</span>
            </div>
          </div>
        </div>

        <!-- 5. Legal Commitment -->
        <div class="legal-box">
          <input id="agreement" v-model="agreed" type="checkbox" class="legal-checkbox" />
          <label for="agreement" class="legal-label">
            {{ t('admin.exceptions.legalCommitment') }}
          </label>
        </div>
      </div>

      <!-- Footer Actions -->
      <div class="drawer-footer">
        <AuraButton variant="outline" @click="emit('close')">
          <span>{{ t('common.cancel') }}</span>
        </AuraButton>
        <AuraButton variant="primary" :disabled="loading" @click="handleSubmit">
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': loading }">
            {{ loading ? 'sync' : 'verified' }}
          </span>
          <span>{{ t('admin.exceptions.signAndRecordBtn') }}</span>
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.drawer-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(3px);
  z-index: 100;
}

.override-drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 520px;
  max-width: 95vw;
  background: var(--surface, #ffffff);
  z-index: 101;
  box-shadow: -6px 0 32px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  transform: translateX(100%);
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  border-left: 1px solid var(--outline, #e1e3e4);
}

.override-drawer.is-open {
  transform: translateX(0);
}

.drawer-header {
  padding: 20px 24px;
  border-bottom: 1px solid var(--outline, #e1e3e4);
  background: var(--surface-container-low, #f8f9fa);
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.drawer-title {
  font-size: 18px;
  font-weight: 800;
  margin: 0;
  color: var(--ink, #191c1d);
}

.drawer-sub {
  font-size: 12px;
  color: var(--muted, #817476);
  margin: 4px 0 0;
}

.btn-close-drawer {
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--muted, #817476);
  padding: 6px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}

.btn-close-drawer:hover {
  background: var(--surface-container-high, #e7e8e9);
  color: var(--ink, #191c1d);
}

.drawer-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  overflow-y: auto;
  flex: 1;
  scrollbar-width: thin;
  scrollbar-color: var(--outline, rgba(140, 140, 140, 0.25)) transparent;
}

.drawer-body::-webkit-scrollbar {
  width: 5px;
}

.drawer-body::-webkit-scrollbar-track {
  background: transparent;
}

.drawer-body::-webkit-scrollbar-thumb {
  background: var(--outline, rgba(140, 140, 140, 0.25));
  border-radius: 999px;
}

.drawer-body::-webkit-scrollbar-thumb:hover {
  background: var(--muted, rgba(140, 140, 140, 0.45));
}

.validation-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fee2e2;
  border: 1px solid #fca5a5;
  color: #b91c1c;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
}

.target-summary-box {
  background: var(--surface-container-low, #f8f9fa);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  font-size: 13px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.summary-label {
  color: var(--muted, #817476);
  font-size: 12px;
  font-weight: 500;
}

.amount-large {
  font-size: 16px;
}

.reason-pill {
  display: inline-block;
  background: var(--surface-container-high, #e7e8e9);
  border: 1px solid var(--outline, #e1e3e4);
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 12px;
  color: var(--on-surface-variant, #4f4446);
  font-weight: 600;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  display: flex;
  align-items: center;
  gap: 4px;
}

.form-textarea {
  width: 100%;
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 13px;
  outline: none;
  font-family: inherit;
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  resize: vertical;
  transition: border-color 0.2s;
}

.form-textarea:focus {
  border-color: var(--primary, #78555e);
}

.textarea-footer {
  display: flex;
  justify-content: flex-end;
}

.char-count {
  font-size: 11px;
  color: var(--muted, #817476);
}

/* File Dropzone */
.file-dropzone {
  position: relative;
  border: 1px dashed var(--outline, #e1e3e4);
  border-radius: 10px;
  background: var(--surface-container-low, #f8f9fa);
  padding: 16px;
  text-align: center;
  transition:
    border-color 0.2s,
    background 0.2s;
}

.file-dropzone:hover {
  border-color: var(--primary, #78555e);
  background: var(--surface-container, #edeeef);
}

.file-input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

.dropzone-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  cursor: pointer;
}

.dropzone-text {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #191c1d);
}

.dropzone-hint {
  font-size: 11px;
  color: var(--muted, #817476);
}

.file-preview-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--surface-container-low, #f8f9fa);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 8px;
  padding: 10px 12px;
}

.file-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.file-details {
  display: flex;
  flex-direction: column;
}

.file-name {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink, #191c1d);
}

.file-size {
  font-size: 11px;
  color: var(--muted, #817476);
}

.btn-remove-file {
  background: transparent;
  border: none;
  color: #ba1a1a;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-remove-file:hover {
  background: #fee2e2;
}

/* Signature Pad */
.sign-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.sign-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-sign-tool {
  background: transparent;
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 6px;
  padding: 2px 8px;
  font-size: 11px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-sign-tool:hover {
  background: var(--surface-container-high, #e7e8e9);
  color: var(--ink, #191c1d);
}

.btn-sign-auto {
  color: var(--primary, #78555e);
  border-color: var(--primary, #78555e);
  background: var(--primary-container, #ffd1dc);
}

.signature-canvas-container {
  position: relative;
  border: 1px dashed var(--outline, #e1e3e4);
  border-radius: 10px;
  background: var(--surface-container-low, #f8f9fa);
  height: 110px;
  overflow: hidden;
}

.signature-canvas {
  width: 100%;
  height: 100%;
  cursor: crosshair;
  display: block;
}

.signature-placeholder {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 13px;
  color: var(--muted, #817476);
}

.legal-box {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background: #fff1f2;
  border: 1px solid #ffe4e6;
  border-radius: 10px;
  padding: 12px;
}

.legal-checkbox {
  margin-top: 3px;
  accent-color: var(--primary, #78555e);
  width: 16px;
  height: 16px;
}

.legal-label {
  font-size: 12px;
  color: #9f1239;
  line-height: 1.45;
  cursor: pointer;
  font-weight: 500;
}

.drawer-footer {
  padding: 18px 24px;
  border-top: 1px solid var(--outline, #e1e3e4);
  background: var(--surface-container-low, #f8f9fa);
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}

.text-critical {
  color: #ba1a1a;
}
.text-success {
  color: #10b981;
}
.text-muted {
  color: var(--muted, #817476);
}
.text-ink {
  color: var(--ink, #191c1d);
}
.pointer-events-none {
  pointer-events: none;
}

.icon-16 {
  font-size: 16px;
}
.icon-18 {
  font-size: 18px;
}
.icon-24 {
  font-size: 24px;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}

/* Fade Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>

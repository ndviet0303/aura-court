<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  show: boolean
  order: any | null
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm'): void
  (e: 'cancel'): void
}>()

const { t } = useI18n()

function formatMoney(val: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}

const qrData = computed(() => {
  return props.order?.metadata?.receipt?.qr_data || props.order?.receipt?.qr_data || null
})

const orderCode = computed(() => {
  return (
    props.order?.payment_reference ||
    props.order?.metadata?.receipt?.order_code ||
    `POS-${props.order?.id || ''}`
  )
})

const amount = computed(() => {
  return props.order?.total_amount || 0
})

const qrUrl = computed(() => {
  if (qrData.value?.qr_url) {
    return qrData.value.qr_url
  }
  const bank = qrData.value?.bank || 'MBBank'
  const account = qrData.value?.account || '0901234567'
  const name = encodeURIComponent(qrData.value?.account_name || 'AURA COURT SYSTEM')
  const memo = encodeURIComponent(orderCode.value)
  return `https://img.vietqr.io/image/${bank}-${account}-compact2.png?amount=${amount.value}&addInfo=${memo}&accountName=${name}`
})
</script>

<template>
  <div v-if="show && order" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm qr-modal-card">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined modal-icon text-blue">qr_code_2</span>
          <h3 class="modal-title">{{ t('admin.pos.qrModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body qr-body">
        <div class="qr-status-tag">
          <span class="status-dot pulsing"></span>
          <span>{{ t('admin.pos.qrWaitingTransfer') }}</span>
        </div>

        <div class="qr-image-wrapper">
          <img :src="qrUrl" alt="VietQR" class="qr-image" />
        </div>

        <div class="qr-info-box">
          <div class="info-row">
            <span class="info-label">{{ t('admin.pos.qrBankLabel') }}</span>
            <strong class="info-val">{{ qrData?.bank || 'MBBank' }}</strong>
          </div>
          <div class="info-row">
            <span class="info-label">{{ t('admin.pos.qrAccountLabel') }}</span>
            <strong class="info-val font-mono">{{ qrData?.account || '0901234567' }}</strong>
          </div>
          <div class="info-row">
            <span class="info-label">{{ t('admin.pos.qrAccountNameLabel') }}</span>
            <strong class="info-val">{{ qrData?.account_name || 'AURA COURT SYSTEM' }}</strong>
          </div>
          <div class="info-row highlight">
            <span class="info-label">{{ t('admin.pos.qrAmountLabel') }}</span>
            <strong class="info-val text-green font-bold">{{ formatMoney(amount) }}</strong>
          </div>
          <div class="info-row highlight-memo">
            <span class="info-label">{{ t('admin.pos.qrMemoLabel') }}</span>
            <strong class="info-val text-blue font-mono select-all">{{ orderCode }}</strong>
          </div>
        </div>

        <p class="qr-hint">
          {{ t('admin.pos.qrInstructions') }}
        </p>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" :disabled="loading" @click="emit('cancel')">
          {{ t('admin.pos.qrCancelBtn') }}
        </button>
        <button type="button" class="btn-confirm" :disabled="loading" @click="emit('confirm')">
          <span v-if="loading" class="material-symbols-outlined spin">progress_activity</span>
          <span v-else class="material-symbols-outlined">check_circle</span>
          <span>{{ t('admin.pos.qrConfirmReceivedBtn') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10000;
}

.modal-card {
  background: #1e293b;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  width: 90%;
  max-width: 440px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-icon {
  font-size: 22px;
}

.text-blue {
  color: #38bdf8;
}

.text-green {
  color: #4ade80;
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #f8fafc;
}

.modal-close-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
}

.qr-body {
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.qr-status-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(56, 189, 248, 0.12);
  border: 1px solid rgba(56, 189, 248, 0.3);
  padding: 4px 12px;
  border-radius: 9999px;
  color: #38bdf8;
  font-size: 13px;
  font-weight: 500;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #38bdf8;
}

.pulsing {
  box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.7);
  animation: pulse 1.8s infinite;
}

@keyframes pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.7);
  }
  70% {
    box-shadow: 0 0 0 8px rgba(56, 189, 248, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(56, 189, 248, 0);
  }
}

.qr-image-wrapper {
  background: white;
  padding: 10px;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.qr-image {
  width: 200px;
  height: 200px;
  object-fit: contain;
  display: block;
}

.qr-info-box {
  width: 100%;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 8px;
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.info-label {
  color: #94a3b8;
}

.info-val {
  color: #f1f5f9;
}

.highlight {
  border-top: 1px dashed rgba(255, 255, 255, 0.1);
  padding-top: 6px;
  margin-top: 2px;
}

.highlight-memo {
  background: rgba(56, 189, 248, 0.08);
  padding: 4px 6px;
  border-radius: 4px;
}

.font-mono {
  font-family: monospace;
}

.select-all {
  user-select: all;
}

.qr-hint {
  font-size: 12px;
  color: #94a3b8;
  text-align: center;
  margin: 0;
  line-height: 1.4;
}

.modal-footer {
  display: flex;
  gap: 10px;
  padding: 12px 18px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(15, 23, 42, 0.4);
}

.btn-cancel {
  flex: 1;
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #ef4444;
  padding: 8px 14px;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
}

.btn-cancel:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.25);
}

.btn-confirm {
  flex: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background: #0284c7;
  border: none;
  color: white;
  padding: 8px 14px;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
}

.btn-confirm:hover:not(:disabled) {
  background: #0369a1;
}

.btn-confirm:disabled,
.btn-cancel:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.spin {
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
</style>

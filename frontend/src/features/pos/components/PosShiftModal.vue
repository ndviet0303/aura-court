<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PosShift } from '../types/pos'

const props = defineProps<{
  show: boolean
  activeShift: PosShift | null
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'openShift', openingCash: number): void
  (e: 'closeShift', shiftId: number, declaredCash: number, note: string): void
}>()

const { t } = useI18n()

const openingCash = ref(1000000)
const declaredCash = ref(0)
const shiftNote = ref('')

function formatMoney(val: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}

function handleOpen() {
  emit('openShift', openingCash.value)
}

function handleClose() {
  if (props.activeShift) {
    emit('closeShift', props.activeShift.id, declaredCash.value, shiftNote.value)
  }
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined modal-icon">badge</span>
          <h3 class="modal-title">{{ t('admin.pos.shiftModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <!-- If Shift is Currently Open -->
        <div v-if="activeShift" class="shift-active-box">
          <div class="shift-status-badge">
            <span class="status-dot"></span>
            <span>{{ t('admin.pos.shiftActiveBadge', { id: activeShift.id }) }}</span>
          </div>

          <div class="shift-stats-grid">
            <div class="stat-card">
              <span class="stat-label">{{ t('admin.pos.shiftOpenedAt') }}</span>
              <strong class="stat-val">{{ activeShift.opened_at }}</strong>
            </div>
            <div class="stat-card">
              <span class="stat-label">{{ t('admin.pos.shiftOpeningCash') }}</span>
              <strong class="stat-val">{{ formatMoney(activeShift.initial_cash || activeShift.opening_cash || 0) }}</strong>
            </div>
            <div class="stat-card">
              <span class="stat-label">{{ t('admin.pos.shiftCashSales') }}</span>
              <strong class="stat-val text-green">{{ formatMoney(activeShift.cash_sales || 0) }}</strong>
            </div>
            <div class="stat-card">
              <span class="stat-label">{{ t('admin.pos.shiftQrSales') }}</span>
              <strong class="stat-val text-blue">{{ formatMoney((activeShift.qr_sales || 0) + (activeShift.wallet_sales || 0)) }}</strong>
            </div>
          </div>

          <div class="close-shift-form">
            <h4 class="form-section-title">
              <span class="material-symbols-outlined icon-18">lock</span>
              <span>{{ t('admin.pos.shiftCloseSection') }}</span>
            </h4>
            
            <div class="form-group">
              <label class="form-label">
                {{ t('admin.pos.shiftDeclaredCashLabel') }}
              </label>
              <input
                v-model.number="declaredCash"
                type="number"
                step="10000"
                class="form-input"
                :placeholder="t('admin.pos.shiftDeclaredCashPlaceholder')"
              />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.pos.shiftNoteLabel') }}</label>
              <textarea
                v-model="shiftNote"
                rows="2"
                class="form-textarea"
                :placeholder="t('admin.pos.shiftNotePlaceholder')"
              ></textarea>
            </div>
          </div>
        </div>

        <!-- If No Shift is Open -->
        <div v-else class="shift-open-form">
          <div class="empty-shift-banner">
            <span class="material-symbols-outlined shift-banner-icon">lock_open</span>
            <h4>{{ t('admin.pos.noShiftOpenTitle') }}</h4>
            <p>{{ t('admin.pos.noShiftOpenSub') }}</p>
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.pos.openingCashLabel') }}</label>
            <input
              v-model.number="openingCash"
              type="number"
              step="50000"
              class="form-input text-bold"
              :placeholder="t('admin.pos.openingCashPlaceholder')"
            />
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">
          {{ t('admin.pos.cancel') }}
        </button>

        <button
          v-if="activeShift"
          type="button"
          class="btn-danger"
          :disabled="loading"
          @click="handleClose"
        >
          <span class="material-symbols-outlined icon-18">lock</span>
          <span>{{ loading ? t('admin.pos.processing') : t('admin.pos.closeShiftBtn') }}</span>
        </button>

        <button
          v-else
          type="button"
          class="btn-primary"
          :disabled="loading || openingCash < 0"
          @click="handleOpen"
        >
          <span class="material-symbols-outlined icon-18">key</span>
          <span>{{ loading ? t('admin.pos.processing') : t('admin.pos.openShiftBtn') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 500px;
  background: var(--surface, #ffffff);
  border-radius: 16px;
  border: 1px solid var(--outline, #e2e8f0);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  overflow: hidden;
  color: var(--ink, #0f172a);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  border-bottom: 1px solid var(--outline, #e2e8f0);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-icon {
  color: var(--primary, #10b981);
  font-size: 22px;
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #0f172a);
}

.modal-close-btn {
  background: transparent;
  border: none;
  color: var(--muted, #64748b);
  cursor: pointer;
  display: flex;
  align-items: center;
}

.modal-body {
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.shift-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 12px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
}

.shift-stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 16px;
}

.stat-card {
  padding: 10px 12px;
  background: var(--surface-hover, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline, #e2e8f0);
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-label {
  font-size: 11px;
  color: var(--muted, #64748b);
}

.stat-val {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #0f172a);
}

.text-green {
  color: #10b981 !important;
}

.text-blue {
  color: #3b82f6 !important;
}

.close-shift-form {
  padding-top: 14px;
  border-top: 1px solid var(--outline, #e2e8f0);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.form-section-title {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #0f172a);
}

.empty-shift-banner {
  text-align: center;
  padding: 20px 10px;
  color: var(--muted, #64748b);
}

.shift-banner-icon {
  font-size: 40px;
  color: var(--primary, #10b981);
  margin-bottom: 8px;
}

.empty-shift-banner h4 {
  margin: 0 0 4px;
  font-size: 15px;
  color: var(--ink, #0f172a);
}

.empty-shift-banner p {
  margin: 0;
  font-size: 12px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink, #0f172a);
}

.form-input,
.form-textarea {
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline, #cbd5e1);
  background: var(--surface, #ffffff);
  color: var(--ink, #0f172a);
  font-size: 13px;
  outline: none;
}

.form-input:focus,
.form-textarea:focus {
  border-color: var(--primary, #10b981);
}

.text-bold {
  font-weight: 700;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 18px;
  border-top: 1px solid var(--outline, #e2e8f0);
}

.btn-cancel {
  padding: 8px 14px;
  background: var(--surface-hover, rgba(0, 0, 0, 0.05));
  border: 1px solid var(--outline, #cbd5e1);
  border-radius: 8px;
  color: var(--ink, #0f172a);
  font-weight: 600;
  cursor: pointer;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: var(--primary, #10b981);
  border: none;
  border-radius: 8px;
  color: #ffffff;
  font-weight: 700;
  cursor: pointer;
}

.btn-danger {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: #ef4444;
  border: none;
  border-radius: 8px;
  color: #ffffff;
  font-weight: 700;
  cursor: pointer;
}

.icon-18 {
  font-size: 18px;
}
</style>

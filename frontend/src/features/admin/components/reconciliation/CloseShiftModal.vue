<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { AuraButton, CurrencyInput } from '@/shared/components'
import type { CashShiftItem } from '../../composables/useAdminShiftReconciliation'

const props = defineProps<{
  show: boolean
  shift: CashShiftItem
  saving: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: { declaredCash: number; note?: string }): void
  (e: 'openDenominations'): void
}>()

const { t } = useI18n()

const declaredCash = ref<number>(props.shift.declared_cash || props.shift.expected_cash || 0)
const note = ref('')

watch(
  () => props.shift.declared_cash,
  (val) => {
    if (val != null) declaredCash.value = val
  }
)

const liveVariance = computed(() => {
  return (declaredCash.value || 0) - props.shift.expected_cash
})

function handleSubmit() {
  if (declaredCash.value < 0) return
  emit('submit', {
    declaredCash: declaredCash.value,
    note: note.value.trim() || undefined,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">lock_clock</span>
          <h3 class="modal-title">{{ t('admin.shifts.closeShiftModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <p class="modal-intro">{{ t('admin.shifts.closeShiftDesc') }}</p>

        <!-- Shift Live Snapshot Grid -->
        <div class="snapshot-grid">
          <div class="snap-box">
            <span class="snap-lbl">{{ t('admin.shifts.openingCashLabel') }}</span>
            <strong class="font-mono text-ink">{{ formatCurrency(shift.opening_cash) }}</strong>
          </div>
          <div class="snap-box">
            <span class="snap-lbl">{{ t('admin.shifts.cashSalesLabel') }}</span>
            <strong class="font-mono text-green">+{{ formatCurrency(shift.cash_sales) }}</strong>
          </div>
          <div class="snap-box">
            <span class="snap-lbl">{{ t('admin.shifts.cashDropsTally') }}</span>
            <strong class="font-mono text-red">-{{ formatCurrency(shift.cash_drops_total) }}</strong>
          </div>
          <div class="snap-box highlight-snap">
            <span class="snap-lbl">{{ t('admin.shifts.expectedInDrawer') }}</span>
            <strong class="font-mono text-pink">{{ formatCurrency(shift.expected_cash) }}</strong>
          </div>
        </div>

        <!-- Counted Cash Input & Shortcut -->
        <div class="form-group">
          <div class="form-label-row">
            <label class="form-label">{{ t('admin.shifts.countedCashLabel') }}</label>
            <button type="button" class="btn-calc-link" @click="emit('openDenominations')">
              <span class="material-symbols-outlined icon-14">calculate</span>
              <span>{{ t('admin.shifts.openCalculator') }}</span>
            </button>
          </div>
          <CurrencyInput
            v-model="declaredCash"
            size="md"
            placeholder="0"
          />
        </div>

        <!-- Realtime Variance Indicator -->
        <div class="variance-alert-card" :class="{ 'has-diff': liveVariance !== 0 }">
          <div class="var-left">
            <span class="material-symbols-outlined var-icon" :class="liveVariance === 0 ? 'text-green' : liveVariance < 0 ? 'text-red' : 'text-yellow'">
              {{ liveVariance === 0 ? 'check_circle' : 'warning' }}
            </span>
            <div class="var-texts">
              <span class="var-title">{{ t('admin.shifts.shiftVarianceAmount') }}</span>
              <strong
                class="font-mono var-val"
                :class="liveVariance === 0 ? 'text-green' : liveVariance < 0 ? 'text-red' : 'text-yellow'"
              >
                {{ liveVariance > 0 ? '+' : '' }}{{ formatCurrency(liveVariance) }}
              </strong>
            </div>
          </div>
          <span
            class="status-tag"
            :class="liveVariance === 0 ? 'tag-green' : liveVariance < 0 ? 'tag-red' : 'tag-yellow'"
          >
            {{ liveVariance === 0 ? t('admin.shifts.balancedCash') : liveVariance < 0 ? t('admin.shifts.shortCash') : t('admin.shifts.overCash') }}
          </span>
        </div>

        <!-- Note Textarea -->
        <div class="form-group">
          <label class="form-label">{{ t('admin.shifts.closingNoteLabel') }}</label>
          <textarea
            v-model="note"
            rows="3"
            class="form-textarea"
            :placeholder="t('admin.shifts.closingNotePlaceholder')"
          ></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="secondary" :disabled="saving" @click="emit('close')">
          {{ t('common.close') }}
        </AuraButton>
        <AuraButton
          variant="primary"
          :disabled="saving || declaredCash < 0"
          @click="handleSubmit"
        >
          <span
            class="material-symbols-outlined icon-16"
            :class="{ 'spin-icon': saving }"
          >
            lock
          </span>
          <span>{{ t('admin.shifts.confirmCloseShift') }}</span>
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
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-card {
  width: 100%;
  max-width: 540px;
  background: var(--surface);
  border-radius: 18px;
  border: 1px solid var(--outline);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  max-height: 90vh;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.modal-close-btn {
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  font-size: 16px;
  padding: 4px;
}

.modal-body {
  padding: 18px 20px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.modal-intro {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
  line-height: 1.4;
}

.snapshot-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.snap-box {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 10px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  font-size: 12px;
}

.highlight-snap {
  background: rgba(219, 39, 119, 0.06);
  border-color: rgba(219, 39, 119, 0.2);
}

.snap-lbl {
  font-size: 10.5px;
  color: var(--muted);
  font-weight: 600;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.form-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}

.btn-calc-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: none;
  background: transparent;
  color: var(--pink-dark, #db2777);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}

.btn-calc-link:hover {
  text-decoration: underline;
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 16px;
  font-weight: 700;
  outline: none;
  box-sizing: border-box;
}

.count-input {
  border-color: var(--pink-dark, #db2777);
}

.form-textarea {
  width: 100%;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  outline: none;
  box-sizing: border-box;
  resize: vertical;
}

.variance-alert-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.var-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.var-icon {
  font-size: 24px;
}

.var-texts {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.var-title {
  font-size: 11px;
  color: var(--muted);
  font-weight: 600;
}

.var-val {
  font-size: 16px;
}

.status-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.tag-green {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.tag-yellow {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
}

.tag-red {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-green { color: #10b981; }
.text-yellow { color: #f59e0b; }
.text-red { color: #ef4444; }
.text-ink { color: var(--ink); }

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid var(--outline);
}

.icon-14 { font-size: 14px; }
.icon-16 { font-size: 16px; }

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

:global(html.dark) .modal-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .snap-box,
:global(html.dark) .variance-alert-card {
  background: rgba(255, 255, 255, 0.02);
}

@media (max-width: 520px) {
  .snapshot-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>

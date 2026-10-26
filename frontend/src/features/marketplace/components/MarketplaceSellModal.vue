<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { UserBookingSlot } from '../types/marketplace'
import { AuraButton } from '@/shared/components'

const props = defineProps<{
  show: boolean
  bookingSlot: UserBookingSlot | null
  askingPrice: number
  processing?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update:askingPrice', price: number): void
  (e: 'confirm'): void
}>()

const { t } = useI18n()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

const maxPrice = computed(() => props.bookingSlot?.maxResalePrice ?? 0)
const setDiscountPreset = (percent: number) =>
  emit('update:askingPrice', Math.floor((maxPrice.value * (100 - percent)) / 1e6) * 10000)

const isFree = computed(() => props.askingPrice === 0)
const priceValid = computed(
  () =>
    !!props.bookingSlot &&
    Number.isInteger(props.askingPrice) &&
    props.askingPrice >= 0 &&
    props.askingPrice <= maxPrice.value,
)
function submit() {
  if (priceValid.value) emit('confirm')
}
</script>

<template>
  <div v-if="show && bookingSlot" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="modal-title-wrap">
          <span class="material-symbols-outlined modal-icon">sell</span>
          <h3 class="modal-title">Đăng Pass Lại 1 Giờ Chơi</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <!-- Target Slot Summary -->
        <div class="slot-summary-card">
          <strong class="slot-court">{{ bookingSlot.courtName }}</strong>
          <div class="slot-meta-row">
            <span class="meta-item">
              <span class="material-symbols-outlined icon-14">location_on</span>
              <span>{{ bookingSlot.venueName }}</span>
            </span>
            <span class="meta-item">
              <span class="material-symbols-outlined icon-14">calendar_today</span>
              <span>{{ bookingSlot.dateStr }}</span>
            </span>
            <span class="meta-item highlight">
              <span class="material-symbols-outlined icon-14">schedule</span>
              <span class="font-mono">{{ bookingSlot.timeSlot }}</span>
            </span>
          </div>
          <div class="slot-price-row">
            <span class="orig-label">Giá bạn đã thanh toán:</span>
            <strong class="orig-val font-mono">{{ formatMoney(maxPrice) }}</strong>
          </div>
        </div>

        <!-- Asking Price Input -->
        <div class="form-group">
          <label class="form-label">
            <span>Đặt giá muốn chuyển nhượng (₫):</span>
            <span class="max-hint">(Tối đa: {{ formatMoney(maxPrice) }})</span>
          </label>
          <div class="price-input-wrap">
            <input
              :value="askingPrice"
              type="number"
              min="0"
              :max="maxPrice"
              step="10000"
              class="form-input font-mono"
              @input="emit('update:askingPrice', Number(($event.target as HTMLInputElement).value))"
            />
            <span class="input-unit font-mono">VND</span>
          </div>

          <!-- Quick Discount Presets -->
          <div class="preset-chips">
            <span class="preset-label">Gợi ý giảm giá nhanh:</span>
            <button type="button" class="chip-btn" @click="setDiscountPreset(10)">-10%</button>
            <button type="button" class="chip-btn popular" @click="setDiscountPreset(20)">
              -20% (Bán nhanh)
            </button>
            <button type="button" class="chip-btn" @click="setDiscountPreset(30)">-30%</button>
          </div>
        </div>

        <p v-if="isFree" class="notice-text">{{ t('resale.p2p.freeListingNote') }}</p>

        <div class="payout-calc-box" data-testid="resale-sell-credit">
          <div class="p-row">
            <span>{{ t('resale.p2p.sellerCredit') }}</span>
            <strong class="font-mono text-ink">
              {{ t('resale.p2p.creditAmount', { amount: formatMoney(askingPrice) }) }}
            </strong>
          </div>
          <div class="p-row">
            <span>{{ t('resale.p2p.feeRow') }}</span>
            <span class="text-ink">{{ t('resale.p2p.feeNone') }}</span>
          </div>
        </div>

        <div class="policy-notice">
          <span class="material-symbols-outlined notice-icon">info</span>
          <p class="notice-text">
            {{ t('resale.p2p.sellerCreditNotice', { venue: bookingSlot.venueName }) }}
          </p>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton variant="secondary" size="md" @click="emit('close')"> Huỷ </AuraButton>
        <AuraButton
          variant="gold"
          size="md"
          :loading="processing"
          :disabled="!priceValid"
          icon="publish"
          @click="submit"
        >
          Đăng Lên Chợ Ngay
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 12, 14, 0.7);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 520px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 24px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: modalIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalIn {
  from {
    opacity: 0;
    transform: scale(0.96) translateY(8px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--outline);
}

.modal-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-icon {
  font-size: 22px;
  color: var(--accent, #c9a15a);
}

.modal-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.modal-close-btn {
  background: transparent;
  border: 0;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  border-radius: 8px;
  transition: all 0.15s ease;
}

.modal-close-btn:hover {
  background: var(--surface-subtle, rgba(0, 0, 0, 0.05));
  color: var(--ink);
}

.modal-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.slot-summary-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px 16px;
  border-radius: 16px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.slot-court {
  font-size: 15.5px;
  color: var(--ink);
}

.slot-meta-row {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  font-size: 12px;
  color: var(--muted);
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.meta-item.highlight {
  color: var(--primary, #6b2d3f);
  font-weight: 700;
}

html.dark .meta-item.highlight {
  color: var(--accent, #c9a15a);
}

.icon-14 {
  font-size: 14px;
}

.slot-price-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 8px;
  border-top: 1px dashed var(--outline);
  font-size: 12px;
}

.orig-label {
  color: var(--muted);
}

.orig-val {
  color: var(--ink);
  font-size: 13px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
}

.max-hint {
  font-size: 11px;
  font-weight: normal;
  color: var(--muted);
}

.price-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.form-input {
  width: 100%;
  padding: 12px 60px 12px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 16px;
  font-weight: 700;
  transition: all 0.2s ease;
}

.form-input:focus {
  outline: none;
  border-color: var(--primary, #6b2d3f);
  box-shadow: 0 0 0 3px rgba(107, 45, 63, 0.1);
}

.input-unit {
  position: absolute;
  right: 16px;
  font-size: 13px;
  font-weight: 700;
  color: var(--muted);
}

.preset-chips {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.preset-label {
  font-size: 11px;
  color: var(--muted);
}

.chip-btn {
  padding: 4px 10px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.chip-btn:hover {
  border-color: var(--primary, #6b2d3f);
  color: var(--primary, #6b2d3f);
}

.chip-btn.popular {
  background: rgba(201, 161, 90, 0.1);
  border-color: var(--accent, #c9a15a);
  color: var(--accent, #c9a15a);
  font-weight: 700;
}

.payout-calc-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px;
  border-radius: 14px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  font-size: 13px;
}

.p-row {
  display: flex;
  justify-content: space-between;
  color: var(--muted);
}

.text-ink {
  color: var(--ink);
}

.policy-notice {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(201, 161, 90, 0.08);
  border: 1px solid rgba(201, 161, 90, 0.25);
}

.notice-icon {
  font-size: 18px;
  color: var(--accent, #c9a15a);
}

.notice-text {
  margin: 0;
  font-size: 11.5px;
  color: var(--muted);
  line-height: 1.4;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.01));
}
</style>

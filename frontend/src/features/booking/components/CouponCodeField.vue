<script setup lang="ts">
import { useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'

defineProps<{ loading?: boolean; error?: string; discount?: number }>()
const emit = defineEmits<{ apply: [] }>()
const code = defineModel<string>({ required: true })
const { t } = useI18n()
const inputId = useId()
</script>

<template>
  <div class="coupon-field">
    <label class="coupon-label" :for="inputId">{{ t('payment.applyCoupon') }}</label>
    <div class="coupon-input-group">
      <input
        :id="inputId"
        v-model="code"
        type="text"
        class="coupon-text-input"
        :placeholder="t('payment.couponPlaceholder')"
        autocomplete="off"
        @keydown.enter.prevent="emit('apply')"
      />
      <button
        type="button"
        class="btn-apply-coupon"
        :disabled="loading || !code.trim()"
        @click="emit('apply')"
      >
        {{ loading ? '…' : t('payment.applyCouponBtn') }}
      </button>
    </div>
    <p v-if="error" class="coupon-error" role="alert">{{ error }}</p>
    <p v-else-if="discount" class="coupon-discount">
      <span>{{ t('payment.couponDiscount') }}</span>
      <strong>-{{ formatCurrency(discount) }}</strong>
    </p>
  </div>
</template>

<style scoped>
.coupon-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.coupon-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}

.coupon-input-group {
  display: flex;
  gap: 8px;
}

.coupon-text-input {
  flex: 1;
  min-width: 0;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  text-transform: uppercase;
}

.btn-apply-coupon {
  padding: 8px 16px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.btn-apply-coupon:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.coupon-error {
  margin: 0;
  font-size: 12px;
  color: var(--error-text);
}

.coupon-discount {
  display: flex;
  justify-content: space-between;
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--success-text);
}
</style>

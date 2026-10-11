<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLoyaltyPreview } from '../composables/useLoyaltyPreview'
import { formatCurrency } from '@/i18n'

const { t } = useI18n()

const props = defineProps<{
  courtId?: number
  slots?: string[]
  quoteToken?: string
  couponCode?: string
  modelValue?: number
}>()

const emit = defineEmits<{
  'update:modelValue': [points: number]
  change: [
    payload: {
      loyalty_points: number
      loyalty_policy_version: number | null
      idempotency_key: string
      discount_vnd: number
      remaining_payable_vnd: number
    },
  ]
}>()

const {
  balance,
  policy,
  pointsSelected,
  discountVnd,
  remainingPayableVnd,
  maxUsablePoints,
  loading,
  error,
  noPolicy,
  idempotencyKey,
  hasPoints,
  hasPolicy,
  isAvailable,
  fetchPreview,
  clearPoints: resetPoints,
  setQuickPoints,
  refreshIdempotencyKey,
} = useLoyaltyPreview()

const inputPoints = ref<number | ''>(props.modelValue || '')

async function loadPreview(requestedPoints?: number) {
  if (!props.courtId && !props.quoteToken) return
  if (props.courtId && (!props.slots || props.slots.length === 0)) return

  const res = await fetchPreview({
    court_id: props.courtId,
    slots: props.slots,
    quote_token: props.quoteToken,
    coupon_code: props.couponCode,
    points: requestedPoints !== undefined && requestedPoints > 0 ? requestedPoints : undefined,
  })

  if (res) {
    emit('update:modelValue', res.points_selected)
    emit('change', {
      loyalty_points: res.points_selected,
      loyalty_policy_version: res.policy?.version ?? null,
      idempotency_key: idempotencyKey.value,
      discount_vnd: res.discount_vnd,
      remaining_payable_vnd: res.remaining_payable_vnd,
    })
  }
}

// Watch changes to target court/slots/quote/coupon to refresh preview & new idempotency key
watch(
  () => [props.courtId, JSON.stringify(props.slots), props.quoteToken, props.couponCode],
  () => {
    refreshIdempotencyKey()
    loadPreview(typeof inputPoints.value === 'number' ? inputPoints.value : 0)
  },
  { deep: true },
)

watch(
  () => props.modelValue,
  (val) => {
    if (val !== undefined && val !== inputPoints.value) {
      inputPoints.value = val > 0 ? val : ''
    }
  },
)

async function onInputChange() {
  const val = Number(inputPoints.value)
  if (isNaN(val) || val <= 0) {
    inputPoints.value = ''
    resetPoints()
    await loadPreview(0)
    return
  }

  const bounded = Math.min(val, maxUsablePoints.value || val)
  inputPoints.value = bounded
  await loadPreview(bounded)
}

async function handleQuickSelect(pct: number) {
  const calculated = setQuickPoints(pct)
  inputPoints.value = calculated > 0 ? calculated : ''
  await loadPreview(calculated)
}

async function handleClear() {
  inputPoints.value = ''
  resetPoints()
  await loadPreview(0)
}

onMounted(() => {
  loadPreview(props.modelValue || 0)
})
</script>

<template>
  <div class="loyalty-point-selector">
    <div class="loyalty-header">
      <div class="loyalty-title-row">
        <span class="material-symbols-outlined loyalty-icon">stars</span>
        <span class="loyalty-title">{{ t('loyalty.title') }}</span>
      </div>
      <span v-if="!noPolicy" class="loyalty-balance-badge">
        {{ t('loyalty.availableBalance') }}
        <strong>{{ balance }}</strong> {{ t('loyalty.points') }}
      </span>
    </div>

    <!-- No Policy State: Informational message without blocking checkout -->
    <div v-if="noPolicy" class="loyalty-info-box no-policy">
      <span class="material-symbols-outlined info-icon">info</span>
      <p class="loyalty-info-text">{{ t('loyalty.noPolicy') }}</p>
    </div>

    <!-- Zero Balance State -->
    <div v-else-if="!hasPoints" class="loyalty-info-box zero-balance">
      <p class="loyalty-info-text">{{ t('loyalty.noPointsAvailable') }}</p>
    </div>

    <!-- Active Point Selector Controls -->
    <div v-else class="loyalty-controls">
      <div class="loyalty-input-row">
        <input
          v-model.number="inputPoints"
          type="number"
          min="0"
          :max="maxUsablePoints"
          :placeholder="t('loyalty.enterPointsPlaceholder')"
          class="loyalty-input"
          :disabled="loading || maxUsablePoints <= 0"
          @change="onInputChange"
          @keydown.enter.prevent="onInputChange"
        />

        <div class="quick-buttons">
          <button
            type="button"
            class="btn-quick"
            :disabled="loading || maxUsablePoints <= 0"
            @click="handleQuickSelect(50)"
          >
            {{ t('loyalty.quickHalf') }}
          </button>
          <button
            type="button"
            class="btn-quick"
            :disabled="loading || maxUsablePoints <= 0"
            @click="handleQuickSelect(100)"
          >
            {{ t('loyalty.quickMax') }}
          </button>
          <button
            v-if="inputPoints !== '' && inputPoints > 0"
            type="button"
            class="btn-quick clear"
            :disabled="loading"
            @click="handleClear"
          >
            {{ t('loyalty.clear') }}
          </button>
        </div>
      </div>

      <!-- Breakdown when points selected -->
      <div v-if="discountVnd > 0" class="loyalty-breakdown">
        <div class="breakdown-row discount">
          <span>{{ t('loyalty.discountAmount') }}</span>
          <span class="discount-value font-mono">-{{ formatCurrency(discountVnd) }}</span>
        </div>
        <div class="breakdown-row remaining">
          <span>{{ t('loyalty.remainingWalletPayable') }}</span>
          <span class="remaining-value font-mono font-bold">{{
            formatCurrency(remainingPayableVnd)
          }}</span>
        </div>
      </div>

      <div v-if="error" class="loyalty-error">
        {{ error }}
      </div>
    </div>
  </div>
</template>

<style scoped>
.loyalty-point-selector {
  border: 1px solid var(--outline, #e2e8f0);
  border-radius: 0.5rem;
  padding: 0.75rem 1rem;
  margin: 0.75rem 0;
  background: var(--surface-subtle, #f8fafc);
  color: var(--ink, #0f172a);
}

.loyalty-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
}

.loyalty-title-row {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-weight: 600;
  color: var(--ink, #0f172a);
}

.loyalty-icon {
  font-size: 1.125rem;
  color: #f59e0b;
}

.loyalty-balance-badge {
  font-size: 0.75rem;
  color: var(--muted, #64748b);
  background: var(--surface, #f1f5f9);
  border: 1px solid var(--outline, #e2e8f0);
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
}

.loyalty-balance-badge strong {
  color: #0284c7;
}

.loyalty-info-box {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  color: var(--muted, #64748b);
}

.loyalty-info-box.no-policy {
  background: var(--surface, #f8fafc);
}

.info-icon {
  font-size: 1rem;
  color: var(--muted, #94a3b8);
}

.loyalty-input-row {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.loyalty-input {
  flex: 1;
  padding: 0.375rem 0.625rem;
  border: 1px solid var(--outline, #cbd5e1);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  background: var(--surface, #fff);
  color: var(--ink, #0f172a);
}

.quick-buttons {
  display: flex;
  gap: 0.25rem;
}

.btn-quick {
  padding: 0.375rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 500;
  border: 1px solid var(--outline, #cbd5e1);
  background: var(--surface, #fff);
  border-radius: 0.25rem;
  cursor: pointer;
  color: var(--ink, #334155);
  transition: all 0.15s ease;
}

.btn-quick:hover:not(:disabled) {
  background: var(--surface-subtle, #f1f5f9);
  border-color: var(--pink-dark, #db2777);
}

.btn-quick.clear {
  color: #ef4444;
  border-color: rgba(239, 68, 68, 0.4);
}

.btn-quick.clear:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.08);
}

.loyalty-breakdown {
  margin-top: 0.5rem;
  padding-top: 0.5rem;
  border-top: 1px dashed var(--outline, #cbd5e1);
  font-size: 0.8125rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.breakdown-row.discount .discount-value {
  color: #16a34a;
  font-weight: 600;
}

.breakdown-row.remaining .remaining-value {
  color: var(--ink, #0f172a);
}

.loyalty-error {
  margin-top: 0.375rem;
  font-size: 0.75rem;
  color: #dc2626;
}
</style>

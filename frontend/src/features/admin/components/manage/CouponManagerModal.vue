<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { Coupon } from '../../composables/useAdminManageCourts'

defineProps<{
  show: boolean
  coupons: Coupon[]
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'createCoupon', payload: { code: string; discount_type: 'fixed' | 'percent'; discount_value: number; usage_limit: number }): void
}>()

const { t } = useI18n()

const code = ref('')
const discountType = ref<'percent' | 'fixed'>('percent')
const discountValue = ref(15)
const usageLimit = ref(50)

function handleCreate() {
  if (!code.value.trim()) return
  emit('createCoupon', {
    code: code.value.toUpperCase().trim(),
    discount_type: discountType.value,
    discount_value: discountValue.value,
    usage_limit: usageLimit.value,
  })
  code.value = ''
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">confirmation_number</span>
          <h3 class="modal-title">{{ t('admin.manage.couponModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <!-- New Coupon Form -->
        <div class="coupon-form-box">
          <h4 class="form-sub-title">{{ t('admin.manage.createNewCoupon') }}</h4>
          <div class="coupon-inputs-grid">
            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.couponCodeLabel') }}</label>
              <input
                v-model="code"
                type="text"
                class="form-input font-mono"
                :placeholder="t('admin.manage.couponCodePlaceholder')"
              />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.discountTypeLabel') }}</label>
              <select v-model="discountType" class="form-input">
                <option value="percent">{{ t('admin.manage.discountPercentOption') }}</option>
                <option value="fixed">{{ t('admin.manage.discountFixedOption') }}</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.discountValueLabel') }}</label>
              <input v-model.number="discountValue" type="number" class="form-input font-mono" />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.usageLimitLabel') }}</label>
              <input v-model.number="usageLimit" type="number" class="form-input font-mono" />
            </div>

            <button
              type="button"
              class="btn-create-coupon"
              :disabled="!code.trim() || saving"
              @click="handleCreate"
            >
              <span class="material-symbols-outlined icon-16">add</span>
              <span>{{ t('admin.manage.createCouponBtn') }}</span>
            </button>
          </div>
        </div>

        <!-- Coupons Table List -->
        <div class="coupons-table-wrap">
          <h4 class="form-sub-title">{{ t('admin.manage.activeCouponsList', { count: coupons.length }) }}</h4>
          <div class="coupons-list">
            <div v-for="c in coupons" :key="c.id" class="coupon-item-row">
              <div class="coupon-code-badge font-mono">{{ c.code }}</div>
              <div class="coupon-meta">
                <strong v-if="c.discount_type === 'percent'">{{ t('admin.manage.discountPercentText', { value: c.discount_value }) }}</strong>
                <strong v-else>{{ t('admin.manage.discountFixedText', { value: formatCurrency(c.discount_value) }) }}</strong>
                <span class="font-mono">{{ t('admin.manage.usedCountText', { used: c.usage_count, limit: c.usage_limit || '∞' }) }}</span>
              </div>
              <span class="status-dot-active">● {{ t('admin.manage.statusActiveText') }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">{{ t('common.close') }}</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.coupon-form-box {
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-bottom: 20px;
}

.form-sub-title {
  margin: 0 0 12px;
  font-size: 13px;
  font-weight: 700;
}

.coupon-inputs-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr) auto;
  gap: 12px;
  align-items: end;
}

.btn-create-coupon {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 10px 18px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.coupons-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 250px;
  overflow-y: auto;
}

.coupon-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.coupon-code-badge {
  font-weight: 800;
  font-size: 13px;
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.1);
  padding: 4px 10px;
  border-radius: 6px;
}

.coupon-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
}

.coupon-meta span {
  color: var(--muted);
  font-size: 11px;
}

.status-dot-active {
  color: #10b981;
  font-size: 11px;
  font-weight: 700;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.icon-16 {
  font-size: 16px;
}
</style>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { AuraButton, CurrencyInput, SkeletonLoader } from '@/shared/components'
import type { LoyaltyConversionConfig } from '../../types/membership'

const props = defineProps<{
  config: LoyaltyConversionConfig
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update-rates', payload: Partial<LoyaltyConversionConfig>): void
}>()

const { t } = useI18n()

const isEditingRates = ref(false)
const editSpend = ref(props.config.spendAmountVnd)
const editSpendPts = ref(props.config.pointsEarned)
const editRedeemPts = ref(props.config.pointsRedeemed)
const editRedeemVnd = ref(props.config.rewardValueVnd)

watch(() => props.config, (newVal) => {
  if (newVal) {
    editSpend.value = newVal.spendAmountVnd
    editSpendPts.value = newVal.pointsEarned
    editRedeemPts.value = newVal.pointsRedeemed
    editRedeemVnd.value = newVal.rewardValueVnd
  }
}, { deep: true })

function startEditRates() {
  editSpend.value = props.config.spendAmountVnd
  editSpendPts.value = props.config.pointsEarned
  editRedeemPts.value = props.config.pointsRedeemed
  editRedeemVnd.value = props.config.rewardValueVnd
  isEditingRates.value = true
}

function saveRates() {
  emit('update-rates', {
    spendAmountVnd: Number(editSpend.value),
    pointsEarned: Number(editSpendPts.value),
    pointsRedeemed: Number(editRedeemPts.value),
    rewardValueVnd: Number(editRedeemVnd.value),
  })
  isEditingRates.value = false
}
</script>

<template>
  <div class="conversion-panel-card">
    <div class="panel-header">
      <div class="title-with-icon">
        <span class="material-symbols-outlined icon-18 text-pink">currency_exchange</span>
        <h3 class="panel-title">{{ t('admin.membership.conversionRatesTitle') }}</h3>
      </div>
      <AuraButton v-if="!isEditingRates && !loading" variant="secondary" size="sm" @click="startEditRates">
        <span class="material-symbols-outlined icon-14">edit</span>
        <span>{{ t('admin.membership.editRatesBtn') }}</span>
      </AuraButton>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="rates-column-stack">
      <SkeletonLoader height="70px" width="100%" radius="14px" />
      <SkeletonLoader height="70px" width="100%" radius="14px" />
    </div>

    <!-- View Mode -->
    <div v-else-if="!isEditingRates" class="rates-column-stack">
      <div class="conversion-tile">
        <div class="tile-side">
          <span class="tile-tag spend">TIÊU DÙNG</span>
          <strong class="tile-amount">{{ formatCurrency(config.spendAmountVnd) }}</strong>
        </div>
        <div class="tile-connector">
          <span class="material-symbols-outlined">arrow_forward</span>
        </div>
        <div class="tile-side">
          <span class="tile-tag earn">TÍCH ĐƯỢC</span>
          <strong class="tile-pts">+{{ config.pointsEarned }} <span class="pts-lbl">{{ t('admin.membership.pointsUnit') }}</span></strong>
        </div>
      </div>

      <div class="conversion-tile">
        <div class="tile-side">
          <span class="tile-tag redeem">ĐỔI THƯỞNG</span>
          <strong class="tile-pts">{{ config.pointsRedeemed }} <span class="pts-lbl">{{ t('admin.membership.pointsUnit') }}</span></strong>
        </div>
        <div class="tile-connector">
          <span class="material-symbols-outlined">arrow_forward</span>
        </div>
        <div class="tile-side">
          <span class="tile-tag voucher">GIÁ TRỊ</span>
          <strong class="tile-amount text-green">{{ formatCurrency(config.rewardValueVnd) }}</strong>
        </div>
      </div>
    </div>

    <!-- Edit Mode -->
    <div v-else class="rates-edit-box">
      <div class="edit-field">
        <label class="edit-lbl">{{ t('admin.membership.spendInputLabel') }}</label>
        <div class="edit-row">
          <div class="flex-1"><CurrencyInput v-model="editSpend" /></div>
          <span class="eq-symbol">=</span>
          <input v-model.number="editSpendPts" type="number" min="1" class="input-sm" />
          <span class="unit-lbl">{{ t('admin.membership.pointsUnit') }}</span>
        </div>
      </div>

      <div class="edit-field">
        <label class="edit-lbl">{{ t('admin.membership.redeemInputLabel') }}</label>
        <div class="edit-row">
          <input v-model.number="editRedeemPts" type="number" min="1" class="input-sm" />
          <span class="unit-lbl">{{ t('admin.membership.pointsUnit') }} =</span>
          <div class="flex-1"><CurrencyInput v-model="editRedeemVnd" /></div>
        </div>
      </div>

      <div class="edit-actions">
        <AuraButton variant="secondary" size="sm" @click="isEditingRates = false">{{ t('admin.membership.cancelBtn') }}</AuraButton>
        <AuraButton variant="primary" size="sm" @click="saveRates">{{ t('admin.membership.saveRatesBtn') }}</AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.conversion-panel-card { display: flex; flex-direction: column; gap: 14px; background: var(--surface); border: 1px solid var(--outline); border-radius: 20px; padding: 20px; }
:global(html.dark) .conversion-panel-card { background: #181a20; border-color: #2a2d36; }
.panel-header { display: flex; justify-content: space-between; align-items: center; }
.title-with-icon { display: flex; align-items: center; gap: 8px; }
.panel-title { margin: 0; font-size: 13.5px; font-weight: 800; color: var(--ink); letter-spacing: 0.02em; }
.rates-column-stack { display: flex; flex-direction: column; gap: 10px; }
.conversion-tile { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-radius: 14px; background: var(--surface-container-high, #f8f9fa); border: 1px solid var(--outline); }
:global(html.dark) .conversion-tile { background: #1e2129; border-color: #2b2f3a; }
.tile-side { display: flex; flex-direction: column; gap: 2px; }
.tile-tag { font-size: 10px; font-weight: 800; letter-spacing: 0.05em; color: var(--muted); }
.tile-tag.spend { color: #f59e0b; }
.tile-tag.earn { color: var(--pink-dark, #db2777); }
.tile-tag.redeem { color: #8b5cf6; }
.tile-tag.voucher { color: #10b981; }
.tile-amount { font-size: 15px; font-weight: 800; color: var(--ink); font-family: monospace; }
.tile-pts { font-size: 15px; font-weight: 800; color: var(--pink-dark, #db2777); font-family: monospace; }
.pts-lbl { font-size: 11.5px; font-weight: 600; color: var(--muted); }
.tile-connector { color: var(--muted); display: flex; }
.rates-edit-box { display: flex; flex-direction: column; gap: 12px; padding: 14px; border-radius: 14px; background: var(--surface-container-high, #f8f9fa); border: 1px solid var(--outline); }
:global(html.dark) .rates-edit-box { background: #1e2129; border-color: #2b2f3a; }
.edit-field { display: flex; flex-direction: column; gap: 6px; }
.edit-lbl { font-size: 11.5px; font-weight: 700; color: var(--muted); }
.edit-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.input-sm { width: 70px; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--outline); background: var(--surface); color: var(--ink); font-size: 13px; font-weight: 700; }
:global(html.dark) .input-sm { background: #14161c; border-color: #2d313d; }
.eq-symbol, .unit-lbl { font-size: 12.5px; font-weight: 700; color: var(--muted); }
.edit-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 4px; }
.flex-1 { flex: 1; min-width: 110px; }
.text-pink { color: var(--pink-dark, #db2777); }
.text-green { color: #10b981; }
.icon-18 { font-size: 18px; }
.icon-14 { font-size: 14px; }
</style>

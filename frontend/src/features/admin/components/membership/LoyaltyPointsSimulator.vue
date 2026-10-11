<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { CurrencyInput } from '@/shared/components'
import type { LoyaltyConversionConfig } from '../../types/membership'

const props = defineProps<{
  config: LoyaltyConversionConfig
}>()

const { t } = useI18n()

const simAmount = ref(500000)
const simTierMultiplier = ref(2.0)
const simRuleMultiplier = ref(1.5)

const calculatedPoints = computed(() => {
  const baseRate = (props.config.pointsEarned || 1) / (props.config.spendAmountVnd || 1000)
  const basePts = (simAmount.value || 0) * baseRate
  return Math.round(basePts * simTierMultiplier.value * simRuleMultiplier.value)
})

const calculatedDiscountValue = computed(() => {
  const rate = (props.config.rewardValueVnd || 5000) / (props.config.pointsRedeemed || 100)
  return Math.round(calculatedPoints.value * rate)
})
</script>

<template>
  <div class="simulator-card">
    <div class="sim-header-row">
      <div class="title-wrap">
        <span class="material-symbols-outlined icon-18 text-pink">calculate</span>
        <h3 class="sim-title">{{ t('admin.membership.simulatorTitle') }}</h3>
      </div>
      <span class="live-tag">REALTIME</span>
    </div>

    <div class="sim-inputs-wrap">
      <div class="sim-field">
        <label class="sim-label">{{ t('admin.membership.simSpendLabel') }}</label>
        <CurrencyInput v-model="simAmount" />
      </div>

      <div class="sliders-row">
        <div class="sim-field flex-1">
          <div class="slider-head">
            <span class="sim-label">{{ t('admin.membership.simTierMultiplierLabel') }}</span>
            <strong class="mult-value text-gold">x{{ simTierMultiplier }}</strong>
          </div>
          <input
            v-model.number="simTierMultiplier"
            type="range"
            min="1.0"
            max="3.0"
            step="0.1"
            class="aura-slider"
          />
        </div>

        <div class="sim-field flex-1">
          <div class="slider-head">
            <span class="sim-label">{{ t('admin.membership.simRuleMultiplierLabel') }}</span>
            <strong class="mult-value text-pink">x{{ simRuleMultiplier }}</strong>
          </div>
          <input
            v-model.number="simRuleMultiplier"
            type="range"
            min="1.0"
            max="3.0"
            step="0.1"
            class="aura-slider"
          />
        </div>
      </div>
    </div>

    <!-- Calculated Metric Banner -->
    <div class="sim-outcome-grid">
      <div class="outcome-col">
        <span class="outcome-label">{{ t('admin.membership.simPointsEarnedLabel') }}</span>
        <strong class="outcome-val text-pink"
          >+{{ calculatedPoints.toLocaleString() }} {{ t('admin.membership.pointsUnit') }}</strong
        >
      </div>
      <div class="outcome-divider"></div>
      <div class="outcome-col">
        <span class="outcome-label">{{ t('admin.membership.simDiscountValueLabel') }}</span>
        <strong class="outcome-val text-green"
          >~ {{ formatCurrency(calculatedDiscountValue) }}</strong
        >
      </div>
    </div>
  </div>
</template>

<style scoped>
.simulator-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  padding: 20px;
}
:global(html.dark) .simulator-card {
  background: #181a20;
  border-color: #2a2d36;
}
.sim-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}
.sim-title {
  margin: 0;
  font-size: 13.5px;
  font-weight: 800;
  color: var(--ink);
}
.live-tag {
  font-size: 10px;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 6px;
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  letter-spacing: 0.05em;
}
.sim-inputs-wrap {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.sim-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.sim-label {
  font-size: 11.5px;
  font-weight: 700;
  color: var(--muted);
}
.sliders-row {
  display: flex;
  gap: 14px;
}
.slider-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.mult-value {
  font-size: 12px;
  font-weight: 800;
  font-family: monospace;
}
.aura-slider {
  accent-color: var(--pink-dark, #db2777);
  cursor: pointer;
  height: 5px;
  border-radius: 9999px;
}
.sim-outcome-grid {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  padding: 12px 16px;
  border-radius: 14px;
  background: var(--surface-container-high, #f8f9fa);
  border: 1px solid var(--outline);
}
:global(html.dark) .sim-outcome-grid {
  background: #1e2129;
  border-color: #2b2f3a;
}
.outcome-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}
.outcome-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
}
.outcome-val {
  font-size: 15px;
  font-weight: 800;
  font-family: monospace;
}
.outcome-divider {
  width: 1px;
  height: 28px;
  background: var(--outline);
  margin: 0 8px;
}
:global(html.dark) .outcome-divider {
  background: #2f333f;
}
.flex-1 {
  flex: 1;
}
.text-pink {
  color: var(--pink-dark, #db2777);
}
.text-gold {
  color: #f59e0b;
}
.text-green {
  color: #10b981;
}
.icon-18 {
  font-size: 18px;
}
@media (max-width: 600px) {
  .sliders-row {
    flex-direction: column;
  }
}
</style>

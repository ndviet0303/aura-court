<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { usePricingCalendarV2, type PriceRuleItem } from '../composables/usePricingCalendarV2'

import PricingRulePriorityStack from '../components/pricing/PricingRulePriorityStack.vue'
import PricingCalendarGrid from '../components/pricing/PricingCalendarGrid.vue'
import PricingSimulatorModal from '../components/pricing/PricingSimulatorModal.vue'
import PricingRuleModal from '../components/pricing/PricingRuleModal.vue'
import PricingPresetsModal from '../components/pricing/PricingPresetsModal.vue'

const { t } = useI18n()

const {
  loading,
  notice,
  error,
  showSimulatorModal,
  showRuleModal,
  editingRule,
  rules,
  loadRules,
  showNotice,
  saveRule,
} = usePricingCalendarV2()

const showPresetsModal = ref(false)

function handleStartCreate() {
  editingRule.value = null
  showRuleModal.value = true
}

function handleStartEdit(rule: PriceRuleItem) {
  editingRule.value = rule
  showRuleModal.value = true
}

onMounted(() => {
  loadRules()
})
</script>

<template>
  <div class="pricing-calendar-v2-view">
    <!-- Header Section -->
    <header class="pricing-header">
      <div class="header-titles">
        <div class="header-title-row">
          <span class="material-symbols-outlined header-icon">price_change</span>
          <h1 class="page-title">{{ t('admin.pricing.title') }}</h1>
        </div>
        <p class="page-sub">{{ t('admin.pricing.subtitle') }}</p>
      </div>

      <div class="header-actions">
        <AuraButton variant="secondary" @click="showPresetsModal = true">
          <span class="material-symbols-outlined icon-18">tune</span>
          <span>{{ t('admin.pricing.presetsBtn') }}</span>
        </AuraButton>
        <AuraButton variant="outline" @click="showSimulatorModal = true">
          <span class="material-symbols-outlined icon-18">calculate</span>
          <span>{{ t('admin.pricing.simulatorBtn') }}</span>
        </AuraButton>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Priority Rules Stack -->
    <PricingRulePriorityStack
      :rules="rules"
      @create-rule="handleStartCreate"
      @edit-rule="handleStartEdit"
    />

    <!-- Interactive Calendar Grid -->
    <PricingCalendarGrid />

    <!-- Modals -->
    <PricingSimulatorModal
      :show="showSimulatorModal"
      @close="showSimulatorModal = false"
    />

    <PricingRuleModal
      :show="showRuleModal"
      :rule="editingRule"
      @close="showRuleModal = false"
      @save="saveRule"
    />

    <PricingPresetsModal
      :show="showPresetsModal"
      @close="showPresetsModal = false"
      @apply-preset="showNotice(t('admin.pricing.presetApplied', { name: $event })); showPresetsModal = false"
    />
  </div>
</template>

<style scoped>
.pricing-calendar-v2-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.pricing-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 6px 0 0;
  font-size: 13px;
  color: var(--muted);
}

.header-actions {
  display: flex;
  gap: 10px;
  align-items: center;
}

.alert-pill {
  padding: 8px 16px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  padding: 8px 16px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.icon-18 {
  font-size: 18px;
}
</style>

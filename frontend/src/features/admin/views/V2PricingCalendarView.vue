<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePricingCalendarV2, type PriceRuleItem } from '../composables/usePricingCalendarV2'

import PricingRulePriorityStack from '../components/pricing/PricingRulePriorityStack.vue'
import PricingCalendarGrid from '../components/pricing/PricingCalendarGrid.vue'
import PricingSimulatorModal from '../components/pricing/PricingSimulatorModal.vue'
import PricingRuleModal from '../components/pricing/PricingRuleModal.vue'
import PricingPresetsModal from '../components/pricing/PricingPresetsModal.vue'

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
        <h1 class="page-title">⚡ Thiết lập Ma trận Giá linh hoạt (Dynamic Pricing V2)</h1>
        <p class="page-sub">Cài đặt quy tắc định giá giờ cao điểm, phụ thu ngày lễ và tự động điều chỉnh theo nhu cầu.</p>
      </div>

      <div class="header-actions">
        <button class="btn-presets" @click="showPresetsModal = true">
          📑 Mẫu cài đặt giá
        </button>
        <button class="btn-simulator" @click="showSimulatorModal = true">
          🧮 Mô phỏng báo giá
        </button>
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
      @apply-preset="showNotice(`Đã áp dụng mẫu: ${$event}`); showPresetsModal = false"
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

.page-title {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.header-actions {
  display: flex;
  gap: 10px;
}

.btn-presets, .btn-simulator {
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-presets:hover, .btn-simulator:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
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
</style>

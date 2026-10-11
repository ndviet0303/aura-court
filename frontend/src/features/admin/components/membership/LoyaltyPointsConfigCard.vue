<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { LoyaltyConversionConfig, MultiplierRule } from '../../types/membership'
import LoyaltyConversionCard from './LoyaltyConversionCard.vue'
import LoyaltyMultiplierRulesTable from './LoyaltyMultiplierRulesTable.vue'
import LoyaltyPointsSimulator from './LoyaltyPointsSimulator.vue'

defineProps<{
  config: LoyaltyConversionConfig
  rules: MultiplierRule[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'open-create-rule'): void
  (e: 'edit-rule', rule: MultiplierRule): void
  (e: 'toggle-rule', ruleId: string): void
  (e: 'delete-rule', ruleId: string): void
  (e: 'update-rates', payload: Partial<LoyaltyConversionConfig>): void
}>()

const { t } = useI18n()
</script>

<template>
  <section class="loyalty-config-section">
    <div class="section-header">
      <div class="title-with-icon">
        <span class="icon-badge material-symbols-outlined">tune</span>
        <h2 class="section-title">{{ t('admin.membership.loyaltySectionTitle') }}</h2>
      </div>
    </div>

    <div class="config-card">
      <div class="config-top-grid">
        <LoyaltyConversionCard
          :config="config"
          :loading="loading"
          @update-rates="emit('update-rates', $event)"
        />

        <LoyaltyPointsSimulator :config="config" />
      </div>

      <div class="config-divider"></div>

      <LoyaltyMultiplierRulesTable
        :rules="rules"
        :loading="loading"
        @open-create-rule="emit('open-create-rule')"
        @edit-rule="emit('edit-rule', $event)"
        @toggle-rule="emit('toggle-rule', $event)"
        @delete-rule="emit('delete-rule', $event)"
      />
    </div>
  </section>
</template>

<style scoped>
.loyalty-config-section {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 12px;
}

.icon-badge {
  font-size: 20px;
  padding: 8px;
  border-radius: 9999px;
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
}

.section-title {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -0.01em;
}

.config-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px;
  border-radius: 20px;
  background: var(--surface);
  border: 1px solid var(--outline);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}

.config-top-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  align-items: start;
}

.config-divider {
  height: 1px;
  background: var(--outline);
}

@media (max-width: 900px) {
  .config-top-grid {
    grid-template-columns: 1fr;
  }
}
</style>

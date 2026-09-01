<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency, resolveLocalizedText } from '@/i18n'
import type { PriceRuleItem } from '../../composables/usePricingCalendarV2'

defineProps<{
  rules: PriceRuleItem[]
}>()

const emit = defineEmits<{
  (e: 'editRule', rule: PriceRuleItem): void
  (e: 'createRule'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="priority-stack-container">
    <div class="stack-header">
      <div>
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">tune</span>
          <h3 class="section-title">{{ t('admin.pricing.hierarchyTitle') }}</h3>
        </div>
        <p class="section-sub">{{ t('admin.pricing.hierarchySub') }}</p>
      </div>

      <button type="button" class="btn-create-rule" @click="emit('createRule')">
        <span class="material-symbols-outlined icon-16">add</span>
        <span>{{ t('admin.pricing.addNewRuleBtn') }}</span>
      </button>
    </div>

    <div class="rules-stack-list">
      <div
        v-for="rule in rules"
        :key="rule.id"
        class="rule-card"
        :class="`type-${rule.type}`"
      >
        <div class="priority-badge">#{{ rule.priority }}</div>

        <div class="rule-details">
          <div class="rule-title-row">
            <strong class="rule-name">{{ rule.name || ((rule as any).court ? resolveLocalizedText((rule as any).court.name) : rule.type.toUpperCase()) }}</strong>
            <span class="rule-type-tag">{{ rule.type }}</span>
          </div>

          <div class="rule-meta">
            <span v-if="rule.starts_at" class="font-mono">
              <span class="material-symbols-outlined icon-14">schedule</span>
              {{ rule.starts_at }} - {{ rule.ends_at }}
            </span>
            <span v-if="rule.adjustment_type === 'percentage_add'" class="text-green">
              {{ t('admin.pricing.ruleHolidaySurcharge', { value: rule.adjustment_value ?? rule.price ?? 0 }) }}
            </span>
            <span v-else-if="rule.adjustment_type === 'percentage_discount'" class="text-blue">
              {{ t('admin.pricing.ruleLowDemandDiscount', { value: rule.adjustment_value ?? rule.price ?? 0 }) }}
            </span>
            <span v-else class="text-pink font-mono">
              {{ t('admin.pricing.ruleFixedRate', { amount: formatCurrency(rule.price ?? rule.adjustment_value ?? 0) }) }}
            </span>
          </div>
        </div>

        <div class="rule-actions">
          <button type="button" class="btn-edit" @click="emit('editRule', rule)">
            <span class="material-symbols-outlined icon-14">edit</span>
            <span>{{ t('admin.inventory.editProduct') }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.priority-stack-container {
  display: flex;
  flex-direction: column;
  gap: 14px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  padding: 20px;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.stack-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.section-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.section-sub {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.btn-create-rule {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 8px 16px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.rules-stack-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.rule-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.priority-badge {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: var(--pink-dark, #db2777);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 13px;
}

.rule-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}

.rule-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.rule-name {
  font-size: 14px;
}

.rule-type-tag {
  font-size: 10px;
  text-transform: uppercase;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.05));
  color: var(--muted);
}

.rule-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 12px;
}

.rule-meta span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.text-green { color: #10b981; }
.text-blue { color: #3b82f6; }
.text-pink { color: var(--pink-dark, #db2777); }

.btn-edit {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.icon-16 { font-size: 16px; }
.icon-14 { font-size: 14px; }
</style>

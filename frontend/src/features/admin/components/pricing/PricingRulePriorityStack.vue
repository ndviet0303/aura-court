<script setup lang="ts">
import type { PriceRuleItem } from '../../composables/usePricingCalendarV2'

defineProps<{
  rules: PriceRuleItem[]
}>()

const emit = defineEmits<{
  (e: 'editRule', rule: PriceRuleItem): void
  (e: 'createRule'): void
}>()
</script>

<template>
  <div class="priority-stack-container">
    <div class="stack-header">
      <div>
        <h3 class="section-title">⚡ Độ ưu tiên giá (Dynamic Price Hierarchy)</h3>
        <p class="section-sub">Quy tắc có Priority cao hơn (1 > 2 > 3) sẽ ghi đè giá cơ bản và áp dụng trước.</p>
      </div>

      <button class="btn-create-rule" @click="emit('createRule')">
        + Thêm Quy Tắc Mới
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
            <strong class="rule-name">{{ rule.name }}</strong>
            <span class="rule-type-tag">{{ rule.type }}</span>
          </div>

          <div class="rule-meta">
            <span v-if="rule.starts_at">⏰ {{ rule.starts_at }} - {{ rule.ends_at }}</span>
            <span v-if="rule.adjustment_type === 'percentage_add'" class="text-green">+{{ rule.adjustment_value }}% (Phụ thu ngày lễ)</span>
            <span v-else-if="rule.adjustment_type === 'percentage_discount'" class="text-blue">-{{ rule.adjustment_value }}% (Giảm giá thấp điểm)</span>
            <span v-else class="text-pink">Cố định: {{ rule.adjustment_value.toLocaleString('vi-VN') }} ₫</span>
          </div>
        </div>

        <div class="rule-actions">
          <button class="btn-edit" @click="emit('editRule', rule)">✏️ Sửa</button>
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
  gap: 12px;
  font-size: 12px;
}

.text-green { color: #10b981; }
.text-blue { color: #3b82f6; }
.text-pink { color: var(--pink-dark, #db2777); }

.btn-edit {
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
</style>

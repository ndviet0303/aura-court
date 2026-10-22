<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { PromotionProgram } from '../../types/promotionProgram'
import { usePromotionProgramLabels } from '../../composables/usePromotionProgramLabels'

defineProps<{
  programs: PromotionProgram[]
  selectedId: number | null
  loading: boolean
}>()

const emit = defineEmits<{ (e: 'select', programId: number): void }>()

const { t } = useI18n()
const { type, status, discount, period } = usePromotionProgramLabels()
</script>

<template>
  <section class="program-list">
    <div v-if="!loading && programs.length === 0" class="program-empty">
      <span class="material-symbols-outlined empty-icon">sell</span>
      <strong>{{ t('admin.promotions.empty') }}</strong>
      <p>{{ t('admin.promotions.emptyHint') }}</p>
    </div>
    <button
      v-for="program in programs"
      :key="program.id"
      type="button"
      class="program-item"
      :class="{ 'is-selected': program.id === selectedId }"
      data-testid="promotion-program-item"
      @click="emit('select', program.id)"
    >
      <span class="item-name">{{ program.name }}</span>
      <span class="item-meta">
        <span class="badge" :class="`badge--${program.status}`">{{ status(program.status) }}</span>
        <span class="badge">{{ type(program.type) }}</span>
        <span>{{ discount(program) }}</span>
      </span>
      <span class="item-sub">{{ period(program) }}</span>
      <span class="item-sub">{{
        t('admin.promotions.usageSummary', {
          used: program.usage.used,
          held: program.usage.held,
          remaining: program.usage.remaining,
        })
      }}</span>
    </button>
  </section>
</template>

<style scoped>
.program-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.program-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 32px 16px;
  border: 1px dashed var(--outline);
  border-radius: 16px;
  color: var(--muted);
  text-align: center;
}

.program-empty strong {
  color: var(--ink);
}

.program-empty p {
  margin: 0;
  font-size: 13px;
}

.empty-icon {
  font-size: 32px;
}

.program-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px 16px;
  border: 1px solid var(--outline);
  border-radius: 14px;
  background: var(--surface);
  color: var(--ink);
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s;
}

.program-item:hover,
.program-item.is-selected {
  border-color: var(--pink-dark, #db2777);
}

.item-name {
  font-weight: 700;
}

.item-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  font-size: 12px;
}

.item-sub {
  color: var(--muted);
  font-size: 12px;
}

.badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-weight: 600;
  background: var(--soft, rgba(0, 0, 0, 0.05));
}

.badge--running {
  color: #047857;
  background: rgba(16, 185, 129, 0.12);
}

.badge--upcoming {
  color: #b45309;
  background: rgba(245, 158, 11, 0.12);
}

.badge--ended,
.badge--stopped {
  color: var(--muted);
}
</style>

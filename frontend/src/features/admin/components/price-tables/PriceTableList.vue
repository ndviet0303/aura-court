<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { PriceTable } from '../../types/priceTable'
import { usePriceTableLabels } from '../../composables/usePriceTableLabels'

defineProps<{
  tables: PriceTable[]
  selectedId: number | null
  loading: boolean
}>()

const emit = defineEmits<{ (e: 'select', tableId: number): void }>()

const { t } = useI18n()
const { kind, status } = usePriceTableLabels()

const courtCount = (table: PriceTable) => new Set(table.links.map((link) => link.court_id)).size
</script>

<template>
  <section class="price-table-list">
    <div v-if="!loading && tables.length === 0" class="price-table-empty">
      <span class="material-symbols-outlined empty-icon">price_change</span>
      <strong>{{ t('admin.priceTables.empty') }}</strong>
      <p>{{ t('admin.priceTables.emptyHint') }}</p>
    </div>
    <button
      v-for="table in tables"
      :key="table.id"
      type="button"
      class="price-table-item"
      :class="{ 'is-selected': table.id === selectedId }"
      @click="emit('select', table.id)"
    >
      <span class="item-name">{{ table.name }}</span>
      <span class="item-meta">
        <span class="badge" :class="`badge--${table.kind}`">{{ kind(table.kind) }}</span>
        <span class="badge" :class="`badge--${table.status}`">{{ status(table.status) }}</span>
        <span class="item-courts">{{
          t('admin.priceTables.courtsCount', { count: courtCount(table) })
        }}</span>
      </span>
    </button>
  </section>
</template>

<style scoped>
.price-table-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.price-table-empty {
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

.price-table-empty strong {
  color: var(--ink);
}

.price-table-empty p {
  margin: 0;
  font-size: 13px;
}

.empty-icon {
  font-size: 32px;
}

.price-table-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px 16px;
  border: 1px solid var(--outline);
  border-radius: 14px;
  background: var(--surface);
  color: var(--ink);
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s;
}

.price-table-item:hover,
.price-table-item.is-selected {
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

.item-courts {
  color: var(--muted);
}

.badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-weight: 600;
  background: var(--soft, rgba(0, 0, 0, 0.05));
}

.badge--holiday {
  color: #b45309;
  background: rgba(245, 158, 11, 0.12);
}

.badge--active {
  color: #047857;
  background: rgba(16, 185, 129, 0.12);
}

.badge--retired {
  color: var(--muted);
}
</style>

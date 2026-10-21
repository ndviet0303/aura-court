<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { PriceTableDetail, PriceTableLink } from '../../types/priceTable'
import { usePriceTableLabels } from '../../composables/usePriceTableLabels'

defineProps<{
  table: PriceTableDetail
  saving: boolean
}>()

const emit = defineEmits<{
  (e: 'apply'): void
  (e: 'end-link', link: PriceTableLink): void
  (e: 'retire'): void
}>()

const { t } = useI18n()
const { kind, status, days, instant, money } = usePriceTableLabels()
</script>

<template>
  <section class="price-table-detail">
    <header class="detail-header">
      <div>
        <h2 class="detail-title">{{ table.name }}</h2>
        <p class="detail-meta">{{ kind(table.kind) }} · {{ status(table.status) }}</p>
      </div>
      <div class="detail-actions">
        <AuraButton
          v-if="table.status === 'active'"
          variant="primary"
          size="sm"
          :disabled="saving"
          @click="emit('apply')"
        >
          {{ t('admin.priceTables.applyBtn') }}
        </AuraButton>
        <AuraButton
          v-if="table.status === 'active'"
          variant="outline"
          size="sm"
          :disabled="saving"
          @click="emit('retire')"
        >
          {{ t('admin.priceTables.retireBtn') }}
        </AuraButton>
      </div>
    </header>

    <h3 class="section-title">{{ t('admin.priceTables.linksTitle') }}</h3>
    <p v-if="table.links.length === 0" class="muted">{{ t('admin.priceTables.noLinks') }}</p>
    <ul v-else class="link-list">
      <li
        v-for="link in table.links"
        :key="`${link.court_id}-${link.starts_at}`"
        class="link-row"
        data-testid="price-table-link"
      >
        <span class="link-court">{{ link.court_name }}</span>
        <span class="muted">{{
          link.ends_at
            ? t('admin.priceTables.linkPeriod', {
                from: instant(link.starts_at),
                to: instant(link.ends_at),
              })
            : t('admin.priceTables.linkOpenEnded', { from: instant(link.starts_at) })
        }}</span>
        <button
          type="button"
          class="link-end-btn"
          :disabled="saving"
          @click="emit('end-link', link)"
        >
          {{ t('admin.priceTables.endLinkBtn') }}
        </button>
      </li>
    </ul>

    <h3 class="section-title">{{ t('admin.priceTables.versionsTitle') }}</h3>
    <article v-for="version in table.versions" :key="version.id" class="version-card">
      <p class="version-meta">
        <strong>{{ t('admin.priceTables.versionLabel', { id: version.id }) }}</strong>
        <span>{{
          t('admin.priceTables.publishedAt', { time: instant(version.published_at) })
        }}</span>
        <span>{{
          t('admin.priceTables.effectiveAt', { time: instant(version.effective_at) })
        }}</span>
      </p>
      <table class="interval-table">
        <thead>
          <tr>
            <th>{{ t('admin.priceTables.colDays') }}</th>
            <th>{{ t('admin.priceTables.colHours') }}</th>
            <th>{{ t('admin.priceTables.colPrice') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(interval, index) in version.intervals" :key="index">
            <td>{{ days(interval) }}</td>
            <td>{{ interval.start }}–{{ interval.end }}</td>
            <td>{{ money(interval.price_per_hour) }}</td>
          </tr>
        </tbody>
      </table>
    </article>
  </section>
</template>

<style scoped>
.price-table-detail {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 20px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
}

.detail-header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px;
}

.detail-title {
  margin: 0;
  font-size: 18px;
}

.detail-meta,
.muted {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 13px;
}

.detail-actions {
  display: flex;
  gap: 8px;
}

.section-title {
  margin: 8px 0 0;
  font-size: 14px;
}

.link-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.link-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 12px;
  padding: 8px 12px;
  border-radius: 10px;
  background: var(--soft, rgba(0, 0, 0, 0.03));
  font-size: 13px;
}

.link-court {
  font-weight: 600;
}

.link-end-btn {
  margin-left: auto;
  border: 0;
  background: transparent;
  color: var(--danger, #ef4444);
  font-weight: 600;
  cursor: pointer;
}

.version-card {
  padding: 12px;
  border: 1px solid var(--outline);
  border-radius: 12px;
}

.version-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  margin: 0 0 8px;
  font-size: 13px;
}

.interval-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.interval-table th,
.interval-table td {
  padding: 6px 8px;
  border-bottom: 1px solid var(--outline);
  text-align: left;
}

.interval-table th {
  color: var(--muted);
  font-weight: 600;
}
</style>

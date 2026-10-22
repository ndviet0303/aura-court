<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, DateTimePicker, SelectDropdown } from '@/shared/components'
import { useCourtPriceTable } from '../../composables/useCourtPriceTable'

const props = defineProps<{ courtId: number; venueId: number }>()

const { t } = useI18n()
const {
  labels,
  links,
  prices,
  missing,
  tableOptions,
  tableId,
  switchAt,
  loading,
  saving,
  notice,
  errors,
  rejectedGaps,
  load,
  apply,
  end,
} = useCourtPriceTable(props.courtId, props.venueId)

onMounted(load)
</script>

<template>
  <section class="price-panel" data-testid="court-price-table">
    <h4 class="panel-title">{{ t('admin.priceTables.court.title') }}</h4>
    <p v-if="loading" class="form-hint">{{ t('common.loading') }}</p>

    <p v-if="!loading && links.length === 0" class="form-hint">
      {{ t('admin.priceTables.court.noLinks') }}
    </p>
    <ul v-else class="plain-list">
      <li v-for="link in links" :key="`${link.table.id}-${link.starts_at}`" class="link-row">
        <span>
          <strong>{{ link.table.name }}</strong> · {{ labels.kind(link.table.kind) }}
          <span class="form-hint block">{{
            link.ends_at
              ? t('admin.priceTables.linkPeriod', {
                  from: labels.instant(link.starts_at),
                  to: labels.instant(link.ends_at),
                })
              : t('admin.priceTables.linkOpenEnded', { from: labels.instant(link.starts_at) })
          }}</span>
        </span>
        <AuraButton
          variant="danger"
          size="sm"
          :disabled="saving"
          @click="end(link.table.kind)"
        >
          {{ t('admin.priceTables.endLinkBtn') }}
        </AuraButton>
      </li>
    </ul>

    <div v-for="price in prices" :key="price.table.id" class="price-block">
      <p class="sub-title">
        {{ t('admin.priceTables.court.pricesTitle', { name: price.table.name }) }}
      </p>
      <ul class="plain-list">
        <li v-for="(interval, index) in price.intervals" :key="index">
          {{ labels.days(interval) }} · {{ interval.start }}–{{ interval.end }} ·
          {{ labels.money(interval.price_per_hour) }}
        </li>
      </ul>
    </div>

    <p v-if="missing && missing.length === 0" class="coverage-ok">
      {{ t('admin.priceTables.court.covered') }}
    </p>
    <div v-else-if="missing" class="coverage-gaps">
      <p class="sub-title">{{ t('admin.priceTables.court.gapsTitle') }}</p>
      <ul class="plain-list">
        <li v-for="item in missing" :key="labels.missingItem(item)">
          {{ labels.missingItem(item) }}
        </li>
      </ul>
    </div>

    <div class="apply-grid">
      <div class="form-group">
        <span class="form-label">{{ t('admin.priceTables.court.tableLabel') }}</span>
        <p v-if="!loading && tableOptions.length === 0" class="form-hint">
          {{ t('admin.priceTables.court.noActiveTables') }}
        </p>
        <SelectDropdown
          v-else
          :model-value="tableId"
          :options="tableOptions"
          :placeholder="t('admin.priceTables.court.tablePlaceholder')"
          :aria-label="t('admin.priceTables.court.tableLabel')"
          variant="bordered"
          @update:model-value="(value) => (tableId = Number(value) || null)"
        />
      </div>
      <div class="form-group">
        <span class="form-label">{{ t('admin.priceTables.court.atLabel') }}</span>
        <DateTimePicker v-model="switchAt" />
      </div>
    </div>
    <span class="form-hint block">{{ t('admin.priceTables.link.nowHint') }}</span>
    <span class="form-hint block">{{ t('admin.priceTables.link.applyHint') }}</span>
    <AuraButton class="apply-btn" :loading="saving" :disabled="!tableId" @click="apply">
      {{ t('admin.priceTables.link.submitApply') }}
    </AuraButton>

    <p v-if="notice" class="coverage-ok" role="status">{{ notice }}</p>
    <div v-if="rejectedGaps.length" class="form-error" role="alert">
      <p>{{ t('admin.priceTables.coverage.title') }}. {{ t('admin.priceTables.coverage.message') }}</p>
      <ul class="plain-list">
        <li v-for="gap in rejectedGaps" :key="gap">{{ gap }}</li>
      </ul>
    </div>
    <p v-for="message in errors" :key="message" class="form-error" role="alert">{{ message }}</p>
  </section>
</template>

<style scoped>
.price-panel {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 16px;
  border-top: 1px solid var(--outline);
}

.panel-title,
.sub-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
}

.sub-title {
  font-size: 13px;
}

.plain-list {
  margin: 0;
  padding-left: 18px;
  font-size: 13px;
}

.link-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 4px 0;
}

.block {
  display: block;
}

.apply-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.apply-btn {
  align-self: flex-start;
}

.coverage-ok {
  margin: 0;
  font-size: 13px;
  color: #16a34a;
}

.coverage-gaps {
  color: #b45309;
}

@media (max-width: 640px) {
  .apply-grid {
    grid-template-columns: 1fr;
  }
}
</style>

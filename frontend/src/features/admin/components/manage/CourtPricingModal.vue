<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency } from '@/i18n'
import type { Court } from '@/features/court/types/court'

defineProps<{
  show: boolean
  court: Court | null
  selectedWeekdayTab: number
  bulkScope: 'current' | 'weekdays' | 'weekends' | 'all'
  bulkStartHour: number
  bulkEndHour: number
  bulkPrice: number
  bulkSaving?: boolean
  priceNotice?: string
  priceError?: string
  weekdayList: Array<{ id: number; label: string; short: string; isWeekend?: boolean }>
  morningSlots: any[]
  afternoonSlots: any[]
  peakSlots: any[]
  nightSlots: any[]
  pricingStats: { count: number; min: number; max: number; avg: number }
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update:selectedWeekdayTab', day: number): void
  (e: 'update:bulkScope', scope: 'current' | 'weekdays' | 'weekends' | 'all'): void
  (e: 'update:bulkStartHour', h: number): void
  (e: 'update:bulkEndHour', h: number): void
  (e: 'update:bulkPrice', p: number): void
  (e: 'applyBulk'): void
  (e: 'deleteSlot', slotId: number): void
}>()

const { t } = useI18n()
</script>

<template>
  <div v-if="show && court" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-xl">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">price_change</span>
          <h3 class="modal-title">
            {{ t('admin.manage.pricingModalTitle', { name: resolveLocalizedText(court.name) }) }}
          </h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body pricing-modal-body">
        <div v-if="priceNotice" class="alert-banner-success">{{ priceNotice }}</div>
        <div v-if="priceError" class="alert-banner-error">{{ priceError }}</div>

        <!-- Weekday Navigation Tabs -->
        <div class="weekday-tabs-row">
          <button
            type="button"
            class="weekday-tab-btn"
            :class="{ active: selectedWeekdayTab === 0 }"
            @click="emit('update:selectedWeekdayTab', 0)"
          >
            {{ t('admin.manage.allDaysTab') }}
          </button>

          <button
            v-for="d in weekdayList"
            :key="d.id"
            type="button"
            class="weekday-tab-btn"
            :class="{ active: selectedWeekdayTab === d.id, weekend: d.isWeekend }"
            @click="emit('update:selectedWeekdayTab', d.id)"
          >
            {{ d.label }}
          </button>
        </div>

        <!-- Bulk Price Setup Section -->
        <div class="bulk-pricing-section">
          <h4 class="section-title">
            <span class="material-symbols-outlined icon-16">tune</span>
            <span>{{ t('admin.manage.bulkPricingSectionTitle') }}</span>
          </h4>
          <div class="bulk-inputs-grid">
            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.applyScopeLabel') }}</label>
              <select
                :value="bulkScope"
                class="form-input"
                @change="emit('update:bulkScope', ($event.target as HTMLSelectElement).value as any)"
              >
                <option value="current">{{ t('admin.manage.scopeCurrentDay') }}</option>
                <option value="weekdays">{{ t('admin.manage.scopeWeekdays') }}</option>
                <option value="weekends">{{ t('admin.manage.scopeWeekends') }}</option>
                <option value="all">{{ t('admin.manage.scopeAllDays') }}</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.fromHourLabel') }}</label>
              <select
                :value="bulkStartHour"
                class="form-input"
                @change="emit('update:bulkStartHour', Number(($event.target as HTMLSelectElement).value))"
              >
                <option v-for="h in 18" :key="h" :value="h + 4">
                  {{ String(h + 4).padStart(2, '0') }}:00
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.toHourLabel') }}</label>
              <select
                :value="bulkEndHour"
                class="form-input"
                @change="emit('update:bulkEndHour', Number(($event.target as HTMLSelectElement).value))"
              >
                <option v-for="h in 18" :key="h" :value="h + 5">
                  {{ String(h + 5).padStart(2, '0') }}:00
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.hourlyRateLabel') }}</label>
              <input
                :value="bulkPrice"
                type="number"
                step="10000"
                class="form-input font-mono"
                @input="emit('update:bulkPrice', Number(($event.target as HTMLInputElement).value) || 0)"
              />
            </div>

            <button
              type="button"
              class="btn-apply-bulk"
              :disabled="bulkSaving"
              @click="emit('applyBulk')"
            >
              <span v-if="bulkSaving">{{ t('admin.manage.applyingBulk') }}</span>
              <span v-else>{{ t('admin.manage.applyBulkPriceBtn') }}</span>
            </button>
          </div>
        </div>

        <!-- Slots Matrices by Period -->
        <div class="slots-matrices-container">
          <div class="period-group">
            <h5 class="period-heading">
              <span class="material-symbols-outlined icon-16">wb_twilight</span>
              <span>{{ t('admin.manage.morningSlotsTitle', { count: morningSlots.length }) }}</span>
            </h5>
            <div class="period-slots-grid">
              <div v-for="slot in morningSlots" :key="slot.id" class="slot-pill">
                <span class="slot-time font-mono">{{ slot.starts_at }} - {{ slot.ends_at }}</span>
                <span class="slot-price font-mono">{{ formatCurrency(slot.price) }}</span>
                <button type="button" class="btn-del-slot" @click="emit('deleteSlot', slot.id)">✕</button>
              </div>
            </div>
          </div>

          <div class="period-group">
            <h5 class="period-heading">
              <span class="material-symbols-outlined icon-16">light_mode</span>
              <span>{{ t('admin.manage.afternoonSlotsTitle', { count: afternoonSlots.length }) }}</span>
            </h5>
            <div class="period-slots-grid">
              <div v-for="slot in afternoonSlots" :key="slot.id" class="slot-pill">
                <span class="slot-time font-mono">{{ slot.starts_at }} - {{ slot.ends_at }}</span>
                <span class="slot-price font-mono">{{ formatCurrency(slot.price) }}</span>
                <button type="button" class="btn-del-slot" @click="emit('deleteSlot', slot.id)">✕</button>
              </div>
            </div>
          </div>

          <div class="period-group highlight-peak">
            <h5 class="period-heading">
              <span class="material-symbols-outlined icon-16 text-pink">local_fire_department</span>
              <span>{{ t('admin.manage.peakSlotsTitle', { count: peakSlots.length }) }}</span>
            </h5>
            <div class="period-slots-grid">
              <div v-for="slot in peakSlots" :key="slot.id" class="slot-pill">
                <span class="slot-time font-mono">{{ slot.starts_at }} - {{ slot.ends_at }}</span>
                <span class="slot-price text-pink font-mono">{{ formatCurrency(slot.price) }}</span>
                <button type="button" class="btn-del-slot" @click="emit('deleteSlot', slot.id)">✕</button>
              </div>
            </div>
          </div>

          <div v-if="nightSlots.length" class="period-group">
            <h5 class="period-heading">
              <span class="material-symbols-outlined icon-16">bedtime</span>
              <span>{{ t('admin.manage.nightSlotsTitle', { count: nightSlots.length }) }}</span>
            </h5>
            <div class="period-slots-grid">
              <div v-for="slot in nightSlots" :key="slot.id" class="slot-pill">
                <span class="slot-time font-mono">{{ slot.starts_at }} - {{ slot.ends_at }}</span>
                <span class="slot-price font-mono">{{ formatCurrency(slot.price) }}</span>
                <button type="button" class="btn-del-slot" @click="emit('deleteSlot', slot.id)">✕</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer pricing-modal-footer">
        <div class="pricing-stats-summary">
          <span>{{ t('admin.manage.totalSlotsSummary', { count: pricingStats.count }) }}</span> · 
          <span>{{ t('admin.manage.minPriceSummary', { amount: formatCurrency(pricingStats.min) }) }}</span> · 
          <span>{{ t('admin.manage.maxPriceSummary', { amount: formatCurrency(pricingStats.max) }) }}</span> · 
          <span>{{ t('admin.manage.avgPriceSummary', { amount: formatCurrency(pricingStats.avg) }) }}</span>
        </div>
        <button type="button" class="btn-primary" @click="emit('close')">{{ t('admin.manage.finishCloseBtn') }}</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.pricing-modal-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-height: 75vh;
  overflow-y: auto;
}

.weekday-tabs-row {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.weekday-tab-btn {
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.weekday-tab-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.weekday-tab-btn.weekend {
  border-color: rgba(245, 158, 11, 0.4);
}

.bulk-pricing-section {
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.section-title {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 12px;
  font-size: 13px;
  font-weight: 700;
}

.bulk-inputs-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr) auto;
  gap: 12px;
  align-items: end;
}

.btn-apply-bulk {
  padding: 10px 18px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
}

.slots-matrices-container {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.period-group {
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.period-group.highlight-peak {
  border-color: rgba(236, 72, 153, 0.4);
  background: rgba(236, 72, 153, 0.02);
}

.period-heading {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 10px;
  font-size: 13px;
  font-weight: 700;
}

.period-slots-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 8px;
}

.slot-pill {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 12px;
}

.slot-time {
  font-weight: 600;
}

.slot-price {
  font-weight: 700;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.btn-del-slot {
  border: 0;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  padding: 2px 4px;
  font-size: 11px;
}

.btn-del-slot:hover {
  color: #ef4444;
}

.pricing-modal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.pricing-stats-summary {
  font-size: 12px;
  color: var(--muted);
}

.alert-banner-success {
  padding: 8px 12px;
  border-radius: 8px;
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  font-size: 13px;
}

.alert-banner-error {
  padding: 8px 12px;
  border-radius: 8px;
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  font-size: 13px;
}

.btn-primary {
  padding: 10px 24px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

.icon-16 {
  font-size: 16px;
}
</style>

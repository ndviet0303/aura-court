<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency, resolveLocalizedText } from '@/i18n'
import { AuraButton, SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { PriceRuleItem } from '../../types/pricing'
import type { Venue, Court } from '@/features/court/types/court'
import PricingRuleSkeleton from './PricingRuleSkeleton.vue'

const props = defineProps<{
  rules: PriceRuleItem[]
  venues: Venue[]
  courts: Court[]
  loading?: boolean
  searchQuery: string
  selectedVenueId: number | 'all'
  selectedCourtId: number | 'all'
  selectedTypeFilter: string
  selectedStatusFilter: string
}>()

const emit = defineEmits<{
  (e: 'update:searchQuery', val: string): void
  (e: 'update:selectedVenueId', val: number | 'all'): void
  (e: 'update:selectedCourtId', val: number | 'all'): void
  (e: 'update:selectedTypeFilter', val: string): void
  (e: 'update:selectedStatusFilter', val: string): void
  (e: 'createRule'): void
  (e: 'editRule', rule: PriceRuleItem): void
  (e: 'toggleActive', rule: PriceRuleItem): void
  (e: 'deleteRule', id: number): void
  (e: 'clearFilters'): void
}>()

const { t } = useI18n()

// Options for dropdowns
const venueOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.pricing.allVenues') },
  ...props.venues.map((v) => ({
    value: v.id,
    label: resolveLocalizedText(v.name),
    icon: 'location_on',
  })),
])

const availableCourts = computed(() => {
  if (props.selectedVenueId !== 'all') {
    return props.courts.filter((c) => (c.venue_id || c.venue?.id) === props.selectedVenueId)
  }
  return props.courts
})

const courtOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.pricing.allCourts') },
  ...availableCourts.value.map((c) => ({
    value: c.id,
    label: resolveLocalizedText(c.name),
    icon: 'sports_tennis',
  })),
])

const typeOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.pricing.allRuleTypes') },
  { value: 'peak', label: t('admin.pricing.typePeakOption'), icon: 'trending_up' },
  { value: 'holiday', label: t('admin.pricing.typeHolidayOption'), icon: 'celebration' },
  { value: 'weekly', label: t('admin.pricing.typeWeeklyOption'), icon: 'calendar_view_week' },
  { value: 'standard', label: t('admin.pricing.typeStandardOption'), icon: 'check_circle' },
  { value: 'base', label: t('admin.pricing.typeBaseOption'), icon: 'layers' },
  { value: 'date_exception', label: t('admin.pricing.typeDateExceptionOption'), icon: 'event_busy' },
  { value: 'surge', label: t('admin.pricing.typeSurgeOption'), icon: 'bolt' },
  { value: 'special_event', label: t('admin.pricing.typeSpecialEventOption'), icon: 'stars' },
])

const statusOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.pricing.allStatuses') },
  { value: 'active', label: t('admin.pricing.statusActive') },
  { value: 'inactive', label: t('admin.pricing.statusInactive') },
])

function formatWeekday(weekday?: number | null): string {
  if (!weekday) return ''
  const days: Record<number, string> = {
    1: t('admin.pricing.monday'),
    2: t('admin.pricing.tuesday'),
    3: t('admin.pricing.wednesday'),
    4: t('admin.pricing.thursday'),
    5: t('admin.pricing.friday'),
    6: t('admin.pricing.saturday'),
    7: t('admin.pricing.sunday'),
  }
  return days[weekday] || `Thứ ${weekday}`
}

function getTypeLabel(type: string): string {
  const typeMap: Record<string, string> = {
    base: t('admin.pricing.typeBaseOption'),
    standard: t('admin.pricing.typeStandardOption'),
    weekly: t('admin.pricing.typeWeeklyOption'),
    peak: t('admin.pricing.typePeakOption'),
    holiday: t('admin.pricing.typeHolidayOption'),
    date_exception: t('admin.pricing.typeDateExceptionOption'),
    surge: t('admin.pricing.typeSurgeOption'),
    special_event: t('admin.pricing.typeSpecialEventOption'),
    bad_weather: t('admin.pricing.typeBadWeatherOption'),
  }
  return typeMap[type] || type.toUpperCase()
}
</script>

<template>
  <div class="priority-stack-container">
    <!-- Header -->
    <div class="stack-header">
      <div class="header-text">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">tune</span>
          <h3 class="section-title">{{ t('admin.pricing.hierarchyTitle') }}</h3>
        </div>
        <p class="section-sub">{{ t('admin.pricing.hierarchySub') }}</p>
      </div>

      <AuraButton variant="primary" @click="emit('createRule')">
        <span class="material-symbols-outlined icon-18">add_circle</span>
        <span>{{ t('admin.pricing.addNewRuleBtn') }}</span>
      </AuraButton>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="rules-toolbar">
      <div class="search-wrap">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          :value="searchQuery"
          type="text"
          class="rules-search-input"
          :placeholder="t('admin.pricing.searchRulesPlaceholder')"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="clear-search-btn"
          @click="emit('update:searchQuery', '')"
        >
          ✕
        </button>
      </div>

      <div class="filter-controls">
        <!-- Venue filter -->
        <div class="filter-dropdown-wrap">
          <SelectDropdown
            :model-value="selectedVenueId"
            :options="venueOptions"
            variant="bordered"
            @update:model-value="emit('update:selectedVenueId', $event === 'all' ? 'all' : Number($event))"
          />
        </div>

        <!-- Court filter -->
        <div class="filter-dropdown-wrap">
          <SelectDropdown
            :model-value="selectedCourtId"
            :options="courtOptions"
            variant="bordered"
            @update:model-value="emit('update:selectedCourtId', $event === 'all' ? 'all' : Number($event))"
          />
        </div>

        <!-- Rule type filter -->
        <div class="filter-dropdown-wrap">
          <SelectDropdown
            :model-value="selectedTypeFilter"
            :options="typeOptions"
            variant="bordered"
            @update:model-value="emit('update:selectedTypeFilter', String($event))"
          />
        </div>

        <!-- Status filter -->
        <div class="filter-dropdown-wrap">
          <SelectDropdown
            :model-value="selectedStatusFilter"
            :options="statusOptions"
            variant="bordered"
            @update:model-value="emit('update:selectedStatusFilter', String($event))"
          />
        </div>
      </div>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="rules-stack-list">
      <PricingRuleSkeleton v-for="i in 4" :key="i" />
    </div>

    <!-- Empty State -->
    <div v-else-if="rules.length === 0" class="empty-rules-state">
      <span class="material-symbols-outlined empty-icon">rule_folder</span>
      <h4 class="empty-title">{{ t('admin.pricing.noRulesFound') }}</h4>
      <p class="empty-sub">{{ t('admin.pricing.noRulesFoundSub') }}</p>
      <div class="empty-actions">
        <AuraButton
          v-if="searchQuery || selectedVenueId !== 'all' || selectedCourtId !== 'all' || selectedTypeFilter !== 'all' || selectedStatusFilter !== 'all'"
          variant="outline"
          @click="emit('clearFilters')"
        >
          <span class="material-symbols-outlined icon-16">filter_alt_off</span>
          <span>{{ t('admin.pricing.clearAllFilters') }}</span>
        </AuraButton>
        <AuraButton variant="primary" @click="emit('createRule')">
          <span class="material-symbols-outlined icon-18">add_circle</span>
          <span>{{ t('admin.pricing.addNewRuleBtn') }}</span>
        </AuraButton>
      </div>
    </div>

    <!-- Rules Stack List -->
    <div v-else class="rules-stack-list">
      <div
        v-for="rule in rules"
        :key="rule.id"
        class="rule-card"
        :class="[`type-${rule.type}`, { 'rule-inactive': !rule.is_active }]"
      >
        <!-- Priority badge -->
        <div class="priority-badge" :title="t('admin.pricing.priorityTooltip', { rank: rule.priority })">
          <span class="priority-num">#{{ rule.priority }}</span>
        </div>

        <!-- Details -->
        <div class="rule-details">
          <div class="rule-title-row">
            <strong class="rule-name">
              {{ rule.name || (rule.court ? resolveLocalizedText(rule.court.name) : getTypeLabel(rule.type)) }}
            </strong>
            <span class="rule-type-badge" :class="`badge-${rule.type}`">
              {{ getTypeLabel(rule.type) }}
            </span>
            <span v-if="rule.court" class="rule-scope-badge">
              <span class="material-symbols-outlined icon-12">sports_tennis</span>
              {{ resolveLocalizedText(rule.court.name) }}
            </span>
            <span v-else-if="rule.venue" class="rule-scope-badge">
              <span class="material-symbols-outlined icon-12">location_on</span>
              {{ resolveLocalizedText(rule.venue.name) }}
            </span>
            <span v-else class="rule-scope-badge scope-global">
              <span class="material-symbols-outlined icon-12">public</span>
              {{ t('admin.pricing.scopeAllVenues') }}
            </span>
          </div>

          <div class="rule-meta">
            <!-- Time Window -->
            <span v-if="rule.starts_at && rule.ends_at" class="meta-item font-mono">
              <span class="material-symbols-outlined icon-14">schedule</span>
              {{ rule.starts_at }} - {{ rule.ends_at }}
            </span>
            <span v-else class="meta-item">
              <span class="material-symbols-outlined icon-14">all_inclusive</span>
              {{ t('admin.pricing.allDayHours') }}
            </span>

            <!-- Weekday or Date Exception -->
            <span v-if="rule.weekday" class="meta-item">
              <span class="material-symbols-outlined icon-14">calendar_today</span>
              {{ formatWeekday(rule.weekday) }}
            </span>
            <span v-else-if="rule.exception_date" class="meta-item font-mono text-amber">
              <span class="material-symbols-outlined icon-14">event</span>
              {{ rule.exception_date }}
            </span>
            <span v-if="rule.effective_from || rule.effective_until" class="meta-item font-mono">
              <span class="material-symbols-outlined icon-14">date_range</span>
              {{ rule.effective_from?.slice(0, 10) || '…' }} – {{ rule.effective_until?.slice(0, 10) || '…' }}
            </span>
            <span class="meta-item">{{ t('admin.pricing.versionLabel', { version: rule.version }) }}</span>

            <!-- Price / Adjustment -->
            <span class="meta-item price-tag font-mono">
              <span class="material-symbols-outlined icon-14">payments</span>
              <strong>{{ formatCurrency(rule.price) }}</strong>
              <small class="slot-unit">/ {{ t('admin.pricing.slotUnit') }}</small>
            </span>
          </div>
        </div>

        <!-- Controls -->
        <div class="rule-actions">
          <!-- Active Switch -->
          <label class="toggle-switch" :title="rule.is_active ? t('admin.pricing.statusActive') : t('admin.pricing.statusInactive')">
            <input
              type="checkbox"
              :checked="rule.is_active"
              @change="emit('toggleActive', rule)"
            />
            <span class="slider round"></span>
          </label>

          <!-- Edit Button -->
          <button
            type="button"
            class="btn-action btn-edit"
            :title="t('admin.pricing.editRuleTitle')"
            @click="emit('editRule', rule)"
          >
            <span class="material-symbols-outlined icon-16">edit</span>
            <span>{{ t('common.edit') }}</span>
          </button>

          <!-- Delete / Deactivate Button -->
          <button
            type="button"
            class="btn-action btn-delete"
            :title="t('common.delete')"
            @click="emit('deleteRule', rule.id)"
          >
            <span class="material-symbols-outlined icon-16">delete_outline</span>
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
  gap: 16px;
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
  gap: 14px;
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

.section-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.section-sub {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.rules-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border-radius: 12px;
  border: 1px solid var(--outline);
}

.search-wrap {
  flex: 1 1 240px;
  min-width: 220px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.search-icon {
  font-size: 18px;
  color: var(--muted);
}

.rules-search-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  width: 100%;
  color: var(--ink);
}

.clear-search-btn {
  background: transparent;
  border: none;
  color: var(--muted);
  cursor: pointer;
  font-size: 12px;
  padding: 2px 4px;
}

.filter-controls {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  flex: 2 1 auto;
  justify-content: flex-end;
}

.filter-dropdown-wrap {
  min-width: 140px;
  flex: 1 1 140px;
  max-width: 200px;
}

.rules-stack-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.rule-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 18px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}

.rule-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
}

.rule-card.rule-inactive {
  opacity: 0.6;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.priority-badge {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: var(--pink-dark, #db2777);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 14px;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(219, 39, 119, 0.25);
}

.rule-details {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}

.rule-title-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.rule-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}

.rule-type-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
  text-transform: capitalize;
}

.badge-peak {
  background: rgba(236, 72, 153, 0.12);
  color: var(--pink-dark, #db2777);
}

.badge-holiday, .badge-special_event {
  background: rgba(245, 158, 11, 0.12);
  color: #d97706;
}

.badge-weekly {
  background: rgba(139, 92, 246, 0.12);
  color: #7c3aed;
}

.badge-standard, .badge-base {
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
}

.badge-surge, .badge-bad_weather {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
}

.badge-date_exception {
  background: rgba(59, 130, 246, 0.12);
  color: #2563eb;
}

.rule-scope-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--muted);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  padding: 2px 8px;
  border-radius: 6px;
}

.scope-global {
  color: #3b82f6;
  background: rgba(59, 130, 246, 0.08);
}

.rule-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
  font-size: 12px;
  color: var(--muted);
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.price-tag {
  color: var(--pink-dark, #db2777);
}

.price-tag strong {
  font-size: 13px;
  font-weight: 800;
}

.slot-unit {
  font-size: 11px;
  color: var(--muted);
}

.text-amber {
  color: #d97706;
}

.rule-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-action:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
}

.btn-edit:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.btn-delete {
  padding: 6px 8px;
  color: #ef4444;
}

.btn-delete:hover {
  background: rgba(239, 68, 68, 0.08);
  border-color: #ef4444;
}

/* Switch */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 38px;
  height: 22px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--outline);
  transition: 0.2s;
  border-radius: 22px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 16px;
  width: 16px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.2s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: #10b981;
}

input:checked + .slider:before {
  transform: translateX(16px);
}

/* Empty State */
.empty-rules-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px dashed var(--outline);
  text-align: center;
}

.empty-icon {
  font-size: 48px;
  color: var(--muted);
  margin-bottom: 12px;
}

.empty-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
}

.empty-sub {
  margin: 6px 0 16px;
  font-size: 13px;
  color: var(--muted);
}

.empty-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.icon-12 { font-size: 12px; }
.icon-14 { font-size: 14px; }
.icon-16 { font-size: 16px; }
.icon-18 { font-size: 18px; }
</style>

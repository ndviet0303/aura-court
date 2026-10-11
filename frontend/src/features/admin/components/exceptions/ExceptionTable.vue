<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency, resolveLocalizedText } from '@/i18n'
import { SelectDropdown, DatePicker, SkeletonLoader, AuraButton } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { FinancialException, ExceptionFilterTab } from '../../types/exceptions'

const props = defineProps<{
  items: FinancialException[]
  activeTab: ExceptionFilterTab
  totalCount: number
  criticalCount: number
  loading?: boolean
  showAdvancedFilters?: boolean
  filterDate?: string | null
  filterSeverity?: string
  filterStatus?: string
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: ExceptionFilterTab): void
  (e: 'update:filterDate', date: string | null): void
  (e: 'update:filterSeverity', sev: string): void
  (e: 'update:filterStatus', status: string): void
  (e: 'toggle-filters'): void
  (e: 'reset-filters'): void
  (e: 'view-detail', item: FinancialException): void
  (e: 'override', item: FinancialException): void
}>()

const { t, te } = useI18n()

const severityOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.exceptions.severityAll') },
  { value: 'critical', label: t('admin.exceptions.severityCritical'), icon: 'warning' },
  { value: 'medium', label: t('admin.exceptions.severityMedium'), icon: 'priority_high' },
  { value: 'low', label: t('admin.exceptions.severityLow'), icon: 'info' },
])

const statusOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.exceptions.statusAll') },
  { value: 'open', label: t('admin.exceptions.statusOpen'), icon: 'pending_actions' },
  { value: 'resolved', label: t('admin.exceptions.statusResolved'), icon: 'check_circle' },
])

function formatDetectedTime(isoString?: string): string {
  if (!isoString) return '--'
  try {
    const d = new Date(isoString)
    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const hours = String(d.getHours()).padStart(2, '0')
    const mins = String(d.getMinutes()).padStart(2, '0')
    return `${day}/${month}, ${hours}:${mins}`
  } catch {
    return isoString
  }
}

function getUserInitials(name?: string): string {
  if (!name || typeof name !== 'string') return 'A'
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return 'A'
  if (parts.length === 1 && parts[0]) return parts[0].substring(0, 2).toUpperCase()
  const first = parts[0]?.[0] || 'A'
  const last = parts[parts.length - 1]?.[0] || ''
  return (first + last).toUpperCase()
}

function localizeDescription(item: FinancialException): string {
  if (item.type && te(`admin.exceptions.descriptions.${item.type}`)) {
    return t(`admin.exceptions.descriptions.${item.type}`)
  }
  const desc = resolveLocalizedText(item.description)
  if (desc.includes('Vượt hạn mức') || desc.includes('Deposit limit'))
    return t('admin.exceptions.descriptions.limit_exceeded')
  if (desc.includes('Lệch đối soát') || desc.includes('settlement mismatch'))
    return t('admin.exceptions.descriptions.settlement_mismatch')
  if (desc.includes('Hoàn tiền hủy') || desc.includes('cancellation refund'))
    return t('admin.exceptions.descriptions.late_refund')
  if (desc.includes('săn voucher') || desc.includes('voucher hunting'))
    return t('admin.exceptions.descriptions.voucher_hunting')
  if (desc.includes('Lệch cọc') || desc.includes('deposit hold mismatch'))
    return t('admin.exceptions.descriptions.deposit_hold_mismatch')
  if (desc.includes('Trùng lặp') || desc.includes('Duplicate QR'))
    return t('admin.exceptions.descriptions.duplicate_charge')
  if (desc.includes('ngập sân') || desc.includes('court flooding'))
    return t('admin.exceptions.descriptions.court_flooded_refund')
  return desc
}

function localizeShift(shift?: string, type?: string): string {
  if (!shift && !type) return '--'
  const text = shift || type || ''
  if (text.includes('Sáng') || text.includes('Morning')) return t('admin.exceptions.shifts.morning')
  if (text.includes('Chiều') || text.includes('Afternoon'))
    return t('admin.exceptions.shifts.afternoon')
  if (text.includes('Tối') || text.includes('Evening')) return t('admin.exceptions.shifts.evening')
  if (type && te(`admin.exceptions.descriptions.${type}`))
    return t(`admin.exceptions.descriptions.${type}`)
  return text
}

function localizeMemberTier(tier?: string): string {
  if (!tier) return ''
  if (tier.includes('Vàng') || tier.includes('Gold')) return t('admin.exceptions.memberTiers.gold')
  if (tier.includes('Kim Cương') || tier.includes('Diamond'))
    return t('admin.exceptions.memberTiers.diamond')
  if (tier.includes('Bạc') || tier.includes('Silver'))
    return t('admin.exceptions.memberTiers.silver')
  if (tier.includes('vãng lai') || tier.includes('Guest'))
    return t('admin.exceptions.memberTiers.guest')
  if (tier.includes('mới') || tier.includes('New')) return t('admin.exceptions.memberTiers.new')
  return tier
}
</script>

<template>
  <section class="table-section">
    <!-- Filter Tabs & Advance Filter Toggle -->
    <div class="tab-filter-bar">
      <div class="tab-list">
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'all' }"
          @click="emit('update:activeTab', 'all')"
        >
          {{ t('admin.exceptions.allExceptionsTab', { count: totalCount }) }}
        </button>
        <button
          type="button"
          class="tab-btn btn-critical"
          :class="{ active: activeTab === 'critical' }"
          @click="emit('update:activeTab', 'critical')"
        >
          <span class="status-dot-critical">●</span>
          <span>{{ t('admin.exceptions.criticalTab', { count: criticalCount }) }}</span>
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'refund' }"
          @click="emit('update:activeTab', 'refund')"
        >
          {{ t('admin.exceptions.delayedRefundTab') }}
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'deposit' }"
          @click="emit('update:activeTab', 'deposit')"
        >
          {{ t('admin.exceptions.depositAnomalyTab') }}
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'deposit_hold' }"
          @click="emit('update:activeTab', 'deposit_hold')"
        >
          {{ t('admin.exceptions.depositHoldTab') }}
        </button>
      </div>

      <div class="filter-actions">
        <button
          type="button"
          class="btn-advanced-filter"
          :class="{ 'is-active': showAdvancedFilters }"
          @click="emit('toggle-filters')"
        >
          <span class="material-symbols-outlined icon-18">tune</span>
          <span>{{ t('admin.exceptions.filterToggle') }}</span>
        </button>
      </div>
    </div>

    <!-- Advanced Filter Bar (Expandable) -->
    <transition name="slide-fade">
      <div v-if="showAdvancedFilters" class="advanced-filter-drawer">
        <div class="filter-grid">
          <div class="filter-field">
            <label class="filter-label">{{ t('admin.exceptions.filterDate') }}</label>
            <DatePicker
              :modelValue="filterDate || ''"
              :placeholder="t('admin.exceptions.filterDate')"
              @update:modelValue="emit('update:filterDate', $event || null)"
            />
          </div>

          <div class="filter-field">
            <label class="filter-label">{{ t('admin.exceptions.filterSeverity') }}</label>
            <SelectDropdown
              :modelValue="filterSeverity || 'all'"
              :options="severityOptions"
              variant="bordered"
              @update:modelValue="emit('update:filterSeverity', String($event))"
            />
          </div>

          <div class="filter-field">
            <label class="filter-label">{{ t('admin.exceptions.filterStatus') }}</label>
            <SelectDropdown
              :modelValue="filterStatus || 'all'"
              :options="statusOptions"
              variant="bordered"
              @update:modelValue="emit('update:filterStatus', String($event))"
            />
          </div>

          <div class="filter-field filter-field-btn">
            <AuraButton variant="outline" size="sm" @click="emit('reset-filters')">
              <span class="material-symbols-outlined icon-16">restart_alt</span>
              <span>{{ t('admin.exceptions.clearFilter') }}</span>
            </AuraButton>
          </div>
        </div>
      </div>
    </transition>

    <!-- Table Container -->
    <div class="exceptions-table-wrapper">
      <table class="aura-data-table">
        <thead>
          <tr>
            <th style="width: 140px">{{ t('admin.exceptions.hdrTxnCode') }}</th>
            <th style="width: 170px">{{ t('admin.exceptions.hdrTimeAndShift') }}</th>
            <th style="width: 220px">{{ t('admin.exceptions.hdrCustomerAccount') }}</th>
            <th style="width: 130px" class="text-right">{{ t('admin.exceptions.hdrAmount') }}</th>
            <th>{{ t('admin.exceptions.hdrExceptionType') }}</th>
            <th style="width: 110px">{{ t('admin.exceptions.hdrRiskLevel') }}</th>
            <th style="width: 170px" class="text-right">{{ t('admin.exceptions.hdrActions') }}</th>
          </tr>
        </thead>
        <tbody>
          <!-- Skeleton Loading Rows -->
          <template v-if="loading">
            <tr v-for="i in 5" :key="`table-skel-${i}`">
              <td><SkeletonLoader height="16px" width="90px" borderRadius="4px" /></td>
              <td>
                <SkeletonLoader height="14px" width="100px" borderRadius="4px" class="mb-1" />
                <SkeletonLoader height="12px" width="70px" borderRadius="4px" />
              </td>
              <td>
                <div class="user-cell">
                  <SkeletonLoader height="32px" width="32px" borderRadius="50%" />
                  <div>
                    <SkeletonLoader height="14px" width="90px" borderRadius="4px" class="mb-1" />
                    <SkeletonLoader height="12px" width="60px" borderRadius="4px" />
                  </div>
                </div>
              </td>
              <td class="text-right">
                <SkeletonLoader height="16px" width="80px" borderRadius="4px" />
              </td>
              <td><SkeletonLoader height="22px" width="120px" borderRadius="6px" /></td>
              <td><SkeletonLoader height="22px" width="70px" borderRadius="12px" /></td>
              <td class="text-right">
                <div class="action-buttons">
                  <SkeletonLoader height="28px" width="80px" borderRadius="6px" />
                  <SkeletonLoader height="28px" width="80px" borderRadius="6px" />
                </div>
              </td>
            </tr>
          </template>

          <!-- Real Data Rows -->
          <template v-else>
            <tr
              v-for="item in items"
              :key="item.id"
              :class="{ 'row-resolved': item.status === 'resolved' }"
            >
              <!-- TXN Code -->
              <td class="code-cell font-mono">{{ item.reference }}</td>

              <!-- Time & Shift -->
              <td>
                <div class="time-main font-mono">{{ formatDetectedTime(item.detected_at) }}</div>
                <div class="time-sub">{{ localizeShift(item.metadata?.shift, item.type) }}</div>
              </td>

              <!-- Customer Account -->
              <td>
                <div class="user-cell">
                  <div class="user-avatar-initial">
                    {{
                      getUserInitials(
                        item.metadata?.user?.name ||
                          item.resolver?.name ||
                          t('admin.schedule.systemUser'),
                      )
                    }}
                  </div>
                  <div>
                    <div class="user-name">
                      {{
                        resolveLocalizedText(
                          item.metadata?.user?.name ||
                            item.resolver?.name ||
                            t('admin.schedule.systemUser'),
                        )
                      }}
                    </div>
                    <div class="user-sub font-mono">
                      <span>{{
                        item.metadata?.user?.id ||
                        (item.resolver?.id ? `UID-${item.resolver.id}` : 'SYS')
                      }}</span>
                      <span v-if="item.metadata?.user?.tier" class="tier-tag">
                        · {{ localizeMemberTier(item.metadata.user.tier) }}</span
                      >
                    </div>
                  </div>
                </div>
              </td>

              <!-- Amount -->
              <td class="text-right font-mono font-bold amount-cell">
                {{ formatCurrency(item.amount) }}
              </td>

              <!-- Exception Type -->
              <td>
                <span class="reason-pill">{{ localizeDescription(item) }}</span>
              </td>

              <!-- Risk Level -->
              <td>
                <span
                  class="risk-badge"
                  :class="{
                    'risk-critical': item.severity === 'critical',
                    'risk-medium': item.severity === 'medium',
                    'risk-low': item.severity === 'low',
                  }"
                >
                  <span class="material-symbols-outlined icon-14">
                    {{
                      item.severity === 'critical'
                        ? 'warning'
                        : item.severity === 'medium'
                          ? 'priority_high'
                          : 'info'
                    }}
                  </span>
                  <span>
                    {{
                      item.severity === 'critical'
                        ? t('admin.exceptions.riskHigh')
                        : item.severity === 'medium'
                          ? t('admin.exceptions.riskMedium')
                          : t('admin.exceptions.riskLow')
                    }}
                  </span>
                </span>
              </td>

              <!-- Actions -->
              <td class="text-right">
                <div class="action-buttons">
                  <button
                    type="button"
                    class="btn-action-detail"
                    :title="t('admin.exceptions.viewDetailBtn')"
                    @click="emit('view-detail', item)"
                  >
                    <span class="material-symbols-outlined icon-14">visibility</span>
                    <span>{{ t('admin.exceptions.viewDetailBtn') }}</span>
                  </button>

                  <span v-if="item.status === 'resolved'" class="status-done">
                    <span class="material-symbols-outlined icon-16">done_all</span>
                    <span>{{ t('admin.exceptions.statusDone') }}</span>
                  </span>
                  <button
                    v-else
                    type="button"
                    class="btn-action-override"
                    :title="t('admin.exceptions.overrideBtn')"
                    @click="emit('override', item)"
                  >
                    <span class="material-symbols-outlined icon-14">gavel</span>
                    <span>{{ t('admin.exceptions.overrideBtn') }}</span>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Empty Row -->
            <tr v-if="items.length === 0">
              <td colspan="7" class="empty-cell">
                <div class="empty-box">
                  <span class="material-symbols-outlined icon-36">check_circle</span>
                  <p>{{ t('admin.exceptions.noExceptionsFound') }}</p>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.table-section {
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.tab-filter-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  border-bottom: 1px solid var(--outline, #e1e3e4);
  background: var(--surface-container-low, #f8f9fa);
  gap: 12px;
  flex-wrap: wrap;
}

.tab-list {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  align-items: center;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.tab-list::-webkit-scrollbar {
  display: none;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 999px;
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
}

.tab-btn:hover {
  background: var(--surface-container, #edeeef);
  color: var(--ink, #191c1d);
}

.status-dot-critical {
  color: #ef4444;
  font-size: 10px;
}

.tab-btn.active {
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
  border-color: var(--primary, #78555e);
}

.filter-actions {
  display: flex;
  align-items: center;
}

.btn-advanced-filter {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 999px;
  padding: 6px 14px;
  font-size: 12px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-advanced-filter:hover,
.btn-advanced-filter.is-active {
  background: var(--surface-container-high, #e7e8e9);
  border-color: var(--primary, #78555e);
  color: var(--primary, #78555e);
}

.advanced-filter-drawer {
  padding: 16px;
  background: var(--surface, #ffffff);
  border-bottom: 1px solid var(--outline, #e1e3e4);
}

.filter-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) auto;
  gap: 14px;
  align-items: flex-end;
}

.filter-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.filter-field-btn {
  margin-bottom: 2px;
}

.filter-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
}

.exceptions-table-wrapper {
  overflow-x: auto;
  scrollbar-width: thin;
  scrollbar-color: var(--outline, rgba(140, 140, 140, 0.25)) transparent;
}

.exceptions-table-wrapper::-webkit-scrollbar {
  height: 6px;
}

.exceptions-table-wrapper::-webkit-scrollbar-track {
  background: transparent;
}

.exceptions-table-wrapper::-webkit-scrollbar-thumb {
  background: var(--outline, rgba(140, 140, 140, 0.25));
  border-radius: 999px;
}

.exceptions-table-wrapper::-webkit-scrollbar-thumb:hover {
  background: var(--muted, rgba(140, 140, 140, 0.45));
}

.aura-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.aura-data-table th {
  padding: 14px 16px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
  border-bottom: 1px solid var(--outline, #e1e3e4);
  background: var(--surface-container-lowest, #ffffff);
  text-align: left;
  white-space: nowrap;
}

.aura-data-table td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--outline-variant, #f3f4f5);
  vertical-align: middle;
}

.aura-data-table tr:hover {
  background: var(--surface-container-low, #f8f9fa);
}

.row-resolved {
  opacity: 0.55;
  background: transparent;
}

.code-cell {
  font-weight: 700;
  color: var(--ink, #191c1d);
  letter-spacing: 0.02em;
}

.time-main {
  font-weight: 600;
  color: var(--ink, #191c1d);
}

.time-sub {
  font-size: 11px;
  color: var(--muted, #817476);
  margin-top: 2px;
}

.user-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-avatar-initial {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--surface-container-high, #dfe0e0);
  color: var(--ink, #191c1d);
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  flex-shrink: 0;
}

.user-name {
  font-weight: 600;
  color: var(--ink, #191c1d);
}

.user-sub {
  font-size: 11px;
  color: var(--muted, #817476);
  margin-top: 1px;
}

.tier-tag {
  color: var(--primary, #78555e);
  font-weight: 600;
}

.amount-cell {
  color: var(--ink, #191c1d);
  font-size: 14px;
}

.reason-pill {
  display: inline-block;
  background: var(--surface-container-high, #e7e8e9);
  border: 1px solid var(--outline, #e1e3e4);
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 12px;
  color: var(--on-surface-variant, #4f4446);
  font-weight: 500;
  max-width: 220px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.risk-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}

.risk-critical {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}

.risk-medium {
  background: rgba(245, 158, 11, 0.15);
  color: #f59e0b;
}

.risk-low {
  background: rgba(99, 102, 241, 0.15);
  color: #818cf8;
}

.action-buttons {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.btn-action-detail {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: transparent;
  border: 1px solid var(--outline, #e1e3e4);
  color: var(--ink, #191c1d);
  padding: 6px 10px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-action-detail:hover {
  background: var(--surface-container-high, #e7e8e9);
}

.btn-action-override {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
  border: none;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-action-override:hover {
  background: var(--primary, #78555e);
  color: #ffffff;
}

.status-done {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #10b981;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 6px;
  background: rgba(16, 185, 129, 0.1);
}

.empty-cell {
  text-align: center;
  padding: 48px 16px;
}

.empty-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: var(--muted, #817476);
}

.empty-box p {
  margin: 0;
  font-size: 14px;
}

.icon-14 {
  font-size: 14px;
}
.icon-16 {
  font-size: 16px;
}
.icon-18 {
  font-size: 18px;
}
.icon-36 {
  font-size: 36px;
  color: #10b981;
}
.mb-1 {
  margin-bottom: 4px;
}

/* Transitions */
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.25s ease-out;
}
.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateY(-8px);
  opacity: 0;
}
</style>

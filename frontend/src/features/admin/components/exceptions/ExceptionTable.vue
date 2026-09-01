<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency, resolveLocalizedText } from '@/i18n'
import type { FinancialException, ExceptionFilterTab } from '../../types/exceptions'

defineProps<{
  items: FinancialException[]
  activeTab: ExceptionFilterTab
  totalCount: number
  criticalCount: number
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: ExceptionFilterTab): void
  (e: 'override', item: FinancialException): void
}>()

const { t } = useI18n()
</script>

<template>
  <section class="table-section">
    <!-- Filter Tabs -->
    <div class="tab-filter-bar">
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

    <!-- Table Container -->
    <div class="exceptions-table-wrapper">
      <table class="aura-data-table">
        <thead>
          <tr>
            <th>{{ t('admin.exceptions.hdrTxnCode') }}</th>
            <th>{{ t('admin.exceptions.hdrTimeAndShift') }}</th>
            <th>{{ t('admin.exceptions.hdrCustomerAccount') }}</th>
            <th class="text-right">{{ t('admin.exceptions.hdrAmount') }}</th>
            <th>{{ t('admin.exceptions.hdrExceptionType') }}</th>
            <th>{{ t('admin.exceptions.hdrRiskLevel') }}</th>
            <th class="text-right">{{ t('admin.exceptions.hdrActions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id" :class="{ 'row-resolved': item.status === 'resolved' }">
            <td class="code-cell font-mono">{{ item.reference }}</td>
            <td>
              <div class="time-main font-mono">{{ item.detected_at }}</div>
              <div class="time-sub">{{ (item.metadata?.shift || item.type) }}</div>
            </td>
            <td>
              <div class="user-cell">
                <div class="user-avatar-initial">{{ (item.resolver?.name || t('admin.schedule.systemUser')).charAt(0) }}</div>
                <div>
                  <div class="user-name">{{ resolveLocalizedText(item.resolver?.name || t('admin.schedule.systemUser')) }}</div>
                  <div class="user-sub font-mono">{{ (item.resolver?.id || '') }} · {{ (item.metadata?.tier || '') }}</div>
                </div>
              </div>
            </td>
            <td class="text-right font-mono font-bold amount-cell">
              {{ formatCurrency(item.amount) }}
            </td>
            <td>
              <span class="reason-pill">{{ resolveLocalizedText(item.description) }}</span>
            </td>
            <td>
              <span
                class="risk-badge"
                :class="{
                  'risk-critical': item.severity === 'critical',
                  'risk-medium': item.severity === 'medium',
                  'risk-low': item.severity === 'low',
                }"
              >
                {{ item.severity === 'critical' ? t('admin.exceptions.riskHigh') : item.severity === 'medium' ? t('admin.exceptions.riskMedium') : t('admin.exceptions.riskLow') }}
              </span>
            </td>
            <td class="text-right">
              <div class="action-buttons">
                <span v-if="item.status === 'resolved'" class="status-done">
                  <span class="material-symbols-outlined icon-16">done_all</span>
                  <span>{{ t('admin.exceptions.statusDone') }}</span>
                </span>
                <button
                  v-else
                  type="button"
                  class="btn-override"
                  @click="emit('override', item)"
                >
                  {{ t('admin.exceptions.overrideBtn') }}
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="items.length === 0">
            <td colspan="7" class="empty-cell">{{ t('admin.exceptions.noExceptionsFound') }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.table-section {
  background: #ffffff;
  border: 1px solid #e1e3e4;
  border-radius: 16px;
  overflow: hidden;
}

.tab-filter-bar {
  display: flex;
  gap: 8px;
  padding: 16px;
  border-bottom: 1px solid #e1e3e4;
  overflow-x: auto;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 999px;
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 600;
  color: #5d5f5f;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
}

.status-dot-critical {
  color: #ef4444;
  font-size: 10px;
}

.tab-btn.active {
  background: var(--primary-container, #F8E8EC);
  color: var(--on-primary-container, #4e1827);
  border-color: var(--border, #e8e1d7);
}

.exceptions-table-wrapper {
  overflow-x: auto;
}

.aura-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.aura-data-table th {
  padding: 14px 16px;
  font-weight: 600;
  color: #5d5f5f;
  border-bottom: 1px solid #e1e3e4;
  background: #f8f9fa;
  text-align: left;
}

.aura-data-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #f3f4f5;
  vertical-align: middle;
}

.row-resolved {
  opacity: 0.6;
  background: #fafafa;
}

.code-cell {
  font-weight: 600;
  color: #191c1d;
}

.time-main {
  font-weight: 500;
  color: #191c1d;
}
.time-sub {
  font-size: 11px;
  color: #817476;
}

.user-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.user-avatar-initial {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #dfe0e0;
  color: #191c1d;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
}

.user-name {
  font-weight: 600;
  color: #191c1d;
}
.user-sub {
  font-size: 11px;
  color: #817476;
}

.amount-cell {
  color: #191c1d;
}

.reason-pill {
  display: inline-block;
  background: #f3f4f5;
  border: 1px solid #e1e3e4;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 12px;
  color: #4f4446;
}

.risk-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}

.risk-critical {
  background: #ffdad6;
  color: #ba1a1a;
}
.risk-medium {
  background: #fef3c7;
  color: #b45309;
}
.risk-low {
  background: #e0e7ff;
  color: #3730a3;
}

.btn-override {
  background: var(--primary-container, #F8E8EC);
  color: var(--on-primary-container, #4e1827);
  border: none;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s;
}

.btn-override:hover {
  opacity: 0.85;
}

.status-done {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #10b981;
  font-weight: 600;
}

.empty-cell {
  text-align: center;
  padding: 32px;
  color: #817476;
}

.icon-16 {
  font-size: 16px;
}
</style>

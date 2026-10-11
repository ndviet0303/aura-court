<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, DatePicker, SelectDropdown, type DropdownOption } from '@/shared/components'
import SkeletonLoader from '@/shared/components/feedback/SkeletonLoader.vue'
import type { AuditLogItem } from '../../composables/useAdminStaff'

const props = withDefaults(
  defineProps<{
    logs: AuditLogItem[]
    searchQuery?: string
    actionFilter?: string
    dateFilter?: string
    loading?: boolean
  }>(),
  {
    searchQuery: '',
    actionFilter: 'all',
    dateFilter: '',
    loading: false,
  },
)

const emit = defineEmits<{
  (e: 'update:searchQuery', val: string): void
  (e: 'update:actionFilter', val: string): void
  (e: 'update:dateFilter', val: string): void
  (e: 'refresh'): void
}>()

const { t } = useI18n()

const actionFilterOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.staff.auditFilterAll'), icon: 'list' },
  { value: 'auth', label: t('admin.staff.auditFilterAuth'), icon: 'lock' },
  { value: 'pricing', label: t('admin.staff.auditFilterPrice'), icon: 'payments' },
  { value: 'payment', label: t('admin.staff.auditFilterPayment'), icon: 'point_of_sale' },
  { value: 'staff', label: t('admin.staff.auditFilterStaff'), icon: 'group' },
])
</script>

<template>
  <div class="audit-logs-container">
    <!-- Header -->
    <div class="audit-header">
      <div class="title-with-icon">
        <span class="material-symbols-outlined card-title-icon">history</span>
        <div>
          <h3 class="audit-heading">{{ t('admin.staff.auditTitle') }}</h3>
          <p class="audit-sub">{{ t('admin.staff.auditSub') }}</p>
        </div>
      </div>

      <AuraButton variant="secondary" size="sm" :disabled="loading" @click="emit('refresh')">
        <span class="material-symbols-outlined icon-16" :class="{ 'spin-icon': loading }"
          >refresh</span
        >
        <span>{{ t('admin.staff.refreshBtn') }}</span>
      </AuraButton>
    </div>

    <!-- Toolbar Filters -->
    <div class="audit-toolbar">
      <div class="search-wrap">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          :value="searchQuery"
          type="text"
          class="audit-search-input"
          :placeholder="t('admin.staff.auditSearchPlaceholder')"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="clear-btn"
          @click="emit('update:searchQuery', '')"
        >
          ✕
        </button>
      </div>

      <div class="dropdown-filter-wrap">
        <SelectDropdown
          :model-value="actionFilter"
          :options="actionFilterOptions"
          variant="bordered"
          @update:model-value="emit('update:actionFilter', String($event))"
        />
      </div>

      <div class="date-filter-wrap">
        <DatePicker
          :model-value="dateFilter"
          :placeholder="t('admin.staff.auditDateFilter')"
          @update:model-value="emit('update:dateFilter', $event)"
        />
      </div>
    </div>

    <!-- Skeletons State -->
    <div v-if="loading" class="audit-items-list">
      <div v-for="i in 5" :key="i" class="audit-item-row skeleton-item-row">
        <SkeletonLoader type="rect" height="48px" />
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="logs.length === 0" class="empty-audit-state">
      <span class="material-symbols-outlined empty-icon">manage_search</span>
      <h4 class="empty-title">{{ t('admin.staff.noAuditLogsFound') }}</h4>
      <p class="empty-sub">{{ t('admin.staff.noAuditLogsSub') }}</p>
    </div>

    <!-- Audit List -->
    <div v-else class="audit-items-list">
      <div
        v-for="item in logs"
        :key="item.id"
        class="audit-item-row"
        :class="`status-${item.status}`"
      >
        <!-- Actor Column -->
        <div class="log-actor-col">
          <div class="actor-name-wrap">
            <span class="material-symbols-outlined icon-16 actor-icon">person</span>
            <strong class="actor-name">{{ item.actorName }}</strong>
          </div>
          <span class="actor-role-tag">{{ item.actorRole }}</span>
        </div>

        <!-- Action Column -->
        <div class="log-action-col">
          <span class="action-title">{{ item.action }}</span>
          <span class="target-desc">
            {{ t('admin.staff.hdrTarget', { target: item.target }) }}
          </span>
        </div>

        <!-- Meta (Timestamp & IP) Column -->
        <div class="log-meta-col">
          <div class="timestamp-row">
            <span class="material-symbols-outlined icon-14">schedule</span>
            <span class="timestamp">{{ item.timestamp }}</span>
          </div>
          <span class="ip-addr font-mono">IP: {{ item.ip }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.audit-logs-container {
  display: flex;
  flex-direction: column;
  gap: 18px;
  background: var(--surface);
  border-radius: 18px;
  border: 1px solid var(--outline);
  padding: 24px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.audit-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 12px;
}

.card-title-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.audit-heading {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.audit-sub {
  margin: 2px 0 0 0;
  font-size: 12.5px;
  color: var(--muted);
}

.audit-toolbar {
  display: flex;
  gap: 12px;
  align-items: center;
  flex-wrap: wrap;
}

.search-wrap {
  position: relative;
  flex: 1;
  min-width: 240px;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 14px;
  font-size: 18px;
  color: var(--muted);
  pointer-events: none;
}

.audit-search-input {
  width: 100%;
  padding: 10px 38px 10px 38px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  outline: none;
  box-sizing: border-box;
}

.audit-search-input:focus {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
}

.clear-btn {
  position: absolute;
  right: 12px;
  background: transparent;
  border: 0;
  color: var(--muted);
  cursor: pointer;
  font-size: 12px;
}

.dropdown-filter-wrap {
  width: 200px;
}

.date-filter-wrap {
  width: 200px;
}

.audit-items-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.audit-item-row {
  display: grid;
  grid-template-columns: 220px 1fr 220px;
  padding: 14px 18px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
  align-items: center;
  gap: 16px;
  transition:
    transform 0.15s,
    box-shadow 0.15s;
}

.audit-item-row:hover {
  transform: translateX(2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.skeleton-item-row {
  padding: 8px 18px;
}

.log-actor-col {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.actor-name-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
}

.actor-icon {
  font-size: 16px;
  color: var(--pink-dark, #db2777);
}

.actor-name {
  color: var(--ink);
  font-size: 13.5px;
}

.actor-role-tag {
  font-size: 11px;
  color: var(--muted);
  padding-left: 22px;
}

.log-action-col {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.action-title {
  font-weight: 700;
  font-size: 13.5px;
  color: var(--ink);
}

.target-desc {
  font-size: 12px;
  color: var(--muted);
}

.log-meta-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
}

.timestamp-row {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--ink);
  font-weight: 500;
}

.ip-addr {
  font-size: 11px;
  color: var(--muted);
}

.status-warning {
  border-left: 4px solid #f59e0b;
}

.status-danger {
  border-left: 4px solid #ef4444;
}

.status-success {
  border-left: 4px solid #10b981;
}

.empty-audit-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  border-radius: 14px;
  border: 1.5px dashed var(--outline);
  text-align: center;
}

.empty-icon {
  font-size: 40px;
  color: var(--muted);
  margin-bottom: 8px;
}

.empty-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
}

.empty-sub {
  margin: 2px 0 0 0;
  font-size: 12.5px;
  color: var(--muted);
}

.icon-14 {
  font-size: 14px;
}
.icon-16 {
  font-size: 16px;
}

:global(html.dark) .audit-logs-container,
:global(html.dark) .audit-item-row,
:global(html.dark) .empty-audit-state {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .audit-search-input {
  background: var(--surface-subtle, #262024);
  color: var(--ink, #faf7f3);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .actor-name,
:global(html.dark) .action-title,
:global(html.dark) .timestamp-row {
  color: var(--ink, #faf7f3);
}

@media (max-width: 900px) {
  .audit-item-row {
    grid-template-columns: 1fr;
    gap: 10px;
  }
  .log-meta-col {
    align-items: flex-start;
  }
}
</style>

<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { HostKyc, KycBusinessType, KycStats, KycStatus } from '../../types/kyc'
import { DatePicker, SelectDropdown, SkeletonLoader } from '@/shared/components'

const props = withDefaults(
  defineProps<{
    items: HostKyc[]
    activeTab: KycStatus | 'all'
    selectedId?: number
    loading?: boolean
    selectedBusinessType?: KycBusinessType | 'all'
    selectedDate?: string
    stats?: KycStats
  }>(),
  {
    loading: false,
    selectedBusinessType: 'all',
    selectedDate: '',
  }
)

const emit = defineEmits<{
  (e: 'update:activeTab', tab: KycStatus | 'all'): void
  (e: 'update:selectedBusinessType', type: KycBusinessType | 'all'): void
  (e: 'update:selectedDate', date: string): void
  (e: 'select', item: HostKyc): void
}>()

const { t } = useI18n()

const tabsContainer = ref<HTMLElement | null>(null)
const isDragging = ref(false)
const hasDragged = ref(false)
const startX = ref(0)
const scrollLeft = ref(0)

function onMouseDown(e: MouseEvent) {
  if (!tabsContainer.value) return
  isDragging.value = true
  hasDragged.value = false
  startX.value = e.pageX - tabsContainer.value.offsetLeft
  scrollLeft.value = tabsContainer.value.scrollLeft

  window.addEventListener('mousemove', onMouseMove)
  window.addEventListener('mouseup', onMouseUp)
}

function onMouseMove(e: MouseEvent) {
  if (!isDragging.value || !tabsContainer.value) return
  e.preventDefault()
  const x = e.pageX - tabsContainer.value.offsetLeft
  const walk = (x - startX.value) * 1.5
  if (Math.abs(walk) > 4) {
    hasDragged.value = true
  }
  tabsContainer.value.scrollLeft = scrollLeft.value - walk
}

function onMouseUp() {
  isDragging.value = false
  window.removeEventListener('mousemove', onMouseMove)
  window.removeEventListener('mouseup', onMouseUp)
  setTimeout(() => {
    hasDragged.value = false
  }, 60)
}

function onWheel(e: WheelEvent) {
  if (!tabsContainer.value) return
  if (e.deltaY !== 0) {
    tabsContainer.value.scrollLeft += e.deltaY * 0.8
  }
}

function handleTabClick(key: KycStatus | 'all') {
  if (hasDragged.value) return
  emit('update:activeTab', key)
}

onUnmounted(() => {
  window.removeEventListener('mousemove', onMouseMove)
  window.removeEventListener('mouseup', onMouseUp)
})

const tabs = computed<{ key: KycStatus | 'all'; label: string; count?: number }[]>(() => [
  { key: 'pending', label: t('admin.kyc.tabPending'), count: props.stats?.pending_count },
  { key: 'needs_supplement', label: t('admin.kyc.tabNeedsSupplement'), count: props.stats?.needs_supplement_count },
  { key: 'flagged', label: t('admin.kyc.tabFlagged'), count: props.stats?.flagged_count },
  { key: 'approved', label: t('admin.kyc.tabApproved'), count: props.stats?.approved_count },
  { key: 'all', label: t('admin.kyc.tabAll') },
])

const businessTypeOptions = computed(() => [
  { value: 'all', label: t('admin.kyc.filterAllTypes') },
  { value: 'enterprise', label: t('admin.kyc.businessEnterprise') },
  { value: 'business_household', label: t('admin.kyc.businessHousehold') },
  { value: 'individual', label: t('admin.kyc.businessIndividual') },
])

function getBusinessTypeLabel(type: KycBusinessType): string {
  switch (type) {
    case 'enterprise':
      return t('admin.kyc.businessEnterprise')
    case 'business_household':
      return t('admin.kyc.businessHousehold')
    case 'individual':
      return t('admin.kyc.businessIndividual')
    default:
      return type
  }
}

function getStatusLabel(status: KycStatus): string {
  switch (status) {
    case 'pending':
      return t('admin.kyc.statusPending')
    case 'needs_supplement':
      return t('admin.kyc.statusNeedsSupplement')
    case 'flagged':
      return t('admin.kyc.statusFlagged')
    case 'approved':
      return t('admin.kyc.statusApproved')
    case 'rejected':
      return t('admin.kyc.statusRejected')
    default:
      return status
  }
}

function getProgressPercent(item: HostKyc): number {
  if (item.status === 'approved') return 100
  let count = 0
  if (item.id_card_front_url) count += 25
  if (item.business_license_url) count += 25
  if (item.napas_match_status === 'matched') count += 25
  if (item.facility_images?.length || item.court_scale > 0) count += 25
  return count
}
</script>

<template>
  <div class="kyc-queue-column">
    <!-- Tab Filter -->
    <div
      ref="tabsContainer"
      class="queue-tabs"
      :class="{ 'is-dragging': isDragging }"
      @mousedown="onMouseDown"
      @wheel.prevent="onWheel"
    >
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === tab.key }"
        @click="handleTabClick(tab.key)"
      >
        <span>{{ tab.label }}</span>
        <span v-if="tab.count !== undefined && tab.count > 0" class="tab-count">
          ({{ tab.count }})
        </span>
      </button>
    </div>

    <!-- Secondary Filters (Business Type & Date) -->
    <div class="queue-sub-filters">
      <div class="filter-col">
        <SelectDropdown
          :model-value="selectedBusinessType"
          :options="businessTypeOptions"
          variant="bordered"
          :placeholder="t('admin.kyc.filterBusinessType')"
          @update:model-value="emit('update:selectedBusinessType', $event as KycBusinessType | 'all')"
        />
      </div>
      <div class="filter-col date-col">
        <DatePicker
          :model-value="selectedDate"
          :placeholder="t('admin.kyc.filterDateLabel')"
          @update:model-value="emit('update:selectedDate', $event)"
        />
      </div>
    </div>

    <!-- Cards Scroll Area -->
    <div class="queue-card-list">
      <!-- Skeleton Loading State -->
      <template v-if="loading">
        <div v-for="i in 3" :key="i" class="kyc-card skeleton-card">
          <div class="card-header-row">
            <SkeletonLoader type="text" width="80px" height="12px" />
            <SkeletonLoader type="rect" width="60px" height="20px" rounded="999px" />
          </div>
          <SkeletonLoader type="title" width="70%" height="18px" />
          <div class="doc-badges-row">
            <SkeletonLoader v-for="j in 4" :key="j" type="rect" width="100%" height="22px" rounded="6px" />
          </div>
          <SkeletonLoader type="rect" width="100%" height="6px" rounded="999px" />
        </div>
      </template>

      <!-- Empty State -->
      <div v-else-if="items.length === 0" class="queue-empty">
        <span class="material-symbols-outlined icon-32">task_alt</span>
        <p>{{ t('admin.kyc.noDataInTab') }}</p>
      </div>

      <!-- Loaded Items -->
      <button
        v-for="item in items"
        v-else
        :key="item.id"
        type="button"
        class="kyc-card"
        :class="{
          'is-active': selectedId === item.id,
          'is-flagged': item.status === 'flagged',
        }"
        @click="emit('select', item)"
      >
        <!-- Top row: Code & Status Badge -->
        <div class="card-header-row">
          <div class="card-meta">
            <span class="kyc-code">{{ item.kyc_code }}</span>
            <span class="business-type-badge">{{ getBusinessTypeLabel(item.business_type) }}</span>
          </div>
          <span
            class="status-pill"
            :class="{
              'pill-pending': item.status === 'pending',
              'pill-flagged': item.status === 'flagged',
              'pill-needs-supplement': item.status === 'needs_supplement',
              'pill-approved': item.status === 'approved',
              'pill-rejected': item.status === 'rejected',
            }"
          >
            <span v-if="item.status === 'pending'" class="pulse-dot"></span>
            {{ getStatusLabel(item.status) }}
          </span>
        </div>

        <!-- Business Name -->
        <div class="card-name">{{ item.business_name }}</div>

        <!-- Mini document verification badges -->
        <div class="doc-badges-row">
          <span class="doc-badge success" :title="t('admin.kyc.sec1Title')">
            <span class="material-symbols-outlined doc-icon">check</span> {{ t('admin.kyc.docCccd') }}
          </span>
          <span
            class="doc-badge"
            :class="item.status === 'flagged' || item.id === 1 ? 'warning' : 'success'"
            :title="t('admin.kyc.sec2Title')"
          >
            <span class="material-symbols-outlined doc-icon">{{ item.status === 'flagged' || item.id === 1 ? 'warning' : 'check' }}</span> {{ t('admin.kyc.docGpkd') }}
          </span>
          <span class="doc-badge success" :title="t('admin.kyc.sec3Title')">
            <span class="material-symbols-outlined doc-icon">check</span> {{ t('admin.kyc.docNapas') }}
          </span>
          <span class="doc-badge success" :title="t('admin.kyc.sec4Title')">
            <span class="material-symbols-outlined doc-icon">check</span> {{ t('admin.kyc.docFacility') }}
          </span>
        </div>

        <!-- Progress bar -->
        <div class="progress-container">
          <div class="progress-bar-bg">
            <div class="progress-fill" :style="{ width: `${getProgressPercent(item)}%` }"></div>
          </div>
          <span class="progress-text">{{ getProgressPercent(item) }}%</span>
        </div>
      </button>
    </div>
  </div>
</template>

<style scoped>
.kyc-queue-column {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.queue-tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--outline, #e1e3e4);
  padding-bottom: 4px;
  overflow-x: auto;
  scrollbar-width: none;
  -ms-overflow-style: none;
  cursor: grab;
  user-select: none;
}

.queue-tabs.is-dragging {
  cursor: grabbing;
}

.queue-tabs::-webkit-scrollbar {
  display: none;
}

.tab-btn {
  border: none;
  background: transparent;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 4px;
}

.tab-btn:hover {
  color: var(--ink, #191c1d);
  background: var(--surface-container, #edeeef);
}

.tab-btn.active {
  color: var(--primary, #78555e);
  background: var(--primary-container, #ffd1dc);
}

.tab-count {
  font-size: 11px;
  font-weight: 700;
}

.queue-sub-filters {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.filter-col {
  min-width: 0;
}

.queue-card-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: calc(100vh - 320px);
  overflow-y: auto;
  padding-right: 4px;
  scrollbar-width: thin;
  scrollbar-color: var(--outline, rgba(140, 140, 140, 0.25)) transparent;
}

.queue-card-list::-webkit-scrollbar {
  width: 5px;
}

.queue-card-list::-webkit-scrollbar-track {
  background: transparent;
}

.queue-card-list::-webkit-scrollbar-thumb {
  background: var(--outline, #e1e3e4);
  border-radius: 999px;
}

.queue-card-list::-webkit-scrollbar-thumb:hover {
  background: var(--muted, #817476);
}

.queue-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 16px;
  color: var(--muted, #817476);
  gap: 8px;
  text-align: center;
  font-size: 13px;
  background: var(--surface, #ffffff);
  border: 1px dashed var(--outline, #e1e3e4);
  border-radius: 16px;
}

.icon-32 { font-size: 32px; }

.kyc-card {
  border: 1px solid var(--outline, #e1e3e4);
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 14px 16px;
  text-align: left;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: all 0.2s ease;
  position: relative;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
  width: 100%;
}

.kyc-card:hover {
  border-color: var(--primary, #78555e);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.kyc-card.is-active {
  border-color: var(--primary, #78555e);
  background: var(--surface, #ffffff);
  box-shadow: 0 0 0 2px var(--primary, #78555e), 0 4px 12px rgba(120, 85, 94, 0.12);
}

.kyc-card.is-flagged {
  border-color: rgba(239, 68, 68, 0.4);
}

.kyc-card.skeleton-card {
  gap: 8px;
  cursor: default;
}

.card-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.card-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.kyc-code {
  font-size: 11px;
  font-weight: 700;
  color: var(--primary, #78555e);
  letter-spacing: 0.04em;
}

.business-type-badge {
  font-size: 10px;
  color: var(--muted, #5d5f5f);
  background: var(--surface-container, #edeeef);
  padding: 2px 6px;
  border-radius: 4px;
}

.status-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  white-space: nowrap;
}

.pill-pending { background: #fef3c7; color: #d97706; }
.pill-flagged { background: #fee2e2; color: #dc2626; }
.pill-needs-supplement { background: #e0f2fe; color: #0284c7; }
.pill-approved { background: #d1fae5; color: #059669; }
.pill-rejected { background: #f1f5f9; color: #64748b; }

.pulse-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #d97706;
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(1.3); }
}

.card-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  line-height: 1.3;
}

.doc-badges-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 4px;
}

.doc-badge {
  font-size: 10px;
  font-weight: 600;
  padding: 3px 4px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2px;
}

.doc-badge.success { background: #d1fae5; color: #059669; }
.doc-badge.warning { background: #fef3c7; color: #d97706; }

.doc-icon { font-size: 12px; }

.progress-container {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 2px;
}

.progress-bar-bg {
  flex: 1;
  height: 5px;
  background: var(--outline, #e1e3e4);
  border-radius: 999px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: var(--primary, #78555e);
  border-radius: 999px;
  transition: width 0.3s ease;
}

.progress-text {
  font-size: 10px;
  font-weight: 700;
  color: var(--primary, #78555e);
}
</style>


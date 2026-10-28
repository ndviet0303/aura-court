<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { SkeletonLoader } from '@/shared/components'
import type { DisputeCase, DisputeStatus } from '../../types/dispute'

defineProps<{
  items: DisputeCase[]
  activeTab: DisputeStatus | 'all'
  selectedId?: number
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: DisputeStatus | 'all'): void
  (e: 'select', item: DisputeCase): void
}>()

const { t, locale } = useI18n()

// Drag-to-scroll for horizontal tabs
const tabContainerRef = ref<HTMLElement | null>(null)
const isMouseDown = ref(false)
const hasDragged = ref(false)
const startX = ref(0)
const scrollStart = ref(0)

function onMouseDown(e: MouseEvent) {
  if (!tabContainerRef.value) return
  isMouseDown.value = true
  hasDragged.value = false
  startX.value = e.clientX
  scrollStart.value = tabContainerRef.value.scrollLeft

  window.addEventListener('mousemove', onMouseMove)
  window.addEventListener('mouseup', onMouseUp)
}

function onMouseMove(e: MouseEvent) {
  if (!isMouseDown.value || !tabContainerRef.value) return
  const currentX = e.clientX
  const walk = (currentX - startX.value) * 1.5
  if (Math.abs(walk) > 3) {
    hasDragged.value = true
  }
  tabContainerRef.value.scrollLeft = scrollStart.value - walk
}

function onMouseUp() {
  isMouseDown.value = false
  window.removeEventListener('mousemove', onMouseMove)
  window.removeEventListener('mouseup', onMouseUp)
}

function handleTabClick(tab: DisputeStatus | 'all', e: MouseEvent) {
  if (hasDragged.value) {
    e.preventDefault()
    e.stopPropagation()
    return
  }
  emit('update:activeTab', tab)
}

function onWheel(e: WheelEvent) {
  if (!tabContainerRef.value) return
  if (e.deltaY !== 0 && e.deltaX === 0) {
    tabContainerRef.value.scrollLeft += e.deltaY
    e.preventDefault()
  }
}

function getStatusBadge(item: DisputeCase) {
  if (item.severity === 'critical') {
    return { label: t('admin.disputes.badgeCriticalSla'), class: 'badge-critical', icon: 'warning' }
  }
  if (item.severity === 'high') {
    return { label: t('admin.disputes.badgeHighPriority'), class: 'badge-high', icon: 'error' }
  }
  if (item.status === 'resolved') {
    return {
      label: t('admin.disputes.badgeArbitrated'),
      class: 'badge-resolved',
      icon: 'check_circle',
    }
  }
  return { label: t('admin.disputes.badgeNormal'), class: 'badge-normal', icon: 'info' }
}

function formatRelativeTime(dateStr?: string): string {
  if (!dateStr) return locale.value === 'en' ? 'Recently' : 'Vừa xong'
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMinutes = Math.floor(diffMs / 60000)

  if (diffMinutes < 1) return locale.value === 'en' ? 'Just now' : 'Vừa xong'
  if (diffMinutes < 60)
    return locale.value === 'en' ? `${diffMinutes}m ago` : `${diffMinutes} phút trước`
  const diffHours = Math.floor(diffMinutes / 60)
  if (diffHours < 24) return locale.value === 'en' ? `${diffHours}h ago` : `${diffHours} giờ trước`
  const diffDays = Math.floor(diffHours / 24)
  return locale.value === 'en' ? `${diffDays}d ago` : `${diffDays} ngày trước`
}
</script>

<template>
  <div class="dispute-ticket-list">
    <!-- Header & Tabs -->
    <div class="list-header">
      <div class="list-title-row">
        <h2 class="list-title">{{ t('admin.disputes.title') }}</h2>
        <span class="total-badge">{{
          t('admin.disputes.totalCases', { count: items.length })
        }}</span>
      </div>

      <!-- Filter Tabs (Draggable / Horizontal Scrollable) -->
      <div
        ref="tabContainerRef"
        class="filter-tabs"
        :class="{ 'is-dragging': isMouseDown }"
        style="scrollbar-width: none"
        @mousedown="onMouseDown"
        @mousemove="onMouseMove"
        @mouseup="onMouseUp"
        @mouseleave="onMouseUp"
        @wheel="onWheel"
      >
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'all' }"
          @click="handleTabClick('all', $event)"
        >
          {{ t('admin.disputes.filterAll') }}
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'pending' }"
          @click="handleTabClick('pending', $event)"
        >
          {{ t('admin.disputes.statusPending') }}
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'investigating' }"
          @click="handleTabClick('investigating', $event)"
        >
          {{ t('admin.disputes.statusInvestigating') }}
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'awaiting_response' }"
          @click="handleTabClick('awaiting_response', $event)"
        >
          {{ t('admin.disputes.statusAwaitingResponse') }}
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'resolved' }"
          @click="handleTabClick('resolved', $event)"
        >
          {{ t('admin.disputes.statusResolved') }}
        </button>
      </div>
    </div>

    <!-- Ticket List Cards / Skeleton -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="n in 4" :key="n" class="ticket-card skeleton-ticket">
        <div class="card-top">
          <SkeletonLoader type="text" width="80px" height="14px" />
          <SkeletonLoader type="text" width="50px" height="12px" />
        </div>
        <SkeletonLoader type="title" width="90%" height="18px" />
        <SkeletonLoader type="text" width="70%" height="14px" />
        <div class="card-footer">
          <SkeletonLoader type="text" rounded="999px" width="110px" height="20px" />
          <SkeletonLoader type="text" width="70px" height="16px" />
        </div>
      </div>
    </div>

    <div v-else-if="items.length === 0" class="empty-state">
      <span class="material-symbols-outlined icon-empty">inbox</span>
      <p>{{ t('admin.disputes.emptyList') }}</p>
    </div>

    <div v-else class="tickets-container">
      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        class="ticket-card"
        :class="{ active: selectedId === item.id }"
        @click="emit('select', item)"
      >
        <div v-if="selectedId === item.id" class="active-indicator"></div>

        <div class="card-top">
          <span class="ticket-code">#{{ item.disputeCode }}</span>
          <span class="ticket-time">{{ formatRelativeTime(item.created_at) }}</span>
        </div>

        <h3 class="ticket-title">{{ item.title }}</h3>
        <p class="ticket-parties">{{ item.complainant.name }} vs {{ item.respondent.name }}</p>

        <div class="card-footer">
          <span class="status-pill" :class="getStatusBadge(item).class">
            <span class="material-symbols-outlined status-icon">{{
              getStatusBadge(item).icon
            }}</span>
            <span>{{ getStatusBadge(item).label }}</span>
          </span>
          <span class="amount-tag">
            {{ item.amountDisputed.toLocaleString(locale === 'en' ? 'en-US' : 'vi-VN') }} ₫
          </span>
        </div>
      </button>
    </div>
  </div>
</template>

<style scoped>
.dispute-ticket-list {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 16px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-sm, 0 1px 3px rgba(0, 0, 0, 0.04));
  color: var(--ink);
}

.list-header {
  padding: 18px 20px 14px;
  border-bottom: 1px solid var(--outline);
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: var(--surface);
}

.list-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.list-title {
  font-size: 16px;
  font-weight: 800;
  margin: 0;
  color: var(--ink);
  letter-spacing: -0.01em;
}

.total-badge {
  font-size: 11px;
  font-weight: 700;
  background: var(--surface-subtle);
  border: 1px solid var(--outline);
  color: var(--muted);
  padding: 2px 8px;
  border-radius: 6px;
}

.filter-tabs {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 4px;
  cursor: grab;
  user-select: none;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.filter-tabs::-webkit-scrollbar {
  display: none;
}

.filter-tabs.is-dragging {
  cursor: grabbing;
  scroll-behavior: auto;
}

.tab-btn {
  background: var(--surface-subtle);
  border: 1px solid var(--outline);
  border-radius: 8px;
  padding: 5px 12px;
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  transition: all 0.15s;
  user-select: none;
}

.tab-btn:hover {
  background: var(--soft);
  color: var(--ink);
}

.tab-btn.active {
  background: var(--primary-container);
  color: var(--primary);
  border-color: var(--primary);
  font-weight: 700;
}

.tickets-container {
  display: flex;
  flex-direction: column;
  max-height: 720px;
  overflow-y: auto;
}

.ticket-card {
  text-align: left;
  padding: 16px 20px;
  border: none;
  border-bottom: 1px solid var(--outline);
  background: transparent;
  cursor: pointer;
  position: relative;
  transition: background 0.15s;
  display: flex;
  flex-direction: column;
  gap: 6px;
  color: inherit;
}

.ticket-card:hover {
  background: var(--surface-subtle);
}

.ticket-card.active {
  background: var(--primary-container);
}

.active-indicator {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--primary);
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.ticket-code {
  font-size: 12px;
  font-weight: 700;
  color: var(--primary);
  letter-spacing: 0.03em;
}

.ticket-time {
  font-size: 11px;
  color: var(--muted);
}

.ticket-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ticket-parties {
  font-size: 12px;
  color: var(--muted);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 4px;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.status-icon {
  font-size: 12px;
}

.badge-critical {
  background: var(--error-bg, rgba(198, 40, 40, 0.2));
  color: var(--error-text, #f87171);
}

.badge-high {
  background: var(--warning-bg, rgba(239, 108, 0, 0.2));
  color: var(--warning-text, #fb923c);
}

.badge-resolved {
  background: var(--success-bg, rgba(46, 125, 50, 0.25));
  color: var(--success-text, #4ade80);
}

.badge-normal {
  background: var(--soft);
  color: var(--muted);
}

.amount-tag {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}

.skeleton-list {
  display: flex;
  flex-direction: column;
}

.skeleton-ticket {
  gap: 10px;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: var(--muted);
  gap: 10px;
  font-size: 13px;
}

.icon-empty {
  font-size: 40px;
  color: var(--outline);
}
</style>

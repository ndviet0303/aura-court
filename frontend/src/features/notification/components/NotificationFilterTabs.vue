<script setup lang="ts">
import type { NotificationFilterType } from '../types/notification'

defineProps<{
  activeFilter: NotificationFilterType
  counts: {
    all: number
    unread: number
    booking: number
    payment: number
    social: number
  }
}>()

const emit = defineEmits<{
  (e: 'update:activeFilter', filter: NotificationFilterType): void
}>()

const filters = [
  { id: 'all' as const, icon: 'all_inclusive' },
  { id: 'unread' as const, icon: 'mark_email_unread' },
  { id: 'booking' as const, icon: 'sports_tennis' },
  { id: 'payment' as const, icon: 'payments' },
  { id: 'social' as const, icon: 'groups' },
]
</script>

<template>
  <div class="notification-filters-bar">
    <div class="filter-pills-list">
      <button
        v-for="f in filters"
        :key="f.id"
        type="button"
        class="filter-pill-btn"
        :class="{ 'is-active': activeFilter === f.id }"
        @click="emit('update:activeFilter', f.id)"
      >
        <span class="material-symbols-outlined filter-ico">{{ f.icon }}</span>
        <span>{{ $t(`notifications.filters.${f.id}`) }}</span>
        <span class="count-badge font-mono" :class="{ 'has-unread': f.id === 'unread' && counts.unread > 0 }">
          {{ counts[f.id] }}
        </span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.notification-filters-bar {
  display: flex;
  align-items: center;
  overflow-x: auto;
  scrollbar-width: none;
  padding-bottom: 4px;
}

.notification-filters-bar::-webkit-scrollbar {
  display: none;
}

.filter-pills-list {
  display: flex;
  align-items: center;
  gap: 8px;
}

.filter-pill-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 14px;
  border-radius: 999px;
  border: 1px solid var(--outline, #e2dad0);
  background: var(--surface, #ffffff);
  color: var(--muted, #6e6668);
  font-size: 13px;
  font-weight: 650;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

html.dark .filter-pill-btn {
  background: var(--surface, #1e191d);
  border-color: var(--outline, #352c32);
  color: #a89fa5;
}

.filter-pill-btn:hover {
  border-color: var(--primary, #6B2D3F);
  color: var(--ink, #1c1618);
}

html.dark .filter-pill-btn:hover {
  border-color: #dfba72;
  color: #faf7f3;
}

.filter-pill-btn.is-active {
  background: var(--primary, #6B2D3F);
  border-color: var(--primary, #6B2D3F);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(107, 45, 63, 0.2);
}

html.dark .filter-pill-btn.is-active {
  background: #dfba72;
  border-color: #dfba72;
  color: #1c1618;
  box-shadow: 0 4px 14px rgba(223, 186, 114, 0.25);
}

.filter-ico {
  font-size: 17px;
}

.count-badge {
  font-size: 11px;
  font-weight: 750;
  padding: 2px 7px;
  border-radius: 999px;
  background: var(--surface-container, rgba(0, 0, 0, 0.06));
  color: inherit;
}

html.dark .count-badge {
  background: rgba(255, 255, 255, 0.1);
}

.filter-pill-btn.is-active .count-badge {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
}

html.dark .filter-pill-btn.is-active .count-badge {
  background: rgba(0, 0, 0, 0.2);
  color: #1c1618;
}

.count-badge.has-unread {
  background: #dc2626 !important;
  color: #ffffff !important;
}
</style>

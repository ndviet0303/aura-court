<script setup lang="ts">
import { useNotificationList } from '../composables/useNotificationList'
import { SkeletonLoader } from '@/shared/components'

import NotificationHeader from '../components/NotificationHeader.vue'
import NotificationFilterTabs from '../components/NotificationFilterTabs.vue'
import NotificationItemCard from '../components/NotificationItemCard.vue'
import NotificationEmptyState from '../components/NotificationEmptyState.vue'

const {
  notifications,
  unread,
  loading,
  markingAllRead,
  error,
  activeFilter,
  filterCounts,
  filteredNotifications,
  load,
  markAsRead,
  markAllAsRead,
} = useNotificationList()
</script>

<template>
  <div class="notifications-view-page">
    <!-- Header Section -->
    <NotificationHeader
      :unread-count="unread"
      :total-count="notifications.length"
      :loading="loading"
      :marking-all-read="markingAllRead"
      @refresh="load"
      @mark-all-read="markAllAsRead"
    />

    <!-- Feedback Error Alert -->
    <transition name="fade">
      <div v-if="error" class="alert-pill-error" role="alert">
        <span class="material-symbols-outlined alert-ico">error</span>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- Category Filter Tabs -->
    <NotificationFilterTabs
      :active-filter="activeFilter"
      :counts="filterCounts"
      @update:active-filter="activeFilter = $event"
    />

    <!-- Main Content List -->
    <main class="notifications-main-content">
      <!-- Loading Skeleton State -->
      <div v-if="loading" class="skeleton-stack">
        <SkeletonLoader type="card" :count="4" />
      </div>

      <!-- Empty State -->
      <NotificationEmptyState
        v-else-if="filteredNotifications.length === 0"
        :filter="activeFilter"
      />

      <!-- Notifications List -->
      <div v-else class="notifications-feed">
        <NotificationItemCard
          v-for="notification in filteredNotifications"
          :key="notification.id"
          :notification="notification"
          @read="markAsRead"
        />
      </div>
    </main>
  </div>
</template>

<style scoped>
.notifications-view-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  width: 100%;
  max-width: 960px;
  margin: 0 auto;
  padding: 32px 20px 64px;
  color: var(--ink, #1c1618);
  box-sizing: border-box;
  min-width: 0;
}

.alert-pill-error {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  border-radius: 12px;
  background: #dc2626;
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 650;
  box-shadow: 0 4px 16px rgba(220, 38, 38, 0.2);
}

.alert-ico {
  font-size: 20px;
  flex-shrink: 0;
}

.notifications-main-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.skeleton-stack {
  display: flex;
  flex-direction: column;
  gap: 14px;
  width: 100%;
  min-width: 0;
}

.notifications-feed {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
  min-width: 0;
}

.fade-enter-active,
.fade-leave-active {
  transition:
    opacity 0.25s ease,
    transform 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>

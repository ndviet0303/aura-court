<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { formatDateTime } from '@/i18n'
import { getNotificationMeta } from '../composables/useNotificationList'
import type { AppNotification } from '../types/notification'

const props = defineProps<{
  notification: AppNotification
}>()

const emit = defineEmits<{
  (e: 'read', item: AppNotification): void
}>()

const meta = computed(() => getNotificationMeta(props.notification.data?.event))
const isUnread = computed(() => !props.notification.read_at)

function handleClick() {
  if (isUnread.value) {
    emit('read', props.notification)
  }
}
</script>

<template>
  <article class="notification-item-card" :class="{ 'is-unread': isUnread }" @click="handleClick">
    <!-- Event Icon Badge -->
    <div class="notif-icon-box" :style="{ color: meta.color, backgroundColor: `${meta.color}15` }">
      <span class="material-symbols-outlined">{{ meta.icon }}</span>
    </div>

    <!-- Content Area -->
    <div class="notif-body-col">
      <div class="notif-top-meta">
        <span
          class="category-badge font-mono"
          :style="{
            color: meta.color,
            borderColor: `${meta.color}35`,
            backgroundColor: `${meta.color}10`,
          }"
        >
          {{ meta.badgeText }}
        </span>

        <div class="time-read-group">
          <time class="notif-time">
            <span class="material-symbols-outlined time-ico">schedule</span>
            {{ formatDateTime(notification.created_at) }}
          </time>
          <span
            v-if="isUnread"
            class="unread-dot"
            :title="$t('notifications.filters.unread')"
          ></span>
        </div>
      </div>

      <h3 class="notif-title">
        {{ notification.data?.title || $t('notifications.categories.system') }}
      </h3>
      <p class="notif-body-text">{{ notification.data?.body }}</p>

      <!-- Action Link if Present -->
      <div v-if="notification.data?.action_url" class="notif-action-row" @click.stop>
        <RouterLink
          :to="notification.data.action_url"
          class="notif-action-btn"
          @click="handleClick"
        >
          <span>{{ $t('notifications.viewDetail') }}</span>
          <span class="material-symbols-outlined btn-arrow">arrow_forward</span>
        </RouterLink>
      </div>
    </div>
  </article>
</template>

<style scoped>
.notification-item-card {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 20px 22px;
  border-radius: 18px;
  border: 1px solid var(--outline, #e2dad0);
  background: var(--surface, #ffffff);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
  box-sizing: border-box;
  width: 100%;
  min-width: 0;
}

html.dark .notification-item-card {
  background: var(--surface, #1e191d);
  border-color: var(--outline, #352c32);
}

.notification-item-card:hover {
  border-color: var(--primary, #6b2d3f);
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
}

html.dark .notification-item-card:hover {
  border-color: #dfba72;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
}

.notification-item-card.is-unread {
  border-left: 4px solid var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.02);
}

html.dark .notification-item-card.is-unread {
  border-left-color: #dfba72;
  background: rgba(223, 186, 114, 0.04);
}

.notif-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  margin-top: 2px;
}

.notif-icon-box .material-symbols-outlined {
  font-size: 22px;
}

.notif-body-col {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
  min-width: 0;
}

.notif-top-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.category-badge {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.06em;
  padding: 2px 7px;
  border-radius: 5px;
  border: 1px solid transparent;
}

.time-read-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.notif-time {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--muted, #6e6668);
}

.time-ico {
  font-size: 14px;
}

.unread-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #dc2626;
  box-shadow: 0 0 6px rgba(220, 38, 38, 0.5);
}

.notif-title {
  margin: 0;
  font-size: 15px;
  font-weight: 750;
  color: var(--ink, #1c1618);
  line-height: 1.35;
}

html.dark .notif-title {
  color: #faf7f3;
}

.notif-body-text {
  margin: 0;
  font-size: 13.5px;
  color: var(--muted, #6e6668);
  line-height: 1.5;
}

.notif-action-row {
  display: flex;
  align-items: center;
  margin-top: 4px;
}

.notif-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 8px;
  background: var(--surface-container, rgba(0, 0, 0, 0.04));
  border: 1px solid var(--outline, #e2dad0);
  color: var(--primary, #6b2d3f);
  font-size: 12.5px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.15s ease;
}

html.dark .notif-action-btn {
  background: rgba(255, 255, 255, 0.05);
  border-color: #3b3238;
  color: #dfba72;
}

.notif-action-btn:hover {
  background: var(--primary, #6b2d3f);
  color: #ffffff;
  border-color: var(--primary, #6b2d3f);
}

html.dark .notif-action-btn:hover {
  background: #dfba72;
  color: #1c1618;
  border-color: #dfba72;
}

.btn-arrow {
  font-size: 14px;
}

@media (max-width: 640px) {
  .notification-item-card {
    padding: 16px;
    gap: 12px;
  }
  .notif-top-meta {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }
}
</style>

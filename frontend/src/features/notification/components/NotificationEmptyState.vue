<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { NotificationFilterType } from '../types/notification'

const props = defineProps<{
  filter: NotificationFilterType
}>()

const emptyInfo = computed(() => {
  switch (props.filter) {
    case 'unread':
      return {
        icon: 'mark_email_read',
        titleKey: 'notifications.emptyUnreadTitle',
        descKey: 'notifications.emptyUnreadDesc',
      }
    case 'booking':
      return {
        icon: 'sports_tennis',
        titleKey: 'notifications.emptyBookingTitle',
        descKey: 'notifications.emptyBookingDesc',
      }
    case 'payment':
      return {
        icon: 'payments',
        titleKey: 'notifications.emptyPaymentTitle',
        descKey: 'notifications.emptyPaymentDesc',
      }
    case 'social':
      return {
        icon: 'groups',
        titleKey: 'notifications.emptySocialTitle',
        descKey: 'notifications.emptySocialDesc',
      }
    default:
      return {
        icon: 'notifications_none',
        titleKey: 'notifications.emptyAllTitle',
        descKey: 'notifications.emptyAllDesc',
      }
  }
})
</script>

<template>
  <div class="empty-state-card">
    <div class="empty-icon-circle">
      <span class="material-symbols-outlined">{{ emptyInfo.icon }}</span>
    </div>

    <h3 class="empty-title">{{ $t(emptyInfo.titleKey) }}</h3>
    <p class="empty-desc">{{ $t(emptyInfo.descKey) }}</p>

    <div class="empty-actions">
      <RouterLink to="/courts" class="btn-cta-explore">
        <span class="material-symbols-outlined">explore</span>
        <span>{{ $t('notifications.exploreBooking') }}</span>
      </RouterLink>
      <RouterLink to="/booking" class="btn-cta-secondary">
        <span class="material-symbols-outlined">calendar_month</span>
        <span>{{ $t('profile.myBookings') }}</span>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.empty-state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 48px 24px;
  border-radius: 20px;
  border: 1px dashed var(--outline, #e2dad0);
  background: var(--surface, #ffffff);
}

html.dark .empty-state-card {
  background: var(--surface, #1e191d);
  border-color: var(--outline, #352c32);
}

.empty-icon-circle {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--surface-container, rgba(0, 0, 0, 0.04));
  color: var(--muted, #6e6668);
  display: grid;
  place-items: center;
  margin-bottom: 16px;
}

html.dark .empty-icon-circle {
  background: rgba(255, 255, 255, 0.06);
  color: #a89fa5;
}

.empty-icon-circle .material-symbols-outlined {
  font-size: 32px;
}

.empty-title {
  margin: 0 0 6px;
  font-size: 17px;
  font-weight: 750;
  color: var(--ink, #1c1618);
}

html.dark .empty-title {
  color: #faf7f3;
}

.empty-desc {
  margin: 0 0 20px;
  font-size: 13.5px;
  color: var(--muted, #6e6668);
  max-width: 420px;
  line-height: 1.5;
}

.empty-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  justify-content: center;
}

.btn-cta-explore {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  border-radius: 10px;
  background: var(--primary, #6B2D3F);
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s ease;
}

html.dark .btn-cta-explore {
  background: #dfba72;
  color: #1c1618;
}

.btn-cta-secondary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  border-radius: 10px;
  background: var(--surface-container, rgba(0, 0, 0, 0.04));
  border: 1px solid var(--outline, #e2dad0);
  color: var(--ink, #1c1618);
  font-size: 13.5px;
  font-weight: 650;
  text-decoration: none;
  transition: all 0.2s ease;
}

html.dark .btn-cta-secondary {
  background: rgba(255, 255, 255, 0.05);
  border-color: #3b3238;
  color: #faf7f3;
}
</style>

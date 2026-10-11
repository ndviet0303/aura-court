<script setup lang="ts">
import { AuraButton } from '@/shared/components'

defineProps<{
  unreadCount: number
  totalCount: number
  loading: boolean
  markingAllRead: boolean
}>()

const emit = defineEmits<{
  (e: 'refresh'): void
  (e: 'markAllRead'): void
}>()
</script>

<template>
  <header class="notifications-header">
    <div class="header-titles">
      <div class="category-eyebrow">
        <span class="material-symbols-outlined eyebrow-icon">notifications_active</span>
        <span class="font-mono">{{ $t('notifications.eyebrow') }}</span>
      </div>

      <div class="title-with-badge">
        <h1 class="page-title">{{ $t('notifications.title') }}</h1>
        <span v-if="unreadCount > 0" class="unread-count-pill font-mono">
          {{ $t('notifications.newBadge', { count: unreadCount }) }}
        </span>
      </div>

      <p class="page-sub">
        {{ $t('notifications.subtitle') }}
      </p>
    </div>

    <div class="header-actions">
      <AuraButton
        variant="secondary"
        size="md"
        :loading="loading"
        :disabled="loading"
        @click="emit('refresh')"
      >
        <template #icon>
          <span class="material-symbols-outlined" :class="{ spinning: loading }">refresh</span>
        </template>
        {{ $t('notifications.refreshBtn') }}
      </AuraButton>

      <AuraButton
        variant="primary"
        size="md"
        :loading="markingAllRead"
        :disabled="unreadCount === 0 || markingAllRead"
        @click="emit('markAllRead')"
      >
        <template #icon>
          <span class="material-symbols-outlined">done_all</span>
        </template>
        {{ $t('notifications.markAllRead') }}
      </AuraButton>
    </div>
  </header>
</template>

<style scoped>
.notifications-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 24px;
  flex-wrap: wrap;
}

.header-titles {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 640px;
}

.category-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: var(--pink-dark, #db2777);
  text-transform: uppercase;
}

html.dark .category-eyebrow {
  color: #dfba72;
}

.eyebrow-icon {
  font-size: 15px;
}

.title-with-badge {
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-title {
  margin: 0;
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ink, #1c1618);
}

html.dark .page-title {
  color: #faf7f3;
}

.unread-count-pill {
  font-size: 12px;
  font-weight: 800;
  padding: 3px 10px;
  border-radius: 999px;
  background: #dc2626;
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
}

.page-sub {
  margin: 0;
  font-size: 14px;
  color: var(--muted, #6e6668);
  line-height: 1.5;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.spinning {
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 640px) {
  .page-title {
    font-size: 22px;
  }
  .header-actions {
    width: 100%;
    justify-content: flex-start;
  }
}
</style>

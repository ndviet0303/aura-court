<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { notificationApi } from '@/features/notification/api/notificationApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { formatDateTime } from '@/i18n'
import type { AppNotification } from '@/features/notification/types/notification'
import { SkeletonLoader } from '@/shared/components'

const { t } = useI18n()
const notifications = ref<AppNotification[]>([])
const unread = ref(0)
const error = ref('')
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const result = await notificationApi.list()
    unread.value = result.unread
    notifications.value = result.notifications.data
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    loading.value = false
  }
}

async function read(notification: AppNotification) {
  if (notification.read_at) return
  await notificationApi.markAsRead(notification.id)
  notification.read_at = new Date().toISOString()
  unread.value = Math.max(0, unread.value - 1)
}

async function readAll() {
  await notificationApi.markAllAsRead()
  notifications.value.forEach((item) => { item.read_at ||= new Date().toISOString() })
  unread.value = 0
}

onMounted(load)
</script>

<template>
  <section class="page-shell">
    <div class="row">
      <div>
        <div class="eyebrow">{{ t('notifications.eyebrow') }}</div>
        <h1 class="page-title">{{ t('notifications.title') }}</h1>
        <p class="muted">{{ unread }} unread.</p>
      </div>
      <button class="btn secondary" :disabled="!unread" @click="readAll">
        {{ t('notifications.markAllRead') }}
      </button>
    </div>
    <p v-if="error" class="error">{{ error }}</p>
    <div v-if="loading" style="margin-top:24px; display:flex; flex-direction:column; gap:12px;">
      <SkeletonLoader type="card" :count="3" />
    </div>
    <div v-else-if="!notifications.length" class="card" style="margin-top:24px">
      <p class="muted">{{ t('notifications.empty') }}</p>
    </div>
    <article
      v-else
      v-for="notification in notifications"
      :key="notification.id"
      class="card"
      :style="{ marginTop: '14px', opacity: notification.read_at ? .72 : 1 }"
      @click="read(notification)"
    >
      <div class="row">
        <div>
          <strong>{{ notification.data?.title }}</strong>
          <p style="margin:8px 0">{{ notification.data?.body }}</p>
          <RouterLink v-if="notification.data?.action_url" :to="notification.data.action_url" class="link-button">
            {{ t('courts.courtDetail') }}
          </RouterLink>
        </div>
        <small class="muted">{{ formatDateTime(notification.created_at) }}</small>
      </div>
    </article>
  </section>
</template>

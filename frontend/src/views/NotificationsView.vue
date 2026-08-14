<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, errorMessage } from '@/lib/api'
import type { AppNotification } from '@/types'

const notifications = ref<AppNotification[]>([])
const unread = ref(0)
const error = ref('')
const formatDate = (value: string) => new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' })

async function load() {
  try { const result = await api.get<{ unread: number; notifications: { data: AppNotification[] } }>('/notifications'); unread.value = result.unread; notifications.value = result.notifications.data }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không tải được thông báo.') }
}

async function read(notification: AppNotification) {
  if (notification.read_at) return
  await api.post('/notifications/' + notification.id + '/read')
  notification.read_at = new Date().toISOString()
  unread.value = Math.max(0, unread.value - 1)
}

async function readAll() { await api.post('/notifications/read-all'); notifications.value.forEach((item) => { item.read_at ||= new Date().toISOString() }); unread.value = 0 }
onMounted(load)
</script>

<template>
  <section class="page-shell"><div class="row"><div><div class="eyebrow">Trung tâm thông báo</div><h1 class="page-title">Thông báo của bạn</h1><p class="muted">{{ unread }} thông báo chưa đọc.</p></div><button class="btn secondary" :disabled="!unread" @click="readAll">Đánh dấu đã đọc</button></div><p v-if="error" class="error">{{ error }}</p><div v-if="!notifications.length" class="card" style="margin-top:24px"><p class="muted">Bạn chưa có thông báo nào.</p></div><article v-for="notification in notifications" :key="notification.id" class="card" :style="{ marginTop: '14px', opacity: notification.read_at ? .72 : 1 }" @click="read(notification)"><div class="row"><div><strong>{{ notification.data.title }}</strong><p style="margin:8px 0">{{ notification.data.body }}</p><RouterLink v-if="notification.data.action_url" :to="notification.data.action_url" class="link-button">Mở chi tiết</RouterLink></div><small class="muted">{{ formatDate(notification.created_at) }}</small></div></article></section>
</template>

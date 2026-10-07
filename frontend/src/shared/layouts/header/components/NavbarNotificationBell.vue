<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { notificationApi } from '@/features/notification/api/notificationApi'

const auth = useAuthStore()
const route = useRoute()
const unreadCount = ref(0)
let inFlightUnread: Promise<void> | null = null

async function fetchUnread() {
  if (!auth.user) {
    unreadCount.value = 0
    return
  }
  if (inFlightUnread) return inFlightUnread
  inFlightUnread = (async () => {
    try {
      const res = await notificationApi.list()
      unreadCount.value = res.unread || 0
    } catch {
      // Non-blocking fallback
    } finally {
      inFlightUnread = null
    }
  })()
  return inFlightUnread
}

// Refetch when route changes or user logs in
watch(() => [route.path, auth.user], () => {
  fetchUnread()
}, { immediate: true })
</script>

<template>
  <RouterLink
    v-if="auth.user"
    to="/notifications"
    class="navbar-bell-btn"
    :class="{ 'is-active': route.path.startsWith('/notifications') }"
    :title="$t('notifications.title')"
    :aria-label="$t('notifications.title')"
  >
    <div class="bell-icon-wrapper">
      <span class="material-symbols-outlined bell-icon">notifications</span>
      <span v-if="unreadCount > 0" class="bell-badge font-mono">
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </div>
  </RouterLink>
</template>

<style scoped>
.navbar-bell-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 999px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #ebdccb);
  color: var(--ink, #1c1618);
  text-decoration: none;
  padding: 0;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  flex-shrink: 0;
  position: relative;
}

.navbar-bell-btn:hover,
.navbar-bell-btn.is-active {
  background: #FAF7F3;
  border-color: #6B2D3F;
  color: #6B2D3F;
  transform: translateY(-1px);
  box-shadow: 0 3px 10px rgba(107, 45, 63, 0.12);
}

html.dark .navbar-bell-btn {
  background: #1e191c;
  border-color: #382f35;
  color: #e5dde2;
}

html.dark .navbar-bell-btn:hover,
html.dark .navbar-bell-btn.is-active {
  background: #262024;
  border-color: rgba(223, 186, 114, 0.4);
  color: #dfba72;
}

.bell-icon-wrapper {
  position: relative;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bell-icon {
  font-size: 20px;
  color: inherit;
  transition: color 0.2s ease;
}

.bell-badge {
  position: absolute;
  top: -7px;
  right: -9px;
  min-width: 16px;
  height: 16px;
  padding: 0 3px;
  border-radius: 999px;
  background: #dc2626;
  color: #ffffff;
  font-size: 9.5px;
  font-weight: 850;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1.5px solid var(--surface, #ffffff);
  box-shadow: 0 2px 6px rgba(220, 38, 38, 0.35);
  box-sizing: border-box;
  line-height: 1;
}

html.dark .bell-badge {
  border-color: #1e191c;
}
</style>

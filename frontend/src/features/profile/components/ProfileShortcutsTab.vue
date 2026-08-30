<script setup lang="ts">
import { RouterLink } from 'vue-router'

const shortcuts = [
  { name: 'Lịch Đã Đặt', path: '/booking', icon: '📅' },
  { name: 'Ví Thành Viên', path: '/wallet', icon: '💳' },
  { name: 'Kèo Ghép Đấu', path: '/matchmaking', icon: '👥' },
  { name: 'Chợ Pass Sân', path: '/marketplace', icon: '🛒' },
]
</script>

<template>
  <div class="profile-tab-card">
    <h3 class="tab-title">⚡ Lối Tắt Nhanh Tiện Ích</h3>

    <div class="shortcuts-soft-grid">
      <RouterLink
        v-for="s in shortcuts"
        :key="s.path"
        :to="s.path"
        class="shortcut-item-card"
      >
        <span class="s-icon">{{ s.icon }}</span>
        <strong class="s-name">{{ s.name }}</strong>
        <span class="s-arrow">➔</span>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.profile-tab-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.tab-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.shortcuts-soft-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
}

.shortcut-item-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  text-decoration: none;
  transition: all 0.15s;
}

.shortcut-item-card:hover {
  border-color: var(--pink-dark, #db2777);
  transform: translateY(-2px);
}

.s-icon {
  font-size: 20px;
}

.s-name {
  font-size: 13px;
  flex: 1;
}

.s-arrow {
  font-size: 12px;
  color: var(--muted);
}
</style>

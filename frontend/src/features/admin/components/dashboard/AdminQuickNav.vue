<script setup lang="ts">
import { RouterLink } from 'vue-router'

const navItems = [
  { name: 'Quản Lý Sân & Bảng Giá', path: '/admin/manage', icon: '🏟️' },
  { name: 'Phân Quyền Nhân Sự', path: '/admin/staff', icon: '👥' },
  { name: 'Đối Soát Ca & Tiền Mặt', path: '/admin/shift-reconciliation', icon: '💵' },
  { name: 'Hợp Đồng Định Kỳ', path: '/admin/contracts', icon: '📑' },
  { name: 'Ma Trận Giá Linh Hoạt', path: '/admin/pricing', icon: '⚡' },
  { name: 'Cụm Sân & Cơ Sở', path: '/admin/venues', icon: '🏢' },
  { name: 'Doanh Thu & Rút Tiền', path: '/admin/payouts', icon: '💰' },
  { name: 'Lịch Hoạt Động & Bảo Trì', path: '/admin/schedule', icon: '📅' },
  { name: 'Cây Nhánh Giải Đấu', path: '/admin/tournaments/bracket', icon: '🏆' },
]
</script>

<template>
  <div class="admin-quick-nav-card">
    <h3 class="title">⚡ Lối Tắt Quản Trị Hệ Thống (Quick Navigation)</h3>
    <div class="nav-grid">
      <RouterLink
        v-for="item in navItems"
        :key="item.path"
        :to="item.path"
        class="nav-card-item"
      >
        <span class="n-icon">{{ item.icon }}</span>
        <strong class="n-name">{{ item.name }}</strong>
        <span class="n-arrow">➔</span>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.admin-quick-nav-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
}

.nav-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 12px;
}

.nav-card-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  text-decoration: none;
  transition: all 0.15s;
}

.nav-card-item:hover {
  border-color: var(--pink-dark, #db2777);
  transform: translateY(-2px);
  background: var(--surface);
}

.n-icon {
  font-size: 20px;
}

.n-name {
  font-size: 13px;
  flex: 1;
}

.n-arrow {
  font-size: 12px;
  color: var(--muted);
}
</style>

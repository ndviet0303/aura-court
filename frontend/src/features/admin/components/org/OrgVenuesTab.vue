<script setup lang="ts">
import type { OrgVenue } from '../../composables/useOrganizationOwner'

defineProps<{
  venues: OrgVenue[]
}>()
</script>

<template>
  <div class="org-venues-tab-card">
    <div class="card-header">
      <h3 class="tab-title">📍 Danh Sách Cơ Sở & Cụm Sân Trực Thuộc</h3>
      <span class="sub">Tất cả chi nhánh hoạt động dưới cùng pháp nhân doanh nghiệp</span>
    </div>

    <div class="venues-list">
      <div v-for="v in venues" :key="v.id" class="venue-item-row">
        <div class="v-info">
          <strong class="v-name">{{ v.name }}</strong>
          <span class="v-addr">📍 {{ v.address }}</span>
        </div>
        <div class="v-meta">
          <span class="courts-chip font-mono">{{ v.courts_count || 0 }} sân đấu</span>
          <span class="status-chip" :class="v.is_active ? 'active' : 'inactive'">
            {{ v.is_active ? '● Hoạt động' : '○ Tạm dừng' }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.org-venues-tab-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.tab-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sub {
  font-size: 12px;
  color: var(--muted);
}

.venues-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.venue-item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  gap: 12px;
}

.v-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.v-name {
  font-size: 14px;
}

.v-addr {
  font-size: 12px;
  color: var(--muted);
}

.v-meta {
  display: flex;
  align-items: center;
  gap: 10px;
}

.courts-chip {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 6px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.status-chip {
  font-size: 11px;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 6px;
}

.status-chip.active { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.status-chip.inactive { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
</style>

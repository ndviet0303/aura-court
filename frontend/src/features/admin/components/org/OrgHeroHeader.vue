<script setup lang="ts">
import type { Organization } from '../../composables/useOrganizationOwner'

defineProps<{
  org: Organization
  activeTab: 'profile' | 'venues' | 'members' | 'policies'
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'profile' | 'venues' | 'members' | 'policies'): void
}>()
</script>

<template>
  <div class="org-hero-card">
    <div class="org-main-row">
      <div class="org-badge-logo">
        <span>🏢</span>
      </div>

      <div class="org-meta">
        <div class="title-row">
          <h1 class="org-name">{{ org.name }}</h1>
          <span class="status-pill font-mono">● {{ org.status.toUpperCase() }}</span>
        </div>
        <p class="org-sub font-mono">MST: {{ org.tax_code }} · Hotline: {{ org.phone }} · {{ org.support_email }}</p>
      </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="org-tabs-bar">
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'profile' }"
        @click="emit('update:activeTab', 'profile')"
      >
        🏢 Hồ Sơ Doanh Nghiệp & Thương Hiệu
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'venues' }"
        @click="emit('update:activeTab', 'venues')"
      >
        📍 Cụm Sân Trực Thuộc ({{ org.venues?.length || 0 }})
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'members' }"
        @click="emit('update:activeTab', 'members')"
      >
        👥 Đội Ngũ Quản Trị ({{ org.members?.length || 0 }})
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'policies' }"
        @click="emit('update:activeTab', 'policies')"
      >
        ⚙️ Chính Sách Vận Hành & Tự Động
      </button>
    </div>
  </div>
</template>

<style scoped>
.org-hero-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.org-main-row {
  display: flex;
  align-items: center;
  gap: 18px;
}

.org-badge-logo {
  width: 60px;
  height: 60px;
  border-radius: 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  border: 1px solid var(--outline);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
}

.org-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.org-name {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.status-pill {
  font-size: 10px;
  font-weight: 800;
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
  padding: 2px 8px;
  border-radius: 4px;
}

.org-sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.org-tabs-bar {
  display: flex;
  gap: 8px;
  border-top: 1px solid var(--outline);
  padding-top: 16px;
  overflow-x: auto;
}

.tab-btn {
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.tab-btn.active {
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  border-color: rgba(236, 72, 153, 0.2);
}
</style>

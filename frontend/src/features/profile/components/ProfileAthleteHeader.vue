<script setup lang="ts">
defineProps<{
  user: any
  initials: string
  activeTab: 'general' | 'security' | 'membership' | 'shortcuts'
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'general' | 'security' | 'membership' | 'shortcuts'): void
}>()
</script>

<template>
  <div class="athlete-hero-card">
    <div class="athlete-main-info">
      <div class="avatar-initials-badge">
        {{ initials }}
      </div>

      <div class="athlete-meta">
        <div class="name-row">
          <h1 class="athlete-display-name">{{ user?.name || 'Vận Động Viên' }}</h1>
          <span class="role-chip font-mono">{{ (user?.role || 'customer').toUpperCase() }}</span>
          <span v-if="user?.social_provider" class="badge-social font-mono">Google</span>
        </div>
        <p class="athlete-email">✉️ {{ user?.email }} · 📱 {{ user?.phone || 'Chưa cập nhật SĐT' }}</p>
      </div>
    </div>

    <!-- Tabs Navigation Bar -->
    <div class="stitch-tabs-bar">
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'general' }"
        @click="emit('update:activeTab', 'general')"
      >
        👤 Thông Tin Cá Nhân
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'security' }"
        @click="emit('update:activeTab', 'security')"
      >
        🔒 Bảo Mật & Mật Khẩu
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'membership' }"
        @click="emit('update:activeTab', 'membership')"
      >
        🏅 Hội Viên & Điểm Thưởng
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'shortcuts' }"
        @click="emit('update:activeTab', 'shortcuts')"
      >
        ⚡ Lối Tắt Nhanh
      </button>
    </div>
  </div>
</template>

<style scoped>
.athlete-hero-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.athlete-main-info {
  display: flex;
  align-items: center;
  gap: 18px;
}

.avatar-initials-badge {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--pink-dark, #db2777);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  font-weight: 900;
  box-shadow: 0 4px 16px rgba(219, 39, 119, 0.3);
}

.athlete-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.name-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.athlete-display-name {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.role-chip {
  font-size: 10px;
  font-weight: 800;
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
  padding: 2px 8px;
  border-radius: 4px;
}

.athlete-email {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.stitch-tabs-bar {
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

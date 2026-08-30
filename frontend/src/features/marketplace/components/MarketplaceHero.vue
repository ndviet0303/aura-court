<script setup lang="ts">
defineProps<{
  activeTab: 'buy' | 'sell'
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'buy' | 'sell'): void
}>()
</script>

<template>
  <div class="marketplace-hero-card">
    <div class="hero-titles">
      <span class="badge font-mono">⚡ P2P ESCROW MARKETPLACE</span>
      <h1 class="page-title">Chợ Chuyển Nhượng & Pass Sân Thể Thao</h1>
      <p class="page-sub">Mua lại slot sân giờ đẹp giá hời hoặc pass lại lịch đặt khi có việc bận. Tiền được giữ an toàn qua Escrow.</p>
    </div>

    <!-- Tab switcher -->
    <div class="tab-switcher">
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'buy' }"
        @click="emit('update:activeTab', 'buy')"
      >
        🛒 Tìm Slot Pass Lại
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'sell' }"
        @click="emit('update:activeTab', 'sell')"
      >
        🏷️ Pass Lại Lịch Của Tôi
      </button>
    </div>
  </div>
</template>

<style scoped>
.marketplace-hero-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.hero-titles {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.badge {
  font-size: 11px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.1);
  padding: 3px 8px;
  border-radius: 6px;
  width: fit-content;
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.tab-switcher {
  display: flex;
  gap: 8px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  padding: 4px;
  border-radius: 12px;
}

.tab-btn {
  padding: 10px 18px;
  border-radius: 8px;
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.tab-btn.active {
  background: var(--surface);
  color: var(--pink-dark, #db2777);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}
</style>

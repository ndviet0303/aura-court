<script setup lang="ts">
defineProps<{
  alertsCount: number
}>()

const emit = defineEmits<{
  (e: 'auto-resolve'): void
  (e: 'open-investigation'): void
}>()
</script>

<template>
  <div v-if="alertsCount > 0" class="alert-banner">
    <div class="banner-left">
      <div class="warning-badge">
        <span class="material-symbols-outlined warning-icon">warning</span>
      </div>
      <div class="banner-content">
        <div class="banner-title">
          Phát hiện {{ alertsCount }} sai lệch tài chính cần xử lý trước chu kỳ quyết toán 17:00 hôm nay
        </div>
        <p class="banner-desc">
          Bao gồm: 1 giao dịch trễ Webhook Napas247 (TXN-88291A), 1 chênh lệch làm tròn phí đối tác 10,000 ₫ (TXN-88288D) và 1 phiên đối soát chờ bù trừ.
        </p>
      </div>
    </div>

    <div class="banner-actions">
      <button class="btn-auto-fix" type="button" @click="emit('auto-resolve')">
        <span class="material-symbols-outlined">auto_fix_high</span>
        Xử lý hàng loạt bằng AI
      </button>
      <button class="btn-open-investigate" type="button" @click="emit('open-investigation')">
        <span class="material-symbols-outlined">manage_search</span>
        Mở Trình Điều Tra
      </button>
    </div>
  </div>
</template>

<style scoped>
.alert-banner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  background: #fffbfa;
  border-left: 4px solid #ba1a1a;
  border-radius: 14px;
  padding: 16px 20px;
  border-top: 1px solid #ffdad6;
  border-right: 1px solid #ffdad6;
  border-bottom: 1px solid #ffdad6;
  box-shadow: 0 2px 10px rgba(186, 26, 26, 0.04);
}

.banner-left {
  display: flex;
  align-items: center;
  gap: 14px;
  flex: 1;
  min-width: 320px;
}

.warning-badge {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: #ffdad6;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.warning-icon {
  font-size: 22px;
  color: #ba1a1a;
  font-variation-settings: 'FILL' 1;
}

.banner-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.banner-title {
  font-size: 15px;
  font-weight: 700;
  color: #ba1a1a;
}

.banner-desc {
  font-size: 13px;
  color: #4f4446;
  margin: 0;
}

.banner-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-auto-fix {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--primary, #6B2D3F);
  color: #ffffff;
  border: none;
  padding: 8px 16px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s, transform 0.1s;
}

.btn-auto-fix:hover {
  background: #5e3e47;
  transform: translateY(-1px);
}

.btn-open-investigate {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #ffffff;
  color: #191c1d;
  border: 1px solid #e1e3e4;
  padding: 8px 16px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-open-investigate:hover {
  background: #f3f4f5;
}
</style>

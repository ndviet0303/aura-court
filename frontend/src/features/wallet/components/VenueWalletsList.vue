<script setup lang="ts">
import type { VenueWallet } from '../composables/useWallet'

defineProps<{
  wallets: VenueWallet[]
}>()

const emit = defineEmits<{
  (e: 'deposit', wallet: VenueWallet): void
  (e: 'qr', wallet: VenueWallet): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="venue-wallets-section">
    <div class="section-hdr">
      <h3 class="title">🏦 Ví Thành Viên Từng Cụm Sân</h3>
      <span class="sub">Số dư được lưu trữ riêng biệt tại từng câu lạc bộ</span>
    </div>

    <div class="wallets-grid">
      <div
        v-for="w in wallets"
        :key="w.id"
        class="wallet-card"
        :class="`gradient-${w.gradient_type}`"
      >
        <div class="w-top">
          <span class="w-chip">● ACTIVE WALLET</span>
          <span class="w-curr">{{ w.currency }}</span>
        </div>

        <div class="w-venue-info">
          <h4 class="v-name">{{ w.venue_name }}</h4>
          <p class="v-addr">{{ w.address }}</p>
        </div>

        <div class="w-balance-box">
          <span class="bal-lbl">Số Dư Khả Dụng</span>
          <strong class="bal-val font-mono">{{ formatMoney(w.balance) }}</strong>
        </div>

        <div class="w-actions">
          <button class="btn-action" @click="emit('deposit', w)">
            ➕ Nạp Thêm
          </button>
          <button class="btn-action" @click="emit('qr', w)">
            📱 Mã QR Chi Tiết
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.venue-wallets-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.section-hdr {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sub {
  font-size: 12px;
  color: var(--muted);
}

.wallets-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 18px;
}

.wallet-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  border-radius: 20px;
  color: #fff;
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.gradient-primary {
  background: linear-gradient(135deg, #db2777 0%, #9d174d 100%);
}

.gradient-secondary {
  background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
}

.gradient-neutral {
  background: linear-gradient(135deg, #475569 0%, #1e293b 100%);
}

.w-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 10px;
  font-weight: 800;
  opacity: 0.9;
}

.w-venue-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.v-name {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
}

.v-addr {
  margin: 0;
  font-size: 11px;
  opacity: 0.8;
}

.w-balance-box {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-top: auto;
}

.bal-lbl {
  font-size: 11px;
  opacity: 0.8;
}

.bal-val {
  font-size: 26px;
}

.w-actions {
  display: flex;
  gap: 10px;
  padding-top: 12px;
  border-top: 1px solid rgba(255, 255, 255, 0.15);
}

.btn-action {
  flex: 1;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  backdrop-filter: blur(4px);
}
</style>

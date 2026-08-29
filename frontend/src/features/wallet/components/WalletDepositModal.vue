<script setup lang="ts">
import type { VenueWallet } from '../composables/useWallet'

defineProps<{
  show: boolean
  wallets: VenueWallet[]
  targetWalletId: string
  amount: number
  method: 'vietqr' | 'vnpay' | 'momo' | 'card'
  quickAmounts: number[]
  processing?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update:targetWalletId', id: string): void
  (e: 'update:amount', amount: number): void
  (e: 'update:method', method: 'vietqr' | 'vnpay' | 'momo' | 'card'): void
  (e: 'confirm'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">💳 Nạp Tiền Vào Ví Hội Viên</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Chọn ví cụm sân muốn nạp:</label>
          <select
            :value="targetWalletId"
            class="form-input"
            @change="emit('update:targetWalletId', ($event.target as HTMLSelectElement).value)"
          >
            <option v-for="w in wallets" :key="w.id" :value="w.id">
              {{ w.venue_name }} (Số dư: {{ formatMoney(w.balance) }})
            </option>
          </select>
        </div>

        <!-- Quick amounts -->
        <div class="form-group">
          <label class="form-label">Chọn nhanh mệnh giá:</label>
          <div class="quick-amounts-grid">
            <button
              v-for="amt in quickAmounts"
              :key="amt"
              type="button"
              class="amt-btn font-mono"
              :class="{ active: amount === amt }"
              @click="emit('update:amount', amt)"
            >
              {{ formatMoney(amt) }}
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Hoặc nhập số tiền tùy chỉnh (₫):</label>
          <input
            :value="amount"
            type="number"
            min="50000"
            step="50000"
            class="form-input"
            @input="emit('update:amount', Number(($event.target as HTMLInputElement).value))"
          />
        </div>

        <!-- Payment Method -->
        <div class="form-group">
          <label class="form-label">Phương thức thanh toán:</label>
          <div class="methods-grid">
            <label class="method-card" :class="{ active: method === 'vietqr' }">
              <input
                type="radio"
                name="dep-method"
                value="vietqr"
                :checked="method === 'vietqr'"
                @change="emit('update:method', 'vietqr')"
              />
              <span>📱 VietQR Chuyển Khoản Tự Động</span>
            </label>
            <label class="method-card" :class="{ active: method === 'momo' }">
              <input
                type="radio"
                name="dep-method"
                value="momo"
                :checked="method === 'momo'"
                @change="emit('update:method', 'momo')"
              />
              <span>👛 Ví MoMo</span>
            </label>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button class="btn-primary" :disabled="processing || amount < 10000" @click="emit('confirm')">
          <span v-if="processing">Đang tạo giao dịch...</span>
          <span v-else>Xác Nhận Nạp {{ formatMoney(amount) }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.quick-amounts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
  gap: 8px;
}

.amt-btn {
  padding: 8px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.amt-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.methods-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.method-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
}

.method-card.active {
  border-color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.04);
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 8px 24px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>

<script setup lang="ts">
import type { PayoutItem } from '../../composables/useAdminPayouts'

defineProps<{
  payouts: PayoutItem[]
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="payout-history-card">
    <div class="card-header">
      <h3 class="title">📜 Lịch Sử Lệnh Rút Tiền & Chuyển Khoản (Payouts)</h3>
      <span class="sub">Tất cả giao dịch chuyển tiền về ngân hàng của chủ sân</span>
    </div>

    <div class="payout-table">
      <div class="table-hdr">
        <span>Mã Tham Chiếu</span>
        <span>Ngày Tạo</span>
        <span>Ngân Hàng Nhận</span>
        <span>Số Tiền Rút</span>
        <span>Trạng Thái</span>
      </div>

      <div class="table-body">
        <div v-for="p in payouts" :key="p.id" class="payout-row">
          <strong class="font-mono text-pink">{{ p.reference }}</strong>
          <span>{{ p.created_at }}</span>
          <div class="bank-col">
            <span>{{ p.beneficiary?.bank_code || '---' }} - {{ p.beneficiary?.account_number || '' }}</span>
            <span class="holder-sub">{{ p.beneficiary?.account_holder || '' }}</span>
          </div>
          <strong class="amount-val">{{ formatMoney(p.amount) }}</strong>
          <span
            class="status-pill"
            :class="`status-${p.status}`"
          >
            ● {{ p.status === 'paid' ? 'Đã chi trả' : p.status === 'processing' ? 'Đang xử lý' : 'Chờ duyệt' }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.payout-history-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  padding: 20px;
}

.card-header {
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

.payout-table {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.table-hdr {
  display: grid;
  grid-template-columns: 1.5fr 1.2fr 2fr 1.5fr 1.2fr;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.payout-row {
  display: grid;
  grid-template-columns: 1.5fr 1.2fr 2fr 1.5fr 1.2fr;
  padding: 14px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.payout-row:last-child {
  border-bottom: 0;
}

.text-pink { color: var(--pink-dark, #db2777); }

.bank-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.holder-sub {
  font-size: 11px;
  color: var(--muted);
}

.amount-val {
  font-size: 14px;
}

.status-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.status-paid { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.status-processing { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
</style>

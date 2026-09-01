<script setup lang="ts">
import { AuraButton } from '@/shared/components'
import type { MonthlyInvoiceData } from '../../types/finance'

const props = defineProps<{
  invoice: MonthlyInvoiceData
}>()

const emit = defineEmits<{
  (e: 'openPay'): void
}>()

function formatOrderMemo(invoiceId: number | string): string {
  const num = parseInt(String(invoiceId).replace(/\D+/g, ''), 10) || 1
  const numStr = String(num)
  const padded = numStr.length < 3 ? numStr.padStart(3, '0') : numStr.slice(0, 11)
  return `ORD${padded}`
}

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

function formatDate(dateStr: string): string {
  if (!dateStr) return '---'
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('vi-VN', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    })
  } catch {
    return dateStr.slice(0, 10)
  }
}

function formatFrequency(freq?: string): string {
  switch (freq) {
    case 'weekly':
      return 'Hàng tuần'
    case 'biweekly':
      return '2 tuần / lần'
    case 'monthly':
      return 'Hàng tháng'
    default:
      return 'Định kỳ'
  }
}
</script>

<template>
  <div class="cycle-summary-card">
    <!-- Top Row: ID Tags & Status Badge -->
    <div class="summary-top-row">
      <div class="tag-group">
        <span class="series-tag font-mono">
          <span class="material-symbols-outlined tag-icon">receipt_long</span>
          HÓA ĐƠN #{{ formatOrderMemo(invoice.id) }}
        </span>
        <span v-if="invoice.recurring_series_id" class="contract-tag font-mono">
          <span class="material-symbols-outlined tag-icon">event_repeat</span>
          Hợp đồng #{{ invoice.recurring_series_id }}
        </span>
      </div>

      <div class="status-box">
        <span
          class="invoice-status-badge"
          :class="{
            'status-paid': invoice.status === 'paid',
            'status-pending': invoice.status === 'pending',
            'status-overdue': invoice.status === 'overdue'
          }"
        >
          <span class="material-symbols-outlined status-icon">
            {{ invoice.status === 'paid' ? 'check_circle' : invoice.status === 'overdue' ? 'error_outline' : 'hourglass_top' }}
          </span>
          {{ invoice.status === 'paid' ? 'ĐÃ THANH TOÁN' : invoice.status === 'overdue' ? 'QUÁ HẠN THANH TOÁN' : 'CHỜ THANH TOÁN' }}
        </span>
      </div>
    </div>

    <!-- Court & Venue Hero Info -->
    <div class="court-hero-box">
      <div class="court-icon-box">
        <span class="material-symbols-outlined court-sport-icon">sports_tennis</span>
      </div>
      <div class="court-meta">
        <h2 class="court-title">{{ invoice.series?.court?.name || 'Sân Cố Định VIP 01' }}</h2>
        <p class="venue-sub">
          <span class="material-symbols-outlined loc-icon">location_on</span>
          <span>{{ invoice.series?.court?.venue?.name || 'Aura Court Club' }}</span>
          <span v-if="invoice.series?.court?.venue?.address" class="venue-addr"> · {{ invoice.series?.court?.venue?.address }}</span>
        </p>
      </div>
    </div>

    <!-- Payment Summary Box -->
    <div class="payment-overview-box">
      <div class="box-header">
        <span class="material-symbols-outlined box-header-icon">info</span>
        <h3 class="box-title">Tóm Tắt Thanh Toán</h3>
      </div>

      <div class="overview-grid">
        <div class="o-item">
          <span class="lbl">
            <span class="material-symbols-outlined o-icon">date_range</span>
            Kỳ thanh toán:
          </span>
          <strong class="val">{{ formatDate(invoice.period_start) }} ➔ {{ formatDate(invoice.period_end) }}</strong>
        </div>

        <div class="o-item">
          <span class="lbl">
            <span class="material-symbols-outlined o-icon">event_busy</span>
            Hạn chót đóng tiền:
          </span>
          <strong class="val" :class="{ 'text-red': invoice.status !== 'paid' }">
            {{ formatDate(invoice.due_at) }}
          </strong>
        </div>

        <div class="o-item">
          <span class="lbl">
            <span class="material-symbols-outlined o-icon">sync</span>
            Tần suất lịch chơi:
          </span>
          <strong class="val">{{ formatFrequency(invoice.series?.frequency) }}</strong>
        </div>

        <div class="o-item o-item-total">
          <span class="lbl">
            <span class="material-symbols-outlined o-icon">account_balance_wallet</span>
            Tổng tiền kỳ này:
          </span>
          <strong class="total-amount text-pink">{{ formatMoney(invoice.amount) }}</strong>
        </div>
      </div>

      <!-- Actions -->
      <div v-if="invoice.status !== 'paid'" class="pay-action-row">
        <p class="pay-hint">
          <span class="material-symbols-outlined hint-icon">verified_user</span>
          Vui lòng hoàn tất thanh toán trước hạn để bảo lưu toàn bộ khung giờ đã xếp lịch.
        </p>
        <AuraButton
          variant="primary"
          size="md"
          icon="qr_code_2"
          class="btn-action-primary"
          @click="emit('openPay')"
        >
          <span>Thanh Toán VietQR Ngay</span>
        </AuraButton>
      </div>

      <div v-else class="paid-success-banner">
        <div class="paid-badge-left">
          <span class="material-symbols-outlined paid-check-icon">verified</span>
          <div class="paid-texts">
            <span class="paid-heading">Kỳ thanh toán đã được quyết toán thành công</span>
            <span class="paid-sub" v-if="invoice.payment_reference">Mã tham chiếu: {{ invoice.payment_reference }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cycle-summary-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.summary-top-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.tag-group {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.series-tag,
.contract-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  padding: 5px 12px;
  border-radius: 8px;
}

.series-tag {
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.1);
  border: 1px solid rgba(236, 72, 153, 0.25);
}

.contract-tag {
  color: var(--muted);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline);
}

.tag-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 15px;
  line-height: 1;
}

.invoice-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 800;
  padding: 6px 14px;
  border-radius: 20px;
  letter-spacing: 0.3px;
}

.status-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 16px;
  line-height: 1;
}

.status-paid {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.status-pending {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
  border: 1px solid rgba(245, 158, 11, 0.25);
}

.status-overdue {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.25);
}

.court-hero-box {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.court-icon-box {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.court-sport-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 26px;
  color: var(--pink-dark, #db2777);
  line-height: 1;
}

.court-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.court-title {
  margin: 0;
  font-size: 20px;
  font-weight: 800;
  color: var(--ink);
}

.venue-sub {
  margin: 0;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
  font-size: 13px;
  color: var(--muted);
}

.loc-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 16px;
  color: #ef4444;
  line-height: 1;
}

.payment-overview-box {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 14px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.box-header {
  display: flex;
  align-items: center;
  gap: 6px;
}

.box-header-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 18px;
  color: var(--muted);
  line-height: 1;
}

.box-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
}

.overview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}

.o-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.lbl {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--muted);
}

.o-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 15px;
  line-height: 1;
}

.val {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}

.total-amount {
  font-size: 20px;
  font-weight: 800;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.text-red {
  color: #ef4444;
}

.pay-action-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
  padding-top: 16px;
  border-top: 1px solid var(--outline);
}

.pay-hint {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--muted);
}

.hint-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 16px;
  color: #10b981;
  line-height: 1;
}

.paid-success-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-radius: 10px;
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.paid-badge-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.paid-check-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 24px;
  color: #10b981;
  line-height: 1;
}

.paid-texts {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.paid-heading {
  font-size: 13px;
  font-weight: 700;
  color: #10b981;
}

.paid-sub {
  font-size: 12px;
  color: var(--muted);
}
</style>

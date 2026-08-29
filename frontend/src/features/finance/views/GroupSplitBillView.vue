<script setup lang="ts">
import { useGroupSplitBill } from '../composables/useGroupSplitBill'

import SplitBillSummaryCard from '../components/split/SplitBillSummaryCard.vue'
import SplitModeSelector from '../components/split/SplitModeSelector.vue'
import SplitMembersList from '../components/split/SplitMembersList.vue'
import SplitQrModal from '../components/split/SplitQrModal.vue'

const {
  bookingCode,
  courtName,
  venueName,
  bookingDate,
  bookingTime,
  bankName,
  accountNumber,
  accountName,
  splitMode,
  showQrModal,
  selectedMemberForQr,
  notice,
  items,
  totalBill,
  members,
  totalPaid,
  remainingAmount,
  progressPercent,
  calculateSplits,
  showNotice,
  openQr,
  markAsPaid,
} = useGroupSplitBill()
</script>

<template>
  <div class="group-split-bill-view">
    <!-- Header Section -->
    <header class="split-header">
      <div class="header-titles">
        <h1 class="page-title">💳 Chia Tiền Sân & Dịch Vụ Theo Nhóm (Split Bill)</h1>
        <p class="page-sub">Tự động chia đều hóa đơn tiền sân, nước uống và tạo mã VietQR thanh toán riêng cho từng người.</p>
      </div>

      <div class="header-actions">
        <button class="btn-share" @click="showNotice('Đã sao chép liên kết chia bill vào clipboard!')">
          🔗 Chia Sẻ Link Bill
        </button>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>

    <!-- Summary Card -->
    <SplitBillSummaryCard
      :booking-code="bookingCode"
      :court-name="courtName"
      :venue-name="venueName"
      :booking-date="bookingDate"
      :booking-time="bookingTime"
      :items="items"
      :total-bill="totalBill"
      :total-paid="totalPaid"
      :remaining-amount="remainingAmount"
      :progress-percent="progressPercent"
    />

    <!-- Split Mode Selection -->
    <section class="mode-section">
      <h3 class="section-title">Chọn Phương Thức Chia Tiền:</h3>
      <SplitModeSelector
        :mode="splitMode"
        @update:mode="splitMode = $event; calculateSplits()"
      />
    </section>

    <!-- Members Split List -->
    <SplitMembersList
      :members="members"
      @open-qr="openQr"
      @mark-paid="markAsPaid"
    />

    <!-- QR Modal -->
    <SplitQrModal
      :show="showQrModal"
      :member="selectedMemberForQr"
      :bank-name="bankName"
      :account-number="accountNumber"
      :account-name="accountName"
      :booking-code="bookingCode"
      @close="showQrModal = false"
      @confirm-paid="markAsPaid"
    />
  </div>
</template>

<style scoped>
.group-split-bill-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1000px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.split-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.page-title {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.btn-share {
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.alert-pill {
  padding: 8px 16px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.mode-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.section-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
}
</style>

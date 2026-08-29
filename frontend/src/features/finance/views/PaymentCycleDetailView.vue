<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { usePaymentCycleDetail } from '../composables/usePaymentCycleDetail'

import CycleSummaryCard from '../components/cycles/CycleSummaryCard.vue'
import CycleOccurrencesTable from '../components/cycles/CycleOccurrencesTable.vue'
import CyclePaymentModal from '../components/cycles/CyclePaymentModal.vue'

const route = useRoute()
const invoiceId = route.params.id || '101'

const {
  loading,
  paying,
  notice,
  error,
  invoice,
  occurrences,
  showPaymentModal,
  loadInvoice,
  payInvoice,
} = usePaymentCycleDetail(invoiceId as string)

onMounted(() => {
  loadInvoice()
})
</script>

<template>
  <div class="payment-cycle-detail-view">
    <!-- Header Section -->
    <header class="detail-header">
      <div class="header-titles">
        <h1 class="page-title">💳 Chi Tiết Kỳ Thanh Toán Hợp Đồng Đặt Sân</h1>
        <p class="page-sub">Theo dõi bảng kê chi tiết các buổi chơi trong kỳ, đối soát lịch và thanh toán tự động qua VietQR.</p>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <div v-if="invoice" class="detail-content-wrap">
      <!-- Summary Card -->
      <CycleSummaryCard
        :invoice="invoice"
        @open-pay="showPaymentModal = true"
      />

      <!-- Occurrences List -->
      <CycleOccurrencesTable
        :occurrences="occurrences"
      />
    </div>

    <!-- Payment Modal -->
    <CyclePaymentModal
      :show="showPaymentModal"
      :invoice="invoice"
      :paying="paying"
      @close="showPaymentModal = false"
      @confirm-pay="payInvoice"
    />
  </div>
</template>

<style scoped>
.payment-cycle-detail-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1100px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.detail-header {
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

.alert-pill {
  padding: 8px 16px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  padding: 8px 16px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.detail-content-wrap {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
</style>

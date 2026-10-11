<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { AuraButton, SkeletonLoader } from '@/shared/components'
import { usePaymentCycleDetail } from '../composables/usePaymentCycleDetail'

import CycleSummaryCard from '../components/cycles/CycleSummaryCard.vue'
import CycleOccurrencesTable from '../components/cycles/CycleOccurrencesTable.vue'
import CyclePaymentModal from '../components/cycles/CyclePaymentModal.vue'

const route = useRoute()
const invoiceId = route.params.id as string

const {
  loading,
  notice,
  error,
  invoice,
  occurrences,
  showPaymentModal,
  loadInvoice,
  printInvoice,
  copyInvoiceLink,
  paymentAttempt,
  refreshing,
  refreshStatus,
} = usePaymentCycleDetail(invoiceId as string)

onMounted(() => {
  loadInvoice()
})
</script>

<template>
  <main class="payment-cycle-detail-view">
    <!-- Top Navigation Bar / Breadcrumb -->
    <nav class="detail-nav-bar" aria-label="Breadcrumb">
      <AuraButton variant="ghost" size="sm" icon="arrow_back" to="/recurring-booking?tab=invoices">
        <span>Danh Sách Hóa Đơn Kỳ</span>
      </AuraButton>

      <div class="nav-actions">
        <AuraButton variant="outline" size="sm" icon="share" @click="copyInvoiceLink">
          <span>Chia Sẻ</span>
        </AuraButton>
        <AuraButton variant="outline" size="sm" icon="print" @click="printInvoice">
          <span>In Bảng Kê</span>
        </AuraButton>
      </div>
    </nav>

    <!-- Header Section -->
    <header class="detail-header">
      <div class="header-icon-box">
        <span class="material-symbols-outlined header-icon">receipt_long</span>
      </div>
      <div class="header-titles">
        <h1 class="page-title">Chi Tiết Kỳ Thanh Toán Hợp Đồng Đặt Sân</h1>
        <p class="page-sub">
          Theo dõi bảng kê các buổi chơi và trạng thái thanh toán theo thông tin chủ sân đã xác
          minh.
        </p>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <transition name="fade">
      <div v-if="notice" class="alert-pill-success">
        <span class="material-symbols-outlined alert-icon">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </transition>

    <transition name="fade">
      <div v-if="error" class="alert-pill-error">
        <span class="material-symbols-outlined alert-icon">error_outline</span>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- Loading Skeleton State -->
    <div v-if="loading && !invoice" class="loading-skeleton-wrap">
      <div class="skeleton-card">
        <SkeletonLoader type="rect" width="200px" height="24px" rounded="8px" />
        <SkeletonLoader type="rect" width="100%" height="120px" rounded="12px" />
      </div>
      <div class="skeleton-card">
        <SkeletonLoader type="rect" width="260px" height="24px" rounded="8px" />
        <SkeletonLoader type="rect" width="100%" height="240px" rounded="12px" />
      </div>
    </div>

    <!-- Error / Empty Fallback -->
    <div v-else-if="!invoice && error" class="empty-error-state">
      <div class="empty-icon-wrap">
        <span class="material-symbols-outlined empty-icon">receipt_long</span>
      </div>
      <h3 class="empty-title">Không Thể Tải Hóa Đơn Kỳ #{{ invoiceId }}</h3>
      <p class="empty-sub">
        Hóa đơn không tồn tại hoặc bạn không có quyền truy cập vào thông tin này.
      </p>
      <div class="empty-actions">
        <AuraButton variant="primary" size="md" icon="refresh" @click="loadInvoice">
          <span>Thử Lại</span>
        </AuraButton>
        <AuraButton
          variant="outline"
          size="md"
          icon="arrow_back"
          to="/recurring-booking?tab=invoices"
        >
          <span>Quay Lại Danh Sách</span>
        </AuraButton>
      </div>
    </div>

    <!-- Main Detail Content -->
    <div v-else-if="invoice" class="detail-content-wrap">
      <!-- Summary Card -->
      <CycleSummaryCard
        :invoice="invoice"
        :payment-available="!!paymentAttempt && paymentAttempt.status === 'pending'"
        @open-pay="showPaymentModal = true"
      />

      <!-- Occurrences List -->
      <CycleOccurrencesTable :occurrences="occurrences" :invoice="invoice" />
    </div>

    <!-- Payment Modal -->
    <CyclePaymentModal
      :show="showPaymentModal"
      :invoice="invoice"
      :payment-attempt="paymentAttempt"
      :refreshing="refreshing"
      @close="showPaymentModal = false"
      @refresh="refreshStatus"
    />
  </main>
</template>

<style scoped>
.payment-cycle-detail-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 20px 48px;
  color: var(--ink);
}

.detail-nav-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.nav-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.detail-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px 24px;
  border-radius: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.header-icon-box {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: rgba(219, 39, 119, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.header-icon {
  font-size: 28px;
  color: var(--pink-dark, #db2777);
}

.header-titles {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  color: var(--ink);
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.alert-pill-success {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 12px;
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.3);
  color: #10b981;
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 12px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #ef4444;
  font-size: 13px;
  font-weight: 600;
}

.alert-icon {
  font-size: 18px;
}

.loading-skeleton-wrap {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.skeleton-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 24px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.empty-error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 56px 24px;
  border-radius: 16px;
  border: 1px dashed var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.01));
  gap: 14px;
}

.empty-icon-wrap {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-icon {
  font-size: 32px;
  color: var(--muted);
}

.empty-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.empty-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
  max-width: 440px;
}

.empty-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
}

.detail-content-wrap {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media print {
  .detail-nav-bar,
  .btn-action-primary,
  .pay-action-row,
  .modal-backdrop {
    display: none !important;
  }
}
</style>

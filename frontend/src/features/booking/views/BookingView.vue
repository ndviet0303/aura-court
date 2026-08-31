<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useUserBookings } from '../composables/useUserBookings'

import BookingHistoryHero from '../components/history/BookingHistoryHero.vue'
import BookingHistoryTabs from '../components/history/BookingHistoryTabs.vue'
import UserBookingCard from '../components/history/UserBookingCard.vue'
import BookingReviewModal from '../components/history/BookingReviewModal.vue'
import BookingQrCheckInModal from '../components/history/BookingQrCheckInModal.vue'
import BookingCancelModal from '../components/history/BookingCancelModal.vue'

const {
  bookings,
  activeTab,
  searchQuery,
  error,
  notice,
  loading,
  filteredBookings,
  totalCount,
  upcomingCount,
  completedCount,
  cancelledCount,
  showReviewModal,
  reviewingBooking,
  reviewRating,
  reviewBody,
  submittingReview,
  showQrModal,
  qrBooking,
  showCancelModal,
  cancellingBooking,
  cancelReason,
  cancelling,
  load,
  openQr,
  closeQr,
  openCancel,
  closeCancel,
  confirmCancel,
  openReview,
  closeReview,
  submitReview,
} = useUserBookings()

onMounted(() => {
  load()
})
</script>

<template>
  <div class="user-booking-view-page">
    <!-- Header Hero Banner -->
    <BookingHistoryHero
      :total-count="totalCount"
      :upcoming-count="upcomingCount"
    />

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill-success">
      <span class="material-symbols-outlined alert-icon">check_circle</span>
      <span>{{ notice }}</span>
    </div>
    <div v-if="error" class="alert-pill-error">
      <span class="material-symbols-outlined alert-icon">error</span>
      <span>{{ error }}</span>
    </div>

    <!-- Status Tabs & Quick Search Bar -->
    <BookingHistoryTabs
      :active-tab="activeTab"
      :search="searchQuery"
      :total-count="totalCount"
      :upcoming-count="upcomingCount"
      :completed-count="completedCount"
      :cancelled-count="cancelledCount"
      @update:active-tab="activeTab = $event"
      @update:search="searchQuery = $event"
    />

    <!-- Loading State Skeleton -->
    <div v-if="loading" class="loading-state-container" data-testid="booking-loading">
      <div v-for="i in 3" :key="i" class="booking-card-skeleton">
        <div class="skeleton-line title"></div>
        <div class="skeleton-box"></div>
        <div class="skeleton-line footer"></div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="filteredBookings.length === 0"
      class="empty-state-card"
      data-testid="booking-empty-state"
    >
      <span class="material-symbols-outlined empty-icon">event_busy</span>
      <h3 class="empty-title">Không Tìm Thấy Lịch Đặt Sân</h3>
      <p class="empty-desc">
        {{ searchQuery ? 'Không có kết quả khớp với từ khóa tìm kiếm của bạn.' : 'Bạn chưa có lịch đặt sân nào trong danh mục này. Hãy chọn sân và đặt giờ chơi ngay!' }}
      </p>
      <RouterLink to="/courts" class="btn-find-court">
        <span class="material-symbols-outlined">sports_tennis</span>
        <span>Khám Phá Sân Đấu</span>
      </RouterLink>
    </div>

    <!-- Bookings List Grid -->
    <div v-else class="bookings-grid" data-testid="booking-list">
      <UserBookingCard
        v-for="b in filteredBookings"
        :key="b.id"
        :booking="b"
        @qr="openQr"
        @cancel="openCancel"
        @review="openReview"
      />
    </div>

    <!-- QR Check-in Pass Modal -->
    <BookingQrCheckInModal
      :show="showQrModal"
      :booking="qrBooking"
      @close="closeQr"
    />

    <!-- Cancellation Confirmation Modal -->
    <BookingCancelModal
      :show="showCancelModal"
      :booking="cancellingBooking"
      :reason="cancelReason"
      :loading="cancelling"
      @update:reason="cancelReason = $event"
      @close="closeCancel"
      @confirm="confirmCancel"
    />

    <!-- Review Rating Modal -->
    <BookingReviewModal
      :show="showReviewModal"
      :booking="reviewingBooking"
      :rating="reviewRating"
      :body="reviewBody"
      :submitting="submittingReview"
      @close="closeReview"
      @update:rating="reviewRating = $event"
      @update:body="reviewBody = $event"
      @submit="submitReview"
    />
  </div>
</template>

<style scoped>
.user-booking-view-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1100px;
  margin: 0 auto;
  padding: 28px 20px 60px;
  color: var(--ink);
}

.alert-pill-success {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 12px;
  background: var(--success-bg, rgba(46, 125, 50, 0.12));
  color: var(--success-text, #2e7d32);
  font-size: 13.5px;
  font-weight: 600;
  border: 1px solid rgba(46, 125, 50, 0.2);
}

.alert-pill-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 12px;
  background: var(--error-bg, rgba(198, 40, 40, 0.12));
  color: var(--error-text, #c62828);
  font-size: 13.5px;
  font-weight: 600;
  border: 1px solid rgba(198, 40, 40, 0.2);
}

.alert-icon {
  font-size: 20px;
}

.bookings-grid {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.loading-state-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.booking-card-skeleton {
  padding: 24px;
  border-radius: 18px;
  border: 1px solid var(--border);
  background: var(--surface);
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.skeleton-line {
  height: 20px;
  border-radius: 6px;
  background: linear-gradient(90deg, var(--surface-subtle) 25%, var(--border) 50%, var(--surface-subtle) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

.skeleton-line.title {
  width: 40%;
}

.skeleton-line.footer {
  width: 60%;
}

.skeleton-box {
  height: 60px;
  border-radius: 10px;
  background: var(--surface-subtle);
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.empty-state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 24px;
  text-align: center;
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--outline);
  box-shadow: 0 4px 16px rgba(107, 45, 63, 0.02);
}

.empty-icon {
  font-size: 48px;
  color: var(--muted);
  margin-bottom: 12px;
}

.empty-title {
  margin: 0 0 8px;
  font-size: 18px;
  font-weight: 800;
}

.empty-desc {
  margin: 0 0 20px;
  font-size: 13.5px;
  color: var(--muted);
  max-width: 480px;
  line-height: 1.5;
}

.btn-find-court {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 22px;
  border-radius: 12px;
  background: var(--primary, #6B2D3F);
  color: var(--on-primary, #fff);
  font-size: 13.5px;
  font-weight: 700;
  text-decoration: none;
  box-shadow: 0 4px 14px rgba(107, 45, 63, 0.2);
  transition: all 0.2s ease;
}

.btn-find-court:hover {
  filter: brightness(1.08);
  transform: translateY(-1px);
}

.btn-find-court .material-symbols-outlined {
  font-size: 18px;
}
</style>

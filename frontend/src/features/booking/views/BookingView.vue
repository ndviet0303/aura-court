<script setup lang="ts">
import { onMounted } from 'vue'
import { useUserBookings } from '../composables/useUserBookings'

import BookingHistoryHero from '../components/history/BookingHistoryHero.vue'
import BookingHistoryTabs from '../components/history/BookingHistoryTabs.vue'
import UserBookingCard from '../components/history/UserBookingCard.vue'
import BookingReviewModal from '../components/history/BookingReviewModal.vue'

const {
  bookings,
  activeTab,
  error,
  notice,
  loading,
  filteredBookings,
  upcomingCount,
  completedCount,
  cancelledCount,
  showReviewModal,
  reviewingBooking,
  reviewRating,
  reviewBody,
  load,
  cancelBooking,
  openReview,
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
      :total-count="bookings.length"
    />

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Status Tabs Bar -->
    <BookingHistoryTabs
      :active-tab="activeTab"
      :total-count="bookings.length"
      :upcoming-count="upcomingCount"
      :completed-count="completedCount"
      :cancelled-count="cancelledCount"
      @update:active-tab="activeTab = $event"
    />

    <!-- Bookings List Grid -->
    <div v-if="loading" class="loading-state-card">
      <span>🏸</span>
      <p>Đang tải lịch đặt sân...</p>
    </div>

    <div v-else-if="filteredBookings.length === 0" class="empty-state-card">
      <span>🏸</span>
      <h3>Không có lịch đặt sân nào</h3>
      <p>Bạn chưa có lịch đặt nào trong mục này. Hãy tìm sân để bắt đầu trận đấu!</p>
    </div>

    <div v-else class="bookings-grid">
      <UserBookingCard
        v-for="b in filteredBookings"
        :key="b.id"
        :booking="b"
        @cancel="cancelBooking"
        @review="openReview"
      />
    </div>

    <!-- Review Modal -->
    <BookingReviewModal
      :show="showReviewModal"
      :booking="reviewingBooking"
      :rating="reviewRating"
      :body="reviewBody"
      @close="showReviewModal = false"
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
  padding: 24px 20px;
  color: var(--ink);
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

.bookings-grid {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.empty-state-card, .loading-state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60px 20px;
  text-align: center;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
}

.empty-state-card span, .loading-state-card span {
  font-size: 40px;
  margin-bottom: 10px;
}
</style>

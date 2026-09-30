<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Court, CourtReview } from '../../types/court'

const props = defineProps<{
  court: Court
  reviews?: CourtReview[]
  loading?: boolean
}>()

const activeFilter = ref<'all' | '5' | '4' | '3'>('all')

const reviewList = computed<CourtReview[]>(() => {
  return props.reviews || []
})

const filteredReviews = computed(() => {
  if (activeFilter.value === '5') return reviewList.value.filter((r) => r.rating === 5)
  if (activeFilter.value === '4') return reviewList.value.filter((r) => r.rating === 4)
  if (activeFilter.value === '3') return reviewList.value.filter((r) => r.rating === 3)
  return reviewList.value
})

const totalReviewsCount = computed(() => {
  return reviewList.value.length || props.court.reviews_count || 0
})

const calculatedAvgRating = computed(() => {
  if (reviewList.value.length > 0) {
    const sum = reviewList.value.reduce((acc, r) => acc + (r.rating || 5), 0)
    return (sum / reviewList.value.length).toFixed(1)
  }
  if (props.court.reviews_avg_rating) {
    return Number(props.court.reviews_avg_rating).toFixed(1)
  }
  return '5.0'
})

const countByStar = computed(() => {
  const counts = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 }
  for (const r of reviewList.value) {
    const star = Math.min(5, Math.max(1, Math.round(r.rating || 5))) as 1 | 2 | 3 | 4 | 5
    counts[star] = (counts[star] || 0) + 1
  }
  return counts
})

function getStarPercent(star: 5 | 4 | 3 | 2 | 1): number {
  if (reviewList.value.length === 0) {
    if (star === 5) return 100
    return 0
  }
  return Math.round((countByStar.value[star] / reviewList.value.length) * 100)
}

function formatDate(dateStr?: string): string {
  if (!dateStr) return 'Đã xác thực'
  try {
    const d = new Date(dateStr)
    if (isNaN(d.getTime())) return dateStr
    return d.toLocaleDateString('vi-VN', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    })
  } catch {
    return dateStr
  }
}
</script>

<template>
  <section id="reviews-section" class="court-reviews-section">
    <!-- Header -->
    <div class="reviews-header">
      <div class="header-left">
        <h2 class="section-title">
          <span class="material-symbols-outlined icon-24">rate_review</span>
          <span>Đánh Giá & Nhận Xét Từ Người Chơi</span>
        </h2>
        <p class="section-desc">Phản hồi thực tế từ các vận động viên đã đặt và thi đấu tại sân</p>
      </div>
      <span class="verified-badge">
        <span class="material-symbols-outlined icon-14">verified</span>
        <span>Đánh giá từ lượt đặt sân thực tế</span>
      </span>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="reviews-loading-box">
      <span class="material-symbols-outlined spin-icon">progress_activity</span>
      <span>Đang tải nhận xét từ cơ sở dữ liệu...</span>
    </div>

    <!-- Rating Summary Dashboard Card -->
    <template v-else>
      <div class="rating-dashboard-card">
        <div class="score-main-col">
          <span class="big-score">{{ calculatedAvgRating }}</span>
          <div class="stars-row">
            <span v-for="i in 5" :key="i" class="material-symbols-outlined star-icon">star</span>
          </div>
          <span class="total-reviews-lbl">
            {{ totalReviewsCount > 0 ? `${totalReviewsCount} lượt đánh giá từ DB` : 'Chưa có lượt đánh giá' }}
          </span>
        </div>

        <!-- Rating Breakdown Bars -->
        <div class="rating-bars-col">
          <div class="rating-bar-row">
            <span class="star-label">5 sao</span>
            <div class="progress-track">
              <div class="progress-fill" :style="{ width: `${getStarPercent(5)}%` }"></div>
            </div>
            <span class="star-pct">{{ getStarPercent(5) }}%</span>
          </div>
          <div class="rating-bar-row">
            <span class="star-label">4 sao</span>
            <div class="progress-track">
              <div class="progress-fill" :style="{ width: `${getStarPercent(4)}%` }"></div>
            </div>
            <span class="star-pct">{{ getStarPercent(4) }}%</span>
          </div>
          <div class="rating-bar-row">
            <span class="star-label">3 sao</span>
            <div class="progress-track">
              <div class="progress-fill" :style="{ width: `${getStarPercent(3)}%` }"></div>
            </div>
            <span class="star-pct">{{ getStarPercent(3) }}%</span>
          </div>
        </div>

        <!-- Criteria Highlights -->
        <div class="criteria-col">
          <div class="criteria-item">
            <span class="criteria-name">Chất lượng mặt sàn</span>
            <strong class="criteria-score">Tiêu chuẩn BWF</strong>
          </div>
          <div class="criteria-item">
            <span class="criteria-name">Hệ thống chiếu sáng</span>
            <strong class="criteria-score">Chống lóa chuyên dụng</strong>
          </div>
          <div class="criteria-item">
            <span class="criteria-name">Tiện ích bãi xe & sảnh</span>
            <strong class="criteria-score">Miễn phí & Bảo vệ 24/7</strong>
          </div>
        </div>
      </div>

      <!-- Filter Buttons (When reviews exist) -->
      <div v-if="reviewList.length > 0" class="filter-tabs-row">
        <button
          type="button"
          class="filter-pill"
          :class="{ active: activeFilter === 'all' }"
          @click="activeFilter = 'all'"
        >
          Tất cả ({{ reviewList.length }})
        </button>
        <button
          v-if="countByStar[5] > 0"
          type="button"
          class="filter-pill"
          :class="{ active: activeFilter === '5' }"
          @click="activeFilter = '5'"
        >
          5 Sao ({{ countByStar[5] }})
        </button>
        <button
          v-if="countByStar[4] > 0"
          type="button"
          class="filter-pill"
          :class="{ active: activeFilter === '4' }"
          @click="activeFilter = '4'"
        >
          4 Sao ({{ countByStar[4] }})
        </button>
      </div>

      <!-- Empty State -->
      <div v-if="reviewList.length === 0" class="empty-reviews-box">
        <span class="material-symbols-outlined empty-icon">rate_review</span>
        <strong class="empty-title">Chưa có đánh giá nào cho sân này</strong>
        <p class="empty-sub">
          Đánh giá được kích hoạt tự động sau khi bạn hoàn tất lượt thi đấu tại sân. Hãy là người đầu tiên trải nghiệm và chia sẻ nhận xét!
        </p>
      </div>

      <!-- Real Reviews List from Database -->
      <div v-else class="reviews-list">
        <article
          v-for="rev in filteredReviews"
          :key="rev.id"
          class="review-item-card"
        >
          <div class="review-top-row">
            <div class="author-info">
              <div class="author-avatar">
                {{ rev.user?.name ? rev.user.name.charAt(0).toUpperCase() : 'U' }}
              </div>
              <div class="author-meta">
                <strong class="author-name">{{ rev.user?.name || 'Vận động viên Aura' }}</strong>
                <div class="sub-meta-row">
                  <span class="review-date">{{ formatDate(rev.created_at) }}</span>
                  <span class="verified-player">
                    <span class="material-symbols-outlined icon-12">check_circle</span>
                    <span>Đã đặt sân</span>
                  </span>
                </div>
              </div>
            </div>

            <div class="stars-item">
              <span
                v-for="s in 5"
                :key="s"
                class="material-symbols-outlined item-star"
                :class="{ 'star-inactive': s > (rev.rating || 5) }"
              >
                star
              </span>
            </div>
          </div>

          <p v-if="rev.body" class="review-content">{{ rev.body }}</p>
        </article>
      </div>
    </template>
  </section>
</template>

<style scoped>
.court-reviews-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  padding: 24px;
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .court-reviews-section {
  background: #1a1518;
  border-color: #382d33;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.reviews-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.header-left {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.icon-24 {
  font-size: 24px;
  color: var(--primary);
}

:global(html.dark) .icon-24 {
  color: #e58e9f;
}

.section-desc {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.verified-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(46, 125, 50, 0.12);
  color: var(--success-text, #2e7d32);
  font-size: 11.5px;
  font-weight: 700;
}

:global(html.dark) .verified-badge {
  background: rgba(74, 222, 128, 0.15);
  color: #4ade80;
}

.icon-14 {
  font-size: 14px;
}

.reviews-loading-box {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 40px;
  color: var(--muted);
  font-size: 13px;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.rating-dashboard-card {
  display: grid;
  grid-template-columns: 160px 1fr 1.1fr;
  gap: 24px;
  padding: 20px;
  border-radius: 16px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  align-items: center;
}

:global(html.dark) .rating-dashboard-card {
  background: #231c20;
  border-color: #382d33;
}

.score-main-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  text-align: center;
}

.big-score {
  font-size: 40px;
  font-weight: 900;
  line-height: 1;
  color: var(--primary);
}

:global(html.dark) .big-score {
  color: #e58e9f;
}

.stars-row {
  display: flex;
  gap: 2px;
}

.star-icon {
  font-family: 'Material Symbols Outlined', sans-serif;
  font-size: 18px;
  font-variation-settings: 'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24 !important;
  color: var(--accent, #c9a15a);
}

.total-reviews-lbl {
  font-size: 11px;
  color: var(--muted);
  font-weight: 600;
}

.rating-bars-col {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.rating-bar-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11.5px;
}

.star-label {
  width: 38px;
  color: var(--muted);
  font-weight: 600;
}

.progress-track {
  flex: 1;
  height: 6px;
  border-radius: 999px;
  background: var(--outline);
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, var(--accent) 0%, #dfba72 100%);
  transition: width 0.4s ease;
}

.star-pct {
  width: 32px;
  text-align: right;
  font-weight: 700;
  color: var(--ink);
}

.criteria-col {
  display: flex;
  flex-direction: column;
  gap: 10px;
  border-left: 1px solid var(--outline);
  padding-left: 20px;
}

:global(html.dark) .criteria-col {
  border-color: #382d33;
}

.criteria-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
}

.criteria-name {
  color: var(--muted);
}

.criteria-score {
  font-weight: 800;
  color: var(--accent);
}

.filter-tabs-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.filter-pill {
  padding: 7px 14px;
  border-radius: 999px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.filter-pill.active {
  background: var(--primary);
  color: var(--on-primary);
  border-color: var(--primary);
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.25);
}

:global(html.dark) .filter-pill.active {
  background: #e58e9f;
  color: #1c0a10;
  border-color: #e58e9f;
}

.empty-reviews-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 36px 20px;
  text-align: center;
  border-radius: 14px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px dashed var(--outline);
}

.empty-icon {
  font-size: 36px;
  color: var(--muted);
  margin-bottom: 8px;
}

.empty-title {
  font-size: 14px;
  color: var(--ink);
  margin-bottom: 4px;
}

.empty-sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
  max-width: 480px;
  line-height: 1.5;
}

.reviews-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.review-item-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px 18px;
  border-radius: 14px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

:global(html.dark) .review-item-card {
  background: #231c20;
  border-color: #382d33;
}

.review-top-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.author-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.author-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: linear-gradient(145deg, var(--primary) 0%, #4a1f2c 100%);
  color: #fff;
  font-size: 14px;
  font-weight: 800;
}

:global(html.dark) .author-avatar {
  background: linear-gradient(145deg, #e58e9f 0%, #8c3b4e 100%);
  color: #1c0a10;
}

.author-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.author-name {
  font-size: 13.5px;
  font-weight: 800;
  color: var(--ink);
}

.sub-meta-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  color: var(--muted);
}

.verified-player {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  color: var(--success-text, #2e7d32);
  font-weight: 600;
}

:global(html.dark) .verified-player {
  color: #4ade80;
}

.icon-12 {
  font-size: 12px;
}

.stars-item {
  display: flex;
  gap: 1px;
}

.item-star {
  font-family: 'Material Symbols Outlined', sans-serif;
  font-size: 16px;
  font-variation-settings: 'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24 !important;
  color: var(--accent, #c9a15a);
}

.star-inactive {
  font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24 !important;
  color: var(--outline);
  opacity: 0.35;
}

.review-content {
  margin: 0;
  font-size: 13px;
  color: var(--ink);
  line-height: 1.5;
}

:global(html.dark) .review-content {
  color: #e8e2e5;
}

@media (max-width: 768px) {
  .rating-dashboard-card {
    grid-template-columns: 1fr;
  }
  .criteria-col {
    border-left: none;
    border-top: 1px solid var(--outline);
    padding-left: 0;
    padding-top: 14px;
  }
}
</style>

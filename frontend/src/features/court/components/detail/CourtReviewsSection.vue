<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { AuraButton } from '@/shared/components'
import BookingReviewModal from '@/features/booking/components/history/BookingReviewModal.vue'
import { useVenueReviews } from '@/features/community/composables/useVenueReviews'
import type { ReportTarget } from '@/features/community/types/community'
import type { Review } from '@/features/community/types/review'
import ReportDialog from '@/features/community/components/ReportDialog.vue'
import ReviewCard from '@/features/community/components/ReviewCard.vue'
import StarRating from '@/features/community/components/StarRating.vue'
import type { Court } from '../../types/court'

// FR-116: reviews belong to the venue, so every court of a venue shows the same list.
// UC-7.10 A-2: the author edits or deletes their review here within the 7-day window (BR-147).
const props = defineProps<{ court: Court }>()

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()
const section = ref<HTMLElement | null>(null)
const { reviews, summary, loading, error, hasMore, loadMore, reload } = useVenueReviews(
  computed(() => props.court.venue_id),
)
const reporting = ref<ReportTarget | null>(null)
const editing = ref<Review | null>(null)
const notice = ref('')

const editable = (review: Review) =>
  review.is_mine && Date.now() <= Date.parse(review.editable_until)
const target = (review: Review) => ({
  code: review.reservation_code,
  review: { ...review, deleted_at: null },
})
function saved(message: string) {
  editing.value = null
  notice.value = message
  void reload()
}

// A review.reply notification links to /courts/{id}#reviews; the list arrives after the page renders.
const stop = watch(loading, async (busy) => {
  if (busy || route.hash !== '#reviews') return
  stop()
  await nextTick()
  section.value?.scrollIntoView({ behavior: 'smooth' })
})
</script>

<template>
  <section id="reviews" ref="section" class="court-reviews">
    <header class="court-reviews-head">
      <h2 class="court-reviews-title">{{ t('reviews.title') }}</h2>
      <div v-if="summary.count" class="court-reviews-summary">
        <strong class="court-reviews-score">{{ Number(summary.average ?? 0).toFixed(1) }}</strong>
        <StarRating :rating="summary.average ?? 0" />
        <span class="court-reviews-muted">{{ t('reviews.count', { count: summary.count }) }}</span>
      </div>
    </header>

    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <p v-if="notice" class="court-reviews-muted" role="status">{{ notice }}</p>
    <p v-if="loading && !reviews.length" class="court-reviews-muted">
      {{ t('common.loading') }}
    </p>
    <p v-else-if="!reviews.length && !error" class="court-reviews-muted">
      {{ t('reviews.empty') }}
    </p>

    <ReviewCard v-for="review in reviews" :key="review.id" :review="review">
      <button
        v-if="auth.user && !review.is_mine"
        type="button"
        class="court-reviews-report"
        @click="reporting = { type: 'review', id: review.id }"
      >
        <span class="material-symbols-outlined">flag</span>{{ t('community.report.action') }}
      </button>
      <button
        v-if="editable(review)"
        type="button"
        class="court-reviews-report"
        @click="editing = review"
      >
        <span class="material-symbols-outlined">edit</span>{{ t('reviews.manage') }}
      </button>
    </ReviewCard>

    <AuraButton v-if="hasMore" variant="outline" :loading="loading" @click="loadMore">
      {{ t('community.loadMore') }}
    </AuraButton>

    <BookingReviewModal
      v-if="editing"
      :booking="target(editing)"
      @close="editing = null"
      @saved="saved"
    />

    <ReportDialog
      v-if="reporting"
      :target="reporting"
      @close="reporting = null"
      @done="reporting = null"
    />
  </section>
</template>

<style scoped>
.court-reviews {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 24px;
  border: 1px solid var(--outline);
  border-radius: 20px;
  background: var(--surface);
  scroll-margin-top: 80px;
}

.court-reviews-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.court-reviews-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
}

.court-reviews-summary {
  display: flex;
  align-items: center;
  gap: 8px;
}

.court-reviews-score {
  font-size: 22px;
}

.court-reviews-muted {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}

.court-reviews-report {
  display: inline-flex;
  align-self: flex-start;
  align-items: center;
  gap: 4px;
  padding: 0;
  border: 0;
  background: none;
  color: var(--muted);
  font-size: 12px;
  cursor: pointer;
}

.court-reviews-report .material-symbols-outlined {
  font-size: 16px;
}
</style>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton, SelectDropdown } from '@/shared/components'
import { useOwnerReviews } from '../composables/useOwnerReviews'
import ReplyEditor from '../components/ReplyEditor.vue'
import ReviewCard from '../components/ReviewCard.vue'
import StarRating from '../components/StarRating.vue'

// FR-117: "Đánh giá khách hàng" — owners reply; they never edit, delete or hide a review.
const { t } = useI18n()
const { venues, venueId, reviews, summary, loading, error, hasMore, loadMore, reply } =
  useOwnerReviews()

const venueOptions = computed(() =>
  venues.value.map((v) => ({ value: v.id, label: resolveLocalizedText(v.name) })),
)
</script>

<template>
  <div class="admin-detail-page">
    <header class="admin-detail-header">
      <div>
        <h1 class="admin-detail-title">{{ t('reviews.owner.title') }}</h1>
        <p class="admin-detail-muted">{{ t('reviews.owner.subtitle') }}</p>
      </div>
      <SelectDropdown
        v-if="venues.length > 1"
        :model-value="venueId"
        :options="venueOptions"
        variant="bordered"
        :aria-label="t('reviews.owner.venue')"
        @update:model-value="venueId = Number($event)"
      />
    </header>

    <p v-if="error" class="admin-detail-error" role="alert">{{ error }}</p>

    <section class="admin-detail-card">
      <p v-if="summary.count" class="owner-reviews-summary">
        <strong>{{ Number(summary.average ?? 0).toFixed(1) }}</strong>
        <StarRating :rating="summary.average ?? 0" />
        <span class="admin-detail-muted">{{ t('reviews.count', { count: summary.count }) }}</span>
      </p>
      <p v-if="loading && !reviews.length" class="admin-detail-muted">
        {{ t('common.loading') }}
      </p>
      <p v-else-if="!venueId && !loading" class="admin-detail-muted">
        {{ t('reviews.owner.noVenues') }}
      </p>
      <p v-else-if="!reviews.length && !error" class="admin-detail-muted">
        {{ t('reviews.empty') }}
      </p>

      <ReviewCard v-for="review in reviews" :key="review.id" :review="review">
        <p class="admin-detail-muted">
          {{ t('reviews.owner.booking', { code: review.reservation_code }) }}
        </p>
        <ReplyEditor
          :review="review"
          @save="async (body, done) => done(await reply(review, body))"
        />
      </ReviewCard>

      <AuraButton v-if="hasMore" variant="outline" :loading="loading" @click="loadMore">
        {{ t('community.loadMore') }}
      </AuraButton>
    </section>
  </div>
</template>

<style scoped>
.owner-reviews-summary {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 8px;
  font-size: 18px;
}
</style>

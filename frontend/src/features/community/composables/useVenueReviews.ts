import { computed, ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { reviewApi } from '../api/reviewApi'
import type { Review, ReviewPage } from '../types/review'

/** FR-116: a venue's public reviews, page by page, with the venue-wide summary. */
export function useVenueReviews(venueId: Ref<number | null | undefined>) {
  const { t } = useI18n()
  const reviews = ref<Review[]>([])
  const summary = ref<ReviewPage['summary']>({ count: 0, average: null })
  const page = ref(1)
  const lastPage = ref(1)
  const loading = ref(false)
  const error = ref('')
  let latest = 0

  async function load(target = 1) {
    const id = venueId.value
    const request = ++latest
    if (!id) {
      reviews.value = []
      return
    }
    loading.value = true
    error.value = ''
    try {
      const res = await reviewApi.venueReviews(id, target)
      if (request !== latest) return
      reviews.value = target === 1 ? res.data : [...reviews.value, ...res.data]
      summary.value = res.summary
      page.value = res.meta.current_page
      lastPage.value = res.meta.last_page
    } catch (e) {
      if (request === latest) error.value = errorMessage(e, t('reviews.loadError'))
    } finally {
      if (request === latest) loading.value = false
    }
  }
  watch(venueId, () => load(1), { immediate: true })

  function replace(review: Review) {
    reviews.value = reviews.value.map((r) => (r.id === review.id ? review : r))
  }

  return {
    reviews,
    summary,
    loading,
    error,
    hasMore: computed(() => page.value < lastPage.value),
    loadMore: () => load(page.value + 1),
    replace,
  }
}

import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { useOwnerCheck } from '@/features/admin/composables/useLifecycleCommands'
import type { Venue } from '@/features/court/types/court'
import { reviewApi } from '../api/reviewApi'
import type { Review } from '../types/review'
import { useVenueReviews } from './useVenueReviews'

/** FR-117: the owner reads one owned venue's reviews and replies; reviews themselves stay read-only. */
export function useOwnerReviews() {
  const { t } = useI18n()
  const isOwnerOf = useOwnerCheck()
  const venues = ref<Venue[]>([])
  const venueId = ref<number | null>(null)
  const venuesError = ref('')
  const list = useVenueReviews(venueId)

  onMounted(async () => {
    try {
      // /admin/venues also lists venues the user only staffs; replying is the owner's alone.
      venues.value = (await reviewApi.ownerVenues()).filter((v) => isOwnerOf(v.organization_id))
      venueId.value = venues.value[0]?.id ?? null
    } catch (e) {
      venuesError.value = errorMessage(e)
    }
  })

  async function reply(review: Review, body: string): Promise<string> {
    try {
      list.replace((await reviewApi.reply(review.id, body.trim())).review)
      return ''
    } catch (e) {
      return errorMessage(e, t('reviews.replyError'))
    }
  }

  return {
    ...list,
    venues,
    venueId,
    error: computed(() => venuesError.value || list.error.value),
    reply,
  }
}

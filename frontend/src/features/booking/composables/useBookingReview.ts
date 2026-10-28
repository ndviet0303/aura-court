import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { reviewApi } from '@/features/community/api/reviewApi'
import type { Reservation } from '../types/reservation'
import { canEditReview } from '../utils/reservation'

/** The booking a review belongs to; the venue's review list builds one from the review (UC-7.10 A-2). */
export type ReviewTarget = Pick<Reservation, 'code' | 'review'>

/** FR-115: write, edit or delete the review of one booking; returns the notice to show on success. */
export function useBookingReview(booking: ReviewTarget) {
  const { t } = useI18n()
  const existing = booking.review && !booking.review.deleted_at ? booking.review : null
  const mode = computed<'create' | 'edit' | 'view'>(() =>
    !existing ? 'create' : canEditReview(booking) ? 'edit' : 'view',
  )
  const rating = ref(existing?.rating ?? 5)
  const body = ref(existing?.body ?? '')
  const images = ref<string[]>([...(existing?.images ?? [])])
  const saving = ref(false)
  const error = ref('')

  async function run(action: () => Promise<unknown>, notice: string): Promise<string | null> {
    saving.value = true
    error.value = ''
    try {
      await action()
      return notice
    } catch (e) {
      error.value = errorMessage(e, t('reviews.saveError'))
      return null
    } finally {
      saving.value = false
    }
  }

  function save(): Promise<string | null> {
    const payload = { rating: rating.value, body: body.value.trim() || null, images: images.value }
    return existing
      ? run(() => reviewApi.update(existing.id, payload), t('reviews.updated'))
      : run(() => reviewApi.create(booking.code, payload), t('reviews.created'))
  }

  // A deleted review stays on the booking, so the customer can never write a new one (BR-147).
  async function remove(): Promise<string | null> {
    if (!existing || !window.confirm(t('reviews.deleteConfirm'))) return null
    return run(() => reviewApi.remove(existing.id), t('reviews.deleted'))
  }

  return { existing, mode, rating, body, images, saving, error, save, remove }
}

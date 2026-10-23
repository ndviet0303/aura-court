import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { venueClock, venueDate, venueInstant } from '@/core/utils/venueTime'
import type { SportType, Venue } from '@/features/court/types/court'
import { communityApi } from '../api/communityApi'
import { POST_MAX, type Post, type PostPayload, type PostType } from '../types/community'

/** The i18n key of what blocks the post, or null; mirrors the POST /social/feed rules. */
export function postProblem(payload: PostPayload): string | null {
  if (!payload.content.trim()) return 'community.composer.contentRequired'
  if (payload.content.length > POST_MAX) return 'community.composer.contentTooLong'
  if (payload.type !== 'matchmaking') return null
  if (!payload.sport) return 'community.composer.sportRequired'
  if (!payload.play_at) return 'community.composer.playAtRequired'
  if (!payload.venue_id && !payload.address?.trim()) return 'community.composer.placeRequired'
  return null
}

let venueCache: Promise<Venue[]> | null = null

/** FR-112: create a post, or edit `initial` with the same form. */
export function usePostComposer(initial: Post | null) {
  const { t } = useI18n()
  const form = reactive({
    type: (initial?.type ?? 'share') as PostType,
    content: initial?.content ?? '',
    images: [...(initial?.images ?? [])],
    sport: (initial?.sport ?? '') as SportType | '',
    venueId: initial?.venue?.id ?? 0,
    address: initial?.address ?? '',
    courtCode: initial?.court_code ?? '',
    // datetime-local value on the venue clock
    playAt: initial?.play_at ? `${venueDate(initial.play_at)}T${venueClock(initial.play_at)}` : '',
  })
  const venues = ref<Venue[]>([])
  const saving = ref(false)
  const error = ref('')

  const payload = computed<PostPayload>(() => ({
    type: form.type,
    content: form.content,
    images: form.images,
    ...(form.type === 'matchmaking'
      ? {
          sport: form.sport || null,
          venue_id: form.venueId || null,
          address: form.address.trim() || null,
          court_code: form.courtCode.trim() || null,
          play_at: form.playAt ? venueInstant(form.playAt) : null,
        }
      : {}),
  }))

  // The venue list is public and rarely changes; load it once, when a matchmaking form needs it.
  watch(
    () => form.type,
    async (type) => {
      if (type !== 'matchmaking' || venues.value.length) return
      venueCache ??= communityApi.venues().catch(() => {
        venueCache = null
        return []
      })
      venues.value = await venueCache
    },
    { immediate: true },
  )

  async function submit(): Promise<Post | null> {
    const problem = postProblem(payload.value)
    if (problem) {
      error.value = t(problem)
      return null
    }
    saving.value = true
    error.value = ''
    try {
      const { post } = initial
        ? await communityApi.update(initial.id, payload.value)
        : await communityApi.create(payload.value)
      return post
    } catch (e) {
      error.value = errorMessage(e, t('community.composer.saveError'))
      return null
    } finally {
      saving.value = false
    }
  }

  return { form, venues, saving, error, submit }
}

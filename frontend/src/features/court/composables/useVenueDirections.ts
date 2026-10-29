import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import { courtApi } from '../api/courtApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court, Venue } from '../types/court'

export function useVenueDirections() {
  const route = useRoute()
  const store = useBookingStore()

  const loading = ref(true)
  const error = ref('')
  const copied = ref(false)
  const directVenue = ref<Venue | null>(null)
  const matchedCourt = ref<Court | null>(null)

  // A court id, or a venue slug from the venue QR and card links.
  const key = computed(() => String(route.params.id || ''))

  const venue = computed<Venue | null>(() => {
    if (directVenue.value) return directVenue.value
    if (matchedCourt.value?.venue) return matchedCourt.value.venue

    // Check store courts by court id, then by venue slug or id
    const lookup = key.value.toLowerCase()
    const courtMatch = store.courts.find((c) => String(c.id) === lookup)
    if (courtMatch?.venue) return courtMatch.venue

    const venueMatch = store.courts.find(
      (c) => c.venue?.slug?.toLowerCase() === lookup || String(c.venue?.id) === lookup,
    )
    // An unknown id or slug shows no venue rather than another venue.
    return venueMatch?.venue || null
  })

  const courtsAtVenue = computed<Court[]>(() => {
    if (!venue.value) return store.courts
    return store.courts.filter((c) => c.venue?.id === venue.value?.id)
  })

  async function resolveVenue() {
    loading.value = true
    error.value = ''
    try {
      if (store.courts.length === 0) {
        await store.loadCourts()
      }

      const lookup = key.value
      if (lookup) {
        // 1. Try finding in store courts
        const foundCourt = store.courts.find((c) => String(c.id) === lookup)
        if (foundCourt) {
          matchedCourt.value = foundCourt
          if (foundCourt.venue) {
            directVenue.value = foundCourt.venue
          }
          return
        }

        // 2. Try fetching venue from API
        try {
          const v = await courtApi.getVenueBySlug(encodeURIComponent(lookup))
          if (v) {
            directVenue.value = v
            return
          }
        } catch {
          // If venue API fails, try court API
        }

        try {
          if (!/^\d+$/.test(lookup)) return
          const c = await courtApi.getById(lookup)
          if (c) {
            matchedCourt.value = c
            if (c.venue) {
              directVenue.value = c.venue
            }
            return
          }
        } catch {
          // Ignore
        }
      }
      if (!venue.value) error.value = 'Không tìm thấy địa điểm được yêu cầu.'
    } catch (cause) {
      error.value = errorMessage(cause, 'Không thể tải thông tin địa điểm.')
    } finally {
      loading.value = false
    }
  }

  async function copyAddress(): Promise<void> {
    const addr = venue.value?.address
    if (!addr) return
    try {
      await navigator.clipboard.writeText(addr)
      copied.value = true
      window.setTimeout(() => {
        copied.value = false
      }, 2000)
    } catch {
      // fallback
    }
  }

  function openGoogleMaps(): void {
    const v = venue.value
    const mapsUrl = v?.google_maps_url
    if (mapsUrl && (mapsUrl.startsWith('http://') || mapsUrl.startsWith('https://'))) {
      window.open(mapsUrl, '_blank', 'noopener,noreferrer')
      return
    }
    const venueName = v?.name || 'Aura Court'
    const venueAddress = v?.address || 'Hà Nội'
    const query = encodeURIComponent(`${venueName}, ${venueAddress}`)
    window.open(
      `https://www.google.com/maps/search/?api=1&query=${query}`,
      '_blank',
      'noopener,noreferrer',
    )
  }

  function shareLocation(): void {
    const v = venue.value
    if (!v) return
    const url = window.location.href
    if (navigator.share) {
      navigator
        .share({
          title: `${v.name} - Aura Court`,
          text: `Địa chỉ: ${v.address}`,
          url,
        })
        .catch(() => {})
    } else {
      copyAddress()
    }
  }

  onMounted(() => {
    resolveVenue()
  })

  watch(key, () => {
    resolveVenue()
  })

  return {
    venue,
    matchedCourt,
    courtsAtVenue,
    loading,
    error,
    copied,
    copyAddress,
    openGoogleMaps,
    shareLocation,
    resolveVenue,
  }
}

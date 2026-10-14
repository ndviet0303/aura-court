import { computed, ref, type Ref } from 'vue'
import type { Court } from '@/features/court/types/court'
import { filterCourts, groupCourtAreas, type LatLng } from '../utils/courtArea'

export type LocateError = '' | 'denied' | 'unavailable'

/** Area pills, court search and the "near me" sort for the home availability picker. */
export function useCourtAreaFilter(
  courts: Readonly<Ref<Court[]>>,
  otherAreaLabel: Readonly<Ref<string>>,
) {
  const selectedArea = ref<string | null>(null)
  const query = ref('')
  // Kept in memory only; never sent to the server.
  const origin = ref<LatLng | null>(null)
  const locating = ref(false)
  const locateError = ref<LocateError>('')

  const areas = computed(() => groupCourtAreas(courts.value, origin.value, otherAreaLabel.value))
  const filteredCourts = computed(() =>
    filterCourts(courts.value, selectedArea.value, query.value, origin.value),
  )

  /** Toggles the nearest-first sort; resolves true once a position is known. */
  function locate(): Promise<boolean> {
    locateError.value = ''
    if (origin.value) {
      origin.value = null
      return Promise.resolve(false)
    }
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      locateError.value = 'unavailable'
      return Promise.resolve(false)
    }
    locating.value = true
    return new Promise((resolve) => {
      navigator.geolocation.getCurrentPosition(
        ({ coords }) => {
          origin.value = { lat: coords.latitude, lng: coords.longitude }
          selectedArea.value = null
          locating.value = false
          resolve(true)
        },
        (err) => {
          locateError.value = err.code === err.PERMISSION_DENIED ? 'denied' : 'unavailable'
          locating.value = false
          resolve(false)
        },
        { timeout: 10000, maximumAge: 300000 },
      )
    })
  }

  function reset() {
    selectedArea.value = null
    query.value = ''
  }

  return {
    selectedArea,
    query,
    origin,
    locating,
    locateError,
    areas,
    filteredCourts,
    locate,
    reset,
  }
}

import { ref, computed, onMounted } from 'vue'
import { useBookingStore } from '@/stores/booking'
import type { Court } from '../types/court'

export function useCourtsList() {
  const store = useBookingStore()

  const searchQuery = ref('')
  const selectedSportType = ref('all')
  const selectedVenue = ref('all')
  const maxPrice = ref(500000)
  const sortBy = ref('popular')

  const sportOptions = [
    { value: 'all', label: 'Tất cả môn thể thao', icon: 'sports' },
    { value: 'badminton', label: 'Cầu lông', icon: 'sports_tennis' },
    { value: 'tennis', label: 'Tennis', icon: 'sports_tennis' },
    { value: 'pickleball', label: 'Pickleball', icon: 'sports_tennis' },
  ]

  const venueOptions = computed(() => {
    const venues = new Set<string>()
    store.courts.forEach((c) => {
      if (c.venue?.name) venues.add(c.venue.name)
    })
    return ['all', ...Array.from(venues)]
  })

  const filteredCourts = computed(() => {
    return store.courts.filter((c) => {
      const q = searchQuery.value.trim().toLowerCase()
      const matchSearch =
        !q ||
        c.name.toLowerCase().includes(q) ||
        (c.venue?.name && c.venue.name.toLowerCase().includes(q))

      const matchVenue =
        selectedVenue.value === 'all' || (c.venue?.name && c.venue.name.includes(selectedVenue.value))

      return matchSearch && matchVenue
    })
  })

  return {
    store,
    searchQuery,
    selectedSportType,
    selectedVenue,
    maxPrice,
    sortBy,
    sportOptions,
    venueOptions,
    filteredCourts,
  }
}

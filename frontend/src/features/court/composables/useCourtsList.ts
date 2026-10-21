import { ref, computed } from 'vue'
import { useBookingStore } from '@/stores/booking'
import { i18n } from '@/i18n'
import type { Court } from '../types/court'

export function courtMatchesSport(court: Court, sport: string): boolean {
  if (!sport || sport === 'all') return true
  // The "football" filter covers both pitch sizes.
  if (sport === 'football') return court.sport_type.startsWith('football')
  return court.sport_type === sport
}

export function courtMatchesPriceRange(court: Court, range: string): boolean {
  if (!range || range === 'all') return true
  const price = court.from_price
  // A court without a price matches no price range.
  if (price == null) return false
  if (range === 'under_100k') return price < 100000
  if (range === '100k_150k') return price >= 100000 && price <= 150000
  if (range === '150k_200k') return price >= 150000 && price <= 200000
  if (range === 'over_200k') return price > 200000
  return true
}

export function useCourtsList() {
  const store = useBookingStore()

  const searchQuery = ref('')
  const selectedSportType = ref('all')
  const selectedVenue = ref('all')
  const selectedPriceRange = ref('all')
  const sortBy = ref('popular')
  const maxPrice = ref(500000)

  const sportCategories = [
    { id: 'all', labelKey: 'courts.allSports', defaultLabel: 'Tất cả môn', icon: 'apps' },
    {
      id: 'badminton',
      labelKey: 'courts.sportBadminton',
      defaultLabel: 'Cầu lông',
      icon: 'sports_tennis',
    },
    {
      id: 'pickleball',
      labelKey: 'courts.sportPickleball',
      defaultLabel: 'Pickleball',
      icon: 'sports_tennis',
    },
    { id: 'tennis', labelKey: 'courts.sportTennis', defaultLabel: 'Tennis', icon: 'sports_tennis' },
    {
      id: 'basketball',
      labelKey: 'courts.sportBasketball',
      defaultLabel: 'Bóng rổ',
      icon: 'sports_basketball',
    },
    {
      id: 'football',
      labelKey: 'courts.sportFootball',
      defaultLabel: 'Bóng đá',
      icon: 'sports_soccer',
    },
    {
      id: 'table_tennis',
      labelKey: 'courts.sportTableTennis',
      defaultLabel: 'Bóng bàn',
      icon: 'table_restaurant',
    },
    {
      id: 'volleyball',
      labelKey: 'courts.sportVolleyball',
      defaultLabel: 'Bóng chuyền',
      icon: 'sports_volleyball',
    },
  ]

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

  const hasActiveFilters = computed(() => {
    return (
      searchQuery.value.trim() !== '' ||
      selectedSportType.value !== 'all' ||
      selectedVenue.value !== 'all' ||
      selectedPriceRange.value !== 'all' ||
      sortBy.value !== 'popular'
    )
  })

  function resetFilters() {
    searchQuery.value = ''
    selectedSportType.value = 'all'
    selectedVenue.value = 'all'
    selectedPriceRange.value = 'all'
    sortBy.value = 'popular'
  }

  const filteredCourts = computed(() => {
    const list = store.courts.filter((c) => {
      const q = searchQuery.value.trim().toLowerCase()
      const matchSearch =
        !q ||
        c.name.toLowerCase().includes(q) ||
        (c.venue?.name && c.venue.name.toLowerCase().includes(q)) ||
        (c.venue?.address && c.venue.address.toLowerCase().includes(q)) ||
        i18n.global.t(`sportTypes.${c.sport_type}`).toLowerCase().includes(q)

      const matchVenue =
        selectedVenue.value === 'all' ||
        (c.venue?.name && c.venue.name.includes(selectedVenue.value))

      const matchSport = courtMatchesSport(c, selectedSportType.value)
      const matchPrice = courtMatchesPriceRange(c, selectedPriceRange.value)

      return matchSearch && matchVenue && matchSport && matchPrice
    })

    // Sort courts
    const sorted = [...list]
    switch (sortBy.value) {
      // Courts without a price sort last both ways.
      case 'price_asc':
        return sorted.sort((a, b) => (a.from_price ?? Infinity) - (b.from_price ?? Infinity))
      case 'price_desc':
        return sorted.sort((a, b) => (b.from_price ?? -Infinity) - (a.from_price ?? -Infinity))
      case 'rating_desc':
        return sorted.sort((a, b) => (b.reviews_avg_rating || 0) - (a.reviews_avg_rating || 0))
      case 'name_asc':
        return sorted.sort((a, b) => a.name.localeCompare(b.name, 'vi'))
      case 'popular':
      default:
        return sorted
    }
  })

  return {
    store,
    searchQuery,
    selectedSportType,
    selectedVenue,
    selectedPriceRange,
    sortBy,
    maxPrice,
    sportCategories,
    sportOptions,
    venueOptions,
    hasActiveFilters,
    resetFilters,
    filteredCourts,
  }
}

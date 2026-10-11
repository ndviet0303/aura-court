import { ref, computed } from 'vue'
import { useBookingStore } from '@/stores/booking'
import type { Court } from '../types/court'

export function getCourtMinPrice(court: Court): number {
  if (court.price_slots && court.price_slots.length > 0) {
    const prices = court.price_slots.map((s) => s.price).filter((p) => p > 0)
    if (prices.length > 0) return Math.min(...prices)
  }
  return 100000
}

export function courtMatchesSport(court: Court, sport: string): boolean {
  if (!sport || sport === 'all') return true
  const zoneSport = court.sport_zone?.sport_type?.toLowerCase() || ''
  const zoneName = court.sport_zone?.name?.toLowerCase() || ''
  const courtName = court.name.toLowerCase()
  const courtDesc = (court.description || '').toLowerCase()

  if (zoneSport === sport) return true

  if (sport === 'badminton') {
    return (
      zoneSport === 'badminton' ||
      zoneName.includes('cầu lông') ||
      zoneName.includes('badminton') ||
      courtName.includes('cầu lông') ||
      courtName.includes('bwf') ||
      courtDesc.includes('cầu lông')
    )
  }
  if (sport === 'pickleball') {
    return (
      zoneSport === 'pickleball' ||
      zoneName.includes('pickleball') ||
      courtName.includes('pickleball') ||
      courtDesc.includes('pickleball')
    )
  }
  if (sport === 'tennis') {
    return (
      zoneSport === 'tennis' ||
      zoneName.includes('tennis') ||
      courtName.includes('tennis') ||
      courtDesc.includes('tennis')
    )
  }
  if (sport === 'basketball') {
    return (
      zoneSport === 'basketball' ||
      zoneName.includes('bóng rổ') ||
      zoneName.includes('basketball') ||
      courtName.includes('bóng rổ') ||
      courtDesc.includes('bóng rổ')
    )
  }
  if (sport === 'football' || sport === 'football_5v5' || sport === 'football_7v7') {
    return (
      zoneSport.startsWith('football') ||
      zoneName.includes('bóng đá') ||
      courtName.includes('bóng đá') ||
      courtDesc.includes('bóng đá')
    )
  }
  if (sport === 'table_tennis') {
    return (
      zoneSport === 'table_tennis' ||
      zoneName.includes('bóng bàn') ||
      courtName.includes('bóng bàn') ||
      courtDesc.includes('bóng bàn')
    )
  }
  if (sport === 'volleyball') {
    return (
      zoneSport === 'volleyball' ||
      zoneName.includes('bóng chuyền') ||
      courtName.includes('bóng chuyền') ||
      courtDesc.includes('bóng chuyền')
    )
  }

  return false
}

export function courtMatchesPriceRange(court: Court, range: string): boolean {
  if (!range || range === 'all') return true
  const price = getCourtMinPrice(court)
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
        (c.sport_zone?.name && c.sport_zone.name.toLowerCase().includes(q))

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
      case 'price_asc':
        return sorted.sort((a, b) => getCourtMinPrice(a) - getCourtMinPrice(b))
      case 'price_desc':
        return sorted.sort((a, b) => getCourtMinPrice(b) - getCourtMinPrice(a))
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

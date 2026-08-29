import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import type { Court } from '../types/court'

export function useCourtDetail() {
  const route = useRoute()
  const bookingStore = useBookingStore()

  const selectedDuration = ref<number>(1.0)
  const isGalleryOpen = ref(false)

  const courtId = Number(route.params.id || 1)

  const court = computed<Court>(() => {
    return (
      bookingStore.courts.find((c) => c.id === courtId) ||
      bookingStore.courts[0] || {
        id: 1,
        name: 'Sân 01 - Tiêu Chuẩn',
        slug: 'san-01-tieu-chuan',
        venue: {
          id: 1,
          name: 'Aura Center Thảo Điền',
          slug: 'aura-center-thao-dien',
          address: '12 Quốc Hương, Thảo Điền, Quận 2',
          google_maps_url: null,
          is_active: true,
          share_url: '',
        },
        description: 'Sân thi đấu chuyên nghiệp với mặt thảm cao cấp',
        price_slots: [
          { id: 1, court_id: 1, weekday: 1, starts_at: '06:00', ends_at: '16:00', price: 120000 },
          { id: 2, court_id: 1, weekday: 1, starts_at: '16:00', ends_at: '22:00', price: 180000 },
        ],
        reviews_count: 42,
        reviews_avg_rating: 4.8,
      }
    )
  })

  const basePrice = computed(() => {
    if (court.value.price_slots && court.value.price_slots.length > 0) {
      return court.value.price_slots[0]?.price || 120000
    }
    return 120000
  })

  const estimatedTotal = computed(() => {
    return basePrice.value * selectedDuration.value
  })

  const durationOptions = [
    { value: 1.0, label: '1.0h' },
    { value: 1.5, label: '1.5h' },
    { value: 2.0, label: '2.0h' },
    { value: 3.0, label: '3.0h' },
  ]

  return {
    court,
    selectedDuration,
    isGalleryOpen,
    basePrice,
    estimatedTotal,
    durationOptions,
  }
}

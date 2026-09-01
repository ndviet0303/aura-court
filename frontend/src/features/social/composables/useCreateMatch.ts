import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/core/api/client'
import { socialApi } from '../api/socialApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court } from '@/features/court/types/court'

export interface FriendInvite {
  id: number
  name: string
  avatar: string
  skill: string
  isSelected: boolean
}

export function useCreateMatch() {
  const router = useRouter()
  const loading = ref(false)
  const submitting = ref(false)
  const notice = ref('')
  const error = ref('')

  const courts = ref<Court[]>([])
  const matchTitle = ref('')
  const selectedSport = ref<'badminton' | 'pickleball' | 'tennis'>('badminton')
  const selectedVenueName = ref('Aura Court Cầu Giấy')
  const selectedCourtId = ref<number>(1)
  const matchDate = ref(new Date().toISOString().slice(0, 10))
  const matchTime = ref('19:00')
  const duration = ref(2)

  const matchFormat = ref<'doubles' | 'singles'>('doubles')
  const skillLevel = ref<'all' | 'intermediate' | 'advanced'>('intermediate')
  const splitCost = ref(true)
  const costPerPerson = ref(60000)
  const maxPlayers = ref(4)

  const quickVenues = [
    { name: 'Aura Court Cầu Giấy', slug: 'aura-court' },
    { name: 'Aura Arena Quần Ngựa', slug: 'aura-arena-quan-ngua' },
    { name: 'Aura Sports Complex Tây Hồ', slug: 'aura-complex-tay-ho' },
    { name: 'Aura Sports Center Sài Gòn', slug: 'aura-central-saigon' },
  ]

  const friends = ref<FriendInvite[]>([
    { id: 1, name: 'Hoàng Minh Tuấn', avatar: '', skill: 'Level 4', isSelected: false },
    { id: 2, name: 'Trần Đăng Khoa', avatar: '', skill: 'Level 3', isSelected: false },
    { id: 3, name: 'Nguyễn Bích Ngọc', avatar: '', skill: 'Level 2', isSelected: false },
  ])

  async function loadCourts() {
    loading.value = true
    try {
      const res = await api.get<Court[]>('/courts')
      courts.value = res || []
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách sân.')
    } finally {
      loading.value = false
    }
  }

  function toggleFriend(id: number) {
    const f = friends.value.find((item) => item.id === id)
    if (f) f.isSelected = !f.isSelected
  }

  async function createMatch() {
    submitting.value = true
    error.value = ''
    try {
      const startDate = new Date(`${matchDate.value}T${matchTime.value}:00`)
      // Ensure start date is valid and in the future (if past today, bump to next hour)
      if (startDate.getTime() <= Date.now()) {
        startDate.setTime(Date.now() + 3600 * 1000)
      }
      const endDate = new Date(startDate.getTime() + duration.value * 3600 * 1000)

      const sportNameVi =
        selectedSport.value === 'badminton'
          ? 'Cầu lông'
          : selectedSport.value === 'pickleball'
            ? 'Pickleball'
            : 'Tennis'

      const generatedTitle =
        matchTitle.value.trim() ||
        `Tìm bạn ghép kèo ${sportNameVi} (${matchFormat.value === 'doubles' ? 'Đánh đôi' : 'Đánh đơn'}) @ ${selectedVenueName.value}`

      const payload = {
        title: generatedTitle,
        visibility: 'public' as const,
        starts_at: startDate.toISOString(),
        ends_at: endDate.toISOString(),
        city: selectedVenueName.value.includes('Sài Gòn') ? 'TP. Hồ Chí Minh' : 'Hà Nội',
        min_skill: skillLevel.value === 'advanced' ? 4 : skillLevel.value === 'intermediate' ? 2 : 1,
        max_skill: 5,
        required_participants: maxPlayers.value,
        availability: {
          sport: selectedSport.value,
          format: matchFormat.value,
          split_cost: splitCost.value,
          cost_per_person: costPerPerson.value,
          court_id: selectedCourtId.value,
          venue_name: selectedVenueName.value,
        },
      }

      await socialApi.createMatch(payload)
      notice.value = 'Tạo kèo giao lưu & ghép trận thành công!'
      setTimeout(() => {
        router.push({ path: '/matchmaking' })
      }, 1000)
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tạo trận đấu.')
    } finally {
      submitting.value = false
    }
  }

  return {
    loading,
    submitting,
    notice,
    error,
    courts,
    matchTitle,
    selectedSport,
    selectedVenueName,
    selectedCourtId,
    matchDate,
    matchTime,
    duration,
    matchFormat,
    skillLevel,
    splitCost,
    costPerPerson,
    maxPlayers,
    quickVenues,
    friends,
    loadCourts,
    toggleFriend,
    createMatch,
    submitMatch: createMatch,
  }
}

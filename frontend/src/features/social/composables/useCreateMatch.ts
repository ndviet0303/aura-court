import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, apiV2 } from '@/core/api/client'
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
  const selectedSport = ref<'badminton' | 'pickleball' | 'tennis'>('badminton')
  const selectedVenueName = ref('Aura Central')
  const selectedCourtId = ref<number>(1)
  const matchDate = ref('2026-08-30')
  const matchTime = ref('18:00')
  const duration = ref(2)

  const matchFormat = ref<'doubles' | 'singles'>('doubles')
  const skillLevel = ref<'all' | 'intermediate' | 'advanced'>('intermediate')
  const splitCost = ref(true)
  const costPerPerson = ref(60000)
  const maxPlayers = ref(4)

  const quickVenues = [
    { name: 'Aura Central', slug: 'aura-central' },
    { name: 'Aura Riverside', slug: 'aura-riverside' },
    { name: 'Aura District 7', slug: 'aura-d7' },
  ]

  const friends = ref<FriendInvite[]>([
    { id: 1, name: 'Minh Hoàng', avatar: 'https://api.dicebear.com/7.x/bottts/svg?seed=Hoang', skill: 'Cao cấp', isSelected: false },
    { id: 2, name: 'Hải Đăng', avatar: 'https://api.dicebear.com/7.x/bottts/svg?seed=Dang', skill: 'Trung bình+', isSelected: true },
    { id: 3, name: 'Thanh Trúc', avatar: 'https://api.dicebear.com/7.x/bottts/svg?seed=Truc', skill: 'Trung bình', isSelected: false },
  ])

  async function loadCourts() {
    loading.value = true
    try {
      const res = await api.get<Court[]>('/courts')
      courts.value = res || []
    } catch (e) {
      // fallback
    } finally {
      loading.value = false
    }
  }

  function toggleFriend(id: number) {
    const f = friends.value.find((item) => item.id === id)
    if (f) f.isSelected = !f.isSelected
  }

  async function submitMatch() {
    submitting.value = true
    try {
      const payload = {
        sport: selectedSport.value,
        court_id: selectedCourtId.value,
        venue_name: selectedVenueName.value,
        starts_at: `${matchDate.value}T${matchTime.value}:00Z`,
        duration_hours: duration.value,
        format: matchFormat.value,
        skill_level: skillLevel.value,
        split_cost: splitCost.value,
        cost_per_person: costPerPerson.value,
        max_players: maxPlayers.value,
        invited_friend_ids: friends.value.filter((f) => f.isSelected).map((f) => f.id),
      }
      const res = await apiV2.post<{ match: { id: number; title: string } }>('/social/matches', payload)
      notice.value = 'Khởi tạo kèo giao lưu thành công! Đang chuyển hướng...'
      setTimeout(() => {
        router.push({ path: '/matchmaking' })
      }, 1000)
    } catch (e) {
      error.value = errorMessage(e, 'Không thể tạo kèo giao lưu.')
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
    submitMatch,
  }
}

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
  const matchDate = ref(new Date().toISOString().slice(0, 10))
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

  const friends = ref<FriendInvite[]>([])

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
      const payload = {
        court_id: selectedCourtId.value,
        starts_at: `${matchDate.value} ${matchTime.value}:00`,
        duration_hours: duration.value,
        sport: selectedSport.value,
        format: matchFormat.value,
        min_skill: skillLevel.value === 'advanced' ? 4 : skillLevel.value === 'intermediate' ? 2 : 1,
        max_skill: 5,
        required_participants: maxPlayers.value,
        split_cost: splitCost.value,
        cost_per_person: costPerPerson.value,
        invited_user_ids: friends.value.filter((f) => f.isSelected).map((f) => f.id),
      }

      await apiV2.post('/social/matches', payload)
      notice.value = 'Tạo kèo giao lưu & ghép trận thành công!'
      setTimeout(() => {
        router.push({ path: '/matchmaking' })
      }, 1200)
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

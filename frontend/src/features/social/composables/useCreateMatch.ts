import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/core/api/client'
import { socialApi } from '../api/socialApi'
import { playLevelLabel } from './useMatchmaking'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court } from '@/features/court/types/court'
import type {
  SocialMatch,
  SocialSportType,
  DropInSessionCategory,
  BadmintonSkillLabel,
} from '../types/social'
import type { DropdownOption } from '@/shared/components'

export interface FriendInvite {
  id: number
  name: string
  avatar: string
  skill: string
  skillLevel: number
  reputation: number
  city: string
  isSelected: boolean
}

export function useCreateMatch() {
  const router = useRouter()
  const auth = useAuthStore()

  const loading = ref(false)
  const submitting = ref(false)
  const notice = ref('')
  const error = ref('')

  // Form Fields
  const matchTitle = ref('')
  const selectedSport = ref<SocialSportType>('badminton')
  const selectedVenueName = ref('Aura Court Cầu Giấy')
  const selectedCourtId = ref<number | null>(null)

  // Date & Time (Default to today/tomorrow next round hour)
  const todayStr = new Date().toISOString().slice(0, 10)
  const matchDate = ref(todayStr)
  const matchTime = ref('19:00')
  const duration = ref(2)

  // Match Parameters
  const matchFormat = ref<'doubles' | 'singles' | 'team'>('doubles')
  const skillLevel = ref<'all' | 'intermediate' | 'advanced'>('intermediate')
  const splitCost = ref(true)
  const costPerPerson = ref(50000)
  const maxPlayers = ref(4)
  const visibility = ref<'public' | 'invite_only'>('public')
  const matchDescription = ref('')

  // Owner-hosted drop-in session fields
  const isOwnerHostedSession = ref(false)
  const sessionCategory = ref<DropInSessionCategory>('giao lưu')
  const acceptedSkillLabels = ref<BadmintonSkillLabel[]>(['TBY', 'TB'])
  const availableSkillLabels: BadmintonSkillLabel[] = ['newbie', 'Y', 'TBY', 'TB', 'K']
  // Venues & Courts
  const courts = ref<Court[]>([])

  const quickVenues = [
    { name: 'Aura Court Cầu Giấy', slug: 'aura-court', city: 'Hà Nội' },
    { name: 'Aura Arena Quần Ngựa', slug: 'aura-arena-quan-ngua', city: 'Hà Nội' },
    { name: 'Aura Sports Complex Tây Hồ', slug: 'aura-complex-tay-ho', city: 'Hà Nội' },
    { name: 'Aura Sports Center Sài Gòn', slug: 'aura-central-saigon', city: 'TP. Hồ Chí Minh' },
  ]

  // Friends & Invite Network
  const friendSearchQuery = ref('')
  const friends = ref<FriendInvite[]>([])
  const loadingFriends = ref(false)

  async function loadFriends(query?: string) {
    loadingFriends.value = true
    try {
      const res = await socialApi.getFriends(query)
      const list = (res as any)?.friends || []
      const previouslySelected = new Set(selectedFriends.value.map((f) => f.id))
      friends.value = list.map((f: any) => ({
        id: f.id,
        name: f.name || 'Người chơi',
        avatar: f.avatar_url || '',
        skill: playLevelLabel(f.play_level),
        skillLevel: f.play_level || 3,
        reputation: 95,
        city: f.city || 'Hà Nội',
        isSelected: previouslySelected.has(f.id),
      }))
    } catch {
      friends.value = []
    } finally {
      loadingFriends.value = false
    }
  }

  watch(friendSearchQuery, (q) => {
    loadFriends(q)
  })

  const filteredFriends = computed(() => {
    if (!friendSearchQuery.value.trim()) return friends.value
    const q = friendSearchQuery.value.toLowerCase()
    return friends.value.filter(
      (f) => f.name.toLowerCase().includes(q) || f.skill.toLowerCase().includes(q),
    )
  })

  const selectedFriends = computed(() => friends.value.filter((f) => f.isSelected))
  const selectedFriendsCount = computed(() => selectedFriends.value.length)

  // Court Options for SelectDropdown
  const courtOptions = computed<DropdownOption[]>(() => {
    const defaultOpt: DropdownOption = {
      value: 0,
      label: 'Sân tự do / Chưa cố định sân cụ thể',
      icon: 'sports_tennis',
    }
    const list: DropdownOption[] = courts.value.map((c: any) => ({
      value: c.id,
      label: `${c.name} ${c.venue ? `(${c.venue.name})` : ''}`,
      sublabel: `${c.sport_type || c.sport?.name || 'Thể thao'} · ${c.price_per_hour ? new Intl.NumberFormat('vi-VN').format(c.price_per_hour) + '₫/h' : 'Tiêu chuẩn'}`,
      icon: 'stadium',
    }))
    return [defaultOpt, ...list]
  })

  // Sport name display helper
  const sportNameVi = computed(() => {
    switch (selectedSport.value) {
      case 'pickleball':
        return 'Pickleball'
      case 'tennis':
        return 'Tennis'
      case 'football':
        return 'Bóng đá Futsal'
      case 'basketball':
        return 'Bóng rổ'
      case 'volleyball':
        return 'Bóng chuyền'
      case 'table_tennis':
        return 'Bóng bàn'
      case 'badminton':
      default:
        return 'Cầu lông'
    }
  })

  const sportIconName = computed(() => {
    switch (selectedSport.value) {
      case 'football':
        return 'sports_soccer'
      case 'basketball':
        return 'sports_basketball'
      case 'volleyball':
        return 'sports_volleyball'
      default:
        return 'sports_tennis'
    }
  })

  const formatNameVi = computed(() => {
    switch (matchFormat.value) {
      case 'singles':
        return 'Đánh đơn'
      case 'team':
        return 'Đấu đội'
      case 'doubles':
      default:
        return 'Đánh đôi'
    }
  })

  const generatedAutoTitle = computed(() => {
    return `Tìm bạn ghép kèo ${sportNameVi.value} (${formatNameVi.value}) @ ${selectedVenueName.value}`
  })

  const displayTitle = computed(() => {
    return matchTitle.value.trim() || generatedAutoTitle.value
  })

  // Real-time Live Preview Match
  const previewMatch = computed<SocialMatch>(() => {
    const selectedCourtObj = courts.value.find((c) => c.id === selectedCourtId.value)
    const hostUser = auth.user

    const minSk = skillLevel.value === 'advanced' ? 4 : skillLevel.value === 'intermediate' ? 2 : 1
    const maxSk = 5

    // Format start time string
    let formattedStart = `${matchTime.value} · ${matchDate.value}`
    try {
      const [y, m, d] = matchDate.value.split('-')
      if (y && m && d) {
        formattedStart = `${matchTime.value} · ${d}/${m}/${y}`
      }
    } catch {
      // fallback
    }

    const isOwnerHosted = isOwnerHostedSession.value
    const hostData = hostUser as {
      id?: number
      name?: string
      avatar_url?: string
      avatar?: string
    } | null
    const participantsList = isOwnerHosted
      ? selectedFriends.value.map((f) => ({
          id: f.id,
          name: f.name,
          avatar: f.avatar,
          skill_level: f.skillLevel,
          is_host: false,
          role: 'Member',
        }))
      : [
          {
            id: hostData?.id || 1,
            name: hostData?.name || 'Tôi (Chủ phòng)',
            avatar: hostData?.avatar_url || hostData?.avatar,
            skill_level: 3,
            is_host: true,
            role: 'Host',
          },
          ...selectedFriends.value.map((f) => ({
            id: f.id,
            name: f.name,
            avatar: f.avatar,
            skill_level: f.skillLevel,
            is_host: false,
            role: 'Member',
          })),
        ]
    return {
      id: 9999,
      title: displayTitle.value,
      sport_type: selectedSport.value,
      sport_name: sportNameVi.value,
      sport_icon: sportIconName.value,
      venue_name: selectedVenueName.value,
      court_name: selectedCourtObj ? selectedCourtObj.name : undefined,
      city: selectedVenueName.value.includes('Sài Gòn') ? 'TP. Hồ Chí Minh' : 'Hà Nội',
      starts_at: formattedStart,
      joined_count: isOwnerHosted ? selectedFriendsCount.value : 1 + selectedFriendsCount.value,
      required_participants: maxPlayers.value,
      visibility: visibility.value,
      min_skill: minSk,
      max_skill: maxSk,
      game_format: formatNameVi.value,
      cost_per_person: isOwnerHosted ? undefined : costPerPerson.value,
      split_bill_mode: isOwnerHosted
        ? 'Chia đều sau buổi'
        : splitCost.value
          ? 'auto_split'
          : 'host_pays',
      host_id: hostData?.id || 1,
      host_name: hostData?.name || (isOwnerHosted ? 'Tôi (Chủ cơ sở)' : 'Tôi (Chủ phòng)'),
      host_avatar: hostData?.avatar_url || hostData?.avatar,
      host_reputation: 99,
      host_level: isOwnerHosted ? 'Chủ cơ sở' : 'Level 3.5',
      description:
        matchDescription.value.trim() ||
        'Mở buổi giao lưu thân thiện, vui vẻ và rèn luyện thể lực.',
      status: 'open',
      is_owner_hosted: isOwnerHosted,
      court_id: selectedCourtId.value || undefined,
      category: isOwnerHosted ? sessionCategory.value : undefined,
      skill_levels: isOwnerHosted ? acceptedSkillLabels.value : undefined,
      participants: participantsList,
      is_joined: !isOwnerHosted,
      is_host: true,
    }
  })

  async function loadCourts() {
    loading.value = true
    try {
      const res = await api.get<Court[]>('/courts')
      courts.value = res || []
      if (courts.value.length > 0 && !selectedCourtId.value && courts.value[0]) {
        selectedCourtId.value = courts.value[0].id
        if (courts.value[0].venue?.name) {
          selectedVenueName.value = courts.value[0].venue.name
        }
      }
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

  function selectQuickVenue(venueName: string) {
    selectedVenueName.value = venueName
    const matchingCourt = courts.value.find((c) =>
      c.venue?.name?.toLowerCase().includes(venueName.toLowerCase()),
    )
    if (matchingCourt) {
      selectedCourtId.value = matchingCourt.id
    }
  }

  async function submitMatch() {
    submitting.value = true
    error.value = ''
    notice.value = ''

    try {
      if (!matchDate.value || !matchTime.value) {
        error.value = 'Vui lòng chọn ngày và giờ bắt đầu trận đấu.'
        submitting.value = false
        return
      }

      const startDate = new Date(`${matchDate.value}T${matchTime.value}:00`)
      const endDate = new Date(startDate.getTime() + duration.value * 3600 * 1000)

      const selectedCourtObj = courts.value.find((c) => c.id === selectedCourtId.value)

      let payload: Record<string, unknown>

      if (isOwnerHostedSession.value) {
        payload = {
          title: displayTitle.value,
          visibility: visibility.value,
          starts_at: startDate.toISOString(),
          ends_at: endDate.toISOString(),
          court_id: selectedCourtId.value,
          is_owner_hosted: true,
          category: sessionCategory.value,
          skill_levels: acceptedSkillLabels.value,
          required_participants: Math.max(1, maxPlayers.value),
          city: selectedVenueName.value.includes('Sài Gòn') ? 'TP. Hồ Chí Minh' : 'Hà Nội',
          metadata: {
            is_owner_hosted: true,
            category: sessionCategory.value,
            skill_levels: acceptedSkillLabels.value,
            court_id: selectedCourtId.value,
            venue_name: selectedVenueName.value,
          },
        }
      } else {
        payload = {
          title: displayTitle.value,
          visibility: visibility.value,
          starts_at: startDate.toISOString(),
          ends_at: endDate.toISOString(),
          city: selectedVenueName.value.includes('Sài Gòn') ? 'TP. Hồ Chí Minh' : 'Hà Nội',
          min_skill:
            skillLevel.value === 'advanced' ? 4 : skillLevel.value === 'intermediate' ? 2 : 1,
          max_skill: 5,
          required_participants: Math.max(2, maxPlayers.value),
          availability: {
            sport: selectedSport.value,
            sport_name: sportNameVi.value,
            format: matchFormat.value,
            split_cost: splitCost.value,
            cost_per_person: costPerPerson.value,
            court_id: selectedCourtId.value || undefined,
            court_name: selectedCourtObj ? selectedCourtObj.name : undefined,
            venue_name: selectedVenueName.value,
            description: matchDescription.value.trim(),
          },
          metadata: {
            sport: selectedSport.value,
            sport_name: sportNameVi.value,
            format: matchFormat.value,
            court_id: selectedCourtId.value || undefined,
            court_name: selectedCourtObj ? selectedCourtObj.name : undefined,
            venue_name: selectedVenueName.value,
            cost_per_person: costPerPerson.value,
            split_cost: splitCost.value,
          },
        }
      }
      const res = await socialApi.createMatch(payload)
      const createdMatchId = (res as any)?.match?.id || (res as any)?.id

      // Invite selected friends if match id exists
      // Batch invite selected friends with individual failure reporting
      if (createdMatchId && selectedFriends.value.length > 0) {
        try {
          const inviteRes = await socialApi.invitePartners(
            createdMatchId,
            selectedFriends.value.map((f) => f.id),
          )
          const failed = inviteRes?.results?.filter((r) => r.status === 'failed') || []
          if (failed.length > 0) {
            const reasons = failed.map((f) => `ID ${f.invitee_id}: ${f.reason || 'Lỗi'}`).join(', ')
            notice.value = `Mở kèo thành công! Lưu ý một số lời mời thất bại: ${reasons}`
          } else {
            notice.value = 'Mở kèo giao lưu & gửi lời mời bạn chơi thành công!'
          }
        } catch {
          notice.value = 'Mở kèo thành công (không thể gửi lời mời bạn chơi tự động).'
        }
      }
      if (!notice.value) {
        notice.value = 'Mở kèo giao lưu & tìm kiếm bạn chơi thành công!'
      }
      setTimeout(() => {
        router.push({ path: '/matchmaking' })
      }, 1000)
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể khởi tạo trận đấu. Vui lòng kiểm tra lại thông tin.')
    } finally {
      submitting.value = false
    }
  }

  return {
    loading,
    submitting,
    notice,
    error,
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
    visibility,
    matchDescription,
    isOwnerHostedSession,
    sessionCategory,
    acceptedSkillLabels,
    availableSkillLabels,
    courts,
    courtOptions,
    quickVenues,
    friends,
    friendSearchQuery,
    filteredFriends,
    selectedFriends,
    selectedFriendsCount,
    sportNameVi,
    sportIconName,
    formatNameVi,
    generatedAutoTitle,
    previewMatch,
    loadCourts,
    loadFriends,
    loadingFriends,
    toggleFriend,
    selectQuickVenue,
    submitMatch,
  }
}

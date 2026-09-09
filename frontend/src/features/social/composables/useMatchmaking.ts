import { ref, computed, onMounted } from 'vue'
import { socialApi } from '../api/socialApi'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/core/utils/errorMessage'
import type {
  SocialMatch,
  PartnerPlayer,
  SocialSportType,
  MatchmakingStats,
} from '../types/social'

export function detectSport(title: string, metadataSport?: string): { sport: SocialSportType; name: string; icon: string } {
  const t = (title + ' ' + (metadataSport || '')).toLowerCase()
  if (t.includes('pickleball')) return { sport: 'pickleball', name: 'Pickleball', icon: 'sports_tennis' }
  if (t.includes('tennis') || t.includes('quần vợt')) return { sport: 'tennis', name: 'Tennis', icon: 'sports_tennis' }
  if (t.includes('bóng đá') || t.includes('football') || t.includes('5v5') || t.includes('7v7')) return { sport: 'football', name: 'Bóng đá', icon: 'sports_soccer' }
  if (t.includes('bóng rổ') || t.includes('basketball') || t.includes('3x3')) return { sport: 'basketball', name: 'Bóng rổ', icon: 'sports_basketball' }
  if (t.includes('bóng chuyền') || t.includes('volleyball')) return { sport: 'volleyball', name: 'Bóng chuyền', icon: 'sports_volleyball' }
  if (t.includes('bóng bàn') || t.includes('table tennis') || t.includes('ping pong')) return { sport: 'table_tennis', name: 'Bóng bàn', icon: 'sports_tennis' }
  return { sport: 'badminton', name: 'Cầu lông', icon: 'sports_tennis' }
}

export function formatMatchTime(startsAtStr?: string, endsAtStr?: string): string {
  if (!startsAtStr) return '19:00 - 21:00 · Hôm nay'
  try {
    const start = new Date(startsAtStr)
    const startH = String(start.getHours()).padStart(2, '0')
    const startM = String(start.getMinutes()).padStart(2, '0')
    const day = String(start.getDate()).padStart(2, '0')
    const month = String(start.getMonth() + 1).padStart(2, '0')
    const year = start.getFullYear()

    let endPart = ''
    if (endsAtStr) {
      const end = new Date(endsAtStr)
      const endH = String(end.getHours()).padStart(2, '0')
      const endM = String(end.getMinutes()).padStart(2, '0')
      endPart = ` - ${endH}:${endM}`
    } else {
      endPart = ` (2 tiếng)`
    }

    return `${startH}:${startM}${endPart} · ${day}/${month}/${year}`
  } catch {
    return startsAtStr
  }
}

export function useMatchmaking() {
  const auth = useAuthStore()

  const loading = ref(false)
  const processing = ref(false)
  const activeTab = ref<'all' | 'open' | 'my_matches' | 'partners' | 'feed' | 'connections'>('all')

  // Filters
  const searchQuery = ref('')
  const selectedSport = ref<SocialSportType>('all')
  const selectedSkill = ref<string>('all')
  const selectedCity = ref<string>('all')

  // Feedback
  const notice = ref('')
  const error = ref('')

  // Modals
  const selectedMatchForJoin = ref<SocialMatch | null>(null)
  const selectedMatchForDetail = ref<SocialMatch | null>(null)

  // Data
  const matches = ref<SocialMatch[]>([])
  const partners = ref<PartnerPlayer[]>([])

  const stats = computed<MatchmakingStats>(() => {
    const total = matches.value.length
    const open = matches.value.filter((m) => m.joined_count < m.required_participants).length
    const active = matches.value.reduce((acc, m) => acc + m.joined_count, 0)
    const venueSet = new Set(matches.value.map((m) => m.venue_name).filter(Boolean))
    return {
      totalMatches: total,
      openMatches: open,
      activePlayers: active + 12,
      totalVenues: Math.max(venueSet.size, 5),
    }
  })

  async function loadMatches() {
    loading.value = true
    error.value = ''
    try {
      const res = await socialApi.getMatches<{ matches?: any[]; data?: any[] }>()
      const list = res?.matches || res?.data || (Array.isArray(res) ? res : [])

      const currentUserId = auth.user?.id

      matches.value = list.map((m: any) => {
        const detected = detectSport(m.title || '', m.metadata?.sport)
        const isUserJoined = currentUserId
          ? m.creator_id === currentUserId ||
            m.participants?.some((p: any) => p.user_id === currentUserId || p.id === currentUserId)
          : false
        const isHost = currentUserId ? m.creator_id === currentUserId : false

        const venue = m.venue?.name || m.venue_name || m.availability?.venue_name || m.metadata?.venue_name || 'Aura Sport Hub'
        const court = m.court?.name || m.court_name || m.availability?.court_name || m.metadata?.court_name || 'Sân tiêu chuẩn'
        const format = m.game_format || m.availability?.format || m.metadata?.format || (detected.sport === 'football' ? '5v5' : detected.sport === 'basketball' ? '3x3' : 'Đánh đôi')
        const cost = m.cost_per_person ?? m.availability?.cost_per_person ?? m.metadata?.cost_per_person ?? (detected.sport === 'football' ? 80000 : 50000)
        const split = m.split_bill_mode || (m.availability?.split_cost ? 'Tự động trừ ví Aura' : 'Chủ phòng tự thu')

        return {
          id: m.id,
          title: m.title || `Kèo ${detected.name} #${m.id}`,
          sport_type: detected.sport,
          sport_name: detected.name,
          sport_icon: detected.icon,
          venue_name: venue,
          court_name: court,
          city: m.city || m.venue?.city || (venue.includes('Sài Gòn') ? 'TP. Hồ Chí Minh' : 'Hà Nội'),
          starts_at: formatMatchTime(m.starts_at, m.ends_at),
          ends_at: m.ends_at,
          joined_count: m.joined_count || (m.participants?.length ? m.participants.length : 1),
          required_participants: m.required_participants || 4,
          visibility: m.visibility || 'public',
          min_skill: m.min_skill ?? 2,
          max_skill: m.max_skill ?? 4,
          game_format: format,
          split_bill_mode: split,
          cost_per_person: cost,
          host_id: m.creator_id || m.host?.id,
          host_name: m.creator?.name || m.host_name || m.metadata?.host || 'Chủ kèo Aura',
          host_avatar: m.creator?.avatar_url || m.host_avatar,
          host_reputation: m.host_reputation || 98,
          host_level: m.host_level || 'Hạng Bán Chuyên',
          description: m.description || m.availability?.description || `Tìm bạn chơi giao lưu nâng cao kỹ năng, tôn trọng văn hóa thi đấu fair-play tại ${venue}.`,
          status: (m.joined_count >= (m.required_participants || 4)) ? 'full' : 'open',
          participants: Array.isArray(m.participants) && m.participants.length > 0
            ? m.participants.map((p: any) => ({
                id: p.id || p.user_id,
                name: p.name || p.user?.name || `VĐV #${p.id || 1}`,
                avatar: p.avatar || p.user?.avatar_url,
                skill_level: p.skill_level || 3,
                is_host: p.user_id === m.creator_id,
              }))
            : [
                {
                  id: m.creator_id || 1,
                  name: m.creator?.name || m.metadata?.host || 'Chủ kèo',
                  avatar: m.creator?.avatar_url,
                  skill_level: m.min_skill || 3,
                  is_host: true,
                },
              ],
          is_joined: isUserJoined,
          is_host: isHost,
        }
      })
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách kèo giao lưu.')
    } finally {
      loading.value = false
    }
  }

  function loadPartners() {
    partners.value = [
      {
        id: 101,
        name: 'Hoàng Minh Tuấn',
        sport_type: 'badminton',
        sport_name: 'Cầu lông',
        level_label: 'Khá · NTRP 3.5',
        skill_level: 4,
        reputation_score: 99,
        city: 'Hà Nội',
        favorite_venues: ['Aura Court Cầu Giấy', 'Aura Quần Ngựa'],
        bio: 'Đánh đôi công thủ toàn diện, thường rảnh các buổi tối 2-4-6. Tìm đồng đội phối hợp ăn ý thi đấu giải phong trào.',
        is_online: true,
        matches_played: 42,
      },
      {
        id: 102,
        name: 'Trần Đăng Khoa',
        sport_type: 'pickleball',
        sport_name: 'Pickleball',
        level_label: 'Trung cấp · DUPR 3.8',
        skill_level: 4,
        reputation_score: 97,
        city: 'Hà Nội',
        favorite_venues: ['Aura Complex Tây Hồ', 'Aura Cầu Giấy'],
        bio: 'Chuyên đánh dink và drop shot, cần tìm partner luyện phản xạ lưới cuối tuần.',
        is_online: true,
        matches_played: 28,
      },
      {
        id: 103,
        name: 'Nguyễn Bích Ngọc',
        sport_type: 'tennis',
        sport_name: 'Tennis',
        level_label: 'Cơ bản · NTRP 2.5',
        skill_level: 2,
        reputation_score: 95,
        city: 'Hà Nội',
        favorite_venues: ['Aura Complex Tây Hồ'],
        bio: 'Đang tập rally bền bóng cuối sân, tìm bạn nữ hoặc nam đánh vui vẻ không áp lực điểm số.',
        is_online: false,
        matches_played: 15,
      },
      {
        id: 104,
        name: 'Lê Hoàng Nam',
        sport_type: 'football',
        sport_name: 'Bóng đá 5v5',
        level_label: 'Khá · Vị trí Hậu vệ / Tiền vệ',
        skill_level: 4,
        reputation_score: 98,
        city: 'Hà Nội',
        favorite_venues: ['Aura Arena Quần Ngựa'],
        bio: 'Thể lực tốt, chuyền bóng thoát pressing, rảnh tối thứ 3 và thứ 6.',
        is_online: true,
        matches_played: 56,
      },
      {
        id: 105,
        name: 'Vũ Đức Thành',
        sport_type: 'badminton',
        sport_name: 'Cầu lông',
        level_label: 'Bán chuyên · NTRP 4.5',
        skill_level: 5,
        reputation_score: 100,
        city: 'TP. Hồ Chí Minh',
        favorite_venues: ['Aura Central Sài Gòn'],
        bio: 'Từng thi đấu giải sinh viên và giải mở rộng, nhận giao lưu chia sẻ kinh nghiệm kỹ chiến thuật.',
        is_online: true,
        matches_played: 89,
      },
      {
        id: 106,
        name: 'Đặng Mai Linh',
        sport_type: 'table_tennis',
        sport_name: 'Bóng bàn',
        level_label: 'Trung bình khá · Cốt vợt Butterfly',
        skill_level: 3,
        reputation_score: 96,
        city: 'Hà Nội',
        favorite_venues: ['Aura Court Cầu Giấy', 'Aura Quần Ngựa'],
        bio: 'Lối đánh giật bóng phải tay xoáy lên, tìm bạn giao lưu học hỏi các buổi chiều.',
        is_online: false,
        matches_played: 21,
      },
    ]
  }

  const filteredMatches = computed(() => {
    return matches.value.filter((m) => {
      // Tab filter
      if (activeTab.value === 'open' && m.joined_count >= m.required_participants) return false
      if (activeTab.value === 'my_matches' && !m.is_joined && !m.is_host) return false

      // Sport filter
      if (selectedSport.value !== 'all' && m.sport_type !== selectedSport.value) {
        if (selectedSport.value === 'football' && !m.sport_type.startsWith('football')) return false
        if (selectedSport.value !== 'football' && m.sport_type !== selectedSport.value) return false
      }

      // City filter
      if (selectedCity.value !== 'all' && !m.city.toLowerCase().includes(selectedCity.value.toLowerCase())) {
        return false
      }

      // Skill filter
      if (selectedSkill.value !== 'all') {
        const skillNum = Number(selectedSkill.value)
        if (!isNaN(skillNum)) {
          if (m.min_skill && m.min_skill > skillNum) return false
          if (m.max_skill && m.max_skill < skillNum) return false
        }
      }

      // Search query
      const q = searchQuery.value.trim().toLowerCase()
      if (q) {
        const matchText = `${m.title} ${m.venue_name} ${m.court_name || ''} ${m.host_name} ${m.sport_name}`.toLowerCase()
        if (!matchText.includes(q)) return false
      }

      return true
    })
  })

  const myMatches = computed(() => {
    return matches.value.filter((m) => m.is_joined || m.is_host)
  })

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4500)
  }

  async function joinMatch(match: SocialMatch) {
    processing.value = true
    error.value = ''
    try {
      await socialApi.joinMatch(match.id)
      match.joined_count++
      match.is_joined = true
      if (match.joined_count >= match.required_participants) {
        match.status = 'full'
      }
      showNotice(`🎉 Bạn đã tham gia kèo "${match.title}" thành công! Vui lòng đến sân đúng giờ.`)
      selectedMatchForJoin.value = null
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tham gia kèo đấu. Vui lòng thử lại.')
    } finally {
      processing.value = false
    }
  }

  function resetFilters() {
    searchQuery.value = ''
    selectedSport.value = 'all'
    selectedSkill.value = 'all'
    selectedCity.value = 'all'
  }

  onMounted(() => {
    loadMatches()
    loadPartners()
  })

  return {
    loading,
    processing,
    activeTab,
    searchQuery,
    selectedSport,
    selectedSkill,
    selectedCity,
    notice,
    error,
    selectedMatchForJoin,
    selectedMatchForDetail,
    matches,
    partners,
    stats,
    filteredMatches,
    myMatches,
    loadMatches,
    loadPartners,
    joinMatch,
    resetFilters,
    showNotice,
  }
}

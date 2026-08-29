import { ref, computed } from 'vue'

export interface SocialMatch {
  id: number
  title: string
  venue_name: string
  court_name?: string
  city: string
  starts_at: string
  joined_count: number
  required_participants: number
  visibility: 'public' | 'invite_only' | 'private'
  min_skill?: number
  max_skill?: number
  host_name: string
  host_avatar?: string
  description?: string
  participants?: Array<{
    id: number
    name: string
    avatar?: string
    skill_level?: number
  }>
}

export function useSocialTournament() {
  const activeTab = ref<'matches' | 'partners' | 'profile'>('matches')
  const searchQuery = ref('')
  const filterSkill = ref('all')
  const filterVenue = ref('all')

  const notice = ref('')
  const error = ref('')

  const selectedMatchForJoin = ref<SocialMatch | null>(null)
  const showPartnerFinder = ref(false)

  const matches = ref<SocialMatch[]>([
    {
      id: 1,
      title: 'Kèo Đôi Nam 2-4-6 Buổi Tối Sôi Động',
      venue_name: 'Downtown Arena (Quận 1)',
      court_name: 'Sân 2 (VIP)',
      city: 'Hồ Chí Minh',
      starts_at: '19:00 - 21:00, Hôm nay',
      joined_count: 3,
      required_participants: 4,
      visibility: 'public',
      min_skill: 3,
      max_skill: 5,
      host_name: 'Hoàng Long',
      description: 'Tìm 1 bạn nam trình độ 3.5+ giao lưu vui vẻ, chia đều tiền sân + nước.',
      participants: [
        { id: 1, name: 'Hoàng Long', skill_level: 4 },
        { id: 2, name: 'Minh Trí', skill_level: 3.5 },
        { id: 3, name: 'Quốc Bảo', skill_level: 4 },
      ],
    },
    {
      id: 2,
      title: 'Giao Lưu Đôi Nam Nữ Cuối Tuần',
      venue_name: 'Westside Courts (Bình Thạnh)',
      court_name: 'Sân 5',
      city: 'Hồ Chí Minh',
      starts_at: '08:00 - 10:00, Chủ Nhật',
      joined_count: 2,
      required_participants: 4,
      visibility: 'public',
      min_skill: 2,
      max_skill: 4,
      host_name: 'Thu Trang',
      description: 'Nhóm phong trào cần thêm 2 bạn đánh vui, rèn luyện sức khoẻ cuối tuần.',
      participants: [
        { id: 10, name: 'Thu Trang', skill_level: 2.5 },
        { id: 11, name: 'Văn Nam', skill_level: 3 },
      ],
    },
  ])

  const filteredMatches = computed(() => {
    return matches.value.filter((m) => {
      const q = searchQuery.value.trim().toLowerCase()
      const matchSearch =
        !q ||
        m.title.toLowerCase().includes(q) ||
        m.venue_name.toLowerCase().includes(q) ||
        m.host_name.toLowerCase().includes(q)

      return matchSearch
    })
  })

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  function joinMatch(match: SocialMatch) {
    match.joined_count++
    showNotice(`Đã tham gia trận giao lưu "${match.title}" thành công!`)
    selectedMatchForJoin.value = null
  }

  return {
    activeTab,
    searchQuery,
    filterSkill,
    filterVenue,
    notice,
    error,
    selectedMatchForJoin,
    showPartnerFinder,
    matches,
    filteredMatches,
    showNotice,
    joinMatch,
  }
}

import { ref, computed, onMounted } from 'vue'
import { socialApi } from '@/features/social/api/socialApi'
import { errorMessage } from '@/core/utils/errorMessage'

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
  const loading = ref(false)
  const activeTab = ref<'matches' | 'partners' | 'profile'>('matches')
  const searchQuery = ref('')
  const filterSkill = ref('all')
  const filterVenue = ref('all')

  const notice = ref('')
  const error = ref('')

  const selectedMatchForJoin = ref<SocialMatch | null>(null)
  const showPartnerFinder = ref(false)

  const matches = ref<SocialMatch[]>([])

  async function loadMatches() {
    loading.value = true
    error.value = ''
    try {
      const res = await socialApi.getMatches<{ matches?: any[]; data?: any[] }>()
      const list = res?.matches || res?.data || (Array.isArray(res) ? res : [])
      matches.value = list.map((m: any) => {
        let formattedTime = m.starts_at || '19:00 - 21:00'
        if (m.starts_at && m.starts_at.includes('T')) {
          try {
            const d = new Date(m.starts_at)
            const hours = String(d.getHours()).padStart(2, '0')
            const minutes = String(d.getMinutes()).padStart(2, '0')
            const day = String(d.getDate()).padStart(2, '0')
            const month = String(d.getMonth() + 1).padStart(2, '0')
            formattedTime = `${hours}:${minutes} · ${day}/${month}/${d.getFullYear()}`
          } catch {
            formattedTime = m.starts_at
          }
        }
        return {
          id: m.id,
          title: m.title || `Kèo giao lưu #${m.id}`,
          venue_name: m.venue?.name || m.venue_name || 'Cơ sở Aura',
          court_name: m.court?.code || m.court_name || 'Sân tiêu chuẩn',
          city: m.venue?.city || 'Hồ Chí Minh',
          starts_at: formattedTime,
          joined_count: m.joined_count || m.participants?.length || 1,
          required_participants: m.required_participants || 4,
          visibility: m.visibility || 'public',
          min_skill: m.min_skill,
          max_skill: m.max_skill,
          host_name: m.host?.name || m.host_name || 'Chủ kèo',
          host_avatar: m.host?.avatar || m.host_avatar,
          description: m.description || '',
          participants: m.participants || [],
        }
      })
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách kèo giao lưu.')
    } finally {
      loading.value = false
    }
  }

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

  async function joinMatch(match: SocialMatch) {
    loading.value = true
    try {
      await socialApi.joinMatch(match.id)
      match.joined_count++
      showNotice(`Đã tham gia kèo "${match.title}" thành công!`)
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tham gia kèo đấu.')
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    loadMatches()
  })

  return {
    loading,
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
    loadMatches,
  }
}

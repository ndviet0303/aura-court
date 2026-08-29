import { ref, computed } from 'vue'
import type { TournamentItem } from '../types/tournament'
import { tournamentApi } from '../api/tournamentApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { useAuthStore } from '@/stores/auth'

export function useTournamentsList() {
  const auth = useAuthStore()
  const activeTab = ref<'all' | 'open' | 'ongoing' | 'completed' | 'my'>('all')
  const searchQuery = ref('')
  const selectedSport = ref('all')
  const selectedFormat = ref('all')

  const loading = ref(false)
  const processing = ref(false)
  const notice = ref('')
  const error = ref('')

  const selectedTournamentForRegister = ref<TournamentItem | null>(null)
  const selectedTournamentForBracket = ref<TournamentItem | null>(null)

  const sampleTournaments = ref<TournamentItem[]>([
    {
      id: 1,
      name: 'Aura Master Open Championship 2026',
      title: 'Aura Master Open Championship 2026',
      slug: 'aura-master-open-2026',
      category: 'Đôi Nam - Hạng A Pro',
      sport: 'badminton',
      format: 'single_elimination',
      status: 'ongoing',
      prize_pool: '50.000.000 ₫',
      dates: '28/08 - 02/09/2026',
      venue: 'Aura Central Arena',
      city: 'Hồ Chí Minh',
      current_slots: 16,
      max_slots: 16,
      entry_fee: '500.000 ₫ / Cặp',
      image_url: 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?auto=format&fit=crop&w=800&q=80',
      description: 'Giải đấu quy tụ các tay vợt hàng đầu miền Nam tranh tài với hệ thống camera AI VAR công nghệ cao.',
      version: 1,
      current_match: {
        round_label: 'Chung Kết Sân 1',
        court: 'Sân Trung Tâm',
        player1_name: 'Nguyễn Tiến Minh / Phạm Cao Cường',
        player2_name: 'Lê Đức Phát / Nguyễn Hải Đăng',
        p1_scores: [21, 19, 14],
        p2_scores: [18, 21, 12],
      },
    },
    {
      id: 2,
      name: 'Saigon Smashers Cup Mùa Thu',
      title: 'Saigon Smashers Cup Mùa Thu',
      slug: 'saigon-smashers-autumn',
      category: 'Đôi Nam Nữ - Phong Trào B',
      sport: 'badminton',
      format: 'round_robin',
      status: 'open',
      prize_pool: '25.000.000 ₫',
      dates: '15/09 - 18/09/2026',
      venue: 'Westside Courts',
      city: 'Hồ Chí Minh',
      current_slots: 12,
      max_slots: 24,
      entry_fee: '350.000 ₫ / Cặp',
      image_url: 'https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?auto=format&fit=crop&w=800&q=80',
      description: 'Giải phong trào giao lưu gắn kết cộng đồng lông thủ yêu thể thao tại TP.HCM.',
      version: 1,
    },
    {
      id: 3,
      name: 'Aura Summer Circuit - Hạng C',
      title: 'Aura Summer Circuit - Hạng C',
      slug: 'aura-summer-circuit-c',
      category: 'Đơn Nam - Nghiệp Dư',
      sport: 'badminton',
      format: 'single_elimination',
      status: 'completed',
      prize_pool: '15.000.000 ₫',
      dates: '10/08 - 12/08/2026',
      venue: 'Aura Central Arena',
      city: 'Hồ Chí Minh',
      current_slots: 32,
      max_slots: 32,
      entry_fee: '200.000 ₫',
      image_url: 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=800&q=80',
      description: 'Giải đấu kết thúc với chức vô địch thuộc về tay vợt Trần Minh Quân.',
      version: 1,
      winner_team: 'Trần Minh Quân',
      runner_up: 'Lê Hoàng Long',
    },
  ])

  const circuitLeaderboard = ref([
    { rank: 1, name: 'CLB Smash Master', points: 2850, tournamentsWon: 4, avatar: '🏆' },
    { rank: 2, name: 'Saigon Badminton Club', points: 2420, tournamentsWon: 3, avatar: '🥈' },
    { rank: 3, name: 'Tân Bình Shuttlecocks', points: 1980, tournamentsWon: 2, avatar: '🥉' },
    { rank: 4, name: 'Aura Elite Team', points: 1750, tournamentsWon: 1, avatar: '🏸' },
  ])

  const filteredTournaments = computed(() => {
    return sampleTournaments.value.filter((t) => {
      const matchTab =
        activeTab.value === 'all' ||
        (activeTab.value === 'open' && t.status === 'open') ||
        (activeTab.value === 'ongoing' && t.status === 'ongoing') ||
        (activeTab.value === 'completed' && t.status === 'completed') ||
        (activeTab.value === 'my' && true)

      const matchSport = selectedSport.value === 'all' || t.sport === selectedSport.value
      const matchFormat = selectedFormat.value === 'all' || t.format === selectedFormat.value

      const q = searchQuery.value.trim().toLowerCase()
      const matchSearch =
        !q ||
        t.name.toLowerCase().includes(q) ||
        (t.category || '').toLowerCase().includes(q) ||
        (t.venue || '').toLowerCase().includes(q)

      return matchTab && matchSport && matchFormat && matchSearch
    })
  })

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4500)
  }

  function registerAthlete(payload: { name: string; phone: string; partnerName?: string }) {
    if (!selectedTournamentForRegister.value) return
    selectedTournamentForRegister.value.current_slots++
    showNotice(`Đã ghi danh tham dự giải "${selectedTournamentForRegister.value.name}" thành công!`)
    selectedTournamentForRegister.value = null
  }

  return {
    activeTab,
    searchQuery,
    selectedSport,
    selectedFormat,
    loading,
    processing,
    notice,
    error,
    selectedTournamentForRegister,
    selectedTournamentForBracket,
    sampleTournaments,
    circuitLeaderboard,
    filteredTournaments,
    showNotice,
    registerAthlete,
  }
}

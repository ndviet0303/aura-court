import { ref, computed, onMounted } from 'vue'
import type { TournamentItem, CircuitLeaderboardItem, TournamentRegistrationPayload } from '../types/tournament'
import { tournamentApi } from '../api/tournamentApi'
import { errorMessage } from '@/core/utils/errorMessage'

export function useTournamentsList() {
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

  const tournaments = ref<TournamentItem[]>([])
  const circuitLeaderboard = ref<CircuitLeaderboardItem[]>([])
  const registeredTournamentIds = ref<number[]>([])

  const stats = computed(() => {
    const list = tournaments.value
    const openCount = list.filter((t) => t.status === 'open' || t.status === 'upcoming').length
    const ongoingCount = list.filter((t) => t.status === 'ongoing').length
    
    // Compute total prize pool from list
    let totalPrize = 0
    list.forEach((t) => {
      if (t.prize_pool) {
        const num = parseInt(t.prize_pool.replace(/[^\d]/g, ''), 10)
        if (!isNaN(num)) totalPrize += num
      }
    })

    const formattedPrize = totalPrize > 0
      ? `${totalPrize.toLocaleString('vi-VN')} ₫`
      : '0 ₫'

    return {
      total: list.length,
      open: openCount,
      ongoing: ongoingCount,
      prizePoolTotal: formattedPrize,
    }
  })

  const filteredTournaments = computed(() => {
    return tournaments.value.filter((t) => {
      const matchTab =
        activeTab.value === 'all' ||
        (activeTab.value === 'open' && (t.status === 'open' || t.status === 'upcoming')) ||
        (activeTab.value === 'ongoing' && t.status === 'ongoing') ||
        (activeTab.value === 'completed' && t.status === 'completed') ||
        (activeTab.value === 'my' && registeredTournamentIds.value.includes(t.id))

      const matchSport = selectedSport.value === 'all' || t.sport === selectedSport.value
      const matchFormat = selectedFormat.value === 'all' || t.format === selectedFormat.value

      const q = searchQuery.value.trim().toLowerCase()
      const matchSearch =
        !q ||
        t.name.toLowerCase().includes(q) ||
        (t.category || '').toLowerCase().includes(q) ||
        (t.venue || '').toLowerCase().includes(q) ||
        (t.city || '').toLowerCase().includes(q)

      return matchTab && matchSport && matchFormat && matchSearch
    })
  })

  async function loadTournaments() {
    loading.value = true
    error.value = ''
    try {
      const res = await tournamentApi.list<TournamentItem[]>()
      tournaments.value = Array.isArray(res) ? res : []
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách giải đấu.')
      tournaments.value = []
    } finally {
      loading.value = false
    }
  }

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4500)
  }

  function resetFilters() {
    activeTab.value = 'all'
    searchQuery.value = ''
    selectedSport.value = 'all'
    selectedFormat.value = 'all'
  }

  async function registerAthlete(payload: TournamentRegistrationPayload) {
    const targetTournament = selectedTournamentForRegister.value
    if (!targetTournament) return

    processing.value = true
    try {
      await tournamentApi.checkInParticipant(targetTournament.id, payload)
      targetTournament.current_slots = Math.min(
        targetTournament.max_slots,
        (targetTournament.current_slots || 0) + 1
      )
      if (!registeredTournamentIds.value.includes(targetTournament.id)) {
        registeredTournamentIds.value.push(targetTournament.id)
      }
      showNotice(`Đã đăng ký tham gia giải đấu "${targetTournament.name}" thành công!`)
      selectedTournamentForRegister.value = null
    } catch (e: unknown) {
      showNotice(errorMessage(e, `Đã ghi danh tham dự giải "${targetTournament.name}".`))
      selectedTournamentForRegister.value = null
    } finally {
      processing.value = false
    }
  }

  onMounted(() => {
    loadTournaments()
  })

  return {
    activeTab,
    searchQuery,
    selectedSport,
    selectedFormat,
    loading,
    processing,
    notice,
    error,
    stats,
    selectedTournamentForRegister,
    selectedTournamentForBracket,
    tournaments,
    circuitLeaderboard,
    filteredTournaments,
    loadTournaments,
    showNotice,
    resetFilters,
    registerAthlete,
  }
}

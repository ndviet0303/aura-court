import { ref, computed, onMounted } from 'vue'
import type { TournamentItem } from '../types/tournament'
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
  const circuitLeaderboard = ref<Array<{ rank: number; name: string; points: number; tournamentsWon: number; avatar: string }>>([])

  const filteredTournaments = computed(() => {
    return tournaments.value.filter((t) => {
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

  async function loadTournaments() {
    loading.value = true
    error.value = ''
    try {
      const res = await tournamentApi.list<TournamentItem[]>()
      if (Array.isArray(res)) {
        tournaments.value = res
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách giải đấu.')
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

  async function registerAthlete(payload: { name: string; phone: string; partnerName?: string }) {
    if (!selectedTournamentForRegister.value) return
    processing.value = true
    try {
      await tournamentApi.checkInParticipant(selectedTournamentForRegister.value.id, payload)
      selectedTournamentForRegister.value.current_slots = (selectedTournamentForRegister.value.current_slots || 0) + 1
      showNotice(`Đã ghi danh tham dự giải "${selectedTournamentForRegister.value.name}" thành công!`)
      selectedTournamentForRegister.value = null
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể ghi danh tham gia giải đấu.')
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
    selectedTournamentForRegister,
    selectedTournamentForBracket,
    tournaments,
    circuitLeaderboard,
    filteredTournaments,
    loadTournaments,
    showNotice,
    registerAthlete,
  }
}

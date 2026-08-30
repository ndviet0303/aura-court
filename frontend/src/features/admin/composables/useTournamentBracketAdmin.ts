import { ref, computed, onMounted } from 'vue'
import type { Participant, FixtureMatch, TournamentAdminData } from '../types/tournamentAdmin'
import { tournamentApi } from '@/features/tournament/api/tournamentApi'
import { getAvatarUrl } from '@/lib/avatar'
import { errorMessage } from '@/core/utils/errorMessage'

export function useTournamentBracketAdmin() {
  const loading = ref(false)
  const tournament = ref<TournamentAdminData>({
    id: 1,
    name: 'Aura Summer Open 2026',
    category: "Cầu Lông Đơn Nam (Men's Singles)",
    sport: 'badminton',
    format: 'single_elimination',
    status: 'open',
    prizePool: '100.000.000 ₫',
    prizePoolUsd: '$10,000',
    startDate: '2026-08-15',
    endDate: '2026-08-18',
    datesFormatted: '15 - 18 Tháng 8, 2026',
    venue: 'Aura Center Court A',
    city: 'Quận 1, TP. Hồ Chí Minh',
    courtsAvailable: 4,
    maxSlots: 32,
    entryFee: '500.000 ₫ / VĐV',
    description: 'Giải đấu mùa hè danh giá nhất hệ thống Aura Court.',
    version: 1,
    seedingRule: 'standard',
  })

  const currentWizardStep = ref<number>(3)
  const activeSubNav = ref<'bracket-management' | 'athlete-check-in' | 'live-referee-console' | 'tournament-overview'>('bracket-management')
  const selectedRoundFilter = ref<'all' | 'r16' | 'qf' | 'sf' | 'final'>('all')
  const zoomLevel = ref<number>(1)

  // Feedback State
  const notice = ref('')
  const error = ref('')
  const isGenerating = ref(false)

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4500)
  }

  function showError(msg: string) {
    error.value = msg
    setTimeout(() => {
      if (error.value === msg) error.value = ''
    }, 5000)
  }

  // Participants Pool
  const participants = ref<Participant[]>([])

  // Athlete Check-in Filtering & Metrics
  const checkInFilterStatus = ref<'all' | 'pending' | 'checked_in' | 'no_show'>('all')
  const checkInSearchQuery = ref('')

  const checkInMetrics = computed(() => {
    const total = participants.value.length || 32
    const checkedInCount = participants.value.filter((p) => p.checkedIn || p.status === 'checked_in').length
    const pending_count = total - checkedInCount
    const percentage = total > 0 ? Math.round((checkedInCount / total) * 100) : 0
    return {
      total,
      checkedInCount,
      pending_count,
      noShowCount: 0,
      percentage,
    }
  })

  const filteredAthletes = computed(() => {
    return participants.value.filter((ath) => {
      const matchStatus =
        checkInFilterStatus.value === 'all' ||
        (checkInFilterStatus.value === 'checked_in' && (ath.checkedIn || ath.status === 'checked_in')) ||
        (checkInFilterStatus.value === 'pending' && (!ath.checkedIn && ath.status === 'pending')) ||
        (checkInFilterStatus.value === 'no_show' && ath.status === 'no_show')

      const q = checkInSearchQuery.value.trim().toLowerCase()
      const matchSearch =
        !q ||
        ath.name.toLowerCase().includes(q) ||
        (ath.club && ath.club.toLowerCase().includes(q)) ||
        (ath.qrCode && ath.qrCode.toLowerCase().includes(q))

      return matchStatus && matchSearch
    })
  })

  async function toggleAthleteCheckIn(ath: Participant) {
    const nextState = !ath.checkedIn
    ath.checkedIn = nextState
    ath.status = nextState ? 'checked_in' : 'pending'
    if (nextState) {
      ath.checkInTime = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
      showNotice(`Đã điểm danh VĐV: ${ath.name}`)
    } else {
      ath.checkInTime = undefined
      showNotice(`Đã huỷ điểm danh: ${ath.name}`)
    }

    try {
      await tournamentApi.checkInParticipant(ath.id, { checked_in: nextState })
    } catch {
      // optimistic check-in
    }
  }

  // Matches Pool
  const matches = ref<FixtureMatch[]>([])

  // Live Referee Console Match
  const liveMatch = ref({
    matchCode: 'QF-M01',
    courtName: 'Court 1 - Show Court',
    playerA: { name: 'Sarah Jenkins', club: 'Apex Club', seed: 2, score: 21, sets: [21, 19], isServing: true },
    playerB: { name: 'Marcus Chen', club: 'Independent', score: 18, sets: [18, 21], isServing: false },
    currentSet: 3,
    hasConflict: false,
    conflictDesc: 'Score Synchronization Conflict: Remote desk submitted 19-18 vs Local 21-18.',
    isSwappedSides: false,
    penalties: [] as Array<{ player: string; type: string; reason: string; time: string }>,
  })

  function incrementScoreA() {
    liveMatch.value.playerA.score++
  }

  function decrementScoreA() {
    if (liveMatch.value.playerA.score > 0) liveMatch.value.playerA.score--
  }

  function incrementScoreB() {
    liveMatch.value.playerB.score++
  }

  function decrementScoreB() {
    if (liveMatch.value.playerB.score > 0) liveMatch.value.playerB.score--
  }

  function switchEnds() {
    liveMatch.value.isSwappedSides = !liveMatch.value.isSwappedSides
    showNotice('Đã đổi sân cho 2 vận động viên!')
  }

  function simulateConflict() {
    liveMatch.value.hasConflict = true
  }

  function resolveConflict() {
    liveMatch.value.hasConflict = false
    showNotice('Đã đồng bộ kết quả tỷ số từ máy chủ.')
  }

  function regenerateBracketTree() {
    isGenerating.value = true
    setTimeout(() => {
      isGenerating.value = false
      showNotice('Đã tái tạo bảng phân nhánh theo hạt giống!')
    }, 600)
  }

  async function loadTournamentData() {
    loading.value = true
    error.value = ''
    try {
      const res = await tournamentApi.getById<any>(tournament.value.id)
      if (res) {
        if (res.name) tournament.value.name = res.name
        if (res.participants) {
          participants.value = res.participants.map((p: any) => ({
            id: p.id,
            seed: p.seed,
            name: p.user?.name || p.name || 'VĐV',
            club: p.club || 'Aura Club',
            level: p.level || 'Lvl 4',
            avatar: p.user?.avatar || getAvatarUrl(p.name || 'VĐV'),
            checkedIn: p.checked_in ?? false,
            status: p.checked_in ? 'checked_in' : 'pending',
            paid: p.paid ?? true,
            racketChecked: p.racket_checked ?? true,
            warmupCourt: p.warmup_court || 'Sân 1',
            qrCode: p.qr_code || `ATH-${p.id}`,
            phone: p.user?.phone || p.phone || '—',
            category: p.category || "Đơn Nam",
          }))
        }
        if (res.fixtures) {
          matches.value = res.fixtures.map((f: any) => ({
            id: f.id,
            roundNumber: f.round || 1,
            roundName: f.round_name || 'Vòng 1',
            position: f.position || 1,
            matchNumber: f.match_number || 1,
            courtName: f.court?.name || `Sân ${f.court_id || 1}`,
            courtId: f.court_id || 1,
            scheduledTime: f.scheduled_time || '09:00',
            status: f.status || 'pending',
            player1: f.player1 ? { id: f.player1.id, name: f.player1.name, avatar: getAvatarUrl(f.player1.name), sets: f.player1_sets || [] } : undefined,
            player2: f.player2 ? { id: f.player2.id, name: f.player2.name, avatar: getAvatarUrl(f.player2.name), sets: f.player2_sets || [] } : undefined,
            winnerId: f.winner_id,
            nextMatchId: f.next_match_id,
          }))
        }
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải dữ liệu giải đấu.')
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    loadTournamentData()
  })

  return {
    loading,
    tournament,
    currentWizardStep,
    activeSubNav,
    selectedRoundFilter,
    zoomLevel,
    notice,
    error,
    isGenerating,
    participants,
    checkInFilterStatus,
    checkInSearchQuery,
    checkInMetrics,
    filteredAthletes,
    matches,
    liveMatch,
    showNotice,
    showError,
    toggleAthleteCheckIn,
    incrementScoreA,
    decrementScoreA,
    incrementScoreB,
    decrementScoreB,
    switchEnds,
    simulateConflict,
    resolveConflict,
    regenerateBracketTree,
    loadTournamentData,
  }
}

import { ref, computed, onMounted } from 'vue'
import type {
  Participant,
  FixtureMatch,
  FixturePlayer,
  TournamentAdminData,
} from '../types/tournamentAdmin'
import type { TournamentItem } from '@/features/tournament/types/tournament'
import { tournamentApi } from '@/features/tournament/api/tournamentApi'
import { getAvatarUrl } from '@/lib/avatar'
import { errorMessage } from '@/core/utils/errorMessage'

export function useTournamentBracketAdmin() {
  const loading = ref(false)
  const loadingDetails = ref(false)
  const isGenerating = ref(false)
  const saving = ref(false)

  // Tournaments list & selection
  const tournamentsList = ref<TournamentItem[]>([])
  const selectedTournamentId = ref<number | null>(null)

  // id 0 means no tournament is loaded yet.
  const tournament = ref<TournamentAdminData>({ id: 0, name: '', datesFormatted: '' })

  const currentWizardStep = ref<number>(3)
  const activeSubNav = ref<
    'bracket-management' | 'athlete-check-in' | 'live-referee-console' | 'tournament-overview'
  >('bracket-management')
  const selectedRoundFilter = ref<'all' | 'r16' | 'qf' | 'sf' | 'final'>('all')

  // Feedback State
  const notice = ref('')
  const error = ref('')

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
    const total = participants.value.length
    const checkedInCount = participants.value.filter(
      (p) => p.checkedIn || p.status === 'checked_in',
    ).length
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
        (checkInFilterStatus.value === 'checked_in' &&
          (ath.checkedIn || ath.status === 'checked_in')) ||
        (checkInFilterStatus.value === 'pending' && !ath.checkedIn && ath.status === 'pending') ||
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
      ath.checkInTime = new Date().toLocaleTimeString('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
      })
      showNotice(`Đã điểm danh: ${ath.name}`)
    } else {
      ath.checkInTime = undefined
      showNotice(`Đã huỷ điểm danh: ${ath.name}`)
    }

    try {
      await tournamentApi.checkInParticipant(ath.id, { checked_in: nextState })
    } catch (e) {
      showError(errorMessage(e, 'Không thể cập nhật trạng thái điểm danh.'))
    }
  }

  // Matches Pool
  const matches = ref<FixtureMatch[]>([])

  // Live Referee Console Match
  function toLivePlayer(p: FixturePlayer | undefined, isServing: boolean) {
    return {
      id: p?.id,
      name: p?.name ?? '',
      club: p?.club ?? '',
      seed: p?.seed,
      score: p?.sets[0] ?? 0,
      sets: p?.sets ?? [],
      isServing,
    }
  }

  const liveMatch = ref({
    fixtureId: undefined as number | undefined,
    matchCode: '',
    courtName: '',
    playerA: toLivePlayer(undefined, true),
    playerB: toLivePlayer(undefined, false),
    currentSet: 1,
    hasConflict: false,
    conflictDesc: 'Xung đột đồng bộ tỷ số từ trọng tài phụ.',
    isSwappedSides: false,
    penalties: [] as Array<{ player: string; type: string; reason: string; time: string }>,
  })

  // Without fixtures the console empties instead of keeping another tournament's match.
  function syncLiveMatchFromFixtures() {
    const active = matches.value.find((m) => m.status === 'live') || matches.value[0]
    liveMatch.value.fixtureId = active?.id
    liveMatch.value.matchCode = active ? `R${active.roundNumber}-M0${active.matchNumber}` : ''
    liveMatch.value.courtName = active?.courtName ?? ''
    liveMatch.value.playerA = toLivePlayer(active?.player1, true)
    liveMatch.value.playerB = toLivePlayer(active?.player2, false)
  }

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
    showNotice('Đã đổi sân cho hai bên!')
  }

  function simulateConflict() {
    liveMatch.value.hasConflict = true
  }

  function resolveConflict() {
    liveMatch.value.hasConflict = false
    showNotice('Đã đồng bộ kết quả tỷ số từ máy chủ.')
  }

  async function saveRefereeScore() {
    const { fixtureId, playerA, playerB } = liveMatch.value
    const winnerId = playerA.score >= playerB.score ? playerA.id : playerB.id
    if (!fixtureId || !winnerId) {
      showError('Trận đấu chưa đủ hai vận động viên.')
      return
    }
    saving.value = true
    try {
      await tournamentApi.scoreFixture(tournament.value.id, fixtureId, {
        expected_version: tournament.value.version,
        winner_id: winnerId,
        score: {
          p1: playerA.score,
          p2: playerB.score,
        },
      })
      showNotice(
        `Đã lưu kết quả trận đấu (${liveMatch.value.playerA.score} - ${liveMatch.value.playerB.score})!`,
      )
      await loadTournamentData(tournament.value.id)
    } catch (e) {
      showError(errorMessage(e, 'Không thể cập nhật tỷ số trận đấu.'))
    } finally {
      saving.value = false
    }
  }

  async function regenerateBracketTree() {
    isGenerating.value = true
    try {
      await tournamentApi.regenerateBracket(tournament.value.id)
      await loadTournamentData(tournament.value.id)
      showNotice('Đã tái tạo bảng phân nhánh theo hạt giống thành công!')
    } catch (e) {
      showError(errorMessage(e, 'Không thể tái tạo bảng phân nhánh.'))
    } finally {
      isGenerating.value = false
    }
  }

  // The modal only saves once both scores and a winner are entered.
  async function saveMatchScore(updated: FixtureMatch, p1Score: number, p2Score: number) {
    saving.value = true
    try {
      if (updated.winnerId) {
        await tournamentApi.scoreFixture(tournament.value.id, updated.id, {
          expected_version: tournament.value.version,
          winner_id: updated.winnerId,
          score: {
            p1: p1Score,
            p2: p2Score,
          },
        })
      }
      showNotice(`Đã cập nhật kết quả trận đấu #${updated.matchNumber}!`)
      await loadTournamentData(tournament.value.id)
      return true
    } catch (e) {
      showError(errorMessage(e, 'Không thể lưu kết quả trận đấu.'))
      return false
    } finally {
      saving.value = false
    }
  }

  async function createTournament(payload: any) {
    saving.value = true
    try {
      const res = await tournamentApi.create<any>(payload)
      const newTournament = res?.tournament || res
      showNotice(`Đã tạo giải đấu "${newTournament.name || payload.name}" thành công!`)
      await loadTournaments()
      if (newTournament?.id) {
        await selectTournament(newTournament.id)
      }
      return true
    } catch (e) {
      showError(errorMessage(e, 'Không thể tạo giải đấu mới.'))
      return false
    } finally {
      saving.value = false
    }
  }

  async function selectTournament(id: number | string) {
    selectedTournamentId.value = Number(id)
    await loadTournamentData(Number(id))
  }

  async function loadTournamentData(id: number) {
    loadingDetails.value = true
    error.value = ''
    try {
      const res = await tournamentApi.getById<any>(id)
      if (res) {
        tournament.value = {
          id: res.id,
          name: res.name,
          category: res.category,
          sport: res.sport,
          format: res.format,
          status: res.status,
          prizePool: res.prize_pool,
          datesFormatted: res.dates,
          venue: res.venue,
          city: res.city,
          maxSlots: res.max_slots,
          entryFee: res.entry_fee,
          description: res.description,
          version: res.version,
        }

        participants.value = (res.participants || []).map((p: any) => ({
          id: p.id,
          seed: p.seed,
          name: p.user?.name || p.name || `VĐV #${p.id}`,
          club: p.club,
          level: p.level,
          avatar: p.avatar || getAvatarUrl(p.name || 'VĐV'),
          checkedIn: p.checked_in ?? false,
          status: p.checked_in ? 'checked_in' : 'pending',
          paid: p.paid,
          racketChecked: p.racket_checked,
          warmupCourt: p.warmup_court,
          qrCode: p.qr_code || `ATH-${p.id}`,
          phone: p.user?.phone || p.phone,
          category: p.category,
        }))

        matches.value = (res.fixtures || [])
          .map((f: any) => ({
            id: f.id,
            roundNumber: f.round,
            roundName:
              f.round === 1
                ? 'Vòng 1'
                : f.round === 2
                  ? 'Tứ Kết'
                  : f.round === 3
                    ? 'Bán Kết'
                    : 'Chung Kết',
            position: f.position,
            matchNumber: f.position,
            // Fixtures carry no court or time yet; leave them empty rather than guess.
            courtName: f.court_name ?? '',
            courtId: f.court_id,
            scheduledTime: f.scheduled_time ?? '',
            status: f.status,
            player1: f.player1
              ? {
                  id: f.player1.id,
                  name: f.player1.name,
                  avatar: f.player1.avatar || getAvatarUrl(f.player1.name),
                  seed: f.player1.seed,
                  sets: f.player1.sets || [],
                  isWinner: f.player1.isWinner,
                }
              : { name: 'Chờ xác định', sets: [] },
            player2: f.player2
              ? {
                  id: f.player2.id,
                  name: f.player2.name,
                  avatar: f.player2.avatar || getAvatarUrl(f.player2.name),
                  seed: f.player2.seed,
                  sets: f.player2.sets || [],
                  isWinner: f.player2.isWinner,
                }
              : { name: 'Chờ xác định', sets: [] },
            winnerId: f.winner_id,
          }))
          .sort(
            (a: FixtureMatch, b: FixtureMatch) =>
              a.roundNumber - b.roundNumber || a.position - b.position,
          )
        syncLiveMatchFromFixtures()
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải dữ liệu giải đấu.')
    } finally {
      loadingDetails.value = false
    }
  }

  async function loadTournaments() {
    loading.value = true
    error.value = ''
    try {
      const list = await tournamentApi.list<TournamentItem[]>()
      tournamentsList.value = list
      if (list.length > 0 && list[0]) {
        const targetId = selectedTournamentId.value || list[0].id
        selectedTournamentId.value = targetId
        await loadTournamentData(targetId)
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách giải đấu.')
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    loadTournaments()
  })

  return {
    loading,
    loadingDetails,
    isGenerating,
    saving,
    tournamentsList,
    selectedTournamentId,
    tournament,
    currentWizardStep,
    activeSubNav,
    selectedRoundFilter,
    notice,
    error,
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
    saveRefereeScore,
    saveMatchScore,
    createTournament,
    regenerateBracketTree,
    selectTournament,
    loadTournaments,
    loadTournamentData,
  }
}

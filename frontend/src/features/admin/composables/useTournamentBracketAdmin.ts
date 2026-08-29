import { ref, computed } from 'vue'
import type { Participant, FixtureMatch, TournamentAdminData } from '../types/tournamentAdmin'
import { getAvatarUrl } from '@/lib/avatar'

export function useTournamentBracketAdmin() {
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
  const participants = ref<Participant[]>([
    {
      id: 1,
      seed: 2,
      name: 'Sarah Jenkins',
      club: 'Apex Club',
      level: 'Lvl 4',
      avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuDJsiguMlxpH29yatca1UobOCVIf_jqlNd-KEux5AFneF-dJhGzpeO2YvTu1zAZzRmVmYjE0qMOk13bxRZ62pAjNLyiH4Bpt9UiIRAqNF2-a9PiROabximiKmNHWIJCL26hvjN0teoyI7cSsOvurJPA_d2zdc--qwO2LUwZl3B8rqGlKZccm6utDCLsD4fMoNSpWruuSdmZR2mGWjEs9cU-xcGUDefJmr0Ac8KRatJ4c__kOTuwnkzn',
      checkedIn: true,
      status: 'checked_in',
      checkInTime: '08:14 AM',
      paid: true,
      racketChecked: true,
      warmupCourt: 'Sân 1',
      qrCode: 'ATH-001',
      phone: '0901 234 567',
      category: 'Đơn Nữ Nâng Cao',
    },
    {
      id: 2,
      name: 'Marcus Chen',
      club: 'Independent',
      level: 'Lvl 5',
      initials: 'MC',
      checkedIn: false,
      status: 'pending',
      estimatedArrival: 'Est. 09:00 AM',
      paid: true,
      racketChecked: false,
      qrCode: 'ATH-002',
      phone: '0902 345 678',
      category: 'Đơn Nam Master',
    },
    {
      id: 3,
      name: 'David Rossi',
      club: 'Velocity BC',
      level: 'Lvl 3',
      avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuCMl0Mcaf--VMhvZnDiJsrRPfbPXP7RrOASs2LTNoHN382mwuPFUQm22uwkIg08iVKCB0bcRNxQyar61RWnVrEKTWNSrtaF8ycKTImSPgM40HW8LTZpkdyHt8rou4LkgwpOItOjvMMsnfe8prsgRbqBP8JVnsUOTMzVtdTqfsU7nWv6Rsqnxd1i2Y_8tvjkb0lrkxhk2M2KPfMI3J2H8UjyK7oPA4i3Dv65NSNPkCo9wQ5-QprN0tuf',
      checkedIn: false,
      status: 'pending',
      estimatedArrival: 'Est. 09:30 AM',
      paid: true,
      racketChecked: false,
      qrCode: 'ATH-003',
      phone: '0903 456 789',
      category: 'Đơn Nam Phong Trào',
    },
    {
      id: 4,
      seed: 1,
      name: 'Chen Long',
      club: 'Aura Elite',
      level: 'Lvl 5',
      avatar: getAvatarUrl('Chen Long'),
      checkedIn: true,
      status: 'checked_in',
      checkInTime: '08:00 AM',
      paid: true,
      racketChecked: true,
      warmupCourt: 'Sân VIP 1',
      qrCode: 'ATH-004',
      phone: '0904 567 890',
      category: 'Đơn Nam Chuyên Nghiệp',
    },
    {
      id: 5,
      seed: 2,
      name: 'Viktor Axelsen',
      club: 'Viking Smash',
      level: 'Lvl 5',
      avatar: getAvatarUrl('Viktor Axelsen'),
      checkedIn: true,
      status: 'checked_in',
      checkInTime: '08:15 AM',
      paid: true,
      racketChecked: true,
      warmupCourt: 'Sân VIP 2',
      qrCode: 'ATH-005',
      phone: '0905 678 901',
      category: 'Đơn Nam Chuyên Nghiệp',
    },
  ])

  // Athlete Check-in Filtering & Metrics
  const checkInFilterStatus = ref<'all' | 'pending' | 'checked_in' | 'no_show'>('all')
  const checkInSearchQuery = ref('')

  const checkInMetrics = computed(() => {
    const total = 142
    const checkedInCount = participants.value.filter((p) => p.checkedIn || p.status === 'checked_in').length + 56
    const pendingCount = total - checkedInCount
    const percentage = Math.round((checkedInCount / total) * 100)
    return {
      total,
      checkedInCount,
      pendingCount,
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

  function toggleAthleteCheckIn(ath: Participant) {
    ath.checkedIn = !ath.checkedIn
    ath.status = ath.checkedIn ? 'checked_in' : 'pending'
    if (ath.checkedIn) {
      ath.checkInTime = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
      showNotice(`Đã điểm danh VĐV: ${ath.name}`)
    } else {
      ath.checkInTime = undefined
      showNotice(`Đã huỷ điểm danh: ${ath.name}`)
    }
  }

  // Matches Pool
  const matches = ref<FixtureMatch[]>([
    {
      id: 101,
      roundNumber: 1,
      roundName: 'Round of 16',
      position: 1,
      matchNumber: 1,
      courtName: 'Sân 1',
      courtId: 1,
      scheduledTime: '09:00',
      status: 'completed',
      player1: { id: 4, seed: 1, name: 'Chen Long', avatar: getAvatarUrl('Chen Long'), sets: [21, 21], isWinner: true },
      player2: { id: 7, name: 'H. S. Prannoy', avatar: getAvatarUrl('H. S. Prannoy'), sets: [14, 18], isWinner: false },
      winnerId: 4,
      nextMatchId: 201,
      nextMatchSlot: 1,
    },
    {
      id: 102,
      roundNumber: 1,
      roundName: 'Round of 16',
      position: 2,
      matchNumber: 2,
      courtName: 'Sân 2',
      courtId: 2,
      scheduledTime: '09:45',
      status: 'completed',
      player1: { id: 8, name: 'L. Sen', avatar: getAvatarUrl('L. Sen'), sets: [19, 21, 21], isWinner: true },
      player2: { id: 9, name: 'Chou Tien-Chen', avatar: getAvatarUrl('Chou Tien-Chen'), sets: [21, 17, 16], isWinner: false },
      winnerId: 8,
      nextMatchId: 201,
      nextMatchSlot: 2,
    },
    {
      id: 201,
      roundNumber: 2,
      roundName: 'Quarter Finals',
      position: 1,
      matchNumber: 5,
      courtName: 'Sân Trung Tâm A',
      courtId: 1,
      scheduledTime: '14:00',
      status: 'live',
      player1: { id: 4, seed: 1, name: 'Chen Long', avatar: getAvatarUrl('Chen Long'), sets: [21, 14], currentScore: 18, isServing: true },
      player2: { id: 8, name: 'L. Sen', avatar: getAvatarUrl('L. Sen'), sets: [17, 21], currentScore: 17, isServing: false },
      nextMatchId: 301,
      nextMatchSlot: 1,
    },
    {
      id: 301,
      roundNumber: 3,
      roundName: 'Semi Finals',
      position: 1,
      matchNumber: 7,
      courtName: 'Sân Trung Tâm A',
      courtId: 1,
      scheduledTime: '16:30',
      status: 'pending',
      player1: { name: 'Thắng Tứ Kết 1', sets: [] },
      player2: { name: 'Thắng Tứ Kết 2', sets: [] },
      nextMatchId: 401,
      nextMatchSlot: 1,
    },
    {
      id: 401,
      roundNumber: 4,
      roundName: 'Finals',
      position: 1,
      matchNumber: 8,
      courtName: 'Sân Chung Kết VIP',
      courtId: 1,
      scheduledTime: '18:00',
      status: 'pending',
      player1: { name: 'Thắng Bán Kết 1', sets: [] },
      player2: { name: 'Thắng Bán Kết 2', sets: [] },
    },
  ])

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

  return {
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
  }
}

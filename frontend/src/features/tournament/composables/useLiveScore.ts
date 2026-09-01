import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { tournamentApi } from '../api/tournamentApi'
import { getAvatarUrl } from '@/lib/avatar'
import { errorMessage } from '@/core/utils/errorMessage'

export type PointActionType = 'point' | 'smash' | 'net' | 'ace' | 'error'

export interface MatchEvent {
  id: number
  player: string
  action: string
  score: string
  type: 'winner' | 'error' | 'ace' | 'shot' | 'system'
  time: string
}

export interface MatchStats {
  aces: [number, number]
  winners: [number, number]
  unforced_errors: [number, number]
  first_serve_pct: [number, number]
  break_points: [string, string]
  max_consecutive_pts: [number, number]
  net_points_won_pct: [number, number]
  total_points_won: [number, number]
  smash_speed_max: string
  longest_rally: string
  duration: string
}

export interface MatchFixture {
  id: number
  tournament_id: number
  tournament_uuid?: string
  tournament_name: string
  court_label: string
  round_label: string
  status: 'live' | 'completed' | 'upcoming'
  player1: {
    name: string
    avatar: string
    seed: string
    club: string
    sets_won: number
    current_score: number
  }
  player2: {
    name: string
    avatar: string
    seed: string
    club: string
    sets_won: number
    current_score: number
  }
  current_set: number
  serving_player: 1 | 2
  version: number
  sets_history: Array<{ set: number; p1: number; p2: number; winner?: 1 | 2 }>
  stats: MatchStats
  winner?: 1 | 2
}

interface MatchSnapshot {
  fixtureId: number
  current_set: number
  p1_score: number
  p2_score: number
  p1_sets_won: number
  p2_sets_won: number
  serving_player: 1 | 2
  status: 'live' | 'completed' | 'upcoming'
  sets_history: Array<{ set: number; p1: number; p2: number; winner?: 1 | 2 }>
  stats: MatchStats
  events: MatchEvent[]
}

export function useLiveScore() {
  const route = useRoute()

  const loading = ref(false)
  const isUmpireMode = ref(false)
  const notice = ref('')
  const error = ref('')
  const tournamentTitle = ref('Giải Đấu Aura Circuit')

  const fixtures = ref<MatchFixture[]>([])
  const selectedFixtureId = ref<number | null>(null)
  const playEvents = ref<MatchEvent[]>([])
  const undoStack = ref<MatchSnapshot[]>([])

  // Timeout / Interval timer
  const intervalSeconds = ref<number | null>(null)
  let intervalTimer: any = null

  const activeMatch = computed(() => {
    if (!selectedFixtureId.value) return fixtures.value[0] || null
    return fixtures.value.find((f) => f.id === selectedFixtureId.value) || fixtures.value[0] || null
  })

  // Check Game Point / Match Point state
  const pointState = computed(() => {
    const m = activeMatch.value
    if (!m || m.status === 'completed') return null

    const p1 = m.player1.current_score
    const p2 = m.player2.current_score

    // P1 at game point
    if ((p1 >= 20 && p1 > p2) || (p1 === 29 && p2 === 29)) {
      if (m.player1.sets_won === 1 || (m.current_set === 3 && m.player1.sets_won === 1)) {
        return { type: 'match_point', player: 1, text: `⚡ MATCH POINT CHO ${m.player1.name}` }
      }
      return { type: 'game_point', player: 1, text: `GAME POINT CHO ${m.player1.name} (SET ${m.current_set})` }
    }

    // P2 at game point
    if ((p2 >= 20 && p2 > p1) || (p1 === 29 && p2 === 29)) {
      if (m.player2.sets_won === 1 || (m.current_set === 3 && m.player2.sets_won === 1)) {
        return { type: 'match_point', player: 2, text: `⚡ MATCH POINT CHO ${m.player2.name}` }
      }
      return { type: 'game_point', player: 2, text: `GAME POINT CHO ${m.player2.name} (SET ${m.current_set})` }
    }

    return null
  })

  function generateStats(p1Score: number, p2Score: number, round: number): MatchStats {
    const p1Total = p1Score > 0 ? p1Score + Math.floor(round * 2) : 18
    const p2Total = p2Score > 0 ? p2Score + Math.floor(round * 1.5) : 14
    return {
      aces: [Math.max(1, Math.floor(p1Total / 7)), Math.max(1, Math.floor(p2Total / 9))],
      winners: [Math.max(3, Math.floor(p1Total * 0.45)), Math.max(2, Math.floor(p2Total * 0.38))],
      unforced_errors: [Math.max(2, Math.floor(p2Total * 0.25)), Math.max(3, Math.floor(p1Total * 0.3))],
      first_serve_pct: [68 + (p1Score % 12), 64 + (p2Score % 15)],
      break_points: [`${Math.max(1, Math.floor(p1Total / 8))}/${Math.max(2, Math.floor(p1Total / 5))}`, `${Math.max(0, Math.floor(p2Total / 10))}/${Math.max(1, Math.floor(p2Total / 7))}`],
      max_consecutive_pts: [Math.max(3, Math.min(8, Math.floor(p1Total / 4))), Math.max(2, Math.min(6, Math.floor(p2Total / 5)))],
      net_points_won_pct: [72 + (p1Score % 14), 60 + (p2Score % 16)],
      total_points_won: [p1Total, p2Total],
      smash_speed_max: `${340 + ((p1Score * 7) % 65)} km/h`,
      longest_rally: `${14 + ((p1Score + p2Score) % 18)} đường cầu`,
      duration: `${24 + ((p1Score + p2Score) % 35)} phút`,
    }
  }

  function takeSnapshot(): MatchSnapshot | null {
    const m = activeMatch.value
    if (!m) return null
    return {
      fixtureId: m.id,
      current_set: m.current_set,
      p1_score: m.player1.current_score,
      p2_score: m.player2.current_score,
      p1_sets_won: m.player1.sets_won,
      p2_sets_won: m.player2.sets_won,
      serving_player: m.serving_player,
      status: m.status,
      sets_history: JSON.parse(JSON.stringify(m.sets_history)),
      stats: JSON.parse(JSON.stringify(m.stats)),
      events: JSON.parse(JSON.stringify(playEvents.value)),
    }
  }

  async function loadLiveMatches() {
    loading.value = true
    error.value = ''
    try {
      const targetParam = route?.params?.id ? String(route.params.id) : null
      let targetTourneys: any[] = []

      if (targetParam) {
        try {
          const single = await tournamentApi.getById<any>(targetParam)
          if (single) {
            targetTourneys = [single]
            tournamentTitle.value = single.name || 'Giải Đấu Aura Circuit'
          }
        } catch {
          const tourneys = await tournamentApi.list<any[]>()
          const list = Array.isArray(tourneys) ? tourneys : []
          const found = list.find((t) => t.uuid === targetParam || String(t.id) === targetParam || t.slug === targetParam)
          if (found) {
            const single = await tournamentApi.getById<any>(found.uuid || found.id)
            targetTourneys = single ? [single] : [found]
            tournamentTitle.value = found.name || 'Giải Đấu Aura Circuit'
          } else {
            targetTourneys = list.slice(0, 3)
          }
        }
      } else {
        const tourneys = await tournamentApi.list<any[]>()
        const list = Array.isArray(tourneys) ? tourneys : []
        targetTourneys = list.slice(0, 3)
      }

      const liveList: MatchFixture[] = []

      for (const t of targetTourneys) {
        try {
          const detail = t.fixtures ? t : await tournamentApi.getById<any>(t.uuid || t.id)
          const participants = detail.participants || []
          const participantMap = new Map<number, any>(participants.map((p: any) => [p.id, p]))

          if (Array.isArray(detail.fixtures) && detail.fixtures.length > 0) {
            for (const f of detail.fixtures) {
              const p1 = f.participant_one_id ? participantMap.get(f.participant_one_id) : null
              const p2 = f.participant_two_id ? participantMap.get(f.participant_two_id) : null
              let scoreObj = f.score
              if (typeof scoreObj === 'string') {
                try {
                  scoreObj = JSON.parse(scoreObj)
                } catch {
                  scoreObj = null
                }
              }
              const p1Score = scoreObj?.p1 ?? (f.winner_id === f.participant_one_id ? 21 : (f.status === 'ongoing' ? 14 : 0))
              const p2Score = scoreObj?.p2 ?? (f.winner_id === f.participant_two_id ? 21 : (f.status === 'ongoing' ? 11 : 0))
              const p1Name = p1?.name || p1?.user?.name || (f.participant_one_id ? `VĐV #${f.participant_one_id}` : 'Chờ xác định')
              const p2Name = p2?.name || p2?.user?.name || (f.participant_two_id ? `VĐV #${f.participant_two_id}` : 'Chờ xác định')
              const p1Avatar = p1?.avatar || p1?.user?.avatar_url || getAvatarUrl(p1Name)
              const p2Avatar = p2?.avatar || p2?.user?.avatar_url || getAvatarUrl(p2Name)

              const roundLabel = f.round === 1 ? 'Tứ Kết' : f.round === 2 ? 'Bán Kết' : 'Chung Kết'
              const statusVal = f.status === 'completed' ? 'completed' : f.status === 'ongoing' ? 'live' : 'upcoming'

              liveList.push({
                id: f.id,
                tournament_id: detail.id,
                tournament_uuid: detail.uuid,
                tournament_name: detail.name || 'Giải Đấu Aura',
                court_label: f.court_name || `Sân A${f.position || 1}`,
                round_label: `${roundLabel} ${f.position || 1}`,
                status: statusVal,
                player1: {
                  name: p1Name,
                  avatar: p1Avatar,
                  seed: p1?.seed ? `Hạt giống #${p1.seed}` : `#${(f.position || 1) * 2 - 1}`,
                  club: p1?.club || 'Aura Elite Club',
                  sets_won: f.winner_id === f.participant_one_id ? 2 : 0,
                  current_score: p1Score,
                },
                player2: {
                  name: p2Name,
                  avatar: p2Avatar,
                  seed: p2?.seed ? `Hạt giống #${p2.seed}` : `#${(f.position || 1) * 2}`,
                  club: p2?.club || 'CLB Tự Do',
                  sets_won: f.winner_id === f.participant_two_id ? 2 : 0,
                  current_score: p2Score,
                },
                current_set: 1,
                serving_player: 1,
                version: f.version || 1,
                sets_history: scoreObj?.sets || [{ set: 1, p1: p1Score, p2: p2Score }],
                stats: generateStats(p1Score, p2Score, f.round || 1),
              })
            }
          }
        } catch {
          // Skip
        }
      }

      const sortedList = liveList.sort((a, b) => {
        const priority: Record<string, number> = { live: 1, upcoming: 2, completed: 3 }
        return (priority[a.status] || 2) - (priority[b.status] || 2)
      })

      fixtures.value = sortedList
      if (sortedList.length > 0 && sortedList[0]) {
        selectedFixtureId.value = sortedList[0].id
        const active = sortedList[0]
        playEvents.value = [
          {
            id: 1,
            player: active.player1.name,
            action: 'Cú smash uy lực chéo sân ghi điểm trực tiếp',
            score: `${active.player1.current_score} - ${active.player2.current_score}`,
            type: 'winner',
            time: '08:42',
          },
          {
            id: 2,
            player: active.player2.name,
            action: 'Bỏ nhỏ tinh tế sát mép lưới thành công',
            score: `${Math.max(0, active.player1.current_score - 1)} - ${active.player2.current_score}`,
            type: 'winner',
            time: '08:40',
          },
          {
            id: 3,
            player: active.player1.name,
            action: 'Pha giao cầu điểm rơi chính xác',
            score: `${Math.max(0, active.player1.current_score - 1)} - ${Math.max(0, active.player2.current_score - 1)}`,
            type: 'ace',
            time: '08:38',
          },
        ]
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải điểm số trực tiếp.')
      fixtures.value = []
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

  /**
   * Check badminton set victory:
   * Rule: First to 21 points with a 2-point lead, or first to 30 points.
   */
  function checkSetWinner(p1: number, p2: number): 1 | 2 | null {
    if ((p1 >= 21 && p1 - p2 >= 2) || p1 >= 30) return 1
    if ((p2 >= 21 && p2 - p1 >= 2) || p2 >= 30) return 2
    return null
  }

  /**
   * Main Umpire Point Injection with Badminton Rules Engine
   */
  async function addPoint(playerIndex: 1 | 2, actionType: PointActionType = 'point') {
    if (!activeMatch.value) return
    const match = activeMatch.value
    if (match.status === 'completed') {
      showNotice('Trận đấu đã kết thúc. Vui lòng chọn trận đấu khác.')
      return
    }

    // Save snapshot before mutating for robust Undo
    const snapshot = takeSnapshot()
    if (snapshot) {
      undoStack.value.push(snapshot)
      if (undoStack.value.length > 20) undoStack.value.shift()
    }

    // Determine scoring player and opponent
    const scorer = playerIndex === 1 ? match.player1 : match.player2
    const opponent = playerIndex === 1 ? match.player2 : match.player1
    const idx: 0 | 1 = playerIndex === 1 ? 0 : 1
    const oppIdx: 0 | 1 = playerIndex === 1 ? 1 : 0

    scorer.current_score++
    match.serving_player = playerIndex
    match.stats.total_points_won[idx] = (match.stats.total_points_won[idx] || 0) + 1

    // Build event description based on Badminton point action
    let actionDesc = 'Ghi điểm trực tiếp'
    let eventType: MatchEvent['type'] = 'winner'

    if (actionType === 'smash') {
      actionDesc = 'Cú smash uy lực chéo sân ghi điểm'
      match.stats.winners[idx] = (match.stats.winners[idx] || 0) + 1
      eventType = 'winner'
    } else if (actionType === 'net') {
      actionDesc = 'Bỏ nhỏ / bắt lưới thành công'
      match.stats.winners[idx] = (match.stats.winners[idx] || 0) + 1
      match.stats.net_points_won_pct[idx] = Math.min(100, (match.stats.net_points_won_pct[idx] || 0) + 2)
      eventType = 'winner'
    } else if (actionType === 'ace') {
      actionDesc = 'Giao bóng ăn điểm trực tiếp (Ace)'
      match.stats.aces[idx] = (match.stats.aces[idx] || 0) + 1
      eventType = 'ace'
    } else if (actionType === 'error') {
      actionDesc = `Đối thủ (${opponent.name}) đánh hỏng / ra ngoài`
      match.stats.unforced_errors[oppIdx] = (match.stats.unforced_errors[oppIdx] || 0) + 1
      eventType = 'error'
    }

    const currentTimeStr = new Date().toTimeString().slice(0, 5)

    playEvents.value.unshift({
      id: Date.now(),
      player: scorer.name,
      action: actionDesc,
      score: `${match.player1.current_score} - ${match.player2.current_score}`,
      type: eventType,
      time: currentTimeStr,
    })

    // Check Set Victory according to BWF rules
    const setWinner = checkSetWinner(match.player1.current_score, match.player2.current_score)

    if (setWinner) {
      const setWinnerPlayer = setWinner === 1 ? match.player1 : match.player2
      const finalP1 = match.player1.current_score
      const finalP2 = match.player2.current_score

      // Record set history
      const finishedSetIdx = match.sets_history.findIndex((s) => s.set === match.current_set)
      if (finishedSetIdx >= 0 && match.sets_history[finishedSetIdx]) {
        match.sets_history[finishedSetIdx].p1 = finalP1
        match.sets_history[finishedSetIdx].p2 = finalP2
        match.sets_history[finishedSetIdx].winner = setWinner
      } else {
        match.sets_history.push({
          set: match.current_set,
          p1: finalP1,
          p2: finalP2,
          winner: setWinner,
        })
      }

      setWinnerPlayer.sets_won++

      // Check Match Victory (Best of 3: first to 2 sets)
      if (setWinnerPlayer.sets_won >= 2 || match.current_set >= 3) {
        match.status = 'completed'
        match.winner = setWinner
        showNotice(`🏆 ${setWinnerPlayer.name} THẮNG CHUNG CUỘC (${match.player1.sets_won} - ${match.player2.sets_won})!`)
        playEvents.value.unshift({
          id: Date.now() + 1,
          player: setWinnerPlayer.name,
          action: `🏆 KẾT THÚC TRẬN: Thắng chung cuộc ${match.player1.sets_won} - ${match.player2.sets_won}!`,
          score: `${finalP1} - ${finalP2}`,
          type: 'system',
          time: currentTimeStr,
        })
      } else {
        // Advance to next set
        const completedSetNum = match.current_set
        match.current_set++
        match.player1.current_score = 0
        match.player2.current_score = 0
        match.serving_player = setWinner // Winner of set serves first in next set
        match.sets_history.push({
          set: match.current_set,
          p1: 0,
          p2: 0,
        })

        showNotice(`🏸 ${setWinnerPlayer.name} thắng Set ${completedSetNum} (${finalP1} - ${finalP2})! Bắt đầu Set ${match.current_set}.`)
        playEvents.value.unshift({
          id: Date.now() + 1,
          player: setWinnerPlayer.name,
          action: `Thắng Set ${completedSetNum} (${finalP1} - ${finalP2}). Đổi sân & Bắt đầu Set ${match.current_set}`,
          score: `0 - 0`,
          type: 'system',
          time: currentTimeStr,
        })
      }
    }

    // Sync optimistic score to API
    try {
      await tournamentApi.scoreFixture(match.tournament_id, match.id, {
        p1_score: match.player1.current_score,
        p2_score: match.player2.current_score,
        set: match.current_set,
      })
    } catch {
      // Retain state
    }
  }

  function undoPoint() {
    if (!activeMatch.value || undoStack.value.length === 0) {
      showNotice('Không có thao tác nào để hoàn tác.')
      return
    }

    const prev = undoStack.value.pop()
    if (!prev) return

    const m = activeMatch.value
    m.current_set = prev.current_set
    m.player1.current_score = prev.p1_score
    m.player2.current_score = prev.p2_score
    m.player1.sets_won = prev.p1_sets_won
    m.player2.sets_won = prev.p2_sets_won
    m.serving_player = prev.serving_player
    m.status = prev.status
    m.sets_history = prev.sets_history
    m.stats = prev.stats
    playEvents.value = prev.events

    showNotice('Đã hoàn tác điểm số / hiệp đấu trước đó.')
  }

  function toggleServer() {
    if (!activeMatch.value) return
    activeMatch.value.serving_player = activeMatch.value.serving_player === 1 ? 2 : 1
    const pName = activeMatch.value.serving_player === 1 ? activeMatch.value.player1.name : activeMatch.value.player2.name
    showNotice(`Đã chuyển quyền giao cầu sang: ${pName}`)
  }

  function switchSides() {
    if (!activeMatch.value) return
    showNotice('Trọng tài yêu cầu hai bên ĐỔI SÂN.')
    playEvents.value.unshift({
      id: Date.now(),
      player: 'Trọng tài sân',
      action: 'Hai bên đổi phần sân thi đấu (Change Ends)',
      score: `${activeMatch.value.player1.current_score} - ${activeMatch.value.player2.current_score}`,
      type: 'system',
      time: new Date().toTimeString().slice(0, 5),
    })
  }

  function penaltyCard(playerIndex: 1 | 2, card: 'yellow' | 'red') {
    if (!activeMatch.value) return
    const m = activeMatch.value
    const target = playerIndex === 1 ? m.player1 : m.player2
    const opp = playerIndex === 1 ? m.player2 : m.player1

    if (card === 'yellow') {
      showNotice(`⚠️ CẢNH CÁO THẺ VÀNG cho ${target.name}!`)
      playEvents.value.unshift({
        id: Date.now(),
        player: target.name,
        action: '⚠️ Nhận thẻ vàng cảnh cáo từ trọng tài',
        score: `${m.player1.current_score} - ${m.player2.current_score}`,
        type: 'system',
        time: new Date().toTimeString().slice(0, 5),
      })
    } else {
      showNotice(`🟥 PHẠT THẺ ĐỎ cho ${target.name}! +1 Điểm phạt cho ${opp.name}.`)
      playEvents.value.unshift({
        id: Date.now(),
        player: target.name,
        action: `🟥 Nhận thẻ đỏ phạt điểm (+1 điểm cho ${opp.name})`,
        score: `${m.player1.current_score} - ${m.player2.current_score}`,
        type: 'system',
        time: new Date().toTimeString().slice(0, 5),
      })
      // Give point to opponent
      addPoint(playerIndex === 1 ? 2 : 1, 'point')
    }
  }

  function triggerInterval(seconds: number = 60) {
    if (intervalTimer) clearInterval(intervalTimer)
    intervalSeconds.value = seconds
    showNotice(`Đang tạm dừng hội ý: ${seconds}s`)

    intervalTimer = setInterval(() => {
      if (intervalSeconds.value && intervalSeconds.value > 0) {
        intervalSeconds.value--
      } else {
        clearInterval(intervalTimer)
        intervalSeconds.value = null
        showNotice('Hết giờ hội ý! Trận đấu tiếp tục.')
      }
    }, 1000)
  }

  watch(
    () => route.params.id,
    () => {
      loadLiveMatches()
    }
  )

  onMounted(() => {
    loadLiveMatches()
  })

  return {
    loading,
    isUmpireMode,
    notice,
    error,
    tournamentTitle,
    fixtures,
    selectedFixtureId,
    activeMatch,
    playEvents,
    pointState,
    intervalSeconds,
    showNotice,
    addPoint,
    undoPoint,
    toggleServer,
    switchSides,
    penaltyCard,
    triggerInterval,
    loadLiveMatches,
  }
}

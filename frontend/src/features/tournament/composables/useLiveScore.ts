import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { tournamentApi } from '../api/tournamentApi'
import { getAvatarUrl } from '@/lib/avatar'
import { errorMessage } from '@/core/utils/errorMessage'
import type { ApiError } from '@/core/types/api'

export type PointActionType = 'point' | 'smash' | 'net' | 'ace' | 'error'

export interface MatchEvent {
  id: number
  player: string
  action: string
  score: string
  type: 'winner' | 'error' | 'ace' | 'shot' | 'system'
  time: string
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
    participant_id?: number
    name: string
    avatar: string
    seed: string
    sets_won: number
    current_score: number
  }
  player2: {
    participant_id?: number
    name: string
    avatar: string
    seed: string
    sets_won: number
    current_score: number
  }
  current_set: number
  serving_player: 1 | 2
  // Last tournament version this page saw; the result is submitted against it.
  tournament_version: number
  sets_history: Array<{ set: number; p1: number; p2: number; winner?: 1 | 2 }>
  winner?: 1 | 2
  // A finished match whose result the server has not accepted yet.
  pending_result?: { saving: boolean; error: string }
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
  events: MatchEvent[]
}

export function useLiveScore() {
  const route = useRoute()

  const loading = ref(false)
  const isUmpireMode = ref(false)
  const notice = ref('')
  const error = ref('')
  const tournamentTitle = ref('')

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
      return {
        type: 'game_point',
        player: 1,
        text: `GAME POINT CHO ${m.player1.name} (SET ${m.current_set})`,
      }
    }

    // P2 at game point
    if ((p2 >= 20 && p2 > p1) || (p1 === 29 && p2 === 29)) {
      if (m.player2.sets_won === 1 || (m.current_set === 3 && m.player2.sets_won === 1)) {
        return { type: 'match_point', player: 2, text: `⚡ MATCH POINT CHO ${m.player2.name}` }
      }
      return {
        type: 'game_point',
        player: 2,
        text: `GAME POINT CHO ${m.player2.name} (SET ${m.current_set})`,
      }
    }

    return null
  })

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
            tournamentTitle.value = single.name || ''
          }
        } catch {
          const tourneys = await tournamentApi.list<any[]>()
          const list = Array.isArray(tourneys) ? tourneys : []
          const found = list.find(
            (t) => t.uuid === targetParam || String(t.id) === targetParam || t.slug === targetParam,
          )
          if (found) {
            const single = await tournamentApi.getById<any>(found.uuid || found.id)
            targetTourneys = single ? [single] : [found]
            tournamentTitle.value = found.name || ''
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
              // Without a recorded score the board starts at 0-0, the count the umpire adds to.
              const p1Score: number = scoreObj?.p1 ?? 0
              const p2Score: number = scoreObj?.p2 ?? 0
              const sets: MatchFixture['sets_history'] = scoreObj?.sets ?? []
              const p1Name =
                p1?.name ||
                p1?.user?.name ||
                (f.participant_one_id ? `VĐV #${f.participant_one_id}` : 'Chờ xác định')
              const p2Name =
                p2?.name ||
                p2?.user?.name ||
                (f.participant_two_id ? `VĐV #${f.participant_two_id}` : 'Chờ xác định')
              const p1Avatar = p1?.avatar || p1?.user?.avatar_url || getAvatarUrl(p1Name)
              const p2Avatar = p2?.avatar || p2?.user?.avatar_url || getAvatarUrl(p2Name)

              const roundLabel = f.round === 1 ? 'Tứ Kết' : f.round === 2 ? 'Bán Kết' : 'Chung Kết'
              const statusVal =
                f.status === 'completed'
                  ? 'completed'
                  : f.status === 'ongoing'
                    ? 'live'
                    : 'upcoming'

              liveList.push({
                id: f.id,
                tournament_id: detail.id,
                tournament_uuid: detail.uuid,
                tournament_name: detail.name,
                // Fixtures carry no court yet; leave it empty rather than guess.
                court_label: f.court_name ?? '',
                round_label: f.position ? `${roundLabel} ${f.position}` : roundLabel,
                status: statusVal,
                player1: {
                  participant_id: f.participant_one_id,
                  name: p1Name,
                  avatar: p1Avatar,
                  seed: p1?.seed ? `Hạt giống #${p1.seed}` : '',
                  sets_won: sets.filter((s) => s.p1 > s.p2).length,
                  current_score: p1Score,
                },
                player2: {
                  participant_id: f.participant_two_id,
                  name: p2Name,
                  avatar: p2Avatar,
                  seed: p2?.seed ? `Hạt giống #${p2.seed}` : '',
                  sets_won: sets.filter((s) => s.p2 > s.p1).length,
                  current_score: p2Score,
                },
                current_set: 1,
                serving_player: 1,
                tournament_version: detail.version,
                sets_history: scoreObj?.sets || [{ set: 1, p1: p1Score, p2: p2Score }],
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
      }
      // The API keeps no rally history; the ticker lists only what the umpire records here.
      playEvents.value = []
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
  function addPoint(playerIndex: 1 | 2, actionType: PointActionType = 'point') {
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

    scorer.current_score++
    match.serving_player = playerIndex

    // Build event description based on Badminton point action
    let actionDesc = 'Ghi điểm trực tiếp'
    let eventType: MatchEvent['type'] = 'winner'

    if (actionType === 'smash') {
      actionDesc = 'Cú smash uy lực chéo sân ghi điểm'
      eventType = 'winner'
    } else if (actionType === 'net') {
      actionDesc = 'Bỏ nhỏ / bắt lưới thành công'
      eventType = 'winner'
    } else if (actionType === 'ace') {
      actionDesc = 'Giao bóng ăn điểm trực tiếp (Ace)'
      eventType = 'ace'
    } else if (actionType === 'error') {
      actionDesc = `Đối thủ (${opponent.name}) đánh hỏng / ra ngoài`
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
        match.pending_result = { saving: false, error: '' }
        showNotice(
          `🏆 ${setWinnerPlayer.name} THẮNG CHUNG CUỘC (${match.player1.sets_won} - ${match.player2.sets_won})!`,
        )
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

        showNotice(
          `🏸 ${setWinnerPlayer.name} thắng Set ${completedSetNum} (${finalP1} - ${finalP2})! Bắt đầu Set ${match.current_set}.`,
        )
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
  }

  function setTournamentVersion(tournamentId: number, version: number) {
    for (const f of fixtures.value) {
      if (f.tournament_id === tournamentId) f.tournament_version = version
    }
  }

  /**
   * Record the finished match through the official result endpoint. The spec has no per-point
   * score command, so points stay on this screen until the umpire confirms the final result.
   */
  async function submitResult() {
    const match = activeMatch.value
    const pending = match?.pending_result
    if (!match || match.status !== 'completed' || !pending || pending.saving) return

    const expectedVersion = match.tournament_version
    pending.saving = true
    pending.error = ''
    try {
      await tournamentApi.scoreFixture(match.tournament_id, match.id, {
        expected_version: expectedVersion,
        winner_id: (match.winner === 1 ? match.player1 : match.player2).participant_id,
        // p1/p2 hold the deciding set's points and sets the full history, as the readers expect.
        score: {
          p1: match.player1.current_score,
          p2: match.player2.current_score,
          sets: match.sets_history,
        },
      })
      match.pending_result = undefined
      setTournamentVersion(match.tournament_id, expectedVersion + 1)
      showNotice('Đã lưu kết quả trận đấu.')
    } catch (e: unknown) {
      pending.error = errorMessage(e, 'Không thể lưu kết quả trận đấu.')
      // Another result moved the tournament version: resync it so confirming again can succeed.
      // A failed resync needs no message; the next attempt reports its own error.
      if ((e as ApiError)?.status === 409) {
        const fresh = await tournamentApi.getById(match.tournament_id).catch(() => null)
        if (fresh) setTournamentVersion(match.tournament_id, fresh.version)
      }
    } finally {
      pending.saving = false
    }
  }

  function undoPoint() {
    const m = activeMatch.value
    // Once the result is sent, only an official correction (with a reason) can change it.
    if (m?.status === 'completed' && (!m.pending_result || m.pending_result.saving)) {
      showNotice('Kết quả trận đấu đã được ghi nhận, không thể hoàn tác.')
      return
    }
    if (!m || undoStack.value.length === 0) {
      showNotice('Không có thao tác nào để hoàn tác.')
      return
    }

    const prev = undoStack.value.pop()
    if (!prev) return

    m.current_set = prev.current_set
    m.player1.current_score = prev.p1_score
    m.player2.current_score = prev.p2_score
    m.player1.sets_won = prev.p1_sets_won
    m.player2.sets_won = prev.p2_sets_won
    m.serving_player = prev.serving_player
    m.status = prev.status
    m.sets_history = prev.sets_history
    playEvents.value = prev.events

    showNotice('Đã hoàn tác điểm số / hiệp đấu trước đó.')
  }

  function toggleServer() {
    if (!activeMatch.value) return
    activeMatch.value.serving_player = activeMatch.value.serving_player === 1 ? 2 : 1
    const pName =
      activeMatch.value.serving_player === 1
        ? activeMatch.value.player1.name
        : activeMatch.value.player2.name
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
    },
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
    submitResult,
    undoPoint,
    toggleServer,
    switchSides,
    penaltyCard,
    triggerInterval,
    loadLiveMatches,
  }
}

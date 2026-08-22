<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiV2, errorMessage } from '@/lib/api'

type Match = { id: number; title: string; city?: string; starts_at: string; joined_count: number; required_participants: number; visibility: string }
type Fixture = { id: number; round: number; position: number; status: string; participant_one_id?: number; participant_two_id?: number; winner_id?: number; score?: Record<string, unknown> }
type Tournament = { id: number; name: string; format: string; status: string; version: number; fixtures: Fixture[] }
const profile = ref({ skill_level: 3, city: '', availability: [] as string[], preferred_positions: [] as string[] })
const matches = ref<Match[]>([])
const tournaments = ref<Tournament[]>([])
const selectedTournament = ref<Tournament | null>(null)
const matchForm = ref({ title: '', starts_at: '', ends_at: '', city: '', required_participants: 4, visibility: 'public' })
const tournamentForm = ref({ name: '', format: 'single_elimination', participant_ids: '' })
const error = ref('')
const notice = ref('')
const date = (value: string) => new Date(value).toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh' })

async function load(): Promise<void> {
  try {
    const [profileResponse, matchesResponse, tournamentsResponse] = await Promise.all([
      apiV2.get<typeof profile.value>('/social/profile'),
      apiV2.get<{ matches: Match[] }>('/social/matches'),
      apiV2.get<{ data: Tournament[] }>('/tournaments'),
    ])
    profile.value = { ...profile.value, ...profileResponse }
    matches.value = matchesResponse.matches || []
    tournaments.value = tournamentsResponse.data || []
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không tải được tính năng cộng đồng.') }
}

async function saveProfile(): Promise<void> {
  try { await apiV2.put('/social/profile', profile.value); notice.value = 'Đã lưu hồ sơ người chơi.' }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể lưu hồ sơ.') }
}

async function createMatch(): Promise<void> {
  try { await apiV2.post('/social/matches', { ...matchForm.value, starts_at: new Date(matchForm.value.starts_at).toISOString(), ends_at: new Date(matchForm.value.ends_at).toISOString() }); notice.value = 'Đã đăng trận đấu.'; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể đăng trận đấu.') }
}

async function joinMatch(match: Match): Promise<void> {
  try { await apiV2.post(`/social/matches/${match.id}/join`); notice.value = 'Đã tham gia trận đấu.'; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể tham gia trận đấu.') }
}

async function createTournament(): Promise<void> {
  try { await apiV2.post('/tournaments', { name: tournamentForm.value.name, format: tournamentForm.value.format, participant_ids: tournamentForm.value.participant_ids.split(',').map((id) => Number(id.trim())) }); notice.value = 'Đã tạo bracket/fixture xác định.'; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể tạo giải đấu.') }
}

async function openTournament(tournament: Tournament): Promise<void> {
  try { selectedTournament.value = (await apiV2.get<{ tournament: Tournament }>(`/tournaments/${tournament.id}`)).tournament }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không tải được bracket.') }
}

async function score(fixture: Fixture): Promise<void> {
  if (!selectedTournament.value || !fixture.participant_one_id) return
  try { await apiV2.post(`/tournaments/${selectedTournament.value.id}/fixtures/${fixture.id}/score`, { expected_version: selectedTournament.value.version, winner_id: fixture.participant_one_id, score: { winner: 1, loser: 0 } }); notice.value = 'Đã ghi nhận điểm và phát sự kiện realtime.'; await openTournament(selectedTournament.value) }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Điểm đã cũ; hãy đồng bộ lại bracket.') }
}

onMounted(load)
</script>

<template>
  <main class="page-shell">
    <section class="row"><div><div class="eyebrow">API v2 · Community</div><h1 class="page-title">Người chơi & giải đấu</h1><p class="muted">Hồ sơ, tìm trận, điều hành bracket và scoreboard theo phiên bản.</p></div></section>
    <p v-if="error" class="error">{{ error }}</p><p v-if="notice" class="notice">{{ notice }}</p>
    <section class="card" style="margin-top: 18px"><h2>Hồ sơ người chơi</h2><form class="row" style="margin-top: 14px; align-items: end" @submit.prevent="saveProfile"><label class="field">Trình độ<input v-model.number="profile.skill_level" type="number" min="1" max="5" /></label><label class="field">Thành phố<input v-model="profile.city" /></label><button class="btn">Lưu hồ sơ</button></form><p class="muted" style="margin-top: 10px">Vị trí được làm tròn để bảo vệ địa chỉ chính xác.</p></section>
    <section class="card" style="margin-top: 18px"><h2>Tìm & đăng trận</h2><form class="row" style="margin-top: 14px; align-items: end" @submit.prevent="createMatch"><label class="field">Tên trận<input v-model="matchForm.title" required /></label><label class="field">Bắt đầu<input v-model="matchForm.starts_at" type="datetime-local" required /></label><label class="field">Kết thúc<input v-model="matchForm.ends_at" type="datetime-local" required /></label><label class="field">Số người<input v-model.number="matchForm.required_participants" type="number" min="2" /></label><button class="btn">Đăng trận</button></form><div v-for="match in matches" :key="match.id" class="row" style="padding: 14px 0; border-bottom: 1px solid var(--line)"><div><strong>{{ match.title }}</strong><p class="muted">{{ date(match.starts_at) }} · {{ match.city || 'Không giới hạn khu vực' }}</p></div><div class="actions"><span class="badge">{{ match.joined_count }}/{{ match.required_participants }}</span><button class="link-button" @click="joinMatch(match)">Tham gia</button></div></div></section>
    <section class="card" style="margin-top: 18px"><h2>Điều hành tournament</h2><form class="row" style="margin-top: 14px; align-items: end" @submit.prevent="createTournament"><label class="field">Tên giải<input v-model="tournamentForm.name" required /></label><label class="field">Format<select v-model="tournamentForm.format"><option value="single_elimination">Single elimination</option><option value="round_robin">Round robin</option></select></label><label class="field" style="min-width: 260px">ID người chơi, cách nhau bằng dấu phẩy<input v-model="tournamentForm.participant_ids" required /></label><button class="btn">Tạo giải</button></form><div v-for="tournament in tournaments" :key="tournament.id" class="row" style="padding: 14px 0; border-bottom: 1px solid var(--line)"><div><strong>{{ tournament.name }}</strong><p class="muted">{{ tournament.format }} · phiên bản {{ tournament.version }}</p></div><button class="link-button" @click="openTournament(tournament)">Mở scoreboard</button></div></section>
    <section v-if="selectedTournament" class="card" style="margin-top: 18px"><h2>{{ selectedTournament.name }} · bracket/scoreboard</h2><div v-for="fixture in selectedTournament.fixtures" :key="fixture.id" class="row" style="padding: 12px 0; border-bottom: 1px solid var(--line)"><span>Vòng {{ fixture.round }} · fixture {{ fixture.position }}</span><span class="badge">{{ fixture.status }}</span><button v-if="fixture.status === 'pending'" class="link-button" @click="score(fixture)">Ghi điểm</button></div><p class="muted" style="margin-top: 10px">Nếu phiên bản đã thay đổi, hệ thống yêu cầu resync trước khi ghi điểm tiếp.</p></section>
  </main>
</template>

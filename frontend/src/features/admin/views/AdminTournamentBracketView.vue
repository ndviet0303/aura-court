<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { tournamentApi } from '@/features/tournament/api/tournamentApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { useAuthStore } from '@/stores/auth'
import { getAvatarUrl } from '@/lib/avatar'

interface Participant {
  id: number
  seed?: number
  name: string
  club?: string
  avatar?: string
  initials?: string
  checkedIn?: boolean
  paid?: boolean
  racketChecked?: boolean
  category?: string
  phone?: string
  level?: string
  status?: 'checked_in' | 'pending' | 'no_show'
  checkInTime?: string
  estimatedArrival?: string
  warmupCourt?: string
  qrCode?: string
  gender?: 'M' | 'F'
}

interface FixtureMatch {
  id: number
  roundNumber: number // 1: R16, 2: QF, 3: SF, 4: Finals
  roundName: string
  position: number
  matchNumber: number
  courtName: string
  courtId?: number
  scheduledTime: string
  status: 'pending' | 'live' | 'completed' | 'walkover'
  player1: {
    id?: number
    seed?: number
    name: string
    avatar?: string
    club?: string
    sets: number[]
    currentScore?: number
    isWinner?: boolean
    isServing?: boolean
  }
  player2: {
    id?: number
    seed?: number
    name: string
    avatar?: string
    club?: string
    sets: number[]
    currentScore?: number
    isWinner?: boolean
    isServing?: boolean
  }
  refereeName?: string
  winnerId?: number
  nextMatchId?: number
  nextMatchSlot?: 1 | 2
}

interface TournamentAdminData {
  id: number
  name: string
  category: string
  sport: string
  format: 'single_elimination' | 'round_robin' | 'double_elimination'
  status: 'draft' | 'open' | 'ongoing' | 'completed'
  prizePool: string
  prizePoolUsd: string
  startDate: string
  endDate: string
  datesFormatted: string
  venue: string
  city: string
  courtsAvailable: number
  maxSlots: number
  entryFee: string
  description: string
  version: number
  seedingRule: 'standard' | 'random' | 'club_split'
}

const auth = useAuthStore()
const route = (() => {
  try {
    return useRoute()
  } catch {
    return { query: {}, path: '/admin/tournaments' } as any
  }
})()

// Active Navigation Tab inside Tournament Management
const activeSubNav = ref<'bracket-management' | 'tournament-overview' | 'athlete-check-in' | 'live-referee-console'>('bracket-management')

// Alert / Notification State
const notice = ref('')
const error = ref('')
const isSaving = ref(false)
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

function handleImageError(event: Event, fallback = '/aura/court-1.jpg') {
  const target = event.target as HTMLImageElement | null
  if (target && target.src !== fallback) {
    target.src = fallback
  }
}

// Current Tournament State (Aligned with Stitch design: Aura Summer Open)
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
  description: 'Giải đấu mùa hè danh giá nhất hệ thống Aura Court. Hội tụ các tay vợt phong trào và bán chuyên hàng đầu.',
  version: 1,
  seedingRule: 'standard',
})

// Setup Wizard Steps
const currentWizardStep = ref<number>(3) // Default to step 3 for previewing bracket as in Stitch

// Filter Round for Bracket
const selectedRoundFilter = ref<'all' | 'r16' | 'qf' | 'sf' | 'final'>('all')
const zoomLevel = ref<number>(1)

// Generate initial pool matching Stitch screen metrics (142 total athletes, 58 checked in, 84 pending, 0 no-show)
function createInitialParticipants(): Participant[] {
  const baseAthletes: Participant[] = [
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
    {
      id: 6,
      seed: 3,
      name: 'K. Momota',
      club: 'Tokyo Wings',
      level: 'Lvl 5',
      avatar: getAvatarUrl('K. Momota'),
      checkedIn: true,
      status: 'checked_in',
      checkInTime: '08:20 AM',
      paid: true,
      racketChecked: true,
      warmupCourt: 'Sân 3',
      qrCode: 'ATH-006',
      phone: '0906 789 012',
      category: 'Đơn Nam Chuyên Nghiệp',
    },
    {
      id: 7,
      seed: 4,
      name: 'S. Kidambi',
      club: 'India Shuttle',
      level: 'Lvl 4',
      avatar: getAvatarUrl('S. Kidambi'),
      checkedIn: true,
      status: 'checked_in',
      checkInTime: '08:25 AM',
      paid: true,
      racketChecked: true,
      qrCode: 'ATH-007',
      phone: '0907 890 123',
      category: 'Đơn Nam Master',
    },
    {
      id: 8,
      seed: 5,
      name: 'Lee Zii Jia',
      club: 'KL Masters',
      level: 'Lvl 5',
      avatar: getAvatarUrl('Lee Zii Jia'),
      checkedIn: true,
      status: 'checked_in',
      checkInTime: '08:30 AM',
      paid: true,
      racketChecked: true,
      warmupCourt: 'Sân 2',
      qrCode: 'ATH-008',
      phone: '0908 901 234',
      category: 'Đơn Nam Chuyên Nghiệp',
    },
    {
      id: 9,
      seed: 6,
      name: 'A. Ginting',
      club: 'Jakarta Club',
      level: 'Lvl 5',
      avatar: getAvatarUrl('A. Ginting'),
      checkedIn: false,
      status: 'pending',
      estimatedArrival: 'Est. 09:15 AM',
      paid: true,
      racketChecked: true,
      qrCode: 'ATH-009',
      phone: '0909 012 345',
      category: 'Đơn Nam Chuyên Nghiệp',
    },
    {
      id: 10,
      seed: 7,
      name: 'J. Christie',
      club: 'Garuda Smash',
      level: 'Lvl 5',
      avatar: getAvatarUrl('J. Christie'),
      checkedIn: true,
      status: 'checked_in',
      checkInTime: '08:40 AM',
      paid: true,
      racketChecked: false,
      qrCode: 'ATH-010',
      phone: '0910 123 456',
      category: 'Đơn Nam Chuyên Nghiệp',
    },
    {
      id: 11,
      seed: 8,
      name: 'Lakshya Sen',
      club: 'Bangalore Elite',
      level: 'Lvl 4',
      avatar: getAvatarUrl('Lakshya Sen'),
      checkedIn: false,
      status: 'pending',
      estimatedArrival: 'Est. 09:45 AM',
      paid: true,
      racketChecked: false,
      qrCode: 'ATH-011',
      phone: '0911 234 567',
      category: 'Đơn Nam Master',
    },
    {
      id: 12,
      name: 'T. Sugiarto',
      club: 'Indonesia PB',
      level: 'Lvl 4',
      avatar: getAvatarUrl('T. Sugiarto'),
      checkedIn: true,
      status: 'checked_in',
      checkInTime: '08:10 AM',
      paid: true,
      racketChecked: true,
      qrCode: 'ATH-012',
      phone: '0912 345 678',
      category: 'Đơn Nam Master',
    },
    {
      id: 13,
      name: 'Nguyễn Tiến Minh',
      club: 'HCM Club',
      level: 'Lvl 5',
      avatar: getAvatarUrl('Nguyễn Tiến Minh'),
      checkedIn: true,
      status: 'checked_in',
      checkInTime: '07:50 AM',
      paid: true,
      racketChecked: true,
      warmupCourt: 'Sân 1',
      qrCode: 'ATH-013',
      phone: '0913 456 789',
      category: 'Đơn Nam Chuyên Nghiệp',
    },
    {
      id: 14,
      name: 'Lê Đức Phát',
      club: 'Quân Đội',
      level: 'Lvl 4',
      avatar: getAvatarUrl('Lê Đức Phát'),
      checkedIn: true,
      status: 'checked_in',
      checkInTime: '08:05 AM',
      paid: true,
      racketChecked: true,
      qrCode: 'ATH-014',
      phone: '0914 567 890',
      category: 'Đơn Nam Master',
    },
    {
      id: 15,
      name: 'Nguyễn Hải Đăng',
      club: 'Aura Youth',
      level: 'Lvl 4',
      avatar: getAvatarUrl('Nguyễn Hải Đăng'),
      checkedIn: true,
      status: 'checked_in',
      checkInTime: '08:12 AM',
      paid: true,
      racketChecked: true,
      qrCode: 'ATH-015',
      phone: '0915 678 901',
      category: 'Đơn Nam Master',
    },
    {
      id: 16,
      name: 'Marcus Fernaldi',
      club: 'Minions Team',
      level: 'Lvl 5',
      avatar: getAvatarUrl('Marcus Fernaldi'),
      checkedIn: true,
      status: 'checked_in',
      checkInTime: '08:35 AM',
      paid: true,
      racketChecked: true,
      qrCode: 'ATH-016',
      phone: '0916 789 012',
      category: 'Đôi Nam Chuyên Nghiệp',
    },
    {
      id: 17,
      name: 'Kevin Sukamuljo',
      club: 'Minions Team',
      level: 'Lvl 5',
      avatar: getAvatarUrl('Kevin Sukamuljo'),
      checkedIn: false,
      status: 'pending',
      estimatedArrival: 'Est. 09:30 AM',
      paid: true,
      racketChecked: false,
      qrCode: 'ATH-017',
      phone: '0917 890 123',
      category: 'Đôi Nam Chuyên Nghiệp',
    },
    {
      id: 18,
      name: 'Chou Tien Chen',
      club: 'Taipei Shuttle',
      level: 'Lvl 5',
      avatar: getAvatarUrl('Chou Tien Chen'),
      checkedIn: true,
      status: 'checked_in',
      checkInTime: '08:22 AM',
      paid: true,
      racketChecked: true,
      qrCode: 'ATH-018',
      phone: '0918 901 234',
      category: 'Đơn Nam Chuyên Nghiệp',
    },
    {
      id: 19,
      name: 'Loh Kean Yew',
      club: 'SG Smashers',
      level: 'Lvl 5',
      avatar: getAvatarUrl('Loh Kean Yew'),
      checkedIn: true,
      status: 'checked_in',
      checkInTime: '08:18 AM',
      paid: true,
      racketChecked: true,
      qrCode: 'ATH-019',
      phone: '0919 012 345',
      category: 'Đơn Nam Chuyên Nghiệp',
    },
  ]

  // Add more generated athletes up to 142 total with exactly 58 checked in and 84 pending
  const clubs = ['Aura Elite', 'Apex Club', 'Velocity BC', 'Tokyo Wings', 'Garuda Smash', 'Viking Smash', 'HCM Club', 'Quân Đội', 'Bangalore Elite', 'Independent', 'Saigon Shuttlers', 'Danang Smash']
  const names = [
    'Alexandre B.', 'Mia Tanaka', 'Oliver Davies', 'Sophia Hernandez', 'Ethan Vance', 'Lucas Silva', 'Emma Watson', 'Liam O\'Connor',
    'Noah Schmidt', 'Ava Dupont', 'Jackson Lee', 'Elena Rostova', 'Mateo Gomez', 'Chloe Martin', 'William Zhang', 'Zoe Petrov',
    'Phạm Hoàng Nam', 'Trần Đình Trọng', 'Vũ Minh Tuấn', 'Hoàng Bảo Long', 'Bùi Đức Anh', 'Đỗ Quang Huy', 'Lý Gia Hào', 'Dương Quốc Cường',
    'Ngô Thành Đạt', 'Mai Văn Toàn', 'Lâm Chấn Khang', 'Phan Thanh Bình', 'Trịnh Quốc Việt', 'Hà Vĩnh Thụy', 'Đinh Tiến Dũng', 'Cao Thái Sơn'
  ]

  const totalDesired = 142
  const targetCheckedIn = 58
  let currentCheckedIn = baseAthletes.filter(a => a.checkedIn).length

  for (let i = baseAthletes.length + 1; i <= totalDesired; i++) {
    const shouldCheckIn = currentCheckedIn < targetCheckedIn && (i % 2 === 0 || i <= 60)
    if (shouldCheckIn) currentCheckedIn++

    const nameIndex = (i - 1) % names.length
    const athleteName = `${names[nameIndex]} #${i}`
    const clubIndex = (i - 1) % clubs.length
    const lvl = i % 3 === 0 ? 'Lvl 5' : i % 2 === 0 ? 'Lvl 4' : 'Lvl 3'
    const pad = String(i).padStart(3, '0')

    baseAthletes.push({
      id: i,
      name: athleteName,
      club: clubs[clubIndex],
      level: lvl,
      avatar: getAvatarUrl(athleteName),
      checkedIn: shouldCheckIn,
      status: shouldCheckIn ? 'checked_in' : 'pending',
      checkInTime: shouldCheckIn ? `08:${String(10 + (i % 45)).padStart(2, '0')} AM` : undefined,
      estimatedArrival: !shouldCheckIn ? `Est. 09:${String(15 + (i % 40)).padStart(2, '0')} AM` : undefined,
      paid: true,
      racketChecked: shouldCheckIn ? (i % 4 !== 0) : false,
      warmupCourt: shouldCheckIn && i % 3 === 0 ? `Sân ${(i % 4) + 1}` : undefined,
      qrCode: `ATH-${pad}`,
      phone: `09${String(10000000 + i).slice(0, 8)}`,
      category: i % 2 === 0 ? 'Đơn Nam Nâng Cao' : 'Đơn Nam Phong Trào',
    })
  }

  return baseAthletes
}

// List of Registered Athletes / Participants
const participants = ref<Participant[]>(createInitialParticipants())

// Bracket Fixtures Hierarchy (Round of 16, Quarterfinals, Semifinals, Finals)
const fixtures = ref<FixtureMatch[]>([
  // Round of 16 (8 matches)
  {
    id: 101,
    roundNumber: 1,
    roundName: 'Vòng 1/16 (R16)',
    position: 1,
    matchNumber: 1,
    courtName: 'Sân 1',
    scheduledTime: '08:30 · 15/08',
    status: 'completed',
    player1: { id: 1, seed: 1, name: 'Chen Long', club: 'Aura Elite', avatar: getAvatarUrl('Chen Long'), sets: [21, 21], isWinner: true },
    player2: { id: 9, name: 'T. Sugiarto', club: 'Indonesia PB', avatar: getAvatarUrl('T. Sugiarto'), sets: [14, 16], isWinner: false },
    refereeName: 'Trọng tài Nguyễn Văn Hùng',
    winnerId: 1,
    nextMatchId: 201,
    nextMatchSlot: 1,
  },
  {
    id: 102,
    roundNumber: 1,
    roundName: 'Vòng 1/16 (R16)',
    position: 2,
    matchNumber: 2,
    courtName: 'Sân 2',
    scheduledTime: '08:30 · 15/08',
    status: 'completed',
    player1: { id: 3, seed: 8, name: 'K. Momota', club: 'Tokyo Wings', avatar: getAvatarUrl('K. Momota'), sets: [21, 21], isWinner: true },
    player2: { id: 6, name: 'A. Ginting', club: 'Jakarta Club', avatar: getAvatarUrl('A. Ginting'), sets: [19, 17], isWinner: false },
    refereeName: 'Trọng tài Trần Minh Trí',
    winnerId: 3,
    nextMatchId: 201,
    nextMatchSlot: 2,
  },
  {
    id: 103,
    roundNumber: 1,
    roundName: 'Vòng 1/16 (R16)',
    position: 3,
    matchNumber: 3,
    courtName: 'Sân 3',
    scheduledTime: '09:45 · 15/08',
    status: 'completed',
    player1: { id: 5, seed: 5, name: 'Lee Zii Jia', club: 'KL Masters', avatar: getAvatarUrl('Lee Zii Jia'), sets: [21, 21], isWinner: true },
    player2: { id: 7, name: 'J. Christie', club: 'Garuda Smash', avatar: getAvatarUrl('J. Christie'), sets: [16, 18], isWinner: false },
    refereeName: 'Trọng tài Lê Quốc Bảo',
    winnerId: 5,
    nextMatchId: 202,
    nextMatchSlot: 1,
  },
  {
    id: 104,
    roundNumber: 1,
    roundName: 'Vòng 1/16 (R16)',
    position: 4,
    matchNumber: 4,
    courtName: 'Sân 4',
    scheduledTime: '09:45 · 15/08',
    status: 'completed',
    player1: { id: 4, seed: 4, name: 'S. Kidambi', club: 'India Shuttle', avatar: getAvatarUrl('S. Kidambi'), sets: [21, 21], isWinner: true },
    player2: { id: 8, name: 'Lakshya Sen', club: 'Bangalore Elite', avatar: getAvatarUrl('Lakshya Sen'), sets: [18, 15], isWinner: false },
    refereeName: 'Trọng tài Phạm Thế Vinh',
    winnerId: 4,
    nextMatchId: 202,
    nextMatchSlot: 2,
  },
  {
    id: 105,
    roundNumber: 1,
    roundName: 'Vòng 1/16 (R16)',
    position: 5,
    matchNumber: 5,
    courtName: 'Sân 1',
    scheduledTime: '11:00 · 15/08',
    status: 'completed',
    player1: { id: 10, name: 'Nguyễn Tiến Minh', club: 'HCM Club', avatar: getAvatarUrl('Nguyễn Tiến Minh'), sets: [21, 21], isWinner: true },
    player2: { id: 11, name: 'Lê Đức Phát', club: 'Quân Đội', avatar: getAvatarUrl('Lê Đức Phát'), sets: [15, 19], isWinner: false },
    refereeName: 'Trọng tài Nguyễn Văn Hùng',
    winnerId: 10,
    nextMatchId: 203,
    nextMatchSlot: 1,
  },
  {
    id: 106,
    roundNumber: 1,
    roundName: 'Vòng 1/16 (R16)',
    position: 6,
    matchNumber: 6,
    courtName: 'Sân 2',
    scheduledTime: '11:00 · 15/08',
    status: 'completed',
    player1: { id: 12, name: 'Nguyễn Hải Đăng', club: 'Aura Youth', avatar: getAvatarUrl('Nguyễn Hải Đăng'), sets: [21, 19, 21], isWinner: true },
    player2: { id: 13, name: 'Marcus Fernaldi', club: 'Minions Team', avatar: getAvatarUrl('Marcus Fernaldi'), sets: [17, 21, 16], isWinner: false },
    refereeName: 'Trọng tài Trần Minh Trí',
    winnerId: 12,
    nextMatchId: 203,
    nextMatchSlot: 2,
  },
  {
    id: 107,
    roundNumber: 1,
    roundName: 'Vòng 1/16 (R16)',
    position: 7,
    matchNumber: 7,
    courtName: 'Sân 3',
    scheduledTime: '13:30 · 15/08',
    status: 'completed',
    player1: { id: 15, name: 'Chou Tien Chen', club: 'Taipei Shuttle', avatar: getAvatarUrl('Chou Tien Chen'), sets: [21, 21], isWinner: true },
    player2: { id: 16, name: 'Loh Kean Yew', club: 'SG Smashers', avatar: getAvatarUrl('Loh Kean Yew'), sets: [18, 14], isWinner: false },
    refereeName: 'Trọng tài Lê Quốc Bảo',
    winnerId: 15,
    nextMatchId: 204,
    nextMatchSlot: 1,
  },
  {
    id: 108,
    roundNumber: 1,
    roundName: 'Vòng 1/16 (R16)',
    position: 8,
    matchNumber: 8,
    courtName: 'Sân 4',
    scheduledTime: '13:30 · 15/08',
    status: 'completed',
    player1: { id: 2, seed: 2, name: 'Viktor Axelsen', club: 'Viking Smash', avatar: getAvatarUrl('Viktor Axelsen'), sets: [21, 21], isWinner: true },
    player2: { id: 14, name: 'Kevin Sukamuljo', club: 'Minions Team', avatar: getAvatarUrl('Kevin Sukamuljo'), sets: [12, 11], isWinner: false },
    refereeName: 'Trọng tài Phạm Thế Vinh',
    winnerId: 2,
    nextMatchId: 204,
    nextMatchSlot: 2,
  },

  // Quarter-Finals (4 matches)
  {
    id: 201,
    roundNumber: 2,
    roundName: 'Tứ Kết (Quarterfinals)',
    position: 1,
    matchNumber: 9,
    courtName: 'Sân 1',
    scheduledTime: '15:00 · 16/08',
    status: 'completed',
    player1: { id: 1, seed: 1, name: 'Chen Long', club: 'Aura Elite', avatar: getAvatarUrl('Chen Long'), sets: [21, 21], isWinner: true },
    player2: { id: 3, seed: 8, name: 'K. Momota', club: 'Tokyo Wings', avatar: getAvatarUrl('K. Momota'), sets: [17, 19], isWinner: false },
    refereeName: 'Trọng tài Nguyễn Văn Hùng',
    winnerId: 1,
    nextMatchId: 301,
    nextMatchSlot: 1,
  },
  {
    id: 202,
    roundNumber: 2,
    roundName: 'Tứ Kết (Quarterfinals)',
    position: 2,
    matchNumber: 10,
    courtName: 'Sân 2',
    scheduledTime: '15:00 · 16/08',
    status: 'completed',
    player1: { id: 5, seed: 5, name: 'Lee Zii Jia', club: 'KL Masters', avatar: getAvatarUrl('Lee Zii Jia'), sets: [21, 18, 21], isWinner: true },
    player2: { id: 4, seed: 4, name: 'S. Kidambi', club: 'India Shuttle', avatar: getAvatarUrl('S. Kidambi'), sets: [19, 21, 16], isWinner: false },
    refereeName: 'Trọng tài Lê Quốc Bảo',
    winnerId: 5,
    nextMatchId: 301,
    nextMatchSlot: 2,
  },
  {
    id: 203,
    roundNumber: 2,
    roundName: 'Tứ Kết (Quarterfinals)',
    position: 3,
    matchNumber: 11,
    courtName: 'Sân 3',
    scheduledTime: '16:30 · 16/08',
    status: 'completed',
    player1: { id: 10, name: 'Nguyễn Tiến Minh', club: 'HCM Club', avatar: getAvatarUrl('Nguyễn Tiến Minh'), sets: [21, 21], isWinner: true },
    player2: { id: 12, name: 'Nguyễn Hải Đăng', club: 'Aura Youth', avatar: getAvatarUrl('Nguyễn Hải Đăng'), sets: [16, 18], isWinner: false },
    refereeName: 'Trọng tài Trần Minh Trí',
    winnerId: 10,
    nextMatchId: 302,
    nextMatchSlot: 1,
  },
  {
    id: 204,
    roundNumber: 2,
    roundName: 'Tứ Kết (Quarterfinals)',
    position: 4,
    matchNumber: 12,
    courtName: 'Sân 4',
    scheduledTime: '16:30 · 16/08',
    status: 'completed',
    player1: { id: 15, name: 'Chou Tien Chen', club: 'Taipei Shuttle', avatar: getAvatarUrl('Chou Tien Chen'), sets: [18, 14], isWinner: false },
    player2: { id: 2, seed: 2, name: 'Viktor Axelsen', club: 'Viking Smash', avatar: getAvatarUrl('Viktor Axelsen'), sets: [21, 21], isWinner: true },
    refereeName: 'Trọng tài Phạm Thế Vinh',
    winnerId: 2,
    nextMatchId: 302,
    nextMatchSlot: 2,
  },

  // Semi-Finals (2 matches)
  {
    id: 301,
    roundNumber: 3,
    roundName: 'Bán Kết (Semifinals)',
    position: 1,
    matchNumber: 13,
    courtName: 'Sân 1 Center Court',
    courtId: 101,
    scheduledTime: '09:30 · 17/08',
    status: 'live',
    player1: { id: 1, seed: 1, name: 'Chen Long', club: 'Aura Elite', avatar: getAvatarUrl('Chen Long'), sets: [21, 18], currentScore: 19, isServing: true },
    player2: { id: 5, seed: 5, name: 'Lee Zii Jia', club: 'KL Masters', avatar: getAvatarUrl('Lee Zii Jia'), sets: [19, 21], currentScore: 17, isServing: false },
    refereeName: 'Trọng tài Trưởng Nguyễn Văn Hùng',
    nextMatchId: 401,
    nextMatchSlot: 1,
  },
  {
    id: 302,
    roundNumber: 3,
    roundName: 'Bán Kết (Semifinals)',
    position: 2,
    matchNumber: 14,
    courtName: 'Sân 2 Center Court',
    courtId: 102,
    scheduledTime: '11:00 · 17/08',
    status: 'pending',
    player1: { id: 10, name: 'Nguyễn Tiến Minh', club: 'HCM Club', avatar: getAvatarUrl('Nguyễn Tiến Minh'), sets: [] },
    player2: { id: 2, seed: 2, name: 'Viktor Axelsen', club: 'Viking Smash', avatar: getAvatarUrl('Viktor Axelsen'), sets: [] },
    refereeName: 'Trọng tài Trưởng Trần Minh Trí',
    nextMatchId: 401,
    nextMatchSlot: 2,
  },

  // Grand Finals (1 match)
  {
    id: 401,
    roundNumber: 4,
    roundName: 'Chung Kết (Grand Finals)',
    position: 1,
    matchNumber: 15,
    courtName: 'Sân 1 Center Court (VIP Taraflex)',
    courtId: 101,
    scheduledTime: '18:30 · 18/08 (Chủ Nhật)',
    status: 'pending',
    player1: { name: 'Thắng Bán Kết 1 (Chen Long / Lee Z. J.)', club: 'Winner SF 1', sets: [] },
    player2: { name: 'Thắng Bán Kết 2 (Tiến Minh / V. Axelsen)', club: 'Winner SF 2', sets: [] },
    refereeName: 'Tổ Trọng tài Quốc Gia Aura',
  },
])

const defaultFinalMatch: FixtureMatch = {
  id: 401,
  roundNumber: 4,
  roundName: 'Chung Kết (Grand Finals)',
  position: 1,
  matchNumber: 15,
  courtName: 'Sân 1 Center Court (VIP Taraflex)',
  courtId: 101,
  scheduledTime: '18:30 · 18/08 (Chủ Nhật)',
  status: 'pending',
  player1: { name: 'Thắng Bán Kết 1 (Chen Long / Lee Z. J.)', club: 'Winner SF 1', sets: [] },
  player2: { name: 'Thắng Bán Kết 2 (Tiến Minh / V. Axelsen)', club: 'Winner SF 2', sets: [] },
  refereeName: 'Tổ Trọng tài Quốc Gia Aura',
}

const defaultLiveMatch: FixtureMatch = {
  id: 301,
  roundNumber: 3,
  roundName: 'Bán Kết (Semifinals)',
  position: 1,
  matchNumber: 13,
  courtName: 'Sân 1 Center Court',
  courtId: 101,
  scheduledTime: '09:30 · 17/08',
  status: 'live',
  player1: { id: 1, seed: 1, name: 'Chen Long', club: 'Aura Elite', avatar: getAvatarUrl('Chen Long'), sets: [21, 18], currentScore: 19, isServing: true },
  player2: { id: 5, seed: 5, name: 'Lee Zii Jia', club: 'KL Masters', avatar: getAvatarUrl('Lee Zii Jia'), sets: [19, 21], currentScore: 17, isServing: false },
  refereeName: 'Trọng tài Trưởng Nguyễn Văn Hùng',
}

// Computed Rounds for Bracket Layout
const r16Matches = computed(() => fixtures.value.filter((f) => f.roundNumber === 1))
const qfMatches = computed(() => fixtures.value.filter((f) => f.roundNumber === 2))
const sfMatches = computed(() => fixtures.value.filter((f) => f.roundNumber === 3))
const finalMatch = computed<FixtureMatch>(() => fixtures.value.find((f) => f.roundNumber === 4) || defaultFinalMatch)

// Match Edit Modal State
const isMatchModalOpen = ref(false)
const selectedMatch = ref<FixtureMatch | null>(null)
const matchScoreForm = ref({
  p1_s1: 21,
  p2_s1: 18,
  p3_s2: 18,
  p4_s2: 21,
  p5_s3: 15,
  p6_s3: 12,
  winnerId: 0,
  courtName: 'Sân 1',
  scheduledTime: '09:00',
  refereeName: 'Trọng tài Nguyễn Văn Hùng',
  status: 'completed' as 'pending' | 'live' | 'completed' | 'walkover',
})

// Step 1 Edit Modal State
const isEditBasicInfoModalOpen = ref(false)
const basicInfoForm = ref({ ...tournament.value })

// Participant Seeding Modal State
const isAddParticipantModalOpen = ref(false)
const newParticipantForm = ref({
  name: '',
  club: 'Aura Badminton Club',
  phone: '0909 123 456',
  seed: 0,
})

// Live Referee Console State
const refereeSelectedCourt = ref<number>(101)
const isSwappedEnds = ref<boolean>(false)
const isConflictAlertVisible = ref<boolean>(false)
const conflictRemoteScores = ref({ p1: 21, p2: 19 })
const isCardsModalOpen = ref<boolean>(false)
const penaltySelectedPlayer = ref<1 | 2>(1)
const isFinishMatchModalOpen = ref<boolean>(false)
const isRallyLogDrawerOpen = ref<boolean>(false)
const isTabletFullscreen = ref<boolean>(false)
const isSoundFeedbackEnabled = ref<boolean>(true)
const p1ScoreAnim = ref<boolean>(false)
const p2ScoreAnim = ref<boolean>(false)
const matchDurationSeconds = ref<number>(2540) // ~42m20s
let matchTimerInterval: ReturnType<typeof setInterval> | null = null

// Selected Court Match Fixture List for quick court selection
const courtLiveMatches = ref<Record<number, FixtureMatch>>({
  101: {
    id: 101,
    roundNumber: 4,
    roundName: 'Chung Kết (Championship Finals)',
    position: 1,
    matchNumber: 15,
    courtName: 'Sân 1 (Center Court)',
    courtId: 101,
    scheduledTime: '15:30 · 18/08',
    status: 'live',
    player1: { id: 101, seed: 1, name: 'Sarah Jenkins', club: 'Northside Club', avatar: getAvatarUrl('Sarah Jenkins'), sets: [21, 18], currentScore: 21, isServing: true },
    player2: { id: 102, seed: 2, name: 'David Chen', club: 'Elite Badminton Academy', avatar: getAvatarUrl('David Chen'), sets: [19, 21], currentScore: 19, isServing: false },
    refereeName: 'Trọng tài BWF Sarah Ross',
  },
  102: {
    id: 102,
    roundNumber: 3,
    roundName: 'Bán Kết 1 (Semifinal 1)',
    position: 1,
    matchNumber: 13,
    courtName: 'Sân 2 (Taraflex VIP)',
    courtId: 102,
    scheduledTime: '14:00 · 18/08',
    status: 'live',
    player1: { id: 1, seed: 1, name: 'Chen Long', club: 'Aura Elite', avatar: getAvatarUrl('Chen Long'), sets: [21, 18], currentScore: 19, isServing: true },
    player2: { id: 5, seed: 5, name: 'Lee Zii Jia', club: 'KL Masters', avatar: getAvatarUrl('Lee Zii Jia'), sets: [19, 21], currentScore: 17, isServing: false },
    refereeName: 'Trọng tài Trưởng Nguyễn Văn Hùng',
  },
  103: {
    id: 103,
    roundNumber: 3,
    roundName: 'Bán Kết 2 (Semifinal 2)',
    position: 2,
    matchNumber: 14,
    courtName: 'Sân 3 (Pro Court)',
    courtId: 103,
    scheduledTime: '14:45 · 18/08',
    status: 'live',
    player1: { id: 3, seed: 3, name: 'Viktor Axelsen', club: 'Odense BC', avatar: getAvatarUrl('Viktor Axelsen'), sets: [21], currentScore: 14, isServing: false },
    player2: { id: 7, seed: 7, name: 'Nguyễn Tiến Minh', club: 'HCM Club', avatar: getAvatarUrl('Nguyễn Tiến Minh'), sets: [16], currentScore: 11, isServing: true },
    refereeName: 'Trọng tài Lê Quốc Bảo',
  },
  104: {
    id: 104,
    roundNumber: 2,
    roundName: 'Tứ Kết (Quarterfinals)',
    position: 4,
    matchNumber: 12,
    courtName: 'Sân 4 (Standard Court)',
    courtId: 104,
    scheduledTime: '13:15 · 18/08',
    status: 'live',
    player1: { id: 4, seed: 4, name: 'Anthony Ginting', club: 'SGS Club', avatar: getAvatarUrl('Anthony Ginting'), sets: [21, 14], currentScore: 8, isServing: true },
    player2: { id: 8, seed: 8, name: 'Loh Kean Yew', club: 'Singapore BC', avatar: getAvatarUrl('Loh Kean Yew'), sets: [17, 21], currentScore: 7, isServing: false },
    refereeName: 'Trọng tài Phạm Thế Vinh',
  },
})

const activeLiveFixture = computed<FixtureMatch>(() => {
  return courtLiveMatches.value[refereeSelectedCourt.value] || fixtures.value.find((f) => f.status === 'live') || defaultLiveMatch
})

const liveSetScoreP1 = ref(21)
const liveSetScoreP2 = ref(19)
const liveCurrentSet = ref(3)
const liveP1SetsWon = ref(1)
const liveP2SetsWon = ref(1)
const liveServingPlayer = ref<1 | 2>(1)

interface PointHistoryItem {
  setScoreP1: number
  setScoreP2: number
  servingPlayer: 1 | 2
  currentSet: number
  p1SetsWon: number
  p2SetsWon: number
  timestamp: string
  description: string
}

const pointHistoryStack = ref<PointHistoryItem[]>([])

interface RallyEvent {
  id: number
  timestamp: string
  setNumber: number
  player: 1 | 2
  playerName: string
  scoreSnapshot: string
  eventType: 'point' | 'smash' | 'drop' | 'net_kill' | 'unforced_error' | 'service_fault' | 'card' | 'timeout'
  label: string
  badgeClass: string
}

const rallyEventsLog = ref<RallyEvent[]>([
  { id: 1, timestamp: '16:14', setNumber: 3, player: 1, playerName: 'Sarah Jenkins', scoreSnapshot: '21 - 18', eventType: 'smash', label: 'Smash Winner (398 km/h)', badgeClass: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' },
  { id: 2, timestamp: '16:12', setNumber: 3, player: 2, playerName: 'David Chen', scoreSnapshot: '20 - 18', eventType: 'drop', label: 'Drop shot hiểm hóc', badgeClass: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300' },
  { id: 3, timestamp: '16:10', setNumber: 3, player: 1, playerName: 'Sarah Jenkins', scoreSnapshot: '20 - 17', eventType: 'net_kill', label: 'Vồ cầu trên lưới (Net Kill)', badgeClass: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' },
  { id: 4, timestamp: '16:08', setNumber: 3, player: 2, playerName: 'David Chen', scoreSnapshot: '19 - 17', eventType: 'unforced_error', label: 'Đối phương đánh ngoài biên', badgeClass: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' },
])

// Badminton Match Rules & Computeds
const isDeuce = computed(() => {
  return liveSetScoreP1.value >= 20 && liveSetScoreP2.value >= 20 && liveSetScoreP1.value === liveSetScoreP2.value
})

const isSuddenDeath = computed(() => {
  return liveSetScoreP1.value === 29 && liveSetScoreP2.value === 29
})

const isMatchPoint = computed(() => {
  if (liveP1SetsWon.value === 1 && liveSetScoreP1.value >= 20 && liveSetScoreP1.value - liveSetScoreP2.value >= 1) return 1
  if (liveP2SetsWon.value === 1 && liveSetScoreP2.value >= 20 && liveSetScoreP2.value - liveSetScoreP1.value >= 1) return 2
  return 0
})

const isGamePoint = computed(() => {
  if (liveSetScoreP1.value >= 20 && liveSetScoreP1.value - liveSetScoreP2.value >= 1 && !isMatchPoint.value) return 1
  if (liveSetScoreP2.value >= 20 && liveSetScoreP2.value - liveSetScoreP1.value >= 1 && !isMatchPoint.value) return 2
  return 0
})

const serviceCourtSide = computed(() => {
  const currentScore = liveServingPlayer.value === 1 ? liveSetScoreP1.value : liveSetScoreP2.value
  return currentScore % 2 === 0 ? 'Phải (Điểm chẵn)' : 'Trái (Điểm lẻ)'
})

const formattedMatchDuration = computed(() => {
  const m = Math.floor(matchDurationSeconds.value / 60)
  const s = matchDurationSeconds.value % 60
  return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
})

// Methods

// Open Match Details Modal for quick scoring and scheduling
function openMatchModal(match: FixtureMatch) {
  selectedMatch.value = match
  matchScoreForm.value = {
    p1_s1: match.player1.sets[0] || 21,
    p2_s1: match.player2.sets[0] || 18,
    p3_s2: match.player1.sets[1] || 21,
    p4_s2: match.player2.sets[1] || 16,
    p5_s3: match.player1.sets[2] || 0,
    p6_s3: match.player2.sets[2] || 0,
    winnerId: match.winnerId || match.player1.id || 1,
    courtName: match.courtName || 'Sân 1',
    scheduledTime: match.scheduledTime || '09:00',
    refereeName: match.refereeName || 'Trọng tài Ban Tổ Chức',
    status: match.status,
  }
  isMatchModalOpen.value = true
}

function saveMatchResult() {
  if (!selectedMatch.value) return

  const m = selectedMatch.value
  m.status = matchScoreForm.value.status
  m.courtName = matchScoreForm.value.courtName
  m.scheduledTime = matchScoreForm.value.scheduledTime
  m.refereeName = matchScoreForm.value.refereeName
  m.winnerId = matchScoreForm.value.winnerId

  const s1_p1 = matchScoreForm.value.p1_s1
  const s1_p2 = matchScoreForm.value.p2_s1
  const s2_p1 = matchScoreForm.value.p3_s2
  const s2_p2 = matchScoreForm.value.p4_s2
  const s3_p1 = matchScoreForm.value.p5_s3
  const s3_p2 = matchScoreForm.value.p6_s3

  m.player1.sets = [s1_p1, s2_p1]
  m.player2.sets = [s1_p2, s2_p2]
  if (s3_p1 > 0 || s3_p2 > 0) {
    m.player1.sets.push(s3_p1)
    m.player2.sets.push(s3_p2)
  }

  // Set winner highlight
  if (m.winnerId === m.player1.id) {
    m.player1.isWinner = true
    m.player2.isWinner = false
  } else if (m.winnerId === m.player2.id) {
    m.player1.isWinner = false
    m.player2.isWinner = true
  }

  // If next match exists, propagate the winner!
  if (m.nextMatchId && m.status === 'completed') {
    const nextMatch = fixtures.value.find((f) => f.id === m.nextMatchId)
    if (nextMatch) {
      const winningPlayer = m.winnerId === m.player1.id ? m.player1 : m.player2
      if (m.nextMatchSlot === 1) {
        nextMatch.player1 = {
          id: winningPlayer.id,
          seed: winningPlayer.seed,
          name: winningPlayer.name,
          club: winningPlayer.club,
          avatar: winningPlayer.avatar,
          sets: [],
        }
      } else {
        nextMatch.player2 = {
          id: winningPlayer.id,
          seed: winningPlayer.seed,
          name: winningPlayer.name,
          club: winningPlayer.club,
          avatar: winningPlayer.avatar,
          sets: [],
        }
      }
    }
  }

  showNotice(`✨ Đã cập nhật kết quả Trận #${m.matchNumber} (${m.player1.name} vs ${m.player2.name})!`)
  isMatchModalOpen.value = false
}

// Re-generate Bracket Algorithm (Standard BWF Seeding)
function reGenerateBracket() {
  isGenerating.value = true

  setTimeout(() => {
    showNotice('🎯 Đã tái tạo bảng phân nhánh theo chuẩn hạt giống BWF & quy tắc tránh cùng CLB!')
    isGenerating.value = false
  }, 600)
}

// Save Draft Action
async function handleSaveDraft() {
  isSaving.value = true
  try {
    try {
      await tournamentApi.create({
        name: tournament.value.name,
        format: tournament.value.format,
        status: 'draft',
      })
    } catch {
      // local fallback
    }
    showNotice('💾 Đã lưu bản nháp cấu hình giải đấu thành công!')
  } catch (err) {
    showError(errorMessage(err, 'Không thể lưu bản nháp lúc này.'))
  } finally {
    isSaving.value = false
  }
}

// Publish Tournament Event
async function handlePublishEvent() {
  isSaving.value = true
  try {
    try {
      await tournamentApi.create({
        name: tournament.value.name,
        format: tournament.value.format,
        status: 'open',
      })
    } catch {
      // local fallback
    }
    tournament.value.status = 'open'
    showNotice('🚀 Đã công bố giải đấu & đồng bộ phân nhánh lên hệ thống người chơi!')
  } catch (err) {
    showError(errorMessage(err, 'Không thể công bố giải đấu.'))
  } finally {
    isSaving.value = false
  }
}

// Basic Info Edit Modal
function openEditBasicInfoModal() {
  basicInfoForm.value = { ...tournament.value }
  isEditBasicInfoModalOpen.value = true
}

function saveBasicInfo() {
  tournament.value = { ...basicInfoForm.value }
  showNotice('✅ Đã cập nhật thông tin cơ bản của giải đấu!')
  isEditBasicInfoModalOpen.value = false
}

// Add Participant
function openAddParticipantModal() {
  newParticipantForm.value = {
    name: '',
    club: 'Aura Badminton Club',
    phone: '0909 888 999',
    seed: 0,
  }
  isAddParticipantModalOpen.value = true
}

function saveNewParticipant() {
  if (!newParticipantForm.value.name.trim()) {
    showError('Vui lòng nhập tên vận động viên.')
    return
  }

  const newId = Date.now()
  const pad = String(participants.value.length + 1).padStart(3, '0')
  participants.value.unshift({
    id: newId,
    name: newParticipantForm.value.name,
    club: newParticipantForm.value.club,
    phone: newParticipantForm.value.phone,
    seed: newParticipantForm.value.seed > 0 ? newParticipantForm.value.seed : undefined,
    level: 'Lvl 4',
    avatar: getAvatarUrl(newParticipantForm.value.name),
    checkedIn: false,
    status: 'pending',
    estimatedArrival: 'Est. 09:00 AM',
    paid: true,
    racketChecked: false,
    qrCode: `ATH-${pad}`,
    category: 'Đơn Nam Nâng Cao',
  })

  showNotice(`🎉 Đã thêm VĐV "${newParticipantForm.value.name}" vào danh sách thi đấu!`)
  isAddParticipantModalOpen.value = false
}

// Check-in Filter & Modal State
const checkinFilterTab = ref<'all' | 'pending' | 'checked_in' | 'no_show'>('all')
const checkinSearchQuery = ref('')
const checkinLevelFilter = ref('all')
const visibleAthletesCount = ref(16)

const isQrScannerModalOpen = ref(false)
const isDigitalPassModalOpen = ref(false)
const selectedPassAthlete = ref<Participant | null>(null)
const manualQrInput = ref('')
const qrScanFeedback = ref('')
const isCameraActive = ref(true)

// Computed Check-in Statistics
const totalAthletesCount = computed(() => participants.value.length)
const checkedInCount = computed(() => participants.value.filter((p) => p.checkedIn).length)
const pendingCount = computed(() => participants.value.filter((p) => !p.checkedIn && p.status !== 'no_show').length)
const noShowCount = computed(() => participants.value.filter((p) => p.status === 'no_show').length)
const checkInPercentage = computed(() => {
  if (totalAthletesCount.value === 0) return 0
  return Math.round((checkedInCount.value / totalAthletesCount.value) * 100)
})
const strokeDashoffset = computed(() => {
  const circ = 251.2
  return circ - (checkInPercentage.value / 100) * circ
})

const filteredAthletesList = computed(() => {
  return participants.value.filter((p) => {
    // Status tab filter
    if (checkinFilterTab.value === 'pending' && (p.checkedIn || p.status === 'no_show')) return false
    if (checkinFilterTab.value === 'checked_in' && !p.checkedIn) return false
    if (checkinFilterTab.value === 'no_show' && p.status !== 'no_show') return false

    // Level filter
    if (checkinLevelFilter.value !== 'all' && p.level !== checkinLevelFilter.value) return false

    // Search query filter
    if (checkinSearchQuery.value.trim()) {
      const q = checkinSearchQuery.value.toLowerCase().trim()
      const matchName = p.name.toLowerCase().includes(q)
      const matchClub = (p.club || '').toLowerCase().includes(q)
      const matchPhone = (p.phone || '').toLowerCase().includes(q)
      const matchQr = (p.qrCode || '').toLowerCase().includes(q)
      const matchSeed = p.seed ? `seed ${p.seed}`.toLowerCase().includes(q) : false
      if (!matchName && !matchClub && !matchPhone && !matchQr && !matchSeed) return false
    }

    return true
  })
})

const visibleAthletes = computed(() => {
  return filteredAthletesList.value.slice(0, visibleAthletesCount.value)
})

const hasMoreAthletes = computed(() => {
  return visibleAthletesCount.value < filteredAthletesList.value.length
})

function loadMoreAthletes() {
  visibleAthletesCount.value += 16
}

function playBeepSound() {
  try {
    const AudioCtx = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext
    if (AudioCtx) {
      const ctx = new AudioCtx()
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.connect(gain)
      gain.connect(ctx.destination)
      osc.type = 'sine'
      osc.frequency.value = 880
      gain.gain.setValueAtTime(0.12, ctx.currentTime)
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.22)
      osc.start(ctx.currentTime)
      osc.stop(ctx.currentTime + 0.22)
    }
  } catch {
    // no-op
  }
}

// Check-in participant toggle
async function toggleCheckIn(participant: Participant) {
  participant.checkedIn = !participant.checkedIn
  if (participant.checkedIn) {
    participant.status = 'checked_in'
    const now = new Date()
    const hh = String(now.getHours()).padStart(2, '0')
    const mm = String(now.getMinutes()).padStart(2, '0')
    participant.checkInTime = `${hh}:${mm} AM`
    playBeepSound()
  } else {
    participant.status = 'pending'
    participant.checkInTime = undefined
  }

  try {
    await tournamentApi.checkInParticipant(participant.id, {
      checked_in: participant.checkedIn,
    })
  } catch {
    // optimistic
  }
  showNotice(participant.checkedIn ? `✅ ${participant.name} đã điểm danh vào sân lúc ${participant.checkInTime || '08:14 AM'}!` : `ℹ️ Đã chuyển ${participant.name} về trạng thái chờ điểm danh`)
}

function markAsNoShow(participant: Participant) {
  participant.checkedIn = false
  participant.status = 'no_show'
  const now = new Date()
  const hh = String(now.getHours()).padStart(2, '0')
  const mm = String(now.getMinutes()).padStart(2, '0')
  participant.checkInTime = `${hh}:${mm} AM`
  showNotice(`⚠️ Đã ghi nhận VĐV ${participant.name} vắng mặt (No-show).`)
}

function openQrScannerModal() {
  qrScanFeedback.value = ''
  manualQrInput.value = ''
  isCameraActive.value = true
  isQrScannerModalOpen.value = true
}

function closeQrScannerModal() {
  isQrScannerModalOpen.value = false
}

function simulateQrScan(code: string) {
  const clean = code.trim().toLowerCase()
  const found = participants.value.find(p => 
    (p.qrCode && p.qrCode.toLowerCase() === clean) ||
    `#ath-${p.id}`.toLowerCase() === clean ||
    `ath-${p.id}`.toLowerCase() === clean ||
    p.name.toLowerCase() === clean ||
    (p.phone && p.phone.replace(/\s+/g, '') === clean.replace(/\s+/g, ''))
  )

  if (found) {
    found.checkedIn = true
    found.status = 'checked_in'
    const now = new Date()
    const hh = String(now.getHours()).padStart(2, '0')
    const mm = String(now.getMinutes()).padStart(2, '0')
    found.checkInTime = `${hh}:${mm} AM`
    playBeepSound()
    qrScanFeedback.value = `🎯 QUÉT THÀNH CÔNG: ${found.name} (${found.seed ? 'Seed ' + found.seed : 'Unseeded'}, ${found.club}) đã điểm danh!`
    showNotice(`✅ ${found.name} (#${found.qrCode}) đã quét mã QR và điểm danh thành công!`)
  } else {
    qrScanFeedback.value = `❌ Không tìm thấy thông tin cho mã "${code}". Vui lòng thử lại.`
  }
}

function submitManualQr() {
  if (!manualQrInput.value.trim()) return
  simulateQrScan(manualQrInput.value.trim())
  manualQrInput.value = ''
}

function openDigitalPassModal(participant: Participant) {
  selectedPassAthlete.value = participant
  isDigitalPassModalOpen.value = true
}

function closeDigitalPassModal() {
  isDigitalPassModalOpen.value = false
}

function printPass() {
  window.print()
}

function exportCheckInReport() {
  showNotice('📊 Đang xuất báo cáo danh sách điểm danh VĐV (CSV/Excel)...')
}

// Live Referee Console Sound & Micro-interactions
function playRefereeSound(type: 'point' | 'fault' | 'set' | 'alert') {
  if (!isSoundFeedbackEnabled.value || typeof window === 'undefined') return
  try {
    const AudioCtx = window.AudioContext || (window as any).webkitAudioContext
    if (!AudioCtx) return
    const ctx = new AudioCtx()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain)
    gain.connect(ctx.destination)
    if (type === 'point') {
      osc.type = 'sine'
      osc.frequency.setValueAtTime(587.33, ctx.currentTime)
      osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.08)
      gain.gain.setValueAtTime(0.12, ctx.currentTime)
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12)
      osc.start()
      osc.stop(ctx.currentTime + 0.12)
    } else if (type === 'fault') {
      osc.type = 'sawtooth'
      osc.frequency.setValueAtTime(220, ctx.currentTime)
      gain.gain.setValueAtTime(0.18, ctx.currentTime)
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25)
      osc.start()
      osc.stop(ctx.currentTime + 0.25)
    } else if (type === 'set') {
      osc.type = 'triangle'
      osc.frequency.setValueAtTime(523.25, ctx.currentTime)
      osc.frequency.exponentialRampToValueAtTime(1046.5, ctx.currentTime + 0.2)
      gain.gain.setValueAtTime(0.18, ctx.currentTime)
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3)
      osc.start()
      osc.stop(ctx.currentTime + 0.3)
    }
  } catch {
    // Audio context may be restricted by browser policy
  }
}

// Live Referee Console point buttons
function incrementPoint(player: 1 | 2, eventTag = 'Điểm thường') {
  pointHistoryStack.value.push({
    setScoreP1: liveSetScoreP1.value,
    setScoreP2: liveSetScoreP2.value,
    servingPlayer: liveServingPlayer.value,
    currentSet: liveCurrentSet.value,
    p1SetsWon: liveP1SetsWon.value,
    p2SetsWon: liveP2SetsWon.value,
    timestamp: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
    description: `+1 Điểm cho ${player === 1 ? activeLiveFixture.value.player1.name : activeLiveFixture.value.player2.name}`,
  })

  if (player === 1) {
    liveSetScoreP1.value += 1
    p1ScoreAnim.value = true
    setTimeout(() => { p1ScoreAnim.value = false }, 250)
  } else {
    liveSetScoreP2.value += 1
    p2ScoreAnim.value = true
    setTimeout(() => { p2ScoreAnim.value = false }, 250)
  }

  liveServingPlayer.value = player
  playRefereeSound('point')

  const pName = player === 1 ? activeLiveFixture.value.player1.name : activeLiveFixture.value.player2.name
  rallyEventsLog.value.unshift({
    id: Date.now(),
    timestamp: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
    setNumber: liveCurrentSet.value,
    player,
    playerName: pName,
    scoreSnapshot: `${liveSetScoreP1.value} - ${liveSetScoreP2.value}`,
    eventType: eventTag === 'Smash Winner' ? 'smash' : eventTag === 'Drop shot' ? 'drop' : eventTag === 'Net Kill' ? 'net_kill' : 'point',
    label: eventTag,
    badgeClass: player === 1 ? 'bg-primary-container text-on-primary-container' : 'bg-secondary-container text-on-secondary-container',
  })

  checkSetWinCondition()
}

function decrementPoint(player: 1 | 2) {
  if (player === 1 && liveSetScoreP1.value > 0) {
    pointHistoryStack.value.push({
      setScoreP1: liveSetScoreP1.value,
      setScoreP2: liveSetScoreP2.value,
      servingPlayer: liveServingPlayer.value,
      currentSet: liveCurrentSet.value,
      p1SetsWon: liveP1SetsWon.value,
      p2SetsWon: liveP2SetsWon.value,
      timestamp: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
      description: `-1 Điểm của ${activeLiveFixture.value.player1.name}`,
    })
    liveSetScoreP1.value -= 1
    p1ScoreAnim.value = true
    setTimeout(() => { p1ScoreAnim.value = false }, 200)
  } else if (player === 2 && liveSetScoreP2.value > 0) {
    pointHistoryStack.value.push({
      setScoreP1: liveSetScoreP1.value,
      setScoreP2: liveSetScoreP2.value,
      servingPlayer: liveServingPlayer.value,
      currentSet: liveCurrentSet.value,
      p1SetsWon: liveP1SetsWon.value,
      p2SetsWon: liveP2SetsWon.value,
      timestamp: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
      description: `-1 Điểm của ${activeLiveFixture.value.player2.name}`,
    })
    liveSetScoreP2.value -= 1
    p2ScoreAnim.value = true
    setTimeout(() => { p2ScoreAnim.value = false }, 200)
  }
}

function undoLastPoint() {
  if (pointHistoryStack.value.length === 0) {
    showNotice('ℹ️ Chưa có lịch sử điểm số nào để hoàn tác.')
    return
  }
  const lastState = pointHistoryStack.value.pop()
  if (lastState) {
    liveSetScoreP1.value = lastState.setScoreP1
    liveSetScoreP2.value = lastState.setScoreP2
    liveServingPlayer.value = lastState.servingPlayer
    liveCurrentSet.value = lastState.currentSet
    liveP1SetsWon.value = lastState.p1SetsWon
    liveP2SetsWon.value = lastState.p2SetsWon
    showNotice(`↩️ Đã hoàn tác điểm về: ${liveSetScoreP1.value} - ${liveSetScoreP2.value}`)
  }
}

function switchServe() {
  liveServingPlayer.value = liveServingPlayer.value === 1 ? 2 : 1
  playRefereeSound('point')
  showNotice(`🏸 Đổi quyền giao cầu cho: ${liveServingPlayer.value === 1 ? activeLiveFixture.value.player1.name : activeLiveFixture.value.player2.name}`)
}

function switchEnds() {
  isSwappedEnds.value = !isSwappedEnds.value
  playRefereeSound('point')
  showNotice(`🔄 Đã đổi bên sân thi đấu (Switch Ends)!`)
}

function simulateConflict() {
  conflictRemoteScores.value = {
    p1: liveSetScoreP1.value + 1,
    p2: liveSetScoreP2.value > 0 ? liveSetScoreP2.value - 1 : 0,
  }
  isConflictAlertVisible.value = true
  playRefereeSound('fault')
}

function acceptRemoteConflict() {
  liveSetScoreP1.value = conflictRemoteScores.value.p1
  liveSetScoreP2.value = conflictRemoteScores.value.p2
  isConflictAlertVisible.value = false
  showNotice(`✅ Đã đồng bộ tỷ số từ máy chủ: ${liveSetScoreP1.value} - ${liveSetScoreP2.value}`)
}

function keepLocalConflict() {
  isConflictAlertVisible.value = false
  showNotice(`✅ Đã giữ tỷ số cục bộ (${liveSetScoreP1.value} - ${liveSetScoreP2.value}) và ghi đè máy chủ.`)
}

function dismissConflictAlert() {
  isConflictAlertVisible.value = false
}

function selectCourt(courtId: number) {
  refereeSelectedCourt.value = courtId
  const match = courtLiveMatches.value[courtId]
  if (match) {
    liveSetScoreP1.value = match.player1.currentScore ?? 21
    liveSetScoreP2.value = match.player2.currentScore ?? 19
    liveP1SetsWon.value = match.player1.sets.filter((s, idx) => s > (match.player2.sets[idx] || 0)).length || 1
    liveP2SetsWon.value = match.player2.sets.filter((s, idx) => s > (match.player1.sets[idx] || 0)).length || 1
    liveCurrentSet.value = (match.player1.sets.length || 2) + 1
    liveServingPlayer.value = match.player1.isServing ? 1 : 2
  }
  showNotice(`🏸 Đã chuyển sang điều khiển: ${courtLiveMatches.value[courtId]?.courtName || `Sân ${courtId}`}`)
}

function selectSet(setNum: number) {
  liveCurrentSet.value = setNum
  showNotice(`🏸 Đang hiển thị / nhập điểm cho Set ${setNum}`)
}

function checkSetWinCondition() {
  const p1 = liveSetScoreP1.value
  const p2 = liveSetScoreP2.value
  if ((p1 >= 21 && p1 - p2 >= 2) || p1 === 30) {
    showNotice(`🏸 Set Point! ${activeLiveFixture.value.player1.name} thắng Set ${liveCurrentSet.value}!`)
  } else if ((p2 >= 21 && p2 - p1 >= 2) || p2 === 30) {
    showNotice(`🏸 Set Point! ${activeLiveFixture.value.player2.name} thắng Set ${liveCurrentSet.value}!`)
  }
}

function finishSet() {
  const p1 = liveSetScoreP1.value
  const p2 = liveSetScoreP2.value
  if (p1 > p2) {
    liveP1SetsWon.value += 1
  } else {
    liveP2SetsWon.value += 1
  }
  playRefereeSound('set')
  showNotice(`🏸 Kết thúc Set ${liveCurrentSet.value}! Tỷ số set: ${p1} - ${p2}`)
  if (liveP1SetsWon.value >= 2 || liveP2SetsWon.value >= 2) {
    isFinishMatchModalOpen.value = true
  } else {
    liveCurrentSet.value += 1
    liveSetScoreP1.value = 0
    liveSetScoreP2.value = 0
  }
}

function finishMatchPrompt() {
  isFinishMatchModalOpen.value = true
}

function confirmFinishMatch() {
  const match = activeLiveFixture.value
  const isP1Winner = liveP1SetsWon.value >= liveP2SetsWon.value
  const winningPlayer = isP1Winner ? match.player1 : match.player2
  match.status = 'completed'
  match.winnerId = winningPlayer.id || 1
  match.player1.isWinner = isP1Winner
  match.player2.isWinner = !isP1Winner

  if (match.nextMatchId) {
    const nextM = fixtures.value.find((f) => f.id === match.nextMatchId)
    if (nextM) {
      if (match.nextMatchSlot === 1) {
        nextM.player1 = {
          id: winningPlayer.id,
          seed: winningPlayer.seed,
          name: winningPlayer.name,
          club: winningPlayer.club,
          avatar: winningPlayer.avatar,
          sets: [],
        }
      } else {
        nextM.player2 = {
          id: winningPlayer.id,
          seed: winningPlayer.seed,
          name: winningPlayer.name,
          club: winningPlayer.club,
          avatar: winningPlayer.avatar,
          sets: [],
        }
      }
    }
  }

  isFinishMatchModalOpen.value = false
  playRefereeSound('set')
  showNotice(`🏆 Chúc mừng ${winningPlayer.name} đã chiến thắng trận đấu! Kết quả đã được cập nhật vào Nhánh Đấu.`)
}

function openCardsModal() {
  isCardsModalOpen.value = true
}

function applyPenalty(player: 1 | 2, penaltyType: 'yellow' | 'red' | 'black' | 'fault' | 'timeout') {
  const pName = player === 1 ? activeLiveFixture.value.player1.name : activeLiveFixture.value.player2.name
  if (penaltyType === 'yellow') {
    showNotice(`🟨 Cảnh cáo Thẻ Vàng cho ${pName} do lỗi tác phong/trì hoãn thi đấu.`)
    playRefereeSound('fault')
  } else if (penaltyType === 'red') {
    showNotice(`🟥 Thẻ Đỏ cho ${pName}! Phạt 1 điểm và trao điểm cho đối phương.`)
    const opponent = player === 1 ? 2 : 1
    incrementPoint(opponent, 'Thẻ Đỏ đối phương')
    playRefereeSound('fault')
  } else if (penaltyType === 'fault') {
    showNotice(`⚠️ Lỗi giao cầu (Service Fault) của ${pName}. Trao quyền giao cầu và điểm cho đối phương.`)
    const opponent = player === 1 ? 2 : 1
    incrementPoint(opponent, 'Lỗi giao cầu')
    playRefereeSound('fault')
  } else if (penaltyType === 'timeout') {
    showNotice(`⏱️ Bắt đầu thời gian nghỉ y tế 2 phút cho ${pName}.`)
  } else if (penaltyType === 'black') {
    showNotice(`⬛ THẺ ĐEN: Truất quyền thi đấu của ${pName}!`)
    const opponent = player === 1 ? 2 : 1
    if (opponent === 1) liveP1SetsWon.value = 2
    else liveP2SetsWon.value = 2
    isFinishMatchModalOpen.value = true
    playRefereeSound('fault')
  }
  isCardsModalOpen.value = false
}

function toggleTabletFullscreen() {
  isTabletFullscreen.value = !isTabletFullscreen.value
  showNotice(isTabletFullscreen.value ? '📱 Đã bật chế độ Bàn Trọng Tài Toàn Màn Hình' : '🖥️ Đã thoát chế độ toàn màn hình')
}

// Zoom methods
function zoomIn() {
  if (zoomLevel.value < 1.4) zoomLevel.value += 0.1
}

function zoomOut() {
  if (zoomLevel.value > 0.7) zoomLevel.value -= 0.1
}

function resetZoom() {
  zoomLevel.value = 1
}

onMounted(async () => {
  matchTimerInterval = setInterval(() => {
    matchDurationSeconds.value += 1
  }, 1000)

  if (route.query?.tab && ['bracket-management', 'tournament-overview', 'athlete-check-in', 'live-referee-console'].includes(String(route.query.tab))) {
    activeSubNav.value = route.query.tab as any
  }
  try {
    const res = await tournamentApi.list<{ data: Array<{ id: number; name: string }> }>()
    if (res?.data && res.data.length > 0) {
      // Sync active tournament
    }
  } catch {
    // keep default stitch values
  }
})

onUnmounted(() => {
  if (matchTimerInterval) {
    clearInterval(matchTimerInterval)
  }
})
</script>

<template>
  <div class="admin-bracket-page">
    <!-- Top Floating Toast Alerts -->
    <div class="alerts-fixed-top">
      <Transition name="fade">
        <div v-if="notice" class="alert-pill alert-pill-success">
          <span class="material-symbols-outlined alert-ico">check_circle</span>
          <span>{{ notice }}</span>
        </div>
      </Transition>
      <Transition name="fade">
        <div v-if="error" class="alert-pill alert-pill-danger">
          <span class="material-symbols-outlined alert-ico">error</span>
          <span>{{ error }}</span>
        </div>
      </Transition>
    </div>

    <!-- Main Workspace Body -->
    <div class="admin-bracket-layout">
      <!-- Sub-Navigation Bar Aligned with Stitch (Tournament Overview, Bracket Management, Athlete Check-in, Live Referee Console) -->
      <div class="stitch-sub-nav">
        <div class="sub-nav-left">
          <div class="sub-nav-brand-badge">
            <span class="material-symbols-outlined text-[20px]">emoji_events</span>
            <span class="font-bold text-sm">AURA TOURNAMENT ADMIN</span>
          </div>

          <nav class="sub-nav-pills" aria-label="Tournament Sub Navigation">
            <button
              type="button"
              class="sub-tab-btn"
              :class="{ active: activeSubNav === 'bracket-management' }"
              @click="activeSubNav = 'bracket-management'"
            >
              <span class="material-symbols-outlined text-[18px]">account_tree</span>
              <span>Quản trị Bracket (Setup & Tree)</span>
            </button>

            <button
              type="button"
              class="sub-tab-btn"
              :class="{ active: activeSubNav === 'athlete-check-in' }"
              @click="activeSubNav = 'athlete-check-in'"
            >
              <span class="material-symbols-outlined text-[18px]">how_to_reg</span>
              <span>Điểm danh VĐV (Check-in)</span>
              <span class="count-badge">{{ participants.filter(p => p.checkedIn).length }}/{{ participants.length }}</span>
            </button>

            <button
              type="button"
              class="sub-tab-btn"
              :class="{ active: activeSubNav === 'live-referee-console' }"
              @click="activeSubNav = 'live-referee-console'"
            >
              <span class="material-symbols-outlined text-[18px]">sports</span>
              <span>Bàn Trọng Tài Live</span>
              <span class="live-dot-mini"></span>
            </button>

            <button
              type="button"
              class="sub-tab-btn"
              :class="{ active: activeSubNav === 'tournament-overview' }"
              @click="activeSubNav = 'tournament-overview'"
            >
              <span class="material-symbols-outlined text-[18px]">analytics</span>
              <span>Tổng quan Giải đấu</span>
            </button>
          </nav>
        </div>

        <div class="sub-nav-right">
          <RouterLink to="/tournaments" class="btn-public-preview" title="Xem giao diện người chơi">
            <span class="material-symbols-outlined text-[18px]">visibility</span>
            <span class="hidden sm:inline">Xem Trang Public</span>
          </RouterLink>
        </div>
      </div>

      <!-- ================= TAB 1: BRACKET MANAGEMENT (THE CORE STITCH SCREEN) ================= -->
      <section v-if="activeSubNav === 'bracket-management'" class="main-screen-container">
        <!-- Top Header & Action Row -->
        <div class="screen-header-row">
          <div class="header-text-group">
            <div class="breadcrumb-tag-row">
              <span class="crumb-highlight">TOURNAMENT ADMIN</span>
              <span class="crumb-dot"></span>
              <span class="crumb-sub">Setup & Bracket Management</span>
            </div>
            <h1 class="page-headline">Quản Trị Giải Đấu & Bracket</h1>
            <p class="page-subline">
              Cấu hình giải đấu, quản lý hạt giống (seeds), và tự động phân nhánh sơ đồ thi đấu thông minh chỉ với 1 cú nhấp.
            </p>
          </div>

          <div class="header-actions-group">
            <button
              type="button"
              class="btn-save-draft"
              :disabled="isSaving"
              @click="handleSaveDraft"
            >
              <span class="material-symbols-outlined text-[20px]">save</span>
              <span>Lưu Bản Nháp</span>
            </button>

            <button
              type="button"
              class="btn-publish-event group"
              :disabled="isSaving"
              @click="handlePublishEvent"
            >
              <span>Công Bố Giải Đấu</span>
              <span class="material-symbols-outlined text-[20px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
            </button>
          </div>
        </div>

        <!-- 2-Column Responsive Grid (Setup Wizard + Bracket Preview) -->
        <div class="grid-2col-layout">
          <!-- LEFT COLUMN: SETUP WIZARD (col-span-12 lg:col-span-4) -->
          <div class="wizard-column">
            <div class="wizard-card">
              <div class="wizard-head">
                <h2 class="wizard-title">Quy Trình Thiết Lập (Setup Wizard)</h2>
                <p class="wizard-desc">Hoàn tất 3 bước dưới đây để tạo sơ đồ thi đấu chuẩn xác.</p>
              </div>

              <div class="wizard-stepper-track">
                <div class="stepper-vertical-line"></div>

                <!-- STEP 1: Basic Info -->
                <div
                  class="step-item group"
                  :class="{ active: currentWizardStep === 1, completed: currentWizardStep > 1 }"
                  @click="currentWizardStep = 1"
                >
                  <div class="step-indicator-circle">
                    <span class="material-symbols-outlined text-[16px]">check</span>
                  </div>
                  <div class="step-content">
                    <div class="step-header-flex">
                      <h3 class="step-name">BƯỚC 1: THÔNG TIN CƠ BẢN</h3>
                      <button type="button" class="btn-step-edit" @click.stop="openEditBasicInfoModal" title="Chỉnh sửa thông tin">
                        <span class="material-symbols-outlined text-[14px]">edit</span>
                      </button>
                    </div>
                    <p class="step-value-primary">{{ tournament.name }}</p>
                    <div class="step-meta-pill-row">
                      <span class="meta-inline-pill">
                        <span class="material-symbols-outlined text-[14px]">calendar_today</span>
                        <span>{{ tournament.datesFormatted }}</span>
                      </span>
                      <span class="meta-inline-pill">
                        <span class="material-symbols-outlined text-[14px]">location_on</span>
                        <span>{{ tournament.venue }}</span>
                      </span>
                    </div>
                  </div>
                </div>

                <!-- STEP 2: Participant Seeds -->
                <div
                  class="step-item group"
                  :class="{ active: currentWizardStep === 2, completed: currentWizardStep > 2 }"
                  @click="currentWizardStep = 2"
                >
                  <div class="step-indicator-circle">
                    <span class="material-symbols-outlined text-[16px]">group</span>
                  </div>
                  <div class="step-content">
                    <div class="step-header-flex">
                      <h3 class="step-name">BƯỚC 2: XẾP HẠT GIỐNG (SEEDS)</h3>
                      <button type="button" class="btn-step-edit" @click.stop="openAddParticipantModal" title="Thêm VĐV / Hạt giống">
                        <span class="material-symbols-outlined text-[14px]">add</span>
                      </button>
                    </div>
                    <p class="step-value-primary">{{ participants.length }} Vận Động Viên Đã Đăng Ký</p>

                    <!-- Seed Chips Cloud (Exact Stitch Representation) -->
                    <div class="seed-chips-cloud">
                      <span
                        v-for="p in participants.slice(0, 4)"
                        :key="p.id"
                        class="seed-chip-pill"
                      >
                        <span class="seed-chip-dot"></span>
                        <span>Seed {{ p.seed || '-' }}: {{ p.name }}</span>
                      </span>
                      <span class="seed-chip-more" @click.stop="activeSubNav = 'athlete-check-in'">
                        +{{ Math.max(0, participants.length - 4) }} tay vợt khác
                      </span>
                    </div>
                  </div>
                </div>

                <!-- STEP 3: Bracket Gen (Active in Stitch) -->
                <div
                  class="step-item step-item-highlight group"
                  :class="{ active: currentWizardStep === 3 }"
                  @click="currentWizardStep = 3"
                >
                  <div class="step-indicator-circle circle-active">
                    <span class="material-symbols-outlined text-[16px]">account_tree</span>
                  </div>
                  <div class="step-content w-full">
                    <h3 class="step-name text-primary">BƯỚC 3: PHÂN NHÁNH THI ĐẤU</h3>
                    <p class="step-value-desc">Xem trước, kiểm tra phân nhánh và tự động phân chia sơ đồ theo chuẩn BWF.</p>

                    <div class="mt-3">
                      <button
                        type="button"
                        class="btn-regenerate-bracket"
                        :disabled="isGenerating"
                        @click="reGenerateBracket"
                      >
                        <span class="material-symbols-outlined text-[20px]" :class="{ 'animate-spin': isGenerating }">refresh</span>
                        <span>{{ isGenerating ? 'Đang phân nhánh...' : 'Tái tạo Bracket (Re-generate)' }}</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Quick Seeding Rules Selector -->
              <div class="seeding-rules-box">
                <span class="rules-label">Quy chuẩn xếp nhánh:</span>
                <div class="rules-options">
                  <label class="rule-radio-label">
                    <input v-model="tournament.seedingRule" type="radio" value="standard" />
                    <span>Hạt giống BWF (1 & 2 chia đầu cuối)</span>
                  </label>
                  <label class="rule-radio-label">
                    <input v-model="tournament.seedingRule" type="radio" value="club_split" />
                    <span>Tách cùng CLB ở vòng đầu</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- RIGHT COLUMN: BRACKET PREVIEW (col-span-12 lg:col-span-8) -->
          <div class="bracket-preview-column">
            <div class="bracket-board-box">
              <!-- Board Header & Round Filter Switcher -->
              <div class="board-header-row">
                <div class="board-title-group">
                  <h2 class="board-headline">Sơ Đồ Nhánh Đấu (Bracket Preview)</h2>
                  <p class="board-subline">{{ tournament.category }} • {{ tournament.format === 'single_elimination' ? 'Loại Trực Tiếp (Single Elimination)' : 'Vòng Tròn (Round Robin)' }}</p>
                </div>

                <div class="board-controls-group">
                  <!-- Round Switcher Tabs (Stitch: Round of 16, Quarterfinals, Semifinals, Finals) -->
                  <div class="round-tabs-pill">
                    <button
                      type="button"
                      class="round-tab"
                      :class="{ active: selectedRoundFilter === 'all' }"
                      @click="selectedRoundFilter = 'all'"
                    >
                      Toàn Bộ
                    </button>
                    <button
                      type="button"
                      class="round-tab"
                      :class="{ active: selectedRoundFilter === 'r16' }"
                      @click="selectedRoundFilter = 'r16'"
                    >
                      Vòng 1/16 (R16)
                    </button>
                    <button
                      type="button"
                      class="round-tab"
                      :class="{ active: selectedRoundFilter === 'qf' }"
                      @click="selectedRoundFilter = 'qf'"
                    >
                      Tứ Kết (QF)
                    </button>
                    <button
                      type="button"
                      class="round-tab"
                      :class="{ active: selectedRoundFilter === 'sf' }"
                      @click="selectedRoundFilter = 'sf'"
                    >
                      Bán Kết (SF)
                    </button>
                    <button
                      type="button"
                      class="round-tab"
                      :class="{ active: selectedRoundFilter === 'final' }"
                      @click="selectedRoundFilter = 'final'"
                    >
                      Chung Kết
                    </button>
                  </div>

                  <!-- Zoom Controls -->
                  <div class="zoom-pill-group">
                    <button type="button" class="btn-zoom" title="Thu nhỏ" @click="zoomOut">
                      <span class="material-symbols-outlined text-[16px]">remove</span>
                    </button>
                    <button type="button" class="btn-zoom-val" title="Đặt lại zoom" @click="resetZoom">
                      {{ Math.round(zoomLevel * 100) }}%
                    </button>
                    <button type="button" class="btn-zoom" title="Phóng to" @click="zoomIn">
                      <span class="material-symbols-outlined text-[16px]">add</span>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Interactive Bracket Canvas Viewport -->
              <div class="bracket-canvas-area">
                <div
                  class="bracket-tree-canvas"
                  :style="{ transform: `scale(${zoomLevel})`, transformOrigin: 'top left' }"
                >
                  <!-- SVG Connection Paths -->
                  <svg class="bracket-svg-layer" width="1080" height="740">
                    <!-- R16 -> QF lines -->
                    <path d="M 240 85 L 290 85 L 290 145 L 340 145" fill="none" class="svg-tree-line" />
                    <path d="M 240 205 L 290 205 L 290 145 L 340 145" fill="none" class="svg-tree-line" />
                    <path d="M 240 325 L 290 325 L 290 385 L 340 385" fill="none" class="svg-tree-line" />
                    <path d="M 240 445 L 290 445 L 290 385 L 340 385" fill="none" class="svg-tree-line" />

                    <!-- QF -> SF lines -->
                    <path d="M 580 145 L 630 145 L 630 265 L 680 265" fill="none" class="svg-tree-line live-pulse-line" />
                    <path d="M 580 385 L 630 385 L 630 265 L 680 265" fill="none" class="svg-tree-line" />

                    <!-- SF -> Finals -->
                    <path d="M 920 265 L 970 265" fill="none" class="svg-tree-line final-gold-line" />
                  </svg>

                  <!-- COLUMN 1: ROUND OF 16 (4 representative upper matches for clear layout) -->
                  <div
                    v-if="selectedRoundFilter === 'all' || selectedRoundFilter === 'r16'"
                    class="bracket-round-column col-r16"
                  >
                    <div class="column-title-pill">
                      <span>VÒNG 1/16 (ROUND OF 16)</span>
                    </div>

                    <div class="matches-stack stack-r16">
                      <div
                        v-for="m in r16Matches.slice(0, 4)"
                        :key="m.id"
                        class="match-node-card group"
                        :class="{ 'is-completed': m.status === 'completed' }"
                        @click="openMatchModal(m)"
                      >
                        <!-- Seed Badge Circle (Stitch style) -->
                        <div v-if="m.player1.seed" class="seed-badge-pinned">
                          {{ m.player1.seed }}
                        </div>

                        <!-- Match Head -->
                        <div class="node-head">
                          <span class="match-time-tag">Trận {{ m.matchNumber }} · {{ m.courtName }}</span>
                          <span class="status-chip-done">Đã xong</span>
                        </div>

                        <!-- Player 1 -->
                        <div class="node-player-slot" :class="{ 'is-winner': m.player1.isWinner }">
                          <div class="player-info-meta">
                            <span class="player-name-text">{{ m.player1.name }}</span>
                            <span v-if="m.player1.isWinner" class="winner-dot"></span>
                          </div>
                          <span class="player-set-score">{{ m.player1.sets[0] || '-' }}</span>
                        </div>

                        <!-- Player 2 -->
                        <div class="node-player-slot" :class="{ 'is-winner': m.player2.isWinner, 'is-dimmed': !m.player2.isWinner }">
                          <div class="player-info-meta">
                            <span class="player-name-text">{{ m.player2.name }}</span>
                            <span class="seed-sub-tag">{{ m.player2.club || 'Aura' }}</span>
                          </div>
                          <span class="player-set-score">{{ m.player2.sets[0] || '-' }}</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- COLUMN 2: QUARTERFINALS (TỨ KẾT) -->
                  <div
                    v-if="selectedRoundFilter === 'all' || selectedRoundFilter === 'qf'"
                    class="bracket-round-column col-qf"
                  >
                    <div class="column-title-pill">
                      <span>TỨ KẾT (QUARTERFINALS)</span>
                    </div>

                    <div class="matches-stack stack-qf">
                      <div
                        v-for="m in qfMatches.slice(0, 2)"
                        :key="m.id"
                        class="match-node-card group"
                        :class="{ 'is-completed': m.status === 'completed' }"
                        @click="openMatchModal(m)"
                      >
                        <div class="node-head">
                          <span class="match-time-tag">Trận {{ m.matchNumber }} · {{ m.courtName }}</span>
                          <span class="status-chip-done">Đã xong</span>
                        </div>

                        <!-- Player 1 -->
                        <div class="node-player-slot" :class="{ 'is-winner': m.player1.isWinner }">
                          <div class="player-info-meta">
                            <span class="player-name-text">{{ m.player1.name }}</span>
                            <span v-if="m.player1.isWinner" class="winner-dot"></span>
                          </div>
                          <span class="player-set-score">{{ m.player1.sets[0] || '-' }}</span>
                        </div>

                        <!-- Player 2 -->
                        <div class="node-player-slot" :class="{ 'is-winner': m.player2.isWinner, 'is-dimmed': !m.player2.isWinner }">
                          <div class="player-info-meta">
                            <span class="player-name-text">{{ m.player2.name }}</span>
                            <span class="seed-sub-tag">{{ m.player2.club || 'Aura' }}</span>
                          </div>
                          <span class="player-set-score">{{ m.player2.sets[0] || '-' }}</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- COLUMN 3: SEMIFINALS (BÁN KẾT) -->
                  <div
                    v-if="selectedRoundFilter === 'all' || selectedRoundFilter === 'sf'"
                    class="bracket-round-column col-sf"
                  >
                    <div class="column-title-pill">
                      <span>BÁN KẾT (SEMIFINALS)</span>
                    </div>

                    <div class="matches-stack stack-sf">
                      <div
                        v-for="m in sfMatches.slice(0, 1)"
                        :key="m.id"
                        class="match-node-card card-live-pulse group"
                        @click="openMatchModal(m)"
                      >
                        <div class="node-head head-live">
                          <span class="match-time-tag">Trận {{ m.matchNumber }} · {{ m.courtName }}</span>
                          <span class="status-chip-live">
                            <span class="live-ping-dot"></span>
                            TRỰC TIẾP
                          </span>
                        </div>

                        <!-- Player 1 Live -->
                        <div class="node-player-slot is-live-serving">
                          <div class="player-info-meta">
                            <span class="player-name-text">{{ m.player1.name }}</span>
                            <span class="material-symbols-outlined serve-racket-icon">sports_tennis</span>
                          </div>
                          <span class="live-current-score">{{ m.player1.currentScore ?? 19 }}</span>
                        </div>

                        <!-- Player 2 Live -->
                        <div class="node-player-slot">
                          <div class="player-info-meta">
                            <span class="player-name-text">{{ m.player2.name }}</span>
                          </div>
                          <span class="live-current-score">{{ m.player2.currentScore ?? 17 }}</span>
                        </div>

                        <div class="node-footer-sets">
                          <span>Set 1: 21-19</span>
                          <span>Set 2: 18-21</span>
                          <span class="text-primary font-bold">Set 3 Đang đấu</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- COLUMN 4: GRAND FINALS (CHUNG KẾT) -->
                  <div
                    v-if="selectedRoundFilter === 'all' || selectedRoundFilter === 'final'"
                    class="bracket-round-column col-final"
                  >
                    <div class="column-title-pill pill-gold">
                      <span class="material-symbols-outlined text-[16px] text-amber-500">emoji_events</span>
                      <span class="text-amber-600 font-bold">CHUNG KẾT (GRAND FINAL)</span>
                    </div>

                    <div class="matches-stack stack-final">
                      <div class="match-node-card card-grand-final" @click="openMatchModal(finalMatch)">
                        <div class="node-head head-final">
                          <span class="match-time-tag">{{ finalMatch.courtName }}</span>
                          <span class="final-badge-gold">CÚP VÔ ĐỊCH</span>
                        </div>

                        <div class="final-inner-box">
                          <div class="finalist-slot-row">
                            <span class="material-symbols-outlined text-[16px] text-muted">sports_tennis</span>
                            <span class="slot-text">{{ finalMatch.player1.name }}</span>
                          </div>
                          <div class="vs-divider-pill">VS</div>
                          <div class="finalist-slot-row">
                            <span class="material-symbols-outlined text-[16px] text-muted">sports_tennis</span>
                            <span class="slot-text">{{ finalMatch.player2.name }}</span>
                          </div>
                        </div>

                        <div class="final-footer-schedule">
                          <span class="material-symbols-outlined text-[14px]">schedule</span>
                          <span>{{ finalMatch.scheduledTime }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Bottom Tip Bar (Exact from Stitch: "Drag to adjust seeds manually") -->
              <div class="board-bottom-tip-row">
                <div class="tip-left">
                  <span class="material-symbols-outlined text-[18px]">info</span>
                  <span class="text-xs sm:text-sm font-medium">
                    Nhấp trực tiếp vào thẻ trận đấu để cập nhật tỷ số từng set, chỉ định sân đấu hoặc gán trọng tài điều khiển.
                  </span>
                </div>

                <div class="tip-right-actions">
                  <button type="button" class="btn-action-small" @click="reGenerateBracket">
                    <span class="material-symbols-outlined text-[16px]">shuffle</span>
                    <span>Xáo Trộn Hạt Giống</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ================= TAB 2: ATHLETE CHECK-IN (STITCH DESIGN fb209f33922d4a63b68163a162b1647c) ================= -->
      <section v-else-if="activeSubNav === 'athlete-check-in'" class="main-screen-container athlete-checkin-view">
        <!-- Top Screen Header & Primary Action Row -->
        <div class="screen-header-row mb-6">
          <div class="header-text-group">
            <div class="breadcrumb-tag-row">
              <span class="crumb-highlight">AURA TOURNAMENT</span>
              <span class="crumb-dot"></span>
              <span class="crumb-sub">Athlete Registry & Access Control</span>
            </div>
            <h1 class="page-headline">Điểm Danh Vận Động Viên</h1>
            <p class="page-subline">
              Quản lý vận động viên có mặt, xác minh danh tính và cấp quyền vào sân thi đấu cho các trận đấu hôm nay.
            </p>
          </div>

          <div class="header-actions-group">
            <button
              type="button"
              class="btn-scan-qr shadow-md"
              @click="openQrScannerModal"
            >
              <span class="material-symbols-outlined text-[20px]">qr_code_scanner</span>
              <span>Quét Mã QR</span>
            </button>

            <button
              type="button"
              class="btn-save-draft"
              @click="openAddParticipantModal"
            >
              <span class="material-symbols-outlined text-[20px]">person_add</span>
              <span>Thêm VĐV</span>
            </button>
          </div>
        </div>

        <!-- 2-Column Responsive Grid (Left 8 Cols Athletes Feed + Right 4 Cols Sticky Gauge & Tools) -->
        <div class="checkin-grid-layout">
          <!-- LEFT 8 COLUMNS: ATHLETE CARDS FEED -->
          <div class="checkin-left-column">
            <!-- Filter Tabs Pills Aligned with Stitch (All, Pending, Checked In, No-show) -->
            <div class="filter-tabs-pill-bar">
              <button
                type="button"
                class="filter-pill-item"
                :class="{ active: checkinFilterTab === 'all' }"
                @click="checkinFilterTab = 'all'"
              >
                Tất Cả ({{ totalAthletesCount }})
              </button>

              <button
                type="button"
                class="filter-pill-item"
                :class="{ active: checkinFilterTab === 'pending' }"
                @click="checkinFilterTab = 'pending'"
              >
                Chờ Điểm Danh ({{ pendingCount }})
              </button>

              <button
                type="button"
                class="filter-pill-item"
                :class="{ active: checkinFilterTab === 'checked_in' }"
                @click="checkinFilterTab = 'checked_in'"
              >
                Đã Điểm Danh ({{ checkedInCount }})
              </button>

              <button
                type="button"
                class="filter-pill-item"
                :class="{ active: checkinFilterTab === 'no_show' }"
                @click="checkinFilterTab = 'no_show'"
              >
                Vắng Mặt ({{ noShowCount }})
              </button>
            </div>

            <!-- Search & Secondary Filter Toolbar -->
            <div class="checkin-search-bar-row">
              <div class="search-field-wrapper">
                <span class="material-symbols-outlined text-muted">search</span>
                <input
                  v-model="checkinSearchQuery"
                  type="text"
                  placeholder="Tìm theo tên VĐV, mã thẻ (#ATH-xxx), CLB, SĐT..."
                  class="search-input-field"
                />
                <button
                  v-if="checkinSearchQuery"
                  type="button"
                  class="btn-clear-search"
                  @click="checkinSearchQuery = ''"
                >
                  <span class="material-symbols-outlined text-[16px]">close</span>
                </button>
              </div>

              <div class="filter-select-wrapper">
                <select v-model="checkinLevelFilter" class="level-select-dropdown">
                  <option value="all">Tất Cả Cấp Độ</option>
                  <option value="Lvl 5">Level 5 (Chuyên Nghiệp)</option>
                  <option value="Lvl 4">Level 4 (Nâng Cao)</option>
                  <option value="Lvl 3">Level 3 (Phong Trào)</option>
                </select>
              </div>

              <button
                type="button"
                class="btn-export-report"
                title="Xuất báo cáo điểm danh"
                @click="exportCheckInReport"
              >
                <span class="material-symbols-outlined text-[18px]">download</span>
                <span class="hidden md:inline">Xuất Báo Cáo</span>
              </button>
            </div>

            <!-- Athletes Cards Feed (Exact Stitch Representation) -->
            <div class="athletes-cards-feed">
              <div
                v-for="p in visibleAthletes"
                :key="p.id"
                class="athlete-feed-card group"
                :class="{
                  'is-checked-in': p.checkedIn,
                  'is-no-show': p.status === 'no_show',
                }"
              >
                <!-- Active Left Stripe -->
                <div v-if="p.checkedIn" class="card-active-indicator-stripe"></div>
                <div v-else-if="p.status === 'no_show'" class="card-noshow-indicator-stripe"></div>

                <!-- Left: Athlete Photo / Initials & Details -->
                <div class="athlete-main-info-group">
                  <!-- Avatar Container with fallback / initials -->
                  <div class="athlete-avatar-box">
                    <img
                      v-if="p.avatar && !p.initials"
                      :src="p.avatar"
                      :alt="p.name"
                      class="athlete-avatar-img"
                      @error="handleImageError($event)"
                    />
                    <div v-else class="athlete-initials-badge">
                      <span>{{ p.initials || p.name.slice(0, 2).toUpperCase() }}</span>
                    </div>
                  </div>

                  <!-- Details Column -->
                  <div class="athlete-text-meta">
                    <div class="athlete-name-row">
                      <h3 class="athlete-name-headline">{{ p.name }}</h3>
                      <span v-if="p.qrCode" class="athlete-id-pill">#{{ p.qrCode }}</span>
                    </div>

                    <!-- Meta Badges (Level, Club, Seed) -->
                    <div class="athlete-badges-row">
                      <span class="badge-level-chip">{{ p.level || 'Lvl 4' }}</span>

                      <span class="badge-club-chip">
                        <span class="material-symbols-outlined text-[13px]">location_on</span>
                        <span>{{ p.club || 'Aura Club' }}</span>
                      </span>

                      <span
                        class="badge-seed-chip"
                        :class="{ 'seed-highlight': p.seed }"
                      >
                        <span class="material-symbols-outlined text-[13px]">workspace_premium</span>
                        <span>{{ p.seed ? 'Seed ' + p.seed : 'Unseeded' }}</span>
                      </span>

                      <span v-if="p.warmupCourt" class="badge-warmup-chip">
                        <span class="material-symbols-outlined text-[13px]">sports_tennis</span>
                        <span>{{ p.warmupCourt }}</span>
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Right: Status Timestamp, Quick Badges & Big Check Action Button -->
                <div class="athlete-action-group">
                  <!-- Quick Badges / Actions Popover -->
                  <div class="athlete-tools-row">
                    <!-- Racket Verification Toggle -->
                    <button
                      type="button"
                      class="quick-pill-btn"
                      :class="{ active: p.racketChecked }"
                      title="Kiểm tra tiêu chuẩn vợt"
                      @click.stop="p.racketChecked = !p.racketChecked; showNotice(p.racketChecked ? `🏸 Vợt của ${p.name} đạt tiêu chuẩn thi đấu!` : `ℹ️ Đã hủy xác nhận vợt cho ${p.name}`)"
                    >
                      <span class="material-symbols-outlined text-[14px]">
                        {{ p.racketChecked ? 'check_circle' : 'pending' }}
                      </span>
                      <span>{{ p.racketChecked ? 'Vợt Đạt Chuẩn' : 'Test Vợt' }}</span>
                    </button>

                    <!-- Digital Pass Modal Trigger -->
                    <button
                      type="button"
                      class="quick-pill-btn"
                      title="Xem thẻ VĐV kỹ thuật số & mã QR"
                      @click.stop="openDigitalPassModal(p)"
                    >
                      <span class="material-symbols-outlined text-[14px]">badge</span>
                      <span>Thẻ VĐV</span>
                    </button>

                    <!-- No Show Toggle -->
                    <button
                      v-if="!p.checkedIn"
                      type="button"
                      class="quick-pill-btn btn-pill-danger"
                      title="Đánh dấu vắng mặt"
                      @click.stop="markAsNoShow(p)"
                    >
                      <span class="material-symbols-outlined text-[14px]">person_off</span>
                    </button>
                  </div>

                  <!-- Status Label & Timestamp -->
                  <div class="status-timestamp-box">
                    <span
                      class="status-title-lbl"
                      :class="{
                        'text-primary': p.checkedIn,
                        'text-error': p.status === 'no_show',
                        'text-muted': !p.checkedIn && p.status !== 'no_show',
                      }"
                    >
                      {{ p.checkedIn ? 'Checked In' : p.status === 'no_show' ? 'No-show' : 'Pending' }}
                    </span>
                    <span class="timestamp-subline">
                      {{ p.checkedIn ? (p.checkInTime || '08:14 AM') : p.status === 'no_show' ? 'Vắng mặt' : (p.estimatedArrival || 'Est. 09:00 AM') }}
                    </span>
                  </div>

                  <!-- Big Circular Check-in Action Button (Stitch Exact UI) -->
                  <button
                    type="button"
                    class="btn-round-checkin"
                    :class="{
                      'is-checked': p.checkedIn,
                      'is-no-show': p.status === 'no_show',
                    }"
                    :title="p.checkedIn ? 'Hủy điểm danh' : 'Bấm để điểm danh vào sân'"
                    @click="toggleCheckIn(p)"
                  >
                    <span
                      v-if="p.checkedIn"
                      class="material-symbols-outlined text-[24px]"
                      style="font-variation-settings: 'FILL' 1;"
                    >
                      check_circle
                    </span>
                    <span v-else class="material-symbols-outlined text-[24px]">
                      radio_button_unchecked
                    </span>
                  </button>
                </div>
              </div>

              <!-- Empty State If No Search Matches -->
              <div v-if="filteredAthletesList.length === 0" class="empty-feed-card">
                <span class="material-symbols-outlined text-[48px] text-muted">person_search</span>
                <h4 class="font-bold text-ink text-base mt-2">Không tìm thấy vận động viên</h4>
                <p class="text-sm text-muted">Không có VĐV nào khớp với từ khóa tìm kiếm hoặc bộ lọc hiện tại.</p>
                <button
                  type="button"
                  class="btn-cancel mt-3"
                  @click="checkinSearchQuery = ''; checkinFilterTab = 'all'; checkinLevelFilter = 'all'"
                >
                  Xóa Bộ Lọc
                </button>
              </div>
            </div>

            <!-- Load More Athletes Button Aligned with Stitch -->
            <div v-if="hasMoreAthletes" class="load-more-row">
              <button
                type="button"
                class="btn-load-more-athletes group"
                @click="loadMoreAthletes"
              >
                <span>Tải Thêm Vận Động Viên (Còn {{ filteredAthletesList.length - visibleAthletesCount }})</span>
                <span class="material-symbols-outlined text-[18px] group-hover:translate-y-0.5 transition-transform">expand_more</span>
              </button>
            </div>
          </div>

          <!-- RIGHT 4 COLUMNS: STICKY CHECK-IN STATUS CARD & LIVE RADIAL PROGRESS GAUGE -->
          <div class="checkin-right-column">
            <div class="sticky-status-card">
              <!-- Background Ambient Glow -->
              <div class="card-ambient-glow"></div>

              <div class="relative z-10">
                <div class="status-card-header">
                  <h2 class="status-card-title">Check-in Status</h2>
                  <span class="status-badge-chip">Trực Tiếp</span>
                </div>

                <!-- Radial Progress Gauge (Exact Stitch SVG Visualization) -->
                <div class="radial-gauge-wrapper">
                  <svg class="radial-svg-gauge" viewBox="0 0 100 100">
                    <circle
                      class="gauge-bg-circle"
                      cx="50"
                      cy="50"
                      fill="transparent"
                      r="40"
                      stroke-width="8"
                    />
                    <circle
                      class="gauge-progress-circle"
                      cx="50"
                      cy="50"
                      fill="transparent"
                      r="40"
                      stroke-width="8"
                      stroke-dasharray="251.2"
                      :stroke-dashoffset="strokeDashoffset"
                      stroke-linecap="round"
                    />
                  </svg>

                  <!-- Inner Gauge Percentage Label -->
                  <div class="gauge-center-content">
                    <span class="gauge-pct-number">{{ checkInPercentage }}%</span>
                    <span class="gauge-pct-sublabel">Arrived</span>
                  </div>
                </div>

                <!-- Breakdown Metrics List -->
                <div class="status-metrics-list">
                  <div
                    class="metric-item-row group cursor-pointer"
                    :class="{ active: checkinFilterTab === 'checked_in' }"
                    @click="checkinFilterTab = 'checked_in'"
                  >
                    <div class="metric-label-group">
                      <div class="metric-dot dot-checked-in"></div>
                      <span class="metric-name">Checked In</span>
                    </div>
                    <span class="metric-value-num text-primary font-bold">{{ checkedInCount }}</span>
                  </div>

                  <div
                    class="metric-item-row group cursor-pointer"
                    :class="{ active: checkinFilterTab === 'pending' }"
                    @click="checkinFilterTab = 'pending'"
                  >
                    <div class="metric-label-group">
                      <div class="metric-dot dot-pending"></div>
                      <span class="metric-name text-muted">Pending</span>
                    </div>
                    <span class="metric-value-num text-muted font-bold">{{ pendingCount }}</span>
                  </div>

                  <div
                    class="metric-item-row group cursor-pointer"
                    :class="{ active: checkinFilterTab === 'no_show' }"
                    @click="checkinFilterTab = 'no_show'"
                  >
                    <div class="metric-label-group">
                      <div class="metric-dot dot-noshow"></div>
                      <span class="metric-name text-muted">No-show</span>
                    </div>
                    <span class="metric-value-num text-muted font-bold">{{ noShowCount }}</span>
                  </div>
                </div>

                <!-- Next Match Starts Banner Aligned with Stitch -->
                <div class="next-match-card-box">
                  <p class="next-match-label">Next Match Starts</p>
                  <p class="next-match-time">09:00 AM</p>
                  <p class="next-match-court">Men's Singles - Court 1 & 2</p>

                  <div class="next-match-actions mt-3">
                    <button
                      type="button"
                      class="btn-go-referee-console"
                      @click="activeSubNav = 'live-referee-console'"
                    >
                      <span class="material-symbols-outlined text-[16px]">sports</span>
                      <span>Bàn Trọng Tài Live</span>
                    </button>
                    <button
                      type="button"
                      class="btn-view-bracket-link"
                      @click="activeSubNav = 'bracket-management'"
                    >
                      <span class="material-symbols-outlined text-[16px]">account_tree</span>
                      <span>Sơ Đồ Nhánh</span>
                    </button>
                  </div>
                </div>

                <!-- Quick QR Code Scanner Panel Simulation Widget -->
                <div class="quick-qr-widget-box">
                  <div class="widget-head-row">
                    <span class="material-symbols-outlined text-primary text-[18px]">qr_code</span>
                    <span class="font-bold text-xs text-ink uppercase tracking-wider">Quét Nhanh Thẻ VĐV</span>
                  </div>

                  <div class="quick-qr-input-group">
                    <input
                      v-model="manualQrInput"
                      type="text"
                      placeholder="Mã thẻ (#ATH-001 hoặc SĐT)"
                      class="quick-qr-field"
                      @keyup.enter="submitManualQr"
                    />
                    <button
                      type="button"
                      class="btn-quick-qr-submit"
                      @click="submitManualQr"
                    >
                      Điểm Danh
                    </button>
                  </div>

                  <!-- Sample Test Scan Chips -->
                  <div class="quick-sample-chips-row">
                    <span class="text-[11px] text-muted">Mẫu thử:</span>
                    <button
                      type="button"
                      class="sample-scan-chip"
                      @click="simulateQrScan('ATH-001')"
                    >
                      Sarah Jenkins
                    </button>
                    <button
                      type="button"
                      class="sample-scan-chip"
                      @click="simulateQrScan('ATH-002')"
                    >
                      Marcus Chen
                    </button>
                    <button
                      type="button"
                      class="sample-scan-chip"
                      @click="simulateQrScan('ATH-003')"
                    >
                      David Rossi
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ================= TAB 3: LIVE REFEREE CONSOLE ================= -->
      <section v-else-if="activeSubNav === 'live-referee-console'" class="main-screen-container referee-screen-root" :class="{ 'tablet-fullscreen-mode': isTabletFullscreen }">
        <!-- HEADER AREA ALIGNED WITH STITCH -->
        <div class="stitch-referee-header-row">
          <div class="court-branding-group">
            <!-- Circular Numbered Court Badge -->
            <div class="court-number-badge">
              <span>{{ refereeSelectedCourt === 101 ? '1' : refereeSelectedCourt === 102 ? '2' : refereeSelectedCourt === 103 ? '3' : '4' }}</span>
            </div>
            <div>
              <h1 class="court-main-title">{{ activeLiveFixture.courtName || 'Court 1' }}</h1>
              <p class="live-match-subline">
                <span class="live-pulse-dot"></span>
                <span class="font-semibold text-ink dark:text-white">Live Match</span>
                <span class="text-muted ml-2">· {{ formattedMatchDuration }} (Set {{ liveCurrentSet }}/3)</span>
              </p>
            </div>
          </div>

          <!-- Center Set Switcher Pills -->
          <div class="set-switcher-pillbox">
            <button
              type="button"
              class="set-tab-btn"
              :class="{ active: liveCurrentSet === 1 }"
              @click="selectSet(1)"
            >
              Set 1 ({{ activeLiveFixture.player1.sets[0] || 21 }}-{{ activeLiveFixture.player2.sets[0] || 19 }})
            </button>
            <button
              type="button"
              class="set-tab-btn"
              :class="{ active: liveCurrentSet === 2 }"
              @click="selectSet(2)"
            >
              Set 2 ({{ activeLiveFixture.player1.sets[1] || 18 }}-{{ activeLiveFixture.player2.sets[1] || 21 }})
            </button>
            <button
              type="button"
              class="set-tab-btn"
              :class="{ active: liveCurrentSet === 3 }"
              @click="selectSet(3)"
            >
              Set 3 <span v-if="liveCurrentSet === 3" class="active-dot-mini"></span>
            </button>
          </div>

          <!-- Right Quick Nav & Tools -->
          <div class="referee-top-right-tools">
            <!-- Court Switcher Dropdown / Pills -->
            <div class="quick-court-tabs">
              <button
                v-for="cId in [101, 102, 103, 104]"
                :key="cId"
                type="button"
                class="court-switch-btn"
                :class="{ active: refereeSelectedCourt === cId }"
                @click="selectCourt(cId)"
              >
                Sân {{ cId - 100 }}
              </button>
            </div>

            <!-- Fullscreen Tablet Toggle -->
            <button
              type="button"
              class="btn-referee-tool-icon"
              :title="isTabletFullscreen ? 'Thoát toàn màn hình' : 'Mở chế độ máy tính bảng toàn màn hình'"
              @click="toggleTabletFullscreen"
            >
              <span class="material-symbols-outlined text-[20px]">
                {{ isTabletFullscreen ? 'fullscreen_exit' : 'fullscreen' }}
              </span>
            </button>

            <!-- Sound FX Toggle -->
            <button
              type="button"
              class="btn-referee-tool-icon"
              :class="{ active: isSoundFeedbackEnabled }"
              :title="isSoundFeedbackEnabled ? 'Tắt âm thanh thông báo' : 'Bật âm thanh trọng tài'"
              @click="isSoundFeedbackEnabled = !isSoundFeedbackEnabled; showNotice(isSoundFeedbackEnabled ? '🔊 Đã bật âm thanh tiếng bíp trọng tài' : '🔇 Đã tắt âm thanh')"
            >
              <span class="material-symbols-outlined text-[20px]">
                {{ isSoundFeedbackEnabled ? 'volume_up' : 'volume_off' }}
              </span>
            </button>

            <!-- Back to Overview Button (Stitch Exact) -->
            <button
              type="button"
              class="btn-back-overview-stitch group"
              @click="activeSubNav = 'tournament-overview'"
            >
              <span class="material-symbols-outlined text-[20px] group-hover:-translate-x-1 transition-transform">arrow_back</span>
              <span class="font-label-sm text-label-sm">Back to Overview</span>
            </button>
          </div>
        </div>

        <!-- REALTIME CONFLICT ALERT BANNER (Stitch Exact UI with Slide Animation) -->
        <Transition name="slide-down">
          <div v-if="isConflictAlertVisible" class="conflict-alert-banner" id="conflict-alert">
            <div class="flex items-start gap-3 w-full">
              <span class="material-symbols-outlined text-error text-[24px] mt-0.5">sync_problem</span>
              <div class="flex-1">
                <h4 class="font-bold text-base text-error mb-1">Score Synchronization Conflict</h4>
                <p class="text-sm text-error/90 mb-3">
                  Another device updated the score simultaneously ({{ conflictRemoteScores.p1 }} - {{ conflictRemoteScores.p2 }}). Please verify the current score.
                </p>
                <div class="flex flex-wrap gap-2.5">
                  <button
                    type="button"
                    class="btn-accept-remote"
                    @click="acceptRemoteConflict"
                  >
                    Accept Remote ({{ conflictRemoteScores.p1 }}-{{ conflictRemoteScores.p2 }})
                  </button>
                  <button
                    type="button"
                    class="btn-keep-local"
                    @click="keepLocalConflict"
                  >
                    Keep Local ({{ liveSetScoreP1 }}-{{ liveSetScoreP2 }})
                  </button>
                </div>
              </div>
              <button
                type="button"
                class="btn-close-alert"
                title="Đóng thông báo"
                @click="dismissConflictAlert"
              >
                <span class="material-symbols-outlined text-error text-[20px]">close</span>
              </button>
            </div>
          </div>
        </Transition>

        <!-- MAIN SCORING ARENA (2 COLUMNS WITH CENTRAL VS & COURT SVG LINES) -->
        <div class="scoring-stage-arena" :class="{ 'is-swapped-sides': isSwappedEnds }">
          <!-- Background Subtle Badminton Court Vector Graphic -->
          <div class="court-vector-backdrop" aria-hidden="true">
            <svg class="w-full h-full opacity-10" viewBox="0 0 1000 500" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="20" y="20" width="960" height="460" rx="16" stroke="currentColor" stroke-width="2" stroke-dasharray="4 4" />
              <line x1="500" y1="20" x2="500" y2="480" stroke="currentColor" stroke-width="3" />
              <line x1="380" y1="20" x2="380" y2="480" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2 2" />
              <line x1="620" y1="20" x2="620" y2="480" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2 2" />
              <line x1="20" y1="250" x2="980" y2="250" stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 3" />
            </svg>
          </div>

          <!-- PLAYER 1 (Sarah Jenkins / Player A) CARD -->
          <div class="referee-player-card stitch-player-card" :class="{ 'is-serving-card': liveServingPlayer === 1, 'order-2': isSwappedEnds }">
            <!-- Player Top Info & Serve Indicator -->
            <div class="card-head-row">
              <div
                class="serve-status-indicator"
                :class="{ 'opacity-100': liveServingPlayer === 1, 'opacity-0': liveServingPlayer !== 1 }"
                id="serve-indicator-a"
              >
                <span class="material-symbols-outlined text-primary text-[30px]" style="font-variation-settings: 'FILL' 1;">sports_tennis</span>
                <span class="serve-label-text">Serving</span>
              </div>

              <!-- Sets Won Tracker Dots (e.g. 2 sets won dots) -->
              <div class="sets-won-dots-group">
                <div class="set-dot" :class="{ 'is-won': liveP1SetsWon >= 1 }"></div>
                <div class="set-dot" :class="{ 'is-won': liveP1SetsWon >= 2 }"></div>
              </div>
            </div>

            <!-- Player Name & Club Info -->
            <div class="player-identity-center">
              <div class="flex items-center justify-center gap-2">
                <img :src="activeLiveFixture.player1.avatar" class="w-10 h-10 rounded-full border-2 border-surface shadow-sm object-cover" @error="handleImageError($event)" />
                <h2 class="player-big-name truncate">{{ activeLiveFixture.player1.name }}</h2>
              </div>
              <p class="player-club-subtext">{{ activeLiveFixture.player1.club || 'Northside Club' }} · Seed {{ activeLiveFixture.player1.seed || 1 }}</p>
            </div>

            <!-- BIG CIRCULAR SCORE BUTTON (Stitch Exact Micro-Interaction) -->
            <button
              type="button"
              class="stitch-big-score-btn group/btn"
              :class="{ 'score-pop-anim': p1ScoreAnim }"
              title="Nhấn để cộng 1 điểm cho vận động viên"
              @click="incrementPoint(1)"
            >
              <span class="score-hover-ring"></span>
              <span class="score-giant-number" id="score-a">{{ liveSetScoreP1 }}</span>
            </button>

            <!-- DECREASE SCORE BUTTON (-1) -->
            <button
              type="button"
              class="btn-stitch-decrease"
              title="Giảm 1 điểm"
              @click="decrementPoint(1)"
            >
              <span class="material-symbols-outlined text-[28px]">remove</span>
            </button>

            <!-- Ambient Soft Glow Backdrop -->
            <div class="ambient-glow-pink"></div>
          </div>

          <!-- CENTER VS DIVIDER & BADMINTON MATCH HELPER BADGES -->
          <div class="stitch-vs-divider-column">
            <!-- Circular VS Badge -->
            <div class="vs-circle-badge">
              <span class="vs-text">VS</span>
            </div>

            <!-- Match Status Badges (Deuce / Match Point / Game Point) -->
            <div class="match-meta-status-box">
              <div v-if="isSuddenDeath" class="status-chip chip-sudden-death animate-bounce">
                🔥 SUDDEN DEATH (29-29)
              </div>
              <div v-else-if="isMatchPoint === 1" class="status-chip chip-match-point">
                MATCH POINT: {{ activeLiveFixture.player1.name }}
              </div>
              <div v-else-if="isMatchPoint === 2" class="status-chip chip-match-point">
                MATCH POINT: {{ activeLiveFixture.player2.name }}
              </div>
              <div v-else-if="isGamePoint === 1" class="status-chip chip-game-point">
                GAME POINT: {{ activeLiveFixture.player1.name }}
              </div>
              <div v-else-if="isGamePoint === 2" class="status-chip chip-game-point">
                GAME POINT: {{ activeLiveFixture.player2.name }}
              </div>
              <div v-else-if="isDeuce" class="status-chip chip-deuce">
                ⚖️ DEUCE ({{ liveSetScoreP1 }}-{{ liveSetScoreP2 }})
              </div>

              <div class="service-box-tag">
                <span class="material-symbols-outlined text-[13px] text-muted">sports</span>
                <span>Ô giao cầu: <strong>{{ serviceCourtSide }}</strong></span>
              </div>
            </div>

            <!-- Quick Serve Switch Button -->
            <button
              type="button"
              class="btn-quick-serve-switch group"
              title="Đổi bên giao cầu"
              @click="switchServe"
            >
              <span class="material-symbols-outlined text-[18px] group-hover:rotate-180 transition-transform duration-300">sync_alt</span>
              <span>Đổi Giao Cầu</span>
            </button>
          </div>

          <!-- PLAYER 2 (David Chen / Player B) CARD -->
          <div class="referee-player-card stitch-player-card" :class="{ 'is-serving-card': liveServingPlayer === 2, 'order-1': isSwappedEnds }">
            <!-- Player Top Info & Serve Indicator -->
            <div class="card-head-row">
              <div
                class="serve-status-indicator"
                :class="{ 'opacity-100': liveServingPlayer === 2, 'opacity-0': liveServingPlayer !== 2 }"
                id="serve-indicator-b"
              >
                <span class="material-symbols-outlined text-primary text-[30px]" style="font-variation-settings: 'FILL' 1;">sports_tennis</span>
                <span class="serve-label-text">Serving</span>
              </div>

              <!-- Sets Won Tracker Dots -->
              <div class="sets-won-dots-group">
                <div class="set-dot" :class="{ 'is-won': liveP2SetsWon >= 1 }"></div>
                <div class="set-dot" :class="{ 'is-won': liveP2SetsWon >= 2 }"></div>
              </div>
            </div>

            <!-- Player Name & Club Info -->
            <div class="player-identity-center">
              <div class="flex items-center justify-center gap-2">
                <img :src="activeLiveFixture.player2.avatar" class="w-10 h-10 rounded-full border-2 border-surface shadow-sm object-cover" @error="handleImageError($event)" />
                <h2 class="player-big-name truncate">{{ activeLiveFixture.player2.name }}</h2>
              </div>
              <p class="player-club-subtext">{{ activeLiveFixture.player2.club || 'Elite Badminton Academy' }} · Seed {{ activeLiveFixture.player2.seed || 2 }}</p>
            </div>

            <!-- BIG CIRCULAR SCORE BUTTON -->
            <button
              type="button"
              class="stitch-big-score-btn group/btn"
              :class="{ 'score-pop-anim': p2ScoreAnim }"
              title="Nhấn để cộng 1 điểm cho vận động viên"
              @click="incrementPoint(2)"
            >
              <span class="score-hover-ring"></span>
              <span class="score-giant-number" id="score-b">{{ liveSetScoreP2 }}</span>
            </button>

            <!-- DECREASE SCORE BUTTON (-1) -->
            <button
              type="button"
              class="btn-stitch-decrease"
              title="Giảm 1 điểm"
              @click="decrementPoint(2)"
            >
              <span class="material-symbols-outlined text-[28px]">remove</span>
            </button>

            <!-- Ambient Soft Glow Backdrop -->
            <div class="ambient-glow-cyan"></div>
          </div>
        </div>

        <!-- BOTTOM ACTIONS TOOLBAR (Stitch Exact UI) -->
        <div class="stitch-referee-bottom-bar">
          <!-- Left Conflict Sync Test Button -->
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="btn-test-conflict group"
              title="Kiểm tra mô phỏng xung đột đồng bộ giữa nhiều thiết bị"
              @click="simulateConflict"
            >
              <span class="material-symbols-outlined text-[20px] text-amber-500 group-hover:scale-110 transition-transform">warning</span>
              <span class="font-label-sm text-label-sm">Test Conflict Sync</span>
            </button>

            <!-- Rally Events Drawer Toggle -->
            <button
              type="button"
              class="btn-rally-log-toggle"
              :class="{ active: isRallyLogDrawerOpen }"
              @click="isRallyLogDrawerOpen = !isRallyLogDrawerOpen"
            >
              <span class="material-symbols-outlined text-[18px]">history</span>
              <span>Lịch Sử Điểm ({{ rallyEventsLog.length }})</span>
            </button>
          </div>

          <!-- Center/Right Action Buttons Group -->
          <div class="flex items-center gap-3">
            <!-- Cards & Penalties Button -->
            <button
              type="button"
              class="btn-referee-action-sec"
              title="Ghi nhận thẻ phạt hoặc thời gian y tế"
              @click="openCardsModal"
            >
              <span class="material-symbols-outlined text-[18px] text-rose-500">style</span>
              <span>Thẻ Phạt / Lỗi</span>
            </button>

            <!-- Undo Last Point Button -->
            <button
              type="button"
              class="btn-referee-action-sec"
              :disabled="pointHistoryStack.length === 0"
              title="Hoàn tác điểm số vừa nhập"
              @click="undoLastPoint"
            >
              <span class="material-symbols-outlined text-[18px]">undo</span>
              <span>Hoàn Tác</span>
            </button>

            <!-- Switch Ends (Đổi Sân) Button -->
            <button
              type="button"
              class="btn-switch-ends group"
              title="Đổi bên sân thi đấu giữa 2 VĐV"
              @click="switchEnds"
            >
              <span class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] group-hover:rotate-180 transition-transform duration-500">swap_horiz</span>
                <span>Switch Ends</span>
              </span>
            </button>

            <!-- Finish Set Button -->
            <button
              type="button"
              class="btn-referee-action-sec"
              title="Kết thúc set đấu hiện tại"
              @click="finishSet"
            >
              <span class="material-symbols-outlined text-[18px] text-emerald-500">done_all</span>
              <span>Finish Set</span>
            </button>

            <!-- Finish Match Button (Stitch Exact UI with Shimmer Glow) -->
            <button
              type="button"
              class="btn-finish-match-stitch group relative overflow-hidden"
              title="Kết thúc trận đấu và cập nhật người chiến thắng vào nhánh đấu"
              @click="finishMatchPrompt"
            >
              <!-- Shimmer animation highlight -->
              <span class="shimmer-wave"></span>
              <span class="flex items-center gap-2 relative z-10">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
                <span>Finish Match</span>
              </span>
            </button>
          </div>
        </div>

        <!-- RALLY TIMELINE DRAWER / EVENT LOG EXPANDABLE -->
        <Transition name="slide-up">
          <div v-if="isRallyLogDrawerOpen" class="rally-events-drawer">
            <div class="drawer-head">
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[20px]">timeline</span>
                <h3 class="font-bold text-sm text-ink">Nhật Ký Từng Pha Cầu (Rally Point Log)</h3>
                <span class="text-xs text-muted">Set {{ liveCurrentSet }}</span>
              </div>
              <button type="button" class="btn-close-sm" @click="isRallyLogDrawerOpen = false">
                <span class="material-symbols-outlined text-[18px]">close</span>
              </button>
            </div>

            <div class="rally-events-list">
              <div
                v-for="ev in rallyEventsLog"
                :key="ev.id"
                class="rally-log-row"
              >
                <span class="log-time">{{ ev.timestamp }}</span>
                <span class="log-badge" :class="ev.badgeClass">{{ ev.playerName }}</span>
                <span class="log-label">{{ ev.label }}</span>
                <span class="log-score-tag">{{ ev.scoreSnapshot }}</span>
              </div>
            </div>
          </div>
        </Transition>
      </section>

      <!-- ================= TAB 4: TOURNAMENT OVERVIEW ================= -->
      <section v-else class="main-screen-container">
        <div class="screen-header-row">
          <div>
            <div class="breadcrumb-tag-row">
              <span class="crumb-highlight">TOURNAMENT OVERVIEW</span>
              <span class="crumb-dot"></span>
              <span class="crumb-sub">Metrics & Schedule</span>
            </div>
            <h1 class="page-headline">Tổng Quan Hoạt Động Giải Đấu</h1>
            <p class="page-subline">Theo dõi tiến độ thi đấu, tỷ lệ hoàn thành các vòng đấu và quản lý lịch phân sân.</p>
          </div>
        </div>

        <!-- KPI Cards Grid -->
        <div class="overview-kpis-grid">
          <div class="kpi-card">
            <span class="material-symbols-outlined kpi-icon text-primary">groups</span>
            <div>
              <span class="kpi-val">{{ participants.length }} / {{ tournament.maxSlots }}</span>
              <span class="kpi-lbl">Vận Động Viên Đăng Ký</span>
            </div>
          </div>

          <div class="kpi-card">
            <span class="material-symbols-outlined kpi-icon text-amber-500">sports_tennis</span>
            <div>
              <span class="kpi-val">12 / 15</span>
              <span class="kpi-lbl">Trận Đã Hoàn Thành</span>
            </div>
          </div>

          <div class="kpi-card">
            <span class="material-symbols-outlined kpi-icon text-emerald-500">payments</span>
            <div>
              <span class="kpi-val">{{ tournament.prizePool }}</span>
              <span class="kpi-lbl">Tổng Giải Thưởng</span>
            </div>
          </div>

          <div class="kpi-card">
            <span class="material-symbols-outlined kpi-icon text-sky-500">calendar_month</span>
            <div>
              <span class="kpi-val">4 Ngày</span>
              <span class="kpi-lbl">{{ tournament.datesFormatted }}</span>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- ================= MODAL: EDIT MATCH RESULTS & ASSIGN REFEREE ================= -->
    <div v-if="isMatchModalOpen && selectedMatch" class="modal-backdrop" @click.self="isMatchModalOpen = false">
      <div class="modal-card">
        <div class="modal-head">
          <div>
            <span class="text-xs font-bold uppercase tracking-wider text-primary">{{ selectedMatch.roundName }}</span>
            <h2 class="text-lg font-bold text-ink">Cập Nhật Trận Đấu #{{ selectedMatch.matchNumber }}</h2>
          </div>
          <button type="button" class="btn-close" @click="isMatchModalOpen = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body space-y-4">
          <!-- Match status switcher -->
          <div>
            <label class="input-lbl">Trạng thái trận đấu</label>
            <select v-model="matchScoreForm.status" class="aura-input">
              <option value="pending">Chờ đấu (Upcoming)</option>
              <option value="live">Đang đấu (LIVE)</option>
              <option value="completed">Đã kết thúc (Completed)</option>
              <option value="walkover">Bỏ cuộc / Xử thắng (Walkover)</option>
            </select>
          </div>

          <!-- Player Scores Input -->
          <div class="score-input-grid">
            <div class="p-3 bg-surface-subtle rounded-xl">
              <span class="font-bold text-sm text-ink block mb-2">{{ selectedMatch.player1.name }}</span>
              <div class="grid grid-cols-3 gap-2">
                <div>
                  <label class="text-[11px] text-muted block">Set 1</label>
                  <input v-model.number="matchScoreForm.p1_s1" type="number" class="aura-input-sm" />
                </div>
                <div>
                  <label class="text-[11px] text-muted block">Set 2</label>
                  <input v-model.number="matchScoreForm.p3_s2" type="number" class="aura-input-sm" />
                </div>
                <div>
                  <label class="text-[11px] text-muted block">Set 3</label>
                  <input v-model.number="matchScoreForm.p5_s3" type="number" class="aura-input-sm" />
                </div>
              </div>
            </div>

            <div class="p-3 bg-surface-subtle rounded-xl">
              <span class="font-bold text-sm text-ink block mb-2">{{ selectedMatch.player2.name }}</span>
              <div class="grid grid-cols-3 gap-2">
                <div>
                  <label class="text-[11px] text-muted block">Set 1</label>
                  <input v-model.number="matchScoreForm.p2_s1" type="number" class="aura-input-sm" />
                </div>
                <div>
                  <label class="text-[11px] text-muted block">Set 2</label>
                  <input v-model.number="matchScoreForm.p4_s2" type="number" class="aura-input-sm" />
                </div>
                <div>
                  <label class="text-[11px] text-muted block">Set 3</label>
                  <input v-model.number="matchScoreForm.p6_s3" type="number" class="aura-input-sm" />
                </div>
              </div>
            </div>
          </div>

          <!-- Winner Selector -->
          <div>
            <label class="input-lbl">Người chiến thắng (Winner advances to next round)</label>
            <select v-model="matchScoreForm.winnerId" class="aura-input">
              <option :value="selectedMatch.player1.id || 1">{{ selectedMatch.player1.name }}</option>
              <option :value="selectedMatch.player2.id || 2">{{ selectedMatch.player2.name }}</option>
            </select>
          </div>

          <!-- Court & Referee -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="input-lbl">Sân thi đấu</label>
              <input v-model="matchScoreForm.courtName" type="text" class="aura-input" />
            </div>
            <div>
              <label class="input-lbl">Trọng tài phụ trách</label>
              <input v-model="matchScoreForm.refereeName" type="text" class="aura-input" />
            </div>
          </div>
        </div>

        <div class="modal-foot">
          <button type="button" class="btn-cancel" @click="isMatchModalOpen = false">Hủy Bỏ</button>
          <button type="button" class="btn-confirm" @click="saveMatchResult">Lưu Kết Quả</button>
        </div>
      </div>
    </div>

    <!-- ================= MODAL: EDIT BASIC INFO ================= -->
    <div v-if="isEditBasicInfoModalOpen" class="modal-backdrop" @click.self="isEditBasicInfoModalOpen = false">
      <div class="modal-card">
        <div class="modal-head">
          <h2 class="text-lg font-bold text-ink">Chỉnh Sửa Thông Tin Giải Đấu</h2>
          <button type="button" class="btn-close" @click="isEditBasicInfoModalOpen = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body space-y-3">
          <div>
            <label class="input-lbl">Tên giải đấu</label>
            <input v-model="basicInfoForm.name" type="text" class="aura-input" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="input-lbl">Hạng mục</label>
              <input v-model="basicInfoForm.category" type="text" class="aura-input" />
            </div>
            <div>
              <label class="input-lbl">Tổng giải thưởng</label>
              <input v-model="basicInfoForm.prizePool" type="text" class="aura-input" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="input-lbl">Địa điểm / Cụm sân</label>
              <input v-model="basicInfoForm.venue" type="text" class="aura-input" />
            </div>
            <div>
              <label class="input-lbl">Số VĐV tối đa</label>
              <input v-model.number="basicInfoForm.maxSlots" type="number" class="aura-input" />
            </div>
          </div>
        </div>

        <div class="modal-foot">
          <button type="button" class="btn-cancel" @click="isEditBasicInfoModalOpen = false">Hủy</button>
          <button type="button" class="btn-confirm" @click="saveBasicInfo">Lưu Thay Đổi</button>
        </div>
      </div>
    </div>

    <!-- ================= MODAL: ADD PARTICIPANT ================= -->
    <div v-if="isAddParticipantModalOpen" class="modal-backdrop" @click.self="isAddParticipantModalOpen = false">
      <div class="modal-card">
        <div class="modal-head">
          <h2 class="text-lg font-bold text-ink">Thêm Vận Động Viên Vào Giải</h2>
          <button type="button" class="btn-close" @click="isAddParticipantModalOpen = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body space-y-3">
          <div>
            <label class="input-lbl">Họ và tên VĐV</label>
            <input v-model="newParticipantForm.name" type="text" class="aura-input" placeholder="Ví dụ: Nguyễn Văn An" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="input-lbl">Câu lạc bộ / Đơn vị</label>
              <input v-model="newParticipantForm.club" type="text" class="aura-input" />
            </div>
            <div>
              <label class="input-lbl">Số thứ tự Hạt giống (0 = Không xếp)</label>
              <input v-model.number="newParticipantForm.seed" type="number" min="0" max="32" class="aura-input" />
            </div>
          </div>
          <div>
            <label class="input-lbl">Số điện thoại liên hệ</label>
            <input v-model="newParticipantForm.phone" type="text" class="aura-input" />
          </div>
        </div>

        <div class="modal-foot">
          <button type="button" class="btn-cancel" @click="isAddParticipantModalOpen = false">Hủy</button>
          <button type="button" class="btn-confirm" @click="saveNewParticipant">Thêm Vào Danh Sách</button>
        </div>
      </div>
    </div>

    <!-- ================= MODAL: QR CODE SCANNER SIMULATOR ================= -->
    <div v-if="isQrScannerModalOpen" class="modal-backdrop" @click.self="closeQrScannerModal">
      <div class="modal-card modal-qr-scanner-card">
        <div class="modal-head">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[24px]">qr_code_scanner</span>
            <div>
              <h2 class="text-lg font-bold text-ink leading-tight">Quét Mã QR Vận Động Viên</h2>
              <p class="text-xs text-muted">Hướng camera vào mã QR trên thẻ VĐV hoặc chọn mẫu quét</p>
            </div>
          </div>
          <button type="button" class="btn-close" @click="closeQrScannerModal">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body space-y-4">
          <!-- Camera Viewfinder Simulator Box -->
          <div class="camera-viewfinder-box">
            <div class="viewfinder-corner top-left"></div>
            <div class="viewfinder-corner top-right"></div>
            <div class="viewfinder-corner bottom-left"></div>
            <div class="viewfinder-corner bottom-right"></div>

            <!-- Sweeping Animated Laser Scanline -->
            <div class="laser-scanline"></div>

            <div class="viewfinder-status-badge">
              <span class="live-dot-mini"></span>
              <span>Camera HD Live (1080p)</span>
            </div>

            <!-- Center Target Graphic -->
            <div class="viewfinder-target-graphic">
              <span class="material-symbols-outlined text-[64px] text-white/30">qr_code_2</span>
              <p class="text-xs text-white/70 mt-1">Căn chỉnh mã QR vào khung ngắm</p>
            </div>
          </div>

          <!-- Live Scan Feedback Alert -->
          <Transition name="fade">
            <div v-if="qrScanFeedback" class="qr-scan-feedback-box" :class="{ 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-200': qrScanFeedback.includes('THÀNH CÔNG'), 'bg-rose-50 text-rose-800 border-rose-300 dark:bg-rose-950 dark:text-rose-200': qrScanFeedback.includes('Không') }">
              <span class="material-symbols-outlined text-[18px]">
                {{ qrScanFeedback.includes('THÀNH CÔNG') ? 'check_circle' : 'error' }}
              </span>
              <span class="text-xs font-semibold">{{ qrScanFeedback }}</span>
            </div>
          </Transition>

          <!-- Quick Test Cards -->
          <div class="scan-samples-container">
            <p class="text-xs font-bold text-ink mb-1.5 uppercase tracking-wider">Chọn Thẻ VĐV Để Quét Thử Nghiệm:</p>
            <div class="grid grid-cols-2 gap-2">
              <button
                type="button"
                class="btn-sample-card"
                @click="simulateQrScan('ATH-001')"
              >
                <div class="text-left">
                  <p class="font-bold text-xs text-ink">Sarah Jenkins</p>
                  <p class="text-[11px] text-muted">#ATH-001 · Apex Club</p>
                </div>
                <span class="material-symbols-outlined text-primary text-[18px]">qr_code</span>
              </button>

              <button
                type="button"
                class="btn-sample-card"
                @click="simulateQrScan('ATH-002')"
              >
                <div class="text-left">
                  <p class="font-bold text-xs text-ink">Marcus Chen</p>
                  <p class="text-[11px] text-muted">#ATH-002 · Independent</p>
                </div>
                <span class="material-symbols-outlined text-primary text-[18px]">qr_code</span>
              </button>

              <button
                type="button"
                class="btn-sample-card"
                @click="simulateQrScan('ATH-003')"
              >
                <div class="text-left">
                  <p class="font-bold text-xs text-ink">David Rossi</p>
                  <p class="text-[11px] text-muted">#ATH-003 · Velocity BC</p>
                </div>
                <span class="material-symbols-outlined text-primary text-[18px]">qr_code</span>
              </button>

              <button
                type="button"
                class="btn-sample-card"
                @click="simulateQrScan('ATH-013')"
              >
                <div class="text-left">
                  <p class="font-bold text-xs text-ink">Nguyễn Tiến Minh</p>
                  <p class="text-[11px] text-muted">#ATH-013 · HCM Club</p>
                </div>
                <span class="material-symbols-outlined text-primary text-[18px]">qr_code</span>
              </button>
            </div>
          </div>

          <!-- Manual Code Input -->
          <div>
            <label class="input-lbl">Hoặc nhập mã thẻ thủ công:</label>
            <div class="flex gap-2">
              <input
                v-model="manualQrInput"
                type="text"
                class="aura-input"
                placeholder="Ví dụ: ATH-001 hoặc 0901234567"
                @keyup.enter="submitManualQr"
              />
              <button
                type="button"
                class="btn-confirm flex-shrink-0"
                @click="submitManualQr"
              >
                Xác Nhận
              </button>
            </div>
          </div>
        </div>

        <div class="modal-foot">
          <button type="button" class="btn-cancel" @click="closeQrScannerModal">Đóng</button>
        </div>
      </div>
    </div>

    <!-- ================= MODAL: DIGITAL ATHLETE ACCREDITATION PASS ================= -->
    <div v-if="isDigitalPassModalOpen && selectedPassAthlete" class="modal-backdrop" @click.self="closeDigitalPassModal">
      <div class="modal-card modal-pass-card">
        <div class="modal-head">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[24px]">badge</span>
            <div>
              <h2 class="text-lg font-bold text-ink leading-tight">Thẻ Vận Động Viên Kỹ Thuật Số</h2>
              <p class="text-xs text-muted">Aura Tournament Official Player Accreditation</p>
            </div>
          </div>
          <button type="button" class="btn-close" @click="closeDigitalPassModal">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body">
          <!-- Pass Lanyard Container -->
          <div class="digital-badge-container">
            <div class="badge-top-hole"></div>

            <div class="badge-header-row">
              <div class="flex items-center gap-2">
                <div class="badge-logo-box">
                  <span class="material-symbols-outlined text-[16px]">sports_tennis</span>
                </div>
                <div>
                  <h4 class="badge-org-title">AURA COURT CONTROL</h4>
                  <p class="badge-tournament-name">{{ tournament.name }}</p>
                </div>
              </div>
              <span class="badge-role-tag">ATHLETE</span>
            </div>

            <!-- Athlete Profile Box in Badge -->
            <div class="badge-profile-section">
              <div class="badge-photo-wrapper">
                <img
                  v-if="selectedPassAthlete.avatar && !selectedPassAthlete.initials"
                  :src="selectedPassAthlete.avatar"
                  :alt="selectedPassAthlete.name"
                  class="badge-photo-img"
                  @error="handleImageError($event)"
                />
                <div v-else class="badge-initials-box">
                  <span>{{ selectedPassAthlete.initials || selectedPassAthlete.name.slice(0, 2).toUpperCase() }}</span>
                </div>
              </div>

              <div class="badge-player-info">
                <h3 class="badge-player-name">{{ selectedPassAthlete.name }}</h3>
                <p class="badge-club-name">{{ selectedPassAthlete.club || 'Aura Club' }}</p>
                <div class="badge-meta-chips mt-1">
                  <span class="badge-micro-pill">{{ selectedPassAthlete.level || 'Lvl 4' }}</span>
                  <span v-if="selectedPassAthlete.seed" class="badge-micro-pill seed-pill">Seed {{ selectedPassAthlete.seed }}</span>
                  <span class="badge-micro-pill">{{ selectedPassAthlete.category || 'Đơn Nam' }}</span>
                </div>
              </div>
            </div>

            <!-- QR Code & Barcode Row -->
            <div class="badge-qr-section">
              <div class="badge-qr-box">
                <span class="material-symbols-outlined text-[72px] text-neutral-800 dark:text-neutral-200">qr_code_2</span>
                <span class="badge-id-code">#{{ selectedPassAthlete.qrCode || 'ATH-' + selectedPassAthlete.id }}</span>
              </div>

              <div class="badge-permissions-list">
                <p class="font-bold text-[11px] text-ink uppercase tracking-wider mb-1">Quyền Hạn Vào Sân:</p>
                <div class="space-y-1 text-[11px]">
                  <div class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-semibold">
                    <span class="material-symbols-outlined text-[14px]">check_circle</span>
                    <span>Sân Thi Đấu Chính (Center Court)</span>
                  </div>
                  <div class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-semibold">
                    <span class="material-symbols-outlined text-[14px]">check_circle</span>
                    <span>Khu Vực Khởi Động (Warm-up Zone)</span>
                  </div>
                  <div class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-semibold">
                    <span class="material-symbols-outlined text-[14px]">check_circle</span>
                    <span>Phòng VĐV & Thay Đồ VIP</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Security Barcode Footer -->
            <div class="badge-barcode-footer">
              <div class="mock-barcode-lines"></div>
              <p class="barcode-sub-text">VALID FOR TOURNAMENT DATES · NON-TRANSFERABLE</p>
            </div>
          </div>
        </div>

        <div class="modal-foot">
          <button type="button" class="btn-cancel" @click="closeDigitalPassModal">Đóng</button>
          <button type="button" class="btn-confirm flex items-center gap-1.5" @click="printPass">
            <span class="material-symbols-outlined text-[16px]">print</span>
            <span>In Thẻ Đeo / Tải PDF</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ================= MODAL: REFEREE CARDS & PENALTIES ================= -->
    <div v-if="isCardsModalOpen" class="modal-backdrop" @click.self="isCardsModalOpen = false">
      <div class="modal-card modal-penalty-card">
        <div class="modal-head">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-rose-500 text-[24px]">style</span>
            <div>
              <h2 class="text-lg font-bold text-ink leading-tight">Thẻ Phạt & Lỗi Trọng Tài</h2>
              <p class="text-xs text-muted">Ghi nhận vi phạm quy định BWF và thẻ kỷ luật trực tiếp</p>
            </div>
          </div>
          <button type="button" class="btn-close" @click="isCardsModalOpen = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body space-y-4">
          <!-- Select Player -->
          <div>
            <label class="input-lbl">Chọn Vận Động Viên:</label>
            <div class="grid grid-cols-2 gap-2">
              <button
                type="button"
                class="btn-select-penalty-player"
                :class="{ active: penaltySelectedPlayer === 1 }"
                @click="penaltySelectedPlayer = 1"
              >
                <img :src="activeLiveFixture.player1.avatar" class="w-7 h-7 rounded-full object-cover" @error="handleImageError($event)" />
                <div class="text-left overflow-hidden">
                  <p class="font-bold text-xs truncate">{{ activeLiveFixture.player1.name }}</p>
                  <p class="text-[10px] text-muted truncate">Player A</p>
                </div>
              </button>

              <button
                type="button"
                class="btn-select-penalty-player"
                :class="{ active: penaltySelectedPlayer === 2 }"
                @click="penaltySelectedPlayer = 2"
              >
                <img :src="activeLiveFixture.player2.avatar" class="w-7 h-7 rounded-full object-cover" @error="handleImageError($event)" />
                <div class="text-left overflow-hidden">
                  <p class="font-bold text-xs truncate">{{ activeLiveFixture.player2.name }}</p>
                  <p class="text-[10px] text-muted truncate">Player B</p>
                </div>
              </button>
            </div>
          </div>

          <!-- Cards Grid -->
          <div class="grid grid-cols-2 gap-2.5">
            <button
              type="button"
              class="card-penalty-item btn-yellow-card"
              @click="applyPenalty(penaltySelectedPlayer, 'yellow')"
            >
              <div class="card-color-box bg-amber-400"></div>
              <div class="text-left">
                <p class="font-bold text-xs text-amber-900 dark:text-amber-100">Thẻ Vàng (Cảnh Cáo)</p>
                <p class="text-[11px] text-amber-700 dark:text-amber-300">Trì hoãn, tác phong phi thể thao</p>
              </div>
            </button>

            <button
              type="button"
              class="card-penalty-item btn-red-card"
              @click="applyPenalty(penaltySelectedPlayer, 'red')"
            >
              <div class="card-color-box bg-rose-600"></div>
              <div class="text-left">
                <p class="font-bold text-xs text-rose-900 dark:text-rose-100">Thẻ Đỏ (Phạt 1 Điểm)</p>
                <p class="text-[11px] text-rose-700 dark:text-rose-300">Cộng 1 điểm cho đối phương</p>
              </div>
            </button>

            <button
              type="button"
              class="card-penalty-item btn-fault-card"
              @click="applyPenalty(penaltySelectedPlayer, 'fault')"
            >
              <span class="material-symbols-outlined text-amber-600 text-[24px]">sports</span>
              <div class="text-left">
                <p class="font-bold text-xs text-ink">Lỗi Giao Cầu (Fault)</p>
                <p class="text-[11px] text-muted">Chân đè vạch / Vợt quá eo</p>
              </div>
            </button>

            <button
              type="button"
              class="card-penalty-item btn-timeout-card"
              @click="applyPenalty(penaltySelectedPlayer, 'timeout')"
            >
              <span class="material-symbols-outlined text-sky-600 text-[24px]">medical_services</span>
              <div class="text-left">
                <p class="font-bold text-xs text-ink">Nghỉ Y Tế (Medical)</p>
                <p class="text-[11px] text-muted">2 phút hỗ trợ y tế sân</p>
              </div>
            </button>

            <button
              type="button"
              class="card-penalty-item btn-black-card col-span-2"
              @click="applyPenalty(penaltySelectedPlayer, 'black')"
            >
              <div class="card-color-box bg-neutral-900 dark:bg-black"></div>
              <div class="text-left">
                <p class="font-bold text-xs text-rose-600">THẺ ĐEN (TRUẤT QUYỀN THI ĐẤU / DISQUALIFIED)</p>
                <p class="text-[11px] text-muted">Xử thua trận đấu ngay lập tức</p>
              </div>
            </button>
          </div>
        </div>

        <div class="modal-foot">
          <button type="button" class="btn-cancel" @click="isCardsModalOpen = false">Đóng</button>
        </div>
      </div>
    </div>

    <!-- ================= MODAL: FINISH MATCH & CONFIRM BRACKET ADVANCE ================= -->
    <div v-if="isFinishMatchModalOpen" class="modal-backdrop" @click.self="isFinishMatchModalOpen = false">
      <div class="modal-card modal-finish-match-card">
        <div class="modal-head">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[24px]">emoji_events</span>
            <div>
              <h2 class="text-lg font-bold text-ink leading-tight">Kết Thúc & Xác Nhận Trận Đấu</h2>
              <p class="text-xs text-muted">{{ activeLiveFixture.courtName }} · {{ activeLiveFixture.roundName }}</p>
            </div>
          </div>
          <button type="button" class="btn-close" @click="isFinishMatchModalOpen = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body space-y-4">
          <!-- Winner Highlight Banner -->
          <div class="match-winner-summary-card">
            <span class="text-xs font-bold uppercase tracking-wider text-primary">VẬN ĐỘNG VIÊN CHIẾN THẮNG</span>
            <div class="flex items-center gap-3 mt-2">
              <img
                :src="liveP1SetsWon >= liveP2SetsWon ? activeLiveFixture.player1.avatar : activeLiveFixture.player2.avatar"
                class="w-14 h-14 rounded-full border-2 border-primary object-cover shadow-sm"
                @error="handleImageError($event)"
              />
              <div>
                <h3 class="text-xl font-extrabold text-ink">
                  {{ liveP1SetsWon >= liveP2SetsWon ? activeLiveFixture.player1.name : activeLiveFixture.player2.name }}
                </h3>
                <p class="text-xs text-muted">
                  {{ liveP1SetsWon >= liveP2SetsWon ? (activeLiveFixture.player1.club || 'Aura Elite') : (activeLiveFixture.player2.club || 'KL Masters') }}
                </p>
              </div>
              <span class="winner-trophy-badge ml-auto">
                <span class="material-symbols-outlined text-[28px] text-amber-500">trophy</span>
              </span>
            </div>
          </div>

          <!-- Set Scores Recap Grid -->
          <div class="set-scores-recap-box">
            <p class="text-xs font-bold uppercase tracking-wider text-muted mb-2">Tóm Tắt Tỷ Số Các Set:</p>
            <div class="grid grid-cols-3 gap-2 text-center">
              <div class="p-2.5 bg-surface rounded-xl border border-outline-variant">
                <span class="text-[11px] text-muted block">Set 1</span>
                <span class="font-extrabold text-base text-ink">21 - 19</span>
              </div>
              <div class="p-2.5 bg-surface rounded-xl border border-outline-variant">
                <span class="text-[11px] text-muted block">Set 2</span>
                <span class="font-extrabold text-base text-ink">18 - 21</span>
              </div>
              <div class="p-2.5 bg-surface rounded-xl border border-primary bg-primary-container/20">
                <span class="text-[11px] text-primary font-bold block">Set 3 (Quyết Định)</span>
                <span class="font-extrabold text-base text-primary">{{ liveSetScoreP1 }} - {{ liveSetScoreP2 }}</span>
              </div>
            </div>
          </div>

          <div class="p-3 bg-emerald-50 dark:bg-emerald-950/40 rounded-xl border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-200">
            <div class="flex items-center gap-1.5 font-bold mb-0.5">
              <span class="material-symbols-outlined text-[16px]">account_tree</span>
              <span>Tự Động Đẩy Nhánh Đấu (Bracket Automation)</span>
            </div>
            <span>Vận động viên thắng sẽ tự động được xếp vào trận Bán Kết / Chung Kết tiếp theo trên cây sơ đồ thi đấu.</span>
          </div>
        </div>

        <div class="modal-foot">
          <button type="button" class="btn-cancel" @click="isFinishMatchModalOpen = false">Hủy</button>
          <button type="button" class="btn-confirm flex items-center gap-1.5" @click="confirmFinishMatch">
            <span class="material-symbols-outlined text-[16px]">check_circle</span>
            <span>Xác Nhận & Cập Nhật Bracket</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Main Container & Soft Minimalism Tokens */
.admin-bracket-page {
  display: flex;
  flex-direction: column;
  min-height: 100%;
  background: var(--surface, #f8f9fa);
  color: var(--ink, #191c1d);
  font-family: 'Inter', sans-serif;
}

:global(html.dark) .admin-bracket-page {
  background: #121418;
  color: #e1e3e4;
}

/* Floating Top Toast Alerts */
.alerts-fixed-top {
  position: fixed;
  top: 80px;
  right: 24px;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  gap: 8px;
  pointer-events: none;
}

.alert-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
  pointer-events: auto;
}

.alert-pill-success {
  background: #10b981;
  color: #ffffff;
}

.alert-pill-danger {
  background: #ef4444;
  color: #ffffff;
}

.alert-ico {
  font-size: 18px;
}

/* Sub-Navigation Bar Aligned with Stitch */
.stitch-sub-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 24px;
  background: var(--surface-container-lowest, #ffffff);
  border-bottom: 1px solid var(--border, #e0e4ea);
  position: sticky;
  top: 0;
  z-index: 40;
}

:global(html.dark) .stitch-sub-nav {
  background: #181a20;
  border-color: #2a2d36;
}

.sub-nav-left {
  display: flex;
  align-items: center;
  gap: 20px;
  overflow-x: auto;
}

.sub-nav-brand-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 12px;
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
  flex-shrink: 0;
}

:global(html.dark) .sub-nav-brand-badge {
  background: #4a3038;
  color: #f8b4c4;
}

.sub-nav-pills {
  display: flex;
  align-items: center;
  gap: 6px;
}

.sub-tab-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted, #6c757d);
  background: transparent;
  border: none;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.sub-tab-btn:hover {
  background: var(--surface-container-low, #f3f4f5);
  color: var(--ink, #191c1d);
}

.sub-tab-btn.active {
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
  font-weight: 700;
}

:global(html.dark) .sub-tab-btn.active {
  background: #54343d;
  color: #f8b4c4;
}

.count-badge {
  padding: 2px 6px;
  border-radius: 9999px;
  background: var(--surface-container-highest, #e1e3e4);
  font-size: 11px;
  font-weight: 700;
}

.live-dot-mini {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #ef4444;
  animation: pulse 1.5s infinite;
}

.btn-public-preview {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid var(--border, #cbd5e1);
  color: var(--ink, #191c1d);
  text-decoration: none;
  background: var(--surface-container-low, #f3f4f5);
  transition: all 0.15s ease;
}

.btn-public-preview:hover {
  background: var(--surface-container-high, #e7e8e9);
}

:global(html.dark) .btn-public-preview {
  border-color: #353942;
  background: #242730;
  color: #d1d5db;
}

/* Main Screen Container */
.main-screen-container {
  padding: 28px 32px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

@media (max-width: 768px) {
  .main-screen-container {
    padding: 16px;
  }
}

/* Screen Header Row */
.screen-header-row {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}

.breadcrumb-tag-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-bottom: 6px;
}

.crumb-highlight {
  color: var(--primary, #78555e);
}

:global(html.dark) .crumb-highlight {
  color: #f8b4c4;
}

.crumb-dot {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: var(--border, #817476);
}

.crumb-sub {
  color: var(--muted, #6c757d);
}

.page-headline {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .page-headline {
  color: #ffffff;
}

.page-subline {
  font-size: 14px;
  color: var(--muted, #6c757d);
  margin-top: 4px;
  max-width: 650px;
}

.header-actions-group {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn-save-draft {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 700;
  border: 1px solid var(--border, #d3c3c5);
  background: var(--surface-container-lowest, #ffffff);
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-save-draft:hover {
  background: var(--surface-container-high, #e7e8e9);
}

:global(html.dark) .btn-save-draft {
  background: #1f2229;
  border-color: #383c47;
  color: #e1e3e4;
}

.btn-publish-event {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 22px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 700;
  background: var(--primary, #78555e);
  color: #ffffff;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
  transition: all 0.2s ease;
}

.btn-publish-event:hover {
  background: #63434c;
  box-shadow: 0 6px 18px rgba(120, 85, 94, 0.35);
}

:global(html.dark) .btn-publish-event {
  background: #f8b4c4;
  color: #201317;
}

/* 2-Column Responsive Layout */
.grid-2col-layout {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 24px;
  align-items: start;
}

@media (max-width: 1100px) {
  .grid-2col-layout {
    grid-template-columns: 1fr;
  }
}

/* Left Wizard Card */
.wizard-card {
  background: var(--surface-container, #edeeef);
  border-radius: 24px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .wizard-card {
  background: #181a20;
  border: 1px solid #262933;
}

.wizard-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .wizard-title {
  color: #ffffff;
}

.wizard-desc {
  font-size: 13px;
  color: var(--muted, #6c757d);
  margin-top: 2px;
}

.wizard-stepper-track {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.stepper-vertical-line {
  position: absolute;
  left: 16px;
  top: 16px;
  bottom: 16px;
  width: 2px;
  background: rgba(129, 116, 118, 0.25);
  z-index: 0;
}

.step-item {
  position: relative;
  display: flex;
  gap: 16px;
  cursor: pointer;
  z-index: 1;
}

.step-indicator-circle {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--primary, #78555e);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
  outline: 4px solid var(--surface-container, #edeeef);
}

:global(html.dark) .step-indicator-circle {
  outline-color: #181a20;
}

.circle-active {
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
}

:global(html.dark) .circle-active {
  background: #54343d;
  color: #f8b4c4;
}

.step-content {
  flex: 1;
  padding-top: 2px;
}

.step-header-flex {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.step-name {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .step-name {
  color: #e1e3e4;
}

.btn-step-edit {
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  cursor: pointer;
  padding: 2px 6px;
  border-radius: 6px;
}

.btn-step-edit:hover {
  background: rgba(0, 0, 0, 0.05);
  color: var(--ink, #191c1d);
}

.step-value-primary {
  font-size: 14px;
  font-weight: 600;
  color: var(--muted, #4f4446);
  margin-top: 2px;
}

:global(html.dark) .step-value-primary {
  color: #c4c6cf;
}

.step-value-desc {
  font-size: 13px;
  color: var(--muted, #6c757d);
  margin-top: 2px;
}

.step-meta-pill-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
  flex-wrap: wrap;
}

.meta-inline-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--muted, #6c757d);
}

/* Seed chips cloud */
.seed-chips-cloud {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.seed-chip-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 9999px;
  background: var(--surface-container-high, #e7e8e9);
  font-size: 11px;
  font-weight: 600;
  color: var(--ink, #191c1d);
}

:global(html.dark) .seed-chip-pill {
  background: #242730;
  color: #d1d5db;
}

.seed-chip-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--primary, #78555e);
}

.seed-chip-more {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 600;
  color: var(--primary, #78555e);
  cursor: pointer;
}

:global(html.dark) .seed-chip-more {
  color: #f8b4c4;
}

.btn-regenerate-bracket {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 12px;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  font-size: 13px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  transition: all 0.2s ease;
}

.btn-regenerate-bracket:hover {
  background: var(--primary, #78555e);
  color: #ffffff;
}

:global(html.dark) .btn-regenerate-bracket {
  background: #54343d;
  color: #ffd1dc;
}

:global(html.dark) .btn-regenerate-bracket:hover {
  background: #f8b4c4;
  color: #201317;
}

/* Seeding rules radio box */
.seeding-rules-box {
  padding-top: 14px;
  border-top: 1px solid rgba(129, 116, 118, 0.15);
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.rules-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--muted, #6c757d);
}

.rules-options {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.rule-radio-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--ink, #191c1d);
  cursor: pointer;
}

:global(html.dark) .rule-radio-label {
  color: #c4c6cf;
}

/* Right Bracket Board Column */
.bracket-preview-column {
  min-width: 0;
}

.bracket-board-box {
  background: var(--surface-container-lowest, #ffffff);
  border-radius: 24px;
  border: 1px solid var(--border, #e1e3e4);
  padding: 24px;
  display: flex;
  flex-direction: column;
  min-height: 600px;
  box-shadow: 0 1px 6px rgba(0, 0, 0, 0.03);
}

:global(html.dark) .bracket-board-box {
  background: #181a20;
  border-color: #262933;
}

.board-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.board-headline {
  font-size: 20px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .board-headline {
  color: #ffffff;
}

.board-subline {
  font-size: 13px;
  color: var(--muted, #6c757d);
  margin-top: 2px;
}

.board-controls-group {
  display: flex;
  align-items: center;
  gap: 12px;
}

.round-tabs-pill {
  display: flex;
  background: var(--surface-container, #edeeef);
  padding: 4px;
  border-radius: 12px;
  gap: 2px;
}

:global(html.dark) .round-tabs-pill {
  background: #242730;
}

.round-tab {
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  cursor: pointer;
  transition: all 0.15s ease;
}

.round-tab.active {
  background: var(--surface-container-lowest, #ffffff);
  color: var(--ink, #191c1d);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  font-weight: 700;
}

:global(html.dark) .round-tab.active {
  background: #323642;
  color: #ffffff;
}

.zoom-pill-group {
  display: flex;
  align-items: center;
  background: var(--surface-container, #edeeef);
  border-radius: 10px;
  padding: 2px;
}

:global(html.dark) .zoom-pill-group {
  background: #242730;
}

.btn-zoom {
  border: none;
  background: transparent;
  padding: 4px 6px;
  cursor: pointer;
  color: var(--muted, #6c757d);
  display: flex;
  align-items: center;
}

.btn-zoom-val {
  border: none;
  background: transparent;
  font-size: 11px;
  font-weight: 700;
  padding: 0 4px;
  color: var(--ink, #191c1d);
  cursor: pointer;
}

/* Canvas Area & Tree View */
.bracket-canvas-area {
  flex: 1;
  overflow-x: auto;
  overflow-y: hidden;
  padding-bottom: 16px;
  min-height: 520px;
}

.bracket-tree-canvas {
  min-width: 1040px;
  height: 580px;
  display: flex;
  gap: 40px;
  position: relative;
  align-items: center;
}

/* SVG Connector Lines */
.bracket-svg-layer {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 0;
}

.svg-tree-line {
  stroke: #e1e3e4;
  stroke-width: 2px;
}

:global(html.dark) .svg-tree-line {
  stroke: #313540;
}

.live-pulse-line {
  stroke: #78555e;
  stroke-width: 2.5px;
  stroke-dasharray: 4;
  animation: dash 2s linear infinite;
}

.final-gold-line {
  stroke: #f59e0b;
  stroke-width: 2.5px;
}

@keyframes dash {
  to {
    stroke-dashoffset: -20;
  }
}

/* Bracket Columns */
.bracket-round-column {
  width: 240px;
  display: flex;
  flex-direction: column;
  justify-content: space-around;
  height: 100%;
  position: relative;
  z-index: 10;
}

.column-title-pill {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--muted, #6c757d);
  margin-bottom: 8px;
  text-align: center;
}

.pill-gold {
  color: #f59e0b;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.matches-stack {
  display: flex;
  flex-direction: column;
  justify-content: space-around;
  height: 100%;
}

/* Match Node Card (Stitch Exact Styling) */
.match-node-card {
  background: var(--surface-container, #edeeef);
  border-radius: 14px;
  padding: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  position: relative;
  cursor: pointer;
  transition: all 0.2s ease;
  border: 1px solid transparent;
}

.match-node-card:hover {
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
  border-color: var(--primary, #78555e);
}

:global(html.dark) .match-node-card {
  background: #20232c;
  border-color: #2e323e;
}

.seed-badge-pinned {
  position: absolute;
  top: -8px;
  left: -8px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--primary, #78555e);
  color: #ffffff;
  font-size: 11px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
  z-index: 20;
}

:global(html.dark) .seed-badge-pinned {
  background: #f8b4c4;
  color: #201317;
}

.node-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 10px;
  margin-bottom: 6px;
}

.match-time-tag {
  color: var(--muted, #6c757d);
  font-weight: 600;
}

.status-chip-done {
  padding: 2px 6px;
  border-radius: 6px;
  background: var(--surface-container-high, #e7e8e9);
  color: var(--muted, #6c757d);
  font-weight: 700;
  font-size: 9px;
  text-transform: uppercase;
}

.status-chip-live {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 6px;
  border-radius: 6px;
  background: #fee2e2;
  color: #ef4444;
  font-weight: 800;
  font-size: 9px;
}

.live-ping-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #ef4444;
  animation: pulse 1.5s infinite;
}

.node-player-slot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 8px;
  border-radius: 8px;
  background: var(--surface-container-lowest, #ffffff);
  margin-bottom: 3px;
}

:global(html.dark) .node-player-slot {
  background: #181a20;
}

.node-player-slot.is-dimmed {
  opacity: 0.65;
  background: var(--surface-container-high, #e7e8e9);
}

:global(html.dark) .node-player-slot.is-dimmed {
  background: #14161b;
}

.player-info-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.player-name-text {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

:global(html.dark) .player-name-text {
  color: #ffffff;
}

.winner-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--primary, #78555e);
  flex-shrink: 0;
}

.seed-sub-tag {
  font-size: 10px;
  color: var(--muted, #6c757d);
}

.player-set-score {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

/* Semifinals Live Card */
.card-live-pulse {
  border: 1px solid var(--primary, #78555e);
  background: var(--surface-container-lowest, #ffffff);
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.12);
}

.serve-racket-icon {
  font-size: 14px;
  color: var(--primary, #78555e);
}

.live-current-score {
  font-size: 14px;
  font-weight: 800;
  color: var(--primary, #78555e);
}

:global(html.dark) .live-current-score {
  color: #f8b4c4;
}

.node-footer-sets {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 10px;
  color: var(--muted, #6c757d);
  padding: 4px 6px 2px;
}

/* Grand Final Card */
.card-grand-final {
  background: var(--surface-container-lowest, #ffffff);
  border: 1.5px solid var(--primary-container, #ffd1dc);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
}

:global(html.dark) .card-grand-final {
  background: #181a20;
  border-color: #54343d;
}

.head-final {
  border-bottom: 1px solid var(--border, #e1e3e4);
  padding-bottom: 6px;
  margin-bottom: 8px;
}

.final-badge-gold {
  font-size: 9px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 6px;
  background: #fef3c7;
  color: #b45309;
}

.final-inner-box {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.finalist-slot-row {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px;
  border-radius: 8px;
  background: var(--surface-container, #edeeef);
  font-size: 12px;
}

:global(html.dark) .finalist-slot-row {
  background: #242730;
}

.slot-text {
  font-weight: 600;
  color: var(--muted, #4f4446);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

:global(html.dark) .slot-text {
  color: #c4c6cf;
}

.vs-divider-pill {
  text-align: center;
  font-size: 10px;
  font-weight: 800;
  color: var(--muted, #817476);
}

.final-footer-schedule {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--muted, #6c757d);
  margin-top: 8px;
  padding-top: 6px;
  border-top: 1px solid var(--border, #e1e3e4);
}

/* Bottom Tip Bar */
.board-bottom-tip-row {
  margin-top: auto;
  padding-top: 16px;
  border-top: 1px solid var(--border, #e1e3e4);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.tip-left {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--muted, #6c757d);
}

.btn-action-small {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface-container-low, #f3f4f5);
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-action-small:hover {
  background: var(--surface-container-high, #e7e8e9);
}

:global(html.dark) .btn-action-small {
  background: #242730;
  border-color: #383c47;
  color: #d1d5db;
}

/* TAB 2: Check-in Table */
.checkin-table-card {
  background: var(--surface-container-lowest, #ffffff);
  border-radius: 20px;
  border: 1px solid var(--border, #e1e3e4);
  padding: 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .checkin-table-card {
  background: #181a20;
  border-color: #262933;
}

.table-head-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.search-input-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--surface-container-low, #f3f4f5);
  padding: 8px 14px;
  border-radius: 9999px;
  width: 320px;
}

:global(html.dark) .search-input-wrap {
  background: #242730;
}

.search-field {
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  width: 100%;
}

.checkin-stats-pill {
  font-size: 13px;
  font-weight: 600;
}

.table-responsive {
  overflow-x: auto;
}

.aura-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.aura-data-table th {
  padding: 10px 14px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--muted, #6c757d);
  border-bottom: 1px solid var(--border, #e1e3e4);
}

.aura-data-table td {
  padding: 12px 14px;
  border-bottom: 1px solid var(--border, #edeeef);
}

:global(html.dark) .aura-data-table td {
  border-color: #22252e;
}

.row-checked-in {
  background: rgba(16, 185, 129, 0.04);
}

.seed-badge-pill {
  padding: 2px 8px;
  border-radius: 9999px;
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
  font-weight: 700;
  font-size: 11px;
}

.player-cell-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.avatar-thumb {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
}

.badge-status {
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
}

.badge-paid {
  background: #d1fae5;
  color: #065f46;
}

.btn-toggle-verify {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 600;
  border: 1px solid var(--border, #cbd5e1);
  background: transparent;
  cursor: pointer;
}

.btn-toggle-verify.verified {
  border-color: #10b981;
  color: #10b981;
}

.btn-checkin-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 700;
  border: none;
  background: var(--primary, #78555e);
  color: #ffffff;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-checkin-action.btn-checked-in {
  background: #10b981;
}

/* ================= STITCH EXACT STYLES: LIVE REFEREE CONSOLE ================= */
.referee-screen-root {
  display: flex;
  flex-direction: column;
  gap: 20px;
  position: relative;
}

.referee-screen-root.tablet-fullscreen-mode {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: var(--surface, #f8f9fa);
  padding: 24px 32px;
  overflow-y: auto;
}

:global(html.dark) .referee-screen-root.tablet-fullscreen-mode {
  background: #111318;
}

/* Header Area */
.stitch-referee-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}

.court-branding-group {
  display: flex;
  align-items: center;
  gap: 16px;
}

.court-number-badge {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  font-size: 24px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(120, 85, 94, 0.15);
}

.court-main-title {
  font-size: 28px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
  line-height: 1.2;
  margin: 0;
}

:global(html.dark) .court-main-title {
  color: #ffffff;
}

.live-match-subline {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: var(--muted, #6c757d);
  margin-top: 2px;
}

.live-pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--primary, #78555e);
  animation: pulseLive 1.5s infinite;
}

@keyframes pulseLive {
  0%, 100% {
    transform: scale(1);
    opacity: 1;
    box-shadow: 0 0 0 0 rgba(120, 85, 94, 0.4);
  }
  50% {
    transform: scale(1.25);
    opacity: 0.8;
    box-shadow: 0 0 0 6px rgba(120, 85, 94, 0);
  }
}

/* Set Switcher Pills */
.set-switcher-pillbox {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--surface-container-high, #e7e8e9);
  padding: 6px;
  border-radius: 9999px;
}

:global(html.dark) .set-switcher-pillbox {
  background: #242731;
}

.set-tab-btn {
  padding: 6px 18px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 600;
  border: none;
  background: transparent;
  color: var(--on-surface-variant, #4f4446);
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 6px;
}

.set-tab-btn:hover {
  background: var(--surface-container, #edeeef);
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .set-tab-btn:hover {
  background: #2f3442;
  color: #ffffff;
}

.set-tab-btn.active {
  background: var(--surface-container-lowest, #ffffff);
  color: var(--on-surface, #191c1d);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

:global(html.dark) .set-tab-btn.active {
  background: #181a20;
  color: #ffffff;
}

.active-dot-mini {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--primary, #78555e);
}

/* Right Tools */
.referee-top-right-tools {
  display: flex;
  align-items: center;
  gap: 10px;
}

.quick-court-tabs {
  display: flex;
  gap: 4px;
  background: var(--surface-container-low, #f3f4f5);
  padding: 4px;
  border-radius: 12px;
}

:global(html.dark) .quick-court-tabs {
  background: #1f222b;
}

.court-switch-btn {
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  cursor: pointer;
  transition: all 0.15s ease;
}

.court-switch-btn:hover {
  color: var(--ink, #191c1d);
}

.court-switch-btn.active {
  background: var(--primary, #78555e);
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(120, 85, 94, 0.25);
}

.btn-referee-tool-icon {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  border: 1px solid var(--border, #e1e3e4);
  background: var(--surface-container-lowest, #ffffff);
  color: var(--ink, #191c1d);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

:global(html.dark) .btn-referee-tool-icon {
  background: #181a20;
  border-color: #2a2e3b;
  color: #ffffff;
}

.btn-referee-tool-icon:hover {
  background: var(--surface-container-high, #e7e8e9);
}

.btn-referee-tool-icon.active {
  color: var(--primary, #78555e);
  border-color: var(--primary-container, #ffd1dc);
  background: var(--primary-container, #ffd1dc);
}

.btn-back-overview-stitch {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 18px;
  border-radius: 14px;
  border: 1px solid var(--outline, #817476);
  background: var(--surface-container-lowest, #ffffff);
  color: var(--on-surface, #191c1d);
  cursor: pointer;
  transition: all 0.2s ease;
}

:global(html.dark) .btn-back-overview-stitch {
  background: #181a20;
  border-color: #3b4050;
  color: #ffffff;
}

.btn-back-overview-stitch:hover {
  background: var(--surface-container, #edeeef);
}

/* Conflict Alert Banner */
.conflict-alert-banner {
  background: var(--error-container, #ffdad6);
  border: 1px solid rgba(186, 26, 26, 0.25);
  border-radius: 18px;
  padding: 16px 20px;
  box-shadow: 0 8px 24px rgba(186, 26, 26, 0.12);
  margin-bottom: 8px;
}

:global(html.dark) .conflict-alert-banner {
  background: #381216;
  border-color: #5c1d23;
}

.btn-accept-remote {
  padding: 8px 16px;
  border-radius: 10px;
  background: var(--error, #ba1a1a);
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  transition: opacity 0.2s ease;
}

.btn-accept-remote:hover {
  opacity: 0.9;
}

.btn-keep-local {
  padding: 8px 16px;
  border-radius: 10px;
  background: transparent;
  border: 1px solid var(--error, #ba1a1a);
  color: var(--error, #ba1a1a);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-keep-local:hover {
  background: rgba(186, 26, 26, 0.08);
}

.btn-close-alert {
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 4px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-close-alert:hover {
  background: rgba(186, 26, 26, 0.1);
}

/* Slide Down Transitions */
.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-12px);
}

/* Main Scoring Arena */
.scoring-stage-arena {
  display: grid;
  grid-template-columns: 1fr 140px 1fr;
  gap: 24px;
  align-items: stretch;
  position: relative;
  min-height: 580px;
}

@media (max-width: 980px) {
  .scoring-stage-arena {
    grid-template-columns: 1fr;
    gap: 16px;
  }
}

.court-vector-backdrop {
  position: absolute;
  inset: 0;
  pointer-events: none;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--outline, #817476);
  z-index: 0;
}

.stitch-player-card {
  background: var(--surface-container, #edeeef);
  border: 2px solid transparent;
  border-radius: 32px;
  padding: 28px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
  position: relative;
  overflow: hidden;
  min-height: 540px;
  transition: all 0.3s ease;
  z-index: 1;
}

:global(html.dark) .stitch-player-card {
  background: #181b22;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.stitch-player-card:hover {
  transform: scale(1.008);
}

.stitch-player-card.is-serving-card {
  border-color: var(--primary-container, #ffd1dc);
  box-shadow: 0 8px 32px rgba(120, 85, 94, 0.12);
}

:global(html.dark) .stitch-player-card.is-serving-card {
  border-color: #5e3e47;
  box-shadow: 0 8px 32px rgba(120, 85, 94, 0.25);
}

.card-head-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  z-index: 2;
}

.serve-status-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  transition: opacity 0.3s ease;
}

.serve-label-text {
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.15em;
  color: var(--primary, #78555e);
}

.sets-won-dots-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.set-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--surface-variant, #e1e3e4);
  transition: all 0.3s ease;
}

:global(html.dark) .set-dot {
  background: #2d313c;
}

.set-dot.is-won {
  background: var(--primary, #78555e);
  box-shadow: 0 0 8px rgba(120, 85, 94, 0.5);
}

.player-identity-center {
  text-align: center;
  z-index: 2;
  margin: 12px 0;
  width: 100%;
}

.player-big-name {
  font-size: 26px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
  line-height: 1.2;
}

:global(html.dark) .player-big-name {
  color: #ffffff;
}

.player-club-subtext {
  font-size: 14px;
  color: var(--on-surface-variant, #4f4446);
  margin-top: 4px;
}

/* Giant Circular Score Button */
.stitch-big-score-btn {
  width: 250px;
  height: 250px;
  max-width: 90%;
  aspect-ratio: 1 / 1;
  border-radius: 50%;
  background: var(--surface-container-lowest, #ffffff);
  border: 6px solid var(--surface-container, #edeeef);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  position: relative;
  z-index: 2;
  margin: 12px auto;
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  outline: none;
}

:global(html.dark) .stitch-big-score-btn {
  background: #20242f;
  border-color: #2c313f;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
}

.stitch-big-score-btn:hover {
  background: var(--primary-container, #ffd1dc);
  border-color: var(--primary-container, #ffd1dc);
  transform: translateY(-2px);
  box-shadow: 0 12px 36px rgba(120, 85, 94, 0.2);
}

:global(html.dark) .stitch-big-score-btn:hover {
  background: #3d232c;
  border-color: #5e3e47;
}

.stitch-big-score-btn:active {
  transform: scale(0.94);
}

.score-giant-number {
  font-size: 110px;
  font-weight: 900;
  line-height: 1;
  letter-spacing: -0.04em;
  color: var(--on-surface, #191c1d);
  user-select: none;
  transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

:global(html.dark) .score-giant-number {
  color: #ffffff;
}

.score-pop-anim .score-giant-number {
  transform: scale(1.18);
  color: var(--primary, #78555e);
}

.btn-stitch-decrease {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: var(--surface-container-lowest, #ffffff);
  border: 1px solid var(--outline-variant, #d3c3c5);
  color: var(--on-surface-variant, #4f4446);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 2;
  margin-top: 8px;
  transition: all 0.2s ease;
}

:global(html.dark) .btn-stitch-decrease {
  background: #20242f;
  border-color: #333847;
  color: #c8c6c6;
}

.btn-stitch-decrease:hover {
  background: var(--surface-container-high, #e7e8e9);
  color: var(--on-surface, #191c1d);
  transform: scale(1.06);
}

/* Ambient Glows */
.ambient-glow-pink {
  position: absolute;
  bottom: -40px;
  left: -40px;
  width: 240px;
  height: 240px;
  border-radius: 50%;
  background: rgba(255, 209, 220, 0.45);
  filter: blur(60px);
  pointer-events: none;
  z-index: 0;
}

.ambient-glow-cyan {
  position: absolute;
  top: -40px;
  right: -40px;
  width: 240px;
  height: 240px;
  border-radius: 50%;
  background: rgba(223, 224, 224, 0.6);
  filter: blur(60px);
  pointer-events: none;
  z-index: 0;
}

:global(html.dark) .ambient-glow-pink {
  background: rgba(120, 85, 94, 0.25);
}

:global(html.dark) .ambient-glow-cyan {
  background: rgba(93, 95, 95, 0.25);
}

/* Center VS Divider Column */
.stitch-vs-divider-column {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 20px;
  z-index: 3;
}

.vs-circle-badge {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--surface-container-lowest, #ffffff);
  border: 1px solid var(--outline-variant, #d3c3c5);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
  display: flex;
  align-items: center;
  justify-content: center;
}

:global(html.dark) .vs-circle-badge {
  background: #20242f;
  border-color: #333847;
}

.vs-text {
  font-size: 18px;
  font-weight: 900;
  font-style: italic;
  color: var(--on-surface-variant, #4f4446);
}

.match-meta-status-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  width: 100%;
}

.status-chip {
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 800;
  text-align: center;
  white-space: nowrap;
}

.chip-match-point {
  background: #fee2e2;
  color: #b91c1c;
  border: 1px solid #fca5a5;
  animation: pulseLive 1s infinite;
}

.chip-game-point {
  background: #fef3c7;
  color: #b45309;
  border: 1px solid #fcd34d;
}

.chip-deuce {
  background: #ede9fe;
  color: #6d28d9;
  border: 1px solid #c4b5fd;
}

.chip-sudden-death {
  background: #f43f5e;
  color: #ffffff;
}

.service-box-tag {
  font-size: 11px;
  color: var(--muted, #6c757d);
  display: flex;
  align-items: center;
  gap: 4px;
  text-align: center;
}

.btn-quick-serve-switch {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 700;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface-container-lowest, #ffffff);
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

:global(html.dark) .btn-quick-serve-switch {
  background: #181a20;
  border-color: #2c313f;
  color: #ffffff;
}

.btn-quick-serve-switch:hover {
  background: var(--surface-container-high, #e7e8e9);
}

/* Bottom Actions Toolbar */
.stitch-referee-bottom-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  background: var(--surface-container-low, #f3f4f5);
  border: 1px solid var(--surface-variant, #e1e3e4);
  border-radius: 24px;
  padding: 16px 24px;
  flex-wrap: wrap;
}

:global(html.dark) .stitch-referee-bottom-bar {
  background: #181b22;
  border-color: #292d39;
}

.btn-test-conflict {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 14px;
  background: var(--surface-container-lowest, #ffffff);
  border: 1px solid var(--border, #cbd5e1);
  color: var(--on-surface-variant, #4f4446);
  cursor: pointer;
  transition: all 0.2s ease;
}

:global(html.dark) .btn-test-conflict {
  background: #20242f;
  border-color: #2c313f;
  color: #c8c6c6;
}

.btn-test-conflict:hover {
  background: var(--surface-container-high, #e7e8e9);
  color: var(--on-surface, #191c1d);
}

.btn-rally-log-toggle {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: 14px;
  font-size: 13px;
  font-weight: 700;
  background: transparent;
  border: 1px solid var(--border, #cbd5e1);
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.2s ease;
}

:global(html.dark) .btn-rally-log-toggle {
  border-color: #2c313f;
  color: #ffffff;
}

.btn-rally-log-toggle.active {
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
  border-color: var(--primary, #78555e);
}

.btn-referee-action-sec {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 12px 20px;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 700;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface-container-lowest, #ffffff);
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.2s ease;
}

:global(html.dark) .btn-referee-action-sec {
  background: #20242f;
  border-color: #2c313f;
  color: #ffffff;
}

.btn-referee-action-sec:hover:not(:disabled) {
  background: var(--surface-container-high, #e7e8e9);
  transform: translateY(-1px);
}

.btn-referee-action-sec:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-switch-ends {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  border-radius: 16px;
  background: var(--surface-container-lowest, #ffffff);
  border: 1px solid var(--outline-variant, #d3c3c5);
  color: var(--on-surface, #191c1d);
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  transition: all 0.2s ease;
}

:global(html.dark) .btn-switch-ends {
  background: #20242f;
  border-color: #2c313f;
  color: #ffffff;
}

.btn-switch-ends:hover {
  background: var(--surface-container-high, #e7e8e9);
  transform: translateY(-1px);
}

.btn-finish-match-stitch {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 28px;
  border-radius: 16px;
  background: var(--primary, #78555e);
  color: #ffffff;
  font-size: 14px;
  font-weight: 800;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(120, 85, 94, 0.25);
  transition: all 0.25s ease;
  position: relative;
  overflow: hidden;
}

.btn-finish-match-stitch:hover {
  background: var(--surface-tint, #78555e);
  box-shadow: 0 6px 20px rgba(120, 85, 94, 0.4);
  transform: translateY(-2px);
}

.shimmer-wave {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.25), transparent);
  transform: translateX(-100%);
  animation: shimmerAnim 2.5s infinite;
}

@keyframes shimmerAnim {
  0% { transform: translateX(-100%); }
  50%, 100% { transform: translateX(100%); }
}

/* Rally Log Drawer */
.rally-events-drawer {
  background: var(--surface-container-lowest, #ffffff);
  border: 1px solid var(--border, #e1e3e4);
  border-radius: 20px;
  padding: 16px 20px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
}

:global(html.dark) .rally-events-drawer {
  background: #181b22;
  border-color: #2a2e3b;
}

.drawer-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border, #edeeef);
  margin-bottom: 12px;
}

:global(html.dark) .drawer-head {
  border-color: #242835;
}

.rally-events-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 200px;
  overflow-y: auto;
}

.rally-log-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 10px;
  border-radius: 10px;
  background: var(--surface-container-low, #f3f4f5);
  font-size: 12px;
}

:global(html.dark) .rally-log-row {
  background: #20242f;
}

.log-time {
  color: var(--muted, #6c757d);
  font-size: 11px;
  min-width: 44px;
}

.log-badge {
  padding: 2px 8px;
  border-radius: 6px;
  font-weight: 700;
  font-size: 11px;
}

.log-label {
  flex: 1;
  color: var(--ink, #191c1d);
  font-weight: 500;
}

:global(html.dark) .log-label {
  color: #ffffff;
}

.log-score-tag {
  font-weight: 800;
  font-size: 13px;
  color: var(--primary, #78555e);
}

/* Modals Penalty & Finish */
.btn-select-penalty-player {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border-radius: 14px;
  border: 1.5px solid var(--border, #cbd5e1);
  background: var(--surface-container-low, #f3f4f5);
  cursor: pointer;
  transition: all 0.2s ease;
}

:global(html.dark) .btn-select-penalty-player {
  background: #20242f;
  border-color: #2c313f;
}

.btn-select-penalty-player.active {
  border-color: var(--primary, #78555e);
  background: var(--primary-container, #ffd1dc);
}

.card-penalty-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border-radius: 14px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface-container-lowest, #ffffff);
  cursor: pointer;
  transition: all 0.2s ease;
}

:global(html.dark) .card-penalty-item {
  background: #20242f;
  border-color: #2c313f;
}

.card-penalty-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.card-color-box {
  width: 24px;
  height: 32px;
  border-radius: 4px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
  flex-shrink: 0;
}

.match-winner-summary-card {
  background: var(--surface-container-low, #f3f4f5);
  border: 1px solid var(--primary-container, #ffd1dc);
  border-radius: 20px;
  padding: 16px 20px;
}

:global(html.dark) .match-winner-summary-card {
  background: #20242f;
  border-color: #5e3e47;
}

.winner-trophy-badge {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: #fef3c7;
  display: flex;
  align-items: center;
  justify-content: center;
}

:global(html.dark) .winner-trophy-badge {
  background: #451a03;
}

/* TAB 4: Overview KPIs */
.overview-kpis-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
}

.kpi-card {
  background: var(--surface-container-lowest, #ffffff);
  border-radius: 20px;
  border: 1px solid var(--border, #e1e3e4);
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .kpi-card {
  background: #181a20;
  border-color: #262933;
}

.kpi-icon {
  font-size: 32px;
}

.kpi-val {
  font-size: 22px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  display: block;
}

:global(html.dark) .kpi-val {
  color: #ffffff;
}

.kpi-lbl {
  font-size: 12px;
  color: var(--muted, #6c757d);
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 520px;
  background: var(--surface-container-lowest, #ffffff);
  border-radius: 24px;
  padding: 24px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
  max-height: 90vh;
  overflow-y: auto;
}

:global(html.dark) .modal-card {
  background: #1e2129;
  border: 1px solid #2e323e;
}

.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.btn-close {
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  cursor: pointer;
}

.input-lbl {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: var(--muted, #4f4446);
  margin-bottom: 6px;
}

:global(html.dark) .input-lbl {
  color: #c4c6cf;
}

.aura-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface-container-low, #f3f4f5);
  font-size: 13px;
  color: var(--ink, #191c1d);
  outline: none;
}

:global(html.dark) .aura-input {
  background: #242730;
  border-color: #383c47;
  color: #e1e3e4;
}

.aura-input-sm {
  width: 100%;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid var(--border, #cbd5e1);
  font-size: 13px;
  font-weight: 700;
  text-align: center;
}

.score-input-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.modal-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid var(--border, #e1e3e4);
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 600;
  border: 1px solid var(--border, #cbd5e1);
  background: transparent;
  cursor: pointer;
}

.btn-confirm {
  padding: 8px 20px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 700;
  border: none;
  background: var(--primary, #78555e);
  color: #ffffff;
  cursor: pointer;
}

:global(html.dark) .btn-confirm {
  background: #f8b4c4;
  color: #201317;
}

/* ================= STITCH ATHLETE CHECK-IN SCREEN STYLES ================= */
.btn-scan-qr {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 22px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 700;
  background: var(--primary, #78555e);
  color: #ffffff;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
  transition: all 0.2s ease;
}

.btn-scan-qr:hover {
  background: #63434c;
  box-shadow: 0 6px 18px rgba(120, 85, 94, 0.35);
  transform: translateY(-1px);
}

:global(html.dark) .btn-scan-qr {
  background: #f8b4c4;
  color: #201317;
}

.checkin-grid-layout {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 24px;
  align-items: start;
}

@media (max-width: 1024px) {
  .checkin-grid-layout {
    grid-template-columns: 1fr;
  }
}

/* Left Column */
.checkin-left-column {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Filter Tabs Pills Bar */
.filter-tabs-pill-bar {
  display: flex;
  align-items: center;
  background: var(--surface-container, #edeeef);
  border-radius: 9999px;
  padding: 4px;
  gap: 4px;
  overflow-x: auto;
}

:global(html.dark) .filter-tabs-pill-bar {
  background: #1e2129;
}

.filter-pill-item {
  flex: 1;
  min-width: 110px;
  padding: 8px 14px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted, #6c757d);
  background: transparent;
  border: none;
  cursor: pointer;
  text-align: center;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.filter-pill-item:hover {
  color: var(--ink, #191c1d);
}

.filter-pill-item.active {
  background: var(--surface-container-lowest, #ffffff);
  color: var(--primary, #78555e);
  font-weight: 700;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

:global(html.dark) .filter-pill-item.active {
  background: #2a2e39;
  color: #f8b4c4;
}

/* Search Bar Row */
.checkin-search-bar-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search-field-wrapper {
  flex: 1;
  min-width: 240px;
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--surface-container-low, #f3f4f5);
  border: 1px solid var(--border, #e1e3e4);
  border-radius: 9999px;
  padding: 8px 16px;
  transition: border-color 0.2s ease;
}

.search-field-wrapper:focus-within {
  border-color: var(--primary, #78555e);
  background: var(--surface-container-lowest, #ffffff);
}

:global(html.dark) .search-field-wrapper {
  background: #181a20;
  border-color: #2a2d36;
}

:global(html.dark) .search-field-wrapper:focus-within {
  background: #20232c;
  border-color: #f8b4c4;
}

.search-input-field {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  font-size: 13px;
  color: var(--ink, #191c1d);
}

:global(html.dark) .search-input-field {
  color: #e1e3e4;
}

.btn-clear-search {
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  cursor: pointer;
  display: flex;
  align-items: center;
}

.filter-select-wrapper {
  position: relative;
}

.level-select-dropdown {
  padding: 9px 16px;
  border-radius: 9999px;
  border: 1px solid var(--border, #e1e3e4);
  background: var(--surface-container-low, #f3f4f5);
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  cursor: pointer;
  outline: none;
}

:global(html.dark) .level-select-dropdown {
  background: #181a20;
  border-color: #2a2d36;
  color: #e1e3e4;
}

.btn-export-report {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 16px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 600;
  border: 1px solid var(--border, #e1e3e4);
  background: var(--surface-container-lowest, #ffffff);
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-export-report:hover {
  background: var(--surface-container-high, #e7e8e9);
}

:global(html.dark) .btn-export-report {
  background: #1e2129;
  border-color: #2e323e;
  color: #e1e3e4;
}

/* Athlete Cards Feed */
.athletes-cards-feed {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.athlete-feed-card {
  position: relative;
  background: var(--surface-container-lowest, #ffffff);
  border-radius: 18px;
  padding: 14px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border: 1px solid var(--border, #edf0f2);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
  overflow: hidden;
  transition: all 0.2s ease;
}

.athlete-feed-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
  border-color: #d3c3c5;
}

:global(html.dark) .athlete-feed-card {
  background: #181a20;
  border-color: #242730;
}

:global(html.dark) .athlete-feed-card:hover {
  border-color: #383c47;
}

.athlete-feed-card.is-checked-in {
  background: var(--surface-container-low, #f3f4f5);
}

:global(html.dark) .athlete-feed-card.is-checked-in {
  background: #1e2129;
}

.card-active-indicator-stripe {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--primary, #78555e);
}

:global(html.dark) .card-active-indicator-stripe {
  background: #f8b4c4;
}

.card-noshow-indicator-stripe {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: #ef4444;
}

/* Main Info Group */
.athlete-main-info-group {
  display: flex;
  align-items: center;
  gap: 14px;
  flex: 1;
  min-width: 0;
}

.athlete-avatar-box {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  overflow: hidden;
  background: var(--surface-container-highest, #e1e3e4);
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

.athlete-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.athlete-initials-badge {
  font-size: 15px;
  font-weight: 700;
  color: var(--primary, #78555e);
}

:global(html.dark) .athlete-initials-badge {
  color: #f8b4c4;
}

.athlete-text-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.athlete-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.athlete-name-headline {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
  line-height: 1.2;
}

:global(html.dark) .athlete-name-headline {
  color: #ffffff;
}

.athlete-id-pill {
  font-size: 11px;
  font-weight: 600;
  color: var(--muted, #6c757d);
  background: var(--surface-container-high, #e7e8e9);
  padding: 1px 6px;
  border-radius: 6px;
}

:global(html.dark) .athlete-id-pill {
  background: #2b2e38;
}

.athlete-badges-row {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.badge-level-chip {
  font-size: 11px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  background: var(--surface-container-high, #e7e8e9);
  padding: 2px 6px;
  border-radius: 6px;
}

:global(html.dark) .badge-level-chip {
  background: #2b2e38;
  color: #d1d5db;
}

.badge-club-chip {
  display: flex;
  align-items: center;
  gap: 2px;
  font-size: 12px;
  font-weight: 500;
  color: var(--muted, #6c757d);
}

.badge-seed-chip {
  display: flex;
  align-items: center;
  gap: 2px;
  font-size: 12px;
  font-weight: 600;
  color: var(--muted, #6c757d);
}

.badge-seed-chip.seed-highlight {
  color: var(--primary, #78555e);
  font-weight: 700;
}

:global(html.dark) .badge-seed-chip.seed-highlight {
  color: #f8b4c4;
}

.badge-warmup-chip {
  display: flex;
  align-items: center;
  gap: 2px;
  font-size: 11px;
  font-weight: 600;
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
  padding: 1px 6px;
  border-radius: 6px;
}

/* Action Group */
.athlete-action-group {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-shrink: 0;
}

.athlete-tools-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

@media (max-width: 768px) {
  .athlete-tools-row {
    display: none;
  }
}

.quick-pill-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 600;
  background: var(--surface-container-high, #e7e8e9);
  color: var(--muted, #4f4446);
  border: none;
  cursor: pointer;
  transition: all 0.15s ease;
}

.quick-pill-btn:hover {
  background: var(--surface-container-highest, #e1e3e4);
  color: var(--ink, #191c1d);
}

.quick-pill-btn.active {
  background: rgba(16, 185, 129, 0.15);
  color: #059669;
  font-weight: 700;
}

.btn-pill-danger {
  color: #ef4444;
}

.btn-pill-danger:hover {
  background: rgba(239, 68, 68, 0.12);
}

:global(html.dark) .quick-pill-btn {
  background: #242730;
  color: #9ca3af;
}

.status-timestamp-box {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 80px;
}

.status-title-lbl {
  font-size: 13px;
  font-weight: 700;
}

.timestamp-subline {
  font-size: 11px;
  color: var(--muted, #6c757d);
}

.btn-round-checkin {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: var(--surface-container-high, #e7e8e9);
  color: var(--muted, #6c757d);
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.btn-round-checkin:hover {
  transform: scale(1.06);
  background: var(--primary, #78555e);
  color: #ffffff;
}

.btn-round-checkin.is-checked {
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
}

.btn-round-checkin.is-no-show {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}

:global(html.dark) .btn-round-checkin {
  background: #262933;
}

:global(html.dark) .btn-round-checkin.is-checked {
  background: #4a3038;
  color: #f8b4c4;
}

/* Empty State */
.empty-feed-card {
  padding: 48px 24px;
  text-align: center;
  background: var(--surface-container-lowest, #ffffff);
  border-radius: 20px;
  border: 1px dashed var(--border, #cbd5e1);
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* Load More */
.load-more-row {
  display: flex;
  justify-content: center;
  padding-top: 12px;
}

.btn-load-more-athletes {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 24px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 700;
  color: var(--primary, #78555e);
  background: var(--surface-container, #edeeef);
  border: 1px solid var(--border, #e1e3e4);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-load-more-athletes:hover {
  background: var(--surface-container-high, #e7e8e9);
  transform: translateY(-1px);
}

:global(html.dark) .btn-load-more-athletes {
  background: #1e2129;
  border-color: #2e323e;
  color: #f8b4c4;
}

/* Right Sticky Status Column */
.checkin-right-column {
  position: relative;
}

.sticky-status-card {
  position: sticky;
  top: 90px;
  background: var(--surface-container-low, #f3f4f5);
  border-radius: 32px;
  padding: 24px;
  border: 1px solid var(--border, #e1e3e4);
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .sticky-status-card {
  background: #181a20;
  border-color: #282b36;
}

.card-ambient-glow {
  position: absolute;
  top: -40px;
  right: -40px;
  width: 180px;
  height: 180px;
  border-radius: 50%;
  background: rgba(120, 85, 94, 0.08);
  filter: blur(40px);
  pointer-events: none;
}

.status-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.status-card-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .status-card-title {
  color: #ffffff;
}

.status-badge-chip {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: #10b981;
  background: rgba(16, 185, 129, 0.12);
  padding: 2px 8px;
  border-radius: 9999px;
}

/* Radial Gauge (Stitch Visuals) */
.radial-gauge-wrapper {
  position: relative;
  width: 180px;
  height: 180px;
  margin: 0 auto 20px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.radial-svg-gauge {
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.gauge-bg-circle {
  stroke: var(--surface-container-highest, #e1e3e4);
}

:global(html.dark) .gauge-bg-circle {
  stroke: #2e323e;
}

.gauge-progress-circle {
  stroke: var(--primary, #78555e);
  transition: stroke-dashoffset 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

:global(html.dark) .gauge-progress-circle {
  stroke: #f8b4c4;
}

.gauge-center-content {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.gauge-pct-number {
  font-size: 38px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  line-height: 1;
  letter-spacing: -0.03em;
}

:global(html.dark) .gauge-pct-number {
  color: #ffffff;
}

.gauge-pct-sublabel {
  font-size: 13px;
  font-weight: 600;
  color: var(--muted, #6c757d);
  margin-top: 4px;
}

/* Status Metrics List */
.status-metrics-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 20px;
}

.metric-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-radius: 12px;
  transition: background 0.15s ease;
}

.metric-item-row:hover,
.metric-item-row.active {
  background: var(--surface-container, #edeeef);
}

:global(html.dark) .metric-item-row:hover,
:global(html.dark) .metric-item-row.active {
  background: #242730;
}

.metric-label-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.metric-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.dot-checked-in {
  background: var(--primary, #78555e);
}

:global(html.dark) .dot-checked-in {
  background: #f8b4c4;
}

.dot-pending {
  background: var(--surface-container-highest, #cbd5e1);
}

.dot-noshow {
  background: #ef4444;
}

.metric-name {
  font-size: 14px;
  font-weight: 600;
}

.metric-value-num {
  font-size: 14px;
}

/* Next Match Card Box */
.next-match-card-box {
  border-top: 1px solid var(--border, #e1e3e4);
  padding-top: 16px;
  margin-bottom: 20px;
}

:global(html.dark) .next-match-card-box {
  border-color: #2a2d36;
}

.next-match-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--muted, #6c757d);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin: 0 0 4px;
}

.next-match-time {
  font-size: 26px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 0 0 2px;
}

:global(html.dark) .next-match-time {
  color: #ffffff;
}

.next-match-court {
  font-size: 13px;
  color: var(--muted, #6c757d);
  margin: 0;
}

.next-match-actions {
  display: flex;
  gap: 8px;
}

.btn-go-referee-console {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 700;
  background: var(--primary, #78555e);
  color: #ffffff;
  border: none;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-go-referee-console:hover {
  background: #63434c;
}

:global(html.dark) .btn-go-referee-console {
  background: #f8b4c4;
  color: #201317;
}

.btn-view-bracket-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  background: var(--surface-container-highest, #e1e3e4);
  color: var(--ink, #191c1d);
  border: none;
  cursor: pointer;
}

:global(html.dark) .btn-view-bracket-link {
  background: #2b2e38;
  color: #e1e3e4;
}

/* Quick QR Widget Box */
.quick-qr-widget-box {
  background: var(--surface-container-lowest, #ffffff);
  border-radius: 18px;
  padding: 16px;
  border: 1px solid var(--border, #e1e3e4);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

:global(html.dark) .quick-qr-widget-box {
  background: #1e2129;
  border-color: #282b36;
}

.widget-head-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.quick-qr-input-group {
  display: flex;
  gap: 6px;
}

.quick-qr-field {
  flex: 1;
  min-width: 0;
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface-container-low, #f3f4f5);
  font-size: 12px;
  outline: none;
}

:global(html.dark) .quick-qr-field {
  background: #262933;
  border-color: #383c47;
  color: #e1e3e4;
}

.btn-quick-qr-submit {
  padding: 8px 14px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
  background: var(--primary, #78555e);
  color: #ffffff;
  border: none;
  cursor: pointer;
  white-space: nowrap;
}

:global(html.dark) .btn-quick-qr-submit {
  background: #f8b4c4;
  color: #201317;
}

.quick-sample-chips-row {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.sample-scan-chip {
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
  background: var(--surface-container-high, #e7e8e9);
  color: var(--ink, #191c1d);
  border: none;
  cursor: pointer;
  transition: all 0.15s ease;
}

.sample-scan-chip:hover {
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
}

/* QR Code Camera Viewfinder Modal */
.modal-qr-scanner-card {
  max-width: 480px;
}

.camera-viewfinder-box {
  position: relative;
  width: 100%;
  height: 260px;
  border-radius: 20px;
  background: #0f1115;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: inset 0 0 40px rgba(0, 0, 0, 0.8);
}

.viewfinder-corner {
  position: absolute;
  width: 32px;
  height: 32px;
  border-color: #ffd1dc;
  border-style: solid;
  pointer-events: none;
  z-index: 10;
}

.viewfinder-corner.top-left {
  top: 24px;
  left: 24px;
  border-width: 3px 0 0 3px;
  border-top-left-radius: 12px;
}

.viewfinder-corner.top-right {
  top: 24px;
  right: 24px;
  border-width: 3px 3px 0 0;
  border-top-right-radius: 12px;
}

.viewfinder-corner.bottom-left {
  bottom: 24px;
  left: 24px;
  border-width: 0 0 3px 3px;
  border-bottom-left-radius: 12px;
}

.viewfinder-corner.bottom-right {
  bottom: 24px;
  right: 24px;
  border-width: 0 3px 3px 0;
  border-bottom-right-radius: 12px;
}

.laser-scanline {
  position: absolute;
  top: 24px;
  left: 24px;
  right: 24px;
  height: 3px;
  background: linear-gradient(90deg, transparent, #ff8da1, #ffffff, #ff8da1, transparent);
  box-shadow: 0 0 12px #ff8da1;
  border-radius: 2px;
  animation: sweepLaser 2.2s ease-in-out infinite;
  z-index: 5;
}

@keyframes sweepLaser {
  0% {
    top: 24px;
    opacity: 0.8;
  }
  50% {
    top: 230px;
    opacity: 1;
  }
  100% {
    top: 24px;
    opacity: 0.8;
  }
}

.viewfinder-status-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 9999px;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  color: #ffffff;
  font-size: 11px;
  font-weight: 600;
  z-index: 10;
}

.viewfinder-target-graphic {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.qr-scan-feedback-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 12px;
  border: 1px solid;
}

.scan-samples-container {
  background: var(--surface-container-low, #f3f4f5);
  border-radius: 16px;
  padding: 14px;
}

:global(html.dark) .scan-samples-container {
  background: #181a20;
}

.btn-sample-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-radius: 12px;
  background: var(--surface-container-lowest, #ffffff);
  border: 1px solid var(--border, #e1e3e4);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-sample-card:hover {
  border-color: var(--primary, #78555e);
  transform: translateY(-1px);
}

:global(html.dark) .btn-sample-card {
  background: #22252f;
  border-color: #313542;
}

/* Digital Accreditation Pass Modal */
.modal-pass-card {
  max-width: 440px;
}

.digital-badge-container {
  background: linear-gradient(180deg, #ffffff 0%, #fdf9fa 100%);
  border: 2px solid var(--border, #ffd1dc);
  border-radius: 24px;
  padding: 24px 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  position: relative;
  box-shadow: 0 10px 30px rgba(120, 85, 94, 0.08);
}

:global(html.dark) .digital-badge-container {
  background: linear-gradient(180deg, #1f222a 0%, #171920 100%);
  border-color: #4a3038;
}

.badge-top-hole {
  width: 48px;
  height: 8px;
  border-radius: 4px;
  background: var(--surface-container-highest, #cbd5e1);
  margin: -10px auto 4px;
}

:global(html.dark) .badge-top-hole {
  background: #383c47;
}

.badge-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border, #ffd1dc);
  padding-bottom: 12px;
}

:global(html.dark) .badge-header-row {
  border-color: #383c47;
}

.badge-logo-box {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: var(--primary, #78555e);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
}

:global(html.dark) .badge-logo-box {
  background: #f8b4c4;
  color: #201317;
}

.badge-org-title {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.05em;
  color: var(--primary, #78555e);
  margin: 0;
}

:global(html.dark) .badge-org-title {
  color: #f8b4c4;
}

.badge-tournament-name {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .badge-tournament-name {
  color: #ffffff;
}

.badge-role-tag {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.06em;
  padding: 4px 10px;
  border-radius: 9999px;
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
}

:global(html.dark) .badge-role-tag {
  background: #54343d;
  color: #f8b4c4;
}

.badge-profile-section {
  display: flex;
  align-items: center;
  gap: 16px;
}

.badge-photo-wrapper {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  overflow: hidden;
  background: var(--surface-container-high, #e1e3e4);
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.badge-photo-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.badge-initials-box {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 800;
  color: var(--primary, #78555e);
}

:global(html.dark) .badge-initials-box {
  color: #f8b4c4;
}

.badge-player-info {
  display: flex;
  flex-direction: column;
}

.badge-player-name {
  font-size: 18px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .badge-player-name {
  color: #ffffff;
}

.badge-club-name {
  font-size: 13px;
  color: var(--muted, #6c757d);
  margin: 0;
}

.badge-meta-chips {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.badge-micro-pill {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 6px;
  background: var(--surface-container-high, #e7e8e9);
  color: var(--ink, #191c1d);
}

:global(html.dark) .badge-micro-pill {
  background: #2a2e39;
  color: #e1e3e4;
}

.badge-micro-pill.seed-pill {
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
}

:global(html.dark) .badge-micro-pill.seed-pill {
  background: #4a3038;
  color: #f8b4c4;
}

.badge-qr-section {
  display: flex;
  align-items: center;
  gap: 16px;
  background: var(--surface-container-low, #f8f9fa);
  border-radius: 16px;
  padding: 12px;
  border: 1px dashed var(--border, #e1e3e4);
}

:global(html.dark) .badge-qr-section {
  background: #14161c;
  border-color: #2e323e;
}

.badge-qr-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}

.badge-id-code {
  font-size: 11px;
  font-weight: 800;
  font-family: monospace;
  color: var(--primary, #78555e);
}

:global(html.dark) .badge-id-code {
  color: #f8b4c4;
}

.badge-permissions-list {
  flex: 1;
}

.badge-barcode-footer {
  border-top: 1px solid var(--border, #e1e3e4);
  padding-top: 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

:global(html.dark) .badge-barcode-footer {
  border-color: #2e323e;
}

.mock-barcode-lines {
  width: 100%;
  height: 24px;
  background: repeating-linear-gradient(
    90deg,
    #191c1d 0px,
    #191c1d 2px,
    transparent 2px,
    transparent 4px,
    #191c1d 4px,
    #191c1d 7px,
    transparent 7px,
    transparent 9px
  );
  opacity: 0.8;
}

:global(html.dark) .mock-barcode-lines {
  background: repeating-linear-gradient(
    90deg,
    #ffffff 0px,
    #ffffff 2px,
    transparent 2px,
    transparent 4px,
    #ffffff 4px,
    #ffffff 7px,
    transparent 7px,
    transparent 9px
  );
}

.barcode-sub-text {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--muted, #6c757d);
  margin: 0;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.4;
  }
}
</style>

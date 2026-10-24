import { computed, ref, type Ref } from 'vue'
import type { Member, TournamentDetailInfo } from '../types/tournament'
import { emptyMember, entryProblem, initialMembers, memberLimits } from '../utils/tournament'

type Shape = Pick<
  TournamentDetailInfo,
  'entry_type' | 'team_main' | 'team_reserve' | 'athlete_fields'
>

/**
 * BR-150: a unit name and its members (who need no account). Shared by the public registration and the
 * representative's direct roster entry; the member count follows the entry type and team sizes.
 */
export function useEntryForm(tournament: Readonly<Ref<Shape | null>>) {
  const name = ref('')
  const members = ref<Member[]>([])
  const limits = computed(() =>
    tournament.value ? memberLimits(tournament.value) : { main: 0, reserve: 0 },
  )
  const reserves = computed(() => members.value.filter((m) => m.role === 'reserve').length)
  const problem = computed(() =>
    tournament.value
      ? entryProblem(name.value, members.value, tournament.value, tournament.value.athlete_fields)
      : null,
  )

  function reset() {
    name.value = ''
    members.value = tournament.value
      ? initialMembers(tournament.value, tournament.value.athlete_fields)
      : []
  }

  function addReserve() {
    if (!tournament.value || reserves.value >= limits.value.reserve) return
    members.value.push(emptyMember(tournament.value.athlete_fields, 'reserve'))
  }

  function removeMember(index: number) {
    members.value.splice(index, 1)
  }

  const payload = () => ({
    name: name.value.trim(),
    members: members.value.map((m) => ({ ...m, full_name: m.full_name.trim() })),
  })

  return { name, members, limits, reserves, problem, reset, addReserve, removeMember, payload }
}

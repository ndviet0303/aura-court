import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { tournamentManageApi } from '../api/tournamentManageApi'
import type { TournamentDetailInfo, TournamentSetup, TournamentType } from '../types/tournament'
import { TIEBREAKERS } from '../types/tournament'
import { apiProblem, fromPicker, toPicker } from '../utils/tournament'

/** The wizard's editable state; datetimes are venue-clock picker values ("YYYY-MM-DDTHH:mm"). */
export type SetupForm = Omit<
  TournamentSetup,
  'registration_deadline' | 'payment_deadline' | 'starts_at' | 'ends_at'
> & {
  type: TournamentType
  registration_deadline: string
  payment_deadline: string
  starts_at: string
  ends_at: string
  venue_id: number | null
  reservation_codes: string
  representative_email: string
}

export function emptySetup(type: TournamentType): SetupForm {
  return {
    type,
    name: '',
    sport: 'badminton',
    entry_type: 'single',
    team_main: 5,
    team_reserve: 2,
    format: 'knockout',
    group_count: 2,
    advance_per_group: 2,
    games_to_win: 2,
    points: { win: 3, draw: 1, loss: 0 },
    tiebreakers: [...TIEBREAKERS],
    rules: '',
    eligibility: '',
    athlete_fields: [],
    forfeit_rule: '',
    prizes: '',
    max_entries: 16,
    fee: 100000,
    registration_deadline: '',
    payment_deadline: '',
    starts_at: '',
    ends_at: '',
    venue_id: null,
    reservation_codes: '',
    representative_email: '',
  }
}

export function setupFromTournament(t: TournamentDetailInfo): SetupForm {
  return {
    ...emptySetup(t.type),
    ...t,
    team_main: t.team_main ?? 5,
    team_reserve: t.team_reserve ?? 2,
    group_count: t.group_count ?? 2,
    advance_per_group: t.advance_per_group ?? 2,
    games_to_win: t.games_to_win ?? 2,
    points: { ...t.points },
    tiebreakers: [...t.tiebreakers],
    athlete_fields: [...t.athlete_fields],
    eligibility: t.eligibility ?? '',
    prizes: t.prizes ?? '',
    registration_deadline: toPicker(t.registration_deadline),
    payment_deadline: toPicker(t.payment_deadline),
    starts_at: toPicker(t.starts_at),
    ends_at: toPicker(t.ends_at),
    venue_id: t.venue.id,
  }
}

/** FR-120, BR-149: fields that do not apply are sent as null; internal tournaments carry no fee or dates. */
export function setupPayload(f: SetupForm): TournamentSetup {
  const team = f.entry_type === 'team'
  const group = f.format === 'group'
  const base: TournamentSetup = {
    name: f.name.trim(),
    sport: f.sport,
    entry_type: f.entry_type,
    team_main: team ? f.team_main : null,
    team_reserve: team ? f.team_reserve : null,
    format: f.format,
    group_count: group ? f.group_count : null,
    advance_per_group: group ? f.advance_per_group : null,
    games_to_win: f.sport === 'badminton' ? f.games_to_win : null,
    points: f.points,
    tiebreakers: f.tiebreakers,
    rules: f.rules,
    eligibility: f.eligibility,
    athlete_fields: f.athlete_fields,
    forfeit_rule: f.forfeit_rule,
    prizes: f.prizes,
  }
  if (f.type === 'internal') return base
  return {
    ...base,
    max_entries: f.max_entries,
    fee: f.fee,
    registration_deadline: fromPicker(f.registration_deadline),
    payment_deadline: fromPicker(f.payment_deadline),
    starts_at: fromPicker(f.starts_at),
    ends_at: fromPicker(f.ends_at),
  }
}

/** Create (public or internal) or edit a draft tournament; resolves to the saved tournament's id. */
export function useTournamentSetup(type: TournamentType, editId: string | null) {
  const { t } = useI18n()
  const form = reactive<SetupForm>(emptySetup(type))
  const venues = ref<{ value: number; label: string }[]>([])
  const loading = ref(false)
  const saving = ref(false)
  const error = ref('')

  onMounted(async () => {
    loading.value = true
    try {
      if (editId) {
        Object.assign(
          form,
          setupFromTournament((await tournamentManageApi.show(editId)).tournament),
        )
      } else if (type === 'public') {
        venues.value = (await tournamentManageApi.venues()).map((v) => ({
          value: v.id,
          label: resolveLocalizedText(v.name),
        }))
        form.venue_id = venues.value[0]?.value ?? null
      }
    } catch (e) {
      error.value = apiProblem(e, t('tournament.loadError'))
    } finally {
      loading.value = false
    }
  })

  async function save(): Promise<string | null> {
    saving.value = true
    error.value = ''
    try {
      const payload = setupPayload(form)
      if (editId) return (await tournamentManageApi.update(editId, payload)).tournament.id
      if (form.type === 'public') {
        return (
          await tournamentManageApi.createPublic({ ...payload, venue_id: form.venue_id ?? 0 })
        ).tournament.id
      }
      const codes = form.reservation_codes.split(/[\s,;]+/).filter(Boolean)
      const email = form.representative_email.trim()
      return (
        await tournamentManageApi.createInternal({
          ...payload,
          reservation_codes: codes,
          representative_email: email || undefined,
        })
      ).tournament.id
    } catch (e) {
      error.value = apiProblem(e, t('tournament.setup.saveError'))
      return null
    } finally {
      saving.value = false
    }
  }

  return { form, venues, loading, saving, error, save }
}

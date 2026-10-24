<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { TournamentDetailInfo } from '../types/tournament'
import { formatMoney } from '../utils/tournament'

defineProps<{ tournament: TournamentDetailInfo }>()
const { t, d } = useI18n()
const when = (value: string | null) => (value ? d(new Date(value), 'datetime') : '–')
</script>

<template>
  <section class="admin-detail-card">
    <h2 class="admin-detail-section-title">{{ t('tournament.tabs.info') }}</h2>
    <dl class="admin-detail-grid">
      <dt>{{ t('tournament.fields.venue') }}</dt>
      <dd>
        {{ tournament.venue.name
        }}<template v-if="tournament.venue.address"> – {{ tournament.venue.address }}</template>
      </dd>
      <dt>{{ t('tournament.fields.sport') }}</dt>
      <dd>{{ t(`tournament.sport.${tournament.sport}`) }}</dd>
      <dt>{{ t('tournament.fields.entry_type') }}</dt>
      <dd>
        {{ t(`tournament.entryType.${tournament.entry_type}`) }}
        <template v-if="tournament.entry_type === 'team'">
          ({{
            t('tournament.teamSize', {
              main: tournament.team_main ?? 0,
              reserve: tournament.team_reserve ?? 0,
            })
          }})
        </template>
      </dd>
      <dt>{{ t('tournament.fields.format') }}</dt>
      <dd>
        {{ t(`tournament.format.${tournament.format}`) }}
        <template v-if="tournament.format === 'group'">
          ·
          {{
            t('tournament.groupSummary', {
              groups: tournament.group_count ?? 0,
              advance: tournament.advance_per_group ?? 0,
            })
          }}
        </template>
      </dd>
      <template v-if="tournament.sport === 'badminton' && tournament.games_to_win">
        <dt>{{ t('tournament.fields.games_to_win') }}</dt>
        <dd>{{ t('tournament.bestOf', { count: tournament.games_to_win }) }}</dd>
      </template>
      <dt>{{ t('tournament.fields.points') }}</dt>
      <dd>{{ t('tournament.pointsSummary', tournament.points) }}</dd>
      <dt>{{ t('tournament.fields.tiebreakers') }}</dt>
      <dd>
        {{
          [
            t('tournament.tiebreaker.points'),
            ...tournament.tiebreakers.map((x) => t(`tournament.tiebreaker.${x}`)),
          ].join(' → ')
        }}
      </dd>
      <template v-if="tournament.type === 'public'">
        <dt>{{ t('tournament.fields.fee') }}</dt>
        <dd>{{ formatMoney(tournament.fee) }}</dd>
        <dt>{{ t('tournament.fields.slots') }}</dt>
        <dd>{{ tournament.accepted_count }}/{{ tournament.max_entries ?? '–' }}</dd>
        <dt>{{ t('tournament.fields.registration_deadline') }}</dt>
        <dd>{{ when(tournament.registration_deadline) }}</dd>
        <dt>{{ t('tournament.fields.payment_deadline') }}</dt>
        <dd>{{ when(tournament.payment_deadline) }}</dd>
      </template>
      <dt>{{ t('tournament.fields.starts_at') }}</dt>
      <dd>{{ when(tournament.starts_at) }} – {{ when(tournament.ends_at) }}</dd>
      <template v-if="tournament.athlete_fields.length">
        <dt>{{ t('tournament.fields.athlete_fields') }}</dt>
        <dd>{{ tournament.athlete_fields.join(', ') }}</dd>
      </template>
      <dt>{{ t('tournament.fields.rules') }}</dt>
      <dd>{{ tournament.rules || '–' }}</dd>
      <dt>{{ t('tournament.fields.eligibility') }}</dt>
      <dd>{{ tournament.eligibility || '–' }}</dd>
      <dt>{{ t('tournament.fields.forfeit_rule') }}</dt>
      <dd>{{ tournament.forfeit_rule || '–' }}</dd>
      <dt>{{ t('tournament.fields.prizes') }}</dt>
      <dd>{{ tournament.prizes || '–' }}</dd>
    </dl>
  </section>
</template>

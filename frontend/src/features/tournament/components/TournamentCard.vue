<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { TournamentSummary } from '../types/tournament'
import { formatMoney } from '../utils/tournament'
import TournamentStatusBadge from './TournamentStatusBadge.vue'

defineProps<{ tournament: TournamentSummary; to: string; role?: string | null }>()
const { t, d } = useI18n()
</script>

<template>
  <RouterLink :to="to" class="tournament-card">
    <header class="tournament-card__head">
      <h3 class="tournament-card__name">{{ tournament.name }}</h3>
      <TournamentStatusBadge :status="tournament.status" />
    </header>
    <p class="admin-detail-muted">
      {{ t(`tournament.sport.${tournament.sport}`) }} ·
      {{ t(`tournament.entryType.${tournament.entry_type}`) }} ·
      {{ t(`tournament.format.${tournament.format}`) }}
      <template v-if="tournament.type === 'internal'"> · {{ t('tournament.internal') }}</template>
      <template v-if="role"> · {{ t(`tournament.role.${role}`) }}</template>
    </p>
    <p class="tournament-card__venue">
      <span class="material-symbols-outlined">location_on</span>
      {{ tournament.venue.name
      }}<template v-if="tournament.venue.address"> – {{ tournament.venue.address }}</template>
    </p>
    <dl class="admin-detail-grid">
      <template v-if="tournament.type === 'public'">
        <dt>{{ t('tournament.fields.fee') }}</dt>
        <dd>{{ formatMoney(tournament.fee) }}</dd>
        <dt>{{ t('tournament.fields.slots') }}</dt>
        <dd>{{ tournament.accepted_count }}/{{ tournament.max_entries ?? '–' }}</dd>
        <template v-if="tournament.registration_deadline">
          <dt>{{ t('tournament.fields.registration_deadline') }}</dt>
          <dd>{{ d(new Date(tournament.registration_deadline), 'datetime') }}</dd>
        </template>
        <template v-if="tournament.payment_deadline">
          <dt>{{ t('tournament.fields.payment_deadline') }}</dt>
          <dd>{{ d(new Date(tournament.payment_deadline), 'datetime') }}</dd>
        </template>
      </template>
      <template v-if="tournament.starts_at">
        <dt>{{ t('tournament.fields.starts_at') }}</dt>
        <dd>{{ d(new Date(tournament.starts_at), 'datetime') }}</dd>
      </template>
    </dl>
  </RouterLink>
</template>

<style scoped>
.tournament-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
  color: var(--ink);
  text-decoration: none;
}

.tournament-card:hover {
  border-color: var(--primary, #6b2d3f);
}

.tournament-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.tournament-card__name {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.tournament-card__venue {
  display: flex;
  align-items: center;
  gap: 4px;
  margin: 0;
  font-size: 13px;
}

.tournament-card__venue .material-symbols-outlined {
  font-size: 18px;
  color: var(--muted);
}
</style>

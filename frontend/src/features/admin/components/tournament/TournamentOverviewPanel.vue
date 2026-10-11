<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { TournamentAdminData, Participant, FixtureMatch } from '../../types/tournamentAdmin'
import { SkeletonLoader } from '@/shared/components'

const props = defineProps<{
  tournament: TournamentAdminData
  participants?: Participant[]
  matches?: FixtureMatch[]
  loading?: boolean
}>()

const { t } = useI18n()

const totalParticipants = computed(() => props.participants?.length || 0)
const totalMatches = computed(() => props.matches?.length || 0)
const completedMatches = computed(
  () => props.matches?.filter((m) => m.status === 'completed').length || 0,
)
const progressPercent = computed(() => {
  if (totalMatches.value === 0) return 0
  return Math.round((completedMatches.value / totalMatches.value) * 100)
})
</script>

<template>
  <div class="tournament-overview-container">
    <div class="title-with-icon">
      <span class="material-symbols-outlined card-title-icon">dashboard</span>
      <div>
        <h3 class="overview-title">{{ t('admin.tournaments.overviewTitle') }}</h3>
        <p class="overview-sub">{{ t('admin.tournaments.overviewSub') }}</p>
      </div>
    </div>

    <!-- 4 KPI Cards with Skeleton Loading -->
    <div v-if="loading" class="kpi-cards-grid">
      <div v-for="i in 4" :key="i" class="kpi-card">
        <SkeletonLoader type="title" width="40%" height="20px" />
        <SkeletonLoader type="text" width="60%" height="14px" />
      </div>
    </div>

    <div v-else class="kpi-cards-grid">
      <div class="kpi-card">
        <span class="material-symbols-outlined kpi-icon">groups</span>
        <span class="kpi-label">{{ t('admin.tournaments.kpiTotalAthletes') }}</span>
        <strong class="kpi-val font-mono">{{
          t('admin.tournaments.kpiAthletesVal', { count: totalParticipants })
        }}</strong>
        <span class="kpi-trend text-green">
          <span class="material-symbols-outlined icon-12">check</span>
          {{ totalParticipants }}/{{ tournament.maxSlots }}
          {{ t('admin.tournaments.slotsOccupied') }}
        </span>
      </div>

      <div class="kpi-card">
        <span class="material-symbols-outlined kpi-icon">payments</span>
        <span class="kpi-label">{{ t('admin.tournaments.kpiPrizePool') }}</span>
        <strong class="kpi-val font-mono">{{ tournament.prizePool }}</strong>
        <span class="kpi-trend font-mono">{{ tournament.entryFee }}</span>
      </div>

      <div class="kpi-card">
        <span class="material-symbols-outlined kpi-icon">sports_tennis</span>
        <span class="kpi-label">{{ t('admin.tournaments.kpiCompletedMatches') }}</span>
        <strong class="kpi-val font-mono">{{
          t('admin.tournaments.kpiMatchesVal', { completed: completedMatches, total: totalMatches })
        }}</strong>
        <span class="kpi-trend text-blue">{{
          t('admin.tournaments.kpiMatchesProgress', { percent: progressPercent })
        }}</span>
      </div>

      <div class="kpi-card">
        <span class="material-symbols-outlined kpi-icon">stadium</span>
        <span class="kpi-label">{{ t('admin.tournaments.kpiCourtsInUse') }}</span>
        <strong class="kpi-val font-mono">{{
          t('admin.tournaments.kpiCourtsVal', {
            active: tournament.courtsAvailable || 4,
            total: tournament.courtsAvailable || 4,
          })
        }}</strong>
        <span class="kpi-trend text-green">{{ tournament.venue }} ({{ tournament.city }})</span>
      </div>
    </div>

    <!-- Tournament Meta Information Section -->
    <div class="tournament-info-section">
      <h4 class="section-subhead">{{ t('admin.tournaments.tournamentDetailsTitle') }}</h4>
      <div class="info-rows-grid">
        <div class="info-cell">
          <span class="cell-label">{{ t('admin.tournaments.formatLabel') }}:</span>
          <strong class="cell-val">{{
            tournament.format === 'single_elimination'
              ? t('admin.tournaments.formatSingleElim')
              : t('admin.tournaments.formatRoundRobin')
          }}</strong>
        </div>
        <div class="info-cell">
          <span class="cell-label">{{ t('admin.tournaments.sportLabel') }}:</span>
          <strong class="cell-val">{{ tournament.sport }} ({{ tournament.category }})</strong>
        </div>
        <div class="info-cell">
          <span class="cell-label">{{ t('admin.tournaments.datesLabel') }}:</span>
          <strong class="cell-val font-mono">{{ tournament.datesFormatted }}</strong>
        </div>
        <div class="info-cell">
          <span class="cell-label">{{ t('admin.tournaments.statusLabel') }}:</span>
          <span class="status-badge" :class="tournament.status">{{ tournament.status }}</span>
        </div>
      </div>
      <p v-if="tournament.description" class="tournament-desc">{{ tournament.description }}</p>
    </div>
  </div>
</template>

<style scoped>
.tournament-overview-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
  background: var(--surface, #ffffff);
  border-radius: 16px;
  border: 1px solid var(--outline, #e2e8f0);
  padding: 20px;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-title-icon {
  font-size: 24px;
  color: var(--pink-dark, #db2777);
}

.overview-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #0f172a);
}

.overview-sub {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--muted, #64748b);
}

.kpi-cards-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid var(--outline, #e2e8f0);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.kpi-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.kpi-label {
  font-size: 12px;
  color: var(--muted, #64748b);
}

.kpi-val {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink, #0f172a);
}

.kpi-trend {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
}

.icon-12 {
  font-size: 12px;
}

.text-green {
  color: #10b981;
}
.text-blue {
  color: #3b82f6;
}

.tournament-info-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--outline, #e2e8f0);
}

.section-subhead {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #0f172a);
}

.info-rows-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.info-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

.cell-label {
  color: var(--muted, #64748b);
}

.cell-val {
  color: var(--ink, #0f172a);
}

.status-badge {
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.status-badge.open,
.status-badge.ready {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-badge.ongoing {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.status-badge.completed {
  background: rgba(100, 116, 139, 0.1);
  color: #64748b;
}

.tournament-desc {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--muted, #64748b);
  line-height: 1.5;
}

@media (max-width: 900px) {
  .kpi-cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .info-rows-grid {
    grid-template-columns: 1fr;
  }
}
</style>

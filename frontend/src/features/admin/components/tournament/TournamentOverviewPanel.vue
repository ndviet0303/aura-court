<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { TournamentAdminData } from '../../types/tournamentAdmin'

defineProps<{
  tournament: TournamentAdminData
}>()

const { t } = useI18n()
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

    <!-- 4 KPI Cards -->
    <div class="kpi-cards-grid">
      <div class="kpi-card">
        <span class="material-symbols-outlined kpi-icon">groups</span>
        <span class="kpi-label">{{ t('admin.tournaments.kpiTotalAthletes') }}</span>
        <strong class="kpi-val font-mono">{{ t('admin.tournaments.kpiAthletesVal', { count: 142 }) }}</strong>
        <span class="kpi-trend text-green">↑ {{ t('admin.tournaments.kpiAthletesTrend') }}</span>
      </div>

      <div class="kpi-card">
        <span class="material-symbols-outlined kpi-icon">payments</span>
        <span class="kpi-label">{{ t('admin.tournaments.kpiPrizePool') }}</span>
        <strong class="kpi-val font-mono">{{ tournament.prizePool }}</strong>
        <span class="kpi-trend font-mono">{{ tournament.prizePoolUsd }}</span>
      </div>

      <div class="kpi-card">
        <span class="material-symbols-outlined kpi-icon">sports_tennis</span>
        <span class="kpi-label">{{ t('admin.tournaments.kpiCompletedMatches') }}</span>
        <strong class="kpi-val font-mono">{{ t('admin.tournaments.kpiMatchesVal', { completed: 18, total: 31 }) }}</strong>
        <span class="kpi-trend text-blue">{{ t('admin.tournaments.kpiMatchesProgress', { percent: 58 }) }}</span>
      </div>

      <div class="kpi-card">
        <span class="material-symbols-outlined kpi-icon">stadium</span>
        <span class="kpi-label">{{ t('admin.tournaments.kpiCourtsInUse') }}</span>
        <strong class="kpi-val font-mono">{{ t('admin.tournaments.kpiCourtsVal', { active: 4, total: 4 }) }}</strong>
        <span class="kpi-trend text-green">{{ t('admin.tournaments.kpiCourtsStatus') }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tournament-overview-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
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
}

.overview-sub {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.kpi-cards-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-top: 8px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.kpi-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.kpi-label {
  font-size: 12px;
  color: var(--muted);
}

.kpi-val {
  font-size: 18px;
  color: var(--ink);
}

.kpi-trend {
  font-size: 11px;
}

.text-green { color: #10b981; }
.text-blue { color: #3b82f6; }

@media (max-width: 900px) {
  .kpi-cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>

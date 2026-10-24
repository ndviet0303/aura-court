<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Standings } from '../types/tournament'

defineProps<{ standings: Standings }>()
const { t } = useI18n()
</script>

<template>
  <div class="standings">
    <p v-if="!Object.keys(standings).length" class="admin-detail-muted">
      {{ t('tournament.standings.empty') }}
    </p>
    <section v-for="(rows, group) in standings" :key="group" class="admin-detail-card">
      <h3 class="admin-detail-section-title">{{ t('tournament.standings.group', { group }) }}</h3>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ t('tournament.standings.entry') }}</th>
              <th>{{ t('tournament.standings.played') }}</th>
              <th>{{ t('tournament.standings.wdl') }}</th>
              <th>{{ t('tournament.standings.score') }}</th>
              <th>{{ t('tournament.standings.diff') }}</th>
              <th>{{ t('tournament.standings.points') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.entry.id">
              <td>{{ row.rank }}</td>
              <td>
                {{ row.entry.name }}
                <span v-if="row.tied" class="standings-tied">{{
                  t('tournament.standings.tied')
                }}</span>
              </td>
              <td>{{ row.played }}</td>
              <td>{{ row.wins }}/{{ row.draws }}/{{ row.losses }}</td>
              <td>{{ row.score_for }}:{{ row.score_against }}</td>
              <td>{{ row.score_diff > 0 ? `+${row.score_diff}` : row.score_diff }}</td>
              <td>
                <strong>{{ row.points }}</strong>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<style scoped>
.standings {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.standings-tied {
  margin-left: 6px;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(217, 119, 6, 0.12);
  color: #b45309;
  font-size: 11px;
  font-weight: 700;
}
</style>

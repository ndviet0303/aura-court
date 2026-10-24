<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { TournamentDetailInfo } from '../types/tournament'

// FR-131: a stopped tournament keeps its results and takes no more writes.
defineProps<{ tournament: Pick<TournamentDetailInfo, 'status' | 'stopped_at' | 'stop_reason'> }>()
const { t, d } = useI18n()
</script>

<template>
  <p v-if="tournament.status === 'stopped'" class="stopped-banner" role="status">
    <strong>{{ t('tournament.stopped.title') }}</strong>
    <template v-if="tournament.stopped_at">
      · {{ d(new Date(tournament.stopped_at), 'datetime') }}
    </template>
    <template v-if="tournament.stop_reason"> · {{ tournament.stop_reason }}</template>
  </p>
</template>

<style scoped>
.stopped-banner {
  margin: 0;
  padding: 10px 14px;
  border-radius: 10px;
  background: rgba(220, 38, 38, 0.1);
  color: #b91c1c;
  font-size: 13px;
}
</style>

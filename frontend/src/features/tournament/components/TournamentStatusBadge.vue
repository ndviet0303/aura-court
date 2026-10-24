<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { EntryStatus, TournamentStatus } from '../types/tournament'

defineProps<{ status: TournamentStatus | EntryStatus; kind?: 'tournament' | 'entry' }>()
const { t } = useI18n()
</script>

<template>
  <span class="status-badge" :class="`status-badge--${status}`">
    {{ t(`tournament.${kind === 'entry' ? 'entryStatus' : 'status'}.${status}`) }}
  </span>
</template>

<style scoped>
.status-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.05));
  color: var(--ink);
  font-size: 11.5px;
  font-weight: 700;
  white-space: nowrap;
}

.status-badge--open,
.status-badge--approved,
.status-badge--in_progress {
  background: rgba(2, 132, 199, 0.12);
  color: #0284c7;
}

.status-badge--paid,
.status-badge--confirmed,
.status-badge--finished {
  background: rgba(5, 150, 105, 0.12);
  color: #059669;
}

.status-badge--rejected,
.status-badge--stopped {
  background: rgba(220, 38, 38, 0.1);
  color: #dc2626;
}

.status-badge--submitted,
.status-badge--drawn,
.status-badge--roster_locked {
  background: rgba(217, 119, 6, 0.12);
  color: #b45309;
}
</style>

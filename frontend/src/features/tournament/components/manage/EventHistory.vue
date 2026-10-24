<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Fixture, TournamentEvent } from '../../types/tournament'
import { scoreText } from '../../utils/tournament'

// UC-7.33: every change is kept; a correction carries `{ fixture_id, before, after }` and its reason.
defineProps<{ events: TournamentEvent[] }>()
const { t, te, d } = useI18n()

const label = (type: string) =>
  te(`tournament.history.type.${type}`) ? t(`tournament.history.type.${type}`) : type

function describe(value: unknown): string {
  if (!value || typeof value !== 'object') return value == null ? '–' : String(value)
  const v = value as Record<string, unknown>
  if ('games' in v || 'goals' in v) return scoreText(v as Fixture['score']) || '–'
  if ('absent_entry_id' in v) return t('tournament.history.walkover')
  // ponytail: other payload shapes are shown raw; give them labels when the server documents them.
  return JSON.stringify(value)
}
</script>

<template>
  <section class="admin-detail-card">
    <h2 class="admin-detail-section-title">{{ t('tournament.tabs.history') }}</h2>
    <ol class="history-list">
      <li v-for="event in events" :key="event.id">
        <div>
          <strong>{{ label(event.type) }}</strong>
          <span class="admin-detail-muted">
            · {{ event.actor?.name ?? t('tournament.history.system') }} ·
            {{ d(new Date(event.created_at), 'datetime') }}
          </span>
        </div>
        <div v-if="'before' in event.payload || 'after' in event.payload" class="history-change">
          {{ describe(event.payload.before) }} → {{ describe(event.payload.after) }}
        </div>
        <div v-if="event.reason" class="admin-detail-muted">
          {{ t('tournament.manage.reason') }}: {{ event.reason }}
        </div>
      </li>
      <li v-if="!events.length" class="admin-detail-muted">{{ t('tournament.history.empty') }}</li>
    </ol>
  </section>
</template>

<style scoped>
.history-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 14px;
}

.history-change {
  font-variant-numeric: tabular-nums;
}
</style>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { venueClockRange } from '@/core/utils/venueTime'
import type { EntryRef, Fixture } from '../types/tournament'
import { isFootballScore, scheduleOrder, scoreText } from '../utils/tournament'

// Read-only on the public page; the management screen adds its actions per row through the slot.
const props = defineProps<{ fixtures: Fixture[]; withActions?: boolean }>()
const { t, d } = useI18n()
const rows = computed(() => scheduleOrder(props.fixtures))

const side = (entry: EntryRef | null) => entry?.name ?? t('tournament.fixture.tbd')
function stage(f: Fixture) {
  return f.stage === 'group'
    ? t('tournament.fixture.group', { group: f.group ?? '', round: f.round })
    : t('tournament.fixture.knockout', { round: f.round, position: f.position })
}
function result(f: Fixture) {
  if (f.status === 'bye') return t('tournament.fixture.bye')
  if (f.forfeit_entry_id) {
    const absent = [f.entry_one, f.entry_two].find((e) => e?.id === f.forfeit_entry_id)
    return t('tournament.fixture.walkover', { name: absent?.name ?? '' })
  }
  const text = scoreText(f.score)
  if (isFootballScore(f.score) && f.score.shootout) {
    return `${text} (${t('tournament.fixture.shootout', { score: f.score.shootout.join('-') })})`
  }
  return text || '–'
}
</script>

<template>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>{{ t('tournament.fixture.stage') }}</th>
          <th>{{ t('tournament.fixture.match') }}</th>
          <th>{{ t('tournament.fixture.court') }}</th>
          <th>{{ t('tournament.fixture.time') }}</th>
          <th>{{ t('tournament.fixture.result') }}</th>
          <th v-if="withActions"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="f in rows" :key="f.id">
          <td>{{ stage(f) }}</td>
          <td>
            <span :class="{ winner: f.winner_entry_id && f.winner_entry_id === f.entry_one?.id }">
              {{ side(f.entry_one) }}
            </span>
            –
            <span :class="{ winner: f.winner_entry_id && f.winner_entry_id === f.entry_two?.id }">
              {{ side(f.entry_two) }}
            </span>
          </td>
          <td>{{ f.court?.code ?? '–' }}</td>
          <td>
            <template v-if="f.starts_at">
              {{ d(new Date(f.starts_at), 'date') }} {{ venueClockRange(f.starts_at, f.ends_at) }}
            </template>
            <template v-else>–</template>
          </td>
          <td>{{ result(f) }}</td>
          <td v-if="withActions"><slot name="actions" :fixture="f" /></td>
        </tr>
        <tr v-if="!rows.length">
          <td :colspan="withActions ? 6 : 5" class="admin-detail-muted">
            {{ t('tournament.fixture.empty') }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.winner {
  font-weight: 800;
}
</style>

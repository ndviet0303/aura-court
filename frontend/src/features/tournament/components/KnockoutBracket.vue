<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { EntryRef, Fixture } from '../types/tournament'
import { knockoutRounds, roundName, scoreText } from '../utils/tournament'

const props = defineProps<{ fixtures: Fixture[] }>()
const { t } = useI18n()
const rounds = computed(() => knockoutRounds(props.fixtures))
const lastRound = computed(() => rounds.value.at(-1)?.round ?? 0)

function title(round: number) {
  const [key, params] = roundName(round, lastRound.value)
  return t(key, params)
}
// A bye has one side only; an empty side of a pending match waits for the earlier round.
function side(f: Fixture, entry: EntryRef | null) {
  if (entry) return entry.name
  return f.status === 'bye' ? t('tournament.fixture.bye') : t('tournament.fixture.tbd')
}
</script>

<template>
  <p v-if="!rounds.length" class="admin-detail-muted">{{ t('tournament.bracket.empty') }}</p>
  <div v-else class="bracket">
    <section v-for="r in rounds" :key="r.round" class="bracket-round">
      <h3 class="bracket-round__title">{{ title(r.round) }}</h3>
      <div
        v-for="f in r.fixtures"
        :key="f.id"
        class="bracket-match"
        :class="{ bye: f.status === 'bye' }"
      >
        <div
          v-for="entry in [f.entry_one, f.entry_two]"
          :key="entry?.id ?? `empty-${f.id}`"
          class="bracket-side"
          :class="{ winner: entry && entry.id === f.winner_entry_id }"
        >
          {{ side(f, entry) }}
        </div>
        <p v-if="f.score || f.forfeit_entry_id" class="bracket-score">
          {{ f.forfeit_entry_id ? t('tournament.fixture.walkoverShort') : scoreText(f.score) }}
        </p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.bracket {
  display: flex;
  gap: 16px;
  overflow-x: auto;
  padding-bottom: 8px;
}

.bracket-round {
  display: flex;
  flex-direction: column;
  justify-content: space-around;
  gap: 12px;
  min-width: 200px;
}

.bracket-round__title {
  margin: 0;
  font-size: 13px;
  font-weight: 700;
  color: var(--muted);
}

.bracket-match {
  border: 1px solid var(--outline);
  border-radius: 10px;
  background: var(--surface);
  overflow: hidden;
}

.bracket-match.bye {
  opacity: 0.7;
}

.bracket-side {
  padding: 6px 10px;
  font-size: 13px;
}

.bracket-side + .bracket-side {
  border-top: 1px solid var(--outline);
}

.bracket-side.winner {
  font-weight: 800;
}

.bracket-score {
  margin: 0;
  padding: 4px 10px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.03));
  color: var(--muted);
  font-size: 12px;
}
</style>

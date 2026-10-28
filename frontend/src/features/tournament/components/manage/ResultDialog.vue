<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { ManagedTournament } from '../../composables/useManagedTournament'
import type { Fixture, ResultPayload } from '../../types/tournament'
import { dependentFixtures, isFootballScore } from '../../utils/tournament'
import FixtureTable from '../FixtureTable.vue'

/**
 * UC-7.32 result per sport, BR-156 walkover, UC-7.33 correction (owner, public, reason required).
 * The server works out the winner and rejects a draw that cannot stand (RESULT_INVALID).
 */
const props = defineProps<{ manage: ManagedTournament; fixture: Fixture; correcting: boolean }>()
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()
const { data, tournament, busy, error, conflictFixtures } = props.manage
// UC-7.33: a correction shows the later fixtures it affects; E-1 narrows them to the played ones in the way.
const affected = computed(() =>
  conflictFixtures.value.length
    ? conflictFixtures.value
    : props.correcting
      ? dependentFixtures(props.fixture, data.value?.fixtures ?? [])
      : [],
)
const football = computed(() => tournament.value?.sport === 'football')
const maxGames = computed(() => 2 * (tournament.value?.games_to_win ?? 1) - 1)
const old = props.fixture.score

const mode = ref<'score' | 'walkover'>(props.fixture.forfeit_entry_id ? 'walkover' : 'score')
const games = ref<[number, number][]>(
  old && !isFootballScore(old)
    ? old.games.map((g) => [...g] as [number, number])
    : Array.from({ length: tournament.value?.games_to_win ?? 1 }, () => [0, 0] as [number, number]),
)
const goals = ref<[number, number]>(old && isFootballScore(old) ? [...old.goals] : [0, 0])
const withShootout = ref(!!(old && isFootballScore(old) && old.shootout))
const shootout = ref<[number, number]>(
  old && isFootballScore(old) && old.shootout ? [...old.shootout] : [0, 0],
)
const absent = ref<number | null>(props.fixture.forfeit_entry_id)
const reason = ref('')
const sides = [props.fixture.entry_one, props.fixture.entry_two]

const ready = computed(
  () =>
    (mode.value === 'score' || absent.value !== null) &&
    (!props.correcting || !!reason.value.trim()),
)

function payload(): ResultPayload {
  if (mode.value === 'walkover') return { absent_entry_id: absent.value! }
  if (football.value)
    return { goals: goals.value, shootout: withShootout.value ? shootout.value : null }
  return { games: games.value }
}

async function save() {
  if (!ready.value) return
  const ok = props.correcting
    ? await props.manage.correct(props.fixture, payload(), reason.value)
    : await props.manage.result(props.fixture, payload())
  if (ok) emit('close')
}
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <form
      class="modal-card modal-sm"
      role="dialog"
      aria-modal="true"
      aria-labelledby="result-title"
      @submit.prevent="save"
    >
      <div class="modal-header">
        <h3 id="result-title" class="modal-title">
          {{
            correcting ? t('tournament.fixture.correctTitle') : t('tournament.fixture.resultTitle')
          }}
        </h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="result-modes" role="radiogroup">
          <label
            ><input v-model="mode" type="radio" value="score" />
            {{ t('tournament.fixture.score') }}</label
          >
          <label>
            <input v-model="mode" type="radio" value="walkover" />
            {{ t('tournament.fixture.walkoverMode') }}
          </label>
        </div>

        <template v-if="mode === 'walkover'">
          <p class="admin-detail-muted">{{ t('tournament.fixture.absentHint') }}</p>
          <label v-for="side in sides" :key="side?.id" class="result-side">
            <input v-model="absent" type="radio" :value="side?.id" :disabled="!side" />
            {{ side?.name ?? t('tournament.fixture.tbd') }}
          </label>
        </template>

        <template v-else-if="football">
          <div class="result-row">
            <span>{{ fixture.entry_one?.name }}</span>
            <input
              v-model.number="goals[0]"
              type="number"
              min="0"
              max="99"
              class="form-input result-num"
            />
            <span>–</span>
            <input
              v-model.number="goals[1]"
              type="number"
              min="0"
              max="99"
              class="form-input result-num"
            />
            <span>{{ fixture.entry_two?.name }}</span>
          </div>
          <label class="result-side">
            <input v-model="withShootout" type="checkbox" />
            {{ t('tournament.fixture.withShootout') }}
          </label>
          <div v-if="withShootout" class="result-row">
            <input
              v-model.number="shootout[0]"
              type="number"
              min="0"
              max="99"
              class="form-input result-num"
            />
            <span>–</span>
            <input
              v-model.number="shootout[1]"
              type="number"
              min="0"
              max="99"
              class="form-input result-num"
            />
          </div>
        </template>

        <template v-else>
          <p class="admin-detail-muted">
            {{ fixture.entry_one?.name }} – {{ fixture.entry_two?.name }} ·
            {{ t('tournament.bestOf', { count: tournament?.games_to_win ?? 1 }) }}
          </p>
          <div v-for="(game, i) in games" :key="i" class="result-row">
            <span>{{ t('tournament.fixture.game', { n: i + 1 }) }}</span>
            <input
              v-model.number="game[0]"
              type="number"
              min="0"
              max="99"
              class="form-input result-num"
            />
            <span>–</span>
            <input
              v-model.number="game[1]"
              type="number"
              min="0"
              max="99"
              class="form-input result-num"
            />
            <AuraButton
              v-if="games.length > 1"
              variant="ghost"
              size="sm"
              @click="games.splice(i, 1)"
            >
              {{ t('common.delete') }}
            </AuraButton>
          </div>
          <AuraButton
            v-if="games.length < maxGames"
            variant="outline"
            size="sm"
            @click="games.push([0, 0])"
          >
            {{ t('tournament.fixture.addGame') }}
          </AuraButton>
        </template>

        <label v-if="correcting" class="form-group">
          <span class="form-label">{{ t('tournament.manage.reason') }} *</span>
          <textarea v-model="reason" rows="2" maxlength="1000" class="form-textarea" />
        </label>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
        <template v-if="affected.length">
          <p class="form-label">{{ t('tournament.fixture.dependents') }}</p>
          <FixtureTable :fixtures="affected" />
        </template>
      </div>
      <div class="modal-footer">
        <AuraButton variant="outline" :disabled="busy" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton type="submit" :loading="busy" :disabled="!ready">
          {{ t('common.save') }}
        </AuraButton>
      </div>
    </form>
  </div>
</template>

<style scoped>
.result-modes,
.result-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.result-side {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.result-num {
  width: 64px;
}
</style>

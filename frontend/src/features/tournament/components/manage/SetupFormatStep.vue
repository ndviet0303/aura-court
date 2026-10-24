<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { SetupForm } from '../../composables/useTournamentSetup'
import { FORMATS, LIMITS } from '../../types/tournament'
import TiebreakerPicker from './TiebreakerPicker.vue'

const form = defineModel<SetupForm>('form', { required: true })
const { t } = useI18n()
</script>

<template>
  <div>
    <fieldset class="form-group setup-choice">
      <legend class="form-label">{{ t('tournament.fields.format') }} *</legend>
      <label v-for="format in FORMATS" :key="format">
        <input v-model="form.format" type="radio" :value="format" />
        {{ t(`tournament.format.${format}`) }}
      </label>
    </fieldset>

    <div v-if="form.format === 'group'" class="setup-row">
      <label class="form-group">
        <span class="form-label">{{ t('tournament.fields.group_count') }} *</span>
        <input
          v-model.number="form.group_count"
          type="number"
          class="form-input"
          :min="LIMITS.groupCount[0]"
          :max="LIMITS.groupCount[1]"
        />
      </label>
      <label class="form-group">
        <span class="form-label">{{ t('tournament.fields.advance_per_group') }} *</span>
        <input v-model.number="form.advance_per_group" type="number" class="form-input" min="0" />
        <span class="form-hint">{{ t('tournament.setup.advanceHint') }}</span>
      </label>
    </div>

    <label v-if="form.sport === 'badminton'" class="form-group">
      <span class="form-label">{{ t('tournament.fields.games_to_win') }} *</span>
      <input
        v-model.number="form.games_to_win"
        type="number"
        class="form-input"
        :min="LIMITS.gamesToWin[0]"
        :max="LIMITS.gamesToWin[1]"
      />
      <span class="form-hint">
        {{ t('tournament.bestOf', { count: form.games_to_win ?? 0 }) }}
      </span>
    </label>

    <fieldset class="form-group setup-points">
      <legend class="form-label">{{ t('tournament.fields.points') }} *</legend>
      <label v-for="key in ['win', 'draw', 'loss'] as const" :key="key" class="form-group">
        <span class="form-hint">{{ t(`tournament.setup.points.${key}`) }}</span>
        <input v-model.number="form.points[key]" type="number" class="form-input" min="0" />
      </label>
    </fieldset>

    <div class="form-group">
      <span class="form-label">{{ t('tournament.fields.tiebreakers') }}</span>
      <TiebreakerPicker v-model="form.tiebreakers" />
    </div>
  </div>
</template>

<style scoped>
.setup-row,
.setup-points {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 0 12px;
}

.setup-points,
.setup-choice {
  border: 0;
  padding: 0;
}

.setup-choice {
  flex-direction: row;
  flex-wrap: wrap;
  gap: 16px;
}
</style>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { SelectDropdown } from '@/shared/components'
import type { SetupForm } from '../../composables/useTournamentSetup'
import { ENTRY_TYPES, LIMITS, SPORTS, type EntryType } from '../../types/tournament'

const form = defineModel<SetupForm>('form', { required: true })
defineProps<{ venues: { value: number; label: string }[]; showVenue: boolean }>()
const { t } = useI18n()
const entryOptions = computed(() =>
  ENTRY_TYPES.map((e) => ({ value: e, label: t(`tournament.entryType.${e}`) })),
)
</script>

<template>
  <div>
    <label class="form-group">
      <span class="form-label">{{ t('tournament.fields.name') }} *</span>
      <input v-model="form.name" class="form-input" :maxlength="LIMITS.name" required />
    </label>

    <div v-if="showVenue" class="form-group">
      <span class="form-label">{{ t('tournament.fields.venue') }} *</span>
      <SelectDropdown
        :model-value="form.venue_id"
        :options="venues"
        variant="bordered"
        :aria-label="t('tournament.fields.venue')"
        @update:model-value="form.venue_id = Number($event)"
      />
    </div>

    <fieldset class="form-group setup-choice">
      <legend class="form-label">{{ t('tournament.fields.sport') }} *</legend>
      <label v-for="sport in SPORTS" :key="sport">
        <input v-model="form.sport" type="radio" :value="sport" />
        {{ t(`tournament.sport.${sport}`) }}
      </label>
    </fieldset>

    <div class="form-group">
      <span class="form-label">{{ t('tournament.fields.entry_type') }} *</span>
      <SelectDropdown
        :model-value="form.entry_type"
        :options="entryOptions"
        variant="bordered"
        :aria-label="t('tournament.fields.entry_type')"
        @update:model-value="form.entry_type = $event as EntryType"
      />
    </div>

    <div v-if="form.entry_type === 'team'" class="setup-row">
      <label class="form-group">
        <span class="form-label">{{ t('tournament.fields.team_main') }} *</span>
        <input
          v-model.number="form.team_main"
          type="number"
          class="form-input"
          :min="LIMITS.teamMain[0]"
          :max="LIMITS.teamMain[1]"
        />
      </label>
      <label class="form-group">
        <span class="form-label">{{ t('tournament.fields.team_reserve') }} *</span>
        <input
          v-model.number="form.team_reserve"
          type="number"
          class="form-input"
          :min="LIMITS.teamReserve[0]"
          :max="LIMITS.teamReserve[1]"
        />
      </label>
    </div>
  </div>
</template>

<style scoped>
.setup-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(160px, 100%), 1fr));
  gap: 0 12px;
}

.setup-choice {
  flex-direction: row;
  flex-wrap: wrap;
  gap: 16px;
  border: 0;
  padding: 0;
}
</style>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { SetupForm } from '../../composables/useTournamentSetup'
import { LIMITS } from '../../types/tournament'
import TagInput from './TagInput.vue'

const form = defineModel<SetupForm>('form', { required: true })
const { t } = useI18n()
// BR-149: a public tournament needs every text; an internal one only its rules and forfeit rule.
const texts = [
  { key: 'rules', max: LIMITS.rules, always: true },
  { key: 'eligibility', max: LIMITS.eligibility, always: false },
  { key: 'forfeit_rule', max: LIMITS.forfeitRule, always: true },
  { key: 'prizes', max: LIMITS.prizes, always: false },
] as const
</script>

<template>
  <div>
    <label v-for="field in texts" :key="field.key" class="form-group">
      <span class="form-label">
        {{ t(`tournament.fields.${field.key}`)
        }}{{ field.always || form.type === 'public' ? ' *' : '' }}
      </span>
      <textarea v-model="form[field.key]" rows="4" class="form-textarea" :maxlength="field.max" />
      <span class="form-hint">{{ form[field.key].length }}/{{ field.max }}</span>
    </label>

    <div class="form-group">
      <span class="form-label">{{ t('tournament.fields.athlete_fields') }}</span>
      <TagInput
        v-model="form.athlete_fields"
        :max="LIMITS.athleteFields"
        :placeholder="t('tournament.setup.athleteFieldsPlaceholder')"
      />
      <span class="form-hint">
        {{ t('tournament.setup.athleteFieldsHint', { max: LIMITS.athleteFields }) }}
      </span>
    </div>
  </div>
</template>

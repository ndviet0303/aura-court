<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { Member } from '../types/tournament'

// BR-150: members need no account; one row per athlete with the tournament's extra fields.
const members = defineModel<Member[]>({ required: true })
defineProps<{
  fields: string[]
  limits: { main: number; reserve: number }
  canAddReserve: boolean
}>()
const emit = defineEmits<{ addReserve: []; remove: [index: number] }>()
const { t } = useI18n()
</script>

<template>
  <div class="member-fields">
    <p class="form-hint">
      {{ t('tournament.member.limits', { main: limits.main, reserve: limits.reserve }) }}
    </p>
    <fieldset v-for="(member, index) in members" :key="index" class="member-row">
      <legend class="form-label">
        {{ t(`tournament.member.${member.role}`) }} {{ index + 1 }}
      </legend>
      <label class="form-group">
        <span class="form-label">{{ t('tournament.member.fullName') }} *</span>
        <input v-model="member.full_name" class="form-input" maxlength="255" required />
      </label>
      <label class="form-group">
        <span class="form-label">{{ t('tournament.member.birthDate') }} *</span>
        <input v-model="member.birth_date" type="date" class="form-input" required />
      </label>
      <label v-for="field in fields" :key="field" class="form-group">
        <span class="form-label">{{ field }} *</span>
        <input v-model="member.extra[field]" class="form-input" maxlength="255" required />
      </label>
      <AuraButton
        v-if="member.role === 'reserve'"
        variant="outline"
        size="sm"
        @click="emit('remove', index)"
      >
        {{ t('common.delete') }}
      </AuraButton>
    </fieldset>
    <AuraButton v-if="canAddReserve" variant="outline" size="sm" @click="emit('addReserve')">
      {{ t('tournament.member.addReserve') }}
    </AuraButton>
  </div>
</template>

<style scoped>
.member-fields {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.member-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr));
  gap: 0 12px;
  align-items: end;
  margin: 0;
  padding: 12px;
  border: 1px solid var(--outline);
  border-radius: 12px;
}
</style>

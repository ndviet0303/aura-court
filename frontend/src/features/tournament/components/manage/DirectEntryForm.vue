<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { ManagedTournament } from '../../composables/useManagedTournament'
import { useEntryForm } from '../../composables/useEntryForm'
import MemberFields from '../MemberFields.vue'

// UC-7.27: one unit typed by hand, checked by the same rules as the Excel import.
const props = defineProps<{ manage: ManagedTournament }>()
const { t } = useI18n()
const { tournament, busy, error } = props.manage
const form = useEntryForm(tournament)
const { name, members, limits, reserves, problem } = form
const showProblem = ref(false)
form.reset()

async function submit() {
  showProblem.value = true
  if (problem.value) return
  if (await props.manage.addEntry(form.payload())) {
    form.reset()
    showProblem.value = false
  }
}
</script>

<template>
  <form v-if="tournament" class="admin-detail-card" novalidate @submit.prevent="submit">
    <h2 class="admin-detail-section-title">{{ t('tournament.roster.directTitle') }}</h2>
    <label class="form-group">
      <span class="form-label">{{ t('tournament.register.unitName') }} *</span>
      <input v-model="name" class="form-input" maxlength="255" required />
    </label>
    <MemberFields
      v-model="members"
      :fields="tournament.athlete_fields"
      :limits="limits"
      :can-add-reserve="reserves < limits.reserve"
      @add-reserve="form.addReserve"
      @remove="form.removeMember"
    />
    <p v-if="showProblem && problem" class="form-error" role="alert">
      {{ t(problem, { main: limits.main, reserve: limits.reserve }) }}
    </p>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <AuraButton type="submit" :loading="busy">{{ t('tournament.roster.add') }}</AuraButton>
  </form>
</template>

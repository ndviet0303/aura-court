<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { ManagedTournament } from '../../composables/useManagedTournament'
import { useEntryForm } from '../../composables/useEntryForm'
import type { ManagedEntry } from '../../types/tournament'
import MemberFields from '../MemberFields.vue'

// UC-7.27: one unit typed by hand, or (A-2) a saved unit corrected, checked by the same rules as the Excel import.
const props = defineProps<{ manage: ManagedTournament; entry?: ManagedEntry | null }>()
const emit = defineEmits<{ done: [] }>()
const { t } = useI18n()
const { tournament, busy, error } = props.manage
const form = useEntryForm(tournament)
const { name, members, limits, reserves, problem } = form
const showProblem = ref(false)
const el = ref<HTMLFormElement | null>(null)

watch(
  () => props.entry,
  async (entry) => {
    showProblem.value = false
    if (!entry) return form.reset()
    form.fill(entry)
    await nextTick()
    el.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  },
  { immediate: true },
)

async function submit() {
  showProblem.value = true
  if (problem.value) return
  const ok = props.entry
    ? await props.manage.updateEntry(props.entry, form.payload())
    : await props.manage.addEntry(form.payload())
  if (!ok) return
  form.reset()
  showProblem.value = false
  emit('done')
}
</script>

<template>
  <form v-if="tournament" ref="el" class="admin-detail-card" novalidate @submit.prevent="submit">
    <h2 class="admin-detail-section-title">
      {{
        entry
          ? t('tournament.roster.editTitle', { name: entry.name })
          : t('tournament.roster.directTitle')
      }}
    </h2>
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
    <div class="direct-entry-actions">
      <AuraButton type="submit" :loading="busy">
        {{ entry ? t('common.save') : t('tournament.roster.add') }}
      </AuraButton>
      <AuraButton v-if="entry" variant="outline" :disabled="busy" @click="emit('done')">
        {{ t('common.cancel') }}
      </AuraButton>
    </div>
  </form>
</template>

<style scoped>
.direct-entry-actions {
  display: flex;
  gap: 8px;
}
</style>

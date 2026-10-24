<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { AuraButton } from '@/shared/components'
import SetupFormatStep from '../components/manage/SetupFormatStep.vue'
import SetupGeneralStep from '../components/manage/SetupGeneralStep.vue'
import SetupRulesStep from '../components/manage/SetupRulesStep.vue'
import SetupScheduleStep from '../components/manage/SetupScheduleStep.vue'
import TournamentTabs from '../components/TournamentTabs.vue'
import { useManageBase } from '../composables/useManageBase'
import { useTournamentSetup } from '../composables/useTournamentSetup'

type Step = 'general' | 'format' | 'rules' | 'schedule'
const STEPS: Step[] = ['general', 'format', 'rules', 'schedule']

// FR-120: create a public (owner) or internal (owner, from bookings) tournament, or edit a draft one.
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const base = useManageBase()
const editId = route.params.id ? String(route.params.id) : null
const type = route.query.type === 'internal' ? 'internal' : 'public'
const { form, venues, loading, saving, error, save } = useTournamentSetup(type, editId)

const step = ref<Step>('general')
const index = computed(() => STEPS.indexOf(step.value))
const tabs = computed(() =>
  STEPS.map((value, i) => ({ value, label: `${i + 1}. ${t(`tournament.setup.steps.${value}`)}` })),
)
const title = computed(() =>
  editId ? t('tournament.setup.editTitle') : t(`tournament.setup.createTitle.${form.type}`),
)

async function submit() {
  const id = await save()
  if (id) await router.push(`${base.value}/${id}`)
}
</script>

<template>
  <div class="admin-detail-page">
    <RouterLink :to="editId ? `${base}/${editId}` : base" class="admin-detail-back">
      ← {{ t('tournament.back') }}
    </RouterLink>
    <h1 class="admin-detail-title">{{ title }}</h1>
    <p v-if="loading" class="admin-detail-muted">{{ t('common.loading') }}</p>

    <TournamentTabs v-model="step" :tabs="tabs" :label="t('tournament.setup.stepsLabel')" />

    <form class="admin-detail-card" novalidate @submit.prevent="submit">
      <SetupGeneralStep
        v-if="step === 'general'"
        v-model:form="form"
        :venues="venues"
        :show-venue="!editId && form.type === 'public'"
      />
      <SetupFormatStep v-else-if="step === 'format'" v-model:form="form" />
      <SetupRulesStep v-else-if="step === 'rules'" v-model:form="form" />
      <SetupScheduleStep v-else v-model:form="form" :creating="!editId" />

      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      <footer class="setup-actions">
        <AuraButton v-if="index > 0" variant="outline" @click="step = STEPS[index - 1]!">
          {{ t('tournament.setup.previous') }}
        </AuraButton>
        <AuraButton
          v-if="index < STEPS.length - 1"
          variant="outline"
          @click="step = STEPS[index + 1]!"
        >
          {{ t('tournament.setup.next') }}
        </AuraButton>
        <AuraButton type="submit" :loading="saving" :disabled="loading">
          {{ editId ? t('common.save') : t('tournament.setup.create') }}
        </AuraButton>
      </footer>
    </form>
  </div>
</template>

<style scoped>
.setup-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}
</style>

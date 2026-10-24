<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { ManagedTournament } from '../../composables/useManagedTournament'
import type { Fixture } from '../../types/tournament'
import FixtureTable from '../FixtureTable.vue'
import ResultDialog from './ResultDialog.vue'
import ScheduleDialog from './ScheduleDialog.vue'

// UC-7.31..UC-7.33: schedule, enter a result or a walkover, and (owner, public) correct a saved result.
const props = defineProps<{ manage: ManagedTournament }>()
const { t } = useI18n()
const { data, can, busy } = props.manage
const scheduling = ref<Fixture | null>(null)
const scoring = ref<{ fixture: Fixture; correcting: boolean } | null>(null)

const playable = (f: Fixture) => f.status === 'pending' && !!f.entry_one && !!f.entry_two

function open(target: 'schedule' | 'result' | 'correct', fixture: Fixture) {
  props.manage.clearError()
  if (target === 'schedule') scheduling.value = fixture
  else scoring.value = { fixture, correcting: target === 'correct' }
}
</script>

<template>
  <section class="admin-detail-card">
    <h2 class="admin-detail-section-title">{{ t('tournament.tabs.schedule') }}</h2>
    <FixtureTable :fixtures="data?.fixtures ?? []" with-actions>
      <template #actions="{ fixture }">
        <div class="fixture-actions">
          <AuraButton
            v-if="can('schedule') && fixture.status === 'pending'"
            variant="outline"
            size="sm"
            :disabled="busy"
            @click="open('schedule', fixture)"
          >
            {{ t('tournament.fixture.schedule') }}
          </AuraButton>
          <AuraButton
            v-if="can('result') && playable(fixture)"
            size="sm"
            :disabled="busy"
            @click="open('result', fixture)"
          >
            {{ t('tournament.fixture.enterResult') }}
          </AuraButton>
          <AuraButton
            v-if="can('correct') && fixture.status === 'completed'"
            variant="ghost"
            size="sm"
            :disabled="busy"
            @click="open('correct', fixture)"
          >
            {{ t('tournament.fixture.correct') }}
          </AuraButton>
        </div>
      </template>
    </FixtureTable>

    <ScheduleDialog
      v-if="scheduling"
      :manage="manage"
      :fixture="scheduling"
      @close="scheduling = null"
    />
    <ResultDialog
      v-if="scoring"
      :manage="manage"
      :fixture="scoring.fixture"
      :correcting="scoring.correcting"
      @close="scoring = null"
    />
  </section>
</template>

<style scoped>
.fixture-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
</style>

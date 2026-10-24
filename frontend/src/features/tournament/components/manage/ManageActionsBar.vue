<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { ManagedTournament } from '../../composables/useManagedTournament'
import ReasonDialog from './ReasonDialog.vue'

// Publish, lock the roster, draw and stop, each shown only when `permissions` allows it.
const props = defineProps<{ manage: ManagedTournament; editLink: string }>()
const { t } = useI18n()
const { can, busy, error } = props.manage
const stopping = ref(false)

async function stop(reason: string) {
  if (await props.manage.stop(reason)) stopping.value = false
}
function openStop() {
  props.manage.clearError()
  stopping.value = true
}
</script>

<template>
  <div class="manage-actions">
    <AuraButton v-if="can('edit')" variant="outline" size="sm" :to="editLink">
      {{ t('common.edit') }}
    </AuraButton>
    <AuraButton v-if="can('publish')" size="sm" :loading="busy" @click="manage.publish">
      {{ t('tournament.manage.publish') }}
    </AuraButton>
    <AuraButton v-if="can('lock')" size="sm" :loading="busy" @click="manage.lockRoster">
      {{ t('tournament.manage.lock') }}
    </AuraButton>
    <AuraButton v-if="can('draw')" size="sm" :loading="busy" @click="manage.draw">
      {{
        manage.tournament.value?.status === 'drawn'
          ? t('tournament.manage.redraw')
          : t('tournament.manage.draw')
      }}
    </AuraButton>
    <AuraButton v-if="can('stop')" variant="danger" size="sm" @click="openStop">
      {{ t('tournament.manage.stop') }}
    </AuraButton>

    <ReasonDialog
      v-if="stopping"
      :title="t('tournament.manage.stopTitle')"
      :message="t('tournament.manage.stopMessage')"
      :required="false"
      :confirm-text="t('tournament.manage.stop')"
      :busy="busy"
      :error="error"
      @close="stopping = false"
      @confirm="stop"
    />
  </div>
</template>

<style scoped>
.manage-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>

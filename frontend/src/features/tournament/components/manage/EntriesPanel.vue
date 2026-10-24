<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { ManagedTournament } from '../../composables/useManagedTournament'
import type { ManagedEntry } from '../../types/tournament'
import TournamentStatusBadge from '../TournamentStatusBadge.vue'
import DirectEntryForm from './DirectEntryForm.vue'
import ReasonDialog from './ReasonDialog.vue'
import RosterImport from './RosterImport.vue'

// UC-7.24 (public review) and UC-7.27 (internal roster by Excel or by hand).
const props = defineProps<{ manage: ManagedTournament }>()
const { t, d } = useI18n()
const { data, tournament, can, busy, error } = props.manage
const rejecting = ref<ManagedEntry | null>(null)

async function reject(reason: string) {
  if (rejecting.value && (await props.manage.reject(rejecting.value, reason)))
    rejecting.value = null
}
function openReject(entry: ManagedEntry) {
  props.manage.clearError()
  rejecting.value = entry
}
function remove(entry: ManagedEntry) {
  if (window.confirm(t('tournament.roster.deleteConfirm', { name: entry.name })))
    void props.manage.removeEntry(entry)
}
</script>

<template>
  <section v-if="tournament" class="admin-detail-card">
    <h2 class="admin-detail-section-title">
      {{ t('tournament.entries.title', { count: data?.entries.length ?? 0 }) }}
    </h2>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>{{ t('tournament.entries.unit') }}</th>
            <th>{{ t('tournament.entries.members') }}</th>
            <th v-if="tournament.type === 'public'">
              {{ t('tournament.entries.representative') }}
            </th>
            <th>{{ t('tournament.fields.status') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="entry in data?.entries ?? []" :key="entry.id">
            <td>
              <strong>{{ entry.name }}</strong>
              <div class="admin-detail-muted">{{ d(new Date(entry.created_at), 'datetime') }}</div>
            </td>
            <td class="entries-members">
              <div v-for="(m, i) in entry.members" :key="i">
                {{ m.full_name }} · {{ m.birth_date }}
                <template v-if="m.role === 'reserve'">
                  · {{ t('tournament.member.reserve') }}</template
                >
                <template v-for="(value, key) in m.extra" :key="key">
                  · {{ key }}: {{ value }}</template
                >
              </div>
            </td>
            <td v-if="tournament.type === 'public'">
              <template v-if="entry.representative">
                {{ entry.representative.name }}
                <div class="admin-detail-muted">
                  {{
                    [entry.representative.email, entry.representative.phone]
                      .filter(Boolean)
                      .join(' · ')
                  }}
                </div>
              </template>
            </td>
            <td>
              <TournamentStatusBadge :status="entry.status" kind="entry" />
              <div v-if="entry.reject_reason" class="admin-detail-muted">
                {{ entry.reject_reason }}
              </div>
            </td>
            <td class="entries-actions">
              <template v-if="can('review') && entry.status === 'submitted'">
                <AuraButton size="sm" :disabled="busy" @click="manage.approve(entry)">
                  {{ t('tournament.entries.approve') }}
                </AuraButton>
                <AuraButton variant="outline" size="sm" :disabled="busy" @click="openReject(entry)">
                  {{ t('tournament.entries.reject') }}
                </AuraButton>
              </template>
              <AuraButton
                v-if="can('roster') && tournament.type === 'internal'"
                variant="ghost"
                size="sm"
                :disabled="busy"
                @click="remove(entry)"
              >
                {{ t('common.delete') }}
              </AuraButton>
            </td>
          </tr>
          <tr v-if="!data?.entries.length">
            <td colspan="5" class="admin-detail-muted">{{ t('tournament.entries.empty') }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <template v-if="tournament && can('roster') && tournament.type === 'internal'">
    <RosterImport :tournament-id="tournament.id" @confirmed="manage.load(true)" />
    <DirectEntryForm :manage="manage" />
  </template>

  <ReasonDialog
    v-if="rejecting"
    :title="t('tournament.entries.rejectTitle', { name: rejecting.name })"
    :required="true"
    :confirm-text="t('tournament.entries.reject')"
    :busy="busy"
    :error="error"
    @close="rejecting = null"
    @confirm="reject"
  />
</template>

<style scoped>
.entries-members {
  white-space: normal;
  font-size: 12.5px;
}

.entries-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
</style>

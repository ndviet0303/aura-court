<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import KnockoutBracket from '../components/KnockoutBracket.vue'
import StandingsTables from '../components/StandingsTables.vue'
import StoppedBanner from '../components/StoppedBanner.vue'
import TournamentInfoPanel from '../components/TournamentInfoPanel.vue'
import TournamentStatusBadge from '../components/TournamentStatusBadge.vue'
import TournamentTabs from '../components/TournamentTabs.vue'
import CourtsPanel from '../components/manage/CourtsPanel.vue'
import EntriesPanel from '../components/manage/EntriesPanel.vue'
import EventHistory from '../components/manage/EventHistory.vue'
import FixturesPanel from '../components/manage/FixturesPanel.vue'
import GrantsPanel from '../components/manage/GrantsPanel.vue'
import ManageActionsBar from '../components/manage/ManageActionsBar.vue'
import { useManageBase } from '../composables/useManageBase'
import { useManagedTournament } from '../composables/useManagedTournament'

type Tab =
  'info' | 'courts' | 'grants' | 'entries' | 'schedule' | 'standings' | 'bracket' | 'history'

// UC-7.17..UC-7.34: one screen for owners and staff (/admin) and representatives (/tournaments/manage).
const { t } = useI18n()
const route = useRoute()
const id = computed(() => String(route.params.id))
const base = useManageBase()
const manage = useManagedTournament(id)
const { data, tournament, loading, error, can } = manage
const tab = ref<Tab>('info')

const tabs = computed(() =>
  (['info', 'courts', 'grants', 'entries', 'schedule', 'standings', 'bracket', 'history'] as Tab[])
    .filter((value) => value !== 'grants' || can('grant') || !!data.value?.grants.length)
    .map((value) => ({ value, label: t(`tournament.tabs.${value}`) })),
)
</script>

<template>
  <div class="admin-detail-page">
    <RouterLink :to="base" class="admin-detail-back"
      >← {{ t('tournament.manage.back') }}</RouterLink
    >
    <p v-if="loading && !data" class="admin-detail-muted">{{ t('common.loading') }}</p>
    <p v-if="error && !data" class="admin-detail-error" role="alert">{{ error }}</p>

    <template v-if="data && tournament">
      <header class="admin-detail-header">
        <div>
          <h1 class="admin-detail-title">{{ tournament.name }}</h1>
          <div class="managed-meta">
            <TournamentStatusBadge :status="tournament.status" />
            <span class="admin-detail-muted">
              {{
                tournament.type === 'internal' ? t('tournament.internal') : t('tournament.public')
              }}
            </span>
            <RouterLink v-if="tournament.status !== 'draft'" :to="`/tournaments/${id}`">
              {{ t('tournament.manage.publicPage') }}
            </RouterLink>
          </div>
        </div>
        <ManageActionsBar :manage="manage" :edit-link="`${base}/${id}/edit`" />
      </header>

      <StoppedBanner :tournament="tournament" />
      <p v-if="error" class="admin-detail-error" role="alert">{{ error }}</p>
      <TournamentTabs v-model="tab" :tabs="tabs" :label="t('tournament.tabs.label')" />

      <TournamentInfoPanel v-if="tab === 'info'" :tournament="tournament" />
      <CourtsPanel v-else-if="tab === 'courts'" :manage="manage" />
      <GrantsPanel v-else-if="tab === 'grants'" :manage="manage" />
      <EntriesPanel v-else-if="tab === 'entries'" :manage="manage" />
      <FixturesPanel v-else-if="tab === 'schedule'" :manage="manage" />
      <StandingsTables v-else-if="tab === 'standings'" :standings="data.standings" />
      <KnockoutBracket v-else-if="tab === 'bracket'" :fixtures="data.fixtures" />
      <EventHistory v-else :events="data.events" />
    </template>
  </div>
</template>

<style scoped>
.managed-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  font-size: 13px;
}
</style>

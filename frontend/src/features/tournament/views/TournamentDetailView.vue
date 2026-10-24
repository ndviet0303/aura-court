<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { AuraButton } from '@/shared/components'
import FixtureTable from '../components/FixtureTable.vue'
import KnockoutBracket from '../components/KnockoutBracket.vue'
import PublicEntryList from '../components/PublicEntryList.vue'
import StandingsTables from '../components/StandingsTables.vue'
import StoppedBanner from '../components/StoppedBanner.vue'
import TournamentInfoPanel from '../components/TournamentInfoPanel.vue'
import TournamentStatusBadge from '../components/TournamentStatusBadge.vue'
import TournamentTabs from '../components/TournamentTabs.vue'
import { useTournamentDetail } from '../composables/useTournamentDetail'

type Tab = 'info' | 'entries' | 'schedule' | 'standings' | 'bracket'

// FR-122: information, rules, accepted entries, schedule, standings and bracket of one tournament.
const { t } = useI18n()
const route = useRoute()
const id = computed(() => String(route.params.id))
const { detail, loading, error } = useTournamentDetail(id)
const tab = ref<Tab>('info')

const tournament = computed(() => detail.value?.tournament ?? null)
const tabs = computed(() =>
  (['info', 'entries', 'schedule', 'standings', 'bracket'] as Tab[]).map((value) => ({
    value,
    label: t(`tournament.tabs.${value}`),
  })),
)
// Owners and officials manage in the admin layout; a representative is a customer.
const manageLink = computed(() =>
  tournament.value?.my_role === 'representative'
    ? `/tournaments/manage/${id.value}`
    : `/admin/tournaments/${id.value}`,
)
</script>

<template>
  <div class="admin-detail-page">
    <RouterLink to="/tournaments" class="admin-detail-back"
      >← {{ t('tournament.back') }}</RouterLink
    >
    <p v-if="loading && !detail" class="admin-detail-muted">{{ t('common.loading') }}</p>
    <p v-if="error" class="admin-detail-error" role="alert">{{ error }}</p>

    <template v-if="detail && tournament">
      <header class="admin-detail-header">
        <div>
          <h1 class="admin-detail-title">{{ tournament.name }}</h1>
          <TournamentStatusBadge :status="tournament.status" />
        </div>
        <div class="detail-actions">
          <AuraButton v-if="tournament.can_register" :to="`/tournaments/${id}/register`">
            {{ t('tournament.register.action') }}
          </AuraButton>
          <AuraButton v-if="tournament.my_role" variant="outline" :to="manageLink">
            {{ t('tournament.manage.action') }}
          </AuraButton>
        </div>
      </header>

      <StoppedBanner :tournament="tournament" />
      <TournamentTabs v-model="tab" :tabs="tabs" :label="t('tournament.tabs.label')" />

      <TournamentInfoPanel v-if="tab === 'info'" :tournament="tournament" />
      <PublicEntryList v-else-if="tab === 'entries'" :entries="detail.entries" />
      <FixtureTable v-else-if="tab === 'schedule'" :fixtures="detail.fixtures" />
      <StandingsTables v-else-if="tab === 'standings'" :standings="detail.standings" />
      <KnockoutBracket v-else :fixtures="detail.fixtures" />
    </template>
  </div>
</template>

<style scoped>
.detail-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>

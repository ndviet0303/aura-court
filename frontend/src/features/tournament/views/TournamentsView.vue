<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { AuraButton, SelectDropdown } from '@/shared/components'
import TournamentCard from '../components/TournamentCard.vue'
import { useTournamentList } from '../composables/useTournamentList'
import { SPORTS, STATUSES, type Sport, type TournamentStatus } from '../types/tournament'

// FR-122: published public tournaments; guests browse, registering needs an account.
const { t } = useI18n()
const auth = useAuthStore()
const { filters, items, loading, error, hasMore, loadMore } = useTournamentList()

const sportOptions = computed(() => [
  { value: '', label: t('tournament.filters.allSports') },
  ...SPORTS.map((s) => ({ value: s, label: t(`tournament.sport.${s}`) })),
])
const statusOptions = computed(() => [
  { value: '', label: t('tournament.filters.allStatuses') },
  ...STATUSES.map((s) => ({ value: s, label: t(`tournament.status.${s}`) })),
])
</script>

<template>
  <div class="admin-detail-page">
    <header class="admin-detail-header">
      <div>
        <h1 class="admin-detail-title">{{ t('tournament.title') }}</h1>
        <p class="admin-detail-muted">{{ t('tournament.subtitle') }}</p>
      </div>
      <div v-if="auth.user" class="tournaments-links">
        <AuraButton variant="outline" size="sm" to="/tournaments/me">
          {{ t('tournament.me.title') }}
        </AuraButton>
        <AuraButton variant="outline" size="sm" to="/tournaments/manage">
          {{ t('tournament.manage.myManaged') }}
        </AuraButton>
      </div>
    </header>

    <div class="tournaments-filters">
      <SelectDropdown
        :model-value="filters.sport"
        :options="sportOptions"
        variant="bordered"
        :aria-label="t('tournament.fields.sport')"
        @update:model-value="filters.sport = $event as Sport | ''"
      />
      <SelectDropdown
        :model-value="filters.status"
        :options="statusOptions"
        variant="bordered"
        :aria-label="t('tournament.fields.status')"
        @update:model-value="filters.status = $event as TournamentStatus | ''"
      />
    </div>

    <p v-if="error" class="admin-detail-error" role="alert">{{ error }}</p>
    <p v-if="!loading && !items.length && !error" class="admin-detail-muted">
      {{ t('tournament.empty') }}
    </p>

    <div class="tournaments-grid">
      <TournamentCard
        v-for="item in items"
        :key="item.id"
        :tournament="item"
        :to="`/tournaments/${item.id}`"
      />
    </div>

    <AuraButton v-if="hasMore || loading" variant="outline" :loading="loading" @click="loadMore">
      {{ t('community.loadMore') }}
    </AuraButton>
  </div>
</template>

<style scoped>
.tournaments-links,
.tournaments-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tournaments-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
  gap: 16px;
}
</style>

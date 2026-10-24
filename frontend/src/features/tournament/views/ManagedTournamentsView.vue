<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import TournamentCard from '../components/TournamentCard.vue'
import { useManageBase } from '../composables/useManageBase'
import { useManagedTournaments } from '../composables/useManagedTournaments'

// Owners see every tournament of their venues, officials the assigned ones, representatives the granted ones.
const { t } = useI18n()
const base = useManageBase()
const { items, loading, error, isOwner } = useManagedTournaments()
</script>

<template>
  <div class="admin-detail-page">
    <header class="admin-detail-header">
      <div>
        <h1 class="admin-detail-title">{{ t('tournament.manage.title') }}</h1>
        <p class="admin-detail-muted">{{ t('tournament.manage.subtitle') }}</p>
      </div>
      <div v-if="isOwner && base === '/admin/tournaments'" class="managed-create">
        <AuraButton to="/admin/tournaments/new?type=public">
          {{ t('tournament.manage.createPublic') }}
        </AuraButton>
        <AuraButton variant="outline" to="/admin/tournaments/new?type=internal">
          {{ t('tournament.manage.createInternal') }}
        </AuraButton>
      </div>
    </header>

    <p v-if="error" class="admin-detail-error" role="alert">{{ error }}</p>
    <p v-if="loading" class="admin-detail-muted">{{ t('common.loading') }}</p>
    <p v-else-if="!items.length && !error" class="admin-detail-muted">
      {{ t('tournament.manage.empty') }}
    </p>

    <div class="managed-grid">
      <TournamentCard
        v-for="item in items"
        :key="item.id"
        :tournament="item"
        :role="item.my_role"
        :to="`${base}/${item.id}`"
      />
    </div>
  </div>
</template>

<style scoped>
.managed-create {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.managed-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}
</style>

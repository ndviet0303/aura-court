<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import MyEntryCard from '../components/MyEntryCard.vue'
import { useMyTournamentEntries } from '../composables/useMyTournamentEntries'

// UC-7.22, UC-7.25: track entries, pay after approval, withdraw before paying.
const { t } = useI18n()
const { entries, loading, acting, error, withdraw, pay, refresh } = useMyTournamentEntries()
</script>

<template>
  <div class="admin-detail-page">
    <RouterLink to="/tournaments" class="admin-detail-back"
      >← {{ t('tournament.back') }}</RouterLink
    >
    <header>
      <h1 class="admin-detail-title">{{ t('tournament.me.title') }}</h1>
      <p class="admin-detail-muted">{{ t('tournament.me.subtitle') }}</p>
    </header>
    <p v-if="error" class="admin-detail-error" role="alert">{{ error }}</p>
    <p v-if="loading && !entries.length" class="admin-detail-muted">{{ t('common.loading') }}</p>
    <p v-else-if="!entries.length && !error" class="admin-detail-muted">
      {{ t('tournament.me.empty') }}
    </p>
    <MyEntryCard
      v-for="entry in entries"
      :key="entry.id"
      :entry="entry"
      :busy="acting === entry.id"
      @withdraw="withdraw(entry)"
      @pay="pay(entry)"
      @refresh="refresh"
    />
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { PublicEntry } from '../types/tournament'

// FR-122: the public list shows the unit and its athletes' names only, never an account.
defineProps<{ entries: PublicEntry[] }>()
const { t } = useI18n()
</script>

<template>
  <section class="admin-detail-card">
    <h2 class="admin-detail-section-title">
      {{ t('tournament.entries.title', { count: entries.length }) }}
    </h2>
    <p v-if="!entries.length" class="admin-detail-muted">{{ t('tournament.entries.empty') }}</p>
    <ol class="entry-list">
      <li v-for="entry in entries" :key="entry.id">
        <strong>{{ entry.name }}</strong>
        <span v-if="entry.group" class="admin-detail-muted">
          · {{ t('tournament.standings.group', { group: entry.group }) }}
        </span>
        <p class="admin-detail-muted">
          {{
            entry.members
              .map((m) =>
                m.role === 'reserve'
                  ? `${m.full_name} (${t('tournament.member.reserve')})`
                  : m.full_name,
              )
              .join(', ')
          }}
        </p>
      </li>
    </ol>
  </section>
</template>

<style scoped>
.entry-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding-left: 20px;
  font-size: 14px;
}
</style>

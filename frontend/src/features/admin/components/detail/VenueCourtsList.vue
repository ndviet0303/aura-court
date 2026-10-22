<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Court } from '@/features/court/types/court'
import { hasOpenMaintenance } from '../../composables/useLifecycleCommands'
import type { LifecycleCommand } from '../../types/lifecycle'
import OperatingStatusBadge from '../lifecycle/OperatingStatusBadge.vue'
import LifecycleActions from '../lifecycle/LifecycleActions.vue'

defineProps<{
  courts: Court[]
  organizationId: number
  venueId: number
  /** Owner of the venue's organization; staff only get the maintenance pause pair. */
  owner: boolean
  busy?: boolean
}>()

const emit = defineEmits<{ command: [court: Court, command: LifecycleCommand] }>()

const { t } = useI18n()
</script>

<template>
  <ul v-if="courts.length" class="court-list">
    <li v-for="court in courts" :key="court.id" class="court-row">
      <div class="court-main">
        <RouterLink
          class="court-code"
          :to="{ name: 'admin-court-detail', params: { organizationId, venueId, courtId: court.id } }"
        >
          {{ court.code }}
        </RouterLink>
        <span class="court-sport">{{ t(`sportTypes.${court.sport_type}`) }}</span>
        <OperatingStatusBadge :status="court.status" />
      </div>
      <LifecycleActions
        target="court"
        :status="court.status"
        :owner="owner"
        :maintenance-open="hasOpenMaintenance(court.operational_stops)"
        :busy="busy"
        @command="(command) => emit('command', court, command)"
      />
    </li>
  </ul>
  <p v-else class="detail-empty">{{ t('admin.venueDetail.noCourts') }}</p>
</template>

<style scoped>
.court-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.court-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px dashed var(--outline);
}

.court-main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.court-code {
  font-weight: 800;
  color: var(--ink);
  text-decoration: none;
}

.court-code:hover {
  color: var(--pink-dark, #db2777);
  text-decoration: underline;
}

.court-sport {
  font-size: 12px;
  color: var(--muted);
}

.detail-empty {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}
</style>

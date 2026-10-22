<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import type { MonitoredVenue } from '../../types/suspension'
import OperatingStatusBadge from '../lifecycle/OperatingStatusBadge.vue'

// Task 5.10: read-only rows; the only write the platform admin has is suspension (D13).
defineProps<{ venues: MonitoredVenue[] }>()
const emit = defineEmits<{ suspend: [venue: MonitoredVenue] }>()

const { t } = useI18n()
</script>

<template>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>{{ t('admin.monitoring.columns.venue') }}</th>
          <th>{{ t('admin.monitoring.columns.organization') }}</th>
          <th>{{ t('admin.monitoring.columns.status') }}</th>
          <th>{{ t('admin.monitoring.columns.courts') }}</th>
          <th>{{ t('admin.monitoring.columns.contact') }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="venue in venues" :key="venue.id">
          <td>
            <RouterLink
              :to="{ name: 'admin-monitoring-venue-detail', params: { venueId: venue.id } }"
            >
              <strong>{{ resolveLocalizedText(venue.name) }}</strong>
            </RouterLink>
            <div class="admin-detail-muted">{{ resolveLocalizedText(venue.address) }}</div>
          </td>
          <td>{{ venue.organization?.name ?? '—' }}</td>
          <td>
            <OperatingStatusBadge :status="venue.status" :suspension="venue.active_suspension" />
          </td>
          <td>{{ venue.courts_count ?? '—' }}</td>
          <td>{{ venue.phone ?? venue.email ?? '—' }}</td>
          <td>
            <button
              v-if="!venue.active_suspension"
              type="button"
              class="suspend-link"
              @click="emit('suspend', venue)"
            >
              {{ t('admin.suspension.suspendAction') }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.suspend-link {
  border: none;
  background: none;
  padding: 0;
  color: #dc2626;
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}
</style>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import type { AdminVenueItem } from '../../composables/useAdminVenues'
import type { LifecycleCommand } from '../../types/lifecycle'
import OperatingStatusBadge from '../lifecycle/OperatingStatusBadge.vue'
import LifecycleActions from '../lifecycle/LifecycleActions.vue'

const props = defineProps<{
  venue: AdminVenueItem
  owner: boolean
  busy?: boolean
}>()

const emit = defineEmits<{
  (e: 'command', venue: AdminVenueItem, command: LifecycleCommand): void
  (e: 'edit', venue: AdminVenueItem): void
  (e: 'showQr', venue: AdminVenueItem): void
  (e: 'showReceiving', venue: AdminVenueItem): void
}>()

const { t } = useI18n()

const sports = computed(() => [...new Set(props.venue.courts?.map((court) => court.sport_type))])
</script>

<template>
  <div class="venue-admin-card" :class="{ 'is-inactive': venue.status !== 'active' }">
    <div class="venue-card-top">
      <div class="venue-meta-info">
        <div class="venue-name-row">
          <h3 class="venue-title">{{ resolveLocalizedText(venue.name) }}</h3>
          <OperatingStatusBadge :status="venue.status" :suspension="venue.active_suspension" />
        </div>
        <p class="venue-address">
          <span class="material-symbols-outlined icon-14">location_on</span>
          <span>{{ resolveLocalizedText(venue.address) || t('admin.venues.noAddress') }}</span>
        </p>
      </div>
    </div>

    <!-- Sports offered by the venue's active courts -->
    <div v-if="sports.length > 0" class="venue-sports-row">
      <span v-for="sport in sports" :key="sport" class="sport-tag">
        {{ t(`sportTypes.${sport}`) }}
      </span>
    </div>

    <div class="venue-stats-bar">
      <span class="stat-item">
        <span class="material-symbols-outlined icon-16">sports_tennis</span>
        <span>{{ t('admin.venues.courtsScale', { count: venue.courts_count || 0 }) }}</span>
      </span>
      <span class="stat-item slug-stat">
        <span class="material-symbols-outlined icon-16">link</span>
        <code class="font-mono">{{ venue.slug }}</code>
      </span>
    </div>

    <LifecycleActions
      target="venue"
      :status="venue.status"
      :owner="owner"
      :busy="busy"
      @command="(command) => emit('command', venue, command)"
    />

    <div class="venue-card-actions">
      <RouterLink
        v-if="venue.organization_id"
        :to="{
          name: 'admin-venue-detail',
          params: { organizationId: venue.organization_id, venueId: venue.id },
        }"
        class="btn-action-sm"
      >
        <span class="material-symbols-outlined icon-16">visibility</span>
        <span>{{ t('admin.venueDetail.viewDetail') }}</span>
      </RouterLink>
      <!-- Staff read their assigned venues; receiving connection and edits stay with the owner. -->
      <button
        v-if="owner"
        type="button"
        class="btn-action-sm"
        @click="emit('showReceiving', venue)"
      >
        <span class="material-symbols-outlined icon-16">account_balance</span>
        <span>{{ t('admin.venues.receivingConnection.openBtn') }}</span>
      </button>
      <button type="button" class="btn-action-sm" @click="emit('showQr', venue)">
        <span class="material-symbols-outlined icon-16">qr_code_2</span>
        <span>{{ t('admin.venues.qrBtn') }}</span>
      </button>
      <button v-if="owner" type="button" class="btn-action-sm" @click="emit('edit', venue)">
        <span class="material-symbols-outlined icon-16">edit</span>
        <span>{{ t('admin.venues.editBtn') }}</span>
      </button>
      <a
        v-if="venue.google_maps_url"
        :href="venue.google_maps_url"
        target="_blank"
        rel="noopener noreferrer"
        class="btn-action-sm link-maps"
        :title="t('admin.venues.mapsBtn')"
      >
        <span class="material-symbols-outlined icon-16">map</span>
        <span>{{ t('admin.venues.mapsBtn') }}</span>
      </a>
      <RouterLink
        :to="`/courts/${venue.slug}/directions`"
        target="_blank"
        class="btn-action-sm link-directions"
        :title="t('admin.venues.directionsBtn')"
      >
        <span class="material-symbols-outlined icon-16">directions</span>
        <span>{{ t('admin.venues.directionsBtn') }}</span>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.venue-admin-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  transition:
    transform 0.15s ease,
    box-shadow 0.15s ease,
    border-color 0.15s ease;
}

.venue-admin-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
  border-color: rgba(219, 39, 119, 0.3);
}

.venue-admin-card.is-inactive {
  opacity: 0.75;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.01));
}

.venue-card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.venue-meta-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}

.venue-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.venue-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.venue-address {
  margin: 0;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--muted);
  line-height: 1.4;
}

.venue-sports-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.sport-tag {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 6px;
  background: rgba(219, 39, 119, 0.08);
  color: var(--pink-dark, #db2777);
}

.venue-stats-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 12px;
  color: var(--ink);
}

.stat-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
}

.slug-stat code {
  font-size: 11px;
  color: var(--muted);
}

.venue-card-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: auto;
  padding-top: 4px;
}

.btn-action-sm {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.15s ease;
}

.btn-action-sm:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.04);
}

.icon-14 {
  font-size: 14px;
}

.icon-16 {
  font-size: 16px;
}
</style>

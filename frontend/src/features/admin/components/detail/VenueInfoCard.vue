<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import type { Venue } from '@/features/court/types/court'
import DetailImageGrid from './DetailImageGrid.vue'

const props = defineProps<{ venue: Venue }>()

const { t } = useI18n()

const fullAddress = computed(() =>
  [props.venue.address, props.venue.ward, props.venue.district, props.venue.city]
    .filter(Boolean)
    .join(', '),
)
</script>

<template>
  <div class="venue-info">
    <dl class="info-list">
      <div class="info-row">
        <dt>{{ t('admin.venueDetail.address') }}</dt>
        <dd>{{ fullAddress || t('admin.venueDetail.notSet') }}</dd>
      </div>
      <div class="info-row">
        <dt>{{ t('admin.venueDetail.phone') }}</dt>
        <dd>{{ venue.phone || t('admin.venueDetail.notSet') }}</dd>
      </div>
      <div class="info-row">
        <dt>{{ t('admin.venueDetail.email') }}</dt>
        <dd>{{ venue.email || t('admin.venueDetail.notSet') }}</dd>
      </div>
      <div v-if="venue.google_maps_url" class="info-row">
        <dt>{{ t('admin.venues.mapsBtn') }}</dt>
        <dd>
          <a :href="venue.google_maps_url" target="_blank" rel="noopener noreferrer">
            {{ venue.google_maps_url }}
          </a>
        </dd>
      </div>
      <div class="info-row is-block">
        <dt>{{ t('admin.venueDetail.description') }}</dt>
        <dd class="description">
          {{ resolveLocalizedText(venue.description) || t('admin.venueDetail.notSet') }}
        </dd>
      </div>
      <div class="info-row is-block">
        <dt>{{ t('admin.venueDetail.amenities') }}</dt>
        <dd>
          <span v-if="venue.amenities?.length" class="chips">
            <span v-for="amenity in venue.amenities" :key="amenity" class="chip">
              {{ amenity }}
            </span>
          </span>
          <span v-else class="muted">{{ t('admin.venueDetail.noAmenities') }}</span>
        </dd>
      </div>
      <div class="info-row is-block">
        <dt>{{ t('admin.venueDetail.images') }}</dt>
        <dd><DetailImageGrid :images="venue.images" :alt="resolveLocalizedText(venue.name)" /></dd>
      </div>
    </dl>
  </div>
</template>

<style scoped>
.info-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
}

.info-row {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 12px;
  font-size: 13px;
}

.info-row.is-block {
  grid-template-columns: 1fr;
  gap: 6px;
}

.info-row dt {
  font-weight: 700;
  color: var(--muted);
}

.info-row dd {
  margin: 0;
  overflow-wrap: anywhere;
}

.description {
  white-space: pre-line;
  line-height: 1.5;
}

.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.chip {
  padding: 3px 10px;
  border-radius: 999px;
  border: 1px solid var(--outline);
  font-size: 12px;
}

.muted {
  color: var(--muted);
}
</style>

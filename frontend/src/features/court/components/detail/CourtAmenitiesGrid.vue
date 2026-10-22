<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  amenities?: string[]
}>()

const { t } = useI18n()

// Icons and copy for the amenity keys of seeded venues; any other declared amenity shows as typed.
const amenityMap: Record<string, { icon: string; nameKey: string; descKey: string }> = {
  parking: {
    icon: 'local_parking',
    nameKey: 'courtDetail.amenityParking',
    descKey: 'courtDetail.amenityParkingDesc',
  },
  wifi: {
    icon: 'wifi',
    nameKey: 'courtDetail.amenityWifi',
    descKey: 'courtDetail.amenityWifiDesc',
  },
  shower: {
    icon: 'shower',
    nameKey: 'courtDetail.amenityShower',
    descKey: 'courtDetail.amenityShowerDesc',
  },
  cafe: {
    icon: 'local_cafe',
    nameKey: 'courtDetail.amenityCafe',
    descKey: 'courtDetail.amenityCafeDesc',
  },
  shop: {
    icon: 'sports_tennis',
    nameKey: 'courtDetail.amenityShop',
    descKey: 'courtDetail.amenityShopDesc',
  },
  lounge: {
    icon: 'weekend',
    nameKey: 'courtDetail.amenityLounge',
    descKey: 'courtDetail.amenityLoungeDesc',
  },
}

// Only what the owner declared; no amenity list is invented when there is none.
const displayedAmenities = computed(() =>
  (props.amenities ?? []).map((item) => {
    const meta = amenityMap[item.toLowerCase()]
    return meta
      ? { icon: meta.icon, name: t(meta.nameKey), desc: t(meta.descKey) }
      : { icon: 'check_circle', name: item, desc: '' }
  }),
)
</script>

<template>
  <div class="court-amenities-section">
    <h3 class="section-title">
      <span class="material-symbols-outlined title-icon">room_service</span>
      <span>{{ $t('courtDetail.amenitiesTitle') }}</span>
    </h3>
    <div v-if="displayedAmenities.length" class="amenities-grid">
      <div v-for="(a, i) in displayedAmenities" :key="i" class="amenity-card">
        <span class="material-symbols-outlined a-icon">{{ a.icon }}</span>
        <strong class="a-name">{{ a.name }}</strong>
        <span v-if="a.desc" class="a-desc">{{ a.desc }}</span>
      </div>
    </div>
    <p v-else class="amenities-empty">{{ $t('courtDetail.noAmenities') }}</p>
  </div>
</template>

<style scoped>
.court-amenities-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.title-icon {
  font-size: 18px;
  color: var(--primary);
}

.amenities-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.amenity-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
  transition: border-color 0.15s ease;
}

.amenity-card:hover {
  border-color: var(--accent);
}

.a-icon {
  font-size: 22px;
  color: var(--accent);
}

.a-name {
  font-size: 13px;
  color: var(--ink);
}

.a-desc {
  font-size: 11px;
  color: var(--muted);
}

.amenities-empty {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

@media (max-width: 768px) {
  .amenities-grid {
    grid-template-columns: 1fr;
  }
}
</style>

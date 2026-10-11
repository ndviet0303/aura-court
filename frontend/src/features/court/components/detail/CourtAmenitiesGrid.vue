<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  amenities?: string[]
}>()

const { t } = useI18n()

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

const displayedAmenities = computed(() => {
  if (props.amenities && props.amenities.length > 0) {
    return props.amenities.map((item) => {
      const key = item.toLowerCase()
      const meta = amenityMap[key] || {
        icon: 'check_circle',
        nameKey: item,
        descKey: item,
      }
      return {
        icon: meta.icon,
        name: meta.nameKey.startsWith('courtDetail.') ? t(meta.nameKey) : item,
        desc: meta.descKey.startsWith('courtDetail.') ? t(meta.descKey) : item,
      }
    })
  }

  return [
    {
      icon: 'local_parking',
      name: t('courtDetail.amenityParking'),
      desc: t('courtDetail.amenityParkingDesc'),
    },
    { icon: 'wifi', name: t('courtDetail.amenityWifi'), desc: t('courtDetail.amenityWifiDesc') },
    {
      icon: 'shower',
      name: t('courtDetail.amenityShower'),
      desc: t('courtDetail.amenityShowerDesc'),
    },
    {
      icon: 'local_cafe',
      name: t('courtDetail.amenityCafe'),
      desc: t('courtDetail.amenityCafeDesc'),
    },
    {
      icon: 'sports_tennis',
      name: t('courtDetail.amenityShop'),
      desc: t('courtDetail.amenityShopDesc'),
    },
    {
      icon: 'weekend',
      name: t('courtDetail.amenityLounge'),
      desc: t('courtDetail.amenityLoungeDesc'),
    },
  ]
})
</script>

<template>
  <div class="court-amenities-section">
    <h3 class="section-title">
      <span class="material-symbols-outlined title-icon">room_service</span>
      <span>{{ $t('courtDetail.amenitiesTitle') }}</span>
    </h3>
    <div class="amenities-grid">
      <div v-for="a in displayedAmenities" :key="a.name" class="amenity-card">
        <span class="material-symbols-outlined a-icon">{{ a.icon }}</span>
        <strong class="a-name">{{ a.name }}</strong>
        <span class="a-desc">{{ a.desc }}</span>
      </div>
    </div>
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

@media (max-width: 768px) {
  .amenities-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useBookingStore } from '@/stores/booking'
import { formatCurrency } from '@/i18n'
import SkeletonLoader from '@/components/SkeletonLoader.vue'

const { t } = useI18n()
const store = useBookingStore()
const image = (index: number) => store.courts[index]?.image_url || `/aura/court-${index + 4}.jpg`

onMounted(async () => {
  try {
    await store.loadCourts()
  } catch {
    /* store exposes the error */
  }
})
</script>

<template>
  <section class="page-shell">
    <div class="eyebrow">{{ t('courts.eyebrow') }}</div>
    <h1 class="page-title">{{ t('courts.title') }}</h1>
    <p class="muted" style="max-width:560px">{{ t('courts.subtitle') }}</p>
    <p v-if="store.error" class="error">{{ store.error }}</p>

    <!-- Skeleton Loading State -->
    <div v-if="store.loading && !store.courts.length" class="court-grid" style="margin-top:38px">
      <SkeletonLoader type="court-card" :count="3" />
    </div>

    <!-- Loaded Courts Grid -->
    <div v-else class="court-grid" style="margin-top:38px">
      <RouterLink
        v-for="(court, index) in store.courts"
        :key="court.id"
        :to="`/courts/${court.id}`"
        class="court-card"
      >
        <div class="court-card-image">
          <img :src="image(index)" :alt="court.name" />
          <span class="tag">
            {{ t('courts.from') }} {{ formatCurrency(court.price_slots?.[0]?.price || 120000) }}{{ t('common.perHour') }}
          </span>
        </div>
        <div class="court-info">
          <h3>{{ court.name }}</h3>
          <p class="muted">
            {{ court.description || t('courts.defaultLocation') }} · <span class="badge">{{ t('courts.openStatus') }}</span>
          </p>
        </div>
      </RouterLink>
    </div>
  </section>
</template>

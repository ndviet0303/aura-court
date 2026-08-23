<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, errorMessage } from '@/lib/api'
import type { Venue } from '@/types'

const { t } = useI18n()
const route = useRoute()
const venue = ref<Venue | null>(null)
const error = ref('')
const copied = ref(false)

onMounted(async () => {
  try {
    venue.value = await api.get<Venue>(`/venues/${encodeURIComponent(String(route.params.slug))}`)
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  }
})

async function copyAddress(): Promise<void> {
  if (!venue.value?.address) return
  await navigator.clipboard.writeText(venue.value.address)
  copied.value = true
  window.setTimeout(() => { copied.value = false }, 1800)
}

function openMaps(): void {
  if (venue.value?.google_maps_url) window.open(venue.value.google_maps_url, '_blank', 'noopener,noreferrer')
}
</script>

<template>
  <section v-if="error" class="page-shell not-found">
    <div class="not-found-art">
      <strong>404</strong>
      <span>{{ t('notFound.badge') }}</span>
    </div>
    <div class="eyebrow">Aura Court</div>
    <h1 class="page-title">{{ t('notFound.title') }}</h1>
    <p class="muted">{{ t('notFound.subtitle') }}</p>
    <RouterLink to="/courts" class="btn">{{ t('courts.title') }}</RouterLink>
  </section>
  <section v-else-if="venue" class="page-shell venue-directions">
    <div class="eyebrow">{{ t('directions.eyebrow') }}</div>
    <h1 class="page-title">{{ venue.name }}</h1>
    <p class="muted">{{ t('directions.venueInfo') }}</p>
    <div class="panel venue-directions-card">
      <span class="feature-icon">location_on</span>
      <h2>{{ t('directions.address') }}</h2>
      <p class="venue-address">{{ venue.address || t('common.loading') }}</p>
      <div class="actions">
        <button type="button" class="btn" :disabled="!venue.google_maps_url" @click="openMaps">
          {{ t('directions.openGoogleMaps') }} <span class="material-symbols-outlined">open_in_new</span>
        </button>
        <button type="button" class="btn secondary" :disabled="!venue.address" @click="copyAddress">
          {{ copied ? t('payment.copySuccess') : t('directions.copyAddress') }}
        </button>
      </div>
    </div>
  </section>
  <section v-else class="page-shell">
    <p class="muted">{{ t('common.loading') }}</p>
  </section>
</template>

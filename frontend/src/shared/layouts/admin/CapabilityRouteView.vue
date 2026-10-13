<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import { useTenantCapabilities } from '@/features/admin/composables/useTenantCapabilities'

// A route with `meta.capability` (the legacy finance pages) renders only when that tenant capability is on;
// a direct link otherwise reports the feature as unavailable instead of an empty page.
const route = useRoute()
const { loaded, loading, resolutionStatus, isEnabled, fetchCapabilities } = useTenantCapabilities()
const capability = computed(() => route.meta.capability as string | undefined)

onMounted(() => {
  if (!loaded.value && !loading.value) fetchCapabilities()
})
</script>

<template>
  <RouterView v-if="!capability || isEnabled(capability)" />
  <div
    v-else-if="resolutionStatus === 'resolved' || resolutionStatus === 'failed'"
    class="capability-unavailable"
    role="status"
    data-testid="capability-unavailable"
  >
    <span class="material-symbols-outlined capability-unavailable__icon">block</span>
    <h2>{{ $t('admin.featureUnavailable') }}</h2>
    <p>{{ $t('admin.featureUnavailableDesc') }}</p>
  </div>
</template>

<style scoped>
.capability-unavailable {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 64px 16px;
  text-align: center;
  color: var(--color-text-muted, #6b7280);
}

.capability-unavailable__icon {
  font-size: 40px;
}
</style>

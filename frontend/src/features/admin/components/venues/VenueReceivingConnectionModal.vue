<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { venuePublicationApi, type OwnerReceivingConnection } from '../../api/venuePublicationApi'
import type { AdminVenueItem } from '../../composables/useAdminVenues'
import OwnerReceivingConnectionPanel from './OwnerReceivingConnectionPanel.vue'

const props = defineProps<{ venue: AdminVenueItem | null }>()
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const loading = ref(false)
const connection = ref<OwnerReceivingConnection | null>(null)
const loadError = ref<string | null>(null)

async function load() {
  const venue = props.venue
  if (!venue?.organization_id) return
  loading.value = true
  try {
    connection.value = (
      await venuePublicationApi.getReceivingConnection(venue.organization_id, venue.id)
    ).data
    loadError.value = null
  } catch (cause) {
    connection.value = null
    loadError.value = errorMessage(cause, t('admin.venues.receivingConnection.loadFailed'))
  } finally {
    loading.value = false
  }
}

watch(() => props.venue, load, { immediate: true })
</script>

<template>
  <Teleport to="body">
    <div
      v-if="venue"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
      @click.self="emit('close')"
    >
      <div
        class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-2xl overflow-hidden border border-gray-100 dark:border-gray-700 flex flex-col max-h-[90vh]"
      >
        <div
          class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between"
        >
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">
            {{ resolveLocalizedText(venue.name) }}
          </h3>
          <button
            type="button"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
            :aria-label="t('common.close')"
            @click="emit('close')"
          >
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-6 overflow-y-auto">
          <OwnerReceivingConnectionPanel
            v-if="venue.organization_id"
            :organization-id="venue.organization_id"
            :venue-id="venue.id"
            :connection="connection"
            :loading="loading"
            :load-error="loadError"
            @updated="(next) => (connection = next)"
            @refresh="load"
          />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { VenuePackageOverview } from '../types'

const props = defineProps<{ venue: VenuePackageOverview | null; busy: boolean }>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm'): void
}>()

const { t, d } = useI18n()
// D8: the owner must accept every consequence before the request carries confirm_consequences.
const accepted = ref(false)
watch(
  () => props.venue,
  () => (accepted.value = false),
)
</script>

<template>
  <div v-if="venue?.package" class="modal-backdrop" @click.self="emit('close')">
    <div
      class="modal-card modal-sm"
      role="dialog"
      aria-modal="true"
      aria-labelledby="terminate-package-title"
      data-testid="terminate-package-dialog"
    >
      <header class="modal-header">
        <h2 id="terminate-package-title" class="modal-title">
          {{ t('admin.packages.terminate.title', { venue: venue.venue_name }) }}
        </h2>
      </header>

      <div class="modal-body">
        <p>
          {{
            t('admin.packages.terminate.effect', {
              term: t('admin.packages.termLabel', { days: venue.package.term_days }),
              end: d(new Date(venue.package.ends_at), 'datetime'),
            })
          }}
        </p>
        <ul class="consequences" data-testid="terminate-package-consequences">
          <li>{{ t('admin.packages.terminate.lossOfTime') }}</li>
          <li>{{ t('admin.packages.terminate.noRefund') }}</li>
          <li>{{ t('admin.packages.terminate.noTransfer') }}</li>
        </ul>
        <label class="accept">
          <input v-model="accepted" type="checkbox" data-testid="terminate-package-accept" />
          <span>{{ t('admin.packages.terminate.confirmLabel') }}</span>
        </label>
      </div>

      <footer class="modal-footer">
        <AuraButton variant="secondary" :disabled="busy" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton
          variant="danger"
          :disabled="!accepted || busy"
          :loading="busy"
          data-testid="terminate-package-confirm"
          @click="emit('confirm')"
        >
          {{ t('admin.packages.terminate.submit') }}
        </AuraButton>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.modal-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.modal-body p {
  margin: 0;
}

.consequences {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
  padding-left: 20px;
  color: var(--error-text);
  font-weight: 700;
}

.accept {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  cursor: pointer;
}

.accept input {
  margin-top: 3px;
}

.modal-footer {
  justify-content: flex-end;
  gap: 8px;
}
</style>

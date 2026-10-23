<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { venueClockRange, venueDate } from '@/core/utils/venueTime'
import { AuraButton } from '@/shared/components'
import type { AffectedConfirm } from '../../composables/useAffectedConfirm'

// Design D7: every stop or schedule change that touches bookings is saved only after this acknowledgement.
const props = defineProps<{ state: AffectedConfirm }>()

const { t } = useI18n()
const acknowledged = ref(false)

// A fresh list (or a new change) always needs a fresh tick.
watch(
  () => props.state.list,
  () => (acknowledged.value = false),
)

const slot = (start: string, end: string) => `${venueDate(start)} · ${venueClockRange(start, end)}`
</script>

<template>
  <Teleport to="body">
    <div v-if="state.list" class="modal-backdrop affected-backdrop" @click.self="state.cancel">
      <div
        class="modal-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="affected-dialog-title"
      >
        <div class="modal-header">
          <h3 id="affected-dialog-title" class="modal-title">
            {{ t('admin.affected.dialogTitle') }}
          </h3>
          <button
            type="button"
            class="modal-close-btn"
            :aria-label="t('common.close')"
            @click="state.cancel"
          >
            ✕
          </button>
        </div>

        <div class="modal-body affected-body">
          <p class="affected-intro">{{ t('admin.affected.dialogIntro') }}</p>
          <p v-if="state.error" class="form-error" role="alert">{{ state.error }}</p>

          <template v-if="state.list.bookings.length">
            <h4 class="affected-heading">
              {{ t('admin.affected.bookingsTitle', { count: state.list.bookings.length }) }}
            </h4>
            <ul class="affected-items">
              <li v-for="booking in state.list.bookings" :key="booking.reservation_id">
                <strong>{{ booking.code }}</strong>
                <span>{{ booking.court_code ?? '—' }}</span>
                <span>{{ slot(booking.starts_at, booking.ends_at) }}</span>
                <span v-if="booking.customer_name || booking.customer_phone" class="muted">
                  {{ [booking.customer_name, booking.customer_phone].filter(Boolean).join(' · ') }}
                </span>
                <span v-if="booking.in_use" class="affected-tag">
                  {{ t('admin.affected.inUse') }}
                </span>
              </li>
            </ul>
          </template>

          <label class="affected-ack">
            <input v-model="acknowledged" type="checkbox" />
            <span>{{ t('admin.affected.acknowledgeLabel') }}</span>
          </label>
        </div>

        <div class="modal-footer">
          <AuraButton variant="outline" :disabled="state.busy" @click="state.cancel">
            {{ t('common.cancel') }}
          </AuraButton>
          <AuraButton
            variant="primary"
            :loading="state.busy"
            :disabled="!acknowledged"
            @click="state.confirm"
          >
            {{ t('admin.affected.acknowledgeSubmit') }}
          </AuraButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
/* Stacks over the stop or special-date dialog it answers. */
.affected-backdrop {
  z-index: 1100;
}

.affected-body {
  display: flex;
  flex-direction: column;
  gap: 10px;
  overflow-y: auto;
}

.affected-intro {
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(245, 158, 11, 0.1);
  color: #92400e;
  font-size: 13px;
}

.affected-heading {
  margin: 4px 0 0;
  font-size: 13px;
  font-weight: 700;
}

.affected-items {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 260px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.affected-items li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 12px;
  padding: 8px 10px;
  border: 1px solid var(--outline);
  border-radius: 10px;
  font-size: 12px;
}

.muted {
  color: var(--muted);
}

.affected-tag {
  padding: 1px 8px;
  border-radius: 999px;
  background: rgba(245, 158, 11, 0.14);
  color: #b45309;
  font-weight: 700;
}

.affected-ack {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
</style>

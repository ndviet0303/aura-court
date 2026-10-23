<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { SuspendVenueState } from '../../composables/useSuspendVenue'
import AffectedBookingsDialog from '../affected/AffectedBookingsDialog.vue'

// D13: the admin sees the impact before confirming; the D7 dialog lists the affected bookings on save.
const props = defineProps<{ state: SuspendVenueState }>()

const { t } = useI18n()

const reason = ref('')
watch(
  () => props.state.venueId,
  () => (reason.value = ''),
)

const length = computed(() => reason.value.trim().length)
const canSubmit = computed(
  () =>
    !props.state.busy &&
    !!props.state.preview &&
    !props.state.preview.already_suspended &&
    length.value >= 10 &&
    length.value <= 1000,
)
</script>

<template>
  <div
    v-if="state.venueId !== null"
    class="modal-backdrop"
    role="dialog"
    aria-modal="true"
    aria-labelledby="suspend-venue-title"
    @click.self="state.close"
  >
    <div class="modal-card">
      <div class="modal-header">
        <h3 id="suspend-venue-title" class="modal-title">
          {{ t('admin.suspension.dialogTitle') }}
        </h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('common.close')"
          @click="state.close"
        >
          ✕
        </button>
      </div>
      <div class="modal-body suspend-body">
        <p class="suspend-warning">{{ t('admin.suspension.dialogIntro') }}</p>
        <p v-if="state.error" class="form-error" role="alert">{{ state.error }}</p>
        <p v-if="state.previewing" class="admin-detail-muted">{{ t('common.loading') }}</p>
        <template v-else-if="state.preview">
          <p v-if="state.preview.already_suspended" class="form-error">
            {{ t('admin.suspension.alreadySuspended') }}
          </p>
          <p class="admin-detail-muted">
            {{ t('admin.suspension.courtCount', { count: state.preview.court_count }) }}
          </p>
          <p class="admin-detail-muted">
            {{ t('admin.suspension.impact', { bookings: state.preview.bookings.length }) }}
          </p>
        </template>
        <label class="form-group">
          <span class="form-label">{{ t('admin.suspension.reason') }} *</span>
          <textarea v-model="reason" rows="3" maxlength="1000" class="form-textarea" />
          <span class="form-hint">{{ t('admin.lifecycle.reasonHint', { count: length }) }}</span>
          <span v-if="state.fieldErrors.reason?.[0]" class="form-error">
            {{ state.fieldErrors.reason[0] }}
          </span>
        </label>
      </div>
      <div class="modal-footer">
        <AuraButton variant="secondary" :disabled="state.busy" @click="state.close">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton
          variant="danger"
          :loading="state.busy"
          :disabled="!canSubmit"
          @click="state.submit(reason.trim())"
        >
          {{ t('admin.suspension.confirm') }}
        </AuraButton>
      </div>
    </div>
  </div>
  <AffectedBookingsDialog :state="state.affected" />
</template>

<style scoped>
.suspend-body {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.suspend-warning {
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(239, 68, 68, 0.08);
  color: #b91c1c;
  font-size: 12px;
}
</style>

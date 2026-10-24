<script setup lang="ts">
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, DateTimePicker, SelectDropdown } from '@/shared/components'
import type { ManagedTournament } from '../../composables/useManagedTournament'
import type { Fixture } from '../../types/tournament'
import { fromPicker, toPicker } from '../../utils/tournament'

// UC-7.31: a court and a time inside the reserved court time (public) or the rentals (internal).
const props = defineProps<{ manage: ManagedTournament; fixture: Fixture }>()
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()
const { data, busy, error } = props.manage
const draft = reactive({
  court_id: props.fixture.court?.id ?? null,
  starts_at: toPicker(props.fixture.starts_at),
  ends_at: toPicker(props.fixture.ends_at),
})
const courtOptions = computed(() =>
  (data.value?.courts ?? []).map((c) => ({ value: c.id, label: c.code })),
)
const ready = computed(() => !!draft.court_id && !!draft.starts_at && !!draft.ends_at)

async function save() {
  if (!ready.value) return
  const ok = await props.manage.schedule(props.fixture, {
    court_id: draft.court_id!,
    starts_at: fromPicker(draft.starts_at)!,
    ends_at: fromPicker(draft.ends_at)!,
  })
  if (ok) emit('close')
}
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <form
      class="modal-card modal-sm"
      role="dialog"
      aria-modal="true"
      aria-labelledby="schedule-title"
      @submit.prevent="save"
    >
      <div class="modal-header">
        <h3 id="schedule-title" class="modal-title">{{ t('tournament.fixture.scheduleTitle') }}</h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div class="modal-body">
        <p class="admin-detail-muted">
          {{ fixture.entry_one?.name ?? t('tournament.fixture.tbd') }} –
          {{ fixture.entry_two?.name ?? t('tournament.fixture.tbd') }}
        </p>
        <div class="form-group">
          <span class="form-label">{{ t('tournament.fixture.court') }}</span>
          <SelectDropdown
            :model-value="draft.court_id"
            :options="courtOptions"
            variant="bordered"
            :placeholder="t('tournament.courts.pickCourt')"
            :aria-label="t('tournament.fixture.court')"
            @update:model-value="draft.court_id = Number($event)"
          />
        </div>
        <div class="form-group">
          <span class="form-label">{{ t('tournament.courts.from') }}</span>
          <DateTimePicker v-model="draft.starts_at" />
        </div>
        <div class="form-group">
          <span class="form-label">{{ t('tournament.courts.to') }}</span>
          <DateTimePicker v-model="draft.ends_at" />
        </div>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      </div>
      <div class="modal-footer">
        <AuraButton variant="outline" :disabled="busy" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton type="submit" :loading="busy" :disabled="!ready">
          {{ t('common.save') }}
        </AuraButton>
      </div>
    </form>
  </div>
</template>

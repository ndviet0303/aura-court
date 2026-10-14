<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { errorMessage } from '@/core/utils/errorMessage'
import { tournamentApi } from '@/features/tournament/api/tournamentApi'
import type { TournamentCustomerOption } from '@/features/tournament/types/tournament'

const props = defineProps<{
  show: boolean
  tournamentId: number
  paid: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'added'): void
}>()

const { t } = useI18n()
const query = ref('')
const results = ref<TournamentCustomerOption[] | null>(null)
const selected = ref<TournamentCustomerOption | null>(null)
const waiverReason = ref('')
const saving = ref(false)
const error = ref('')

// A paid tournament collects nothing on an organizer add, so the waiver reason is mandatory.
const canSubmit = computed(
  () => !!selected.value && (!props.paid || waiverReason.value.trim() !== '') && !saving.value,
)

watch(
  () => props.show,
  () => {
    query.value = ''
    results.value = null
    selected.value = null
    waiverReason.value = ''
    error.value = ''
  },
)

async function search() {
  if (!query.value.trim()) return
  error.value = ''
  try {
    results.value = await tournamentApi.searchCustomers(query.value)
  } catch (cause) {
    error.value = errorMessage(cause, t('admin.tournaments.addParticipantFailed'))
  }
}

async function submit() {
  if (!canSubmit.value || !selected.value) return
  saving.value = true
  error.value = ''
  try {
    await tournamentApi.addParticipant(props.tournamentId, {
      user_id: selected.value.user_id,
      ...(props.paid ? { waiver_reason: waiverReason.value.trim() } : {}),
    })
    emit('added')
  } catch (cause) {
    error.value = errorMessage(cause, t('admin.tournaments.addParticipantFailed'))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      aria-labelledby="add-participant-title"
      data-testid="tournament-add-participant"
    >
      <header class="modal-header">
        <h3 id="add-participant-title" class="modal-title">
          {{ t('admin.tournaments.addParticipantTitle') }}
        </h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          ✕
        </button>
      </header>

      <form class="modal-body" @submit.prevent="submit">
        <div class="search-row">
          <input
            v-model="query"
            type="search"
            class="form-input"
            :aria-label="t('admin.tournaments.addParticipantSearch')"
            :placeholder="t('admin.tournaments.addParticipantSearch')"
            @keydown.enter.prevent="search"
          />
          <AuraButton
            variant="secondary"
            size="sm"
            icon="search"
            :aria-label="t('admin.tournaments.addParticipantSearch')"
            @click="search"
          />
        </div>

        <p v-if="results && results.length === 0" class="muted">
          {{ t('admin.tournaments.addParticipantNoResult') }}
        </p>
        <ul v-else-if="results" class="result-list">
          <li v-for="customer in results" :key="customer.user_id">
            <label class="result-item">
              <input v-model="selected" type="radio" name="participant" :value="customer" />
              <span>{{ customer.name }}</span>
              <span class="muted font-mono">{{ customer.phone || customer.email }}</span>
            </label>
          </li>
        </ul>

        <div v-if="paid" class="form-group">
          <label class="form-label required" for="waiver-reason">
            {{ t('admin.tournaments.waiverReasonLabel') }}
          </label>
          <textarea
            id="waiver-reason"
            v-model="waiverReason"
            rows="2"
            maxlength="255"
            class="form-input"
            required
          ></textarea>
          <small class="muted">{{ t('admin.tournaments.waiverReasonHint') }}</small>
        </div>

        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      </form>

      <footer class="modal-footer">
        <AuraButton variant="secondary" :disabled="saving" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton variant="primary" :disabled="!canSubmit" :loading="saving" @click="submit">
          {{ t('admin.tournaments.addParticipantSubmit') }}
        </AuraButton>
      </footer>
    </section>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(0, 0, 0, 0.6);
}

.modal-card {
  width: 100%;
  max-width: 480px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e2e8f0);
  border-radius: 16px;
  overflow: hidden;
}

.modal-header,
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 14px 20px;
}

.modal-footer {
  justify-content: flex-end;
  border-top: 1px solid var(--outline, #e2e8f0);
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink, #0f172a);
}

.modal-close-btn {
  border: 0;
  background: transparent;
  color: var(--muted, #64748b);
  cursor: pointer;
}

.modal-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 0 20px 16px;
}

.search-row {
  display: flex;
  gap: 8px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #0f172a);
}

.form-label.required::after {
  content: ' *';
  color: #ef4444;
}

.form-input {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid var(--outline, #e2e8f0);
  border-radius: 10px;
  background: var(--surface, #ffffff);
  color: var(--ink, #0f172a);
  font: inherit;
}

.result-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-height: 220px;
  margin: 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;
}

.result-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border-radius: 8px;
  cursor: pointer;
}

.muted {
  margin: 0;
  font-size: 12px;
  color: var(--muted, #64748b);
}

.form-error {
  margin: 0;
  font-size: 12.5px;
  color: #dc2626;
}
</style>

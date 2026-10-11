<script setup lang="ts">
import { ref } from 'vue'
import { AuraButton } from '@/shared/components'
import type { TournamentItem, TournamentRegistrationPayload } from '../types/tournament'

defineProps<{
  show: boolean
  tournament: TournamentItem | null
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: TournamentRegistrationPayload): void
}>()

const name = ref('')
const phone = ref('')
const partnerName = ref('')
const skillLevel = ref<number>(3)
const notes = ref('')

function handleSubmit() {
  if (!name.value.trim() || !phone.value.trim()) return
  emit('submit', {
    name: name.value.trim(),
    phone: phone.value.trim(),
    partnerName: partnerName.value.trim() || undefined,
    skillLevel: skillLevel.value,
    notes: notes.value.trim() || undefined,
  })
  name.value = ''
  phone.value = ''
  partnerName.value = ''
  notes.value = ''
}
</script>

<template>
  <div v-if="show && tournament" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="header-title-wrap">
          <div class="header-ico-box">
            <span class="material-symbols-outlined">how_to_reg</span>
          </div>
          <div>
            <h3 class="modal-title">{{ $t('tournament.registerModalTitle') }}</h3>
            <p class="modal-sub">{{ $t('tournament.registerModalSub') }}</p>
          </div>
        </div>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="$t('common.close')"
          @click="emit('close')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <!-- Tournament Quick Specs -->
        <div class="tourney-summary-box">
          <strong class="tourney-name">{{ tournament.name }}</strong>
          <div class="summary-details">
            <span class="detail-tag">
              <span class="material-symbols-outlined mini-ico">sports_tennis</span>
              {{ tournament.category || tournament.sport }}
            </span>
            <span class="detail-tag">
              <span class="material-symbols-outlined mini-ico">payments</span>
              {{ $t('tournament.entryFeeLabel') }} {{ tournament.entry_fee || $t('payment.free') }}
            </span>
            <span class="detail-tag">
              <span class="material-symbols-outlined mini-ico">location_on</span>
              {{ tournament.venue }}
            </span>
          </div>
        </div>

        <!-- Registration Form -->
        <form class="reg-form" @submit.prevent="handleSubmit">
          <div class="form-group">
            <label class="form-label" for="reg-athlete-name">
              {{ $t('tournament.athleteCaptainLabel') }} <span class="required">*</span>
            </label>
            <div class="input-with-icon">
              <span class="material-symbols-outlined field-ico">person</span>
              <input
                id="reg-athlete-name"
                v-model="name"
                type="text"
                class="form-input"
                :placeholder="$t('tournament.athleteCaptainPlaceholder')"
                required
              />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="reg-athlete-phone">
              {{ $t('tournament.contactPhoneLabel') }} <span class="required">*</span>
            </label>
            <div class="input-with-icon">
              <span class="material-symbols-outlined field-ico">phone_in_talk</span>
              <input
                id="reg-athlete-phone"
                v-model="phone"
                type="tel"
                class="form-input"
                :placeholder="$t('tournament.contactPhonePlaceholder')"
                required
              />
            </div>
          </div>

          <div
            v-if="tournament.category?.includes('Đôi') || tournament.category?.includes('4vs4')"
            class="form-group"
          >
            <label class="form-label" for="reg-partner-name">
              {{ $t('tournament.partnerNameLabel') }}
            </label>
            <div class="input-with-icon">
              <span class="material-symbols-outlined field-ico">group_add</span>
              <input
                id="reg-partner-name"
                v-model="partnerName"
                type="text"
                class="form-input"
                :placeholder="$t('tournament.partnerNamePlaceholder')"
              />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="reg-notes">{{ $t('tournament.notesLabel') }}</label>
            <input
              id="reg-notes"
              v-model="notes"
              type="text"
              class="form-input"
              :placeholder="$t('tournament.notesPlaceholder')"
            />
          </div>
        </form>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton variant="ghost" size="md" @click="emit('close')">
          {{ $t('tournament.cancelBtn') }}
        </AuraButton>

        <AuraButton
          variant="primary"
          size="md"
          icon="check_circle"
          :disabled="!name.trim() || !phone.trim() || loading"
          :loading="loading"
          @click="handleSubmit"
        >
          {{ $t('tournament.confirmRegisterBtn') }}
        </AuraButton>
      </div>
    </div>
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
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  padding: 20px;
}

.modal-card {
  width: 100%;
  max-width: 520px;
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--outline);
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid var(--outline);
}

.header-title-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-ico-box {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.modal-sub {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.modal-close-btn {
  border: 0;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  padding: 6px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-close-btn:hover {
  color: var(--ink);
  background: rgba(0, 0, 0, 0.05);
}

.modal-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.tourney-summary-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px 16px;
  border-radius: 14px;
  background: var(--surface-container, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline);
}

.tourney-name {
  font-size: 14px;
  font-weight: 750;
  color: var(--ink);
}

.summary-details {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.detail-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11.5px;
  color: var(--muted);
}

.mini-ico {
  font-size: 14px;
  color: var(--pink-dark, #db2777);
}

.reg-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12.5px;
  font-weight: 650;
  color: var(--ink);
}

.required {
  color: #ef4444;
}

.input-with-icon {
  position: relative;
  display: flex;
  align-items: center;
}

.field-ico {
  position: absolute;
  left: 12px;
  font-size: 18px;
  color: var(--muted);
  pointer-events: none;
}

.form-input {
  width: 100%;
  padding: 10px 14px 10px 38px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  font-family: inherit;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

.form-input:focus {
  outline: none;
  border-color: var(--pink-dark);
  box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.15);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
  background: var(--surface-container-low, rgba(0, 0, 0, 0.01));
}
</style>

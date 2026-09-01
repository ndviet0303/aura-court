<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { FixtureMatch } from '../../types/tournamentAdmin'
import { AuraButton, SelectDropdown, type DropdownOption } from '@/shared/components'

const props = defineProps<{
  show: boolean
  match: FixtureMatch | null
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', match: FixtureMatch, scoreP1?: number, scoreP2?: number): void
}>()

const { t } = useI18n()

const courtName = ref('')
const scheduledTime = ref('')
const status = ref<string>('pending')
const score1 = ref<number>(21)
const score2 = ref<number>(18)
const selectedWinnerId = ref<number | undefined>(undefined)

const statusOptions = computed<DropdownOption[]>(() => [
  { value: 'pending', label: t('admin.tournaments.statusPending') || 'Chờ thi đấu' },
  { value: 'live', label: t('admin.tournaments.statusLive') || 'Đang trực tiếp' },
  { value: 'completed', label: t('admin.tournaments.statusCompleted') || 'Đã hoàn thành' },
  { value: 'walkover', label: t('admin.tournaments.statusWalkover') || 'Bỏ cuộc / Xử thua' },
])

watch(
  () => props.match,
  (m) => {
    if (m) {
      courtName.value = m.courtName || ''
      scheduledTime.value = m.scheduledTime || '09:00'
      status.value = m.status || 'pending'
      score1.value = m.player1?.sets?.[0] ?? 21
      score2.value = m.player2?.sets?.[0] ?? 18
      selectedWinnerId.value = m.winnerId || (score1.value >= score2.value ? m.player1?.id : m.player2?.id)
    }
  },
  { immediate: true }
)

function onScoreChange() {
  if (props.match) {
    if (score1.value > score2.value && props.match.player1?.id) {
      selectedWinnerId.value = props.match.player1.id
    } else if (score2.value > score1.value && props.match.player2?.id) {
      selectedWinnerId.value = props.match.player2.id
    }
  }
}

function handleSave() {
  if (props.match) {
    const updated: FixtureMatch = {
      ...props.match,
      courtName: courtName.value,
      scheduledTime: scheduledTime.value,
      status: status.value as any,
      winnerId: selectedWinnerId.value,
    }
    emit('save', updated, Number(score1.value), Number(score2.value))
  }
}
</script>

<template>
  <div v-if="show && match" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">edit_note</span>
          <h2 class="modal-title">
            {{ t('admin.tournaments.updateMatchModalTitle', { number: match.matchNumber, round: match.roundName }) }}
          </h2>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">{{ t('admin.tournaments.courtField') }}</label>
            <input v-model="courtName" type="text" class="form-input" />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.tournaments.scheduledTimeField') }}</label>
            <input v-model="scheduledTime" type="text" class="form-input font-mono" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.tournaments.matchStatusField') }}</label>
          <SelectDropdown
            v-model="status"
            :options="statusOptions"
            variant="bordered"
          />
        </div>

        <!-- Scores & Winner Picker -->
        <div class="match-score-inputs-grid">
          <div
            class="player-score-card"
            :class="{ 'is-selected-winner': selectedWinnerId === match.player1?.id }"
            @click="match.player1?.id && (selectedWinnerId = match.player1.id)"
          >
            <div class="player-card-top">
              <span class="player-label">{{ match.player1?.name || 'VĐV 1' }}</span>
              <span v-if="selectedWinnerId === match.player1?.id" class="winner-badge">
                <span class="material-symbols-outlined icon-14">emoji_events</span>
                Thắng
              </span>
            </div>
            <label class="score-sublabel">{{ t('admin.tournaments.scoreP1Label') }}</label>
            <input
              v-model.number="score1"
              type="number"
              min="0"
              max="99"
              class="form-input font-mono score-input"
              @input="onScoreChange"
            />
          </div>

          <div
            class="player-score-card"
            :class="{ 'is-selected-winner': selectedWinnerId === match.player2?.id }"
            @click="match.player2?.id && (selectedWinnerId = match.player2.id)"
          >
            <div class="player-card-top">
              <span class="player-label">{{ match.player2?.name || 'VĐV 2' }}</span>
              <span v-if="selectedWinnerId === match.player2?.id" class="winner-badge">
                <span class="material-symbols-outlined icon-14">emoji_events</span>
                Thắng
              </span>
            </div>
            <label class="score-sublabel">{{ t('admin.tournaments.scoreP2Label') }}</label>
            <input
              v-model.number="score2"
              type="number"
              min="0"
              max="99"
              class="form-input font-mono score-input"
              @input="onScoreChange"
            />
          </div>
        </div>

        <div class="advancement-notice-box">
          <span class="material-symbols-outlined icon-16 notice-icon">alt_route</span>
          <span>Người chiến thắng sẽ tự động được đưa vào nhánh đấu vòng kế tiếp (Bán kết / Chung kết).</span>
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="secondary" :disabled="saving" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton variant="primary" :disabled="saving" @click="handleSave">
          <span v-if="saving" class="material-symbols-outlined icon-16 spin">sync</span>
          <span v-else class="material-symbols-outlined icon-16">save</span>
          <span>{{ saving ? t('common.saving') : t('common.save') }}</span>
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e2e8f0);
  border-radius: 20px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid var(--outline, #e2e8f0);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-title-icon {
  font-size: 24px;
  color: var(--pink-dark, #db2777);
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #0f172a);
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 18px;
  cursor: pointer;
  color: var(--muted, #64748b);
}

.modal-body {
  padding: 20px 24px;
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
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #0f172a);
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline, #e2e8f0);
  background: var(--surface, #ffffff);
  color: var(--ink, #0f172a);
  font-size: 13px;
  outline: none;
  box-sizing: border-box;
  transition: border-color 0.15s;
}

.form-input:focus {
  border-color: var(--pink-dark, #db2777);
}

.form-row-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.match-score-inputs-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.player-score-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border-radius: 12px;
  border: 2px solid var(--outline, #e2e8f0);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  cursor: pointer;
  transition: all 0.2s;
}

.player-score-card:hover {
  border-color: var(--pink-dark, #db2777);
}

.player-score-card.is-selected-winner {
  border-color: #10b981;
  background: rgba(16, 185, 129, 0.06);
}

.player-card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.player-label {
  font-weight: 700;
  font-size: 13px;
  color: var(--ink, #0f172a);
}

.winner-badge {
  display: flex;
  align-items: center;
  gap: 4px;
  background: #10b981;
  color: #fff;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 800;
}

.score-sublabel {
  font-size: 11px;
  color: var(--muted, #64748b);
}

.score-input {
  font-size: 20px;
  font-weight: 800;
  text-align: center;
}

.advancement-notice-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  background: rgba(219, 39, 119, 0.08);
  border: 1px solid rgba(219, 39, 119, 0.2);
  color: var(--pink-dark, #db2777);
  font-size: 12px;
  font-weight: 600;
}

.notice-icon {
  color: var(--pink-dark, #db2777);
  font-size: 18px;
  flex-shrink: 0;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline, #e2e8f0);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.icon-16 {
  font-size: 16px;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}
</style>

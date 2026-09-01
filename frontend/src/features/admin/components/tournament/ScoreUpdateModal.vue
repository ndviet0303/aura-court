<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { FixtureMatch } from '../../types/tournamentAdmin'

const props = defineProps<{
  show: boolean
  match: FixtureMatch | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', match: FixtureMatch): void
}>()

const { t } = useI18n()

const courtName = ref('')
const scheduledTime = ref('')
const status = ref<'pending' | 'live' | 'completed' | 'walkover'>('pending')
const score1 = ref('')
const score2 = ref('')

watch(
  () => props.match,
  (m) => {
    if (m) {
      courtName.value = m.courtName
      scheduledTime.value = m.scheduledTime
      status.value = m.status
      score1.value = m.player1.sets.join('-') || '21-14'
      score2.value = m.player2.sets.join('-') || '18-21'
    }
  },
  { immediate: true }
)

function handleSave() {
  if (props.match) {
    const updated = {
      ...props.match,
      courtName: courtName.value,
      scheduledTime: scheduledTime.value,
      status: status.value,
    }
    emit('save', updated)
  }
}
</script>

<template>
  <div v-if="show && match" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">edit_note</span>
          <h2 class="modal-title font-mono">
            {{ t('admin.tournaments.updateMatchModalTitle', { number: match.matchNumber, round: match.roundName }) }}
          </h2>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">{{ t('admin.tournaments.courtField') }}</label>
          <input v-model="courtName" type="text" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.tournaments.scheduledTimeField') }}</label>
          <input v-model="scheduledTime" type="text" class="form-input font-mono" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.tournaments.matchStatusField') }}</label>
          <select v-model="status" class="form-input">
            <option value="pending">{{ t('admin.tournaments.statusPending') }}</option>
            <option value="live">{{ t('admin.tournaments.statusLive') }}</option>
            <option value="completed">{{ t('admin.tournaments.statusCompleted') }}</option>
            <option value="walkover">{{ t('admin.tournaments.statusWalkover') }}</option>
          </select>
        </div>

        <div class="match-score-inputs-grid">
          <div class="form-group">
            <label class="form-label">{{ t('admin.tournaments.playerScoresField', { name: match.player1.name }) }}</label>
            <input v-model="score1" type="text" class="form-input font-mono" placeholder="21, 21" />
          </div>
          <div class="form-group">
            <label class="form-label">{{ t('admin.tournaments.playerScoresField', { name: match.player2.name }) }}</label>
            <input v-model="score2" type="text" class="form-input font-mono" placeholder="18, 14" />
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">{{ t('common.cancel') }}</button>
        <button type="button" class="btn-primary btn-confirm" @click="handleSave">
          <span class="material-symbols-outlined icon-16">save</span>
          <span>{{ t('common.save') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.match-score-inputs-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.btn-cancel {
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

.icon-16 {
  font-size: 16px;
}
</style>

<script setup lang="ts">
import { ref, watch } from 'vue'
import type { FixtureMatch } from '../../types/tournamentAdmin'

const props = defineProps<{
  show: boolean
  match: FixtureMatch | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', match: FixtureMatch): void
}>()

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
        <h2>Cập Nhật Trận Đấu #{{ match.matchNumber }} ({{ match.roundName }})</h2>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Sân thi đấu:</label>
          <input v-model="courtName" type="text" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">Giờ dự kiến:</label>
          <input v-model="scheduledTime" type="text" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">Trạng thái trận đấu:</label>
          <select v-model="status" class="form-input">
            <option value="pending">Chờ thi đấu</option>
            <option value="live">Đang trực tiếp</option>
            <option value="completed">Đã hoàn thành</option>
            <option value="walkover">Bỏ cuộc / Xử thua</option>
          </select>
        </div>

        <div class="match-score-inputs-grid">
          <div class="form-group">
            <label class="form-label">{{ match.player1.name }} (Điểm các set):</label>
            <input v-model="score1" type="text" class="form-input" placeholder="21, 21" />
          </div>
          <div class="form-group">
            <label class="form-label">{{ match.player2.name }} (Điểm các set):</label>
            <input v-model="score2" type="text" class="form-input" placeholder="18, 14" />
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button class="btn-primary btn-confirm" @click="handleSave">Lưu Thay Đổi</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.match-score-inputs-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.modal-header h2 {
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
  padding: 8px 18px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>

<script setup lang="ts">
import type { MatchFixture } from '../../composables/useLiveScore'

defineProps<{
  match: MatchFixture
}>()

const emit = defineEmits<{
  (e: 'addPoint', playerIndex: 1 | 2): void
  (e: 'undoPoint'): void
}>()
</script>

<template>
  <div class="umpire-control-panel">
    <div class="panel-header">
      <h3 class="title">🎛️ Bảng Điều Khiển Trọng Tài (Umpire Console)</h3>
      <button class="btn-undo" @click="emit('undoPoint')">
        ↩️ Hoàn Tác Điểm
      </button>
    </div>

    <div class="control-actions-grid">
      <button class="btn-point-p1" @click="emit('addPoint', 1)">
        <span>+1 Điểm cho</span>
        <strong>{{ match.player1.name }}</strong>
      </button>

      <button class="btn-point-p2" @click="emit('addPoint', 2)">
        <span>+1 Điểm cho</span>
        <strong>{{ match.player2.name }}</strong>
      </button>
    </div>
  </div>
</template>

<style scoped>
.umpire-control-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
}

.btn-undo {
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 12px;
  cursor: pointer;
}

.control-actions-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.btn-point-p1, .btn-point-p2 {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 20px;
  border-radius: 14px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  cursor: pointer;
  transition: transform 0.1s;
}

.btn-point-p1:active, .btn-point-p2:active {
  transform: scale(0.98);
}

.btn-point-p1 span, .btn-point-p2 span {
  font-size: 12px;
  opacity: 0.9;
}

.btn-point-p1 strong, .btn-point-p2 strong {
  font-size: 16px;
}
</style>

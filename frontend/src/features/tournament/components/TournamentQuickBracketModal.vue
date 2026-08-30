<script setup lang="ts">
import { RouterLink } from 'vue-router'
import type { TournamentItem } from '../types/tournament'

defineProps<{
  show: boolean
  tournament: TournamentItem | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()
</script>

<template>
  <div v-if="show && tournament" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <h3 class="modal-title">🏆 Sơ Đồ Cây Nhánh Đấu: {{ tournament.name }}</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="bracket-preview-tree">
          <div class="bracket-round">
            <span class="round-hdr">Tứ Kết (Quarterfinals)</span>
            <div class="match-box">
              <div class="p-item winner">Nguyễn Tiến Minh (2)</div>
              <div class="p-item">Lê Đức Phát (0)</div>
            </div>
            <div class="match-box">
              <div class="p-item winner">Phạm Cao Cường (2)</div>
              <div class="p-item">Nguyễn Hải Đăng (1)</div>
            </div>
          </div>

          <div class="bracket-round">
            <span class="round-hdr">Bán Kết (Semifinals)</span>
            <div class="match-box">
              <div class="p-item winner">Nguyễn Tiến Minh (2)</div>
              <div class="p-item">Phạm Cao Cường (1)</div>
            </div>
          </div>

          <div class="bracket-round">
            <span class="round-hdr">Chung Kết (Finals)</span>
            <div class="match-box highlight-final">
              <div class="p-item winner">👑 Nguyễn Tiến Minh (2)</div>
              <div class="p-item">Trần Minh Quân (0)</div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <RouterLink
          v-if="tournament.slug"
          :to="`/tournaments/${tournament.slug}/bracket`"
          class="btn-full-bracket"
        >
          Xem Cây Nhánh Đầy Đủ & Điểm Số
        </RouterLink>
        <button class="btn-cancel" @click="emit('close')">Đóng</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bracket-preview-tree {
  display: flex;
  justify-content: space-around;
  gap: 16px;
  padding: 20px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow-x: auto;
}

.bracket-round {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 180px;
  justify-content: space-around;
}

.round-hdr {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  text-align: center;
}

.match-box {
  display: flex;
  flex-direction: column;
  background: var(--surface);
  border-radius: 8px;
  border: 1px solid var(--outline);
  overflow: hidden;
  font-size: 12px;
}

.highlight-final {
  border-color: #f59e0b;
}

.p-item {
  padding: 6px 10px;
  border-bottom: 1px solid var(--outline);
}

.p-item:last-child {
  border-bottom: 0;
}

.p-item.winner {
  font-weight: 700;
  color: var(--pink-dark, #db2777);
}

.btn-full-bracket {
  padding: 8px 18px;
  border-radius: 8px;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}
</style>

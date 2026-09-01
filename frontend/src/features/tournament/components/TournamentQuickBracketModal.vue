<script setup lang="ts">
import { AuraButton } from '@/shared/components'
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
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="header-title-wrap">
          <div class="header-ico-box">
            <span class="material-symbols-outlined">account_tree</span>
          </div>
          <div>
            <h3 class="modal-title">Sơ Đồ Nhánh Đấu Nhanh: {{ tournament.name }}</h3>
            <p class="modal-sub">Xem nhanh kết quả các vòng tứ kết, bán kết và chung kết giải đấu.</p>
          </div>
        </div>
        <button type="button" class="modal-close-btn" aria-label="Đóng" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <div class="bracket-preview-tree">
          <!-- Round 1: Quarter Finals -->
          <div class="bracket-round">
            <span class="round-hdr">Tứ Kết (Quarterfinals)</span>
            <div class="match-box">
              <div class="p-item winner">
                <span class="p-name">Nguyễn Tiến Minh</span>
                <strong class="p-score font-mono">2</strong>
              </div>
              <div class="p-item">
                <span class="p-name">Lê Đức Phát</span>
                <strong class="p-score font-mono">0</strong>
              </div>
            </div>

            <div class="match-box">
              <div class="p-item winner">
                <span class="p-name">Phạm Cao Cường</span>
                <strong class="p-score font-mono">2</strong>
              </div>
              <div class="p-item">
                <span class="p-name">Nguyễn Hải Đăng</span>
                <strong class="p-score font-mono">1</strong>
              </div>
            </div>
          </div>

          <!-- Round 2: Semi Finals -->
          <div class="bracket-round">
            <span class="round-hdr">Bán Kết (Semifinals)</span>
            <div class="match-box">
              <div class="p-item winner">
                <span class="p-name">Nguyễn Tiến Minh</span>
                <strong class="p-score font-mono">2</strong>
              </div>
              <div class="p-item">
                <span class="p-name">Phạm Cao Cường</span>
                <strong class="p-score font-mono">1</strong>
              </div>
            </div>
          </div>

          <!-- Round 3: Finals -->
          <div class="bracket-round">
            <span class="round-hdr highlight">Chung Kết (Finals)</span>
            <div class="match-box highlight-final">
              <div class="p-item winner">
                <div class="p-name-champ">
                  <span class="material-symbols-outlined champ-ico">emoji_events</span>
                  <span>Nguyễn Tiến Minh</span>
                </div>
                <strong class="p-score font-mono">2</strong>
              </div>
              <div class="p-item">
                <span class="p-name">Trần Minh Quân</span>
                <strong class="p-score font-mono">0</strong>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton variant="ghost" size="md" @click="emit('close')">
          Đóng
        </AuraButton>

        <AuraButton
          :to="`/tournaments/bracket?id=${tournament.id}`"
          variant="primary"
          size="md"
          icon-right="arrow_forward"
        >
          Xem Cây Nhánh Đầy Đủ & Điểm Số
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
  max-width: 780px;
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
}

.bracket-preview-tree {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  padding: 18px;
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  border-radius: 16px;
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
  font-weight: 800;
  color: var(--muted);
  text-align: center;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding-bottom: 4px;
}

.round-hdr.highlight {
  color: var(--pink-dark, #db2777);
}

.match-box {
  display: flex;
  flex-direction: column;
  background: var(--surface);
  border-radius: 10px;
  border: 1px solid var(--outline);
  overflow: hidden;
  font-size: 12.5px;
}

.highlight-final {
  border-color: rgba(201, 161, 90, 0.5);
  box-shadow: 0 4px 16px rgba(201, 161, 90, 0.15);
}

.p-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  border-bottom: 1px solid var(--outline);
}

.p-item:last-child {
  border-bottom: 0;
}

.p-item.winner {
  font-weight: 750;
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.04);
}

.p-name-champ {
  display: flex;
  align-items: center;
  gap: 6px;
}

.champ-ico {
  font-size: 16px;
  color: var(--accent, #c9a15a);
}

.p-score {
  font-size: 13px;
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


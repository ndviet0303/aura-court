<script setup lang="ts">
import { AuraButton } from '@/shared/components'
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
      <div class="hdr-title-wrap">
        <div class="hdr-ico-box">
          <span class="material-symbols-outlined">tune</span>
        </div>
        <div>
          <h3 class="title">Bảng Điều Khiển Trọng Tài (Umpire Console)</h3>
          <span class="sub">Chấm điểm trực tiếp trên sân cho từng pha cầu của VĐV</span>
        </div>
      </div>

      <AuraButton
        variant="secondary"
        size="sm"
        icon="undo"
        @click="emit('undoPoint')"
      >
        Hoàn Tác Điểm
      </AuraButton>
    </div>

    <div class="control-actions-grid">
      <button type="button" class="btn-point-p1" @click="emit('addPoint', 1)">
        <span class="btn-lbl">+1 Điểm Cho</span>
        <strong class="btn-name">{{ match.player1.name }}</strong>
      </button>

      <button type="button" class="btn-point-p2" @click="emit('addPoint', 2)">
        <span class="btn-lbl">+1 Điểm Cho</span>
        <strong class="btn-name">{{ match.player2.name }}</strong>
      </button>
    </div>
  </div>
</template>

<style scoped>
.umpire-control-panel {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 22px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.hdr-title-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
}

.hdr-ico-box {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: rgba(236, 72, 153, 0.12);
  color: var(--pink-dark, #db2777);
  display: flex;
  align-items: center;
  justify-content: center;
}

.title {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
  color: var(--ink);
}

.sub {
  font-size: 11.5px;
  color: var(--muted);
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
  justify-content: center;
  gap: 6px;
  padding: 22px 16px;
  border-radius: 16px;
  border: 0;
  background: linear-gradient(135deg, var(--primary, #6b2d3f) 0%, var(--pink-dark, #db2777) 100%);
  color: #fff;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(236, 72, 153, 0.25);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.btn-point-p1:hover, .btn-point-p2:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(236, 72, 153, 0.35);
}

.btn-point-p1:active, .btn-point-p2:active {
  transform: scale(0.98);
}

.btn-lbl {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  opacity: 0.9;
}

.btn-name {
  font-size: 16px;
  font-weight: 850;
}
</style>


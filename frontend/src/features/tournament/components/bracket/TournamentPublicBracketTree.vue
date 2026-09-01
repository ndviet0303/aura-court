<script setup lang="ts">
import { ref } from 'vue'
import type { BracketMatch } from '../../composables/useTournamentBracket'

defineProps<{
  quarters: BracketMatch[]
  semis: BracketMatch[]
  finalMatch: BracketMatch | null
}>()

const emit = defineEmits<{
  (e: 'selectMatch', match: BracketMatch): void
}>()

const treeContainer = ref<HTMLElement | null>(null)
const isDragging = ref(false)
const hasDragged = ref(false)
const startX = ref(0)
const scrollLeft = ref(0)

function onMouseDown(e: MouseEvent) {
  if (!treeContainer.value) return
  isDragging.value = true
  hasDragged.value = false
  startX.value = e.pageX - treeContainer.value.offsetLeft
  scrollLeft.value = treeContainer.value.scrollLeft
}

function onMouseMove(e: MouseEvent) {
  if (!isDragging.value || !treeContainer.value) return
  e.preventDefault()
  const x = e.pageX - treeContainer.value.offsetLeft
  const walk = (x - startX.value) * 1.3
  if (Math.abs(walk) > 5) {
    hasDragged.value = true
  }
  treeContainer.value.scrollLeft = scrollLeft.value - walk
}

function onMouseUp() {
  isDragging.value = false
  setTimeout(() => {
    hasDragged.value = false
  }, 60)
}

function onMouseLeave() {
  isDragging.value = false
}

function handleSelectMatch(match: BracketMatch) {
  if (hasDragged.value) return
  emit('selectMatch', match)
}
</script>

<template>
  <div v-if="quarters.length === 0 && semis.length === 0 && !finalMatch" class="empty-bracket-card">
    <div class="empty-ico-box">
      <span class="material-symbols-outlined">account_tree</span>
    </div>
    <h4 class="empty-title">Chưa Có Sơ Đồ Nhánh Đấu</h4>
    <p class="empty-sub">
      Ban tổ chức đang sắp xếp hạt giống hoặc giải đấu đang trong giai đoạn mở đăng ký VĐV.
    </p>
  </div>

  <div
    v-else
    ref="treeContainer"
    class="bracket-tree-wrapper"
    :class="{ 'is-dragging': isDragging }"
    @mousedown="onMouseDown"
    @mousemove="onMouseMove"
    @mouseup="onMouseUp"
    @mouseleave="onMouseLeave"
  >
    <!-- Quarter Finals Column -->
    <div class="bracket-column">
      <h3 class="col-header">TỨ KẾT (Quarter-Finals)</h3>
      <div class="matches-stack">
        <div
          v-for="m in quarters"
          :key="m.id"
          class="bracket-match-node"
          @click="handleSelectMatch(m)"
        >
          <div class="node-meta">
            <span>{{ m.label }} · {{ m.time }}</span>
            <span class="court-txt">{{ m.court }}</span>
          </div>

          <div class="player-slot" :class="{ winner: m.player1.isWinner }">
            <div class="p-details">
              <img :src="m.player1.avatar" class="p-avt" />
              <span class="p-name">{{ m.player1.name }}</span>
            </div>
            <strong class="p-score font-mono">{{ m.player1.score }}</strong>
          </div>

          <div class="player-slot" :class="{ winner: m.player2.isWinner }">
            <div class="p-details">
              <img :src="m.player2.avatar" class="p-avt" />
              <span class="p-name">{{ m.player2.name }}</span>
            </div>
            <strong class="p-score font-mono">{{ m.player2.score }}</strong>
          </div>
        </div>
      </div>
    </div>

    <!-- Semi Finals Column -->
    <div class="bracket-column">
      <h3 class="col-header">BÁN KẾT (Semi-Finals)</h3>
      <div class="matches-stack">
        <div
          v-for="m in semis"
          :key="m.id"
          class="bracket-match-node"
          @click="handleSelectMatch(m)"
        >
          <div class="node-meta">
            <span>{{ m.label }} · {{ m.time }}</span>
            <span class="court-txt">{{ m.court }}</span>
          </div>

          <div class="player-slot" :class="{ winner: m.player1.isWinner }">
            <div class="p-details">
              <img :src="m.player1.avatar" class="p-avt" />
              <span class="p-name">{{ m.player1.name }}</span>
            </div>
            <strong class="p-score font-mono">{{ m.player1.score }}</strong>
          </div>

          <div class="player-slot" :class="{ winner: m.player2.isWinner }">
            <div class="p-details">
              <img :src="m.player2.avatar" class="p-avt" />
              <span class="p-name">{{ m.player2.name }}</span>
            </div>
            <strong class="p-score font-mono">{{ m.player2.score }}</strong>
          </div>
        </div>
      </div>
    </div>

    <!-- Final Match Column -->
    <div class="bracket-column">
      <h3 class="col-header highlight">
        <span class="material-symbols-outlined col-header-ico">emoji_events</span>
        <span>CHUNG KẾT (Finals)</span>
      </h3>
      <div class="matches-stack">
        <div
          v-if="finalMatch"
          class="bracket-match-node finals-node"
          @click="handleSelectMatch(finalMatch)"
        >
          <div class="node-meta">
            <span class="live-tag">
              <span class="live-pulse-dot"></span>
              LIVE · {{ finalMatch.time }}
            </span>
            <span class="court-txt">{{ finalMatch.court }}</span>
          </div>

          <div class="player-slot" :class="{ winner: finalMatch.player1.isWinner }">
            <div class="p-details">
              <img :src="finalMatch.player1.avatar" class="p-avt" />
              <span class="p-name">{{ finalMatch.player1.name }}</span>
            </div>
            <strong class="p-score font-mono">{{ finalMatch.player1.score }}</strong>
          </div>

          <div class="player-slot" :class="{ winner: finalMatch.player2.isWinner }">
            <div class="p-details">
              <img :src="finalMatch.player2.avatar" class="p-avt" />
              <span class="p-name">{{ finalMatch.player2.name }}</span>
            </div>
            <strong class="p-score font-mono">{{ finalMatch.player2.score }}</strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>



<style scoped>
.bracket-tree-wrapper {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  overflow-x: auto;
  padding: 10px 2px 14px;
  cursor: grab;
  user-select: none;
  -webkit-user-select: none;
  scroll-behavior: smooth;
}

.bracket-tree-wrapper.is-dragging {
  cursor: grabbing;
  scroll-behavior: auto;
}

.bracket-tree-wrapper::-webkit-scrollbar {
  height: 5px;
}

.bracket-tree-wrapper::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.04);
  border-radius: 999px;
}

:global(html.dark .bracket-tree-wrapper::-webkit-scrollbar-track) {
  background: rgba(255, 255, 255, 0.06);
}

.bracket-tree-wrapper::-webkit-scrollbar-thumb {
  background: rgba(219, 39, 119, 0.3);
  border-radius: 999px;
}

.bracket-tree-wrapper::-webkit-scrollbar-thumb:hover {
  background: var(--pink-dark, #db2777);
}

.bracket-column {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 280px;
}

.col-header {
  margin: 0;
  font-size: 13px;
  font-weight: 800;
  color: var(--muted);
  text-align: center;
  padding: 8px;
  background: var(--surface);
  border-radius: 8px;
  border: 1px solid var(--outline);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.col-header-ico {
  font-size: 18px;
  color: var(--accent, #c9a15a);
}

.col-header.highlight {
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.05);
  border-color: rgba(236, 72, 153, 0.2);
}

.matches-stack {
  display: flex;
  flex-direction: column;
  gap: 16px;
  justify-content: space-around;
  flex: 1;
}

.bracket-match-node {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  overflow: hidden;
  cursor: pointer;
  transition: all 0.15s;
}

.bracket-match-node:hover {
  border-color: var(--pink-dark, #db2777);
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
}

.finals-node {
  border-color: rgba(236, 72, 153, 0.4);
  box-shadow: 0 4px 16px rgba(236, 72, 153, 0.08);
}

.node-meta {
  display: flex;
  justify-content: space-between;
  padding: 6px 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 10px;
  color: var(--muted);
}

.live-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #ef4444;
  font-weight: 800;
}

.live-pulse-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #ef4444;
  animation: pulse-dot 1.4s infinite;
}

@keyframes pulse-dot {
  0% { transform: scale(0.9); opacity: 1; }
  50% { transform: scale(1.4); opacity: 0.5; }
  100% { transform: scale(0.9); opacity: 1; }
}


.player-slot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
}

.player-slot:last-child {
  border-bottom: 0;
}

.player-slot.winner {
  font-weight: 700;
  background: rgba(16, 185, 129, 0.04);
}

.p-details {
  display: flex;
  align-items: center;
  gap: 8px;
}

.p-avt {
  width: 24px;
  height: 24px;
  border-radius: 50%;
}

.p-score {
  font-size: 13px;
  color: var(--pink-dark, #db2777);
}

.empty-bracket-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 24px;
  text-align: center;
  background: var(--surface);
  border-radius: 20px;
  border: 1px dashed var(--outline);
}

.empty-ico-box {
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: var(--surface-container, rgba(0, 0, 0, 0.04));
  color: var(--muted);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 14px;
}

.empty-ico-box span {
  font-size: 28px;
}

.empty-title {
  margin: 0 0 6px;
  font-size: 16px;
  font-weight: 750;
  color: var(--ink);
}

.empty-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
  max-width: 420px;
}
</style>


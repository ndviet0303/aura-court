<script setup lang="ts">
import { computed } from 'vue'
import { AuraButton } from '@/shared/components'
import { getAvatarUrl } from '@/lib/avatar'
import type { TournamentItem } from '../types/tournament'

const props = defineProps<{
  show: boolean
  tournament: TournamentItem | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const participantsList = computed(() => {
  if (!props.tournament?.participants) return []
  return props.tournament.participants.slice(0, 8)
})
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
            <h3 class="modal-title">
              {{ $t('tournament.quickBracketTitle', { name: tournament.name }) }}
            </h3>
            <p class="modal-sub">{{ $t('tournament.quickBracketSub') }}</p>
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
        <div class="tournament-summary-strip">
          <div v-if="tournament.venue" class="summary-item">
            <span class="material-symbols-outlined s-ico">location_on</span>
            <span>{{ tournament.venue }}</span>
          </div>
          <div v-if="tournament.dates" class="summary-item">
            <span class="material-symbols-outlined s-ico">calendar_month</span>
            <span>{{ tournament.dates }}</span>
          </div>
          <div class="summary-item">
            <span class="material-symbols-outlined s-ico">group</span>
            <span>{{ tournament.current_slots }}/{{ tournament.max_slots }} VĐV</span>
          </div>
          <div v-if="tournament.prize_pool" class="summary-item">
            <span class="material-symbols-outlined s-ico">payments</span>
            <span class="text-pink font-bold">{{ tournament.prize_pool }}</span>
          </div>
        </div>

        <div v-if="participantsList.length > 0" class="participants-preview-section">
          <h4 class="sec-title">{{ $t('tournament.seedsListTitle') }}</h4>
          <div class="participants-grid">
            <div
              v-for="(p, idx) in participantsList"
              :key="p.id || idx"
              class="participant-mini-card"
            >
              <span v-if="p.seed" class="seed-num font-mono">#{{ p.seed }}</span>
              <img :src="p.avatar || getAvatarUrl(p.name)" :alt="p.name" class="p-mini-avt" />
              <div class="p-mini-info">
                <span class="p-mini-name">{{ p.name }}</span>
                <span v-if="p.club" class="p-mini-club">{{ p.club }}</span>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="empty-preview-box">
          <span class="material-symbols-outlined">info</span>
          <span>{{ $t('tournament.openFullBracketHelp') }}</span>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton variant="ghost" size="md" @click="emit('close')">
          {{ $t('common.close') }}
        </AuraButton>

        <AuraButton
          :to="`/tournaments/${tournament.uuid || tournament.id}/bracket`"
          variant="primary"
          size="md"
          icon-right="arrow_forward"
          @click="emit('close')"
        >
          {{ $t('tournament.viewFullBracketBtn') }}
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
  max-width: 720px;
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
  gap: 18px;
}

.tournament-summary-strip {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 10px;
  padding: 12px 16px;
  border-radius: 12px;
  background: var(--surface-container, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline);
  font-size: 12px;
}

.summary-item {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--ink);
}

.s-ico {
  font-size: 16px;
  color: var(--pink-dark, #db2777);
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.font-bold {
  font-weight: 800;
}

.participants-preview-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.sec-title {
  margin: 0;
  font-size: 13px;
  font-weight: 750;
  color: var(--muted);
}

.participants-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.participant-mini-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-radius: 10px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.seed-num {
  font-size: 11px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
}

.p-mini-avt {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  object-fit: cover;
}

.p-mini-info {
  display: flex;
  flex-direction: column;
  gap: 1px;
  overflow: hidden;
}

.p-mini-name {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.p-mini-club {
  font-size: 11px;
  color: var(--muted);
}

.empty-preview-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  border: 1px dashed var(--outline);
  color: var(--muted);
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

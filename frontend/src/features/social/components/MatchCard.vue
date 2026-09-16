<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { AuraButton } from '@/shared/components'
import type { SocialMatch } from '../types/social'

const { t } = useI18n()

const props = defineProps<{
  match: SocialMatch
}>()

const emit = defineEmits<{
  (e: 'join', match: SocialMatch): void
  (e: 'detail', match: SocialMatch): void
  (e: 'view-bill', match: SocialMatch): void
}>()

const remainingSlots = computed(() => {
  return Math.max(0, props.match.required_participants - props.match.joined_count)
})

const isFull = computed(() => {
  return props.match.joined_count >= props.match.required_participants
})
</script>

<template>
  <article class="match-card" :class="{ 'is-full': isFull, 'is-joined': match.is_joined }">
    <!-- Header: Sport & Status -->
    <div class="card-header">
      <div class="sport-badge">
        <span class="material-symbols-outlined sport-ico">{{ match.sport_icon }}</span>
        <span class="sport-name">{{ match.sport_name }}</span>
        <span v-if="match.is_owner_hosted" class="owner-session-badge" style="margin-left: 6px; padding: 2px 6px; background: #8b263e; color: #fff; border-radius: 4px; font-size: 11px; font-weight: 700;">
          Chủ sân · {{ match.category || 'Vãng lai' }}
        </span>
        <span v-if="match.game_format" class="format-dot">·</span>
        <span v-if="match.game_format" class="format-txt">{{ match.game_format }}</span>
      </div>
      <div class="slots-status-badge" :class="{ full: isFull, open: !isFull }">
        <span class="material-symbols-outlined status-ico">{{ isFull ? 'check_circle' : 'group_add' }}</span>
        <span>{{ isFull ? $t('matchmaking.fullBadge') : $t('matchmaking.slotsStatusNeed', { count: remainingSlots }) }}</span>
      </div>
    </div>

    <!-- Match Title & Host -->
    <div class="card-body">
      <h3 class="match-title" :title="match.title">
        {{ match.title }}
      </h3>

      <div class="host-profile-row">
        <div class="host-avatar-box">
          <img v-if="match.host_avatar" :src="match.host_avatar" :alt="match.host_name" class="avatar-img" />
          <span v-else class="avatar-fallback">{{ match.host_name.charAt(0).toUpperCase() }}</span>
        </div>
        <div class="host-text-col">
          <div class="host-name-row">
            <span class="host-name">{{ match.host_name }}</span>
            <span v-if="match.host_reputation" class="reputation-badge" :title="$t('matchmaking.reputationScoreTitle')">
              <span class="material-symbols-outlined rep-ico">verified</span>
              {{ match.host_reputation }}%
            </span>
          </div>
          <span class="host-sub">{{ match.is_owner_hosted ? 'Chủ cơ sở tổ chức' : $t('matchmaking.hostLabel') }} · {{ match.city }}</span>
        </div>
      </div>

      <!-- Info Details Grid -->
      <div class="match-meta-list">
        <div class="meta-item">
          <span class="material-symbols-outlined meta-ico">location_on</span>
          <span class="meta-val">
            <strong>{{ match.venue_name }}</strong>
            <span v-if="match.court_name" class="court-sub"> · {{ match.court_name }}</span>
          </span>
        </div>

        <div class="meta-item">
          <span class="material-symbols-outlined meta-ico">schedule</span>
          <span class="meta-val font-mono">{{ match.starts_at }}</span>
        </div>

        <div class="meta-item-row">
          <div class="meta-item">
            <span class="material-symbols-outlined meta-ico">signal_cellular_alt</span>
            <span v-if="match.is_owner_hosted" class="meta-val">
              Trình độ: <strong>{{ (match.skill_levels && match.skill_levels.length) ? match.skill_levels.join(', ') : 'Giao lưu' }}</strong>
            </span>
            <span v-else class="meta-val">Level {{ match.min_skill || 1 }} - {{ match.max_skill || 5 }}</span>
          </div>

          <div class="meta-item">
            <span class="material-symbols-outlined meta-ico">payments</span>
            <span v-if="match.is_owner_hosted" class="meta-val font-mono highlight">
              {{ match.cost_per_person ? 'Ước tính ' + formatCurrency(match.cost_per_person) : 'Chia đều sau buổi' }}
            </span>
            <span v-else class="meta-val font-mono highlight">{{ match.cost_per_person ? formatCurrency(match.cost_per_person) : $t('matchmaking.freeCost') }}</span>
          </div>
        </div>
      </div>
      <!-- Slots Progress Visualizer -->
      <div class="slots-visualizer">
        <div class="slots-header">
          <span class="slots-label">{{ $t('matchmaking.joinedListLabel') }}</span>
          <span class="slots-count font-mono">{{ match.joined_count }} / {{ match.required_participants }}</span>
        </div>

        <div class="slots-track">
          <div
            v-for="idx in match.required_participants"
            :key="idx"
            class="slot-bubble"
            :class="{
              filled: idx <= match.joined_count,
              host: !match.is_owner_hosted && idx === 1,
              empty: idx > match.joined_count
            }"
            :title="idx <= match.joined_count ? (match.participants[idx - 1]?.name || $t('matchmaking.slotFilledTitle')) : $t('matchmaking.slotEmptyTitle')"
          >
            <span v-if="!match.is_owner_hosted && idx === 1" class="material-symbols-outlined bubble-ico">crown</span>
            <span v-else-if="idx <= match.joined_count" class="material-symbols-outlined bubble-ico">person</span>
            <span v-else class="material-symbols-outlined bubble-ico">add</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer Actions -->
    <div class="card-footer">
      <AuraButton
        v-if="match.is_owner_hosted && (match.is_host || match.status === 'ended' || match.status === 'completed' || match.is_joined)"
        variant="secondary"
        size="md"
        icon="receipt_long"
        block
        style="margin-bottom: 8px;"
        @click="emit('view-bill', match)"
      >
        {{ (match.status === 'ended' || match.status === 'completed') ? 'Xem bảng chi phí' : (match.is_host ? 'Quản lý buổi / Chốt bill' : 'Chi tiết buổi') }}
      </AuraButton>

      <AuraButton
        v-if="match.is_joined"
        variant="secondary"
        size="md"
        icon="check_circle"
        block
        disabled
      >
        {{ $t('matchmaking.alreadyJoinedBtn') }}
      </AuraButton>

      <AuraButton
        v-else-if="match.status === 'ended' || match.status === 'completed'"
        variant="ghost"
        size="md"
        icon="flag"
        block
        disabled
      >
        Buổi chơi đã kết thúc
      </AuraButton>

      <AuraButton
        v-else
        :variant="isFull ? 'ghost' : 'primary'"
        size="md"
        :icon="isFull ? 'visibility' : 'group_add'"
        block
        :disabled="isFull"
        @click="emit('join', match)"
      >
        {{ isFull ? $t('matchmaking.matchFullBtn') : (match.is_owner_hosted ? 'Đăng ký tham gia buổi' : $t('matchmaking.joinThisMatchBtn')) }}
      </AuraButton>
    </div>
  </article>
</template>

<style scoped>
.match-card {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 18px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 18px;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
  transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.match-card:hover {
  transform: translateY(-2px);
  border-color: rgba(219, 39, 119, 0.4);
  box-shadow: 0 8px 24px rgba(219, 39, 119, 0.08);
}

.match-card.is-full {
  opacity: 0.88;
}

.match-card.is-joined {
  border-color: rgba(16, 185, 129, 0.5);
  background: linear-gradient(180deg, var(--surface) 0%, rgba(16, 185, 129, 0.02) 100%);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.sport-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 8px;
  background: rgba(219, 39, 119, 0.08);
  color: var(--pink-dark, #db2777);
  font-size: 11.5px;
  font-weight: 750;
}

.sport-ico {
  font-size: 15px;
}

.format-dot {
  opacity: 0.5;
}

.format-txt {
  font-weight: 600;
  color: var(--ink);
}

.slots-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 750;
  white-space: nowrap;
}

.slots-status-badge.open {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.slots-status-badge.full {
  background: var(--surface-container, rgba(0, 0, 0, 0.06));
  color: var(--muted);
}

.status-ico {
  font-size: 14px;
}

.card-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
  flex: 1;
}

.match-title {
  margin: 0;
  font-size: 15.5px;
  font-weight: 750;
  line-height: 1.4;
  color: var(--ink);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.host-profile-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-bottom: 10px;
  border-bottom: 1px dashed var(--outline);
}

.host-avatar-box {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: rgba(219, 39, 119, 0.1);
  color: var(--pink-dark, #db2777);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 14px;
  flex-shrink: 0;
  overflow: hidden;
}

.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.host-text-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.host-name-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.host-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.reputation-badge {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 10.5px;
  font-weight: 800;
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
  padding: 1px 5px;
  border-radius: 4px;
}

.rep-ico {
  font-size: 12px;
}

.host-sub {
  font-size: 11.5px;
  color: var(--muted);
}

.match-meta-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid var(--outline);
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  color: var(--muted);
}

.meta-item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  padding-top: 4px;
  border-top: 1px dashed var(--outline);
}

.meta-ico {
  font-size: 16px;
  color: var(--pink-dark, #db2777);
  flex-shrink: 0;
}

.meta-val {
  color: var(--ink);
}

.meta-val.highlight {
  color: var(--pink-dark, #db2777);
  font-weight: 750;
}

.court-sub {
  color: var(--muted);
  font-weight: normal;
}

.slots-visualizer {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 8px 0;
}

.slots-header {
  display: flex;
  justify-content: space-between;
  font-size: 11.5px;
  color: var(--muted);
  font-weight: 600;
}

.slots-track {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.slot-bubble {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.15s ease;
}

.slot-bubble.filled {
  background: rgba(219, 39, 119, 0.15);
  color: var(--pink-dark, #db2777);
  border: 1px solid rgba(219, 39, 119, 0.3);
}

.slot-bubble.filled.host {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border: 0;
}

.slot-bubble.empty {
  background: transparent;
  color: var(--muted);
  border: 1px dashed var(--outline);
}

.bubble-ico {
  font-size: 15px;
}

.card-footer {
  margin-top: 14px;
  padding-top: 10px;
  border-top: 1px solid var(--outline);
}
</style>

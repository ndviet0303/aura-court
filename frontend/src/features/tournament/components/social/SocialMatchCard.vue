<script setup lang="ts">
import { AuraButton } from '@/shared/components'
import type { SocialMatch } from '../../composables/useSocialTournament'

defineProps<{
  match: SocialMatch
}>()

const emit = defineEmits<{
  (e: 'join', match: SocialMatch): void
}>()
</script>

<template>
  <div class="social-match-card">
    <div class="card-header">
      <div class="host-info">
        <div class="host-avatar-box">
          <span class="material-symbols-outlined">sports_tennis</span>
        </div>
        <div>
          <h4 class="match-title">{{ match.title }}</h4>
          <span class="host-name">Chủ phòng: <strong>{{ match.host_name }}</strong></span>
        </div>
      </div>
      <span class="slots-pill">
        <span class="material-symbols-outlined mini-ico">group</span>
        {{ match.joined_count }} / {{ match.required_participants }} người
      </span>
    </div>

    <div class="card-meta">
      <span class="meta-tag">
        <span class="material-symbols-outlined meta-ico">location_on</span>
        {{ match.venue_name }} {{ match.court_name ? `· ${match.court_name}` : '' }}
      </span>
      <span class="meta-tag">
        <span class="material-symbols-outlined meta-ico">schedule</span>
        {{ match.starts_at }}
      </span>
      <span v-if="match.min_skill" class="meta-tag">
        <span class="material-symbols-outlined meta-ico">trending_up</span>
        Trình độ yêu cầu: Level {{ match.min_skill }} - {{ match.max_skill }}
      </span>
    </div>

    <p v-if="match.description" class="desc">{{ match.description }}</p>

    <div v-if="match.participants && match.participants.length > 0" class="participants-row">
      <span class="p-title">Đã tham gia:</span>
      <div class="p-tags">
        <span v-for="p in match.participants" :key="p.id" class="p-chip">
          <span class="material-symbols-outlined chip-ico">person</span>
          {{ p.name }} (Lv.{{ p.skill_level }})
        </span>
      </div>
    </div>

    <div class="card-footer">
      <AuraButton
        variant="primary"
        size="md"
        icon="group_add"
        block
        :disabled="match.joined_count >= match.required_participants"
        @click="emit('join', match)"
      >
        {{ match.joined_count >= match.required_participants ? 'Đã đủ thành viên' : 'Tham Gia Kèo Này' }}
      </AuraButton>
    </div>
  </div>
</template>

<style scoped>
.social-match-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  border-radius: 18px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
  transition: transform 0.2s ease, border-color 0.2s ease;
}

.social-match-card:hover {
  transform: translateY(-2px);
  border-color: var(--primary-border, var(--pink-dark));
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}

.host-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.host-avatar-box {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.match-title {
  margin: 0;
  font-size: 15px;
  font-weight: 750;
  color: var(--ink);
}

.host-name {
  font-size: 11.5px;
  color: var(--muted);
}

.slots-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  font-size: 11px;
  font-weight: 750;
  white-space: nowrap;
}

.card-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12.5px;
  color: var(--muted);
}

.meta-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.meta-ico {
  font-size: 16px;
  color: var(--pink-dark, #db2777);
}

.desc {
  margin: 0;
  font-size: 12.5px;
  line-height: 1.45;
  color: var(--muted);
}

.participants-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.p-title {
  font-size: 11.5px;
  font-weight: 650;
  color: var(--muted);
}

.p-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.p-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 6px;
  background: var(--surface-container, rgba(0, 0, 0, 0.04));
  font-size: 11px;
  color: var(--ink);
}

.chip-ico {
  font-size: 13px;
  color: var(--muted);
}

.card-footer {
  margin-top: auto;
  padding-top: 8px;
}
</style>


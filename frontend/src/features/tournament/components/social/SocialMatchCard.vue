<script setup lang="ts">
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
        <span class="host-avatar">🏸</span>
        <div>
          <h4 class="match-title">{{ match.title }}</h4>
          <span class="host-name">Chủ phòng: <strong>{{ match.host_name }}</strong></span>
        </div>
      </div>
      <span class="slots-pill">{{ match.joined_count }} / {{ match.required_participants }} người</span>
    </div>

    <div class="card-meta">
      <span>📍 {{ match.venue_name }} {{ match.court_name ? `· ${match.court_name}` : '' }}</span>
      <span>⏰ {{ match.starts_at }}</span>
      <span v-if="match.min_skill">🎯 Trình độ yêu cầu: Level {{ match.min_skill }} - {{ match.max_skill }}</span>
    </div>

    <p class="desc">{{ match.description }}</p>

    <div class="participants-row">
      <span class="p-title">Đã tham gia:</span>
      <div class="p-tags">
        <span v-for="p in match.participants" :key="p.id" class="p-chip">
          👤 {{ p.name }} (Lv.{{ p.skill_level }})
        </span>
      </div>
    </div>

    <div class="card-footer">
      <button
        class="btn-join"
        :disabled="match.joined_count >= match.required_participants"
        @click="emit('join', match)"
      >
        {{ match.joined_count >= match.required_participants ? 'Đã đủ thành viên' : '⚡ Tham Gia Kèo' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.social-match-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
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

.host-avatar {
  font-size: 24px;
}

.match-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
}

.host-name {
  font-size: 11px;
  color: var(--muted);
}

.slots-pill {
  padding: 4px 10px;
  border-radius: 12px;
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  font-size: 11px;
  font-weight: 700;
}

.card-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
  color: var(--muted);
}

.desc {
  margin: 0;
  font-size: 12px;
  line-height: 1.4;
}

.participants-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.p-title {
  font-size: 11px;
  color: var(--muted);
}

.p-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.p-chip {
  padding: 3px 8px;
  border-radius: 6px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  font-size: 11px;
}

.card-footer {
  margin-top: auto;
  padding-top: 8px;
}

.btn-join {
  width: 100%;
  padding: 10px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.btn-join:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>

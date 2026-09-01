<script setup lang="ts">
import { onMounted } from 'vue'
import { useCreateMatch } from '../composables/useCreateMatch'
import { AuraButton } from '@/shared/components'

import SportCategorySelector from '../components/match/SportCategorySelector.vue'
import MatchLocationTimeForm from '../components/match/MatchLocationTimeForm.vue'
import MatchSettingsForm from '../components/match/MatchSettingsForm.vue'
import MatchInviteNetworkCard from '../components/match/MatchInviteNetworkCard.vue'

const {
  submitting,
  notice,
  error,
  courts,
  matchTitle,
  selectedSport,
  selectedVenueName,
  selectedCourtId,
  matchDate,
  matchTime,
  duration,
  matchFormat,
  skillLevel,
  splitCost,
  costPerPerson,
  maxPlayers,
  quickVenues,
  friends,
  loadCourts,
  toggleFriend,
  submitMatch,
} = useCreateMatch()

onMounted(() => {
  loadCourts()
})
</script>

<template>
  <div class="create-match-view-page">
    <!-- Top Navigation -->
    <div class="top-nav">
      <AuraButton to="/matchmaking" variant="ghost" size="sm" icon="arrow_back">
        Quay lại Ghép Kèo
      </AuraButton>
    </div>

    <!-- Header Hero -->
    <header class="match-hero">
      <div class="hero-titles">
        <span class="pill-badge font-mono">INITIATE PLAY</span>
        <h1 class="page-title">Khởi Tạo Kèo Mới & Ghép Trận</h1>
        <p class="page-sub">Tìm kiếm đồng đội, mở kèo giao lưu câu lạc bộ và chia sẻ chi phí sân tự động.</p>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <transition name="fade">
      <div v-if="notice" class="alert-pill-success" role="status">
        <span class="material-symbols-outlined alert-ico">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </transition>

    <transition name="fade">
      <div v-if="error" class="alert-pill-error" role="alert">
        <span class="material-symbols-outlined alert-ico">error</span>
        <span>{{ error }}</span>
      </div>
    </transition>

    <form class="create-match-form" @submit.prevent="submitMatch">
      <!-- Optional Custom Title -->
      <div class="title-input-card">
        <label for="match-title-input" class="input-label">Tiêu đề kèo giao lưu (tùy chọn)</label>
        <input
          id="match-title-input"
          v-model="matchTitle"
          type="text"
          class="text-input"
          placeholder="Ví dụ: Tìm 2 bạn đánh đôi nam/nữ cầu lông trình độ khá..."
        />
      </div>

      <!-- Sport Selector -->
      <SportCategorySelector
        :selected-sport="selectedSport"
        @update:selected-sport="selectedSport = $event"
      />

      <!-- Location & Time -->
      <MatchLocationTimeForm
        :courts="courts"
        :selected-venue-name="selectedVenueName"
        :selected-court-id="selectedCourtId"
        :match-date="matchDate"
        :match-time="matchTime"
        :duration="duration"
        :quick-venues="quickVenues"
        @update:selected-venue-name="selectedVenueName = $event"
        @update:selected-court-id="selectedCourtId = $event"
        @update:match-date="matchDate = $event"
        @update:match-time="matchTime = $event"
        @update:duration="duration = $event"
      />

      <!-- Match Parameters -->
      <MatchSettingsForm
        :format="matchFormat"
        :skill-level="skillLevel"
        :split-cost="splitCost"
        :cost-per-person="costPerPerson"
        :max-players="maxPlayers"
        @update:format="matchFormat = $event"
        @update:skill-level="skillLevel = $event"
        @update:split-cost="splitCost = $event"
        @update:cost-per-person="costPerPerson = $event"
        @update:max-players="maxPlayers = $event"
      />

      <!-- Friend Network Invites -->
      <MatchInviteNetworkCard
        :friends="friends"
        @toggle="toggleFriend"
      />

      <!-- Submit CTA -->
      <div class="form-submit-row">
        <AuraButton
          type="submit"
          variant="primary"
          size="lg"
          icon="group_add"
          block
          :loading="submitting"
        >
          {{ submitting ? 'Đang khởi tạo trận đấu...' : 'Mở Kèo & Xuất Bản Lên Cộng Đồng' }}
        </AuraButton>
      </div>
    </form>
  </div>
</template>

<style scoped>
.create-match-view-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 900px;
  margin: 0 auto;
  padding: 24px 20px 48px;
  color: var(--ink);
}

.top-nav {
  display: flex;
}

.match-hero {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.pill-badge {
  font-size: 11px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.1);
  padding: 3px 8px;
  border-radius: 6px;
  width: fit-content;
}

.page-title {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.alert-pill-success {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.3);
  font-size: 13.5px;
  font-weight: 700;
}

.alert-pill-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.3);
  font-size: 13.5px;
  font-weight: 700;
}

.alert-ico {
  font-size: 20px;
  flex-shrink: 0;
}

.create-match-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.title-input-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 18px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 16px;
}

.input-label {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.text-input {
  width: 100%;
  padding: 12px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.text-input:focus {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
}

.form-submit-row {
  display: flex;
  justify-content: center;
  padding-top: 10px;
}
</style>

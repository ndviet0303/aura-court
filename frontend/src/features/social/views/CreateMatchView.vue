<script setup lang="ts">
import { onMounted } from 'vue'
import { useCreateMatch } from '../composables/useCreateMatch'
import { AuraButton } from '@/shared/components'

import SportCategorySelector from '../components/match/SportCategorySelector.vue'
import MatchLocationTimeForm from '../components/match/MatchLocationTimeForm.vue'
import MatchSettingsForm from '../components/match/MatchSettingsForm.vue'
import MatchInviteNetworkCard from '../components/match/MatchInviteNetworkCard.vue'
import MatchLivePreviewCard from '../components/match/MatchLivePreviewCard.vue'

const {
  loading,
  submitting,
  notice,
  error,
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
  visibility,
  matchDescription,
  courts,
  courtOptions,
  quickVenues,
  friendSearchQuery,
  filteredFriends,
  selectedFriendsCount,
  previewMatch,
  loadCourts,
  toggleFriend,
  selectQuickVenue,
  submitMatch,
} = useCreateMatch()

onMounted(() => {
  loadCourts()
})
</script>

<template>
  <main class="create-match-page-view">
    <!-- Top Navigation -->
    <div class="top-nav-bar">
      <AuraButton to="/matchmaking" variant="ghost" size="sm" icon="arrow_back">
        {{ $t('matchmaking.backToMatchmaking') }}
      </AuraButton>
    </div>

    <!-- Header Hero -->
    <header class="create-hero">
      <span class="hero-badge font-mono">{{ $t('matchmaking.eyebrow') }}</span>
      <h1 class="page-title">{{ $t('matchmaking.createMatchPageTitle') }}</h1>
      <p class="page-subtitle">
        {{ $t('matchmaking.createMatchPageSub') }}
      </p>
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

    <!-- 2-Column Responsive Layout -->
    <form class="create-layout" @submit.prevent="submitMatch">
      <!-- Left Column: Form Steps -->
      <div class="form-steps-col">
        <!-- Optional Custom Title Input Card -->
        <div class="custom-title-card">
          <label for="match-title-custom" class="title-label">
            <span class="material-symbols-outlined label-icon">edit</span>
            {{ $t('matchmaking.customTitleLabel') }}
          </label>
          <input
            id="match-title-custom"
            v-model="matchTitle"
            type="text"
            class="title-text-input"
            :placeholder="$t('matchmaking.customTitlePlaceholder')"
          />
        </div>

        <!-- Sport Selector -->
        <SportCategorySelector
          :selected-sport="selectedSport"
          @update:selected-sport="selectedSport = $event"
        />

        <!-- Location & Time Form -->
        <MatchLocationTimeForm
          :courts="courts"
          :court-options="courtOptions"
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
          @select-quick-venue="selectQuickVenue"
        />

        <!-- Match Settings Form -->
        <MatchSettingsForm
          :format="matchFormat"
          :skill-level="skillLevel"
          :split-cost="splitCost"
          :cost-per-person="costPerPerson"
          :max-players="maxPlayers"
          :visibility="visibility"
          :match-description="matchDescription"
          @update:format="matchFormat = $event"
          @update:skill-level="skillLevel = $event"
          @update:split-cost="splitCost = $event"
          @update:cost-per-person="costPerPerson = $event"
          @update:max-players="maxPlayers = $event"
          @update:visibility="visibility = $event"
          @update:match-description="matchDescription = $event"
        />

        <!-- Invite Network Card -->
        <MatchInviteNetworkCard
          :friends="filteredFriends"
          :search-query="friendSearchQuery"
          :selected-count="selectedFriendsCount"
          @toggle="toggleFriend"
          @update:search-query="friendSearchQuery = $event"
        />
      </div>

      <!-- Right Column: Real-time Live Preview & Submit Sticky Card -->
      <div class="preview-col">
        <MatchLivePreviewCard
          :preview-match="previewMatch"
          :submitting="submitting"
          :selected-friends-count="selectedFriendsCount"
          @submit="submitMatch"
        />
      </div>
    </form>
  </main>
</template>

<style scoped>
.create-match-page-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 20px 64px;
  color: var(--ink);
}

.top-nav-bar {
  display: flex;
}

.create-hero {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.hero-badge {
  font-size: 11px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.1);
  padding: 3px 10px;
  border-radius: 6px;
  width: fit-content;
}

.page-title {
  margin: 0;
  font-size: 26px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.page-subtitle {
  margin: 0;
  font-size: 13.5px;
  color: var(--muted);
}

.alert-pill-success {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 14px;
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
  padding: 12px 18px;
  border-radius: 14px;
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

.create-layout {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 24px;
  align-items: start;
}

.form-steps-col {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.custom-title-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 20px 22px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 18px;
  box-shadow: var(--shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.04));
}

.title-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.label-icon {
  font-size: 16px;
  color: var(--pink-dark, #db2777);
}

.title-text-input {
  width: 100%;
  padding: 12px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.015));
  color: var(--ink);
  font-size: 14px;
  font-weight: 600;
  font-family: inherit;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.title-text-input:focus {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
}

.preview-col {
  position: relative;
}

@media (max-width: 980px) {
  .create-layout {
    grid-template-columns: 1fr;
  }
}
</style>

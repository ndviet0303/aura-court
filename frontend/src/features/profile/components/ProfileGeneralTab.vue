<script setup lang="ts">
import { AuraButton } from '@/shared/components'

const props = defineProps<{
  name: string
  phone: string
  playLevel: string
  favoriteSports: string[]
  avatarUrl: string
  saving: boolean
}>()

const emit = defineEmits<{
  (e: 'update:name', val: string): void
  (e: 'update:phone', val: string): void
  (e: 'update:playLevel', val: string): void
  (e: 'update:favoriteSports', val: string[]): void
  (e: 'update:avatarUrl', val: string): void
  (e: 'save'): void
}>()

const availableSports = [
  { id: 'badminton', name: 'Cầu Lông', icon: 'sports_tennis' },
  { id: 'pickleball', name: 'Pickleball', icon: 'sports_baseball' },
  { id: 'tennis', name: 'Quần Vợt (Tennis)', icon: 'sports_tennis' },
  { id: 'table_tennis', name: 'Bóng Bàn', icon: 'sports_cricket' },
  { id: 'football', name: 'Bóng Đá Futsal', icon: 'sports_soccer' },
]

const playLevels = [
  { id: 'beginner', title: 'Mới bắt đầu', desc: 'Mới làm quen môn thể thao, thi đấu rèn luyện sức khỏe cơ bản.' },
  { id: 'intermediate', title: 'Phong trào', desc: 'Chơi đều đặn 1-3 năm, có bộ kỹ thuật cơ bản và độ bền tốt.' },
  { id: 'advanced', title: 'Bán chuyên', desc: 'Thi đấu giải phong trào, kỹ chiến thuật và thể lực vững vàng.' },
  { id: 'pro', title: 'Chuyên nghiệp', desc: 'Vận động viên thi đấu chuyên nghiệp hoặc huấn luyện viên.' },
]

function toggleSport(sportId: string) {
  const current = [...props.favoriteSports]
  const index = current.indexOf(sportId)
  if (index > -1) {
    if (current.length > 1) {
      current.splice(index, 1)
    }
  } else {
    current.push(sportId)
  }
  emit('update:favoriteSports', current)
}
</script>

<template>
  <div class="profile-tab-card">
    <div class="section-title-row">
      <div class="title-with-icon">
        <span class="material-symbols-outlined title-icon">badge</span>
        <div>
          <h2 class="section-heading">{{ $t('profile.title') }}</h2>
          <p class="section-sub">{{ $t('profile.subtitle') }}</p>
        </div>
      </div>
    </div>

    <form class="profile-edit-form" @submit.prevent="emit('save')">
      <!-- Main Info Grid -->
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label" for="profile-name">
            {{ $t('profile.fullName') }} <span class="required">*</span>
          </label>
          <div class="input-with-icon">
            <span class="material-symbols-outlined input-icon">person</span>
            <input
              id="profile-name"
              :value="name"
              type="text"
              class="form-input"
              :placeholder="$t('profile.fullName')"
              required
              @input="emit('update:name', ($event.target as HTMLInputElement).value)"
            />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="profile-phone">
            {{ $t('profile.phone') }}
          </label>
          <div class="input-with-icon">
            <span class="material-symbols-outlined input-icon">call</span>
            <input
              id="profile-phone"
              :value="phone"
              type="tel"
              class="form-input"
              :placeholder="$t('profile.phonePlaceholder')"
              @input="emit('update:phone', ($event.target as HTMLInputElement).value)"
            />
          </div>
        </div>
      </div>

      <!-- Play Level Selection Cards -->
      <div class="form-group">
        <label class="form-label">
          {{ $t('profile.playLevel') }}
        </label>
        <div class="levels-grid">
          <button
            type="button"
            class="level-card"
            :class="{ 'is-selected': playLevel === 'beginner' }"
            @click="emit('update:playLevel', 'beginner')"
          >
            <div class="level-top">
              <span class="material-symbols-outlined level-check">
                {{ playLevel === 'beginner' ? 'check_circle' : 'radio_button_unchecked' }}
              </span>
              <strong class="level-name">{{ $t('profile.levelBeginner') }}</strong>
            </div>
            <p class="level-desc">{{ $t('profile.levelBeginnerDesc') }}</p>
          </button>

          <button
            type="button"
            class="level-card"
            :class="{ 'is-selected': playLevel === 'intermediate' }"
            @click="emit('update:playLevel', 'intermediate')"
          >
            <div class="level-top">
              <span class="material-symbols-outlined level-check">
                {{ playLevel === 'intermediate' ? 'check_circle' : 'radio_button_unchecked' }}
              </span>
              <strong class="level-name">{{ $t('profile.levelIntermediate') }}</strong>
            </div>
            <p class="level-desc">{{ $t('profile.levelIntermediateDesc') }}</p>
          </button>

          <button
            type="button"
            class="level-card"
            :class="{ 'is-selected': playLevel === 'advanced' }"
            @click="emit('update:playLevel', 'advanced')"
          >
            <div class="level-top">
              <span class="material-symbols-outlined level-check">
                {{ playLevel === 'advanced' ? 'check_circle' : 'radio_button_unchecked' }}
              </span>
              <strong class="level-name">{{ $t('profile.levelAdvanced') }}</strong>
            </div>
            <p class="level-desc">{{ $t('profile.levelAdvancedDesc') }}</p>
          </button>

          <button
            type="button"
            class="level-card"
            :class="{ 'is-selected': playLevel === 'pro' }"
            @click="emit('update:playLevel', 'pro')"
          >
            <div class="level-top">
              <span class="material-symbols-outlined level-check">
                {{ playLevel === 'pro' ? 'check_circle' : 'radio_button_unchecked' }}
              </span>
              <strong class="level-name">{{ $t('profile.levelPro') }}</strong>
            </div>
            <p class="level-desc">{{ $t('profile.levelProDesc') }}</p>
          </button>
        </div>
      </div>

      <!-- Favorite Sports Tag Chips -->
      <div class="form-group">
        <label class="form-label">
          {{ $t('profile.favoriteSports') }}
        </label>
        <div class="sports-chip-row">
          <button
            v-for="sport in availableSports"
            :key="sport.id"
            type="button"
            class="sport-chip-btn"
            :class="{ 'is-active': favoriteSports.includes(sport.id) }"
            @click="toggleSport(sport.id)"
          >
            <span class="material-symbols-outlined sport-icon">{{ sport.icon }}</span>
            <span>{{ $t(`profile.sports.${sport.id}`) }}</span>
          </button>
        </div>
      </div>

      <!-- Avatar Customization -->
      <div class="form-group">
        <label class="form-label" for="profile-avatar">
          {{ $t('profile.chooseAvatar') }}
        </label>
        <div class="input-with-icon">
          <span class="material-symbols-outlined input-icon">image</span>
          <input
            id="profile-avatar"
            :value="avatarUrl"
            type="url"
            class="form-input"
            placeholder="https://example.com/avatar.jpg"
            @input="emit('update:avatarUrl', ($event.target as HTMLInputElement).value)"
          />
        </div>
        <span class="field-hint">{{ $t('profile.chooseAvatarHint') }}</span>
      </div>

      <!-- Actions -->
      <div class="form-actions-bar">
        <AuraButton
          type="submit"
          variant="primary"
          :loading="saving"
          :disabled="saving"
        >
          <template #icon>
            <span class="material-symbols-outlined">save</span>
          </template>
          {{ $t('profile.saveChanges') }}
        </AuraButton>
      </div>
    </form>
  </div>
</template>

<style scoped>
.profile-tab-card {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: 28px;
  border-radius: 24px;
  border: 1px solid var(--outline, #e2dad0);
  background: var(--surface, #ffffff);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
  box-sizing: border-box;
  width: 100%;
  min-width: 0;
  overflow: hidden;
}

html.dark .profile-tab-card {
  background: var(--surface, #1e191d);
  border-color: var(--outline, #352c32);
}

.section-title-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.title-with-icon {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.title-icon {
  font-size: 26px;
  color: var(--primary, #6B2D3F);
  margin-top: 2px;
}

html.dark .title-icon {
  color: #dfba72;
}

.section-heading {
  margin: 0;
  font-size: 18px;
  font-weight: 750;
  color: var(--ink, #1c1618);
}

html.dark .section-heading {
  color: #faf7f3;
}

.section-sub {
  margin: 4px 0 0;
  font-size: 13.5px;
  color: var(--muted, #6e6668);
}

.profile-edit-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
}

.form-label {
  font-size: 13.5px;
  font-weight: 650;
  color: var(--ink, #1c1618);
}

html.dark .form-label {
  color: #e5dde2;
}

.required {
  color: #dc2626;
}

.input-with-icon {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
  min-width: 0;
}

.input-icon {
  position: absolute;
  left: 14px;
  font-size: 20px;
  color: var(--muted, #6e6668);
  pointer-events: none;
}

.form-input {
  width: 100%;
  padding: 11px 14px 11px 42px;
  border-radius: 12px;
  border: 1px solid var(--outline, #e2dad0);
  background: var(--surface-container, #faf7f3);
  color: var(--ink, #1c1618);
  font-size: 14px;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

html.dark .form-input {
  background: #141113;
  border-color: #3b3238;
  color: #faf7f3;
}

.form-input:focus {
  outline: none;
  border-color: var(--primary, #6B2D3F);
  box-shadow: 0 0 0 3px rgba(107, 45, 63, 0.12);
}

html.dark .form-input:focus {
  border-color: #dfba72;
  box-shadow: 0 0 0 3px rgba(223, 186, 114, 0.15);
}

.field-hint {
  font-size: 12px;
  color: var(--muted, #6e6668);
}

/* Play Levels Grid */
.levels-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.level-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1.5px solid var(--outline, #e2dad0);
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  cursor: pointer;
  text-align: left;
  transition: all 0.2s ease;
  box-sizing: border-box;
  width: 100%;
  min-width: 0;
}

html.dark .level-card {
  background: rgba(255, 255, 255, 0.03);
  border-color: #3b3238;
}

.level-card:hover {
  border-color: var(--primary, #6B2D3F);
}

html.dark .level-card:hover {
  border-color: #dfba72;
}

.level-card.is-selected {
  border-color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.05);
}

html.dark .level-card.is-selected {
  border-color: #dfba72;
  background: rgba(223, 186, 114, 0.08);
}

.level-top {
  display: flex;
  align-items: center;
  gap: 8px;
}

.level-check {
  font-size: 18px;
  color: var(--muted, #6e6668);
}

.level-card.is-selected .level-check {
  color: var(--pink-dark, #db2777);
}

html.dark .level-card.is-selected .level-check {
  color: #dfba72;
}

.level-name {
  font-size: 14px;
  font-weight: 750;
  color: var(--ink, #1c1618);
}

html.dark .level-name {
  color: #faf7f3;
}

.level-desc {
  margin: 0;
  font-size: 12px;
  color: var(--muted, #6e6668);
  line-height: 1.4;
}

/* Sports Chips */
.sports-chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.sport-chip-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 999px;
  border: 1px solid var(--outline, #e2dad0);
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  color: var(--ink, #1c1618);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

html.dark .sport-chip-btn {
  background: rgba(255, 255, 255, 0.04);
  border-color: #3b3238;
  color: #faf7f3;
}

.sport-chip-btn:hover {
  border-color: var(--primary, #6B2D3F);
}

html.dark .sport-chip-btn:hover {
  border-color: #dfba72;
}

.sport-chip-btn.is-active {
  background: #6B2D3F;
  border-color: #6B2D3F;
  color: #ffffff;
}

html.dark .sport-chip-btn.is-active {
  background: #dfba72;
  border-color: #dfba72;
  color: #1c1618;
}

.sport-icon {
  font-size: 16px;
}

.form-actions-bar {
  display: flex;
  justify-content: flex-end;
  padding-top: 10px;
  border-top: 1px solid var(--outline, #e2dad0);
}

@media (max-width: 640px) {
  .profile-tab-card {
    padding: 20px 16px;
  }
  .form-grid {
    grid-template-columns: 1fr;
  }
  .levels-grid {
    grid-template-columns: 1fr;
  }
}
</style>

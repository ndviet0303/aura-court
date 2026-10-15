<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const imageUrl = defineModel<string>({ required: true })
const { t } = useI18n()

const courtImagePresets = computed(() => [
  {
    name: t('admin.manage.presetFootball'),
    sub: t('admin.manage.presetFootballSub'),
    url: '/courts/football-mini-turf.jpg',
  },
  {
    name: t('admin.manage.presetPickleball'),
    sub: t('admin.manage.presetPickleballSub'),
    url: '/courts/pickleball-neon.jpg',
  },
  {
    name: t('admin.manage.presetTennis'),
    sub: t('admin.manage.presetTennisSub'),
    url: '/courts/tennis-1.jpg',
  },
  {
    name: t('admin.manage.presetBasketball'),
    sub: t('admin.manage.presetBasketballSub'),
    url: '/courts/basketball-nba.jpg',
  },
  {
    name: t('admin.manage.presetYonex'),
    sub: t('admin.manage.presetYonexSub'),
    url: '/courts/badminton-0.jpg',
  },
  {
    name: t('admin.manage.presetBlue'),
    sub: t('admin.manage.presetBlueSub'),
    url: '/courts/badminton-court.jpg',
  },
])
</script>

<template>
  <div>
    <span class="tag-prompt presets-label">{{ t('admin.manage.presetImagesLabel') }}</span>
    <div class="presets-grid">
      <button
        v-for="preset in courtImagePresets"
        :key="preset.name"
        type="button"
        class="preset-thumb-btn"
        :class="{ active: imageUrl === preset.url }"
        :aria-pressed="imageUrl === preset.url"
        :title="preset.sub"
        @click="imageUrl = preset.url"
      >
        <img :src="preset.url" alt="" loading="lazy" />
        <span class="preset-name">{{ preset.name }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.tag-prompt {
  font-size: 11px;
  color: var(--muted);
}

.presets-label {
  display: block;
  margin: 12px 0 6px;
}

.presets-grid {
  display: grid;
  /* Six presets: 3 or 2 columns keep the rows even. */
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

@media (max-width: 560px) {
  .presets-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.preset-thumb-btn {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  padding: 6px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s ease;
}

.preset-thumb-btn:hover {
  border-color: var(--accent);
}

.preset-thumb-btn.active {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 1px var(--pink-dark, #db2777);
}

.preset-thumb-btn img {
  width: 100%;
  aspect-ratio: 16 / 9;
  border-radius: 6px;
  object-fit: cover;
}

.preset-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>

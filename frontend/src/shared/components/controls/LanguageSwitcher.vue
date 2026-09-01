<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { setAppLocale, type AppLocale } from '@/i18n'
import { useRoute } from 'vue-router'
import { applySeo } from '@/lib/seo'

const { locale } = useI18n()
const route = useRoute()
const open = ref(false)
const root = ref<HTMLElement | null>(null)

const current = computed(() => (locale.value as AppLocale) || 'vi')

const languages = [
  {
    code: 'vi' as AppLocale,
    name: 'Tiếng Việt',
    short: 'VI',
    flag: 'vn',
  },
  {
    code: 'en' as AppLocale,
    name: 'English (US)',
    short: 'EN',
    flag: 'us',
  },
]

const defaultLang = languages[0]!

const currentLanguage = computed(() => {
  return languages.find((l) => l.code === current.value) || defaultLang
})

function selectLocale(target: AppLocale) {
  setAppLocale(target)
  applySeo(route)
  open.value = false
}

function toggleDropdown() {
  open.value = !open.value
}

function handleOutsideClick(event: MouseEvent) {
  if (root.value && !root.value.contains(event.target as Node)) {
    open.value = false
  }
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && open.value) {
    open.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
  document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideClick)
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div ref="root" class="lang-dropdown">
    <!-- Trigger Button -->
    <button
      type="button"
      class="lang-trigger"
      :class="{ open }"
      :aria-expanded="open"
      aria-haspopup="listbox"
      aria-label="Select language"
      @click.stop="toggleDropdown"
    >
      <!-- Current Flag -->
      <span class="flag-icon-wrap" aria-hidden="true">
        <!-- Vietnam Flag SVG -->
        <svg v-if="currentLanguage.flag === 'vn'" class="flag-svg" viewBox="0 0 640 480">
          <rect width="640" height="480" fill="#da251d" rx="28" />
          <polygon
            fill="#ffff00"
            points="320,84 357,198 477,198 380,268 417,382 320,312 223,382 260,268 163,198 283,198"
          />
        </svg>
        <!-- US Flag SVG -->
        <svg v-else class="flag-svg" viewBox="0 0 640 480">
          <g rx="28">
            <rect width="640" height="480" fill="#bd3d44" rx="28" />
            <path stroke="#fff" stroke-width="37" d="M0,55H640M0,129H640M0,203H640M0,277H640M0,351H640M0,425H640" />
            <rect width="260" height="260" fill="#192f5d" />
            <!-- Star Pattern -->
            <g fill="#fff" transform="scale(0.9) translate(14, 12)">
              <polygon points="20,10 23,20 33,20 25,26 28,36 20,30 12,36 15,26 7,20 17,20" />
              <polygon points="60,10 63,20 73,20 65,26 68,36 60,30 52,36 55,26 47,20 57,20" />
              <polygon points="100,10 103,20 113,20 105,26 108,36 100,30 92,36 95,26 87,20 97,20" />
              <polygon points="140,10 143,20 153,20 145,26 148,36 140,30 132,36 135,26 127,20 137,20" />
              <polygon points="180,10 183,20 193,20 185,26 188,36 180,30 172,36 175,26 167,20 177,20" />
              <polygon points="220,10 223,20 233,20 225,26 228,36 220,30 212,36 215,26 207,20 217,20" />

              <polygon points="40,50 43,60 53,60 45,66 48,76 40,70 32,76 35,66 27,60 37,60" />
              <polygon points="80,50 83,60 93,60 85,66 88,76 80,70 72,76 75,66 67,60 77,60" />
              <polygon points="120,50 123,60 133,60 125,66 128,76 120,70 112,76 115,66 107,60 117,60" />
              <polygon points="160,50 163,60 173,60 165,66 168,76 160,70 152,76 155,66 147,60 157,60" />
              <polygon points="200,50 203,60 213,60 205,66 208,76 200,70 192,76 195,66 187,60 197,60" />

              <polygon points="20,90 23,100 33,100 25,106 28,116 20,110 12,116 15,106 7,100 17,100" />
              <polygon points="60,90 63,100 73,100 65,106 68,116 60,110 52,116 55,106 47,100 57,100" />
              <polygon points="100,90 103,100 113,100 105,106 108,116 100,110 92,116 95,106 87,100 97,100" />
              <polygon points="140,90 143,100 153,100 145,106 148,116 140,110 132,116 135,106 127,100 137,100" />
              <polygon points="180,90 183,100 193,100 185,106 188,116 180,110 172,116 175,106 167,100 177,100" />
              <polygon points="220,90 223,100 233,100 225,106 228,116 220,110 212,116 215,106 207,100 217,100" />

              <polygon points="40,130 43,140 53,140 45,146 48,156 40,150 32,156 35,146 27,140 37,140" />
              <polygon points="80,130 83,140 93,140 85,146 88,156 80,150 72,156 75,146 67,140 77,140" />
              <polygon points="120,130 123,140 133,140 125,146 128,156 120,150 112,156 115,146 107,140 117,140" />
              <polygon points="160,130 163,140 173,140 165,146 168,156 160,150 152,156 155,146 147,140 157,140" />
              <polygon points="200,130 203,140 213,140 205,146 208,156 200,150 192,156 195,146 187,140 197,140" />

              <polygon points="20,170 23,180 33,180 25,186 28,196 20,190 12,196 15,186 7,180 17,180" />
              <polygon points="60,170 63,180 73,180 65,186 68,196 60,190 52,196 55,186 47,180 57,180" />
              <polygon points="100,170 103,180 113,180 105,186 108,196 100,190 92,196 95,186 87,180 97,180" />
              <polygon points="140,170 143,180 153,180 145,186 148,196 140,190 132,196 135,186 127,180 137,180" />
              <polygon points="180,170 183,180 193,180 185,186 188,196 180,190 172,196 175,186 167,180 177,180" />
              <polygon points="220,170 223,180 233,180 225,186 228,196 220,190 212,196 215,186 207,180 217,180" />

              <polygon points="40,210 43,220 53,220 45,226 48,236 40,230 32,236 35,226 27,220 37,220" />
              <polygon points="80,210 83,220 93,220 85,226 88,236 80,230 72,236 75,226 67,220 77,220" />
              <polygon points="120,210 123,220 133,220 125,226 128,236 120,230 112,236 115,226 107,220 117,220" />
              <polygon points="160,210 163,220 173,220 165,226 168,236 160,230 152,236 155,226 147,220 157,220" />
              <polygon points="200,210 203,220 213,220 205,226 208,236 200,230 192,236 195,226 187,220 197,220" />
            </g>
          </g>
        </svg>
      </span>

      <span class="lang-text">{{ currentLanguage.short }}</span>
      <span class="material-symbols-outlined lang-arrow" aria-hidden="true">expand_more</span>
    </button>

    <!-- Dropdown Menu -->
    <transition name="dropdown-fade">
      <div v-if="open" class="lang-menu" role="listbox">
        <button
          v-for="lang in languages"
          :key="lang.code"
          type="button"
          class="lang-option"
          :class="{ selected: lang.code === current }"
          role="option"
          :aria-selected="lang.code === current"
          @click="selectLocale(lang.code)"
        >
          <span class="option-flag-wrap">
            <!-- Vietnam Flag SVG -->
            <svg v-if="lang.flag === 'vn'" class="flag-svg" viewBox="0 0 640 480">
              <rect width="640" height="480" fill="#da251d" rx="28" />
              <polygon
                fill="#ffff00"
                points="320,84 357,198 477,198 380,268 417,382 320,312 223,382 260,268 163,198 283,198"
              />
            </svg>
            <!-- US Flag SVG -->
            <svg v-else class="flag-svg" viewBox="0 0 640 480">
              <g rx="28">
                <rect width="640" height="480" fill="#bd3d44" rx="28" />
                <path stroke="#fff" stroke-width="37" d="M0,55H640M0,129H640M0,203H640M0,277H640M0,351H640M0,425H640" />
                <rect width="260" height="260" fill="#192f5d" />
                <g fill="#fff" transform="scale(0.9) translate(14, 12)">
                  <polygon points="20,10 23,20 33,20 25,26 28,36 20,30 12,36 15,26 7,20 17,20" />
                  <polygon points="60,10 63,20 73,20 65,26 68,36 60,30 52,36 55,26 47,20 57,20" />
                  <polygon points="100,10 103,20 113,20 105,26 108,36 100,30 92,36 95,26 87,20 97,20" />
                  <polygon points="140,10 143,20 153,20 145,26 148,36 140,30 132,36 135,26 127,20 137,20" />
                  <polygon points="180,10 183,20 193,20 185,26 188,36 180,30 172,36 175,26 167,20 177,20" />
                  <polygon points="220,10 223,20 233,20 225,26 228,36 220,30 212,36 215,26 207,20 217,20" />
                  <polygon points="40,50 43,60 53,60 45,66 48,76 40,70 32,76 35,66 27,60 37,60" />
                  <polygon points="80,50 83,60 93,60 85,66 88,76 80,70 72,76 75,66 67,60 77,60" />
                  <polygon points="120,50 123,60 133,60 125,66 128,76 120,70 112,76 115,66 107,60 117,60" />
                  <polygon points="160,50 163,60 173,60 165,66 168,76 160,70 152,76 155,66 147,60 157,60" />
                  <polygon points="200,50 203,60 213,60 205,66 208,76 200,70 192,76 195,66 187,60 197,60" />
                  <polygon points="20,90 23,100 33,100 25,106 28,116 20,110 12,116 15,106 7,100 17,100" />
                  <polygon points="60,90 63,100 73,100 65,106 68,116 60,110 52,116 55,106 47,100 57,100" />
                  <polygon points="100,90 103,100 113,100 105,106 108,116 100,110 92,116 95,106 87,100 97,100" />
                  <polygon points="140,90 143,100 153,100 145,106 148,116 140,110 132,116 135,106 127,100 137,100" />
                  <polygon points="180,90 183,100 193,100 185,106 188,116 180,110 172,116 175,106 167,100 177,100" />
                  <polygon points="220,90 223,100 233,100 225,106 228,116 220,110 212,116 215,106 207,100 217,100" />
                </g>
              </g>
            </svg>
          </span>

          <span class="option-name">{{ lang.name }}</span>

          <span v-if="lang.code === current" class="material-symbols-outlined check-icon">
            check
          </span>
        </button>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.lang-dropdown {
  position: relative;
  display: inline-block;
}

.lang-trigger {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #ebdccb);
  border-radius: 9999px;
  padding: 6px 12px 6px 8px;
  color: var(--ink, #191c1d);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.18s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.lang-trigger:hover,
.lang-trigger.open {
  background: var(--surface, #ffffff);
  border-color: #6B2D3F;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.12);
}

.flag-icon-wrap {
  width: 21px;
  height: 15px;
  border-radius: 3px;
  overflow: hidden;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 1px rgba(0, 0, 0, 0.25);
  flex-shrink: 0;
}

.flag-svg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.lang-text {
  font-size: 13px;
  letter-spacing: 0.02em;
  color: var(--ink, #1c1618);
  font-weight: 700;
}

.lang-arrow {
  font-size: 18px;
  color: #6B2D3F;
  transition: transform 0.18s ease;
  line-height: 1;
}

html.dark .lang-arrow {
  color: #dfba72;
}

.lang-trigger.open .lang-arrow {
  transform: rotate(180deg);
}

html.dark .lang-trigger {
  background: #1e191c;
  border-color: #382f35;
  color: #ffffff;
}

html.dark .lang-trigger:hover,
html.dark .lang-trigger.open {
  background: #262024;
  border-color: rgba(223, 186, 114, 0.4);
}

html.dark .lang-text {
  color: #ffffff !important;
}

/* Dropdown popover */
.lang-menu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  z-index: 60;
  width: 170px;
  background: #ffffff;
  border: 1px solid #ebdccb;
  border-radius: 14px;
  padding: 5px;
  box-shadow: 0 10px 28px rgba(107, 45, 63, 0.12);
  display: flex;
  flex-direction: column;
  gap: 2px;
}

html.dark .lang-menu {
  background: #1e181c !important;
  border-color: #382f35 !important;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6) !important;
}

.lang-option {
  border: 0;
  background: transparent;
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-radius: 10px;
  cursor: pointer;
  color: var(--ink, #191c1d);
  font-size: 13px;
  font-weight: 600;
  text-align: left;
  transition: all 0.15s ease;
}

html.dark .lang-option {
  color: #d1d5db;
}

.lang-option:hover {
  background: #FAF7F3;
  color: #6B2D3F;
}

html.dark .lang-option:hover {
  background: #262024;
  color: #dfba72;
}

.lang-option.selected {
  background: rgba(107, 45, 63, 0.08);
  color: #6B2D3F;
  font-weight: 700;
}

html.dark .lang-option.selected {
  background: #361a24;
  color: #dfba72;
}

.option-flag-wrap {
  width: 21px;
  height: 15px;
  border-radius: 3px;
  overflow: hidden;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 1px rgba(0, 0, 0, 0.25);
  flex-shrink: 0;
}

.option-name {
  flex: 1;
}

.check-icon {
  font-size: 17px;
  color: #6B2D3F;
}

html.dark .check-icon {
  color: #dfba72;
}

/* Transitions */
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.97);
}
</style>

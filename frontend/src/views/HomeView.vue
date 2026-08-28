<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/lib/api'
import { useBookingStore } from '@/stores/booking'
import { useAuthStore } from '@/stores/auth'
import type { AvailabilitySlot, Court } from '@/types'
import { formatCurrency, formatTime } from '@/i18n'
import DatePicker from '@/components/DatePicker.vue'
import SelectDropdown from '@/components/SelectDropdown.vue'
import SkeletonLoader from '@/components/SkeletonLoader.vue'

const { t } = useI18n()
const store = useBookingStore()
const auth = useAuthStore()
const router = useRouter()
const date = ref(new Date().toISOString().slice(0, 10))
const selected = ref<number | null>(null)
const timeFilter = ref('all')
const sportTypeFilter = ref('all')
const loading = ref(false)
const error = ref('')

const sportCategories = [
  { id: 'all', name: 'Tất cả môn', icon: 'apps' },
  { id: 'badminton', name: 'Cầu lông', icon: 'sports_tennis' },
  { id: 'football_5v5', name: 'Bóng đá 5v5', icon: 'sports_soccer' },
  { id: 'football_7v7', name: 'Bóng đá 7v7', icon: 'sports_soccer' },
  { id: 'tennis', name: 'Tennis', icon: 'sports_tennis' },
  { id: 'pickleball', name: 'Pickleball', icon: 'sports_tennis' },
  { id: 'basketball', name: 'Bóng rổ', icon: 'sports_basketball' },
  { id: 'volleyball', name: 'Bóng chuyền', icon: 'sports_volleyball' },
  { id: 'table_tennis', name: 'Bóng bàn', icon: 'table_restaurant' },
]

async function selectSportCategory(sportId: string) {
  sportTypeFilter.value = sportId
  try {
    await store.loadCourts({ sport_type: sportId })
    selected.value = store.courts[0]?.id || null
    await loadAvailability()
  } catch {
    /* store handles error */
  }
}

const courtOptions = computed(() =>
  store.courts.map((court: Court) => ({
    value: court.id,
    label: `${court.name}${court.sport_zone ? ` (${court.sport_zone.name})` : ''}`,
  }))
)

const timeOptions = computed(() => [
  { value: 'all', label: t('home.search.allSlots') },
  { value: 'morning', label: t('home.search.morning') },
  { value: 'evening', label: t('home.search.evening') },
])

const total = computed(() => store.selectedSlots.reduce((sum: number, slot: AvailabilitySlot) => sum + slot.price, 0))
const image = (index: number) => store.courts[index]?.image_url || `/aura/court-${index + 4}.jpg`
const visibleSlots = computed(() => store.slots.filter((slot: AvailabilitySlot) => {
  const hour = Number(new Date(slot.startsAt).toLocaleTimeString('en-US', { timeZone: 'Asia/Ho_Chi_Minh', hour: '2-digit', hour12: false }))
  return timeFilter.value === 'morning' ? hour < 17 : timeFilter.value === 'evening' ? hour >= 17 : true
}))

async function loadAvailability() {
  if (selected.value) {
    try {
      await store.loadAvailability(selected.value, date.value)
    } catch {
      /* store exposes the user-facing error */
    }
  }
}

async function chooseCourt() {
  await loadAvailability()
}

async function submit() {
  if (!auth.user) return router.push({ name: 'login', query: { redirect: '/booking' } })
  if (!store.selectedSlots.length) return
  loading.value = true
  error.value = ''
  try {
    const result = await store.createBooking()
    router.push('/payment/' + result.booking.code)
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    loading.value = false
  }
}

function scrollToAvailability() {
  document.getElementById('availability')?.scrollIntoView({ behavior: 'smooth' })
}

onMounted(async () => {
  try {
    await store.loadCourts()
    selected.value = store.courts[0]?.id || null
    await loadAvailability()
  } catch {
    /* store exposes the user-facing error */
  }
})
</script>

<template>
  <section class="hero">
    <div>
      <div class="eyebrow">{{ t('home.eyebrow') }}</div>
      <h1>{{ t('home.heroTitle') }} <em>{{ t('home.heroTitleEmphasis') }}</em></h1>
      <p>{{ t('home.heroSubtitle') }}</p>
      <div class="actions">
        <RouterLink to="/courts" class="btn">
          {{ t('home.bookNow') }} <span class="material-symbols-outlined">arrow_forward</span>
        </RouterLink>
        <a href="#features" class="btn secondary">{{ t('home.exploreAura') }}</a>
      </div>
    </div>
    <div class="hero-mosaic">
      <div>
        <img class="short" src="/aura/court-1.jpg" alt="Aura Court" />
        <img class="tall" src="/aura/court-2.jpg" alt="Aura Court Floor" style="margin-top:12px" />
      </div>
      <div>
        <img class="tall" src="/aura/court-3.jpg" alt="Badminton Player" />
        <div class="access-card" style="margin-top:12px">
          <div>
            <strong>{{ t('home.open247') }}</strong>
            <span>{{ t('home.openBadge') }}</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Multi-sport Categories Bar -->
  <section class="container" style="margin-top: -10px; margin-bottom: 20px;">
    <div class="sport-categories-strip">
      <button
        v-for="cat in sportCategories"
        :key="cat.id"
        type="button"
        class="sport-category-btn"
        :class="{ active: sportTypeFilter === cat.id }"
        @click="selectSportCategory(cat.id)"
      >
        <span class="material-symbols-outlined icon-20">{{ cat.icon }}</span>
        <span>{{ cat.name }}</span>
      </button>
    </div>
  </section>

  <section class="search-strip">
    <div class="search-field">
      <label>{{ t('home.search.court') }}</label>
      <SelectDropdown
        v-model="selected"
        :options="courtOptions"
        :placeholder="t('home.search.court')"
        :aria-label="t('home.search.court')"
        @change="chooseCourt"
      />
    </div>
    <div class="search-field">
      <label>{{ t('home.search.date') }}</label>
      <DatePicker v-model="date" @change="loadAvailability" />
    </div>
    <div class="search-field">
      <label>{{ t('home.search.timeSlot') }}</label>
      <SelectDropdown
        v-model="timeFilter"
        :options="timeOptions"
        :placeholder="t('home.search.timeSlot')"
        :aria-label="t('home.search.timeSlot')"
      />
    </div>
    <button class="search-action" :aria-label="t('home.search.searchAction')" @click="scrollToAvailability">search</button>
  </section>


  <section class="section" id="availability">
    <div class="section-head">
      <div>
        <div class="eyebrow">{{ t('home.availability.eyebrow') }}</div>
        <h2>{{ t('home.availability.title') }}</h2>
      </div>
      <RouterLink to="/courts" class="muted">
        {{ t('home.availability.viewAllCourts') }} <span class="material-symbols-outlined">arrow_forward</span>
      </RouterLink>
    </div>
    <p v-if="store.error" class="error">{{ store.error }}</p>

    <!-- Time Slots Loading / Loaded States -->
    <div v-if="store.slotsLoading" class="slot-preview">
      <SkeletonLoader type="slot" :count="8" />
    </div>
    <div v-else-if="visibleSlots.length" class="slot-preview">
      <button
        v-for="slot in visibleSlots"
        :key="slot.startsAt"
        class="slot"
        :class="{ selected: store.selectedSlots.some((item: AvailabilitySlot) => item.startsAt === slot.startsAt), unavailable: !slot.available }"
        :disabled="!slot.available"
        @click="store.toggle(slot)"
      >
        {{ formatTime(slot.startsAt) }}
        <small>{{ formatCurrency(slot.price) }}</small>
      </button>
    </div>
    <div v-else class="muted" style="margin: 16px 0 28px;">
      {{ t('home.availability.noSlots') }}
    </div>

    <!-- Courts Grid Loading / Loaded States -->
    <div v-if="store.courtsLoading && !store.courts.length" class="court-grid">
      <SkeletonLoader type="court-card" :count="3" />
    </div>
    <div v-else class="court-grid">
      <RouterLink
        v-for="(court, index) in store.courts"
        :key="court.id"
        :to="`/courts/${court.slug || court.id}`"
        class="court-card"
      >
        <div class="court-card-image">
          <img :src="image(index)" :alt="court.name" />
          <span class="tag">
            {{ t('courts.from') }} {{ formatCurrency(court.price_slots?.[0]?.price || 120000) }}{{ t('common.perHour') }}
          </span>
        </div>
        <div class="court-info">
          <h3>{{ court.name }}</h3>
          <p class="muted">
            <span class="material-symbols-outlined">location_on</span>
            {{ court.description || t('courts.defaultLocation') }}
          </p>
        </div>
      </RouterLink>
    </div>
  </section>

  <section class="section" style="padding-top:10px" id="features">
    <div class="section-head">
      <div>
        <div class="eyebrow">{{ t('home.features.eyebrow') }}</div>
        <h2>{{ t('home.features.title') }}</h2>
      </div>
    </div>
    <div class="feature-row">
      <article class="feature">
        <span class="feature-icon">bolt</span>
        <h3>{{ t('home.features.f1Title') }}</h3>
        <p class="muted">{{ t('home.features.f1Desc') }}</p>
      </article>
      <article class="feature">
        <span class="feature-icon">verified</span>
        <h3>{{ t('home.features.f2Title') }}</h3>
        <p class="muted">{{ t('home.features.f2Desc') }}</p>
      </article>
      <article class="feature">
        <span class="feature-icon">payments</span>
        <h3>{{ t('home.features.f3Title') }}</h3>
        <p class="muted">{{ t('home.features.f3Desc') }}</p>
      </article>
    </div>
  </section>

  <div v-if="store.selectedSlots.length" class="floating-booking">
    <span>{{ t('home.floating.summary', { count: store.selectedSlots.length, total: formatCurrency(total) }) }}</span>
    <button class="btn pink" :disabled="loading" @click="submit">{{ t('home.floating.proceed') }}</button>
  </div>
  <p v-if="error" class="error container">{{ error }}</p>
</template>

<style scoped>
.sport-categories-strip {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow-x: auto;
  padding: 8px 4px 12px;
  scrollbar-width: none;
}
.sport-categories-strip::-webkit-scrollbar {
  display: none;
}
.sport-category-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 9999px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
  color: var(--ink, #1a202c);
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.2s ease;
}
.sport-category-btn:hover {
  border-color: var(--pink, #ffd1dc);
  background: var(--pink-light, #fff5f7);
  color: var(--pink-dark, #78555e);
}
.sport-category-btn.active {
  background: var(--pink-dark, #78555e);
  color: #ffffff;
  border-color: var(--pink-dark, #78555e);
  box-shadow: 0 4px 12px rgba(120, 85, 94, 0.25);
}
</style>


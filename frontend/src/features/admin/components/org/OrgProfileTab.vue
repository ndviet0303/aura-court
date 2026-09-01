<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import SelectDropdown from '@/shared/components/forms/SelectDropdown.vue'
import type { Organization } from '../../composables/useOrganizationOwner'

const props = defineProps<{
  org: Organization
  brandColors: Array<{ label: string; value: string }>
  timezoneOptions: Array<{ label: string; value: string }>
  currencyOptions: Array<{ label: string; value: string }>
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'save', payload: Partial<Organization>): void
}>()

const { t } = useI18n()

const name = ref(props.org.name || '')
const slug = ref(props.org.slug || '')
const taxCode = ref(props.org.tax_code || '')
const supportEmail = ref(props.org.support_email || '')
const phone = ref(props.org.phone || '')
const address = ref(props.org.address || '')
const brandColor = ref(props.org.brand_color || '#6B2D3F')
const logoUrl = ref(props.org.logo_url || '')
const timezone = ref(props.org.timezone || 'Asia/Ho_Chi_Minh')
const currency = ref(props.org.currency || 'VND')

watch(
  () => props.org,
  (o) => {
    name.value = o.name || ''
    slug.value = o.slug || ''
    taxCode.value = o.tax_code || ''
    supportEmail.value = o.support_email || ''
    phone.value = o.phone || ''
    address.value = o.address || ''
    brandColor.value = o.brand_color || '#6B2D3F'
    logoUrl.value = o.logo_url || ''
    timezone.value = o.timezone || 'Asia/Ho_Chi_Minh'
    currency.value = o.currency || 'VND'
  },
  { deep: true }
)

function handleSave() {
  emit('save', {
    name: name.value.trim(),
    slug: slug.value.trim(),
    tax_code: taxCode.value.trim(),
    support_email: supportEmail.value.trim(),
    phone: phone.value.trim(),
    address: address.value.trim(),
    brand_color: brandColor.value,
    logo_url: logoUrl.value.trim(),
    timezone: timezone.value,
    currency: currency.value,
  })
}
</script>

<template>
  <div class="org-profile-tab-card">
    <div class="card-header">
      <div class="title-with-icon">
        <span class="material-symbols-outlined card-title-icon">badge</span>
        <div>
          <h3 class="tab-title">{{ t('admin.org.profileTitle') }}</h3>
          <p class="tab-sub">{{ t('admin.org.profileSubtitle') }}</p>
        </div>
      </div>
    </div>

    <form class="profile-form" @submit.prevent="handleSave">
      <div class="form-grid">
        <!-- Name -->
        <div class="form-group">
          <label class="form-label">
            {{ t('admin.org.orgNameLabel') }}
            <span class="req">*</span>
          </label>
          <input
            v-model="name"
            type="text"
            required
            class="form-input"
            placeholder="Aura Court Premium Badminton"
          />
        </div>

        <!-- Slug -->
        <div class="form-group">
          <label class="form-label">{{ t('admin.org.slugLabel') }}</label>
          <input
            v-model="slug"
            type="text"
            class="form-input font-mono"
            placeholder="aura-court-premium"
          />
        </div>

        <!-- Tax Code -->
        <div class="form-group">
          <label class="form-label">{{ t('admin.org.taxCodeLabel') }}</label>
          <input
            v-model="taxCode"
            type="text"
            class="form-input font-mono"
            placeholder="0108923456"
          />
        </div>

        <!-- Support Email -->
        <div class="form-group">
          <label class="form-label">{{ t('admin.org.supportEmailLabel') }}</label>
          <input
            v-model="supportEmail"
            type="email"
            class="form-input font-mono"
            placeholder="support@auracourt.vn"
          />
        </div>

        <!-- Hotline -->
        <div class="form-group">
          <label class="form-label">{{ t('admin.org.phoneLabel') }}</label>
          <input
            v-model="phone"
            type="tel"
            class="form-input font-mono"
            placeholder="1900 6868"
          />
        </div>

        <!-- Address -->
        <div class="form-group">
          <label class="form-label">{{ t('admin.org.addressLabel') }}</label>
          <input
            v-model="address"
            type="text"
            class="form-input"
            placeholder="18 Duy Tân, Cầu Giấy, Hà Nội"
          />
        </div>

        <!-- Timezone Dropdown -->
        <div class="form-group">
          <label class="form-label">{{ t('admin.org.timezoneLabel') }}</label>
          <SelectDropdown
            v-model="timezone"
            :options="timezoneOptions"
            variant="bordered"
          />
        </div>

        <!-- Currency Dropdown -->
        <div class="form-group">
          <label class="form-label">{{ t('admin.org.currencyLabel') }}</label>
          <SelectDropdown
            v-model="currency"
            :options="currencyOptions"
            variant="bordered"
          />
        </div>

        <!-- Logo URL & Preview -->
        <div class="form-group full-width">
          <label class="form-label">{{ t('admin.org.logoUrlLabel') }}</label>
          <div class="logo-input-row">
            <input
              v-model="logoUrl"
              type="url"
              class="form-input font-mono"
              :placeholder="t('admin.org.logoUrlPlaceholder')"
            />
            <div v-if="logoUrl" class="logo-preview-thumb">
              <img :src="logoUrl" alt="Logo preview" />
            </div>
          </div>
        </div>

        <!-- Brand Color Selector -->
        <div class="form-group full-width">
          <label class="form-label">{{ t('admin.org.brandColorLabel') }}</label>
          <div class="colors-palette-row">
            <button
              v-for="c in brandColors"
              :key="c.value"
              type="button"
              class="color-chip-card"
              :class="{ active: brandColor.toLowerCase() === c.value.toLowerCase() }"
              :aria-pressed="brandColor.toLowerCase() === c.value.toLowerCase()"
              @click="brandColor = c.value"
            >
              <span class="color-circle" :style="{ background: c.value }"></span>
              <span class="c-lbl">{{ c.label }}</span>
            </button>

            <!-- Custom Color Input -->
            <div class="custom-color-wrap">
              <div class="color-picker-btn-wrap" :style="{ '--picker-color': brandColor }">
                <input
                  v-model="brandColor"
                  type="color"
                  class="color-picker-input"
                  title="Custom color picker"
                />
                <span class="picker-swatch" :style="{ background: brandColor }"></span>
              </div>
              <input
                v-model="brandColor"
                type="text"
                class="form-input font-mono custom-hex-input"
                :placeholder="t('admin.org.customHexPlaceholder')"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Action Footer -->
      <div class="form-actions">
        <AuraButton
          type="submit"
          variant="primary"
          :disabled="saving"
        >
          <span
            class="material-symbols-outlined icon-18"
            :class="{ 'spin-icon': saving }"
          >
            {{ saving ? 'sync' : 'save' }}
          </span>
          <span>{{ t('admin.org.saveOrgBtn') }}</span>
        </AuraButton>
      </div>
    </form>
  </div>
</template>

<style scoped>
.org-profile-tab-card {
  display: flex;
  flex-direction: column;
  gap: 22px;
  padding: 28px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 12px;
}

.card-title-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.tab-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.tab-sub {
  margin: 2px 0 0 0;
  font-size: 12.5px;
  color: var(--muted);
}

.profile-form {
  display: flex;
  flex-direction: column;
  gap: 22px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 18px 20px;
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}

.full-width {
  grid-column: 1 / -1;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.req {
  color: #ef4444;
  margin-left: 2px;
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  font-family: inherit;
  box-sizing: border-box;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-input:focus {
  outline: none;
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.15);
}

.logo-input-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo-preview-thumb {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  overflow: hidden;
  flex-shrink: 0;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
}

.logo-preview-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.colors-palette-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}

.color-chip-card {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 10px;
  border: 1.5px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink, #191c1d);
  cursor: pointer;
  font-size: 12.5px;
  font-weight: 600;
  transition: all 0.2s ease;
  font-family: inherit;
}

.color-chip-card:hover {
  border-color: var(--pink-dark, #db2777);
  background: var(--soft, #f8f9fa);
  color: var(--ink, #191c1d);
}

.color-chip-card.active {
  border-color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.1);
  color: var(--pink-dark, #db2777);
  font-weight: 700;
}

:global(html.dark) .color-chip-card {
  background: var(--surface-subtle, #262024);
  color: var(--ink, #FAF7F3);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .color-chip-card:hover {
  background: var(--soft, #30292e);
  color: #ffffff;
  border-color: var(--pink-dark, #E58E9F);
}

:global(html.dark) .color-chip-card.active {
  background: rgba(229, 142, 159, 0.18);
  color: var(--pink-dark, #E58E9F);
  border-color: var(--pink-dark, #E58E9F);
}

.color-circle {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 1px solid rgba(0, 0, 0, 0.15);
  flex-shrink: 0;
}

:global(html.dark) .color-circle {
  border-color: rgba(255, 255, 255, 0.2);
}

.custom-color-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.color-picker-btn-wrap {
  position: relative;
  width: 40px;
  height: 40px;
  border-radius: 10px;
  border: 1.5px solid var(--outline);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--surface);
  cursor: pointer;
  box-sizing: border-box;
}

:global(html.dark) .color-picker-btn-wrap {
  background: var(--surface-subtle, #262024);
  border-color: var(--outline, #4d4049);
}

.color-picker-input {
  position: absolute;
  top: -10px;
  left: -10px;
  width: 60px;
  height: 60px;
  opacity: 0;
  cursor: pointer;
}

.picker-swatch {
  width: 100%;
  height: 100%;
  border-radius: 8px;
  pointer-events: none;
  border: 1px solid rgba(0, 0, 0, 0.1);
  box-sizing: border-box;
}

:global(html.dark) .picker-swatch {
  border-color: rgba(255, 255, 255, 0.2);
}

.custom-hex-input {
  width: 130px;
}

:global(html.dark) .form-input {
  background: var(--surface-subtle, #262024);
  color: var(--ink, #FAF7F3);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .form-input:focus {
  border-color: var(--pink-dark, #E58E9F);
  box-shadow: 0 0 0 3px rgba(229, 142, 159, 0.2);
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  border-top: 1px solid var(--outline);
  padding-top: 18px;
}

:global(html.dark) .org-profile-tab-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .tab-title {
  color: var(--ink, #FAF7F3);
}

:global(html.dark) .tab-sub {
  color: var(--muted, #a89fa4);
}

:global(html.dark) .form-label {
  color: var(--ink, #FAF7F3);
}

.icon-18 {
  font-size: 18px;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}
</style>

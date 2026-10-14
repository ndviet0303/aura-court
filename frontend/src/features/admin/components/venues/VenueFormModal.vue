<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { AdminVenueItem } from '../../composables/useAdminVenues'
import VenueBookingPolicyForm from './VenueBookingPolicyForm.vue'
import VenueStatusToggle from './VenueStatusToggle.vue'
import { formatVenueCoordinates, parseVenueCoordinates } from '../../utils/venueCoordinates'
import type { VenueBookingPolicySettings, VenueBookingPolicyVersion } from '../../types/admin'

const props = defineProps<{
  show: boolean
  venue: AdminVenueItem | null
  saving?: boolean
  bookingPolicy?: VenueBookingPolicyVersion | null
  loadingBookingPolicy?: boolean
  savingBookingPolicy?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: any): void
  (e: 'save-policy', settings: VenueBookingPolicySettings): void
}>()

const { t } = useI18n()

const name = ref('')
const address = ref('')
const city = ref('')
const district = ref('')
const ward = ref('')
const phone = ref('')
const email = ref('')
const description = ref('')
const imagesText = ref('')
const googleMapsUrl = ref('')
const coordinates = ref('')
const isActive = ref(true)
const validationError = ref('')

watch(
  () => props.venue,
  (v) => {
    validationError.value = ''
    name.value =
      typeof v?.name === 'string' ? v.name : (v?.name as any)?.vi || (v?.name as any)?.en || ''
    address.value =
      typeof v?.address === 'string'
        ? v.address
        : (v?.address as any)?.vi || (v?.address as any)?.en || ''
    city.value = v?.city || ''
    district.value = v?.district || ''
    ward.value = v?.ward || ''
    phone.value = v?.phone || ''
    email.value = v?.email || ''
    description.value = v?.description || ''
    imagesText.value = (v?.images || []).join('\n')
    googleMapsUrl.value = v?.google_maps_url || ''
    coordinates.value = formatVenueCoordinates(v)
    isActive.value = v?.is_active ?? true
  },
  { immediate: true },
)

const isValidMapsUrl = computed(() => {
  const url = googleMapsUrl.value.trim()
  if (!url) return false
  return (
    url.startsWith('https://') &&
    (url.includes('google.com') || url.includes('goo.gl') || url.includes('maps'))
  )
})

function handleSave() {
  validationError.value = ''
  const trimmedName = name.value.trim()
  const trimmedAddress = address.value.trim()
  const trimmedMaps = googleMapsUrl.value.trim()

  if (!trimmedName) {
    validationError.value = t('admin.venues.venueNameLabel') + ' is required'
    return
  }

  if (trimmedMaps && !isValidMapsUrl.value) {
    validationError.value = t('admin.venues.googleMapsInvalid')
    return
  }

  const coords = parseVenueCoordinates(coordinates.value)
  if (!coords) {
    validationError.value = t('admin.venues.coordinatesInvalid')
    return
  }

  emit('save', {
    name: trimmedName,
    address: trimmedAddress || undefined,
    city: city.value.trim() || undefined,
    district: district.value.trim() || undefined,
    ward: ward.value.trim() || undefined,
    phone: phone.value.trim() || undefined,
    email: email.value.trim() || undefined,
    description: description.value.trim() || undefined,
    images: imagesText.value
      .split(/\r?\n/)
      .map((image) => image.trim())
      .filter(Boolean),
    google_maps_url: trimmedMaps || undefined,
    ...coords,
    is_active: isActive.value,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop modal-overlay-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">{{
            venue ? 'edit' : 'add_business'
          }}</span>
          <h3 class="modal-title">
            {{
              venue ? t('admin.venues.editVenueModalTitle') : t('admin.venues.addVenueModalTitle')
            }}
          </h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <div v-if="validationError" class="form-error-banner">
          <span class="material-symbols-outlined icon-16">error</span>
          <span>{{ validationError }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">
            {{ t('admin.venues.venueNameLabel') }} <span class="required">*</span>
          </label>
          <input
            v-model="name"
            type="text"
            class="form-input"
            :placeholder="t('admin.venues.venueNamePlaceholder')"
            required
          />
        </div>

        <div class="form-group address-parts">
          <label class="form-label"
            >{{ t('admin.venues.cityLabel') }}<input v-model="city" type="text" class="form-input"
          /></label>
          <label class="form-label"
            >{{ t('admin.venues.districtLabel')
            }}<input v-model="district" type="text" class="form-input"
          /></label>
          <label class="form-label"
            >{{ t('admin.venues.wardLabel') }}<input v-model="ward" type="text" class="form-input"
          /></label>
        </div>

        <div class="form-group contact-parts">
          <label class="form-label"
            >{{ t('admin.venues.phoneLabel') }}<input v-model="phone" type="tel" class="form-input"
          /></label>
          <label class="form-label"
            >{{ t('admin.venues.emailLabel')
            }}<input v-model="email" type="email" class="form-input"
          /></label>
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.venues.descriptionLabel') }}</label>
          <textarea v-model="description" class="form-input" rows="3" maxlength="2000" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.venues.imagesLabel') }}</label>
          <textarea
            v-model="imagesText"
            class="form-input"
            rows="3"
            :placeholder="t('admin.venues.imagesHint')"
          />
        </div>

        <div class="form-group">
          <label class="form-label">
            {{ t('admin.venues.fullAddressLabel') }}
          </label>
          <input
            v-model="address"
            type="text"
            class="form-input"
            :placeholder="t('admin.venues.fullAddressPlaceholder')"
          />
        </div>

        <div class="form-group">
          <label class="form-label">
            {{ t('admin.venues.googleMapsLabel') }}
          </label>
          <input
            v-model="googleMapsUrl"
            type="url"
            class="form-input font-mono"
            :placeholder="t('admin.venues.googleMapsPlaceholder')"
          />
          <span class="field-hint">{{ t('admin.venues.googleMapsHint') }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.venues.coordinatesLabel') }}</label>
          <input
            v-model="coordinates"
            type="text"
            inputmode="decimal"
            class="form-input font-mono"
            placeholder="21.0306, 105.7830"
          />
          <span class="field-hint">{{ t('admin.venues.coordinatesHint') }}</span>
        </div>

        <!-- Custom Status Toggle Card -->
        <div v-if="venue?.status === 'published' || venue?.status === 'active'" class="form-group">
          <label class="form-label">{{ t('admin.venues.operationalStatusLabel') }}</label>
          <VenueStatusToggle v-model="isActive" />
        </div>

        <VenueBookingPolicyForm
          :policy="bookingPolicy ?? null"
          :loading="loadingBookingPolicy ?? false"
          :saving="savingBookingPolicy ?? false"
          :can-configure="!!venue"
          @save="emit('save-policy', $event)"
        />
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton variant="secondary" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton
          variant="primary"
          :loading="saving"
          :disabled="!name.trim()"
          @click="handleSave"
        >
          <span class="material-symbols-outlined icon-18">check</span>
          <span>{{ t('admin.venues.saveVenueBtn') }}</span>
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 18px;
  width: 100%;
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.28);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  max-height: 90vh;
  animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalPop {
  from {
    opacity: 0;
    transform: scale(0.96) translateY(8px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.modal-md {
  max-width: 520px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 22px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 18px;
  cursor: pointer;
  color: var(--muted);
  padding: 4px 8px;
  border-radius: 6px;
  transition: all 0.15s ease;
}

.modal-close-btn:hover {
  color: var(--ink);
  background: rgba(0, 0, 0, 0.06);
}

.modal-body {
  padding: 22px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  overflow-y: auto;
}

.form-error-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  font-size: 13px;
  font-weight: 600;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.address-parts,
.contact-parts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  gap: 10px;
}

.form-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}

.required {
  color: #ef4444;
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 13px;
  font-family: inherit;
  outline: none;
  transition:
    border-color 0.15s ease,
    box-shadow 0.15s ease;
  box-sizing: border-box;
}

.form-input:focus {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.15);
}

.field-hint {
  font-size: 11px;
  color: var(--muted);
  line-height: 1.4;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  padding: 16px 22px;
  border-top: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.icon-16 {
  font-size: 16px;
}
.icon-18 {
  font-size: 18px;
}
</style>

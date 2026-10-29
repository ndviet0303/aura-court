<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { AdminVenueItem } from '../../composables/useAdminVenues'
import VenueBookingPolicyForm from './VenueBookingPolicyForm.vue'
import VenueImageUploader from './VenueImageUploader.vue'
import { formatVenueCoordinates, parseVenueCoordinates } from '../../utils/venueCoordinates'
import type { VenueBookingPolicySettings, VenueBookingPolicyVersion } from '../../types/admin'
import type { VenueForm } from '../../types/admin'

const props = defineProps<{
  show: boolean
  venue: AdminVenueItem | null
  saving?: boolean
  error?: string
  fieldErrors?: Record<string, string[]>
  bookingPolicy?: VenueBookingPolicyVersion | null
  loadingBookingPolicy?: boolean
  savingBookingPolicy?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: VenueForm): void
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
const amenitiesText = ref('')
const images = ref<string[]>([])
const googleMapsUrl = ref('')
const coordinates = ref('')
const coordinatesError = ref('')

watch(
  () => (props.show ? props.venue : undefined), // refill on every open, not only on a new venue
  (v) => {
    coordinatesError.value = ''
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
    amenitiesText.value = (v?.amenities || []).join('\n')
    images.value = [...(v?.images || [])]
    googleMapsUrl.value = v?.google_maps_url || ''
    coordinates.value = formatVenueCoordinates(v)
  },
  { immediate: true },
)

/** The server message for a field, or for an item of a list field such as images.3. */
function err(field: string): string | undefined {
  const errors = props.fieldErrors ?? {}
  const key = Object.keys(errors).find((k) => k === field || k.startsWith(`${field}.`))
  return key ? errors[key]?.[0] : undefined
}

// The server trims and validates every field (BR-26) and answers 422 per field; an empty optional
// field goes as null so an update clears it instead of keeping the saved value.
function handleSave() {
  const coords = parseVenueCoordinates(coordinates.value)
  coordinatesError.value = coords ? '' : t('admin.venues.coordinatesInvalid')
  if (!coords) return

  emit('save', {
    name: name.value.trim(),
    address: address.value.trim(),
    phone: phone.value.trim(),
    email: email.value.trim(),
    city: city.value.trim() || null,
    district: district.value.trim() || null,
    ward: ward.value.trim() || null,
    description: description.value.trim() || null,
    amenities: amenitiesText.value
      .split(/\r?\n/)
      .map((item) => item.trim())
      .filter(Boolean),
    images: images.value,
    google_maps_url: googleMapsUrl.value.trim() || null,
    ...coords,
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
        <div v-if="error" class="form-error-banner">
          <span class="material-symbols-outlined icon-16">error</span>
          <span>{{ error }}</span>
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
          <p v-if="err('name')" class="field-error">{{ err('name') }}</p>
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
          <p v-if="err('city')" class="field-error">{{ err('city') }}</p>
          <p v-if="err('district')" class="field-error">{{ err('district') }}</p>
          <p v-if="err('ward')" class="field-error">{{ err('ward') }}</p>
        </div>

        <div class="form-group contact-parts">
          <label class="form-label"
            >{{ t('admin.venues.phoneLabel') }}<input v-model="phone" type="tel" class="form-input"
          /></label>
          <label class="form-label"
            >{{ t('admin.venues.emailLabel')
            }}<input v-model="email" type="email" class="form-input"
          /></label>
          <p v-if="err('phone')" class="field-error">{{ err('phone') }}</p>
          <p v-if="err('email')" class="field-error">{{ err('email') }}</p>
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.venues.descriptionLabel') }}</label>
          <textarea v-model="description" class="form-input" rows="3" maxlength="2000" />
          <p v-if="err('description')" class="field-error">{{ err('description') }}</p>
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.venues.amenitiesLabel') }}</label>
          <textarea v-model="amenitiesText" class="form-input" rows="3" />
          <span class="field-hint">{{ t('admin.venues.amenitiesHint') }}</span>
          <p v-if="err('amenities')" class="field-error">{{ err('amenities') }}</p>
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.venues.imagesLabel') }}</label>
          <VenueImageUploader v-model="images" :error="err('images')" />
        </div>

        <div class="form-group">
          <label class="form-label">
            {{ t('admin.venues.fullAddressLabel') }} <span class="required">*</span>
          </label>
          <input
            v-model="address"
            type="text"
            class="form-input"
            :placeholder="t('admin.venues.fullAddressPlaceholder')"
          />
          <p v-if="err('address')" class="field-error">{{ err('address') }}</p>
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
          <p v-if="err('google_maps_url')" class="field-error">{{ err('google_maps_url') }}</p>
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
          <p v-if="coordinatesError" class="field-error">{{ coordinatesError }}</p>
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
  grid-template-columns: repeat(auto-fit, minmax(min(130px, 100%), 1fr));
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

.field-error {
  grid-column: 1 / -1;
  margin: 0;
  font-size: 12px;
  color: #ef4444;
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

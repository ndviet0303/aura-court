<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { AdminVenueItem } from '../../composables/useAdminVenues'

const props = defineProps<{
  show: boolean
  venue: AdminVenueItem | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: any): void
}>()

const { t } = useI18n()

const name = ref('')
const address = ref('')
const googleMapsUrl = ref('')
const isActive = ref(true)

watch(
  () => props.venue,
  (v) => {
    if (v) {
      name.value = v.name
      address.value = v.address || ''
      googleMapsUrl.value = v.google_maps_url || ''
      isActive.value = v.is_active
    } else {
      name.value = ''
      address.value = ''
      googleMapsUrl.value = ''
      isActive.value = true
    }
  },
  { immediate: true }
)

function handleSave() {
  if (!name.value.trim()) return
  emit('save', {
    name: name.value.trim(),
    address: address.value.trim(),
    google_maps_url: googleMapsUrl.value.trim() || undefined,
    is_active: isActive.value,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">{{ venue ? 'edit' : 'add_location' }}</span>
          <h3 class="modal-title">
            {{ venue ? t('admin.venues.editVenueModalTitle') : t('admin.venues.addVenueModalTitle') }}
          </h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">{{ t('admin.venues.venueNameLabel') }} <span class="required">*</span>:</label>
          <input v-model="name" type="text" class="form-input" :placeholder="t('admin.venues.venueNamePlaceholder')" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.venues.fullAddressLabel') }}</label>
          <input v-model="address" type="text" class="form-input" :placeholder="t('admin.venues.fullAddressPlaceholder')" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.venues.googleMapsLabel') }}</label>
          <input v-model="googleMapsUrl" type="url" class="form-input font-mono" :placeholder="t('admin.venues.googleMapsPlaceholder')" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.venues.operationalStatusLabel') }}</label>
          <label class="check-box-label">
            <input v-model="isActive" type="checkbox" />
            <span>{{ t('admin.venues.operatingOpenCheckbox') }}</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">{{ t('common.cancel') }}</button>
        <button type="button" class="btn-primary" :disabled="!name.trim()" @click="handleSave">
          {{ t('admin.venues.saveVenueBtn') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.check-box-label {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
  cursor: pointer;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 8px 24px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>

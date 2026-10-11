<script setup lang="ts">
defineProps<{ fieldErrors: Record<string, string[]> }>()

const addressLine = defineModel<string>('addressLine', { required: true })
const ward = defineModel<string>('ward', { required: true })
const district = defineModel<string>('district', { required: true })
const city = defineModel<string>('city', { required: true })
const postalCode = defineModel<string>('postalCode', { required: true })
</script>

<template>
  <div class="form-section-card">
    <div class="form-section-header">
      <div class="section-icon-box"><span class="material-symbols-outlined">location_on</span></div>
      <div class="section-title-wrap">
        <h4 class="section-title">2. Địa chỉ & Vị trí Cụm sân</h4>
        <p class="section-subtitle">
          Vị trí thực tế để khách hàng tìm đường và định vị trên bản đồ
        </p>
      </div>
    </div>
    <div class="form-grid">
      <div class="form-field-item form-grid-full">
        <label for="address_line" class="field-label"
          >Địa chỉ chi tiết (Số nhà, tên đường) <span class="req-star">*</span></label
        >
        <input
          id="address_line"
          v-model="addressLine"
          type="text"
          placeholder="Ví dụ: 123 Kha Vạn Cân..."
          autocomplete="street-address"
          class="form-input"
        />
        <p v-if="fieldErrors.address_line" class="field-error-msg">
          {{ fieldErrors.address_line[0] }}
        </p>
      </div>
      <div class="form-field-item">
        <label for="ward" class="field-label">Phường / Xã</label>
        <input
          id="ward"
          v-model="ward"
          type="text"
          placeholder="Phường Linh Chiểu"
          class="form-input"
        />
        <p v-if="fieldErrors.ward" class="field-error-msg">{{ fieldErrors.ward[0] }}</p>
      </div>
      <div class="form-field-item">
        <label for="district" class="field-label">Quận / Huyện</label>
        <input
          id="district"
          v-model="district"
          type="text"
          placeholder="Thành phố Thủ Đức"
          class="form-input"
        />
        <p v-if="fieldErrors.district" class="field-error-msg">{{ fieldErrors.district[0] }}</p>
      </div>
      <div class="form-field-item">
        <label for="city" class="field-label"
          >Tỉnh / Thành phố <span class="req-star">*</span></label
        >
        <input
          id="city"
          v-model="city"
          type="text"
          placeholder="TP. Hồ Chí Minh"
          autocomplete="address-level1"
          class="form-input"
        />
        <p v-if="fieldErrors.city" class="field-error-msg">{{ fieldErrors.city[0] }}</p>
      </div>
      <div class="form-field-item">
        <label for="postal_code" class="field-label">Mã bưu chính</label>
        <input
          id="postal_code"
          v-model="postalCode"
          type="text"
          placeholder="700000"
          class="form-input"
        />
        <p v-if="fieldErrors.postal_code" class="field-error-msg">
          {{ fieldErrors.postal_code[0] }}
        </p>
      </div>
    </div>
  </div>
</template>

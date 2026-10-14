<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const isActive = defineModel<boolean>({ required: true })
const { t } = useI18n()
</script>

<template>
  <div class="status-toggle-card" :class="{ 'is-active': isActive }" @click="isActive = !isActive">
    <div class="toggle-card-left">
      <span
        class="material-symbols-outlined status-card-icon"
        :class="isActive ? 'text-green' : 'text-muted'"
      >
        {{ isActive ? 'check_circle' : 'pause_circle' }}
      </span>
      <div class="toggle-card-info">
        <strong class="toggle-card-title">{{ t('admin.venues.operatingOpenCheckbox') }}</strong>
        <span class="toggle-card-desc">
          {{ isActive ? t('admin.venues.statusActive') : t('admin.venues.statusInactive') }}
        </span>
      </div>
    </div>
    <div class="switch-control-wrap" @click.stop>
      <input v-model="isActive" type="checkbox" />
      <span class="slider"></span>
    </div>
  </div>
</template>

<style scoped>
.status-toggle-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  cursor: pointer;
  transition: all 0.15s ease;
}

.status-toggle-card:hover {
  border-color: rgba(219, 39, 119, 0.3);
}

.status-toggle-card.is-active {
  border-color: rgba(16, 185, 129, 0.3);
  background: rgba(16, 185, 129, 0.03);
}

.toggle-card-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.status-card-icon {
  font-size: 22px;
}

.toggle-card-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.toggle-card-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.toggle-card-desc {
  font-size: 11px;
  color: var(--muted);
}

/* Switch control */
.switch-control-wrap {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  cursor: pointer;
  flex-shrink: 0;
}

.switch-control-wrap input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #cbd5e1;
  transition: 0.2s;
  border-radius: 24px;
}

.slider:before {
  position: absolute;
  content: '';
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.2s;
  border-radius: 50%;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
}

input:checked + .slider {
  background-color: #10b981;
}

input:checked + .slider:before {
  transform: translateX(20px);
}

.text-green {
  color: #10b981;
}

.text-muted {
  color: var(--muted);
}
</style>

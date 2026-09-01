<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { CustomerProfile } from '../types/pos'

const props = defineProps<{
  show: boolean
  customerDatabase: CustomerProfile[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'select', customer: CustomerProfile): void
}>()

const { t } = useI18n()
const customerQuery = ref('')

const filteredCustomers = computed(() => {
  const q = customerQuery.value.trim().toLowerCase()
  if (!q) return props.customerDatabase
  return props.customerDatabase.filter(
    (c) => c.name.toLowerCase().includes(q) || c.phone.includes(q),
  )
})
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined modal-icon">person_search</span>
          <h3 class="modal-title">{{ t('admin.pos.customerSearch') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="search-input-wrap">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="customerQuery"
            type="text"
            class="form-input"
            :placeholder="t('admin.pos.customerQueryPlaceholder')"
            autofocus
          />
        </div>
        <div class="customer-list-box">
          <div
            v-for="cust in filteredCustomers"
            :key="cust.id"
            class="customer-select-item"
            @click="emit('select', cust)"
          >
            <div class="cust-info">
              <strong class="cust-name">{{ cust.name }}</strong>
              <span class="cust-phone">({{ cust.phone }})</span>
              <div class="cust-tier-tag">
                {{ t('admin.pos.tierDiscount', { tier: cust.tier, discount: cust.discount_rate * 100, points: cust.loyalty_points }) }}
              </div>
            </div>
            <button type="button" class="btn-select">
              {{ t('admin.pos.select') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 520px;
  background: var(--surface, #ffffff);
  border-radius: 16px;
  border: 1px solid var(--outline, #e2e8f0);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  overflow: hidden;
  color: var(--ink, #0f172a);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--outline, #e2e8f0);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.modal-close-btn {
  background: transparent;
  border: 0;
  color: var(--muted, #64748b);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  border-radius: 6px;
}

.modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.search-input-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline, #e2e8f0);
  background: var(--surface, #ffffff);
}

.search-icon {
  font-size: 20px;
  color: var(--muted, #64748b);
}

.form-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  width: 100%;
  color: var(--ink, #0f172a);
}

.customer-list-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 280px;
  overflow-y: auto;
}

.customer-select-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid var(--outline, #e2e8f0);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  cursor: pointer;
  transition: all 0.15s;
}

.customer-select-item:hover {
  border-color: var(--pink-dark, #db2777);
  background: var(--surface, #ffffff);
}

.cust-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}

.cust-name {
  font-weight: 700;
}

.cust-phone {
  color: var(--muted, #64748b);
  font-size: 12px;
}

.cust-tier-tag {
  font-size: 11px;
  color: var(--pink-dark, #db2777);
  font-weight: 600;
}

.btn-select {
  padding: 6px 14px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}
</style>

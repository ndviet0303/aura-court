<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { OperatingStatus, VenueSuspension } from '@/features/court/types/court'

defineProps<{
  status?: OperatingStatus
  suspension?: VenueSuspension | null
}>()

const { t } = useI18n()
</script>

<template>
  <span class="operating-badges">
    <span v-if="status" class="operating-chip" :class="`status-${status}`">
      ● {{ t(`admin.lifecycle.status.${status}`) }}
    </span>
    <span v-if="suspension" class="operating-chip status-suspended" :title="suspension.reason">
      {{ t('admin.lifecycle.suspended') }}
    </span>
  </span>
</template>

<style scoped>
.operating-badges {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 4px;
}

.operating-chip {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 700;
  color: #fff;
}

.status-inactive {
  background: rgba(100, 116, 139, 0.9);
}

.status-active {
  background: rgba(16, 185, 129, 0.9);
}

.status-paused {
  background: rgba(245, 158, 11, 0.9);
}

.status-closed {
  background: rgba(71, 85, 105, 0.95);
}

.status-suspended {
  background: rgba(239, 68, 68, 0.9);
}
</style>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { RemediationStatus } from '../../types/suspension'

defineProps<{ status: RemediationStatus | null }>()

const { t } = useI18n()
</script>

<template>
  <span class="remediation-chip" :class="`remediation-${status ?? 'none'}`">
    {{ t(`admin.remediation.status.${status ?? 'none'}`) }}
  </span>
</template>

<style scoped>
.remediation-chip {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
  background: rgba(100, 116, 139, 0.12);
  color: #475569;
}

.remediation-pending {
  background: rgba(59, 130, 246, 0.12);
  color: #1d4ed8;
}

.remediation-more_info_requested {
  background: rgba(245, 158, 11, 0.14);
  color: #b45309;
}

.remediation-rejected {
  background: rgba(239, 68, 68, 0.12);
  color: #b91c1c;
}

.remediation-approved {
  background: rgba(16, 185, 129, 0.12);
  color: #047857;
}
</style>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { CustomerTier } from '../types/customer'

defineProps<{ tier: CustomerTier }>()
const { t } = useI18n()
</script>

<template>
  <span class="tier" :class="`is-${tier}`">{{ t(`customers.tiers.${tier}`) }}</span>
</template>

<style scoped>
.tier {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.is-bronze {
  background: rgba(180, 83, 9, 0.12);
  color: #92400e;
}

.is-silver {
  background: rgba(100, 116, 139, 0.16);
  color: #334155;
}

.is-gold {
  background: rgba(234, 179, 8, 0.18);
  color: #854d0e;
}

.is-diamond {
  background: rgba(14, 165, 233, 0.16);
  color: #075985;
}
</style>

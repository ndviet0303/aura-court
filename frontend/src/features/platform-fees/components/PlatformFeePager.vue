<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{ page: number; lastPage: number; loading: boolean; label: string }>()
const emit = defineEmits<{ go: [page: number] }>()

const { t } = useI18n()
</script>

<template>
  <nav v-if="lastPage > 1" class="pager" :aria-label="label">
    <button type="button" :disabled="loading || page <= 1" @click="emit('go', page - 1)">
      {{ t('admin.platformFees.pager.prev') }}
    </button>
    <span>{{ t('admin.platformFees.pager.pageOf', { page, last: lastPage }) }}</span>
    <button type="button" :disabled="loading || page >= lastPage" @click="emit('go', page + 1)">
      {{ t('admin.platformFees.pager.next') }}
    </button>
  </nav>
</template>

<style scoped>
.pager {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  font-size: 13px;
}
</style>

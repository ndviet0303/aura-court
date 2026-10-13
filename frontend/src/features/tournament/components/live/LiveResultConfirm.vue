<script setup lang="ts">
import { AuraButton } from '@/shared/components'
import type { MatchFixture } from '../../composables/useLiveScore'

defineProps<{ match: MatchFixture }>()

const emit = defineEmits<{ (e: 'confirm'): void }>()
</script>

<template>
  <template v-if="match.status === 'completed'">
    <div class="completed-flag">🏆 ĐÃ KẾT THÚC</div>
    <template v-if="match.pending_result">
      <AuraButton
        size="sm"
        variant="success"
        block
        :loading="match.pending_result.saving"
        @click="emit('confirm')"
      >
        Lưu kết quả
      </AuraButton>
      <p v-if="match.pending_result.error" class="sync-error" role="alert">
        {{ match.pending_result.error }}
      </p>
    </template>
  </template>
</template>

<style scoped>
.completed-flag {
  font-size: 10.5px;
  font-weight: 800;
  color: #10b981;
  background: rgba(16, 185, 129, 0.12);
  padding: 4px 8px;
  border-radius: 6px;
  text-align: center;
}

.sync-error {
  margin: 0;
  font-size: 10.5px;
  font-weight: 700;
  color: #ef4444;
  text-align: center;
}
</style>

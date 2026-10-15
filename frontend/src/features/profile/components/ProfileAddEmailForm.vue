<script setup lang="ts">
import { ref } from 'vue'
import { AuraButton } from '@/shared/components'

// BR-06: a Facebook sign-up without an email adds one here before it can be verified.
defineProps<{ saving: boolean }>()

const emit = defineEmits<{ (e: 'submit', email: string): void }>()

const email = ref('')
</script>

<template>
  <form
    class="add-email-card"
    data-testid="add-email-form"
    @submit.prevent="emit('submit', email.trim())"
  >
    <div class="add-email-text">
      <strong>{{ $t('profile.addEmailTitle') }}</strong>
      <span>{{ $t('profile.addEmailHint') }}</span>
    </div>
    <div class="add-email-row">
      <input
        v-model="email"
        type="email"
        required
        class="add-email-input"
        autocomplete="email"
        :aria-label="$t('profile.addEmailTitle')"
        :placeholder="$t('profile.addEmailPlaceholder')"
      />
      <AuraButton type="submit" :disabled="saving || !email.trim()" :loading="saving">
        {{ $t('profile.addEmailBtn') }}
      </AuraButton>
    </div>
  </form>
</template>

<style scoped>
.add-email-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.add-email-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13.5px;
  color: var(--ink);
}

.add-email-text span {
  color: var(--muted);
}

.add-email-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.add-email-input {
  flex: 1;
  min-width: 220px;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 14px;
}
</style>

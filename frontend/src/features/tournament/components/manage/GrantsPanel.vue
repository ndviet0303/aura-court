<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { ManagedTournament } from '../../composables/useManagedTournament'
import type { Grant } from '../../types/tournament'

// UC-7.18: officials (assigned staff) for a public tournament, a representative for an internal one.
const props = defineProps<{ manage: ManagedTournament }>()
const { t } = useI18n()
const { data, can, busy } = props.manage
const email = ref('')
const role = computed<Grant['role']>(() =>
  data.value?.tournament.type === 'internal' ? 'representative' : 'official',
)

async function add() {
  if (await props.manage.grant(role.value, email.value)) email.value = ''
}
function revoke(grant: Grant) {
  if (window.confirm(t('tournament.grants.revokeConfirm', { name: grant.user.name })))
    void props.manage.revoke(grant)
}
</script>

<template>
  <section class="admin-detail-card">
    <h2 class="admin-detail-section-title">{{ t('tournament.grants.title') }}</h2>
    <p class="admin-detail-muted">{{ t(`tournament.grants.hint.${role}`) }}</p>
    <ul class="grants-list">
      <li v-for="grant in data?.grants ?? []" :key="grant.id">
        <span>
          <strong>{{ grant.user.name }}</strong> · {{ grant.user.email }} ·
          {{ t(`tournament.role.${grant.role}`) }}
        </span>
        <AuraButton
          v-if="can('grant')"
          variant="ghost"
          size="sm"
          :disabled="busy"
          @click="revoke(grant)"
        >
          {{ t('tournament.grants.revoke') }}
        </AuraButton>
      </li>
      <li v-if="!data?.grants.length" class="admin-detail-muted">
        {{ t('tournament.grants.empty') }}
      </li>
    </ul>
    <form v-if="can('grant')" class="grants-add" @submit.prevent="add">
      <label class="form-group">
        <span class="form-label">{{ t('tournament.grants.email') }}</span>
        <input v-model="email" type="email" class="form-input" required />
      </label>
      <AuraButton type="submit" :loading="busy" :disabled="!email.trim()">
        {{ t('tournament.grants.add', { role: t(`tournament.role.${role}`) }) }}
      </AuraButton>
    </form>
  </section>
</template>

<style scoped>
.grants-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 12px 0;
  padding: 0;
  list-style: none;
  font-size: 14px;
}

.grants-list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.grants-add {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 12px;
}
</style>

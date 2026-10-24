<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { AuraButton } from '@/shared/components'
import MemberFields from '../components/MemberFields.vue'
import { useTournamentRegistration } from '../composables/useTournamentRegistration'
import { formatMoney } from '../utils/tournament'

// FR-123, FR-124: one unit per customer; the owner approves it before the fee can be paid.
const { t } = useI18n()
const route = useRoute()
const id = computed(() => String(route.params.id))
const { tournament, loading, loadError, form, submitting, error, showProblem, submit } =
  useTournamentRegistration(id)
const { name, members, limits, reserves, problem } = form
</script>

<template>
  <div class="admin-detail-page">
    <RouterLink :to="`/tournaments/${id}`" class="admin-detail-back">
      ← {{ t('tournament.back') }}
    </RouterLink>
    <p v-if="loading" class="admin-detail-muted">{{ t('common.loading') }}</p>
    <p v-if="loadError" class="admin-detail-error" role="alert">{{ loadError }}</p>

    <form v-if="tournament" class="admin-detail-card" novalidate @submit.prevent="submit">
      <h1 class="admin-detail-title">
        {{ t('tournament.register.title', { name: tournament.name }) }}
      </h1>
      <p v-if="!tournament.can_register" class="admin-detail-error">
        {{ t('tournament.register.closed') }}
      </p>
      <p class="admin-detail-muted">
        {{ t('tournament.register.hint', { fee: formatMoney(tournament.fee) }) }}
      </p>

      <label class="form-group">
        <span class="form-label">{{ t('tournament.register.unitName') }} *</span>
        <input v-model="name" class="form-input" maxlength="255" required />
      </label>

      <MemberFields
        v-model="members"
        :fields="tournament.athlete_fields"
        :limits="limits"
        :can-add-reserve="reserves < limits.reserve"
        @add-reserve="form.addReserve"
        @remove="form.removeMember"
      />

      <p v-if="showProblem && problem" class="form-error" role="alert">
        {{ t(problem, { main: limits.main, reserve: limits.reserve }) }}
      </p>
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      <AuraButton type="submit" :loading="submitting" :disabled="!tournament.can_register">
        {{ t('tournament.register.submit') }}
      </AuraButton>
    </form>
  </div>
</template>

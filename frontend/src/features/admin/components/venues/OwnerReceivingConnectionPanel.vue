<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import {
  venuePublicationApi,
  type OwnerReceivingConnection,
  type ReceivingConnectionEvidenceItem,
} from '../../api/venuePublicationApi'

const props = defineProps<{
  organizationId: number
  venueId: number
  connection: OwnerReceivingConnection | null
  loading: boolean
  loadError?: string | null
}>()

const emit = defineEmits<{
  (e: 'updated', connection: OwnerReceivingConnection): void
  (e: 'refresh'): void
}>()

const { t, locale } = useI18n()
const saving = ref(false)
const syncing = ref(false)
const selecting = ref(false)
const addingConnection = ref(false)
const error = ref('')
const notice = ref('')
const bankCode = ref('MBBank')
const accountName = ref('')
const accountNumber = ref('')
const legalOwnerConfirmed = ref(false)
const hmacSecret = ref('')
const apiToken = ref('')
const providerAccountId = ref('')
const evidence = ref<ReceivingConnectionEvidenceItem[]>([])
const onboardingSteps = computed(() => locale.value.startsWith('vi')
  ? (props.connection?.onboarding?.instructions_vi ?? [])
  : (props.connection?.onboarding?.instructions_en ?? []))

watch(
  () => props.connection?.id,
  async (connectionId) => {
    evidence.value = []
    providerAccountId.value = props.connection?.provider_account_id ?? ''
    if (!connectionId) return
    try {
      const result = await venuePublicationApi.getReceivingConnectionEvidence(
        props.organizationId,
        props.venueId,
        connectionId,
      )
      evidence.value = result.data
    } catch {
      // The status and setup form remain available if evidence history is temporarily unavailable.
    }
  },
  { immediate: true },
)

async function createConnection() {
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    const result = await venuePublicationApi.createReceivingConnection(
      props.organizationId,
      props.venueId,
      {
        bank_code: bankCode.value.trim(),
        account_name: accountName.value.trim(),
        account_number: accountNumber.value.trim(),
        legal_owner_confirmed: true,
      },
    )
    accountNumber.value = ''
    accountName.value = ''
    legalOwnerConfirmed.value = false
    addingConnection.value = false
    notice.value = t('admin.venues.receivingConnection.created')
    emit('updated', result.data)
  } catch (cause) {
    error.value = errorMessage(cause, t('admin.venues.receivingConnection.saveFailed'))
  } finally {
    saving.value = false
  }
}

async function selectConnection() {
  if (!props.connection || props.connection.status !== 'verified' || props.connection.is_selected) return
  selecting.value = true
  error.value = ''
  notice.value = ''
  try {
    const result = await venuePublicationApi.selectReceivingConnection(
      props.organizationId,
      props.venueId,
      props.connection.id,
    )
    notice.value = t('admin.venues.receivingConnection.selected')
    emit('updated', result.data)
  } catch (cause) {
    error.value = errorMessage(cause, t('admin.venues.receivingConnection.selectFailed'))
  } finally {
    selecting.value = false
  }
}

async function rotateHmacSecret() {
  if (!props.connection || !hmacSecret.value) return
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    const result = await venuePublicationApi.rotateReceivingCredential(
      props.organizationId,
      props.venueId,
      props.connection.id,
      hmacSecret.value,
    )
    hmacSecret.value = ''
    notice.value = t('admin.venues.receivingConnection.secretSaved')
    emit('updated', result.data)
  } catch (cause) {
    error.value = errorMessage(cause, t('admin.venues.receivingConnection.secretFailed'))
  } finally {
    saving.value = false
  }
}

async function rotateApiToken() {
  if (!props.connection || !apiToken.value || !providerAccountId.value) return
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    const result = await venuePublicationApi.rotateReceivingApiToken(
      props.organizationId,
      props.venueId,
      props.connection.id,
      apiToken.value,
      providerAccountId.value.trim(),
    )
    apiToken.value = ''
    notice.value = t('admin.venues.receivingConnection.apiTokenSaved')
    emit('updated', result.data)
  } catch (cause) {
    error.value = errorMessage(cause, t('admin.venues.receivingConnection.apiTokenFailed'))
  } finally {
    saving.value = false
  }
}

async function syncTransactions() {
  if (!props.connection) return
  syncing.value = true
  error.value = ''
  notice.value = ''
  try {
    const result = await venuePublicationApi.syncReceivingTransactions(
      props.organizationId,
      props.venueId,
      props.connection.id,
    )
    notice.value = t('admin.venues.receivingConnection.syncComplete', {
      count: result.data.transactions_received,
    })
    emit('refresh')
  } catch (cause) {
    error.value = errorMessage(cause, t('admin.venues.receivingConnection.syncFailed'))
  } finally {
    syncing.value = false
  }
}
</script>

<template>
  <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900/40" data-testid="owner-receiving-connection-panel">
    <div class="flex items-start justify-between gap-3">
      <div>
      <h4 class="text-sm font-bold text-gray-900 dark:text-white">
        {{ t('admin.venues.receivingConnection.title') }}
      </h4>
      <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
        {{ t('admin.venues.receivingConnection.description') }}
      </p>
      </div>
      <button type="button" class="shrink-0 rounded-lg border border-gray-300 px-2.5 py-1.5 text-[11px] font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-300" data-testid="refresh-receiving-connection" @click="emit('refresh')">
        {{ t('admin.venues.receivingConnection.refreshStatus') }}
      </button>
    </div>

    <div v-if="loadError" class="rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/30 dark:text-red-300">
      {{ loadError }}
    </div>
    <div v-if="error" class="rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/30 dark:text-red-300">
      {{ error }}
    </div>
    <div v-if="notice" class="rounded-lg bg-emerald-50 p-3 text-xs text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">
      {{ notice }}
    </div>

    <div v-if="loading" class="py-5 text-center text-xs text-gray-500">
      {{ t('admin.venues.receivingConnection.loading') }}
    </div>

    <form v-if="(!connection || addingConnection) && !loading && !loadError" class="space-y-3" data-testid="receiving-connection-create" @submit.prevent="createConnection">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <label class="space-y-1 text-xs font-medium text-gray-700 dark:text-gray-300">
          <span>{{ t('admin.venues.receivingConnection.bankCode') }}</span>
          <input v-model="bankCode" required maxlength="32" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" autocomplete="off" data-testid="receiving-bank-code" />
        </label>
        <label class="space-y-1 text-xs font-medium text-gray-700 dark:text-gray-300">
          <span>{{ t('admin.venues.receivingConnection.accountName') }}</span>
          <input v-model="accountName" required maxlength="160" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" autocomplete="off" data-testid="receiving-account-name" />
        </label>
      </div>
      <label class="block space-y-1 text-xs font-medium text-gray-700 dark:text-gray-300">
        <span>{{ t('admin.venues.receivingConnection.accountNumber') }}</span>
        <input v-model="accountNumber" required inputmode="numeric" maxlength="34" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" autocomplete="off" data-testid="receiving-account-number" />
      </label>
      <label class="flex items-start gap-2 text-xs text-gray-700 dark:text-gray-300">
        <input v-model="legalOwnerConfirmed" type="checkbox" required class="mt-0.5 rounded border-gray-300" data-testid="receiving-legal-owner-confirmed" />
        <span>{{ t('admin.venues.receivingConnection.legalOwnerAttestation') }}</span>
      </label>
      <p class="text-[11px] text-gray-500 dark:text-gray-400">
        {{ t('admin.venues.receivingConnection.noBankPassword') }}
      </p>
      <button type="submit" :disabled="saving || !legalOwnerConfirmed" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50" data-testid="save-receiving-connection">
        {{ saving ? t('admin.venues.receivingConnection.saving') : t('admin.venues.receivingConnection.saveAccount') }}
      </button>
      <button v-if="connection" type="button" class="ml-2 rounded-lg border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-300" @click="addingConnection = false">
        {{ t('admin.venues.receivingConnection.cancelAddAccount') }}
      </button>
    </form>

    <div v-else-if="loadError" class="text-xs text-gray-600 dark:text-gray-400">
      <button type="button" class="underline" @click="emit('refresh')">
        {{ t('admin.venues.receivingConnection.retryLoad') }}
      </button>
    </div>

    <div v-if="connection && !addingConnection && !loading && !loadError" class="space-y-4" data-testid="receiving-connection-configured">
      <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
        <div>
          <p class="text-xs font-semibold text-gray-900 dark:text-white">
            {{ connection.bank_code }} · {{ connection.account_name }} · {{ connection.account_number_masked }}
          </p>
          <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
            {{ t('admin.venues.receivingConnection.credentialVersion', { version: connection.credential_version }) }}
          </p>
        </div>
        <span
          class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
          :class="connection.status === 'verified' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'"
          data-testid="receiving-connection-status"
        >
          {{ t(`admin.venues.receivingConnection.status.${connection.status}`) }}
        </span>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <span v-if="connection.is_selected" class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300" data-testid="selected-receiver-badge">
          {{ t('admin.venues.receivingConnection.selected') }}
        </span>
        <button v-else-if="connection.status === 'verified'" type="button" :disabled="selecting" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50" data-testid="select-receiving-connection" @click="selectConnection">
          {{ selecting ? t('admin.venues.receivingConnection.selecting') : t('admin.venues.receivingConnection.select') }}
        </button>
        <span v-else class="text-[11px] text-amber-700 dark:text-amber-300" data-testid="receiver-selection-required">
          {{ t('admin.venues.receivingConnection.verifyBeforeSelecting') }}
        </span>
        <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-300" data-testid="add-receiving-connection" @click="addingConnection = true">
          {{ t('admin.venues.receivingConnection.addAnotherAccount') }}
        </button>
      </div>

      <div class="space-y-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
        <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">
          {{ t('admin.venues.receivingConnection.sepaySetup') }}
        </p>
        <code class="block break-all rounded bg-gray-100 p-2 text-[11px] text-gray-700 dark:bg-gray-800 dark:text-gray-300" data-testid="sepay-webhook-url">{{ connection.webhook_url }}</code>
        <ol class="list-inside list-decimal space-y-1 text-[11px] text-gray-600 dark:text-gray-400">
          <li v-for="(instruction, index) in onboardingSteps" :key="index">{{ instruction }}</li>
        </ol>
      </div>

      <div class="space-y-2">
        <label class="block space-y-1 text-xs font-medium text-gray-700 dark:text-gray-300">
          <span>{{ t('admin.venues.receivingConnection.hmacSecret') }}</span>
          <input v-model="hmacSecret" type="password" autocomplete="new-password" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" data-testid="hmac-secret-input" />
        </label>
        <button type="button" :disabled="saving || hmacSecret.length < 32" class="rounded-lg border border-emerald-600 px-3 py-2 text-xs font-semibold text-emerald-700 disabled:opacity-50 dark:text-emerald-300" data-testid="save-hmac-secret" @click="rotateHmacSecret">
          {{ saving ? t('admin.venues.receivingConnection.saving') : t('admin.venues.receivingConnection.saveHmacSecret') }}
        </button>
        <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ t('admin.venues.receivingConnection.secretNeverShown') }}</p>
      </div>

      <details class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
        <summary class="cursor-pointer text-xs font-semibold text-gray-800 dark:text-gray-200">
          {{ t('admin.venues.receivingConnection.optionalQuery') }}
        </summary>
        <div class="mt-3 space-y-3">
          <p class="text-[11px] text-gray-500 dark:text-gray-400">
            {{ t('admin.venues.receivingConnection.queryApiNote') }}
          </p>
          <label class="block space-y-1 text-xs font-medium text-gray-700 dark:text-gray-300">
            <span>{{ t('admin.venues.receivingConnection.providerAccountId') }}</span>
            <input v-model="providerAccountId" :placeholder="connection.provider_account_id || ''" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" autocomplete="off" />
          </label>
          <label class="block space-y-1 text-xs font-medium text-gray-700 dark:text-gray-300">
            <span>{{ t('admin.venues.receivingConnection.apiToken') }}</span>
            <input v-model="apiToken" type="password" autocomplete="new-password" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" data-testid="sepay-api-token-input" />
          </label>
          <button type="button" :disabled="saving || apiToken.length !== 64 || !providerAccountId" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 disabled:opacity-50 dark:border-gray-600 dark:text-gray-300" data-testid="save-sepay-api-token" @click="rotateApiToken">
            {{ t('admin.venues.receivingConnection.saveApiToken') }}
          </button>
          <button v-if="connection.query_api_configured && connection.status === 'verified'" type="button" :disabled="syncing" class="ml-2 rounded-lg bg-gray-800 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50 dark:bg-gray-200 dark:text-gray-900" data-testid="sync-sepay-transactions" @click="syncTransactions">
            {{ syncing ? t('admin.venues.receivingConnection.syncing') : t('admin.venues.receivingConnection.syncTransactions') }}
          </button>
        </div>
      </details>

      <div v-if="evidence.length" class="space-y-2">
        <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">{{ t('admin.venues.receivingConnection.evidenceTitle') }}</p>
        <div v-for="event in evidence" :key="event.id" class="flex justify-between rounded bg-gray-50 px-3 py-2 text-[11px] dark:bg-gray-800">
          <span>{{ event.event_type }} · {{ t('admin.venues.receivingConnection.version', { version: event.credential_version }) }}</span>
          <time>{{ event.created_at ? new Date(event.created_at).toLocaleString() : '' }}</time>
        </div>
      </div>
    </div>
  </section>
</template>

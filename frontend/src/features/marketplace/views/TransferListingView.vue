<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { formatDateTime } from '@/i18n'
import { AuraButton } from '@/shared/components'
import CreateTransferModal from '../components/CreateTransferModal.vue'
import ListingContactList from '../components/ListingContactList.vue'
import ListingItemList from '../components/ListingItemList.vue'
import { useTransferListing } from '../composables/useTransferListing'
import type { ListingContact } from '../types/transfer'

// UC-9.5..UC-9.7: a buyer picks bookings and contacts the seller; the seller sees contacts, creates a
// transaction for one of them and withdraws bookings no transaction holds.
const { t } = useI18n()
const route = useRoute()
const id = computed(() => Number(route.params.id))
const {
  listing,
  available,
  selected,
  refusals,
  loading,
  busy,
  error,
  notice,
  load,
  toggle,
  contact,
  withdraw,
} = useTransferListing(id)

const creating = ref<{ conversationId?: number; reservationIds?: number[] } | null>(null)
const createFor = (c: ListingContact) =>
  (creating.value = { conversationId: c.conversation_id, reservationIds: c.reservation_ids })

function closeCreate() {
  creating.value = null
  void load()
}
</script>

<template>
  <main class="resale-page">
    <RouterLink :to="{ name: 'resale-marketplace' }" class="back"
      >← {{ t('resale.back') }}</RouterLink
    >
    <p v-if="loading && !listing" class="muted">{{ t('common.loading') }}</p>

    <template v-if="listing">
      <header class="listing-head">
        <h1>{{ t('resale.detail.title') }}</h1>
        <p class="muted">
          {{ t('resale.seller') }}: <strong>{{ listing.seller.name }}</strong> ·
          {{ t('resale.postedAt', { time: formatDateTime(listing.created_at) }) }}
        </p>
        <p v-if="listing.note" class="listing-note">{{ listing.note }}</p>
      </header>

      <section class="items">
        <h2>{{ t('resale.detail.items') }}</h2>
        <p class="muted">
          {{ t(listing.is_mine ? 'resale.detail.sellerHint' : 'resale.detail.pickHint') }}
        </p>
        <ListingItemList
          :items="listing.items"
          :selected="selected"
          :selectable="available.length > 0"
          :refusals="refusals"
          @toggle="toggle"
        />
        <div class="actions">
          <template v-if="listing.is_mine">
            <AuraButton
              variant="outline"
              :disabled="!selected.length"
              :loading="busy"
              data-testid="listing-withdraw"
              @click="withdraw"
            >
              {{ t('resale.detail.withdraw') }}
            </AuraButton>
            <AuraButton
              :disabled="!available.length"
              @click="creating = { reservationIds: selected }"
            >
              {{ t('resale.create.open') }}
            </AuraButton>
          </template>
          <AuraButton
            v-else
            :disabled="!selected.length"
            :loading="busy"
            data-testid="listing-contact"
            @click="contact"
          >
            {{ t('resale.detail.contact') }}
          </AuraButton>
        </div>
        <p v-if="notice" class="notice" role="status">{{ notice }}</p>
      </section>

      <ListingContactList
        v-if="listing.is_mine"
        :contacts="listing.contacts ?? []"
        :can-create="available.length > 0"
        @create="createFor"
      />
    </template>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>

    <CreateTransferModal
      v-if="creating && listing"
      :listing-id="listing.id"
      :conversation-id="creating.conversationId"
      :reservation-ids="creating.reservationIds"
      @close="closeCreate"
    />
  </main>
</template>

<style scoped>
.resale-page {
  display: grid;
  gap: 16px;
  max-width: 960px;
  margin: 0 auto;
  padding: 24px 16px 48px;
  color: var(--ink);
}
.back {
  color: var(--muted);
  font-size: 13px;
}
.listing-head h1 {
  margin: 0 0 4px;
  font-size: 22px;
}
.listing-note {
  margin: 8px 0 0;
  white-space: pre-line;
  overflow-wrap: anywhere;
}
.items {
  display: grid;
  gap: 10px;
}
.items h2 {
  margin: 0;
  font-size: 16px;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}
.notice {
  margin: 0;
  color: #16a34a;
  font-size: 13px;
}
.muted {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}
</style>

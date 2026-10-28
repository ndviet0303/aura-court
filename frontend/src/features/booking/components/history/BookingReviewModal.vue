<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton } from '@/shared/components'
import ImageGrid from '@/features/community/components/ImageGrid.vue'
import ImageUploadField from '@/features/community/components/ImageUploadField.vue'
import { REVIEW_BODY_MAX, REVIEW_IMAGES_MAX } from '@/features/community/types/review'
import type { Reservation } from '../../types/reservation'
import { displayCode } from '../../utils/reservation'
import { useBookingReview, type ReviewTarget } from '../../composables/useBookingReview'

// The venue's review list opens it without the court; the booking history passes the whole booking.
const props = defineProps<{
  booking: ReviewTarget & Partial<Pick<Reservation, 'court' | 'legacy_mapping'>>
}>()
const emit = defineEmits<{ close: []; saved: [notice: string] }>()

const { t } = useI18n()
const { existing, mode, rating, body, images, saving, error, save, remove } = useBookingReview(
  props.booking,
)
const hovered = ref<number | null>(null)
const uploading = ref(false)

async function done(action: () => Promise<string | null>) {
  const notice = await action()
  if (notice) emit('saved', notice)
}
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="review-modal-title">
      <div class="modal-header">
        <h3 id="review-modal-title" class="modal-title">{{ t(`reviews.modal.${mode}`) }}</h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body review-modal-body">
        <p class="review-modal-booking">
          <template v-if="booking.court">
            <strong>{{ booking.court.code }}</strong>
            · {{ resolveLocalizedText(booking.court.venue?.name) }} ·
          </template>
          #{{ displayCode(booking) }}
        </p>

        <template v-if="mode === 'view' && existing">
          <p
            class="review-modal-stars"
            :aria-label="t('reviews.ratingLabel', { rating: existing.rating })"
          >
            {{ '★'.repeat(existing.rating) }}{{ '☆'.repeat(5 - existing.rating) }}
          </p>
          <p v-if="existing.body" class="review-modal-text">{{ existing.body }}</p>
          <ImageGrid :images="existing.images" />
          <p class="form-hint">{{ t('reviews.editClosed') }}</p>
        </template>

        <template v-else>
          <div class="form-group">
            <span class="form-label">{{ t('reviews.rating') }} *</span>
            <div class="review-modal-picker" @mouseleave="hovered = null">
              <button
                v-for="star in 5"
                :key="star"
                type="button"
                class="review-modal-star"
                :class="{ active: (hovered ?? rating) >= star }"
                :aria-label="t('reviews.ratingLabel', { rating: star })"
                :aria-pressed="rating === star"
                @mouseenter="hovered = star"
                @click="rating = star"
              >
                <span class="material-symbols-outlined">star</span>
              </button>
            </div>
          </div>
          <label class="form-group">
            <span class="form-label">{{ t('reviews.body') }}</span>
            <textarea
              v-model="body"
              rows="4"
              :maxlength="REVIEW_BODY_MAX"
              class="form-textarea"
              :placeholder="t('reviews.bodyPlaceholder')"
            />
            <span class="form-hint">{{ body.length }}/{{ REVIEW_BODY_MAX }}</span>
          </label>
          <ImageUploadField
            v-model="images"
            v-model:uploading="uploading"
            :max="REVIEW_IMAGES_MAX"
          />
          <p class="form-hint">{{ t('reviews.editWindow') }}</p>
        </template>

        <div v-if="existing?.reply" class="review-modal-reply">
          <strong>{{ t('reviews.replyFromVenue') }}</strong>
          <p class="review-modal-text">{{ existing.reply.body }}</p>
        </div>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      </div>

      <div class="modal-footer">
        <AuraButton
          v-if="mode === 'edit'"
          variant="danger"
          class="review-modal-delete"
          :disabled="saving"
          @click="done(remove)"
        >
          {{ t('common.delete') }}
        </AuraButton>
        <AuraButton variant="outline" :disabled="saving" @click="emit('close')">
          {{ mode === 'view' ? t('common.close') : t('common.cancel') }}
        </AuraButton>
        <AuraButton
          v-if="mode !== 'view'"
          :loading="saving"
          :disabled="uploading"
          @click="done(save)"
        >
          {{ mode === 'edit' ? t('common.save') : t('reviews.submit') }}
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.review-modal-body {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.review-modal-booking {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}

.review-modal-picker {
  display: flex;
  gap: 2px;
}

.review-modal-star {
  display: flex;
  padding: 2px;
  border: 0;
  background: none;
  color: var(--outline);
  cursor: pointer;
}

.review-modal-star .material-symbols-outlined {
  font-size: 30px;
  font-variation-settings: 'FILL' 1;
}

.review-modal-star.active {
  color: #f59e0b;
}

.review-modal-stars {
  margin: 0;
  color: #f59e0b;
  font-size: 20px;
}

.review-modal-text {
  margin: 0;
  white-space: pre-line;
  overflow-wrap: anywhere;
}

.review-modal-reply {
  padding: 10px 12px;
  border-left: 3px solid var(--primary, #6b2d3f);
  border-radius: 8px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.03));
  font-size: 13px;
}

.review-modal-delete {
  margin-right: auto;
}
</style>

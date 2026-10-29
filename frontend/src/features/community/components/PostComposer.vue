<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton, SelectDropdown } from '@/shared/components'
import { SPORT_TYPES, type SportType } from '@/features/court/types/court'
import { usePostComposer } from '../composables/usePostComposer'
import { POST_IMAGES_MAX, POST_MAX, type Post, type PostType } from '../types/community'
import ImageUploadField from './ImageUploadField.vue'

const props = defineProps<{ post?: Post | null }>()
const emit = defineEmits<{ saved: [post: Post]; cancel: [] }>()

const { t } = useI18n()
const { form, venues, saving, error, submit } = usePostComposer(props.post ?? null)
const uploading = ref(false)

const TYPES: PostType[] = ['share', 'matchmaking']
const sportOptions = SPORT_TYPES.map((s) => ({ value: s, label: t(`sportTypes.${s}`) }))
const venueOptions = computed(() => [
  { value: 0, label: t('community.composer.noVenue') },
  ...venues.value.map((v) => ({ value: v.id, label: resolveLocalizedText(v.name) })),
])

async function save() {
  const post = await submit()
  if (post) emit('saved', post)
}
</script>

<template>
  <form class="composer" @submit.prevent="save">
    <div class="composer-types" role="radiogroup" :aria-label="t('community.composer.type')">
      <label v-for="type in TYPES" :key="type" class="composer-type">
        <input v-model="form.type" type="radio" :value="type" />
        <span>{{ t(`community.types.${type}`) }}</span>
      </label>
    </div>

    <div class="form-group">
      <textarea
        v-model="form.content"
        rows="4"
        :aria-label="t('community.composer.content')"
        :maxlength="POST_MAX"
        class="form-textarea"
        :placeholder="t(`community.composer.placeholder.${form.type}`)"
      />
      <span class="form-hint">{{ form.content.length }}/{{ POST_MAX }}</span>
    </div>

    <fieldset v-if="form.type === 'matchmaking'" class="composer-match">
      <legend class="form-label">{{ t('community.composer.matchLegend') }}</legend>
      <label class="form-group">
        <span class="form-label">{{ t('community.match.sport') }} *</span>
        <SelectDropdown
          :model-value="form.sport"
          :options="sportOptions"
          variant="bordered"
          :placeholder="t('community.composer.pickSport')"
          :aria-label="t('community.match.sport')"
          @update:model-value="form.sport = $event as SportType"
        />
      </label>
      <label class="form-group">
        <span class="form-label">{{ t('community.match.playAt') }} *</span>
        <input v-model="form.playAt" type="datetime-local" class="form-input" />
      </label>
      <label class="form-group">
        <span class="form-label">{{ t('community.composer.venue') }}</span>
        <SelectDropdown
          :model-value="form.venueId"
          :options="venueOptions"
          variant="bordered"
          :aria-label="t('community.composer.venue')"
          @update:model-value="form.venueId = Number($event)"
        />
      </label>
      <label v-if="!form.venueId" class="form-group">
        <span class="form-label">{{ t('community.composer.address') }}</span>
        <input v-model="form.address" type="text" maxlength="255" class="form-input" />
      </label>
      <label class="form-group">
        <span class="form-label">{{ t('community.match.courtCode') }}</span>
        <input v-model="form.courtCode" type="text" maxlength="50" class="form-input" />
      </label>
      <p class="form-hint composer-wide">{{ t('community.composer.placeHint') }}</p>
      <p class="form-hint composer-wide">{{ t('community.match.disclaimer') }}</p>
    </fieldset>

    <ImageUploadField v-model="form.images" v-model:uploading="uploading" :max="POST_IMAGES_MAX" />

    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <div class="composer-actions">
      <AuraButton v-if="post" variant="outline" :disabled="saving" @click="emit('cancel')">
        {{ t('common.cancel') }}
      </AuraButton>
      <AuraButton type="submit" :loading="saving" :disabled="uploading">
        {{ post ? t('common.save') : t('community.composer.publish') }}
      </AuraButton>
    </div>
  </form>
</template>

<style scoped>
.composer {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.composer-types {
  display: flex;
  gap: 8px;
}

.composer-type {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border: 1px solid var(--outline);
  border-radius: 999px;
  font-size: 13px;
  cursor: pointer;
}

.composer-type:has(input:checked) {
  border-color: var(--primary, #6b2d3f);
  color: var(--primary, #6b2d3f);
  font-weight: 700;
}

.composer-match {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr));
  gap: 12px;
  margin: 0;
  padding: 12px;
  border: 1px solid var(--outline);
  border-radius: 12px;
}

.composer-wide {
  grid-column: 1 / -1;
  margin: 0;
}

.composer-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>

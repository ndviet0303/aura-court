<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import { AuraButton } from '@/shared/components'
import type { PromotionProgramCodes, VoucherCode } from '../../types/promotionProgram'
import { usePromotionProgramLabels } from '../../composables/usePromotionProgramLabels'

const props = defineProps<{
  title: string
  codes: PromotionProgramCodes
}>()

const emit = defineEmits<{ (e: 'close'): void }>()

const { t } = useI18n()
const { codeState, courtName } = usePromotionProgramLabels()

const selected = ref<VoucherCode | null>(props.codes.codes[0] ?? null)
const qrUrl = ref('')
const copied = ref('')
const counts = computed(() =>
  props.codes.codes.reduce<Record<string, number>>(
    (total, code) => ({ ...total, [code.state]: (total[code.state] ?? 0) + 1 }),
    {},
  ),
)

// Drawn on the device, so the code never goes to a third-party QR service.
watch(
  selected,
  async (code) => {
    qrUrl.value = ''
    const link = code?.deep_link_url
    if (!link) return
    const url = await QRCode.toDataURL(link, { width: 280, margin: 2 })
    if (selected.value?.deep_link_url === link) qrUrl.value = url
  },
  { immediate: true },
)

async function copy(text?: string | null) {
  if (!text) return
  try {
    await navigator.clipboard.writeText(text)
    copied.value = text
  } catch {
    copied.value = ''
  }
}
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg" data-testid="promotion-program-codes">
      <div class="modal-header">
        <h3 class="modal-title">{{ title }}</h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('admin.promotions.form.cancel')"
          @click="emit('close')"
        >
          ✕
        </button>
      </div>

      <div class="modal-body codes-layout">
        <section v-if="selected" class="qr-panel">
          <strong class="qr-code">{{ selected.code }}</strong>
          <img
            v-if="qrUrl"
            :src="qrUrl"
            :alt="t('admin.promotions.codes.qrAlt', { code: selected.code })"
            width="220"
            height="220"
          />
          <a
            v-if="selected.deep_link_url"
            class="deep-link"
            :href="selected.deep_link_url"
            target="_blank"
            rel="noopener"
            >{{ selected.deep_link_url }}</a
          >
          <p v-if="codes.court" class="form-hint">
            {{ t('admin.promotions.codes.linkCourt', { court: courtName(codes.court.name) }) }}
          </p>
          <p v-else class="form-hint">{{ t('admin.promotions.codes.noLink') }}</p>
          <div class="qr-actions">
            <AuraButton
              variant="secondary"
              size="sm"
              icon="content_copy"
              @click="copy(selected?.code)"
            >
              {{ t('admin.promotions.codes.copyCode') }}
            </AuraButton>
            <AuraButton
              v-if="selected.deep_link_url"
              variant="secondary"
              size="sm"
              icon="link"
              @click="copy(selected?.deep_link_url)"
            >
              {{ t('admin.promotions.codes.copyLink') }}
            </AuraButton>
            <a v-if="qrUrl" class="qr-download" :href="qrUrl" :download="`${selected.code}.png`">
              {{ t('admin.promotions.codes.downloadQr') }}
            </a>
          </div>
          <p v-if="copied" class="form-hint" role="status">
            {{ t('admin.promotions.codes.copied') }}
          </p>
        </section>

        <section class="code-list">
          <p class="form-hint">
            {{
              t('admin.promotions.codes.summary', {
                available: counts.available ?? 0,
                held: counts.held ?? 0,
                used: counts.used ?? 0,
              })
            }}
          </p>
          <p v-if="codes.codes.length === 0" class="form-hint">
            {{ t('admin.promotions.codes.empty') }}
          </p>
          <ul class="code-rows">
            <li v-for="code in codes.codes" :key="code.id">
              <button
                type="button"
                class="code-row"
                :class="{ 'is-selected': code.id === selected?.id }"
                data-testid="voucher-code"
                @click="selected = code"
              >
                <span class="code-text">{{ code.code }}</span>
                <span class="badge" :class="`badge--${code.state}`">{{
                  codeState(code.state)
                }}</span>
              </button>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.codes-layout {
  display: grid;
  grid-template-columns: minmax(240px, 1fr) 1fr;
  align-items: start;
  gap: 20px;
}

@media (max-width: 720px) {
  .codes-layout {
    grid-template-columns: 1fr;
  }
}

.qr-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  text-align: center;
}

.qr-code,
.code-text {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  letter-spacing: 0.08em;
}

.qr-code {
  font-size: 18px;
}

.deep-link {
  max-width: 100%;
  color: var(--muted);
  font-size: 12px;
  word-break: break-all;
}

.qr-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 8px;
}

.qr-download {
  color: var(--pink-dark, #db2777);
  font-size: 13px;
  font-weight: 600;
}

.code-rows {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-height: 420px;
  margin: 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;
}

.code-row {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 6px 10px;
  border: 1px solid var(--outline);
  border-radius: 8px;
  background: transparent;
  color: var(--ink);
  font-size: 13px;
  cursor: pointer;
}

.code-row.is-selected {
  border-color: var(--pink-dark, #db2777);
}

.badge {
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--soft, rgba(0, 0, 0, 0.05));
  font-size: 12px;
  font-weight: 600;
}

.badge--available {
  color: #047857;
  background: rgba(16, 185, 129, 0.12);
}

.badge--held {
  color: #b45309;
  background: rgba(245, 158, 11, 0.12);
}

.badge--used {
  color: var(--muted);
}
</style>

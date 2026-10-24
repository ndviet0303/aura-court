<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useRosterImport } from '../../composables/useRosterImport'

// UC-7.27, D24: template → upload → preview (nothing written) → confirm replaces the internal roster.
const props = defineProps<{ tournamentId: string }>()
const emit = defineEmits<{ confirmed: [] }>()
const { t } = useI18n()
const { file, preview, working, error, choose, confirm, template } = useRosterImport(
  () => props.tournamentId,
  () => emit('confirmed'),
)

function onPick(event: Event) {
  const input = event.target as HTMLInputElement
  void choose(input.files?.[0] ?? null)
  input.value = ''
}
</script>

<template>
  <section class="admin-detail-card">
    <h2 class="admin-detail-section-title">{{ t('tournament.roster.importTitle') }}</h2>
    <p class="admin-detail-muted">{{ t('tournament.roster.importHint') }}</p>
    <div class="roster-actions">
      <AuraButton variant="outline" size="sm" :disabled="working" @click="template">
        {{ t('tournament.roster.template') }}
      </AuraButton>
      <label class="roster-file">
        <span class="material-symbols-outlined" aria-hidden="true">upload_file</span>
        {{ file ? file.name : t('tournament.roster.pickFile') }}
        <input
          type="file"
          accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
          :disabled="working"
          @change="onPick"
        />
      </label>
    </div>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>

    <template v-if="preview">
      <h3 class="roster-subtitle">
        {{ t('tournament.roster.previewTitle', { count: preview.entries.length }) }}
      </h3>
      <div v-if="preview.errors.length" class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>{{ t('tournament.roster.row') }}</th>
              <th>{{ t('tournament.roster.field') }}</th>
              <th>{{ t('tournament.roster.problem') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(e, i) in preview.errors" :key="i" class="roster-error">
              <td>{{ e.row }}</td>
              <td>{{ e.field }}</td>
              <td>{{ e.message }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <ul class="roster-units">
        <li v-for="unit in preview.entries" :key="unit.name">
          <strong>{{ unit.name }}</strong
          >:
          {{ unit.members.map((m) => m.full_name).join(', ') }}
        </li>
      </ul>
      <AuraButton
        :loading="working"
        :disabled="preview.errors.length > 0 || !preview.entries.length"
        @click="confirm"
      >
        {{ t('tournament.roster.confirm') }}
      </AuraButton>
    </template>
  </section>
</template>

<style scoped>
.roster-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  margin: 12px 0;
}

.roster-file {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border: 1px dashed var(--outline);
  border-radius: 8px;
  font-size: 14px;
  cursor: pointer;
}

.roster-file input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
}

.roster-file:focus-within {
  outline: 2px solid var(--primary);
}

.roster-subtitle {
  margin: 16px 0 8px;
  font-size: 15px;
}

.roster-error td {
  color: var(--error-text);
}

.roster-units {
  margin: 12px 0;
  padding-left: 18px;
  font-size: 14px;
}
</style>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { OperatingStatus } from '@/features/court/types/court'
import type { LifecycleCommand } from '../../types/lifecycle'

const props = defineProps<{
  target: 'venue' | 'court'
  status?: OperatingStatus
  /** Owner of the target's organization; staff only get the court maintenance pair. */
  owner: boolean
  /** The court's open pause is a maintenance pause (staff may resume only that). */
  maintenanceOpen?: boolean
  busy?: boolean
}>()

const emit = defineEmits<{ command: [command: LifecycleCommand] }>()

const { t } = useI18n()

type Action = { command: LifecycleCommand; label: string; icon: string }

// Allowed moves: inactive→active, active→paused, paused→active, any but closed→closed.
const actions = computed<Action[]>(() => {
  const s = props.status
  const list: Array<Action | false> = props.owner
    ? [
        s === 'inactive' && {
          command: 'open',
          label: props.target === 'venue' ? 'openVenue' : 'openCourt',
          icon: 'play_arrow',
        },
        s === 'active' && { command: 'pause', label: 'pause', icon: 'pause' },
        s === 'paused' && { command: 'resume', label: 'resume', icon: 'play_arrow' },
        !!s && s !== 'closed' && { command: 'close', label: 'close', icon: 'block' },
      ]
    : props.target === 'court'
      ? [
          s === 'active' && { command: 'pause', label: 'maintenancePause', icon: 'build' },
          s === 'paused' &&
            !!props.maintenanceOpen && {
              command: 'resume',
              label: 'resumeMaintenance',
              icon: 'play_arrow',
            },
        ]
      : []
  return list.filter((action): action is Action => !!action)
})
</script>

<template>
  <div v-if="actions.length" class="lifecycle-actions">
    <button
      v-for="action in actions"
      :key="action.command"
      type="button"
      class="lifecycle-btn"
      :class="`lifecycle-${action.command}`"
      :disabled="busy"
      @click="emit('command', action.command)"
    >
      <span class="material-symbols-outlined lifecycle-icon">{{ action.icon }}</span>
      <span>{{ t(`admin.lifecycle.${action.label}`) }}</span>
    </button>
  </div>
</template>

<style scoped>
.lifecycle-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.lifecycle-btn {
  flex: 1;
  min-height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 0 10px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 11.5px;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.lifecycle-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.lifecycle-open,
.lifecycle-resume {
  border-color: rgba(16, 185, 129, 0.5);
  color: #059669;
}

.lifecycle-pause {
  border-color: rgba(245, 158, 11, 0.5);
  color: #b45309;
}

.lifecycle-close {
  border-color: rgba(239, 68, 68, 0.45);
  color: #dc2626;
}

.lifecycle-icon {
  font-size: 15px;
}
</style>

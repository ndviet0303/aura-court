import { defineComponent, h } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { i18n } from '@/i18n'
import PricingRuleModal from '../components/pricing/PricingRuleModal.vue'

const DatePickerStub = defineComponent({
  props: { modelValue: { type: String, default: '' } },
  emits: ['update:modelValue'],
  setup(props, { emit, attrs }) {
    return () =>
      h('input', {
        ...attrs,
        type: 'date',
        value: props.modelValue,
        onInput: (event: Event) =>
          emit('update:modelValue', (event.target as HTMLInputElement).value),
      })
  },
})

describe('PricingRuleModal', () => {
  it('loads and saves inclusive effective date bounds', async () => {
    const wrapper = mount(PricingRuleModal, {
      props: {
        show: true,
        rule: {
          id: 4,
          type: 'peak',
          price: 180000,
          priority: 3,
          is_active: true,
          version: 2,
          effective_from: '2026-10-01',
          effective_until: '2026-10-31',
        },
        venues: [],
        sportZones: [],
        courts: [],
      },
      global: {
        plugins: [i18n],
        stubs: {
          AuraButton: true,
          DatePicker: DatePickerStub,
          SelectDropdown: true,
          TimePicker: true,
        },
      },
    })

    const from = wrapper.get('[data-testid="price-rule-effective-from"]')
    const until = wrapper.get('[data-testid="price-rule-effective-until"]')
    expect((from.element as HTMLInputElement).value).toBe('2026-10-01')
    expect((until.element as HTMLInputElement).value).toBe('2026-10-31')

    await until.setValue('2026-11-02')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      effective_from: '2026-10-01',
      effective_until: '2026-11-02',
      priority: 3,
    })
  })
})

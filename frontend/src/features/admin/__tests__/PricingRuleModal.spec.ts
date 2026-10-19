import { defineComponent, h } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { i18n } from '@/i18n'
import type { Court, SportType, Venue } from '@/features/court/types/court'
import { SelectDropdown } from '@/shared/components'
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

  it('scopes a rule to a venue sport only once a venue is chosen', async () => {
    const venue = (id: number): Venue => ({
      id,
      name: `Venue ${id}`,
      slug: `venue-${id}`,
      address: null,
      google_maps_url: null,
      is_active: true,
    })
    const court = (id: number, venueId: number, sport_type: SportType): Court => ({
      id,
      name: `Court ${id}`,
      venue_id: venueId,
      venue: venue(venueId),
      sport_type,
      slot_duration: 60,
    })
    const wrapper = mount(PricingRuleModal, {
      props: {
        show: true,
        rule: null,
        venues: [venue(1), venue(2)],
        courts: [
          court(11, 1, 'badminton'),
          court(12, 1, 'pickleball'),
          court(13, 1, 'pickleball'),
          court(21, 2, 'tennis'),
        ],
      },
      global: {
        plugins: [i18n],
        stubs: { AuraButton: true, DatePicker: DatePickerStub, TimePicker: true },
      },
    })
    // Dropdown order: rule type, venue, sport, court.
    const dropdowns = () => wrapper.findAllComponents(SelectDropdown)
    const optionValues = (index: number) =>
      (dropdowns()[index]!.props('options') as { value: string | number }[]).map((o) => o.value)

    expect(dropdowns()[2]!.props('disabled')).toBe(true)

    dropdowns()[1]!.vm.$emit('update:modelValue', 1)
    await flushPromises()
    expect(dropdowns()[2]!.props('disabled')).toBe(false)
    expect(optionValues(2)).toEqual(['', 'badminton', 'pickleball'])

    dropdowns()[2]!.vm.$emit('update:modelValue', 'pickleball')
    await flushPromises()
    expect(optionValues(3)).toEqual(['', 12, 13])

    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      venue_id: 1,
      sport_type: 'pickleball',
      court_id: null,
    })

    // Clearing the venue clears its sport and locks the select again.
    dropdowns()[1]!.vm.$emit('update:modelValue', '')
    await flushPromises()
    expect(dropdowns()[2]!.props('disabled')).toBe(true)
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')?.[1]?.[0]).toMatchObject({ venue_id: null, sport_type: null })
  })
})

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import DatePicker from '../forms/DatePicker.vue'
import { i18n } from '@/i18n'

describe('DatePicker', () => {
  it('renders a themed calendar and emits the selected date', async () => {
    const wrapper = mount(DatePicker, {
      props: { modelValue: '2026-08-03' },
      global: { plugins: [i18n] },
    })

    expect(wrapper.get('.date-picker-trigger').text()).toContain('03/08/2026')
    await wrapper.get('.date-picker-trigger').trigger('click')
    expect(wrapper.text()).toContain('Tháng 8 năm 2026')

    const day = wrapper.findAll('.date-picker-day').find(button => button.text() === '4')
    await day?.trigger('click')
    const updates = wrapper.emitted('update:modelValue') || []
    expect(updates[updates.length - 1]).toEqual(['2026-08-04'])
  })
})

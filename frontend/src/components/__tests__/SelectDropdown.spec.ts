import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import SelectDropdown from '../SelectDropdown.vue'

describe('SelectDropdown', () => {
  const sampleOptions = [
    { value: 'all', label: 'Tất cả khung giờ' },
    { value: 'morning', label: 'Buổi sáng · 06:00–17:00' },
    { value: 'evening', label: 'Buổi tối · 17:00–23:00' },
  ]

  it('renders current selected option label and opens popover menu on click', async () => {
    const wrapper = mount(SelectDropdown, {
      props: {
        modelValue: 'all',
        options: sampleOptions,
      },
    })

    expect(wrapper.get('.custom-dropdown-trigger').text()).toContain('Tất cả khung giờ')
    expect(wrapper.find('.custom-dropdown-menu').exists()).toBe(false)

    await wrapper.get('.custom-dropdown-trigger').trigger('click')
    expect(wrapper.find('.custom-dropdown-menu').exists()).toBe(true)

    const items = wrapper.findAll('.dropdown-item')
    expect(items.length).toBe(3)
    expect(items[0]!.classes()).toContain('selected')

    await items[1]!.trigger('click')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['morning'])
    expect(wrapper.emitted('change')?.[0]).toEqual(['morning'])
  })

  it('handles primitive string options correctly', async () => {
    const wrapper = mount(SelectDropdown, {
      props: {
        modelValue: 'Option 2',
        options: ['Option 1', 'Option 2', 'Option 3'],
      },
    })

    expect(wrapper.get('.custom-dropdown-trigger').text()).toContain('Option 2')
  })

  it('renders placeholder when no option is selected', () => {
    const wrapper = mount(SelectDropdown, {
      props: {
        modelValue: null,
        options: sampleOptions,
        placeholder: 'Chọn khung giờ',
      },
    })

    expect(wrapper.get('.custom-dropdown-trigger').text()).toContain('Chọn khung giờ')
  })
})

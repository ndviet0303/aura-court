import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CourtSpecsGrid from '../components/detail/CourtSpecsGrid.vue'
import { i18n } from '@/i18n'

describe('CourtSpecsGrid', () => {
  it('describes the playing surface of the court sport', () => {
    const surfaceOf = (sportType: string) =>
      mount(CourtSpecsGrid, { props: { sportType }, global: { plugins: [i18n] } })
        .find('.spec-card')
        .text()

    expect(surfaceOf('football_7v7')).toContain(i18n.global.t('courtDetail.surfaceFootball'))
    expect(surfaceOf('badminton')).toContain(i18n.global.t('courtDetail.specMat'))
  })
})

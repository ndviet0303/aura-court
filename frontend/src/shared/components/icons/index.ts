export { default as IconBase } from './IconBase.vue'
export { default as IconHome } from './IconHome.vue'
export { default as IconCalendar } from './IconCalendar.vue'
export { default as IconSearch } from './IconSearch.vue'
export { default as IconUser } from './IconUser.vue'
export { default as IconWallet } from './IconWallet.vue'
export { default as IconFilter } from './IconFilter.vue'
export { default as IconCheck } from './IconCheck.vue'
export { default as IconClose } from './IconClose.vue'
export { default as IconArrowLeft } from './IconArrowLeft.vue'
export { default as IconArrowRight } from './IconArrowRight.vue'
export { default as IconLocation } from './IconLocation.vue'

export interface IconProvenance {
  name: string
  nodeId: string
  sourceFile: string
  description: string
}

export const iconProvenanceMap: Record<string, IconProvenance> = {
  home: {
    name: 'IconHome',
    nodeId: '2128:42766',
    sourceFile: 'icons/iconly/regular/bold/home--2128-42766.svg',
    description: 'Home navigation icon',
  },
  calendar: {
    name: 'IconCalendar',
    nodeId: '2128:42731',
    sourceFile: 'icons/iconly/regular/bold/calendar--2128-42731.svg',
    description: 'Calendar and date selector icon',
  },
  search: {
    name: 'IconSearch',
    nodeId: '2128:42669',
    sourceFile: 'icons/iconly/regular/bold/search--2128-42669.svg',
    description: 'Court and booking search icon',
  },
  user: {
    name: 'IconUser',
    nodeId: '2128:42701',
    sourceFile: 'icons/iconly/regular/bold/profile--2128-42701.svg',
    description: 'User profile and account icon',
  },
  wallet: {
    name: 'IconWallet',
    nodeId: '2128:42670',
    sourceFile: 'icons/iconly/regular/bold/wallet--2128-42670.svg',
    description: 'Wallet and balances icon',
  },
  filter: {
    name: 'IconFilter',
    nodeId: '2128:42756',
    sourceFile: 'icons/iconly/regular/bold/filter--2128-42756.svg',
    description: 'Court and schedule filter icon',
  },
  check: {
    name: 'IconCheck',
    nodeId: '1129:18851',
    sourceFile: 'elements/additional-icons/icon-check-component-additional-icons--1129-18851.svg',
    description: 'Selection and confirmation cue',
  },
  close: {
    name: 'IconClose',
    nodeId: '1129:18822',
    sourceFile: 'elements/additional-icons/icon-times-component-additional-icons--1129-18822.svg',
    description: 'Modal and drawer dismiss icon',
  },
  arrowLeft: {
    name: 'IconArrowLeft',
    nodeId: '1129:18852',
    sourceFile: 'elements/additional-icons/icon-angle-left-component-additional-icons--1129-18852.svg',
    description: 'Back navigation and prior step icon',
  },
  arrowRight: {
    name: 'IconArrowRight',
    nodeId: '1129:18846',
    sourceFile: 'elements/additional-icons/icon-angle-right-component-additional-icons--1129-18846.svg',
    description: 'Forward navigation and next step icon',
  },
  location: {
    name: 'IconLocation',
    nodeId: '1129:18856',
    sourceFile: 'elements/additional-icons/icon-pin-component-additional-icons--1129-18856.svg',
    description: 'Venue directions and court location icon',
  },
}

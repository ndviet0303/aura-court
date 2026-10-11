import { ref, computed } from 'vue'

export interface PaginationOptions {
  initialPage?: number
  pageSize?: number
  totalItems?: number
}

export function usePagination(options: PaginationOptions = {}) {
  const currentPage = ref(options.initialPage ?? 1)
  const pageSize = ref(options.pageSize ?? 10)
  const totalItems = ref(options.totalItems ?? 0)

  const totalPages = computed(() => Math.max(1, Math.ceil(totalItems.value / pageSize.value)))

  const hasNextPage = computed(() => currentPage.value < totalPages.value)
  const hasPrevPage = computed(() => currentPage.value > 1)

  function nextPage() {
    if (hasNextPage.value) {
      currentPage.value++
    }
  }

  function prevPage() {
    if (hasPrevPage.value) {
      currentPage.value--
    }
  }

  function setPage(page: number) {
    if (page >= 1 && page <= totalPages.value) {
      currentPage.value = page
    }
  }

  function setTotal(total: number) {
    totalItems.value = total
  }

  function reset() {
    currentPage.value = options.initialPage ?? 1
    totalItems.value = 0
  }

  return {
    currentPage,
    pageSize,
    totalItems,
    totalPages,
    hasNextPage,
    hasPrevPage,
    nextPage,
    prevPage,
    setPage,
    setTotal,
    reset,
  }
}

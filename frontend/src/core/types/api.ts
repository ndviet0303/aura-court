export type ApiError = {
  message: string
  errors?: Record<string, string[]>
  status?: number
  code?: string | number
}

export type QueryParams = Record<string, string | number | boolean | undefined | null>

export interface ApiRequestOptions extends Omit<RequestInit, 'body'> {
  params?: QueryParams
  idempotencyKey?: string
}

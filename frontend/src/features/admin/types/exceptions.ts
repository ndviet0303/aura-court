export type RiskLevel = 'critical' | 'medium' | 'low'
export type ExceptionStatus = 'pending' | 'resolved' | 'investigating'
export type AuditLogType = 'override' | 'flag' | 'block' | 'unlock'

export interface ExceptionUser {
  name: string
  id: string
  avatar?: string
  tier?: string
}

export interface FinancialException {
  id: string
  code: string
  timestamp: string
  shift: string
  user: ExceptionUser
  amount: number
  reason: string
  riskLevel: RiskLevel
  status: ExceptionStatus
}

export interface AuditLog {
  id: string
  author: string
  role: string
  time: string
  action: string
  details: string
  hash: string
  type: AuditLogType
}

export interface ExceptionStats {
  criticalCount: number
  pendingCount: number
  resolvedCount: number
  totalRiskValue: number
}

export type ExceptionFilterTab = 'all' | 'critical' | 'refund' | 'deposit' | 'deposit_hold'

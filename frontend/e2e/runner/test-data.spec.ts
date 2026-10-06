import assert from 'node:assert/strict'
import childProcess from 'node:child_process'
import { syncBuiltinESMExports } from 'node:module'
import { after, before, mock, test } from 'node:test'
import { getDiscoveryManifest, sendSyntheticPaymentWebhook } from './test-data.ts'
import type { SyntheticWebhookOptions } from './test-data.ts'
import type { RunManifest } from './types.ts'

const originalExec = childProcess.execFileSync
let phpCalls = 0
let capturedPhp = ''
before(() => {
  mock.method(childProcess, 'execFileSync', (_command: string, args: string[]) => {
    phpCalls++
    capturedPhp = args[1] || ''
    throw new Error('Credential access is disabled in this unit test')
  })
  syncBuiltinESMExports()
})
after(() => {
  mock.restoreAll()
  syncBuiltinESMExports()
})

function fixture(): { manifest: RunManifest; options: SyntheticWebhookOptions } {
  const manifest = getDiscoveryManifest()
  manifest.runId = 'prod_e2e_1791180000000_c0decafe'
  manifest.backend.url = 'https://court-api.ziet.dev'
  manifest.fixtures!.run_id = manifest.runId
  manifest.fixtures!.organizations.org1.id = 73
  manifest.fixtures!.organizations.org2.id = 74
  manifest.fixtures!.users.cashier.email = `cashier_${manifest.runId}@auracourt.test`
  return { manifest, options: {
    routingId: 'synthetic', secret: 'synthetic', accountNumber: 'TEST',
    amount: 220000, code: 'POS-6-NEWCHECKOUT', posOrderId: 123,
  } }
}

const unsafeScopes: Array<[string, (m: RunManifest, o: SyntheticWebhookOptions) => void]> = [
  ['foreign callback origin', m => { m.backend.url = 'https://other.example' }],
  ['business tenant', m => { m.fixtures!.organizations.org1.id = 75 }],
  ['missing checkout ID', (_m, o) => { delete o.posOrderId }],
  ['old fixture namespace', m => { m.fixtures!.run_id = 'old-run' }],
  ['non-generated cashier', m => { m.fixtures!.users.cashier.email = 'cashier@example.com' }],
  ['invalid creation time', m => { m.createdAt = 'invalid' }],
  ['invalid amount', (_m, o) => { o.amount = 0 }],
  ['invalid POS reference', (_m, o) => { o.code = 'POS-invalid' }],
]
for (const [name, alter] of unsafeScopes) {
  test(`production POS rejects ${name} before credential access`, async () => {
    const { manifest, options } = fixture()
    alter(manifest, options)
    const callsBefore = phpCalls
    await assert.rejects(sendSyntheticPaymentWebhook(manifest, options), /Production POS signing requires/)
    assert.equal(phpCalls, callsBefore)
  })
}

test('guarded POS PHP has valid syntax without executing or reading credentials', async () => {
  const { manifest, options } = fixture()
  await assert.rejects(sendSyntheticPaymentWebhook(manifest, options), /Credential access is disabled/)
  const output = originalExec('php', ['-l'], {
    input: `<?php\n${capturedPhp}\n`, encoding: 'utf8',
  })
  assert.match(output, /No syntax errors detected/)
})

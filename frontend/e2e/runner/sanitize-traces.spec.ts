import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import os from 'node:os'
import { execFileSync } from 'node:child_process'
import { sanitizeText, sanitizeZipArchive, sanitizeDirectory } from './sanitize-traces.ts'

test('sanitizeText scrubs credentials, tokens, cookies, and webhook secrets', () => {
  const dirty = JSON.stringify({
    password: 'Password123!@#run_xyz',
    authorization: 'Bearer secret_token_1234567890abcdef',
    cookie: 'aura_session=sess_12345; XSRF-TOKEN=xsrf_67890',
    secret: 'whsec_e2e_1a_run123',
    sepay_secret: 'test_sepay_hmac_secret_e2e',
  })

  const cleaned = sanitizeText(dirty)

  assert.ok(!cleaned.includes('Password123!@#run_xyz'), 'Password must be redacted')
  assert.ok(!cleaned.includes('secret_token_1234567890abcdef'), 'Bearer token must be redacted')
  assert.ok(!cleaned.includes('sess_12345'), 'Session cookie must be redacted')
  assert.ok(!cleaned.includes('whsec_e2e_1a_run123'), 'Webhook secret must be redacted')
  assert.ok(!cleaned.includes('test_sepay_hmac_secret_e2e'), 'Sepay secret must be redacted')
  assert.ok(cleaned.includes('[REDACTED]'), 'Must include redaction placeholder')
})

test('sanitizeZipArchive scrubs secrets inside zip entries', () => {
  const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'zip-sanitize-test-'))
  const testZip = path.join(tempDir, 'test-trace.zip')
  const entryDir = path.join(tempDir, 'entries')
  fs.mkdirSync(entryDir)

  const traceNetworkContent = JSON.stringify({
    request: {
      url: 'http://127.0.0.1:8000/api/v1/auth/login',
      headers: {
        authorization: 'Bearer jwt_secret_token_123456789',
        cookie: 'aura_session=cookie_val_99999',
      },
      postData: '{"email":"owner@auracourt.vn","password":"Password123!@#xyz"}',
    },
    response: {
      headers: {
        'set-cookie': 'aura_session=cookie_val_99999; path=/',
      },
    },
  })

  fs.writeFileSync(path.join(entryDir, 'trace.network'), traceNetworkContent, 'utf-8')
  execFileSync('python3', [
    '-c',
    `import zipfile, sys; z = zipfile.ZipFile(sys.argv[1], 'w'); z.write(sys.argv[2], 'trace.network'); z.close()`,
    testZip,
    path.join(entryDir, 'trace.network'),
  ])

  const sanitized = sanitizeZipArchive(testZip)
  assert.ok(sanitized, 'sanitizeZipArchive must succeed')

  // Read back zip content
  const readBack = execFileSync('python3', [
    '-c',
    `import zipfile, sys; z = zipfile.ZipFile(sys.argv[1], 'r'); print(z.read('trace.network').decode('utf-8'))`,
    testZip,
  ], { encoding: 'utf-8' })

  assert.ok(!readBack.includes('Password123!@#xyz'), 'Zip trace must not contain raw password')
  assert.ok(!readBack.includes('jwt_secret_token_123456789'), 'Zip trace must not contain raw jwt token')
  assert.ok(!readBack.includes('cookie_val_99999'), 'Zip trace must not contain raw session cookie')
  assert.ok(readBack.includes('[REDACTED'), 'Zip trace must contain redaction placeholders')
  fs.rmSync(tempDir, { recursive: true, force: true })
})

test('sanitizeDirectory cleans reports and trace files in folder structure', () => {
  const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'dir-sanitize-test-'))
  const reportPath = path.join(tempDir, 'report.html')
  fs.writeFileSync(reportPath, '<html><body>Token: Bearer secret_access_token_12345</body></html>', 'utf-8')

  const res = sanitizeDirectory(tempDir)
  assert.equal(res.filesScanned, 1)
  assert.equal(res.filesSanitized, 1)

  const content = fs.readFileSync(reportPath, 'utf-8')
  assert.ok(!content.includes('secret_access_token_12345'))
  assert.ok(content.includes('[REDACTED_TOKEN]'))

  fs.rmSync(tempDir, { recursive: true, force: true })
})

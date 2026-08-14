import assert from 'node:assert/strict'
import test from 'node:test'

import {
  formatDui,
  formatNit,
  formatPhone,
  hasValidDuiCheckDigit,
  hasValidNitCheckDigit,
  isValidSalvadoranPhone,
} from './salvadoranValidation.js'

test('formatea documentos y telefonos usando su representacion canonica', () => {
  assert.equal(formatDui('123456784'), '12345678-4')
  assert.equal(formatNit('06142906951010'), '0614-290695-101-0')
  assert.equal(formatPhone('23456789'), '2345-6789')
})

test('valida el digito verificador y rechaza documentos repetidos', () => {
  assert.equal(hasValidDuiCheckDigit('12345678-4'), true)
  assert.equal(hasValidDuiCheckDigit('88888888-6'), false)
  assert.equal(hasValidDuiCheckDigit('88888888-8'), false)
  assert.equal(hasValidNitCheckDigit('0614-290695-101-0'), true)
  assert.equal(hasValidNitCheckDigit('0614-290695-101-3'), false)
  assert.equal(hasValidNitCheckDigit('0000-000000-000-0'), false)
})

test('acepta solo telefonos locales validos y no repetidos', () => {
  for (const phone of ['2345-6789', '6123-4567', '7123-4567']) {
    assert.equal(isValidSalvadoranPhone(phone), true)
  }

  for (const phone of ['5123-4567', '+503 2345-6789', '7777-7777']) {
    assert.equal(isValidSalvadoranPhone(phone), false)
  }
})

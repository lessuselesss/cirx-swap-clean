/**
 * Test file to verify consolidated utilities work correctly
 * Run with: npm test or vitest run utils/__tests__/utilities.test.js
 */

import { describe, it, expect } from 'vitest'
import { useFormattedNumbers } from '../../composables/useFormattedNumbers.js'

const { formatAddress, isValidEthereumAddress, isValidSolanaAddress } = useFormattedNumbers()
import { validateTokenAmount, validateWalletAddress } from '../validation'
import { formatTokenAmount, formatCurrency, formatPercentage } from '../formatting'
import { WALLET_METADATA, getWalletMetadata, isWalletSupported } from '../config/walletConfig'

describe('Address Formatting', () => {
  it('should format Ethereum addresses correctly', () => {
    const address = '0x1234567890123456789012345678901234567890'
    expect(formatAddress(address)).toBe('0x1234...7890')
    expect(formatAddress(address, { startChars: 8, endChars: 6 })).toBe('0x123456...567890')
  })

  it('should validate Ethereum addresses', () => {
    expect(isValidEthereumAddress('0x1234567890123456789012345678901234567890')).toBe(true)
    expect(isValidEthereumAddress('invalid-address')).toBe(false)
    expect(isValidEthereumAddress('')).toBe(false)
  })

  it('should validate Solana addresses', () => {
    expect(isValidSolanaAddress('11111111111111111111111111111112')).toBe(true)
    expect(isValidSolanaAddress('invalid-address')).toBe(false)
  })
})

describe('Validation', () => {
  it('should validate token amounts correctly', () => {
    expect(validateTokenAmount('100').isValid).toBe(true)
    expect(validateTokenAmount('0').isValid).toBe(true)
    expect(validateTokenAmount('-10').isValid).toBe(false)
    expect(validateTokenAmount('abc').isValid).toBe(false)
    expect(validateTokenAmount('').isValid).toBe(false)
  })

  it('should validate wallet addresses', () => {
    const ethResult = validateWalletAddress('0x1234567890123456789012345678901234567890')
    expect(ethResult.isValid).toBe(true)
    expect(ethResult.detectedType).toBe('ethereum')

    const invalidResult = validateWalletAddress('invalid')
    expect(invalidResult.isValid).toBe(false)
  })
})

describe('Formatting', () => {
  it('should format token amounts correctly', () => {
    expect(formatTokenAmount(1000.123456)).toBe('1,000.123456')
    expect(formatTokenAmount(0.000001)).toBe('0.00000100')
    expect(formatTokenAmount(1000000, { compact: true })).toBe('1M')
  })

  it('should format currency correctly', () => {
    expect(formatCurrency(1234.56)).toBe('$1,234.56')
  })

  it('should format percentages correctly', () => {
    expect(formatPercentage(0.1523)).toBe('15.23%')
  })
})

describe('Wallet Configuration', () => {
  it('should provide wallet metadata', () => {
    expect(getWalletMetadata('metamask')).toBeDefined()
    expect(getWalletMetadata('metamask').name).toBe('MetaMask')
    expect(getWalletMetadata('nonexistent')).toBe(null)
  })

  it('should check wallet support', () => {
    expect(isWalletSupported('metamask')).toBe(true)
    expect(isWalletSupported('nonexistent')).toBe(false)
  })
})
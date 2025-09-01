/**
 * Unit tests for useFormattingUtils
 * Testing number, currency, token, and percentage formatting
 */
import { describe, it, expect } from 'vitest'
import { useFormattingUtils } from '../core/useFormattingUtils.js'

const { 
  formatNumber,
  formatCurrency,
  formatTokenAmount,
  formatPercentage,
  formatFileSize,
  formatDuration,
  formatTimeAgo,
  formatAddress,
  formatTransactionHash
} = useFormattingUtils()

describe('useFormattingUtils', () => {
  describe('formatNumber', () => {
    it('should format basic numbers', () => {
      expect(formatNumber(1234.56)).toBe('1,234.56')
      expect(formatNumber(1000)).toBe('1,000.00')
    })

    it('should handle different decimal places', () => {
      expect(formatNumber(3.14159, { decimals: 4 })).toBe('3.1416')
      expect(formatNumber(100, { decimals: 0 })).toBe('100')
    })

    it('should handle compact notation', () => {
      expect(formatNumber(1500000, { compact: true })).toBe('1.5M')
      expect(formatNumber(2500, { compact: true })).toBe('2.5K')
    })

    it('should handle invalid inputs', () => {
      expect(formatNumber(null)).toBe('0')
      expect(formatNumber('')).toBe('0')
      expect(formatNumber('abc')).toBe('0')
      expect(formatNumber(NaN)).toBe('0')
    })

    it('should use custom fallback', () => {
      expect(formatNumber('invalid', { fallback: 'N/A' })).toBe('N/A')
    })
  })

  describe('formatCurrency', () => {
    it('should format USD currency by default', () => {
      expect(formatCurrency(1234.56)).toBe('$1,234.56')
      expect(formatCurrency(0)).toBe('$0.00')
    })

    it('should handle different currencies', () => {
      expect(formatCurrency(100, { currency: 'EUR' })).toContain('100')
    })

    it('should handle compact currency', () => {
      expect(formatCurrency(1500000, { compact: true })).toBe('$1.5M')
    })
  })

  describe('formatTokenAmount', () => {
    it('should format token amounts with symbols', () => {
      expect(formatTokenAmount(1.234567, { symbol: 'ETH', decimals: 4 })).toBe('1.2346 ETH')
      expect(formatTokenAmount(0, { symbol: 'BTC' })).toBe('0 BTC')
    })

    it('should handle very small amounts', () => {
      expect(formatTokenAmount(0.000001)).toBe('1.00e-6')
    })

    it('should handle large amounts with compact notation', () => {
      expect(formatTokenAmount(1500000, { compact: true, symbol: 'USDC' })).toBe('1.5M USDC')
    })

    it('should handle amounts less than 1', () => {
      expect(formatTokenAmount(0.123456, { decimals: 4 })).toContain('0.123456')
    })

    it('should show full precision when requested', () => {
      expect(formatTokenAmount(0.123456789, { showFullPrecision: true })).toBe('0.123456789')
    })
  })

  describe('formatPercentage', () => {
    it('should format percentages correctly', () => {
      expect(formatPercentage(0.1)).toBe('10%')
      expect(formatPercentage(0.1234, { decimals: 3 })).toBe('12.34%')
    })

    it('should handle already multiplied values', () => {
      expect(formatPercentage(15, { multiply100: false })).toBe('15%')
    })

    it('should handle invalid inputs', () => {
      expect(formatPercentage(null)).toBe('0%')
      expect(formatPercentage('')).toBe('0%')
      expect(formatPercentage('abc')).toBe('0%')
    })

    it('should show signs when requested', () => {
      expect(formatPercentage(0.05, { showSign: true })).toContain('+')
    })
  })

  describe('formatFileSize', () => {
    it('should format file sizes correctly', () => {
      expect(formatFileSize(0)).toBe('0 B')
      expect(formatFileSize(1024)).toBe('1.0 KB')
      expect(formatFileSize(1048576)).toBe('1.0 MB')
    })

    it('should handle binary units', () => {
      expect(formatFileSize(1024, { binary: true })).toBe('1.0 KiB')
    })

    it('should handle different decimal places', () => {
      expect(formatFileSize(1536, { decimals: 2 })).toBe('1.54 KB')
    })
  })

  describe('formatDuration', () => {
    it('should format durations correctly', () => {
      expect(formatDuration(0)).toBe('0s')
      expect(formatDuration(90)).toBe('1 minutes 30 seconds')
      expect(formatDuration(3661)).toBe('1 hours 1 minutes 1 second')
    })

    it('should handle short format', () => {
      expect(formatDuration(90, { format: 'short' })).toBe('1m 30s')
    })

    it('should handle compact format', () => {
      expect(formatDuration(3661, { format: 'compact' })).toBe('1h 1m 1s')
    })

    it('should handle negative durations', () => {
      expect(formatDuration(-100)).toBe('0s')
    })
  })

  describe('formatTimeAgo', () => {
    it('should format recent times', () => {
      const now = new Date()
      const recent = new Date(now.getTime() - 30 * 1000)
      expect(formatTimeAgo(recent)).toBe('just now')
    })

    it('should format minutes ago', () => {
      const now = new Date()
      const past = new Date(now.getTime() - 5 * 60 * 1000)
      expect(formatTimeAgo(past)).toBe('5m ago')
    })

    it('should format hours ago', () => {
      const now = new Date()
      const past = new Date(now.getTime() - 2 * 60 * 60 * 1000)
      expect(formatTimeAgo(past)).toBe('2h ago')
    })
  })

  describe('formatAddress', () => {
    it('should truncate long addresses', () => {
      const address = '0x1234567890abcdef1234567890abcdef12345678'
      expect(formatAddress(address)).toBe('0x1234...5678')
    })

    it('should handle custom lengths', () => {
      const address = '0x1234567890abcdef1234567890abcdef12345678'
      expect(formatAddress(address, { prefixLength: 8, suffixLength: 6 })).toBe('0x123456...345678')
    })

    it('should not truncate short addresses', () => {
      const shortAddress = '0x123456'
      expect(formatAddress(shortAddress)).toBe('0x123456')
    })

    it('should handle invalid addresses', () => {
      expect(formatAddress(null)).toBe('')
      expect(formatAddress('')).toBe('')
    })
  })

  describe('formatTransactionHash', () => {
    it('should format transaction hashes with default lengths', () => {
      const hash = '0xabcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890'
      expect(formatTransactionHash(hash)).toBe('0xabcdef...567890')
    })

    it('should handle custom options', () => {
      const hash = '0xabcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890'
      expect(formatTransactionHash(hash, { prefixLength: 10, suffixLength: 8 })).toBe('0xabcdef12...34567890')
    })
  })
})
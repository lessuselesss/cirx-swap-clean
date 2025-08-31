/**
 * Unit tests for useMathUtils
 * Testing edge cases and safe arithmetic operations
 */
import { describe, it, expect } from 'vitest'
import { useMathUtils } from '../useMathUtils.js'

const { 
  safeDiv, 
  safeMul, 
  safeAdd, 
  safeSub,
  safePercentage, 
  validateNumber,
  calculatePercentage,
  applyPercentage,
  safeRound,
  clamp
} = useMathUtils()

describe('useMathUtils', () => {
  describe('safeDiv', () => {
    it('should perform normal division', () => {
      expect(safeDiv(10, 2)).toBe(5)
      expect(safeDiv(7, 3)).toBeCloseTo(2.333, 3)
    })

    it('should handle division by zero', () => {
      expect(safeDiv(10, 0)).toBe(0)
      expect(safeDiv(10, 0, 999)).toBe(999)
    })

    it('should handle invalid inputs', () => {
      expect(safeDiv(NaN, 2)).toBe(0)
      expect(safeDiv(10, NaN)).toBe(0)
      expect(safeDiv('abc', 2)).toBe(0)
      expect(safeDiv(10, 'xyz')).toBe(0)
    })

    it('should handle Infinity results', () => {
      expect(safeDiv(Number.MAX_VALUE, 1e-100, -1)).toBe(-1)
    })
  })

  describe('safeMul', () => {
    it('should perform normal multiplication', () => {
      expect(safeMul(5, 3)).toBe(15)
      expect(safeMul(2.5, 4)).toBe(10)
    })

    it('should handle invalid inputs', () => {
      expect(safeMul(NaN, 2)).toBe(0)
      expect(safeMul(10, NaN)).toBe(0)
      expect(safeMul('abc', 2)).toBe(0)
    })

    it('should handle very large numbers', () => {
      expect(safeMul(Number.MAX_VALUE, 2, -1)).toBe(-1)
    })
  })

  describe('safePercentage', () => {
    it('should convert valid numbers', () => {
      expect(safePercentage(50)).toBe(50)
      expect(safePercentage('25.5')).toBe(25.5)
    })

    it('should handle invalid inputs', () => {
      expect(safePercentage('abc')).toBe(0)
      expect(safePercentage(NaN)).toBe(0)
      expect(safePercentage(Infinity)).toBe(0)
    })

    it('should use custom default value', () => {
      expect(safePercentage('invalid', 100)).toBe(100)
    })
  })

  describe('validateNumber', () => {
    it('should validate positive numbers', () => {
      expect(validateNumber(42)).toBe(42)
      expect(validateNumber('123.45')).toBe(123.45)
    })

    it('should reject negative numbers', () => {
      expect(validateNumber(-10)).toBe(null)
    })

    it('should reject invalid values', () => {
      expect(validateNumber('abc')).toBe(null)
      expect(validateNumber(NaN)).toBe(null)
      expect(validateNumber(Infinity)).toBe(null)
    })
  })

  describe('calculatePercentage', () => {
    it('should calculate percentage correctly', () => {
      expect(calculatePercentage(25, 100)).toBe(25)
      expect(calculatePercentage(1, 4)).toBe(25)
    })

    it('should handle division by zero', () => {
      expect(calculatePercentage(10, 0)).toBe(0)
      expect(calculatePercentage(10, 0, 100)).toBe(100)
    })
  })

  describe('applyPercentage', () => {
    it('should apply percentage correctly', () => {
      expect(applyPercentage(100, 10)).toBe(10)
      expect(applyPercentage(200, 25)).toBe(50)
    })

    it('should handle edge cases', () => {
      expect(applyPercentage(100, 0)).toBe(0)
      expect(applyPercentage(0, 50)).toBe(0)
    })
  })

  describe('safeRound', () => {
    it('should round to specified decimals', () => {
      expect(safeRound(3.14159, 2)).toBe(3.14)
      expect(safeRound(3.14159, 4)).toBe(3.1416)
    })

    it('should handle invalid inputs', () => {
      expect(safeRound('abc', 2)).toBe(0)
      expect(safeRound(NaN, 2)).toBe(0)
    })
  })

  describe('clamp', () => {
    it('should clamp values within range', () => {
      expect(clamp(5, 0, 10)).toBe(5)
      expect(clamp(-5, 0, 10)).toBe(0)
      expect(clamp(15, 0, 10)).toBe(10)
    })

    it('should handle invalid inputs', () => {
      expect(clamp('abc', 0, 10)).toBe(0)
    })
  })
})
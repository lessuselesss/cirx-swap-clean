/**
 * Mathematical Utility Functions
 * Consolidated from multiple composables to eliminate redundancy
 * Provides safe arithmetic operations with proper error handling
 */
import { ref, computed } from 'vue'

export function useMathUtils() {
  
  /**
   * Generic safe operation handler
   * @param {string} operation - The operation type ('div', 'mul', 'add', 'sub')
   * @param {number} a - First operand
   * @param {number} b - Second operand  
   * @param {number} fallback - Fallback value for invalid operations
   * @returns {number} Result or fallback value
   */
  const safeOperation = (operation, a, b, fallback = 0) => {
    // Validate inputs are numbers
    if (typeof a !== 'number' || typeof b !== 'number' || isNaN(a) || isNaN(b)) {
      return fallback
    }
    
    let result
    switch (operation) {
      case 'div':
        // Handle division by zero
        if (b === 0) return fallback
        result = a / b
        break
      case 'mul':
        result = a * b
        break
      case 'add':
        result = a + b
        break
      case 'sub':
        result = a - b
        break
      case 'mod':
        if (b === 0) return fallback
        result = a % b
        break
      default:
        console.warn(`Unknown operation: ${operation}`)
        return fallback
    }
    
    // Check if result is finite (not Infinity or -Infinity)
    return isFinite(result) ? result : fallback
  }

  /**
   * Safe division with fallback
   * Consolidated from useSwapHandler.js safeDiv (lines 12-18)
   */
  const safeDiv = (a, b, fallback = 0) => {
    return safeOperation('div', a, b, fallback)
  }

  /**
   * Safe multiplication with fallback
   * Consolidated from useSwapHandler.js safeMul (lines 20-26)
   */
  const safeMul = (a, b, fallback = 0) => {
    return safeOperation('mul', a, b, fallback)
  }

  /**
   * Safe addition with fallback
   */
  const safeAdd = (a, b, fallback = 0) => {
    return safeOperation('add', a, b, fallback)
  }

  /**
   * Safe subtraction with fallback
   */
  const safeSub = (a, b, fallback = 0) => {
    return safeOperation('sub', a, b, fallback)
  }

  /**
   * Safe percentage conversion
   * Consolidated from useSwapHandler.js safePercentage (lines 28-31)
   */
  const safePercentage = (value, defaultValue = 0) => {
    const num = parseFloat(value)
    return (isNaN(num) || !isFinite(num)) ? defaultValue : num
  }

  /**
   * Validate and parse number with detailed error reporting
   * Consolidated from useSwapHandler.js validateNumber (lines 33-39)
   */
  const validateNumber = (value, name = 'value') => {
    const num = parseFloat(value)
    if (isNaN(num) || !isFinite(num) || num < 0) {
      console.warn(`Invalid ${name}:`, value)
      return null
    }
    return num
  }

  /**
   * Safe percentage calculation (value as percentage of total)
   */
  const calculatePercentage = (value, total, fallback = 0) => {
    if (total === 0) return fallback
    return safeMul(safeDiv(value, total, 0), 100, fallback)
  }

  /**
   * Apply percentage to a value
   */
  const applyPercentage = (value, percentage, fallback = 0) => {
    return safeMul(value, safeDiv(percentage, 100, 0), fallback)
  }

  /**
   * Round to specified decimal places
   */
  const safeRound = (value, decimals = 2, fallback = 0) => {
    const num = parseFloat(value)
    if (isNaN(num) || !isFinite(num)) return fallback
    
    const factor = Math.pow(10, decimals)
    const rounded = Math.round(safeMul(num, factor, 0)) / factor
    return isFinite(rounded) ? rounded : fallback
  }

  /**
   * Clamp value between min and max
   */
  const clamp = (value, min, max) => {
    const num = parseFloat(value)
    if (isNaN(num)) return min
    return Math.max(min, Math.min(max, num))
  }

  /**
   * Check if a value is a valid number
   */
  const isValidNumber = (value) => {
    const num = parseFloat(value)
    return !isNaN(num) && isFinite(num)
  }

  /**
   * Convert basis points to percentage (1 basis point = 0.01%)
   */
  const basisPointsToPercentage = (basisPoints, fallback = 0) => {
    return safeDiv(basisPoints, 10000, fallback)
  }

  /**
   * Convert percentage to basis points
   */
  const percentageToBasisPoints = (percentage, fallback = 0) => {
    return safeMul(percentage, 10000, fallback)
  }

  // Return all utility functions
  return {
    // Core safe operations
    safeOperation,
    safeDiv,
    safeMul, 
    safeAdd,
    safeSub,
    
    // Validation and conversion
    safePercentage,
    validateNumber,
    isValidNumber,
    
    // Percentage calculations
    calculatePercentage,
    applyPercentage,
    basisPointsToPercentage,
    percentageToBasisPoints,
    
    // Utility functions
    safeRound,
    clamp
  }
}

// Named exports for direct import
export const {
  safeDiv,
  safeMul,
  safeAdd,
  safeSub,
  safePercentage,
  validateNumber,
  isValidNumber,
  calculatePercentage,
  applyPercentage,
  safeRound,
  clamp,
  basisPointsToPercentage,
  percentageToBasisPoints
} = useMathUtils()
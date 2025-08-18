/**
 * Validation utilities
 * Consolidates duplicate validation logic across the codebase
 */

import { isValidEthereumAddress, isValidSolanaAddress, isValidCircularAddress } from './addressFormatting.js'

/**
 * Validate token amount
 * @param {string|number} amount - Amount to validate
 * @param {object} options - Validation options
 * @returns {object} Validation result
 */
export function validateTokenAmount(amount, options = {}) {
  const {
    min = 0,
    max = Infinity,
    decimals = 18,
    required = true
  } = options

  const result = {
    isValid: true,
    errors: []
  }

  // Required check
  if (required && (!amount || amount === '')) {
    result.isValid = false
    result.errors.push('Amount is required')
    return result
  }

  if (!amount && !required) {
    return result
  }

  // Convert to number
  const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount

  // Numeric check
  if (isNaN(numAmount)) {
    result.isValid = false
    result.errors.push('Amount must be a valid number')
    return result
  }

  // Positive check
  if (numAmount < 0) {
    result.isValid = false
    result.errors.push('Amount must be positive')
  }

  // Min/Max checks
  if (numAmount < min) {
    result.isValid = false
    result.errors.push(`Amount must be at least ${min}`)
  }

  if (numAmount > max) {
    result.isValid = false
    result.errors.push(`Amount must not exceed ${max}`)
  }

  // Decimal places check
  const decimalsRegex = new RegExp(`^\\d+(\\.\\d{1,${decimals}})?$`)
  if (!decimalsRegex.test(amount.toString())) {
    result.isValid = false
    result.errors.push(`Amount can have at most ${decimals} decimal places`)
  }

  return result
}

/**
 * Validate wallet address based on type
 * @param {string} address - Address to validate
 * @param {string} type - 'ethereum', 'solana', 'circular', or 'auto'
 * @returns {object} Validation result
 */
export function validateWalletAddress(address, type = 'auto') {
  const result = {
    isValid: true,
    errors: [],
    detectedType: null
  }

  if (!address || address.trim() === '') {
    result.isValid = false
    result.errors.push('Address is required')
    return result
  }

  const cleanAddress = address.trim()

  if (type === 'circular' || type === 'auto') {
    if (isValidCircularAddress(cleanAddress)) {
      result.detectedType = 'circular'
      return result
    }
  }

  if (type === 'ethereum' || type === 'auto') {
    if (isValidEthereumAddress(cleanAddress)) {
      result.detectedType = 'ethereum'
      return result
    }
  }

  if (type === 'solana' || type === 'auto') {
    if (isValidSolanaAddress(cleanAddress)) {
      result.detectedType = 'solana'
      return result
    }
  }

  result.isValid = false
  if (type === 'auto') {
    result.errors.push('Invalid wallet address format')
  } else {
    result.errors.push(`Invalid ${type} address format`)
  }

  return result
}

/**
 * Validate email address
 * @param {string} email - Email to validate
 * @param {boolean} required - Is email required
 * @returns {object} Validation result
 */
export function validateEmail(email, required = true) {
  const result = {
    isValid: true,
    errors: []
  }

  if (!email || email.trim() === '') {
    if (required) {
      result.isValid = false
      result.errors.push('Email is required')
    }
    return result
  }

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!emailRegex.test(email.trim())) {
    result.isValid = false
    result.errors.push('Invalid email format')
  }

  return result
}

/**
 * Validate password strength
 * @param {string} password - Password to validate
 * @param {object} options - Validation options
 * @returns {object} Validation result with strength score
 */
export function validatePassword(password, options = {}) {
  const {
    minLength = 8,
    requireUppercase = true,
    requireLowercase = true,
    requireNumbers = true,
    requireSpecialChars = true
  } = options

  const result = {
    isValid: true,
    errors: [],
    strength: 0,
    strengthLabel: 'weak'
  }

  if (!password) {
    result.isValid = false
    result.errors.push('Password is required')
    return result
  }

  // Length check
  if (password.length < minLength) {
    result.isValid = false
    result.errors.push(`Password must be at least ${minLength} characters`)
  } else {
    result.strength += 1
  }

  // Character type checks
  if (requireUppercase && !/[A-Z]/.test(password)) {
    result.isValid = false
    result.errors.push('Password must contain uppercase letters')
  } else if (/[A-Z]/.test(password)) {
    result.strength += 1
  }

  if (requireLowercase && !/[a-z]/.test(password)) {
    result.isValid = false
    result.errors.push('Password must contain lowercase letters')
  } else if (/[a-z]/.test(password)) {
    result.strength += 1
  }

  if (requireNumbers && !/\d/.test(password)) {
    result.isValid = false
    result.errors.push('Password must contain numbers')
  } else if (/\d/.test(password)) {
    result.strength += 1
  }

  if (requireSpecialChars && !/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
    result.isValid = false
    result.errors.push('Password must contain special characters')
  } else if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
    result.strength += 1
  }

  // Additional strength bonuses
  if (password.length >= 12) result.strength += 1
  if (password.length >= 16) result.strength += 1

  // Set strength label
  if (result.strength <= 2) result.strengthLabel = 'weak'
  else if (result.strength <= 4) result.strengthLabel = 'medium'
  else if (result.strength <= 6) result.strengthLabel = 'strong'
  else result.strengthLabel = 'very-strong'

  return result
}

/**
 * Generic form field validator
 * @param {object} formData - Form data to validate
 * @param {object} rules - Validation rules
 * @returns {object} Validation results for all fields
 */
export function validateForm(formData, rules) {
  const results = {}
  let isFormValid = true

  for (const [field, value] of Object.entries(formData)) {
    const fieldRules = rules[field]
    if (!fieldRules) continue

    const fieldResult = {
      isValid: true,
      errors: []
    }

    // Required check
    if (fieldRules.required && (!value || value.toString().trim() === '')) {
      fieldResult.isValid = false
      fieldResult.errors.push(`${field} is required`)
    }

    // Custom validator
    if (fieldRules.validator && typeof fieldRules.validator === 'function') {
      const customResult = fieldRules.validator(value)
      if (!customResult.isValid) {
        fieldResult.isValid = false
        fieldResult.errors.push(...customResult.errors)
      }
    }

    results[field] = fieldResult
    if (!fieldResult.isValid) {
      isFormValid = false
    }
  }

  return {
    isValid: isFormValid,
    fields: results,
    getAllErrors: () => {
      return Object.values(results)
        .flatMap(field => field.errors)
    }
  }
}


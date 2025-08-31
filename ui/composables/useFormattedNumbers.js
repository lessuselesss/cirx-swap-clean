/**
 * Formatted Numbers and Address Validation Composable
 * Provides address formatting, validation utilities, and number formatting
 */

import { computed } from 'vue'

export function useFormattedNumbers() {
  /**
   * Validate if a string is a valid Ethereum address
   * @param {string} address - The address to validate
   * @returns {boolean} - True if valid Ethereum address
   */
  const isValidEthereumAddress = (address) => {
    if (!address || typeof address !== 'string') return false
    // Check if it's a valid hex string starting with 0x and 40 hex characters
    return /^0x[a-fA-F0-9]{40}$/.test(address)
  }

  /**
   * Validate if a string is a valid Solana address
   * @param {string} address - The address to validate
   * @returns {boolean} - True if valid Solana address
   */
  const isValidSolanaAddress = (address) => {
    if (!address || typeof address !== 'string') return false
    // Solana addresses are base58 encoded, 32-44 characters
    return /^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(address)
  }

  /**
   * Validate if a string is a valid Circular Protocol address
   * Circular addresses must be exactly 0x + 64 hex characters (66 total)
   * @param {string} address - The address to validate
   * @returns {boolean} - True if valid Circular address
   */
  const isValidCircularAddress = (address) => {
    if (!address || typeof address !== 'string') return false
    
    const trimmed = address.trim()
    
    // Circular addresses are 0x + exactly 64 hex characters (66 total)
    return (
      trimmed.startsWith('0x') &&
      trimmed.length === 66 &&
      /^0x[a-fA-F0-9]{64}$/.test(trimmed)
    )
  }

  /**
   * Determine the type of address
   * @param {string} address - The address to analyze
   * @returns {string|null} - Address type: 'ethereum', 'solana', 'circular', or null if invalid
   */
  const getAddressType = (address) => {
    if (!address || typeof address !== 'string') return null
    
    // Check for Circular Protocol address first (66 chars: 0x + 64 hex)
    if (isValidCircularAddress(address)) {
      return 'circular'
    }
    
    // Check for Ethereum address (42 chars: 0x + 40 hex)
    if (isValidEthereumAddress(address)) {
      return 'ethereum'
    }
    
    // Check for Solana address
    if (isValidSolanaAddress(address)) {
      return 'solana'
    }
    
    return null
  }

  /**
   * Format address for display (truncate middle)
   * @param {string} address - The address to format
   * @param {number} prefixLength - Number of characters to show at start
   * @param {number} suffixLength - Number of characters to show at end
   * @returns {string} - Formatted address
   */
  const formatAddress = (address, prefixLength = 6, suffixLength = 4) => {
    if (!address || address.length <= prefixLength + suffixLength) {
      return address || ''
    }
    
    return `${address.slice(0, prefixLength)}...${address.slice(-suffixLength)}`
  }

  /**
   * Check if address is valid for a specific chain
   * @param {string} address - The address to validate
   * @param {string} chain - The chain type ('ethereum', 'solana', 'circular')
   * @returns {boolean} - True if valid for the specified chain
   */
  const isValidAddressForChain = (address, chain) => {
    switch (chain?.toLowerCase()) {
      case 'ethereum':
        return isValidEthereumAddress(address)
      case 'solana':
        return isValidSolanaAddress(address)
      case 'circular':
        return isValidCircularAddress(address)
      default:
        return false
    }
  }

  /**
   * Format numbers with proper decimal places and commas
   * @param {number|string} value - The number to format
   * @param {number} decimals - Number of decimal places
   * @returns {string} - Formatted number
   */
  const formatNumber = (value, decimals = 2) => {
    if (value === null || value === undefined || value === '') return '0'
    
    const numValue = typeof value === 'string' ? parseFloat(value) : value
    if (isNaN(numValue)) return '0'
    
    return numValue.toLocaleString('en-US', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals
    })
  }

  /**
   * Format currency values
   * @param {number|string} value - The value to format
   * @param {string} currency - Currency symbol (default: '$')
   * @param {number} decimals - Number of decimal places
   * @returns {string} - Formatted currency
   */
  const formatCurrency = (value, currency = '$', decimals = 2) => {
    const formatted = formatNumber(value, decimals)
    return `${currency}${formatted}`
  }

  /**
   * Format token amounts with proper decimal handling
   * @param {number|string} value - The token amount
   * @param {string} symbol - Token symbol
   * @param {number} decimals - Number of decimal places
   * @returns {string} - Formatted token amount
   */
  const formatTokenAmount = (value, symbol = '', decimals = 6) => {
    const formatted = formatNumber(value, decimals)
    return symbol ? `${formatted} ${symbol}` : formatted
  }

  /**
   * Format percentage values
   * @param {number|string} value - The percentage value
   * @param {number} decimals - Number of decimal places
   * @returns {string} - Formatted percentage
   */
  const formatPercentage = (value, decimals = 2) => {
    const formatted = formatNumber(value, decimals)
    return `${formatted}%`
  }

  return {
    // Address validation functions
    isValidEthereumAddress,
    isValidSolanaAddress,
    isValidCircularAddress,
    getAddressType,
    formatAddress,
    isValidAddressForChain,
    
    // Number formatting functions
    formatNumber,
    formatCurrency,
    formatTokenAmount,
    formatPercentage
  }
}
/**
 * Address formatting utilities for blockchain addresses
 * Consolidates duplicate formatting logic across the codebase
 */

/**
 * Format blockchain addresses for display
 * @param {string} address - Full blockchain address
 * @param {object} options - Formatting options
 * @returns {string} Formatted address
 */
export function formatAddress(address, options = {}) {
  if (!address) return ''
  
  const { 
    startChars = 6, 
    endChars = 4, 
    separator = '...' 
  } = options
  
  if (address.length <= startChars + endChars + separator.length) {
    return address
  }
  
  return `${address.slice(0, startChars)}${separator}${address.slice(-endChars)}`
}

/**
 * Validate Ethereum address format
 * @param {string} address - Address to validate
 * @returns {boolean} Is valid Ethereum address
 */
export function isValidEthereumAddress(address) {
  if (!address) return false
  return /^0x[a-fA-F0-9]{40}$/.test(address)
}

/**
 * Validate Solana address format
 * @param {string} address - Address to validate  
 * @returns {boolean} Is valid Solana address
 */
export function isValidSolanaAddress(address) {
  if (!address) return false
  return /^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(address)
}

/**
 * Get address type based on format
 * @param {string} address - Address to check
 * @returns {string|null} 'ethereum', 'solana', or null
 */
export function getAddressType(address) {
  if (isValidEthereumAddress(address)) return 'ethereum'
  if (isValidSolanaAddress(address)) return 'solana'
  return null
}

/**
 * Format address with blockchain-specific formatting
 * @param {string} address - Address to format
 * @param {object} options - Formatting options
 * @returns {string} Formatted address with type context
 */
export function formatAddressWithType(address, options = {}) {
  const type = getAddressType(address)
  const formatted = formatAddress(address, options)
  
  if (options.showType && type) {
    return `${formatted} (${type})`
  }
  
  return formatted
}
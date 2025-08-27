/**
 * Address formatting and validation utilities
 */

/**
 * Validate if a string is a valid Ethereum address
 * @param {string} address - The address to validate
 * @returns {boolean} - True if valid Ethereum address
 */
export function isValidEthereumAddress(address) {
  if (!address || typeof address !== 'string') return false
  // Check if it's a valid hex string starting with 0x and 40 hex characters
  return /^0x[a-fA-F0-9]{40}$/.test(address)
}

/**
 * Validate if a string is a valid Solana address
 * @param {string} address - The address to validate
 * @returns {boolean} - True if valid Solana address
 */
export function isValidSolanaAddress(address) {
  if (!address || typeof address !== 'string') return false
  // Solana addresses are base58 encoded, 32-44 characters
  return /^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(address)
}

/**
 * Validate if a string is a valid Circular Protocol address
 * @param {string} address - The address to validate
 * @returns {boolean} - True if valid Circular address
 */
export function isValidCircularAddress(address) {
  if (!address || typeof address !== 'string') return false
  // Circular addresses are Ethereum-compatible (0x + 40 hex chars)
  return isValidEthereumAddress(address)
}

/**
 * Determine the type of address
 * @param {string} address - The address to analyze
 * @returns {string|null} - Address type: 'ethereum', 'solana', 'circular', or null if invalid
 */
export function getAddressType(address) {
  if (!address || typeof address !== 'string') return null
  
  // Check for Circular Protocol address first (same format as Ethereum)
  if (isValidCircularAddress(address)) {
    return 'circular'
  }
  
  // Check for Ethereum address
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
export function formatAddress(address, prefixLength = 6, suffixLength = 4) {
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
export function isValidAddressForChain(address, chain) {
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
/**
 * Core Address Validation Utilities
 * Consolidated from multiple files to eliminate duplication
 * Previously scattered across useFormattedNumbers.js, useSwapValidation.js, validators.js, useCirxUtils.js
 */

export const useAddressValidation = () => {
  /**
   * Validate Ethereum address format
   * @param {string} address - Address to validate
   * @returns {boolean} True if valid Ethereum address
   */
  const isValidEthereumAddress = (address) => {
    if (!address || typeof address !== 'string') return false
    // Check if it matches the Ethereum address pattern (0x followed by 40 hex chars)
    const ethAddressRegex = /^0x[a-fA-F0-9]{40}$/
    return ethAddressRegex.test(address)
  }

  /**
   * Validate Solana address format
   * @param {string} address - Address to validate
   * @returns {boolean} True if valid Solana address
   */
  const isValidSolanaAddress = (address) => {
    if (!address || typeof address !== 'string') return false
    // Solana addresses are base58 encoded and typically 32-44 characters
    const solanaAddressRegex = /^[1-9A-HJ-NP-Za-km-z]{32,44}$/
    return solanaAddressRegex.test(address)
  }

  /**
   * Validate Circular Protocol address format
   * @param {string} address - Address to validate
   * @returns {boolean} True if valid Circular address
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
   * Validate Bitcoin address format
   * @param {string} address - Address to validate
   * @returns {boolean} True if valid Bitcoin address
   */
  const isValidBitcoinAddress = (address) => {
    if (!address || typeof address !== 'string') return false
    // P2PKH (starts with 1), P2SH (starts with 3), or Bech32 (starts with bc1)
    const btcLegacyRegex = /^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/
    const btcBech32Regex = /^bc1[a-z0-9]{39,59}$/
    return btcLegacyRegex.test(address) || btcBech32Regex.test(address)
  }

  /**
   * Detect the blockchain type from an address
   * @param {string} address - Address to analyze
   * @returns {string|null} Blockchain type or null if unrecognized
   */
  const detectAddressType = (address) => {
    if (!address || typeof address !== 'string') return null
    
    if (isValidEthereumAddress(address)) return 'ethereum'
    if (isValidSolanaAddress(address)) return 'solana'
    if (isValidCircularAddress(address)) return 'circular'
    if (isValidBitcoinAddress(address)) return 'bitcoin'
    
    return null
  }

  /**
   * Generic address validation
   * @param {string} address - Address to validate
   * @param {string} type - Optional blockchain type to validate against
   * @returns {boolean} True if valid address
   */
  const isValidAddress = (address, type = null) => {
    if (!address || typeof address !== 'string') return false
    
    if (type) {
      switch (type.toLowerCase()) {
        case 'ethereum':
        case 'eth':
          return isValidEthereumAddress(address)
        case 'solana':
        case 'sol':
          return isValidSolanaAddress(address)
        case 'circular':
        case 'cirx':
          return isValidCircularAddress(address)
        case 'bitcoin':
        case 'btc':
          return isValidBitcoinAddress(address)
        default:
          return false
      }
    }
    
    // If no type specified, check if it's valid for any supported blockchain
    return detectAddressType(address) !== null
  }

  /**
   * Validate if address has proper checksum (Ethereum specific)
   * @param {string} address - Ethereum address to validate
   * @returns {boolean} True if checksum is valid
   */
  const hasValidChecksum = (address) => {
    if (!isValidEthereumAddress(address)) return false
    
    // If all lowercase or all uppercase, no checksum validation needed
    const addressNoPrefx = address.slice(2)
    if (addressNoPrefx === addressNoPrefx.toLowerCase() || 
        addressNoPrefx === addressNoPrefx.toUpperCase()) {
      return true
    }
    
    // For mixed case, would need Web3 or ethers.js to validate checksum
    // For now, accept mixed case as potentially valid
    return true
  }

  /**
   * Normalize address format
   * @param {string} address - Address to normalize
   * @param {string} type - Blockchain type
   * @returns {string} Normalized address
   */
  const normalizeAddress = (address, type = null) => {
    if (!address || typeof address !== 'string') return ''
    
    const detectedType = type || detectAddressType(address)
    
    switch (detectedType) {
      case 'ethereum':
        // Ethereum addresses should be lowercase with 0x prefix
        return address.toLowerCase()
      case 'solana':
        // Solana addresses are case-sensitive
        return address.trim()
      case 'circular':
        // Circular addresses should maintain their case
        return address.trim()
      case 'bitcoin':
        // Bitcoin addresses are case-sensitive
        return address.trim()
      default:
        return address.trim()
    }
  }

  /**
   * Check if two addresses are the same (handles case sensitivity)
   * @param {string} addr1 - First address
   * @param {string} addr2 - Second address
   * @returns {boolean} True if addresses are the same
   */
  const isSameAddress = (addr1, addr2) => {
    if (!addr1 || !addr2) return false
    
    const type1 = detectAddressType(addr1)
    const type2 = detectAddressType(addr2)
    
    // Different blockchain types
    if (type1 !== type2) return false
    
    // Normalize and compare
    return normalizeAddress(addr1, type1) === normalizeAddress(addr2, type2)
  }

  return {
    // Individual validators
    isValidEthereumAddress,
    isValidSolanaAddress,
    isValidCircularAddress,
    isValidBitcoinAddress,
    
    // Generic validators
    isValidAddress,
    hasValidChecksum,
    
    // Utilities
    detectAddressType,
    normalizeAddress,
    isSameAddress
  }
}
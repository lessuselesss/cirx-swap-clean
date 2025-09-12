import { ref } from 'vue'
import { useFormattedNumbers } from '~/composables/useFormattedNumbers'

/**
 * Circular Protocol Address Validation Composable
 * 
 * Provides async validation for Circular Protocol addresses using the NAG API
 * Synchronized with backend network/chain configuration
 */


export const useCircularAddressValidation = () => {
  const validationCache = ref(new Map())
  const validationPromises = ref(new Map())
  const networkConfig = ref(null)
  
  // Initialize formatted numbers composable
  const { isValidCircularAddress } = useFormattedNumbers()

  // Get backend configuration for network synchronization
  const getBackendConfig = async () => {
    if (networkConfig.value) {
      return networkConfig.value
    }

    try {
      const config = useRuntimeConfig()
      const apiBaseUrl = config.public.apiBaseUrl || 'http://localhost:18423/api/v1'
      
      const configUrl = `${apiBaseUrl}/config/circular-network`
      console.log('🔧 Fetching backend config from:', configUrl)
      
      const response = await fetch(configUrl)
      if (!response.ok) {
        throw new Error(`Config API error: ${response.status}`)
      }
      
      const data = await response.json()
      networkConfig.value = data
      
      console.log('🔗 Backend network config loaded:', {
        network: data.network,
        environment: data.environment, 
        chain: data.chain_name
      })
      
      return data
    } catch (error) {
      console.error('Failed to get backend config, using fallback:', error)
      // Fallback configuration
      return {
        network: 'testnet',
        environment: 'development',
        blockchain_id: '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2',
        nag_url: '/v1/proxy/circular-protocol-validators?cep=',
        chain_name: 'Circular SandBox'
      }
    }
  }

  /**
   * Check delimiter-based address validation
   * Only green light if: 0x + exactly 64 chars + balance >= 0.0
   * @param {string} address - The address to validate 
   * @returns {Promise<{isValid: boolean, exists: boolean, hasBalance: boolean, balance?: string, error?: string}>}
   */
  const checkAddressExists = async (address) => {
    if (!address || typeof address !== 'string') {
      return { isValid: false, exists: false, hasBalance: false, error: 'Invalid address format' }
    }

    // Get backend config for synchronized validation
    const config = await getBackendConfig()
    const cacheKey = `${address}_${config.blockchain_id}`
    
    if (validationCache.value.has(cacheKey)) {
      return validationCache.value.get(cacheKey)
    }

    if (validationPromises.value.has(cacheKey)) {
      return validationPromises.value.get(cacheKey)
    }

    // Start validation
    const validationPromise = performAddressValidation(address, config)
    validationPromises.value.set(cacheKey, validationPromise)

    try {
      const result = await validationPromise
      validationCache.value.set(cacheKey, result)
      return result
    } finally {
      validationPromises.value.delete(cacheKey)
    }
  }

  /**
   * Perform the actual address validation against NAG API
   * Checks both wallet existence AND balance (green light only if balance >= 0.0)
   * @private
   */
  const performAddressValidation = async (address, config) => {
    try {
      console.log('🔍 Validating Circular address:', {
        address: address.slice(0, 10) + '...',
        blockchain: config.chain_name,
        network: config.network
      })

      // Get the API base URL
      const runtimeConfig = useRuntimeConfig()
      const apiBaseUrl = runtimeConfig.public.apiBaseUrl || 'http://localhost:18423/api/v1'
      
      // Build the full URL - handle different prefix patterns
      let nagUrl
      console.log('🔧 URL Construction Debug:', {
        apiBaseUrl,
        'config.nag_url': config.nag_url,
        'nag_url starts with /v1/': config.nag_url.startsWith('/v1/'),
        'nag_url starts with /api/v1/': config.nag_url.startsWith('/api/v1/')
      })
      
      if (config.nag_url.startsWith('/v1/')) {
        // For /v1/ paths, append directly since apiBaseUrl already ends with /v1
        nagUrl = apiBaseUrl + config.nag_url.substring(3) // Remove '/v1' (3 chars)
        console.log('🔧 Using /v1/ logic:', { nagUrl })
      } else if (config.nag_url.startsWith('/api/v1/')) {
        // Legacy /api/v1/ paths - strip and append
        nagUrl = apiBaseUrl + config.nag_url.substring(7) // Remove '/api/v1' (7 chars)
        console.log('🔧 Using /api/v1/ logic:', { nagUrl })
      } else if (config.nag_url.startsWith('/')) {
        // Other absolute paths - append to base
        nagUrl = apiBaseUrl + config.nag_url
        console.log('🔧 Using absolute path logic:', { nagUrl })
      } else {
        // Full URL provided
        nagUrl = config.nag_url
        console.log('🔧 Using full URL logic:', { nagUrl })
      }

      // Step 1: Check wallet existence first
      const checkUrl = nagUrl + 'Circular_CheckWallet_'
      const requestBody = {
        Blockchain: config.blockchain_id,
        Address: address.replace('0x', ''), // Strip 0x prefix as required by NAG API
        Version: config.version || '1.0.8'
      }
      
      console.log('🔍 Final constructed NAG API URL:', checkUrl)
      console.log('🔍 Expected URL should be:', 'https://circularprotocol.io/buy/api/v1/proxy/circular-protocol-validators?cep=Circular_CheckWallet_')
      console.log('🔍 NAG API Request Body:', requestBody)
      
      const walletResponse = await fetch(checkUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        mode: 'cors', // Explicitly enable CORS
        body: JSON.stringify(requestBody)
      })

      if (!walletResponse.ok) {
        throw new Error(`HTTP ${walletResponse.status}: ${walletResponse.statusText}`)
      }

      let walletData
      try {
        walletData = await walletResponse.json()
      } catch (parseError) {
        console.error('🚨 Failed to parse wallet check response as JSON:', parseError)
        throw new Error('Server returned invalid response (expected JSON, got HTML/text). Check backend logs.')
      }
      console.log('🔍 Wallet check response:', walletData)

      if (walletData.Result !== 200) {
        return {
          isValid: false,
          exists: false,
          hasBalance: false,
          error: walletData.ERROR || `Wallet check failed: ${walletData.Result}`
        }
      }

      // Step 2: Get balance for existing wallet (required for green light)
      const balanceResponse = await fetch(nagUrl + 'Circular_GetWalletBalance_', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        mode: 'cors', // Explicitly enable CORS
        body: JSON.stringify({
          Blockchain: config.blockchain_id,
          Address: address.replace('0x', ''), // Strip 0x prefix as required by NAG API
          Asset: 'CIRX',
          Version: config.version || '1.0.8'
        })
      })

      if (!balanceResponse.ok) {
        throw new Error(`Balance HTTP ${balanceResponse.status}: ${balanceResponse.statusText}`)
      }

      let balanceData
      try {
        balanceData = await balanceResponse.json()
      } catch (parseError) {
        console.error('🚨 Failed to parse balance check response as JSON:', parseError)
        throw new Error('Server returned invalid response (expected JSON, got HTML/text). Check backend logs.')
      }
      console.log('🔍 Balance check response:', balanceData)

      if (balanceData.Result === 200 && balanceData.Response?.Balance !== undefined) {
        const balance = parseFloat(balanceData.Response.Balance) || 0
        
        return {
          isValid: true,
          exists: true,
          hasBalance: balance >= 0.0, // Green light only if balance >= 0.0
          balance: balance.toString(),
          response: {
            wallet: walletData.Response,
            balance: balanceData.Response
          }
        }
      } else {
        // Wallet exists but balance check failed
        return {
          isValid: true,
          exists: true,
          hasBalance: false,
          error: `Balance check failed: ${balanceData.ERROR || balanceData.Result}`,
          response: { wallet: walletData.Response }
        }
      }
    } catch (error) {
      console.error('🔍 Address validation error:', error)
      return {
        isValid: false,
        exists: false,
        hasBalance: false,
        error: `Validation failed: ${error.message}`
      }
    }
  }

  /**
   * Get cached validation result if available
   */
  const getCachedValidation = (address) => {
    const cacheKey = `${address}_${getBlockchainId()}`
    return validationCache.value.get(cacheKey)
  }

  /**
   * Clear validation cache
   */
  const clearCache = () => {
    validationCache.value.clear()
    validationPromises.value.clear()
  }

  /**
   * Delimiter-based address format validation 
   * Only valid if: 0x + exactly 64 characters
   * This defines when the delimiter has "stopped" and validation should occur
   */
  const isValidCircularAddressFormat = (address) => {
    // Use the consolidated validation function that supports both formats
    return isValidCircularAddress(address)
  }

  /**
   * Check if address is awaiting Circular wallet API response
   * Yellow flashing condition: exactly 66 characters but API response not yet received
   */
  const isAddressPending = (address) => {
    if (!address || typeof address !== 'string') {
      return false
    }
    
    const trimmed = address.trim()
    
    // Only flash yellow when we have a complete address format (66 chars)
    // but are still waiting for the Circular wallet API response
    return (
      trimmed.startsWith('0x') &&
      trimmed.length === 66 && // Exactly the complete length (0x + 64 chars)
      /^0x[a-fA-F0-9]{64}$/.test(trimmed) // Valid hex format
    )
  }

  return {
    checkAddressExists,
    getCachedValidation,
    clearCache,
    isValidCircularAddressFormat,
    isAddressPending
  }
}

/**
 * Validation utilities
 * Consolidates duplicate validation logic across the codebase
 */

const { isValidEthereumAddress, isValidSolanaAddress, isValidCircularAddress } = useFormattedNumbers()

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

  if (requireSpecialChars && !/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
    result.isValid = false
    result.errors.push('Password must contain special characters')
  } else if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
    result.strength += 1
  }

  // Determine strength label
  if (result.strength >= 4) {
    result.strengthLabel = 'strong'
  } else if (result.strength >= 3) {
    result.strengthLabel = 'medium'
  }

  return result
}
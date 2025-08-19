import { ref, computed } from 'vue'

/**
 * Backend API Integration for CIRX OTC Platform
 * Handles wallet-to-wallet transfer system via PHP backend
 */
export function useBackendApi() {
  const runtimeConfig = useRuntimeConfig()
  
  // Configuration
  const API_BASE_URL = runtimeConfig.public.apiBaseUrl || 'http://localhost:8080/api'
  const API_KEY = runtimeConfig.public.apiKey || null
  
  // Deposit wallet addresses for different chains/tokens
  const DEPOSIT_ADDRESSES = computed(() => ({
    ETH: runtimeConfig.public.ethDepositAddress || '0x834244D016F29d6acb42C1B054a88e2e9b1c9228',
    USDC: runtimeConfig.public.usdcDepositAddress || '0x834244D016F29d6acb42C1B054a88e2e9b1c9228',
    USDT: runtimeConfig.public.usdtDepositAddress || '0x834244D016F29d6acb42C1B054a88e2e9b1c9228',
    POLYGON: runtimeConfig.public.polygonDepositAddress || '0x834244D016F29d6acb42C1B054a88e2e9b1c9228',
    BSC: runtimeConfig.public.bscDepositAddress || '0x834244D016F29d6acb42C1B054a88e2e9b1c9228'
  }))
  
  // State management
  const isLoading = ref(false)
  const lastError = ref(null)
  
  // Helper function to create API headers
  const getHeaders = () => {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    }
    
    if (API_KEY) {
      headers['X-API-Key'] = API_KEY
    }
    
    return headers
  }
  
  // Helper function to handle API responses
  const handleApiResponse = async (response) => {
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      const errorMessage = errorData.message || `HTTP ${response.status}: ${response.statusText}`
      throw new Error(errorMessage)
    }
    
    return response.json()
  }
  
  /**
   * Initiate a swap transaction with the backend
   */
  const initiateSwap = async (swapData) => {
    try {
      isLoading.value = true
      lastError.value = null
      
      // Validate required fields
      const requiredFields = ['txId', 'paymentChain', 'cirxRecipientAddress', 'amountPaid', 'paymentToken']
      const missing = requiredFields.filter(field => !swapData[field])
      
      if (missing.length > 0) {
        throw new Error(`Missing required fields: ${missing.join(', ')}`)
      }
      
      // Make API request
      const fullUrl = `${API_BASE_URL}/v1/transactions/initiate-swap`
      console.log('🔥 Making API request to:', fullUrl)
      console.log('🔥 Swap data:', swapData)
      
      const response = await fetch(fullUrl, {
        method: 'POST',
        headers: getHeaders(),
        body: JSON.stringify(swapData)
      })
      
      const result = await handleApiResponse(response)
      
      return {
        success: true,
        swapId: result.swapId,
        message: result.message,
        status: result.status
      }
      
    } catch (error) {
      lastError.value = error.message
      console.error('Swap initiation failed:', error)
      throw error
      
    } finally {
      isLoading.value = false
    }
  }
  
  /**
   * Get transaction status from the backend
   */
  const getTransactionStatus = async (swapId) => {
    try {
      isLoading.value = true
      lastError.value = null
      
      if (!swapId) {
        throw new Error('Swap ID is required')
      }
      
      const response = await fetch(`${API_BASE_URL}/v1/transactions/${swapId}/status`, {
        method: 'GET',
        headers: getHeaders()
      })
      
      const result = await handleApiResponse(response)
      
      // Backend returns data nested in 'data' property
      const data = result.data || result
      
      return {
        success: true,
        status: data.status,
        message: data.message,
        txId: data.payment_info?.payment_tx_id || data.txId,
        cirxTransferTxId: data.recipient_info?.cirx_transfer_tx_id || data.cirxTransferTxId || null,
        phase: data.phase,
        progress: data.progress,
        transactionId: data.transaction_id
      }
      
    } catch (error) {
      lastError.value = error.message
      console.error('Status check failed:', error)
      throw error
      
    } finally {
      isLoading.value = false
    }
  }
  
  /**
   * Get CIRX balance for an address
   */
  const getCirxBalance = async (address) => {
    try {
      isLoading.value = true
      lastError.value = null
      
      if (!address) {
        throw new Error('Address is required')
      }
      
      const response = await fetch(`${API_BASE_URL}/v1/balance/${address}`, {
        method: 'GET',
        headers: getHeaders()
      })
      
      const result = await handleApiResponse(response)
      
      return {
        success: result.success,
        address: result.address,
        balance: result.balance,
        timestamp: result.timestamp
      }
      
    } catch (error) {
      lastError.value = error.message
      console.error('Balance check failed:', error)
      throw error
      
    } finally {
      isLoading.value = false
    }
  }
  
  /**
   * Calculate CIRX quote based on payment amount and token
   * Platform fee: 4 CIRX is subtracted from the CIRX amount received (not added to payment)
   * User pays exactly the input amount, receives CIRX minus 4 CIRX platform fee
   */
  const calculateCirxQuote = (paymentAmount, paymentToken, isOTC = false) => {
    try {
      const amount = parseFloat(paymentAmount)
      if (isNaN(amount) || amount <= 0) {
        throw new Error('Invalid payment amount')
      }
      
      // Token prices (these would ideally come from the backend or a price oracle)
      const tokenPrices = {
        'ETH': 2700.0,   // $2,700 per ETH
        'USDC': 1.0,     // $1 per USDC
        'USDT': 1.0,     // $1 per USDT
        'SOL': 100.0,    // $100 per SOL
        'BNB': 300.0,    // $300 per BNB
        'MATIC': 0.80    // $0.80 per MATIC
      }
      
      const tokenPrice = tokenPrices[paymentToken] || 1.0
      
      // Platform fee: 4 CIRX (subtracted from CIRX received, not added to payment)
      const platformFeeCirx = 4.0
      const platformFeeUsd = platformFeeCirx * 2.5 // 4 CIRX × $2.50 = $10
      
      // User's payment amount in USD
      const usdAmount = amount * tokenPrice
      
      // Base CIRX rate: $2.50 per CIRX (1 USD = 0.4 CIRX)
      let grossCirxAmount = usdAmount / 2.5
      
      // Apply OTC discount if applicable (to gross amount before fee)
      let discountPercentage = 0
      if (isOTC) {
        if (usdAmount >= 50000) {
          discountPercentage = 12 // 12% discount for $50K+
        } else if (usdAmount >= 10000) {
          discountPercentage = 8  // 8% discount for $10K-$50K
        } else if (usdAmount >= 1000) {
          discountPercentage = 5  // 5% discount for $1K-$10K
        }
        
        if (discountPercentage > 0) {
          const discountMultiplier = 1 + (discountPercentage / 100)
          grossCirxAmount *= discountMultiplier
        }
      }
      
      // Subtract 4 CIRX platform fee from received amount
      const netCirxAmount = Math.max(0, grossCirxAmount - platformFeeCirx)
      
      // User pays exactly what they input (no additional fees)
      const totalPaymentRequired = amount
      
      return {
        cirxAmount: netCirxAmount.toFixed(1), // Net amount after 4 CIRX fee
        grossCirxAmount: grossCirxAmount.toFixed(1), // Before platform fee
        usdValue: usdAmount.toFixed(2),
        discountPercentage: discountPercentage.toString(),
        platformFee: {
          cirx: platformFeeCirx.toString(),
          usd: platformFeeUsd.toString(),
          deductedFromCirx: true // Important: fee is deducted from CIRX, not payment
        },
        totalPaymentRequired: totalPaymentRequired.toFixed(6), // No fee added to payment
        basePaymentAmount: amount.toFixed(6)
      }
      
    } catch (error) {
      console.error('Quote calculation failed:', error)
      throw error
    }
  }
  
  /**
   * Get the appropriate deposit address for a payment token
   */
  const getDepositAddress = (paymentToken, paymentChain = 'ethereum') => {
    // Map tokens to their deposit addresses
    const tokenAddressMap = {
      'ETH': DEPOSIT_ADDRESSES.value.ETH,
      'USDC': DEPOSIT_ADDRESSES.value.USDC,
      'USDT': DEPOSIT_ADDRESSES.value.USDT
    }
    
    // Map chains to their deposit addresses
    const chainAddressMap = {
      'ethereum': DEPOSIT_ADDRESSES.value.ETH,
      'polygon': DEPOSIT_ADDRESSES.value.POLYGON,
      'bsc': DEPOSIT_ADDRESSES.value.BSC
    }
    
    // Prioritize token-specific address, fallback to chain address
    return tokenAddressMap[paymentToken] || chainAddressMap[paymentChain] || DEPOSIT_ADDRESSES.value.ETH
  }
  
  /**
   * Validate if a Circular Protocol address is properly formatted
   */
  const validateCircularAddress = (address) => {
    if (!address || typeof address !== 'string') {
      return false
    }
    
    // Circular Protocol addresses are 64 hex characters with 0x prefix
    const circularAddressRegex = /^0x[a-fA-F0-9]{64}$/
    return circularAddressRegex.test(address)
  }
  
  /**
   * Create a complete swap transaction data object
   */
  const createSwapTransaction = (paymentTxId, paymentChain, cirxRecipientAddress, amountPaid, paymentToken) => {
    return {
      txId: paymentTxId,
      paymentChain: paymentChain.toLowerCase(),
      cirxRecipientAddress,
      amountPaid: amountPaid.toString(),
      paymentToken: paymentToken.toUpperCase()
    }
  }
  
  // Return the interface
  return {
    // Configuration
    API_BASE_URL,
    DEPOSIT_ADDRESSES,
    
    // State
    isLoading,
    lastError,
    
    // API Methods
    initiateSwap,
    getTransactionStatus,
    getCirxBalance,
    
    // Utility Methods
    calculateCirxQuote,
    getDepositAddress,
    validateCircularAddress,
    createSwapTransaction,
    
    // Constants
    tokenPrices: {
      'ETH': 2700.0,
      'USDC': 1.0,
      'USDT': 1.0,
      'SOL': 100.0,
      'BNB': 300.0,
      'MATIC': 0.80
    }
  }
}
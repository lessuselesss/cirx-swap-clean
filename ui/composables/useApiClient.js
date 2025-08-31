/**
 * Unified API Client
 * Consolidated from useBackendAPIs.js to eliminate redundant API patterns
 * Provides consistent HTTP request handling, error management, and retry logic
 */
import { ref, computed } from 'vue'

export function useApiClient() {
  
  // Get runtime configuration
  const runtimeConfig = useRuntimeConfig()
  
  // API Configuration
  const API_BASE_URL = runtimeConfig.public.apiBaseUrl || 'http://localhost:18423/api'
  const API_KEY = runtimeConfig.public.apiKey || null
  
  // Global state management
  const isLoading = ref(false)
  const lastError = ref(null)
  const requestCount = ref(0)
  
  // Request timeout configuration
  const DEFAULT_TIMEOUT = 10000 // 10 seconds
  const RETRY_ATTEMPTS = 3
  const RETRY_DELAY = 1000 // 1 second
  
  /**
   * Create standardized API headers
   * Consolidated from useBackendAPIs.js getHeaders() (lines 28-39)
   */
  const createHeaders = (customHeaders = {}) => {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Request-ID': `req-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      ...customHeaders
    }
    
    if (API_KEY) {
      headers['X-API-Key'] = API_KEY
    }
    
    return headers
  }

  /**
   * Enhanced API response handler with detailed error information
   * Consolidated from useBackendAPIs.js handleApiResponse() (lines 42-50)
   */
  const handleApiResponse = async (response, context = {}) => {
    if (!response.ok) {
      let errorData = {}
      let errorMessage = `HTTP ${response.status}: ${response.statusText}`
      
      try {
        errorData = await response.json()
        errorMessage = errorData.message || errorData.error || errorMessage
      } catch (parseError) {
        console.warn('Failed to parse error response:', parseError)
      }
      
      // Create detailed error object
      const apiError = new Error(errorMessage)
      apiError.status = response.status
      apiError.data = errorData
      apiError.url = response.url
      apiError.context = context
      
      throw apiError
    }
    
    try {
      return await response.json()
    } catch (parseError) {
      console.warn('Failed to parse successful response:', parseError)
      return { success: true }
    }
  }

  /**
   * Create AbortController with timeout
   */
  const createTimeoutController = (timeout = DEFAULT_TIMEOUT) => {
    const controller = new AbortController()
    const timeoutId = setTimeout(() => {
      controller.abort()
    }, timeout)
    
    return { controller, timeoutId }
  }

  /**
   * Retry logic with exponential backoff
   */
  const withRetry = async (operation, maxAttempts = RETRY_ATTEMPTS) => {
    let lastError
    
    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
      try {
        return await operation()
      } catch (error) {
        lastError = error
        
        // Don't retry certain error types
        if (error.status && (error.status === 400 || error.status === 401 || error.status === 403)) {
          throw error
        }
        
        if (attempt < maxAttempts) {
          const delay = RETRY_DELAY * Math.pow(2, attempt - 1) // Exponential backoff
          console.warn(`Request failed, retrying in ${delay}ms (attempt ${attempt}/${maxAttempts})`)
          await new Promise(resolve => setTimeout(resolve, delay))
        }
      }
    }
    
    throw lastError
  }

  /**
   * Unified API request method
   * Consolidates patterns from initiateSwap() and getTransactionStatus()
   */
  const createApiRequest = async (method, endpoint, options = {}) => {
    const {
      data = null,
      headers = {},
      timeout = DEFAULT_TIMEOUT,
      retry = true,
      validateData = null,
      context = {}
    } = options

    // Update global state
    requestCount.value++
    isLoading.value = true
    lastError.value = null

    const makeRequest = async () => {
      // Create timeout controller
      const { controller, timeoutId } = createTimeoutController(timeout)
      
      try {
        // Validate request data if validator provided
        if (validateData && data) {
          const validation = validateData(data)
          if (!validation.valid) {
            throw new Error(`Validation failed: ${validation.errors.join(', ')}`)
          }
        }

        // Build full URL
        const fullUrl = `${API_BASE_URL}${endpoint.startsWith('/') ? '' : '/'}${endpoint}`
        
        // Create request configuration
        const requestConfig = {
          method: method.toUpperCase(),
          headers: createHeaders(headers),
          signal: controller.signal
        }

        // Add body for POST/PUT/PATCH requests
        if (data && ['POST', 'PUT', 'PATCH'].includes(requestConfig.method)) {
          requestConfig.body = JSON.stringify(data)
        }

        console.log(`🌐 ${method.toUpperCase()} ${fullUrl}`, data ? { data } : '')

        // Make the request
        const response = await fetch(fullUrl, requestConfig)
        clearTimeout(timeoutId)

        // Handle response
        const result = await handleApiResponse(response, {
          method,
          endpoint,
          requestId: requestConfig.headers['X-Request-ID'],
          ...context
        })

        return result

      } catch (error) {
        clearTimeout(timeoutId)
        
        if (error.name === 'AbortError') {
          const timeoutError = new Error(`Request timeout after ${timeout}ms`)
          timeoutError.code = 'TIMEOUT'
          throw timeoutError
        }
        
        throw error
      }
    }

    try {
      const result = retry ? await withRetry(makeRequest) : await makeRequest()
      
      return {
        success: true,
        data: result,
        requestId: requestCount.value
      }
      
    } catch (error) {
      lastError.value = error.message
      console.error(`API Request Failed [${method} ${endpoint}]:`, error)
      
      // Re-throw with additional context
      const enhancedError = new Error(error.message)
      enhancedError.originalError = error
      enhancedError.method = method
      enhancedError.endpoint = endpoint
      enhancedError.requestId = requestCount.value
      
      throw enhancedError
      
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Convenience methods for different HTTP verbs
   */
  const get = (endpoint, options = {}) => 
    createApiRequest('GET', endpoint, options)
  
  const post = (endpoint, data, options = {}) => 
    createApiRequest('POST', endpoint, { ...options, data })
  
  const put = (endpoint, data, options = {}) => 
    createApiRequest('PUT', endpoint, { ...options, data })
  
  const patch = (endpoint, data, options = {}) => 
    createApiRequest('PATCH', endpoint, { ...options, data })
  
  const del = (endpoint, options = {}) => 
    createApiRequest('DELETE', endpoint, options)

  /**
   * Validation helpers for common request types
   */
  const validators = {
    swapData: (data) => {
      const requiredFields = ['txId', 'paymentChain', 'cirxRecipientAddress', 'amountPaid', 'paymentToken']
      const missing = requiredFields.filter(field => !data[field])
      
      return {
        valid: missing.length === 0,
        errors: missing.length > 0 ? [`Missing required fields: ${missing.join(', ')}`] : []
      }
    },
    
    transactionId: (data) => {
      const valid = data && typeof data.transactionId === 'string' && data.transactionId.length > 0
      return {
        valid,
        errors: valid ? [] : ['Valid transactionId is required']
      }
    }
  }

  /**
   * Health check endpoint
   */
  const healthCheck = async () => {
    try {
      const result = await get('/health/quick', { 
        timeout: 5000, 
        retry: false,
        context: { operation: 'health_check' }
      })
      return result.success
    } catch (error) {
      console.warn('Health check failed:', error.message)
      return false
    }
  }

  /**
   * Get API status and metrics
   */
  const getApiStatus = () => ({
    isLoading: isLoading.value,
    lastError: lastError.value,
    requestCount: requestCount.value,
    baseUrl: API_BASE_URL,
    hasApiKey: !!API_KEY
  })

  /**
   * Clear error state
   */
  const clearError = () => {
    lastError.value = null
  }

  // Return public API
  return {
    // Core request methods
    createApiRequest,
    get,
    post,
    put,
    patch,
    del,
    
    // Utilities
    healthCheck,
    getApiStatus,
    clearError,
    validators,
    
    // State (readonly)
    isLoading: computed(() => isLoading.value),
    lastError: computed(() => lastError.value),
    requestCount: computed(() => requestCount.value)
  }
}

// Named exports for direct import
export const useApi = useApiClient
/**
 * Blockchain Transaction Composable
 * Handles ETH and ERC20 token transactions using AppKit/viem
 */
import { ref, computed } from 'vue'
import { parseUnits, formatUnits } from 'viem'
import { useAppKitWallet } from './useAppKitWallet.js'
import { useApiClient } from './core/useApiClient.js'
import { useCirxUtils } from './useCirxUtils.js'

// ERC20 ABI for token transfers
const ERC20_ABI = [
  {
    name: 'transfer',
    type: 'function',
    stateMutability: 'nonpayable',
    inputs: [
      { name: 'to', type: 'address' },
      { name: 'amount', type: 'uint256' }
    ],
    outputs: [{ name: 'success', type: 'bool' }]
  },
  {
    name: 'allowance',
    type: 'function',
    stateMutability: 'view',
    inputs: [
      { name: 'owner', type: 'address' },
      { name: 'spender', type: 'address' }
    ],
    outputs: [{ name: 'allowance', type: 'uint256' }]
  },
  {
    name: 'approve',
    type: 'function',
    stateMutability: 'nonpayable',
    inputs: [
      { name: 'spender', type: 'address' },
      { name: 'amount', type: 'uint256' }
    ],
    outputs: [{ name: 'success', type: 'bool' }]
  }
]

// Token contract addresses for mainnet
const TOKEN_CONTRACTS = {
  USDC: '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', // USDC on Ethereum
  USDT: '0xdAC17F958D2ee523a2206206994597C13D831ec7'  // USDT on Ethereum
}

// Token decimals
const TOKEN_DECIMALS = {
  ETH: 18,
  USDC: 6,
  USDT: 6
}

export function useBlockchainTransaction() {
  const wallet = useAppKitWallet()
  const apiClient = useApiClient()
  const cirxUtils = useCirxUtils()
  
  const isExecuting = ref(false)
  const executionStep = ref('')
  
  /**
   * Execute a blockchain transaction (ETH or ERC20) using AppKit's provider
   */
  const executeTransaction = async (paymentToken, amount, recipientAddress) => {
    if (!wallet.isConnected.value) {
      throw new Error('Wallet not connected')
    }
    
    // Note: recipientAddress is a CIRX address for the backend, not needed for blockchain transaction
    // The actual blockchain transaction goes to the protocol's deposit address
    
    isExecuting.value = true
    executionStep.value = 'Preparing transaction...'
    
    try {
      let txHash
      const depositAddress = String(cirxUtils.getDepositAddress(paymentToken, 'ethereum'))
      
      // Get AppKit's provider directly - this respects the user's connected chain
      const provider = await window.$appKit?.getWalletProvider()
      if (!provider) {
        throw new Error('AppKit provider not available')
      }
      
      if (paymentToken === 'ETH') {
        // Native ETH transfer using AppKit provider
        executionStep.value = 'Estimating gas for ETH transaction...'
        
        const value = '0x' + parseUnits(amount.toString(), TOKEN_DECIMALS.ETH).toString(16)
        
        // Estimate gas for the transaction
        const gasEstimate = await provider.request({
          method: 'eth_estimateGas',
          params: [{
            from: String(wallet.address.value),
            to: String(depositAddress),
            value: value
          }]
        })
        
        // Get current gas price
        const gasPrice = await provider.request({
          method: 'eth_gasPrice',
          params: []
        })
        
        // Add 20% buffer to gas estimate
        const gasLimit = '0x' + Math.floor(parseInt(gasEstimate, 16) * 1.2).toString(16)
        
        executionStep.value = 'Sending ETH transaction...'
        
        txHash = await provider.request({
          method: 'eth_sendTransaction',
          params: [{
            from: String(wallet.address.value),
            to: String(depositAddress),
            value: value,
            gas: gasLimit,
            gasPrice: gasPrice
          }]
        })
        
      } else {
        // ERC20 token transfer using AppKit provider
        const contractAddress = TOKEN_CONTRACTS[paymentToken]
        if (!contractAddress) {
          throw new Error(`Contract address not found for token ${paymentToken}`)
        }
        
        const decimals = TOKEN_DECIMALS[paymentToken]
        const transferAmount = parseUnits(amount.toString(), decimals)
        
        // Encode ERC20 transfer function call
        // transfer(address,uint256) = 0xa9059cbb
        const transferSelector = '0xa9059cbb'
        const paddedAddress = depositAddress.slice(2).padStart(64, '0')
        const paddedAmount = transferAmount.toString(16).padStart(64, '0')
        const transferData = transferSelector + paddedAddress + paddedAmount
        
        executionStep.value = `Estimating gas for ${paymentToken} transaction...`
        
        // Estimate gas for the ERC20 transfer
        const gasEstimate = await provider.request({
          method: 'eth_estimateGas',
          params: [{
            from: String(wallet.address.value),
            to: String(contractAddress),
            data: String(transferData)
          }]
        })
        
        // Get current gas price
        const gasPrice = await provider.request({
          method: 'eth_gasPrice',
          params: []
        })
        
        // Add 20% buffer to gas estimate
        const gasLimit = '0x' + Math.floor(parseInt(gasEstimate, 16) * 1.2).toString(16)
        
        executionStep.value = `Sending ${paymentToken} transaction...`
        
        txHash = await provider.request({
          method: 'eth_sendTransaction',
          params: [{
            from: String(wallet.address.value),
            to: String(contractAddress),
            data: String(transferData),
            gas: gasLimit,
            gasPrice: gasPrice
          }]
        })
      }
      
      executionStep.value = 'Transaction sent, waiting for confirmation...'
      
      // Wait for transaction receipt using AppKit's provider
      const receipt = await provider.request({
        method: 'eth_getTransactionReceipt',
        params: [txHash]
      })
      
      // Simple polling for receipt if not immediately available
      if (!receipt) {
        for (let i = 0; i < 30; i++) {
          await new Promise(resolve => setTimeout(resolve, 2000))
          const pollReceipt = await provider.request({
            method: 'eth_getTransactionReceipt',
            params: [txHash]
          })
          if (pollReceipt) break
        }
      }
      
      return {
        success: true,
        txHash,
        amount: amount.toString(),
        token: paymentToken,
        depositAddress,
        senderAddress: wallet.address.value
      }
      
    } catch (error) {
      console.error('Transaction execution failed:', error)
      
      // Handle specific wallet errors
      if (error.code === 4001) {
        throw new Error('Transaction rejected by user')
      } else if (error.message?.includes('insufficient funds')) {
        throw new Error(`Insufficient ${paymentToken} balance`)
      } else if (error.message?.includes('gas') || error.message?.includes('estimate')) {
        throw new Error('Gas estimation failed. Please try again or contact support.')
      } else if (error.message?.includes('revert') || error.message?.includes('execution reverted')) {
        throw new Error('Transaction reverted. Check contract conditions or token balance.')
      } else if (error.message?.includes('nonce')) {
        throw new Error('Nonce error. Please reset your wallet or try again.')
      } else {
        throw new Error(error.message || 'Transaction failed')
      }
    } finally {
      isExecuting.value = false
      executionStep.value = ''
    }
  }
  
  /**
   * Submit transaction to backend API
   */
  const submitToBackend = async (transactionResult, cirxRecipientAddress) => {
    if (!transactionResult.success) {
      throw new Error('Cannot submit failed transaction')
    }
    
    const swapData = cirxUtils.createSwapTransaction(
      transactionResult.txHash,
      'ethereum', // TODO: Make this dynamic based on connected chain
      cirxRecipientAddress,
      transactionResult.amount,
      transactionResult.token
    )
    
    // Add sender address if available
    if (transactionResult.senderAddress) {
      swapData.senderAddress = transactionResult.senderAddress
    }
    
    console.log('Submitting to backend:', swapData)
    
    const apiResponse = await apiClient.post('/transactions/initiate-swap', swapData)
    
    // API client wraps backend response in 'data' property
    const backendResponse = apiResponse.data
    
    if (backendResponse.status !== 'success') {
      throw new Error(backendResponse.message || 'Backend submission failed')
    }
    
    return {
      success: true,
      swapId: backendResponse.swapId,
      transactionHash: transactionResult.txHash
    }
  }
  
  /**
   * Execute complete transaction flow: wallet transaction + backend submission
   */
  const executeCompleteTransaction = async (paymentToken, amount, cirxRecipientAddress) => {
    try {
      // Step 1: Execute blockchain transaction
      const transactionResult = await executeTransaction(paymentToken, amount, cirxRecipientAddress)
      
      // Step 2: Submit to backend
      executionStep.value = 'Submitting to backend...'
      const backendResult = await submitToBackend(transactionResult, cirxRecipientAddress)
      
      return {
        success: true,
        txHash: transactionResult.txHash,
        swapId: backendResult.swapId,
        amount: transactionResult.amount,
        token: transactionResult.token
      }
      
    } catch (error) {
      console.error('Complete transaction failed:', error)
      throw error
    }
  }
  
  /**
   * Estimate gas for a transaction (useful for pre-flight checks)
   */
  const estimateGas = async (paymentToken, amount) => {
    if (!wallet.publicClient?.value || !wallet.address.value) {
      return null
    }
    
    try {
      const depositAddress = cirxUtils.getDepositAddress(paymentToken, 'ethereum')
      
      if (paymentToken === 'ETH') {
        const gasEstimate = await wallet.publicClient.value.estimateGas({
          to: depositAddress,
          value: parseUnits(amount.toString(), TOKEN_DECIMALS.ETH),
          account: wallet.address.value
        })
        return gasEstimate
      } else {
        const contractAddress = TOKEN_CONTRACTS[paymentToken]
        if (!contractAddress) return null
        
        const decimals = TOKEN_DECIMALS[paymentToken]
        const transferAmount = parseUnits(amount.toString(), decimals)
        
        const gasEstimate = await wallet.publicClient.value.estimateContractGas({
          address: contractAddress,
          abi: ERC20_ABI,
          functionName: 'transfer',
          args: [depositAddress, transferAmount],
          account: wallet.address.value
        })
        return gasEstimate
      }
    } catch (error) {
      console.error('Gas estimation failed:', error)
      return null
    }
  }
  
  return {
    // State
    isExecuting: computed(() => isExecuting.value),
    executionStep: computed(() => executionStep.value),
    
    // Methods
    executeTransaction,
    submitToBackend,
    executeCompleteTransaction,
    estimateGas,
    
    // Constants
    TOKEN_CONTRACTS,
    TOKEN_DECIMALS
  }
}
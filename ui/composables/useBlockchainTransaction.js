/**
 * Blockchain Transaction Composable
 * Handles ETH and ERC20 token transactions using AppKit/viem
 */
import { ref, computed, unref } from 'vue'
import { parseUnits, formatUnits, isAddress } from 'viem'
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
   * Execute a blockchain transaction (ETH or ERC20)
   */
  const executeTransaction = async (paymentToken, amount, recipientAddress) => {
    // Get raw values from reactive refs to avoid postMessage cloning issues
    // CRITICAL: Use unref() to extract raw values without Vue reactivity wrappers
    const currentWalletClient = unref(wallet.walletClient)
    const currentPublicClient = unref(wallet.publicClient)  
    const currentAddress = unref(wallet.address)
    const currentIsConnected = unref(wallet.isConnected)
    
    if (!currentIsConnected || !currentWalletClient) {
      throw new Error('Wallet not connected')
    }
    
    if (!isAddress(recipientAddress)) {
      throw new Error('Invalid recipient address')
    }
    
    isExecuting.value = true
    executionStep.value = 'Preparing transaction...'
    
    try {
      let txHash
      const depositAddress = cirxUtils.getDepositAddress(paymentToken, 'ethereum')
      
      if (paymentToken === 'ETH') {
        // Native ETH transfer
        executionStep.value = 'Sending ETH transaction...'
        txHash = await currentWalletClient.sendTransaction({
          to: depositAddress,
          value: parseUnits(amount.toString(), TOKEN_DECIMALS.ETH),
          account: currentAddress
        })
      } else {
        // ERC20 token transfer
        const contractAddress = TOKEN_CONTRACTS[paymentToken]
        if (!contractAddress) {
          throw new Error(`Contract address not found for token ${paymentToken}`)
        }
        
        const decimals = TOKEN_DECIMALS[paymentToken]
        const transferAmount = parseUnits(amount.toString(), decimals)
        
        executionStep.value = `Sending ${paymentToken} transaction...`
        txHash = await currentWalletClient.writeContract({
          address: contractAddress,
          abi: ERC20_ABI,
          functionName: 'transfer',
          args: [depositAddress, transferAmount],
          account: currentAddress
        })
      }
      
      executionStep.value = 'Transaction sent, waiting for confirmation...'
      
      // Wait for transaction receipt
      if (currentPublicClient) {
        await currentPublicClient.waitForTransactionReceipt({ 
          hash: txHash,
          timeout: 60000 // 60 second timeout
        })
      }
      
      return {
        success: true,
        txHash,
        amount: amount.toString(),
        token: paymentToken,
        depositAddress,
        senderAddress: currentAddress
      }
      
    } catch (error) {
      console.error('Transaction execution failed:', error)
      
      // Handle specific wallet errors
      if (error.code === 4001) {
        throw new Error('Transaction rejected by user')
      } else if (error.message?.includes('insufficient funds')) {
        throw new Error(`Insufficient ${paymentToken} balance`)
      } else if (error.message?.includes('gas')) {
        throw new Error('Transaction failed due to gas issues. Try increasing gas limit.')
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
    
    const response = await apiClient.post('/transactions/initiate-swap', swapData)
    
    if (response.status !== 'success') {
      throw new Error(response.message || 'Backend submission failed')
    }
    
    return {
      success: true,
      swapId: response.swapId,
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
    // CRITICAL: Use unref() to extract raw values without Vue reactivity wrappers
    const currentPublicClient = unref(wallet.publicClient)
    const currentAddress = unref(wallet.address)
    
    if (!currentPublicClient || !currentAddress) {
      return null
    }
    
    try {
      const depositAddress = cirxUtils.getDepositAddress(paymentToken, 'ethereum')
      
      if (paymentToken === 'ETH') {
        const gasEstimate = await currentPublicClient.estimateGas({
          to: depositAddress,
          value: parseUnits(amount.toString(), TOKEN_DECIMALS.ETH),
          account: currentAddress
        })
        return gasEstimate
      } else {
        const contractAddress = TOKEN_CONTRACTS[paymentToken]
        if (!contractAddress) return null
        
        const decimals = TOKEN_DECIMALS[paymentToken]
        const transferAmount = parseUnits(amount.toString(), decimals)
        
        const gasEstimate = await currentPublicClient.estimateContractGas({
          address: contractAddress,
          abi: ERC20_ABI,
          functionName: 'transfer',
          args: [depositAddress, transferAmount],
          account: currentAddress
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
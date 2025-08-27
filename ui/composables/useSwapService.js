import { computed, ref } from 'vue'
import { parseEther, formatEther, parseUnits, formatUnits, getContract } from 'viem'
import { useWalletStore } from '~/stores/wallet'

/**
 * Swap service for backend API integration
 * Provides payment processing and CIRX token swaps via backend
 * Environment-based address configuration for payment monitoring
 */
export function useSwapService() {
  const walletStore = useWalletStore()
  const runtimeConfig = useRuntimeConfig()

  // Contract configuration based on environment
  const CONTRACT_CONFIG = {
    // Production addresses (will be populated when contracts are deployed)
    production: {
      CIRX_TOKEN: process.env.NUXT_PUBLIC_CIRX_TOKEN_ADDRESS || null,
      VESTING_CONTRACT: process.env.NUXT_PUBLIC_VESTING_CONTRACT_ADDRESS || null,
      OTC_SWAP: process.env.NUXT_PUBLIC_OTC_SWAP_ADDRESS || null,
      USDC: process.env.NUXT_PUBLIC_USDC_ADDRESS || '0xA0b86a33E6417c5c6E9c8B9b4f5b5e4E8d8e8d8e',
      USDT: process.env.NUXT_PUBLIC_USDT_ADDRESS || '0xB0b86a33E6417c5c6E9c8B9b4f5b5e4E8d8e8d8e'
    },
    
    // Development/testing addresses
    development: {
      CIRX_TOKEN: null, // Will be set when local contracts are deployed
      VESTING_CONTRACT: null,
      OTC_SWAP: null,
      USDC: '0xA0b86a33E6417c5c6E9c8B9b4f5b5e4E8d8e8d8e', // Mock addresses for dev
      USDT: '0xB0b86a33E6417c5c6E9c8B9b4f5b5e4E8d8e8d8e'
    }
  }

  // Get current environment configuration
  const isDevelopment = process.env.NODE_ENV === 'development'
  const currentConfig = isDevelopment ? CONTRACT_CONFIG.development : CONTRACT_CONFIG.production

  // Contract addresses with null checks
  const CONTRACT_ADDRESSES = computed(() => ({
    CIRX_TOKEN: currentConfig.CIRX_TOKEN,
    VESTING_CONTRACT: currentConfig.VESTING_CONTRACT,
    OTC_SWAP: currentConfig.OTC_SWAP,
    USDC: currentConfig.USDC,
    USDT: currentConfig.USDT,
    ETH: '0x0000000000000000000000000000000000000000' // Native ETH
  }))

  // Contract deployment status
  const contractsDeployed = computed(() => ({
    cirxToken: !!CONTRACT_ADDRESSES.value.CIRX_TOKEN,
    vestingContract: !!CONTRACT_ADDRESSES.value.VESTING_CONTRACT,
    otcSwap: !!CONTRACT_ADDRESSES.value.OTC_SWAP,
    allDeployed: !!(CONTRACT_ADDRESSES.value.CIRX_TOKEN && 
                    CONTRACT_ADDRESSES.value.VESTING_CONTRACT && 
                    CONTRACT_ADDRESSES.value.OTC_SWAP)
  }))

  // Contract ABIs
  const ABIS = {
    ERC20: [
      {
        name: 'balanceOf',
        type: 'function',
        stateMutability: 'view',
        inputs: [{ name: 'account', type: 'address' }],
        outputs: [{ name: '', type: 'uint256' }]
      },
      {
        name: 'allowance',
        type: 'function',
        stateMutability: 'view',
        inputs: [
          { name: 'owner', type: 'address' },
          { name: 'spender', type: 'address' }
        ],
        outputs: [{ name: '', type: 'uint256' }]
      },
      {
        name: 'approve',
        type: 'function',
        stateMutability: 'nonpayable',
        inputs: [
          { name: 'spender', type: 'address' },
          { name: 'amount', type: 'uint256' }
        ],
        outputs: [{ name: '', type: 'bool' }]
      },
      {
        name: 'transfer',
        type: 'function',
        stateMutability: 'nonpayable',
        inputs: [
          { name: 'to', type: 'address' },
          { name: 'amount', type: 'uint256' }
        ],
        outputs: [{ name: '', type: 'bool' }]
      }
    ],

    OTC_SWAP: [
      {
        name: 'getLiquidQuote',
        type: 'function',
        stateMutability: 'view',
        inputs: [
          { name: 'inputToken', type: 'address' },
          { name: 'inputAmount', type: 'uint256' }
        ],
        outputs: [
          { name: 'cirxAmount', type: 'uint256' },
          { name: 'fee', type: 'uint256' }
        ]
      },
      {
        name: 'getOTCQuote',
        type: 'function',
        stateMutability: 'view',
        inputs: [
          { name: 'inputToken', type: 'address' },
          { name: 'inputAmount', type: 'uint256' }
        ],
        outputs: [
          { name: 'cirxAmount', type: 'uint256' },
          { name: 'fee', type: 'uint256' },
          { name: 'discountBps', type: 'uint256' }
        ]
      },
      {
        name: 'swapLiquid',
        type: 'function',
        stateMutability: 'payable',
        inputs: [
          { name: 'inputToken', type: 'address' },
          { name: 'inputAmount', type: 'uint256' },
          { name: 'minCirxOut', type: 'uint256' }
        ],
        outputs: []
      },
      {
        name: 'swapOTC',
        type: 'function',
        stateMutability: 'payable',
        inputs: [
          { name: 'inputToken', type: 'address' },
          { name: 'inputAmount', type: 'uint256' },
          { name: 'minCirxOut', type: 'uint256' }
        ],
        outputs: []
      }
    ],

    VESTING: [
      {
        name: 'getVestingInfo',
        type: 'function',
        stateMutability: 'view',
        inputs: [{ name: 'user', type: 'address' }],
        outputs: [
          { name: 'totalAmount', type: 'uint256' },
          { name: 'startTime', type: 'uint256' },
          { name: 'claimedAmount', type: 'uint256' },
          { name: 'claimableAmount', type: 'uint256' },
          { name: 'isActive', type: 'bool' }
        ]
      },
      {
        name: 'claimTokens',
        type: 'function',
        stateMutability: 'nonpayable',
        inputs: [],
        outputs: []
      }
    ]
  }

  // Helper functions
  const validateConnection = () => {
    if (!walletStore.isConnected) {
      throw new Error('Wallet not connected')
    }
    
    if (walletStore.activeChain !== 'ethereum') {
      throw new Error('Ethereum wallet required for contract interactions')
    }

    if (!walletStore.activeWallet?.isOnSupportedChain) {
      throw new Error('Please switch to a supported network')
    }
  }

  const validateContractAddress = (contractType) => {
    const address = CONTRACT_ADDRESSES.value[contractType]
    if (!address) {
      throw new Error(`${contractType} contract not deployed or configured`)
    }
    return address
  }

  const getTokenDecimals = (tokenSymbol) => {
    // Most tokens use 18 decimals, but we can customize here
    const decimals = {
      ETH: 18,
      USDC: 6,  // USDC uses 6 decimals
      USDT: 6,  // USDT uses 6 decimals
      CIRX: 18
    }
    return decimals[tokenSymbol] || 18
  }

  // Token balance operations
  const getTokenBalance = async (tokenSymbol, userAddress = null) => {
    try {
      validateConnection()
      
      const address = userAddress || walletStore.activeWallet?.address
      if (!address) {
        throw new Error('No address provided')
      }

      // Handle native ETH
      if (tokenSymbol === 'ETH') {
        const balance = await walletStore.ethereumWallet.publicClient?.getBalance({ address })
        return balance ? formatEther(balance) : '0'
      }

      // Handle ERC20 tokens
      const tokenAddress = CONTRACT_ADDRESSES.value[tokenSymbol]
      if (!tokenAddress) {
        throw new Error(`Token ${tokenSymbol} not configured`)
      }

      const balance = await walletStore.ethereumWallet.publicClient?.readContract({
        address: tokenAddress,
        abi: ABIS.ERC20,
        functionName: 'balanceOf',
        args: [address]
      })

      const decimals = getTokenDecimals(tokenSymbol)
      return balance ? formatUnits(balance, decimals) : '0'

    } catch (error) {
      console.error(`Failed to get ${tokenSymbol} balance:`, error)
      
      // Return mock balance in development when contracts aren't deployed
      if (isDevelopment && !contractsDeployed.value.allDeployed) {
        const mockBalances = {
          ETH: '1.5',
          USDC: '1000.00',
          USDT: '500.00',
          CIRX: '0.00'
        }
        return mockBalances[tokenSymbol] || '0'
      }

      throw error
    }
  }

  // Token approval operations
  const approveToken = async (tokenSymbol, spenderAddress, amount) => {
    try {
      validateConnection()
      
      if (tokenSymbol === 'ETH') {
        return null // ETH doesn't need approval
      }

      const tokenAddress = validateContractAddress(tokenSymbol)
      const decimals = getTokenDecimals(tokenSymbol)
      const amountWei = parseUnits(amount.toString(), decimals)

      const hash = await walletStore.ethereumWallet.walletClient?.writeContract({
        address: tokenAddress,
        abi: ABIS.ERC20,
        functionName: 'approve',
        args: [spenderAddress, amountWei]
      })

      return hash

    } catch (error) {
      console.error(`Failed to approve ${tokenSymbol}:`, error)
      throw error
    }
  }

  const getAllowance = async (tokenSymbol, ownerAddress, spenderAddress) => {
    try {
      if (tokenSymbol === 'ETH') {
        return '999999999' // ETH doesn't need approval
      }

      const tokenAddress = validateContractAddress(tokenSymbol)
      
      const allowance = await walletStore.ethereumWallet.publicClient?.readContract({
        address: tokenAddress,
        abi: ABIS.ERC20,
        functionName: 'allowance',
        args: [ownerAddress, spenderAddress]
      })

      const decimals = getTokenDecimals(tokenSymbol)
      return allowance ? formatUnits(allowance, decimals) : '0'

    } catch (error) {
      console.error(`Failed to get ${tokenSymbol} allowance:`, error)
      return '0'
    }
  }

  // Swap quote operations
  const getLiquidQuote = async (inputToken, inputAmount) => {
    try {
      if (!contractsDeployed.value.otcSwap) {
        // Return mock quote for development
        const mockPrice = inputToken === 'ETH' ? 2500 : 1 // $2500 per ETH, $1 per stablecoin
        return {
          cirxAmount: (parseFloat(inputAmount) * mockPrice).toFixed(2),
          fee: (parseFloat(inputAmount) * mockPrice * 0.003).toFixed(4), // 0.3% fee
          feePercentage: '0.3'
        }
      }

      validateConnection()
      const contractAddress = validateContractAddress('OTC_SWAP')
      const tokenAddress = CONTRACT_ADDRESSES.value[inputToken]
      const decimals = getTokenDecimals(inputToken)
      const amountWei = parseUnits(inputAmount.toString(), decimals)

      const [cirxAmount, fee] = await walletStore.ethereumWallet.publicClient?.readContract({
        address: contractAddress,
        abi: ABIS.OTC_SWAP,
        functionName: 'getLiquidQuote',
        args: [tokenAddress, amountWei]
      })

      return {
        cirxAmount: formatUnits(cirxAmount, 18),
        fee: formatUnits(fee, 18),
        feePercentage: '0.3' // Could be dynamic based on contract
      }

    } catch (error) {
      console.error('Failed to get liquid quote:', error)
      throw error
    }
  }

  const getOTCQuote = async (inputToken, inputAmount) => {
    try {
      if (!contractsDeployed.value.otcSwap) {
        // Return mock quote for development
        const mockPrice = inputToken === 'ETH' ? 2500 : 1
        const baseAmount = parseFloat(inputAmount) * mockPrice
        const usdValue = baseAmount

        // Mock discount tiers
        let discount = 0
        if (usdValue >= 50000) discount = 12
        else if (usdValue >= 10000) discount = 8
        else if (usdValue >= 1000) discount = 5

        const discountMultiplier = 1 + (discount / 100)
        const cirxAmount = baseAmount * discountMultiplier

        return {
          cirxAmount: cirxAmount.toFixed(2),
          fee: (cirxAmount * 0.0015).toFixed(4), // 0.15% fee for OTC
          discount: discount.toString(),
          feePercentage: '0.15'
        }
      }

      validateConnection()
      const contractAddress = validateContractAddress('OTC_SWAP')
      const tokenAddress = CONTRACT_ADDRESSES.value[inputToken]
      const decimals = getTokenDecimals(inputToken)
      const amountWei = parseUnits(inputAmount.toString(), decimals)

      const [cirxAmount, fee, discountBps] = await walletStore.ethereumWallet.publicClient?.readContract({
        address: contractAddress,
        abi: ABIS.OTC_SWAP,
        functionName: 'getOTCQuote',
        args: [tokenAddress, amountWei]
      })

      return {
        cirxAmount: formatUnits(cirxAmount, 18),
        fee: formatUnits(fee, 18),
        discount: (Number(discountBps) / 100).toString(),
        feePercentage: '0.15'
      }

    } catch (error) {
      console.error('Failed to get OTC quote:', error)
      throw error
    }
  }

  // Swap execution operations
  const executeLiquidSwap = async (inputToken, inputAmount, minCirxOut, slippageTolerance = 0.5) => {
    try {
      validateConnection()
      
      // Since contracts aren't deployed, use backend API approach
      if (!contractsDeployed.value.otcSwap) {
        
        // Step 1: User sends payment transaction to monitored address
        const paymentAddress = '0x834244D016F29d6acb42C1B054a88e2e9b1c9228' // From .env.local
        const tokenAddress = CONTRACT_ADDRESSES.value[inputToken]
        const decimals = getTokenDecimals(inputToken)
        const amountWei = parseUnits(inputAmount.toString(), decimals)

        let paymentHash
        if (inputToken === 'ETH') {
          // Send ETH to payment address
          paymentHash = await walletStore.ethereumWallet.walletClient?.sendTransaction({
            to: paymentAddress,
            value: amountWei
          })
        } else {
          // Send ERC20 token to payment address
          paymentHash = await walletStore.ethereumWallet.walletClient?.writeContract({
            address: tokenAddress,
            abi: ABIS.ERC20,
            functionName: 'transfer',
            args: [paymentAddress, amountWei]
          })
        }

        if (!paymentHash) {
          throw new Error('Payment transaction failed')
        }

        // Step 2: Call backend API to initiate swap processing
        const response = await $fetch(`${runtimeConfig.public.apiBaseUrl}/transactions/initiate-swap`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            txId: paymentHash,
            paymentChain: 'ethereum',
            cirxRecipientAddress: walletStore.activeWallet.address,
            amountPaid: inputAmount.toString(),
            paymentToken: inputToken
          })
        })

        return {
          success: true,
          hash: paymentHash,
          swapId: response.swapId,
          type: 'liquid'
        }
      }

      // Original contract code (kept for when contracts are deployed)
      const contractAddress = validateContractAddress('OTC_SWAP')
      const tokenAddress = CONTRACT_ADDRESSES.value[inputToken]
      const decimals = getTokenDecimals(inputToken)
      const amountWei = parseUnits(inputAmount.toString(), decimals)
      
      // Apply slippage tolerance to minimum output
      const slippageMultiplier = 1 - (slippageTolerance / 100)
      const adjustedMinOut = (parseFloat(minCirxOut) * slippageMultiplier).toString()
      const minOutWei = parseUnits(adjustedMinOut, 18)

      // Handle approval for ERC20 tokens
      if (inputToken !== 'ETH') {
        const currentAllowance = await getAllowance(
          inputToken, 
          walletStore.activeWallet.address, 
          contractAddress
        )
        
        if (parseFloat(currentAllowance) < parseFloat(inputAmount)) {
          await approveToken(inputToken, contractAddress, inputAmount)
        }
      }

      // Execute swap
      const hash = await walletStore.ethereumWallet.walletClient?.writeContract({
        address: contractAddress,
        abi: ABIS.OTC_SWAP,
        functionName: 'swapLiquid',
        args: [tokenAddress, amountWei, minOutWei],
        value: inputToken === 'ETH' ? amountWei : 0n
      })

      return {
        success: true,
        hash,
        type: 'liquid'
      }

    } catch (error) {
      console.error('Liquid swap failed:', error)
      throw error
    }
  }

  const executeOTCSwap = async (inputToken, inputAmount, minCirxOut, slippageTolerance = 0.5) => {
    try {
      validateConnection()
      
      // Since contracts aren't deployed, use backend API approach
      if (!contractsDeployed.value.otcSwap) {
        
        // Step 1: User sends payment transaction to monitored address
        const paymentAddress = '0x834244D016F29d6acb42C1B054a88e2e9b1c9228' // From .env.local
        const tokenAddress = CONTRACT_ADDRESSES.value[inputToken]
        const decimals = getTokenDecimals(inputToken)
        const amountWei = parseUnits(inputAmount.toString(), decimals)

        let paymentHash
        if (inputToken === 'ETH') {
          // Send ETH to payment address
          paymentHash = await walletStore.ethereumWallet.walletClient?.sendTransaction({
            to: paymentAddress,
            value: amountWei
          })
        } else {
          // Send ERC20 token to payment address
          paymentHash = await walletStore.ethereumWallet.walletClient?.writeContract({
            address: tokenAddress,
            abi: ABIS.ERC20,
            functionName: 'transfer',
            args: [paymentAddress, amountWei]
          })
        }

        if (!paymentHash) {
          throw new Error('Payment transaction failed')
        }

        // Step 2: Call backend API to initiate swap processing
        const response = await $fetch(`${runtimeConfig.public.apiBaseUrl}/transactions/initiate-swap`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            txId: paymentHash,
            paymentChain: 'ethereum',
            cirxRecipientAddress: walletStore.activeWallet.address,
            amountPaid: inputAmount.toString(),
            paymentToken: inputToken
          })
        })

        return {
          success: true,
          hash: paymentHash,
          swapId: response.swapId,
          type: 'otc'
        }
      }

      // Original contract code (kept for when contracts are deployed)
      const contractAddress = validateContractAddress('OTC_SWAP')
      const tokenAddress = CONTRACT_ADDRESSES.value[inputToken]
      const decimals = getTokenDecimals(inputToken)
      const amountWei = parseUnits(inputAmount.toString(), decimals)
      
      // Apply slippage tolerance
      const slippageMultiplier = 1 - (slippageTolerance / 100)
      const adjustedMinOut = (parseFloat(minCirxOut) * slippageMultiplier).toString()
      const minOutWei = parseUnits(adjustedMinOut, 18)

      // Handle approval for ERC20 tokens
      if (inputToken !== 'ETH') {
        const currentAllowance = await getAllowance(
          inputToken, 
          walletStore.activeWallet.address, 
          contractAddress
        )
        
        if (parseFloat(currentAllowance) < parseFloat(inputAmount)) {
          await approveToken(inputToken, contractAddress, inputAmount)
        }
      }

      // Execute OTC swap
      const hash = await walletStore.ethereumWallet.walletClient?.writeContract({
        address: contractAddress,
        abi: ABIS.OTC_SWAP,
        functionName: 'swapOTC',
        args: [tokenAddress, amountWei, minOutWei],
        value: inputToken === 'ETH' ? amountWei : 0n
      })

      return {
        success: true,
        hash,
        type: 'otc'
      }

    } catch (error) {
      console.error('OTC swap failed:', error)
      throw error
    }
  }

  // Vesting operations
  const getVestingInfo = async (userAddress = null) => {
    try {
      const address = userAddress || walletStore.activeWallet?.address
      if (!address) {
        throw new Error('No address provided')
      }

      if (!contractsDeployed.value.vestingContract) {
        // Return mock vesting info for development
        return {
          totalAmount: '0',
          startTime: 0,
          claimedAmount: '0',
          claimableAmount: '0',
          isActive: false
        }
      }

      validateConnection()
      const contractAddress = validateContractAddress('VESTING_CONTRACT')

      const [totalAmount, startTime, claimedAmount, claimableAmount, isActive] = 
        await walletStore.ethereumWallet.publicClient?.readContract({
          address: contractAddress,
          abi: ABIS.VESTING,
          functionName: 'getVestingInfo',
          args: [address]
        })

      return {
        totalAmount: formatUnits(totalAmount, 18),
        startTime: Number(startTime),
        claimedAmount: formatUnits(claimedAmount, 18),
        claimableAmount: formatUnits(claimableAmount, 18),
        isActive
      }

    } catch (error) {
      console.error('Failed to get vesting info:', error)
      throw error
    }
  }

  const claimVestedTokens = async () => {
    try {
      validateConnection()
      
      if (!contractsDeployed.value.vestingContract) {
        throw new Error('Vesting contract not deployed. Please contact support.')
      }

      const contractAddress = validateContractAddress('VESTING_CONTRACT')

      const hash = await walletStore.ethereumWallet.walletClient?.writeContract({
        address: contractAddress,
        abi: ABIS.VESTING,
        functionName: 'claimTokens',
        args: []
      })

      return {
        success: true,
        hash
      }

    } catch (error) {
      console.error('Claim failed:', error)
      throw error
    }
  }

  // Return the interface
  return {
    // Configuration
    CONTRACT_ADDRESSES,
    contractsDeployed,
    isDevelopment,
    
    // Token operations
    getTokenBalance,
    approveToken,
    getAllowance,
    
    // Quote operations
    getLiquidQuote,
    getOTCQuote,
    
    // Swap operations
    executeLiquidSwap,
    executeOTCSwap,
    
    // Vesting operations
    getVestingInfo,
    claimVestedTokens,
    
    // Utilities
    validateConnection,
    validateContractAddress,
    getTokenDecimals,
    
    // Constants
    ABIS
  }
}
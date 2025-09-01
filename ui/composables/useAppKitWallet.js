import { ref, watch, computed } from 'vue'
import { useAppKitAccount, useDisconnect, useAppKitEvents, useAppKitState } from "@reown/appkit/vue"
import { createPublicClient, createWalletClient, custom, http } from 'viem'
import { mainnet } from 'viem/chains'
import { safeToast } from '~/composables/useToast'

export function useAppKitWallet() {
    const { address, isConnected: rawIsConnected, chainId } = useAppKitAccount()
    const { disconnect } = useDisconnect()
    const { open } = useAppKitState() // Centralized AppKit modal control  
    const events = useAppKitEvents() // Initialize events system
    
    // Ensure isConnected always returns a boolean (never undefined)
    const isConnected = computed(() => {
        const connected = rawIsConnected?.value
        return connected === true // Explicit boolean conversion
    })
    
    // Get provider reference from AppKit for viem clients
    const provider = ref(null)
    
    // Initialize provider when wallet connects (with safe null check)
    watch(() => isConnected.value, async (connected) => {
        if (connected && window.$appKit) {
            try {
                provider.value = await window.$appKit.getWalletProvider()
                console.log('✅ AppKit provider initialized:', !!provider.value)
            } catch (error) {
                console.warn('Failed to get wallet provider:', error)
                provider.value = null
            }
        } else {
            provider.value = null
        }
    }, { immediate: true })
    
    // Connection state management
    const connectionToast = ref({ show: false, type: 'success', title: '', message: '', walletIcon: null })
    const lastConnectedWalletIcon = ref(null) // Store icon when connected
    
    // Create public client for balance operations
    const publicClient = computed(() => {
        try {
            if (provider.value && isConnected.value) {
                return createPublicClient({
                    chain: mainnet,
                    transport: custom(provider.value)
                })
            }
            // Fallback HTTP client for reading operations
            return createPublicClient({
                chain: mainnet,
                transport: http()
            })
        } catch (error) {
            console.warn('Failed to create public client:', error)
            return createPublicClient({
                chain: mainnet,
                transport: http()
            })
        }
    })

    // Create wallet client for transaction operations
    const walletClient = computed(() => {
        try {
            if (!provider.value || !isConnected.value) {
                return null
            }
            
            return createWalletClient({
                chain: mainnet,
                transport: custom(provider.value)
            })
        } catch (error) {
            console.warn('Failed to create wallet client:', error)
            return null
        }
    })
    
    // Wallet icon determination (placeholder - can be enhanced based on wallet type)
    const walletIcon = ref(null)
    
    // Centralized balance management
    const tokenBalances = ref({
        ETH: '0',
        USDC: '0',
        USDT: '0',
        CIRX: '0'
    })
    
    const balanceLoading = ref(false)
    const lastBalanceUpdate = ref(null)
    
    // Token contract addresses (centralized configuration using environment variables)
    // Note: CIRX is NOT an ERC-20 - it's the native asset of Circular Protocol multi-chain
    const TOKEN_ADDRESSES = {
        // Official mainnet contract addresses
        USDC: process.env.NUXT_PUBLIC_USDC_ADDRESS || '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', // Circle USDC
        USDT: process.env.NUXT_PUBLIC_USDT_ADDRESS || '0xdac17f958d2ee523a2206206994597c13d831ec7'  // Tether USDT
        // CIRX not included - it's a native asset, not ERC-20
    }
    
    const getTokenDecimals = (tokenSymbol) => {
        const decimals = {
            ETH: 18,
            USDC: 6,
            USDT: 6,
            CIRX: 18
        }
        return decimals[tokenSymbol] || 18
    }
    
    // Centralized balance fetching functions
    const fetchTokenBalance = async (tokenSymbol) => {
        try {
            if (!address.value || !isConnected.value || !publicClient.value) {
                return '0'
            }
            
            if (tokenSymbol === 'ETH') {
                const balance = await publicClient.value.getBalance({ 
                    address: address.value 
                })
                return (Number(balance) / 1e18).toFixed(6)
            }
            
            const tokenAddress = TOKEN_ADDRESSES[tokenSymbol]
            if (!tokenAddress) {
                console.warn(`Token address not configured for ${tokenSymbol}`)
                return '0'
            }
            
            const balance = await publicClient.value.readContract({
                address: tokenAddress,
                abi: [{
                    name: 'balanceOf',
                    type: 'function',
                    stateMutability: 'view',
                    inputs: [{ name: 'account', type: 'address' }],
                    outputs: [{ name: '', type: 'uint256' }]
                }],
                functionName: 'balanceOf',
                args: [address.value]
            })
            
            const decimals = getTokenDecimals(tokenSymbol)
            const formattedBalance = Number(balance) / Math.pow(10, decimals)
            return formattedBalance.toFixed(6)
            
        } catch (error) {
            console.warn(`Failed to fetch ${tokenSymbol} balance:`, error)
            return '0'
        }
    }
    
    const fetchAllBalances = async () => {
        if (!address.value || !isConnected.value) {
            tokenBalances.value = { ETH: '0', USDC: '0', USDT: '0', CIRX: '0' }
            return
        }
        
        balanceLoading.value = true
        
        try {
            const [ethBalance, usdcBalance, usdtBalance] = await Promise.all([
                fetchTokenBalance('ETH'),
                fetchTokenBalance('USDC'),
                fetchTokenBalance('USDT')
                // TODO: Add CIRX when contract is deployed
            ])
            
            tokenBalances.value = {
                ETH: ethBalance,
                USDC: usdcBalance, 
                USDT: usdtBalance,
                CIRX: '0' // Placeholder
            }
            
            lastBalanceUpdate.value = new Date()
            
        } catch (error) {
            console.error('Failed to fetch balances:', error)
            // Keep existing balances on error, don't reset to zero
        } finally {
            balanceLoading.value = false
        }
    }
    
    const refreshBalances = () => {
        if (isConnected.value && address.value) {
            fetchAllBalances()
        }
    }
    
    // Balance refresh interval management
    let balanceRefreshInterval = null
    
    const startBalanceRefresh = () => {
        if (balanceRefreshInterval) {
            clearInterval(balanceRefreshInterval)
        }
        
        // Initial fetch
        fetchAllBalances()
        
        // Set up periodic refresh (every 30 seconds)
        balanceRefreshInterval = setInterval(() => {
            if (isConnected.value && address?.value) {
                fetchAllBalances()
            } else {
                clearInterval(balanceRefreshInterval)
                balanceRefreshInterval = null
            }
        }, 30000)
    }
    
    const stopBalanceRefresh = () => {
        if (balanceRefreshInterval) {
            clearInterval(balanceRefreshInterval)
            balanceRefreshInterval = null
        }
    }
    
    // Computed values for easy access
    const formattedBalances = computed(() => {
        const format = (balance) => {
            const num = parseFloat(balance)
            if (isNaN(num) || num === 0) return '0'
            if (num < 0.000001) return '< 0.000001'
            if (num < 1) return num.toFixed(6)
            if (num < 1000) return num.toFixed(4)
            if (num < 1000000) return (num / 1000).toFixed(2) + 'K'
            return (num / 1000000).toFixed(2) + 'M'
        }
        
        return {
            ETH: format(tokenBalances.value.ETH),
            USDC: format(tokenBalances.value.USDC),
            USDT: format(tokenBalances.value.USDT), 
            CIRX: format(tokenBalances.value.CIRX)
        }
    })
    
    // AppKit events - use Vue watchers instead of direct event listeners
    // The useAppKitEvents() hook doesn't provide a traditional .on() interface
    // Connection/disconnection events are handled by Vue watchers below
    console.log('🔧 AppKit Events initialized:', events ? 'Available' : 'Not available')
    
    // Watch for connection state changes with toast notifications
    watch([() => isConnected.value, () => address?.value || null], 
        ([connected, addr], [prevConnected, prevAddr] = [false, null]) => {
            // Skip if values haven't actually changed or are still initializing
            if (connected === undefined || addr === undefined) return
            console.log('🔍 WALLET CONNECTION WATCH: State changed:', { connected, addr, prevConnected, prevAddr })
            
            if (connected && !prevConnected) {
                // Just connected - store the icon for later use
                lastConnectedWalletIcon.value = walletIcon.value
                connectionToast.value = {
                    show: true,
                    type: 'success',
                    title: 'Wallet Connected',
                    message: `Connected to ${addr?.slice(0, 6)}...${addr?.slice(-4)}`,
                    walletIcon: walletIcon.value
                }
                
                // Show toast notification
                safeToast('Wallet connected successfully', 'success')
                
                // Start balance refresh when connected
                setTimeout(startBalanceRefresh, 1000) // Small delay to ensure connection is stable
                
            } else if (!connected && prevConnected) {
                // Just disconnected - use stored icon
                console.log('🔍 Wallet disconnected, using stored icon:', lastConnectedWalletIcon.value)
                connectionToast.value = {
                    show: true,
                    type: 'error',
                    title: 'Wallet Disconnected',
                    message: 'Your wallet has been disconnected',
                    walletIcon: lastConnectedWalletIcon.value
                }
                
                // Show toast notification
                safeToast('Wallet disconnected', 'error')
                
                // Clear stored icon after use
                lastConnectedWalletIcon.value = null
                
                // Stop balance refresh when disconnected
                stopBalanceRefresh()
            }
        }, 
        { immediate: false }
    )
    
    // Debug watcher for connection updates
    watch(() => [isConnected.value, address?.value], 
        ([connected, addr]) => {
            console.log('🔍 WALLET DEBUG: AppKit state changed:')
            console.log('  - Connected:', connected)
            console.log('  - Address:', (addr && typeof addr === 'string' && addr.length > 10) 
                ? addr.slice(0, 6) + '...' + addr.slice(-4) 
                : addr || 'none')
            console.log('  - Timestamp:', new Date().toISOString())
        }, 
        { immediate: true }
    )
    
    return {
        // AppKit state
        address,
        isConnected, 
        chainId,
        disconnect,
        
        // AppKit modal control (centralized singleton)
        open,
        
        // Connection state management
        connectionToast,
        lastConnectedWalletIcon,
        walletIcon,
        
        // Centralized balance management
        tokenBalances,
        formattedBalances,
        balanceLoading,
        lastBalanceUpdate,
        fetchTokenBalance,
        fetchAllBalances,
        refreshBalances,
        
        // Blockchain clients
        publicClient,
        walletClient,
        
        // Token configuration
        TOKEN_ADDRESSES,
        getTokenDecimals
    }
}
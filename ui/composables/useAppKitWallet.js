import { ref, watch, computed } from 'vue'
import { useAppKitAccount, useDisconnect, useAppKitEvents, useAppKitState } from "@reown/appkit/vue"
import { createPublicClient, createWalletClient, custom, http } from 'viem'
import { mainnet, sepolia } from 'viem/chains'
import { safeToast } from '~/composables/useToast'
import { getTokenDecimals } from '~/composables/core/useTokenUtils.js'

// Singleton state - shared across all instances of useAppKitWallet()
let singletonState = null

export function useAppKitWallet() {
    // Return existing singleton if already initialized
    if (singletonState) {
        return singletonState
    }
    
    // Add try-catch to prevent critical errors during AppKit hook usage
    let address, rawIsConnected, chainId, disconnect, open, events
    
    try {
        const accountHooks = useAppKitAccount()
        address = accountHooks?.address || ref(null)
        rawIsConnected = accountHooks?.isConnected || ref(false)
        chainId = accountHooks?.chainId || ref(null)
        
        const disconnectHooks = useDisconnect()
        disconnect = disconnectHooks?.disconnect || (() => console.warn('Disconnect not available'))
        
        const stateHooks = useAppKitState()
        open = stateHooks?.open || (() => console.warn('AppKit modal not available'))
        
        events = useAppKitEvents() || null
    } catch (error) {
        console.error('❌ Error initializing AppKit hooks:', error)
        // Provide fallback values to prevent undefined errors
        address = ref(null)
        rawIsConnected = ref(false)
        chainId = ref(null)
        disconnect = () => console.warn('Disconnect not available')
        open = () => console.warn('AppKit modal not available')
        events = null
    }
    
    // Debug: Log what useAppKitAccount actually returns with throttling
    let lastDebugLog = 0
    const DEBUG_THROTTLE = 5000 // 5 seconds
    
    const logDebugInfo = () => {
        const now = Date.now()
        if (now - lastDebugLog > DEBUG_THROTTLE) {
            console.log('🔍 useAppKitAccount state:', {
                address: address?.value,
                rawIsConnected: rawIsConnected?.value,
                chainId: chainId?.value,
                addressType: typeof address?.value,
                isConnectedType: typeof rawIsConnected?.value,
                chainIdType: typeof chainId?.value,
                timestamp: new Date().toISOString()
            })
            lastDebugLog = now
        }
    }
    
    // Log initial state
    logDebugInfo()
    
    // Use the raw AppKit isConnected state directly - it's already reactive
    const isConnected = rawIsConnected
    
    // Check for existing MetaMask connection and sync with AppKit
    const syncWithMetaMask = async () => {
        if (!process.client) return
        
        try {
            // Check if MetaMask is connected but AppKit isn't
            if (window.ethereum && window.ethereum.selectedAddress && !isConnected?.value) {
                console.log('🔗 MetaMask is connected but AppKit is not - attempting sync...')
                console.log('MetaMask address:', window.ethereum.selectedAddress)
                console.log('AppKit connected:', isConnected?.value)
                
                // Method 1: Try to reconnect through MetaMask provider
                try {
                    console.log('🔄 Attempting MetaMask account refresh...')
                    const accounts = await window.ethereum.request({ method: 'eth_accounts' })
                    console.log('MetaMask accounts:', accounts)
                    
                    if (accounts && accounts.length > 0) {
                        // Force AppKit to check for connection updates
                        if (window.$appKit) {
                            console.log('🔄 Triggering AppKit state refresh...')
                            // Try to get wagmi to recognize the connection
                            const state = window.$appKit.getState?.()
                            console.log('AppKit state before sync:', state)
                            
                            // Wait a moment for potential state updates
                            await new Promise(resolve => setTimeout(resolve, 500))
                            
                            // Check if AppKit now sees the connection
                            const accountAfter = window.$appKit.getAccount?.()
                            console.log('AppKit account after sync attempt:', accountAfter)
                            
                            if (accountAfter?.address) {
                                console.log('✅ AppKit now sees connected account:', accountAfter.address)
                                
                                // Direct state update from AppKit internal values (avoids Vue lifecycle warnings)
                                console.log('🔧 Force-setting reactive state from AppKit internal values...')
                                address.value = accountAfter.address
                                rawIsConnected.value = true
                                if (accountAfter?.chainId) {
                                    chainId.value = accountAfter.chainId
                                }
                                
                                await new Promise(resolve => setTimeout(resolve, 300))
                                logDebugInfo()
                                return true
                            }
                        }
                    }
                } catch (providerError) {
                    console.warn('⚠️ MetaMask provider sync failed:', providerError)
                }
                
                // Method 2: Try wagmi reconnection if available
                try {
                    if (window.$appKit && typeof window.$appKit.reconnect === 'function') {
                        console.log('🔄 Attempting AppKit reconnect...')
                        await window.$appKit.reconnect()
                        await new Promise(resolve => setTimeout(resolve, 1000))
                        
                        if (isConnected?.value) {
                            console.log('✅ AppKit reconnect successful!')
                            return true
                        }
                    }
                } catch (reconnectError) {
                    console.warn('⚠️ AppKit reconnect failed:', reconnectError)
                }
                
                console.log('⚠️ MetaMask sync unsuccessful - AppKit state not updated')
                return false
            }
            
            // Already synchronized or no MetaMask connection
            return true
            
        } catch (error) {
            console.error('❌ Error syncing with MetaMask:', error)
            return false
        }
    }
    
    // Run sync check on component mount and when visibility changes
    if (process.client) {
        setTimeout(syncWithMetaMask, 2000) // Delayed to ensure AppKit is fully initialized
        document.addEventListener('visibilitychange', syncWithMetaMask)
    }
    
    // Get provider reference from AppKit for viem clients
    const provider = ref(null)
    
    // Initialize provider when wallet connects (with safe null check)
    watch(() => isConnected?.value, async (connected) => {
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
            if (provider.value && isConnected?.value) {
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
            if (!provider.value || !isConnected?.value) {
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
    
    // Centralized balance fetching functions
    const fetchTokenBalance = async (tokenSymbol) => {
        try {
            if (!address.value || !isConnected?.value || !publicClient.value) {
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
        if (!address.value || !isConnected?.value) {
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
        if (isConnected?.value && address.value) {
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
            if (isConnected?.value && address?.value) {
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
    watch([() => isConnected?.value, () => address?.value || null], 
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
                safeToast.success('Wallet connected successfully')
                
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
                safeToast.info('Wallet disconnected')
                
                // Clear stored icon after use
                lastConnectedWalletIcon.value = null
                
                // Stop balance refresh when disconnected
                stopBalanceRefresh()
            }
        }, 
        { immediate: false }
    )
    
    // Debug watcher for connection updates with throttling
    let lastWatchLog = 0
    const WATCH_LOG_THROTTLE = 2000 // 2 seconds
    
    watch(() => [isConnected?.value, address?.value], 
        ([connected, addr]) => {
            const now = Date.now()
            if (now - lastWatchLog > WATCH_LOG_THROTTLE) {
                console.log('🔍 WALLET DEBUG: AppKit state changed:')
                console.log('  - Connected:', connected)
                console.log('  - Address:', (addr && typeof addr === 'string' && addr.length > 10) 
                    ? addr.slice(0, 6) + '...' + addr.slice(-4) 
                    : addr || 'none')
                console.log('  - Direct AppKit account:', window.$appKit?.getAccount?.())
                console.log('  - Direct AppKit state:', window.$appKit?.getState?.())
                console.log('  - Timestamp:', new Date().toISOString())
                lastWatchLog = now
            }
            
            // Always call logDebugInfo to maintain throttled general logging
            logDebugInfo()
        }, 
        { immediate: true }
    )
    
    // Create the singleton state object
    singletonState = {
        // AppKit state
        address,
        isConnected, 
        chainId,
        disconnect,
        
        // AppKit modal control (centralized singleton)
        open,
        
        // MetaMask sync utility
        syncWithMetaMask,
        
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
        TOKEN_ADDRESSES
        // getTokenDecimals now imported from useTokenUtils
    }
    
    return singletonState
}
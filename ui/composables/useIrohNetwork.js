import { ref, computed } from 'vue'

/**
 * IROH Network Composable
 * 
 * Provides Vue.js composable for IROH distributed networking integration
 * Handles service discovery, real-time updates, and P2P communication
 */

export const useIrohNetwork = () => {
    const config = useRuntimeConfig()
    const isEnabled = ref(false)
    const isConnected = ref(false)
    const nodeId = ref(null)
    const discoveredServices = ref(new Map())
    const connectionStatus = ref('disconnected')
    const networkStats = ref({})

    // IROH bridge connection
    const bridgeUrl = config.public.irohBridgeUrl || 'http://localhost:9090'
    
    // Initialize IROH connection
    const initialize = async () => {
        try {
            // Check if IROH is enabled
            if (!config.public.irohEnabled) {
                console.log('IROH networking is disabled')
                return false
            }

            // Health check the bridge
            const healthResponse = await $fetch(`${bridgeUrl}/health`)
            
            if (!healthResponse.success) {
                console.warn('IROH bridge health check failed')
                return false
            }

            // Get node information
            const nodeResponse = await $fetch(`${bridgeUrl}/node/info`)
            if (nodeResponse.success) {
                nodeId.value = nodeResponse.data.node_id
                isEnabled.value = true
                isConnected.value = true
                connectionStatus.value = 'connected'
                
                console.log('IROH network initialized:', {
                    nodeId: nodeId.value,
                    bridgeUrl
                })

                // Start periodic network monitoring
                startNetworkMonitoring()
                
                return true
            }

            return false
        } catch (error) {
            console.warn('Failed to initialize IROH network:', error)
            connectionStatus.value = 'error'
            return false
        }
    }

    // Discover services on the network
    const discoverServices = async (serviceName, capabilities = [], maxResults = 10) => {
        if (!isConnected.value) {
            console.warn('IROH not connected - cannot discover services')
            return []
        }

        try {
            const response = await $fetch(`${bridgeUrl}/services/discover`, {
                method: 'POST',
                body: {
                    service_name: serviceName,
                    capabilities,
                    max_results: maxResults
                }
            })

            if (response.success) {
                const services = response.data || []
                discoveredServices.value.set(serviceName, services)
                
                console.log(`Discovered ${services.length} ${serviceName} services`)
                return services
            }

            return []
        } catch (error) {
            console.error('Service discovery failed:', error)
            return []
        }
    }

    // Discover backend services
    const discoverBackends = async () => {
        return await discoverServices('cirx-swap-backend', [
            'swap-execution',
            'payment-verification',
            'transaction-history'
        ])
    }

    // Send direct message to a specific node
    const sendToNode = async (nodeId, payload) => {
        if (!isConnected.value) {
            throw new Error('IROH not connected')
        }

        try {
            const response = await $fetch(`${bridgeUrl}/send/${nodeId}`, {
                method: 'POST',
                body: payload
            })

            if (response.success) {
                return response.data
            } else {
                throw new Error(response.error || 'Failed to send message')
            }
        } catch (error) {
            console.error('Failed to send message to node:', error)
            throw error
        }
    }

    // Broadcast message to a topic
    const broadcastToTopic = async (topic, message) => {
        if (!isConnected.value) {
            console.warn('IROH not connected - cannot broadcast')
            return false
        }

        try {
            const response = await $fetch(`${bridgeUrl}/broadcast`, {
                method: 'POST',
                body: {
                    topic,
                    message
                }
            })

            return response.success
        } catch (error) {
            console.error('Failed to broadcast message:', error)
            return false
        }
    }

    // Subscribe to transaction updates (simulated via polling for now)
    const subscribeToTransactionUpdates = (callback) => {
        if (!isConnected.value) {
            console.warn('IROH not connected - cannot subscribe to updates')
            return null
        }

        // For now, we'll simulate real-time updates via polling
        // In a full implementation, this would use WebSocket or Server-Sent Events
        // connected to the IROH gossip protocol
        
        const interval = setInterval(async () => {
            try {
                // Poll for updates from backend services
                const backends = discoveredServices.value.get('cirx-swap-backend') || []
                
                for (const backend of backends) {
                    // In a real implementation, this would receive gossip messages
                    // For now, it's a placeholder for the subscription mechanism
                }
            } catch (error) {
                console.debug('Update polling error:', error)
            }
        }, 5000) // Poll every 5 seconds

        return () => clearInterval(interval)
    }

    // Get network statistics
    const getNetworkStats = async () => {
        if (!isConnected.value) {
            return {}
        }

        try {
            const response = await $fetch(`${bridgeUrl}/node/peers`)
            if (response.success) {
                networkStats.value = response.data
                return response.data
            }
            return {}
        } catch (error) {
            console.error('Failed to get network stats:', error)
            return {}
        }
    }

    // Start periodic network monitoring
    const startNetworkMonitoring = () => {
        setInterval(async () => {
            try {
                await getNetworkStats()
                
                // Check connection health
                const healthResponse = await $fetch(`${bridgeUrl}/health`)
                if (!healthResponse.success) {
                    connectionStatus.value = 'degraded'
                } else {
                    connectionStatus.value = 'connected'
                }
            } catch (error) {
                connectionStatus.value = 'error'
                isConnected.value = false
            }
        }, 30000) // Monitor every 30 seconds
    }

    // Get all discovered services
    const getAllServices = async () => {
        if (!isConnected.value) {
            return []
        }

        try {
            const response = await $fetch(`${bridgeUrl}/services/list`)
            return response.success ? response.data : []
        } catch (error) {
            console.error('Failed to get services:', error)
            return []
        }
    }

    // Reconnect to the network
    const reconnect = async () => {
        connectionStatus.value = 'connecting'
        isConnected.value = false
        
        const success = await initialize()
        if (success) {
            connectionStatus.value = 'connected'
            isConnected.value = true
        } else {
            connectionStatus.value = 'error'
        }
        
        return success
    }

    // Auto-initialize on creation (if enabled)
    onMounted(async () => {
        if (config.public.irohEnabled) {
            await initialize()
        }
    })

    return {
        // State
        isEnabled: readonly(isEnabled),
        isConnected: readonly(isConnected),
        nodeId: readonly(nodeId),
        discoveredServices: readonly(discoveredServices),
        connectionStatus: readonly(connectionStatus),
        networkStats: readonly(networkStats),

        // Methods
        initialize,
        discoverServices,
        discoverBackends,
        sendToNode,
        broadcastToTopic,
        subscribeToTransactionUpdates,
        getNetworkStats,
        getAllServices,
        reconnect
    }
}

/**
 * Real-time Transaction Updates Composable
 * 
 * Provides real-time transaction status updates via IROH networking
 */


export const useRealTimeTransactions = () => {
    const { isEnabled, isConnected, nodeId, discoveredServices, connectionStatus, networkStats, subscribeToTransactionUpdates, broadcastToTopic } = useIrohNetwork()
    
    const transactions = ref(new Map())
    const subscriptions = ref(new Map())
    const updateListeners = ref(new Set())

    // Subscribe to updates for a specific transaction
    const subscribeToTransaction = (transactionId, callback) => {
        if (!isConnected.value) {
            console.warn('IROH not connected - cannot subscribe to transaction updates')
            return null
        }

        // Store the callback for this transaction
        if (!updateListeners.value.has(transactionId)) {
            updateListeners.value.set(transactionId, new Set())
        }
        updateListeners.value.get(transactionId).add(callback)

        // If this is the first subscription for this transaction, start monitoring
        if (!subscriptions.value.has(transactionId)) {
            const unsubscribe = subscribeToTransactionUpdates((update) => {
                if (update.transaction_id === transactionId) {
                    handleTransactionUpdate(update)
                }
            })
            
            subscriptions.value.set(transactionId, unsubscribe)
        }

        // Return unsubscribe function
        return () => {
            const listeners = updateListeners.value.get(transactionId)
            if (listeners) {
                listeners.delete(callback)
                
                // If no more listeners, unsubscribe from the transaction
                if (listeners.size === 0) {
                    const unsubscribe = subscriptions.value.get(transactionId)
                    if (unsubscribe) {
                        unsubscribe()
                        subscriptions.value.delete(transactionId)
                    }
                    updateListeners.value.delete(transactionId)
                }
            }
        }
    }

    // Handle incoming transaction updates
    const handleTransactionUpdate = (update) => {
        const transactionId = update.transaction_id
        
        // Update local transaction cache
        const existingTransaction = transactions.value.get(transactionId) || {}
        const updatedTransaction = {
            ...existingTransaction,
            id: transactionId,
            status: update.status,
            metadata: update.metadata || {},
            lastUpdate: update.timestamp || Date.now(),
            sourceNode: update.node_id
        }
        
        transactions.value.set(transactionId, updatedTransaction)

        // Notify all listeners for this transaction
        const listeners = updateListeners.value.get(transactionId)
        if (listeners) {
            listeners.forEach(callback => {
                try {
                    callback(updatedTransaction, update)
                } catch (error) {
                    console.error('Error in transaction update callback:', error)
                }
            })
        }

        console.log(`Transaction ${transactionId} updated:`, {
            status: update.status,
            source: update.node_id
        })
    }

    // Get transaction with real-time status
    const getTransaction = async (transactionId) => {
        try {
            // First check local cache
            const cached = transactions.value.get(transactionId)
            if (cached) {
                return cached
            }

            // Fetch from backend with IROH integration
            const response = await $fetch(`/transactions/${transactionId}/status/realtime`)
            
            if (response.success) {
                const transaction = response.data
                transactions.value.set(transactionId, transaction)
                return transaction
            }

            return null
        } catch (error) {
            console.error('Failed to get transaction:', error)
            return null
        }
    }

    // Monitor transaction until completion
    const monitorTransaction = (transactionId, options = {}) => {
        const {
            onStatusChange = () => {},
            onComplete = () => {},
            onError = () => {},
            timeout = 30000 // 30 seconds default timeout
        } = options

        return new Promise((resolve, reject) => {
            let timeoutId
            
            const unsubscribe = subscribeToTransaction(transactionId, (transaction, update) => {
                onStatusChange(transaction, update)
                
                // Check for completion
                if (transaction.status === 'completed') {
                    onComplete(transaction)
                    cleanup()
                    resolve(transaction)
                } else if (transaction.status?.includes('failed')) {
                    onError(transaction)
                    cleanup()
                    reject(new Error(`Transaction failed: ${transaction.status}`))
                }
            })

            // Set timeout
            if (timeout > 0) {
                timeoutId = setTimeout(() => {
                    cleanup()
                    reject(new Error('Transaction monitoring timeout'))
                }, timeout)
            }

            const cleanup = () => {
                if (unsubscribe) unsubscribe()
                if (timeoutId) clearTimeout(timeoutId)
            }
        })
    }

    // Broadcast transaction event to network
    const broadcastTransactionEvent = async (transactionId, eventType, data = {}) => {
        if (!isConnected.value) {
            return false
        }

        const event = {
            type: eventType,
            transaction_id: transactionId,
            data,
            timestamp: Date.now(),
            frontend_node: nodeId.value
        }

        return await broadcastToTopic('transaction-updates', event)
    }

    // Get all cached transactions
    const getAllTransactions = () => {
        return Array.from(transactions.value.values())
    }

    // Clear transaction cache
    const clearCache = () => {
        transactions.value.clear()
    }

    // Get transaction by ID from cache
    const getCachedTransaction = (transactionId) => {
        return transactions.value.get(transactionId)
    }

    // Update transaction in cache
    const updateCachedTransaction = (transactionId, updates) => {
        const existing = transactions.value.get(transactionId) || {}
        const updated = { ...existing, ...updates, lastUpdate: Date.now() }
        transactions.value.set(transactionId, updated)
        return updated
    }

    // Check if we're monitoring a transaction
    const isMonitoring = (transactionId) => {
        return subscriptions.value.has(transactionId)
    }

    // Get monitoring statistics
    const getMonitoringStats = () => {
        return {
            activeSubscriptions: subscriptions.value.size,
            cachedTransactions: transactions.value.size,
            connectionStatus: connectionStatus.value,
            isConnected: isConnected.value
        }
    }

    return {
        // State
        isEnabled: readonly(isEnabled),
        isConnected: readonly(isConnected),
        nodeId: readonly(nodeId),
        discoveredServices: readonly(discoveredServices),
        connectionStatus: readonly(connectionStatus),
        networkStats: readonly(networkStats),

        // Transaction methods
        subscribeToTransaction,
        getTransaction,
        monitorTransaction,
        broadcastTransactionEvent,
        getAllTransactions,
        clearCache,
        getCachedTransaction,
        updateCachedTransaction,
        isMonitoring,
        getMonitoringStats
    }
}
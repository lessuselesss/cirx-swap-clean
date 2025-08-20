/**
 * Real-time Transaction Updates Composable
 * 
 * Provides real-time transaction status updates via IROH networking
 */

export const useRealTimeTransactions = () => {
    const { isConnected, subscribeToTransactionUpdates, broadcastToTopic } = useIrohNetwork()
    
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
            const response = await $fetch(`/api/v1/transactions/${transactionId}/status/realtime`)
            
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
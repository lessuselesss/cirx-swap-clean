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
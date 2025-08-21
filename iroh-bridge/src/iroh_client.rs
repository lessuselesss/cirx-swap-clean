use anyhow::Result;
use iroh::{Endpoint, RelayMode, SecretKey};
use iroh_gossip::{net::Gossip, proto::TopicId};
use std::collections::HashMap;
use std::sync::Arc;
use tracing::{info, debug, error};

use crate::config::Config;
use crate::error::BridgeError;

#[derive(Clone)]
pub struct IrohClient {
    endpoint: Endpoint,
    gossip: Arc<Gossip>,
    config: Config,
    node_id: String,
}

impl IrohClient {
    pub async fn new(config: Config) -> Result<Self> {
        info!("Initializing IROH client...");

        // Create endpoint builder
        let mut builder = Endpoint::builder();

        // Configure relay mode
        if config.relay.enable {
            builder = builder.relay_mode(RelayMode::Default);
            debug!("Relay mode enabled with default relays");
        } else {
            builder = builder.relay_mode(RelayMode::Disabled);
            debug!("Relay mode disabled");
        }

        // Enable discovery services
        if config.network.enable_n0_discovery {
            builder = builder.discovery_n0();
            debug!("n0 discovery service enabled");
        }

        if config.network.enable_local_discovery {
            builder = builder.discovery_local_network();
            debug!("Local network discovery enabled");
        }

        // Bind the endpoint
        let endpoint = builder.bind().await?;
        let node_id = endpoint.node_id().to_string();
        
        info!("IROH endpoint bound successfully");
        info!("Node ID: {}", node_id);

        // Initialize gossip service
        let gossip = Gossip::builder()
            .spawn(endpoint.clone())
            .await?;

        info!("Gossip service initialized");

        let client = IrohClient {
            endpoint,
            gossip: Arc::new(gossip),
            config,
            node_id,
        };

        // Subscribe to configured topics
        client.subscribe_to_topics().await?;

        Ok(client)
    }

    pub fn node_id(&self) -> &str {
        &self.node_id
    }

    pub fn endpoint(&self) -> &Endpoint {
        &self.endpoint
    }

    pub fn gossip(&self) -> Arc<Gossip> {
        Arc::clone(&self.gossip)
    }

    /// Subscribe to all configured gossip topics
    async fn subscribe_to_topics(&self) -> Result<()> {
        for topic_name in &self.config.gossip.topics {
            let topic_id = TopicId::from_bytes(topic_name.as_bytes());
            
            match self.gossip.subscribe(topic_id, vec![]) {
                Ok(_) => {
                    debug!("Subscribed to topic: {}", topic_name);
                }
                Err(e) => {
                    error!("Failed to subscribe to topic '{}': {}", topic_name, e);
                }
            }
        }
        
        info!("Subscribed to {} topics", self.config.gossip.topics.len());
        Ok(())
    }

    /// Broadcast a message to a specific topic
    pub async fn broadcast_to_topic(&self, topic: &str, message: serde_json::Value) -> Result<()> {
        let topic_id = TopicId::from_bytes(topic.as_bytes());
        
        // Try to get existing subscription or create new one
        let topic_handle = match self.gossip.subscribe(topic_id, vec![]) {
            Ok(handle) => handle,
            Err(e) => {
                error!("Failed to get topic handle for '{}': {}", topic, e);
                return Err(BridgeError::GossipError(e.to_string()).into());
            }
        };

        let (sender, _receiver) = topic_handle.split();
        
        let message_bytes = serde_json::to_vec(&message)?;
        sender.broadcast(message_bytes.into()).await
            .map_err(|e| BridgeError::GossipError(e.to_string()))?;

        debug!("Broadcast message to topic '{}': {:?}", topic, message);
        Ok(())
    }

    /// Connect to a specific node by ID
    pub async fn connect_to_node(&self, node_id: &str) -> Result<iroh::Connection> {
        let node_addr = node_id.parse()
            .map_err(|e| BridgeError::InvalidNodeId(format!("Invalid node ID '{}': {}", node_id, e)))?;

        let connection = self.endpoint.connect(node_addr, b"cirx-swap").await
            .map_err(|e| BridgeError::ConnectionFailed(format!("Failed to connect to node '{}': {}", node_id, e)))?;

        debug!("Connected to node: {}", node_id);
        Ok(connection)
    }

    /// Send a request to a specific node and wait for response
    pub async fn send_request_to_node(
        &self, 
        node_id: &str, 
        request: serde_json::Value
    ) -> Result<serde_json::Value> {
        let connection = self.connect_to_node(node_id).await?;
        
        // Open bidirectional stream
        let (mut send, mut recv) = connection.open_bi().await
            .map_err(|e| BridgeError::ConnectionFailed(format!("Failed to open stream: {}", e)))?;

        // Send request
        let request_bytes = serde_json::to_vec(&request)?;
        send.write_all(&request_bytes).await
            .map_err(|e| BridgeError::RequestFailed(format!("Failed to send request: {}", e)))?;
        send.finish()?;

        // Receive response
        let response_bytes = recv.read_to_end(self.config.gossip.max_message_size).await
            .map_err(|e| BridgeError::RequestFailed(format!("Failed to read response: {}", e)))?;

        let response: serde_json::Value = serde_json::from_slice(&response_bytes)
            .map_err(|e| BridgeError::RequestFailed(format!("Failed to parse response: {}", e)))?;

        debug!("Received response from node '{}': {:?}", node_id, response);
        Ok(response)
    }

    /// Get current node's address information
    pub async fn get_node_address(&self) -> Result<iroh::NodeAddr> {
        let addr = self.endpoint.node_addr().await
            .map_err(|e| BridgeError::NetworkError(format!("Failed to get node address: {}", e)))?;
        Ok(addr)
    }

    /// Get information about connected peers
    pub async fn get_peer_info(&self) -> Result<HashMap<String, serde_json::Value>> {
        // This would require additional IROH API calls to get peer information
        // For now, return basic information
        let mut peers = HashMap::new();
        
        peers.insert("local_node_id".to_string(), serde_json::Value::String(self.node_id.clone()));
        
        // Add relay information if available
        if self.config.relay.enable {
            peers.insert("relay_enabled".to_string(), serde_json::Value::Bool(true));
        }

        Ok(peers)
    }

    /// Health check for the IROH client
    pub async fn health_check(&self) -> Result<serde_json::Value> {
        let addr = self.get_node_address().await?;
        
        Ok(serde_json::json!({
            "status": "healthy",
            "node_id": self.node_id,
            "relay_enabled": self.config.relay.enable,
            "local_discovery": self.config.network.enable_local_discovery,
            "n0_discovery": self.config.network.enable_n0_discovery,
            "subscribed_topics": self.config.gossip.topics,
            "node_address": {
                "relay_url": addr.relay_url.map(|u| u.to_string()),
                "direct_addresses": addr.direct_addresses.iter().map(|a| a.to_string()).collect::<Vec<_>>()
            }
        }))
    }
}
use anyhow::Result;
use dashmap::DashMap;
use futures::StreamExt;
use serde::{Deserialize, Serialize};
use std::collections::HashMap;
use std::sync::Arc;
use std::time::{SystemTime, UNIX_EPOCH};
use tokio::time::{interval, Duration};
use tracing::{debug, info, warn, error};
use uuid::Uuid;

use crate::iroh_client::IrohClient;
use crate::error::BridgeError;

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct ServiceAnnouncement {
    pub service_id: String,
    pub node_id: String,
    pub service_name: String,
    pub capabilities: Vec<String>,
    pub endpoint_info: ServiceEndpoint,
    pub timestamp: u64,
    pub ttl: u64, // Time to live in seconds
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct ServiceEndpoint {
    pub node_addr: String,
    pub http_endpoint: Option<String>,
    pub health_check_path: Option<String>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct ServiceQuery {
    pub service_name: String,
    pub required_capabilities: Vec<String>,
    pub max_results: Option<usize>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct ServiceQueryResponse {
    pub services: Vec<ServiceAnnouncement>,
    pub query_id: String,
    pub timestamp: u64,
}

#[derive(Clone)]
pub struct ServiceRegistry {
    iroh_client: IrohClient,
    services: Arc<DashMap<String, ServiceAnnouncement>>,
    my_services: Arc<DashMap<String, ServiceAnnouncement>>,
}

impl ServiceRegistry {
    pub async fn new(iroh_client: IrohClient) -> Result<Self> {
        let registry = ServiceRegistry {
            iroh_client,
            services: Arc::new(DashMap::new()),
            my_services: Arc::new(DashMap::new()),
        };

        // Start background tasks
        registry.start_discovery_listener().await?;
        registry.start_cleanup_task();
        registry.start_announcement_task();

        Ok(registry)
    }

    /// Register a service with the network
    pub async fn register_service(&self, service_name: String, capabilities: Vec<String>) -> Result<()> {
        let service_id = Uuid::new_v4().to_string();
        let node_addr = self.iroh_client.get_node_address().await?;
        
        let announcement = ServiceAnnouncement {
            service_id: service_id.clone(),
            node_id: self.iroh_client.node_id().to_string(),
            service_name: service_name.clone(),
            capabilities,
            endpoint_info: ServiceEndpoint {
                node_addr: node_addr.to_string(),
                http_endpoint: Some("http://localhost:8080".to_string()), // TODO: Make configurable
                health_check_path: Some("/api/v1/health".to_string()),
            },
            timestamp: current_timestamp(),
            ttl: 300, // 5 minutes TTL
        };

        // Store locally
        self.my_services.insert(service_id.clone(), announcement.clone());

        // Announce to network
        self.announce_service(&announcement).await?;

        info!("Registered service '{}' with ID: {}", service_name, service_id);
        Ok(())
    }

    /// Discover services by name and capabilities
    pub async fn discover_services(&self, query: ServiceQuery) -> Vec<ServiceAnnouncement> {
        let mut matching_services = Vec::new();

        // Search local cache first
        for service in self.services.iter() {
            let announcement = service.value();
            
            if self.matches_query(&announcement, &query) {
                matching_services.push(announcement.clone());
            }
        }

        // If we don't have enough results, query the network
        if matching_services.len() < query.max_results.unwrap_or(10) {
            if let Err(e) = self.query_network_services(&query).await {
                warn!("Failed to query network for services: {}", e);
            }
            
            // Wait a bit for responses and check again
            tokio::time::sleep(Duration::from_millis(500)).await;
            
            for service in self.services.iter() {
                let announcement = service.value();
                
                if self.matches_query(&announcement, &query) &&
                   !matching_services.iter().any(|s| s.service_id == announcement.service_id) {
                    matching_services.push(announcement.clone());
                }
            }
        }

        // Sort by timestamp (newest first) and limit results
        matching_services.sort_by(|a, b| b.timestamp.cmp(&a.timestamp));
        
        if let Some(max) = query.max_results {
            matching_services.truncate(max);
        }

        debug!("Found {} services matching query: {:?}", matching_services.len(), query);
        matching_services
    }

    /// Get information about a specific service
    pub fn get_service(&self, service_id: &str) -> Option<ServiceAnnouncement> {
        self.services.get(service_id).map(|s| s.clone())
    }

    /// Get all registered services
    pub fn get_all_services(&self) -> Vec<ServiceAnnouncement> {
        self.services.iter().map(|s| s.value().clone()).collect()
    }

    /// Get services registered by this node
    pub fn get_my_services(&self) -> Vec<ServiceAnnouncement> {
        self.my_services.iter().map(|s| s.value().clone()).collect()
    }

    /// Remove a service from the registry
    pub fn remove_service(&self, service_id: &str) -> Option<ServiceAnnouncement> {
        self.services.remove(service_id).map(|(_, s)| s)
    }

    /// Announce a service to the network
    async fn announce_service(&self, announcement: &ServiceAnnouncement) -> Result<()> {
        let message = serde_json::json!({
            "type": "service_announcement",
            "data": announcement
        });

        self.iroh_client.broadcast_to_topic("service-discovery", message).await?;
        debug!("Announced service: {}", announcement.service_name);
        Ok(())
    }

    /// Query the network for services
    async fn query_network_services(&self, query: &ServiceQuery) -> Result<()> {
        let query_id = Uuid::new_v4().to_string();
        let message = serde_json::json!({
            "type": "service_query",
            "query_id": query_id,
            "data": query
        });

        self.iroh_client.broadcast_to_topic("service-discovery", message).await?;
        debug!("Sent service query: {:?}", query);
        Ok(())
    }

    /// Check if a service matches a query
    fn matches_query(&self, announcement: &ServiceAnnouncement, query: &ServiceQuery) -> bool {
        // Check service name
        if announcement.service_name != query.service_name {
            return false;
        }

        // Check if service is still valid (TTL)
        let now = current_timestamp();
        if now > announcement.timestamp + announcement.ttl {
            return false;
        }

        // Check required capabilities
        for required_cap in &query.required_capabilities {
            if !announcement.capabilities.contains(required_cap) {
                return false;
            }
        }

        true
    }

    /// Start listening for service discovery messages
    async fn start_discovery_listener(&self) -> Result<()> {
        let gossip = self.iroh_client.gossip();
        let services = Arc::clone(&self.services);
        let my_services = Arc::clone(&self.my_services);
        let iroh_client = self.iroh_client.clone();

        tokio::spawn(async move {
            // Subscribe to service discovery topic
            let topic_id = iroh_gossip::proto::TopicId::from_bytes(b"service-discovery");
            
            let topic_handle = match gossip.subscribe(topic_id, vec![]) {
                Ok(handle) => handle,
                Err(e) => {
                    error!("Failed to subscribe to service-discovery topic: {}", e);
                    return;
                }
            };

            let (_sender, mut receiver) = topic_handle.split();

            // Listen for messages
            while let Ok(Some(event)) = receiver.try_next().await {
                if let iroh_gossip::net::Event::Gossip(iroh_gossip::net::GossipEvent::Received(msg)) = event {
                    if let Err(e) = Self::handle_discovery_message(&msg.content, &services, &my_services, &iroh_client).await {
                        debug!("Error handling discovery message: {}", e);
                    }
                }
            }
        });

        Ok(())
    }

    /// Handle incoming service discovery messages
    async fn handle_discovery_message(
        content: &[u8],
        services: &DashMap<String, ServiceAnnouncement>,
        my_services: &DashMap<String, ServiceAnnouncement>,
        iroh_client: &IrohClient,
    ) -> Result<()> {
        let message: serde_json::Value = serde_json::from_slice(content)?;
        
        match message.get("type").and_then(|t| t.as_str()) {
            Some("service_announcement") => {
                if let Some(data) = message.get("data") {
                    let announcement: ServiceAnnouncement = serde_json::from_value(data.clone())?;
                    
                    // Don't add our own announcements to the services list
                    if announcement.node_id != iroh_client.node_id() {
                        services.insert(announcement.service_id.clone(), announcement);
                        debug!("Added service announcement: {}", announcement.service_name);
                    }
                }
            }
            Some("service_query") => {
                if let (Some(query_id), Some(data)) = (
                    message.get("query_id").and_then(|q| q.as_str()),
                    message.get("data")
                ) {
                    let query: ServiceQuery = serde_json::from_value(data.clone())?;
                    
                    // Check if any of our services match
                    let mut matching_services = Vec::new();
                    for service in my_services.iter() {
                        let announcement = service.value();
                        if Self::service_matches_query(announcement, &query) {
                            matching_services.push(announcement.clone());
                        }
                    }

                    // Send response if we have matching services
                    if !matching_services.is_empty() {
                        let response = ServiceQueryResponse {
                            services: matching_services,
                            query_id: query_id.to_string(),
                            timestamp: current_timestamp(),
                        };

                        let response_message = serde_json::json!({
                            "type": "service_query_response",
                            "data": response
                        });

                        if let Err(e) = iroh_client.broadcast_to_topic("service-discovery", response_message).await {
                            debug!("Failed to send service query response: {}", e);
                        }
                    }
                }
            }
            Some("service_query_response") => {
                if let Some(data) = message.get("data") {
                    let response: ServiceQueryResponse = serde_json::from_value(data.clone())?;
                    
                    // Add discovered services to our cache
                    for announcement in response.services {
                        services.insert(announcement.service_id.clone(), announcement);
                    }
                }
            }
            _ => {
                debug!("Unknown service discovery message type: {:?}", message.get("type"));
            }
        }

        Ok(())
    }

    /// Helper method for service matching
    fn service_matches_query(announcement: &ServiceAnnouncement, query: &ServiceQuery) -> bool {
        if announcement.service_name != query.service_name {
            return false;
        }

        for required_cap in &query.required_capabilities {
            if !announcement.capabilities.contains(required_cap) {
                return false;
            }
        }

        true
    }

    /// Start cleanup task to remove expired services
    fn start_cleanup_task(&self) {
        let services = Arc::clone(&self.services);
        
        tokio::spawn(async move {
            let mut cleanup_interval = interval(Duration::from_secs(60)); // Clean up every minute
            
            loop {
                cleanup_interval.tick().await;
                
                let now = current_timestamp();
                let mut to_remove = Vec::new();
                
                // Find expired services
                for service in services.iter() {
                    let announcement = service.value();
                    if now > announcement.timestamp + announcement.ttl {
                        to_remove.push(announcement.service_id.clone());
                    }
                }
                
                // Remove expired services
                for service_id in to_remove {
                    if let Some((_, announcement)) = services.remove(&service_id) {
                        debug!("Removed expired service: {}", announcement.service_name);
                    }
                }
            }
        });
    }

    /// Start periodic announcement task for our services
    fn start_announcement_task(&self) {
        let my_services = Arc::clone(&self.my_services);
        let iroh_client = self.iroh_client.clone();
        
        tokio::spawn(async move {
            let mut announce_interval = interval(Duration::from_secs(120)); // Announce every 2 minutes
            
            loop {
                announce_interval.tick().await;
                
                // Re-announce all our services
                for service in my_services.iter() {
                    let mut announcement = service.value().clone();
                    announcement.timestamp = current_timestamp(); // Update timestamp
                    
                    if let Err(e) = iroh_client.broadcast_to_topic("service-discovery", serde_json::json!({
                        "type": "service_announcement",
                        "data": announcement
                    })).await {
                        debug!("Failed to re-announce service {}: {}", announcement.service_name, e);
                    }
                }
            }
        });
    }
}

fn current_timestamp() -> u64 {
    SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .unwrap()
        .as_secs()
}
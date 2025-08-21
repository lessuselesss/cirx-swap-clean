use serde::{Deserialize, Serialize};
use std::path::Path;
use anyhow::Result;

#[derive(Debug, Clone, Deserialize, Serialize)]
pub struct Config {
    pub network: NetworkConfig,
    pub relay: RelayConfig,
    pub gossip: GossipConfig,
    pub http_bridge: HttpBridgeConfig,
    pub backend: BackendConfig,
}

#[derive(Debug, Clone, Deserialize, Serialize)]
pub struct NetworkConfig {
    /// Enable local network discovery
    pub enable_local_discovery: bool,
    /// Enable n0 discovery service
    pub enable_n0_discovery: bool,
    /// Custom relay URLs
    pub custom_relays: Vec<String>,
    /// Prefer local connections when available
    pub prefer_local: bool,
}

#[derive(Debug, Clone, Deserialize, Serialize)]
pub struct RelayConfig {
    /// Enable relay functionality
    pub enable: bool,
    /// STUN port for relay
    pub stun_port: u16,
    /// Custom relay URL if running own relay
    pub custom_url: Option<String>,
}

#[derive(Debug, Clone, Deserialize, Serialize)]
pub struct GossipConfig {
    /// Enable gossip protocol
    pub enable: bool,
    /// Topics to subscribe to
    pub topics: Vec<String>,
    /// Maximum message size for gossip
    pub max_message_size: usize,
}

#[derive(Debug, Clone, Deserialize, Serialize)]
pub struct HttpBridgeConfig {
    /// HTTP server listen address
    pub listen_address: String,
    /// Enable CORS
    pub enable_cors: bool,
    /// Request timeout in seconds
    pub request_timeout: u64,
    /// Maximum concurrent requests
    pub max_concurrent_requests: usize,
}

#[derive(Debug, Clone, Deserialize, Serialize)]
pub struct BackendConfig {
    /// Backend service URL (for local communication)
    pub service_url: Option<String>,
    /// Health check endpoint
    pub health_endpoint: String,
    /// Health check interval in seconds
    pub health_check_interval: u64,
}

impl Default for Config {
    fn default() -> Self {
        Config {
            network: NetworkConfig {
                enable_local_discovery: true,
                enable_n0_discovery: true,
                custom_relays: vec![],
                prefer_local: true,
            },
            relay: RelayConfig {
                enable: true,
                stun_port: 3478,
                custom_url: None,
            },
            gossip: GossipConfig {
                enable: true,
                topics: vec![
                    "service-discovery".to_string(),
                    "transaction-updates".to_string(),
                    "order-book-sync".to_string(),
                ],
                max_message_size: 1024 * 1024, // 1MB
            },
            http_bridge: HttpBridgeConfig {
                listen_address: "0.0.0.0:9090".to_string(),
                enable_cors: true,
                request_timeout: 30,
                max_concurrent_requests: 100,
            },
            backend: BackendConfig {
                service_url: Some("http://localhost:8080".to_string()),
                health_endpoint: "/api/v1/health".to_string(),
                health_check_interval: 30,
            },
        }
    }
}

impl Config {
    /// Load configuration from TOML file
    pub fn load<P: AsRef<Path>>(path: P) -> Result<Self> {
        let content = std::fs::read_to_string(path)?;
        let config: Config = toml::de::from_str(&content)?;
        Ok(config)
    }

    /// Save configuration to TOML file
    pub fn save<P: AsRef<Path>>(&self, path: P) -> Result<()> {
        let content = toml::ser::to_string_pretty(self)?;
        std::fs::write(path, content)?;
        Ok(())
    }

    /// Validate configuration
    pub fn validate(&self) -> Result<()> {
        if self.http_bridge.request_timeout == 0 {
            anyhow::bail!("HTTP bridge request timeout must be greater than 0");
        }

        if self.http_bridge.max_concurrent_requests == 0 {
            anyhow::bail!("Max concurrent requests must be greater than 0");
        }

        if self.backend.health_check_interval == 0 {
            anyhow::bail!("Backend health check interval must be greater than 0");
        }

        Ok(())
    }
}
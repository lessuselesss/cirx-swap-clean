use thiserror::Error;

#[derive(Error, Debug)]
pub enum BridgeError {
    #[error("Configuration error: {0}")]
    ConfigError(String),

    #[error("Network error: {0}")]
    NetworkError(String),

    #[error("Connection failed: {0}")]
    ConnectionFailed(String),

    #[error("Invalid node ID: {0}")]
    InvalidNodeId(String),

    #[error("Request failed: {0}")]
    RequestFailed(String),

    #[error("Response parsing error: {0}")]
    ResponseParseError(String),

    #[error("Gossip protocol error: {0}")]
    GossipError(String),

    #[error("Service registry error: {0}")]
    ServiceRegistryError(String),

    #[error("HTTP bridge error: {0}")]
    HttpBridgeError(String),

    #[error("Backend communication error: {0}")]
    BackendError(String),

    #[error("Serialization error: {0}")]
    SerializationError(#[from] serde_json::Error),

    #[error("IO error: {0}")]
    IoError(#[from] std::io::Error),

    #[error("IROH error: {0}")]
    IrohError(String),

    #[error("Timeout error: {0}")]
    TimeoutError(String),
}

impl From<iroh::endpoint::ConnectError> for BridgeError {
    fn from(err: iroh::endpoint::ConnectError) -> Self {
        BridgeError::ConnectionFailed(err.to_string())
    }
}

impl From<anyhow::Error> for BridgeError {
    fn from(err: anyhow::Error) -> Self {
        BridgeError::IrohError(err.to_string())
    }
}
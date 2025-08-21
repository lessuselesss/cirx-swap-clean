use anyhow::Result;
use axum::{
    extract::{Path, Query, State},
    http::{HeaderMap, StatusCode},
    response::Json,
    routing::{get, post},
    Router,
};
use serde::{Deserialize, Serialize};
use std::collections::HashMap;
use std::sync::Arc;
use tokio::net::TcpListener;
use tower_http::cors::{Any, CorsLayer};
use tracing::{debug, info, error};

use crate::iroh_client::IrohClient;
use crate::service_registry::{ServiceRegistry, ServiceQuery};
use crate::error::BridgeError;

#[derive(Clone)]
pub struct HttpBridge {
    listen_address: String,
    iroh_client: IrohClient,
    service_registry: ServiceRegistry,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct ServiceDiscoveryRequest {
    pub service_name: String,
    pub capabilities: Option<Vec<String>>,
    pub max_results: Option<usize>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct NodeRequest {
    pub node_id: String,
    pub payload: serde_json::Value,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct BroadcastRequest {
    pub topic: String,
    pub message: serde_json::Value,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct ApiResponse<T> {
    pub success: bool,
    pub data: Option<T>,
    pub error: Option<String>,
    pub timestamp: u64,
}

impl<T> ApiResponse<T> {
    pub fn success(data: T) -> Self {
        Self {
            success: true,
            data: Some(data),
            error: None,
            timestamp: std::time::SystemTime::now()
                .duration_since(std::time::UNIX_EPOCH)
                .unwrap()
                .as_secs(),
        }
    }

    pub fn error(message: String) -> Self {
        Self {
            success: false,
            data: None,
            error: Some(message),
            timestamp: std::time::SystemTime::now()
                .duration_since(std::time::UNIX_EPOCH)
                .unwrap()
                .as_secs(),
        }
    }
}

impl HttpBridge {
    pub fn new(
        listen_address: String,
        iroh_client: IrohClient,
        service_registry: ServiceRegistry,
    ) -> Self {
        Self {
            listen_address,
            iroh_client,
            service_registry,
        }
    }

    pub async fn run(self) -> Result<()> {
        let app = self.create_router().await;
        
        let listener = TcpListener::bind(&self.listen_address).await
            .map_err(|e| BridgeError::HttpBridgeError(format!("Failed to bind to {}: {}", self.listen_address, e)))?;
        
        info!("HTTP bridge listening on {}", self.listen_address);
        
        axum::serve(listener, app).await
            .map_err(|e| BridgeError::HttpBridgeError(format!("Server error: {}", e)))?;

        Ok(())
    }

    async fn create_router(self) -> Router {
        let bridge_state = Arc::new(self);

        Router::new()
            // Health check
            .route("/health", get(health_check))
            
            // Node information
            .route("/node/info", get(node_info))
            .route("/node/address", get(node_address))
            .route("/node/peers", get(peer_info))
            
            // Service discovery
            .route("/services/discover", post(discover_services))
            .route("/services/list", get(list_services))
            .route("/services/my", get(my_services))
            
            // Communication
            .route("/send/:node_id", post(send_to_node))
            .route("/broadcast", post(broadcast_message))
            
            // Backend proxy (for PHP integration)
            .route("/proxy/*path", post(proxy_to_backend))
            .route("/proxy/*path", get(proxy_to_backend))
            
            // WebSocket endpoint for real-time updates (future enhancement)
            // .route("/ws", get(websocket_handler))
            
            .layer(CorsLayer::new().allow_origin(Any).allow_methods(Any).allow_headers(Any))
            .with_state(bridge_state)
    }
}

// Handler functions
async fn health_check(
    State(bridge): State<Arc<HttpBridge>>,
) -> Result<Json<ApiResponse<serde_json::Value>>, StatusCode> {
    match bridge.iroh_client.health_check().await {
        Ok(health_data) => Ok(Json(ApiResponse::success(health_data))),
        Err(e) => {
            error!("Health check failed: {}", e);
            Err(StatusCode::INTERNAL_SERVER_ERROR)
        }
    }
}

async fn node_info(
    State(bridge): State<Arc<HttpBridge>>,
) -> Json<ApiResponse<serde_json::Value>> {
    let info = serde_json::json!({
        "node_id": bridge.iroh_client.node_id(),
        "bridge_version": env!("CARGO_PKG_VERSION"),
        "features": {
            "service_discovery": true,
            "gossip": true,
            "direct_messaging": true,
            "backend_proxy": true
        }
    });
    
    Json(ApiResponse::success(info))
}

async fn node_address(
    State(bridge): State<Arc<HttpBridge>>,
) -> Result<Json<ApiResponse<serde_json::Value>>, StatusCode> {
    match bridge.iroh_client.get_node_address().await {
        Ok(addr) => {
            let addr_info = serde_json::json!({
                "node_id": addr.node_id.to_string(),
                "relay_url": addr.relay_url.map(|u| u.to_string()),
                "direct_addresses": addr.direct_addresses.iter().map(|a| a.to_string()).collect::<Vec<_>>()
            });
            Ok(Json(ApiResponse::success(addr_info)))
        }
        Err(e) => {
            error!("Failed to get node address: {}", e);
            Err(StatusCode::INTERNAL_SERVER_ERROR)
        }
    }
}

async fn peer_info(
    State(bridge): State<Arc<HttpBridge>>,
) -> Result<Json<ApiResponse<serde_json::Value>>, StatusCode> {
    match bridge.iroh_client.get_peer_info().await {
        Ok(peers) => Ok(Json(ApiResponse::success(serde_json::Value::Object(
            peers.into_iter().map(|(k, v)| (k, v)).collect()
        )))),
        Err(e) => {
            error!("Failed to get peer info: {}", e);
            Err(StatusCode::INTERNAL_SERVER_ERROR)
        }
    }
}

async fn discover_services(
    State(bridge): State<Arc<HttpBridge>>,
    Json(request): Json<ServiceDiscoveryRequest>,
) -> Json<ApiResponse<Vec<crate::service_registry::ServiceAnnouncement>>> {
    let query = ServiceQuery {
        service_name: request.service_name,
        required_capabilities: request.capabilities.unwrap_or_default(),
        max_results: request.max_results,
    };

    let services = bridge.service_registry.discover_services(query).await;
    Json(ApiResponse::success(services))
}

async fn list_services(
    State(bridge): State<Arc<HttpBridge>>,
) -> Json<ApiResponse<Vec<crate::service_registry::ServiceAnnouncement>>> {
    let services = bridge.service_registry.get_all_services();
    Json(ApiResponse::success(services))
}

async fn my_services(
    State(bridge): State<Arc<HttpBridge>>,
) -> Json<ApiResponse<Vec<crate::service_registry::ServiceAnnouncement>>> {
    let services = bridge.service_registry.get_my_services();
    Json(ApiResponse::success(services))
}

async fn send_to_node(
    State(bridge): State<Arc<HttpBridge>>,
    Path(node_id): Path<String>,
    Json(payload): Json<serde_json::Value>,
) -> Result<Json<ApiResponse<serde_json::Value>>, StatusCode> {
    match bridge.iroh_client.send_request_to_node(&node_id, payload).await {
        Ok(response) => Ok(Json(ApiResponse::success(response))),
        Err(e) => {
            error!("Failed to send request to node {}: {}", node_id, e);
            Ok(Json(ApiResponse::error(format!("Failed to send request: {}", e))))
        }
    }
}

async fn broadcast_message(
    State(bridge): State<Arc<HttpBridge>>,
    Json(request): Json<BroadcastRequest>,
) -> Result<Json<ApiResponse<String>>, StatusCode> {
    match bridge.iroh_client.broadcast_to_topic(&request.topic, request.message).await {
        Ok(_) => Ok(Json(ApiResponse::success("Message broadcast successfully".to_string()))),
        Err(e) => {
            error!("Failed to broadcast message: {}", e);
            Ok(Json(ApiResponse::error(format!("Failed to broadcast: {}", e))))
        }
    }
}

async fn proxy_to_backend(
    State(bridge): State<Arc<HttpBridge>>,
    headers: HeaderMap,
    Path(path): Path<String>,
    Query(params): Query<HashMap<String, String>>,
    body: Option<axum::body::Bytes>,
) -> Result<Json<ApiResponse<serde_json::Value>>, StatusCode> {
    // For now, this is a placeholder that forwards requests to the local backend
    // In a full implementation, this would:
    // 1. Discover backend services
    // 2. Load balance between them
    // 3. Forward the HTTP request
    // 4. Return the response

    debug!("Proxying request to path: /{}", path);
    
    // Mock response for demonstration
    let response = serde_json::json!({
        "proxy_status": "not_implemented",
        "requested_path": format!("/{}", path),
        "params": params,
        "headers_count": headers.len(),
        "body_size": body.as_ref().map(|b| b.len()).unwrap_or(0)
    });

    Ok(Json(ApiResponse::success(response)))
}
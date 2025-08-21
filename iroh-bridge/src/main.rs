use anyhow::Result;
use clap::Parser;
use std::path::PathBuf;
use tracing::{info, warn};

mod config;
mod service_registry;
mod http_bridge;
mod iroh_client;
mod error;

use config::Config;
use service_registry::ServiceRegistry;
use http_bridge::HttpBridge;
use iroh_client::IrohClient;

#[derive(Parser, Debug)]
#[command(name = "iroh-bridge")]
#[command(about = "IROH networking bridge for CIRX swap platform")]
struct Args {
    /// Configuration file path
    #[arg(short, long, default_value = "config/iroh.toml")]
    config: PathBuf,

    /// Enable verbose logging
    #[arg(short, long)]
    verbose: bool,

    /// Listen address for HTTP bridge
    #[arg(long, default_value = "0.0.0.0:9090")]
    listen: String,

    /// Service name to register
    #[arg(long, default_value = "cirx-swap-backend")]
    service_name: String,

    /// Service capabilities (comma-separated)
    #[arg(long, default_value = "swap-execution,payment-verification,transaction-history")]
    capabilities: String,
}

#[tokio::main]
async fn main() -> Result<()> {
    let args = Args::parse();

    // Initialize logging
    let log_level = if args.verbose { "debug" } else { "info" };
    tracing_subscriber::fmt()
        .with_env_filter(format!("iroh_bridge={},iroh={}", log_level, log_level))
        .init();

    info!("Starting IROH bridge for CIRX swap platform");

    // Load configuration
    let config = Config::load(&args.config).unwrap_or_else(|e| {
        warn!("Failed to load config from {:?}: {}. Using defaults.", args.config, e);
        Config::default()
    });

    info!("Configuration loaded: {:?}", config);

    // Initialize IROH client
    let iroh_client = IrohClient::new(config.clone()).await?;
    info!("IROH client initialized with Node ID: {}", iroh_client.node_id());

    // Initialize service registry
    let service_registry = ServiceRegistry::new(iroh_client.clone()).await?;
    
    // Register this service
    let capabilities: Vec<String> = args.capabilities
        .split(',')
        .map(|s| s.trim().to_string())
        .collect();
    
    service_registry.register_service(args.service_name.clone(), capabilities).await?;
    info!("Service '{}' registered with IROH network", args.service_name);

    // Start HTTP bridge server
    let http_bridge = HttpBridge::new(
        args.listen.clone(),
        iroh_client.clone(),
        service_registry.clone(),
    );

    info!("Starting HTTP bridge on {}", args.listen);

    // Run the bridge server
    if let Err(e) = http_bridge.run().await {
        tracing::error!("HTTP bridge error: {}", e);
        return Err(e);
    }

    Ok(())
}
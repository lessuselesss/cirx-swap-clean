# Multi-stage build for IROH bridge
FROM rust:1.75 as builder

WORKDIR /app

# Copy dependency files first for better caching
COPY Cargo.toml Cargo.lock ./

# Create a dummy main.rs to build dependencies
RUN mkdir src && echo "fn main() {}" > src/main.rs
RUN cargo build --release && rm src/main.rs

# Copy source code and build the actual binary
COPY src ./src
COPY config ./config

# Build the release binary
RUN cargo build --release

# Runtime stage - use minimal Debian image
FROM debian:bookworm-slim

# Install necessary runtime dependencies
RUN apt-get update && apt-get install -y \
    ca-certificates \
    libssl3 \
    && rm -rf /var/lib/apt/lists/*

# Create app user for security
RUN useradd -r -s /bin/false -m -d /app iroh

WORKDIR /app

# Copy binary from builder stage
COPY --from=builder /app/target/release/iroh-bridge /usr/local/bin/iroh-bridge

# Copy configuration directory
COPY --from=builder /app/config ./config

# Set ownership
RUN chown -R iroh:iroh /app

# Switch to app user
USER iroh

# Expose ports
EXPOSE 9090/tcp
EXPOSE 3478/udp

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:9090/health || exit 1

# Default command
ENTRYPOINT ["/usr/local/bin/iroh-bridge"]
CMD ["--config", "/app/config/iroh.toml", "--listen", "0.0.0.0:9090"]
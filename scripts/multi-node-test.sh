#!/bin/bash

# Multi-Node IROH Testing Script
# This script demonstrates how to run multiple IROH bridge instances
# that would discover and connect to each other

echo "🌐 Starting Multi-Node IROH Network Test"
echo "========================================"

# Function to start a node in background
start_node() {
    local port=$1
    local name=$2
    
    echo "🚀 Starting $name on port $port..."
    
    # Modify the enhanced bridge to use different port
    sed "s/const PORT = 9090/const PORT = $port/g" scripts/enhanced-iroh-bridge.js > "/tmp/iroh-bridge-$port.js"
    
    # Start the node
    node "/tmp/iroh-bridge-$port.js" &
    local pid=$!
    echo "$pid" > "/tmp/iroh-node-$port.pid"
    
    echo "✅ $name started (PID: $pid)"
    sleep 2
}

# Function to test node connectivity
test_connectivity() {
    local port=$1
    local name=$2
    
    echo "🔍 Testing $name connectivity..."
    
    local health=$(curl -s "http://localhost:$port/health" | jq -r '.success')
    if [[ "$health" == "true" ]]; then
        echo "✅ $name: Healthy"
        
        # Get node info
        local node_info=$(curl -s "http://localhost:$port/node/info")
        local node_id=$(echo "$node_info" | jq -r '.data.node_id')
        echo "   Node ID: ${node_id:0:20}..."
        
        return 0
    else
        echo "❌ $name: Unhealthy"
        return 1
    fi
}

# Function to simulate inter-node communication
test_inter_node_communication() {
    echo ""
    echo "📡 Testing Inter-Node Communication"
    echo "--------------------------------"
    
    # Send a broadcast from node 1
    echo "Sending broadcast from Node 1..."
    curl -s -X POST "http://localhost:9090/broadcast" \
        -H "Content-Type: application/json" \
        -d '{
            "topic": "multi-node-test",
            "message": {
                "type": "INTER_NODE_TEST",
                "from": "node-1",
                "timestamp": '$(date +%s)'
            }
        }' > /dev/null
    
    echo "✅ Broadcast sent from Node 1"
    
    # Check if other nodes would receive it (in real IROH, they would)
    echo "💡 In real IROH deployment, Node 2 and Node 3 would automatically receive this message"
    echo "💡 Messages would be synchronized across all peers in the network"
}

# Function to cleanup
cleanup() {
    echo ""
    echo "🧹 Cleaning up nodes..."
    
    for port in 9090 9092 9094; do
        if [[ -f "/tmp/iroh-node-$port.pid" ]]; then
            local pid=$(cat "/tmp/iroh-node-$port.pid")
            if kill -0 "$pid" 2>/dev/null; then
                kill "$pid"
                echo "🛑 Stopped node on port $port"
            fi
            rm -f "/tmp/iroh-node-$port.pid"
            rm -f "/tmp/iroh-bridge-$port.js"
        fi
    done
}

# Main execution
main() {
    # Setup cleanup on exit
    trap cleanup EXIT INT TERM
    
    echo "Starting 3 IROH nodes to simulate distributed network..."
    echo ""
    
    # Start multiple nodes (in real deployment, these would be on different servers)
    start_node 9090 "Node 1 (Primary)"
    start_node 9092 "Node 2 (EU)"
    start_node 9094 "Node 3 (APAC)"
    
    echo ""
    echo "🔍 Testing Node Health"
    echo "---------------------"
    
    # Test each node
    test_connectivity 9090 "Node 1"
    test_connectivity 9092 "Node 2"  
    test_connectivity 9094 "Node 3"
    
    # Test inter-node communication
    test_inter_node_communication
    
    echo ""
    echo "📊 Network Topology"
    echo "------------------"
    echo "Node 1 (Primary): http://localhost:9090"
    echo "Node 2 (EU):      http://localhost:9092"  
    echo "Node 3 (APAC):    http://localhost:9094"
    echo ""
    echo "💡 In a real IROH deployment:"
    echo "   - Each node would automatically discover the others"
    echo "   - Messages broadcast to one node reach all nodes"
    echo "   - Services can be discovered across the entire network"
    echo "   - Load balancing happens automatically"
    echo "   - Network is resilient to individual node failures"
    
    echo ""
    echo "Press Ctrl+C to stop all nodes..."
    
    # Keep running until interrupted
    while true; do
        sleep 10
        
        # Show some activity
        echo "📈 Network active - $(date)"
        
        # In real deployment, you'd see:
        # - Cross-node transaction synchronization
        # - Automatic failover
        # - Load distribution
        # - Real-time peer discovery
    done
}

# Run the test
main
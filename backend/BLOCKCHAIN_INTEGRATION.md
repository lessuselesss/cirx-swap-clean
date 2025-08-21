# CIRX OTC Backend - Blockchain Integration

## Overview

This document outlines the blockchain API client integration that replaces the previous mock implementations in the CIRX OTC Backend. The integration provides production-ready blockchain verification and transaction capabilities while maintaining backward compatibility with existing tests.

## Architecture

### Blockchain Client Layer

```
┌─────────────────────────────────────────────────────────┐
│                 Application Layer                        │
├─────────────────────────────────────────────────────────┤
│  PaymentVerificationService  │  CirxTransferService     │
├─────────────────────────────────────────────────────────┤
│              BlockchainClientFactory                    │
├─────────────────────────────────────────────────────────┤
│  EthereumBlockchainClient   │   CirxBlockchainClient    │
├─────────────────────────────────────────────────────────┤
│            AbstractBlockchainClient                     │
├─────────────────────────────────────────────────────────┤
│        BlockchainClientInterface (Contract)             │
├─────────────────────────────────────────────────────────┤
│              HTTP/JSON-RPC Transport                    │
│         (Guzzle HTTP Client + Retry Logic)              │
└─────────────────────────────────────────────────────────┘
```

## Components

### 1. BlockchainClientInterface

**Location**: `/src/Blockchain/BlockchainClientInterface.php`

Defines the standard interface for all blockchain clients:
- Transaction retrieval and verification
- Balance checking
- Transaction broadcasting
- Gas estimation
- Health monitoring
- Network identification

### 2. AbstractBlockchainClient

**Location**: `/src/Blockchain/AbstractBlockchainClient.php`

Provides common functionality:
- JSON-RPC communication with retry logic
- Exponential backoff for failed requests
- Backup RPC URL support
- Logging integration
- Utility methods for hex/decimal conversion

### 3. EthereumBlockchainClient

**Location**: `/src/Blockchain/EthereumBlockchainClient.php`

Handles EVM-compatible blockchains (Ethereum, Polygon, BSC):
- Native ETH transactions
- ERC-20 token operations
- Transaction confirmation tracking
- Log parsing for token transfers
- Multi-network support

**Supported Networks**:
- Ethereum Mainnet (Chain ID: 1)
- Ethereum Goerli Testnet (Chain ID: 5)
- Ethereum Sepolia Testnet (Chain ID: 11155111)
- Polygon Mainnet (Chain ID: 137)
- Binance Smart Chain (Chain ID: 56)

### 4. CirxBlockchainClient

**Location**: `/src/Blockchain/CirxBlockchainClient.php`

Specialized client for CIRX token operations:
- CIRX token transfers (native or ERC-20 style)
- Balance verification
- Transaction confirmation
- Support for both native CIRX and contract-based CIRX

### 5. BlockchainClientFactory

**Location**: `/src/Blockchain/BlockchainClientFactory.php`

Factory class for creating and managing blockchain clients:
- Environment-based configuration
- Client caching
- Health check coordination
- Network routing

### 6. BlockchainException

**Location**: `/src/Blockchain/Exceptions/BlockchainException.php`

Specialized exception handling:
- Connection failures
- Transaction not found
- Invalid responses
- Insufficient balance
- Transaction failures

## Configuration

### Environment Variables

Update your `.env` file with blockchain configuration:

```env
# Ethereum Blockchain Configuration
ETHEREUM_RPC_URL=https://mainnet.infura.io/v3/YOUR_INFURA_PROJECT_ID
ETHEREUM_RPC_URL_BACKUP=https://eth-mainnet.alchemyapi.io/v2/YOUR_ALCHEMY_KEY
ETHEREUM_CHAIN_ID=1
ETHEREUM_PRIVATE_KEY=your_ethereum_wallet_private_key_here

# Polygon Configuration  
POLYGON_RPC_URL=https://polygon-mainnet.infura.io/v3/YOUR_INFURA_PROJECT_ID
POLYGON_RPC_URL_BACKUP=https://polygon-rpc.com
POLYGON_CHAIN_ID=137

# CIRX Blockchain Configuration
CIRX_RPC_URL=https://nag.circularlabs.io/NAG_Mainnet.php?cep=
CIRX_RPC_URL_BACKUP=https://nag.circularlabs.io/NAG.php?cep=
CIRX_WALLET_ADDRESS=0x1234567890123456789012345678901234567890
CIRX_WALLET_PRIVATE_KEY=your_cirx_wallet_private_key_here
CIRX_CONTRACT_ADDRESS=0x1234567890123456789012345678901234567890

# Token Configuration
USDC_CONTRACT_ADDRESS=0xA0b86a33E6441e8532B8aE1F8A0b86a33E644122
USDT_CONTRACT_ADDRESS=0xdAC17F958D2ee523a2206206994597C13D831ec7
```

### Security Considerations

**Private Keys**: 
- Store private keys securely using environment variables
- Never commit private keys to version control
- Use different keys for development, staging, and production
- Consider using hardware security modules (HSM) for production

**RPC Endpoints**:
- Use authenticated endpoints (Infura, Alchemy) for reliability
- Configure backup RPC URLs for redundancy
- Monitor API usage and rate limits
- Implement proper error handling for endpoint failures

**Circular Protocol NAG Endpoints**:
- **Production**: `https://nag.circularlabs.io/NAG_Mainnet.php?cep=`
- **Development/Testing**: `https://nag.circularlabs.io/NAG.php?cep=`
- No API keys required for NAG endpoints
- CEP commands are appended to base URL (e.g., `Circular_GetBlockchains_`)
- All requests use POST with JSON payload

## Integration Points

### PaymentVerificationService

**Direct Blockchain Verification**:
- Uses blockchain clients directly for payment verification (indexer disabled for custodial architecture)
- Verifies transaction status, confirmations, amounts, and recipients
- Supports all major tokens (ETH, USDC, USDT, MATIC, BNB)
- Maintains test mode compatibility

**Key Methods**:
```php
// Direct blockchain verification (indexer disabled for custodial architecture)
$result = $service->verifyPayment($txHash, $chain, $amount, $token, $wallet);

// Automatically uses blockchain fallback verification when INDEXER_URL is disabled
// This is the recommended configuration for custodial wallet + NAG API architecture
```

### CirxTransferService  

**Real Blockchain Transfers**:
- Executes actual CIRX token transfers
- Validates wallet balances before transfer
- Supports both native CIRX and ERC-20 style transfers
- Includes transaction confirmation waiting

**Key Methods**:
```php
// Enhanced transfer with blockchain integration
$result = $service->transferCirxToUser($transaction);

// Returns CirxTransferResult with actual transaction hash
```

## Test Mode Compatibility

The blockchain integration includes automatic test mode detection:

**Test Mode Triggers**:
- `APP_ENV=testing`
- `defined('PHPUNIT_RUNNING')`

**Test Mode Behavior**:
- Returns mock success results
- Maintains existing test assertions
- Preserves integration test compatibility
- No actual blockchain calls made

**Example Test Mode Response**:
```php
// PaymentVerificationService in test mode
PaymentVerificationResult::success(
    $txHash,
    $expectedAmount, 
    $projectWallet,
    12, // Mock confirmations
    ['verification_method' => 'test_mode_fallback']
);
```

## Error Handling

### Retry Logic
- **Automatic retries**: 3 attempts with exponential backoff
- **Backup RPC URLs**: Automatic failover to secondary endpoints
- **Timeout handling**: Configurable request timeouts
- **Connection pooling**: Persistent HTTP connections

### Error Categories
1. **Connection Errors**: Network failures, DNS issues, timeouts
2. **Authentication Errors**: Invalid API keys, rate limiting
3. **Blockchain Errors**: Transaction not found, insufficient gas
4. **Validation Errors**: Invalid addresses, malformed data

### Error Response Format
```php
BlockchainException::connectionFailed(
    'ethereum',
    'https://mainnet.infura.io/v3/...',
    'Connection timeout',
    $previousException
);
```

## Performance Optimization

### Caching Strategy
- **Client instances**: Factory caches clients by network
- **Configuration**: Environment variables cached on startup
- **Gas prices**: Cached with TTL for cost optimization

### Request Optimization
- **Batch requests**: Multiple operations in single RPC call
- **Connection reuse**: HTTP keep-alive connections
- **Parallel processing**: Concurrent blockchain queries

### Resource Management
- **Memory efficient**: Lazy loading of clients
- **Connection limits**: Proper connection pool management
- **Timeout controls**: Prevents hanging requests

## Monitoring and Logging

### Health Checks
```php
$factory = new BlockchainClientFactory();
$healthStatus = $factory->healthCheck();

// Returns status for all configured networks:
// - Ethereum Mainnet: Healthy/Unhealthy
// - Polygon: Healthy/Unhealthy
// - CIRX: Healthy/Unhealthy
```

### Logging Integration
- **Structured logging**: JSON format with context
- **Performance metrics**: Request timing, retry counts
- **Error tracking**: Detailed error context and stack traces
- **Security events**: Authentication failures, suspicious activity

### Log Channels
- `blockchain`: General blockchain operations
- `transaction`: Transaction-specific events  
- `security`: Authentication and security events
- `api`: API request/response logging

## Usage Examples

### Basic Payment Verification

```php
use App\Services\PaymentVerificationService;

$verificationService = new PaymentVerificationService();

// Verify a payment (uses direct blockchain verification with indexer disabled)
$result = $verificationService->verifyPayment(
    '0xabc123...',  // Transaction hash
    'ethereum',     // Blockchain network
    '100.0',        // Expected amount
    'USDC',         // Token type
    '0x742d35...'   // Project wallet address
);

if ($result->isValid()) {
    echo "Payment verified: " . $result->getActualAmount();
} else {
    echo "Verification failed: " . $result->getErrorMessage();
}
```

### CIRX Token Transfer

```php
use App\Services\CirxTransferService;

$transferService = new CirxTransferService();

// Execute CIRX transfer (uses blockchain client)
$result = $transferService->transferCirxToUser($transaction);

if ($result->isSuccess()) {
    echo "Transfer successful: " . $result->getTransactionHash();
} else {
    echo "Transfer failed: " . $result->getErrorMessage();
}
```

### Direct Blockchain Client Usage

```php
use App\Blockchain\BlockchainClientFactory;

$factory = new BlockchainClientFactory();

// Get Ethereum client
$ethClient = $factory->getEthereumClient('mainnet');

// Check transaction status
$tx = $ethClient->getTransaction('0xabc123...');
$confirmations = $ethClient->getTransactionConfirmations('0xabc123...');

// Get token balance
$balance = $ethClient->getTokenBalance(
    '0xA0b86a33E6441e8532B8aE1F8A0b86a33E644122', // USDC contract
    '0x742d35...' // Wallet address
);
```

### CIRX Operations

```php
use App\Blockchain\BlockchainClientFactory;

$factory = new BlockchainClientFactory();
$cirxClient = $factory->getCirxClient();

// Check CIRX balance
$balance = $cirxClient->getCirxBalance('0x742d35...');

// Send CIRX transfer (requires private key)
if ($cirxClient->hasPrivateKey()) {
    $txHash = $cirxClient->sendCirxTransfer(
        '0xrecipient...', // Recipient address
        '100.0'          // CIRX amount
    );
}

// Verify CIRX transfer
$isValid = $cirxClient->verifyCirxTransfer(
    '0xtxhash...',    // Transaction hash
    '0xrecipient...', // Expected recipient
    '100.0'           // Expected amount
);
```

## Deployment Checklist

### Pre-Deployment
- [ ] Configure RPC endpoints with valid API keys
- [ ] Set up private keys securely
- [ ] Test blockchain connectivity
- [ ] Verify token contract addresses
- [ ] Run integration tests

### Production Setup
- [ ] Use production RPC endpoints
- [ ] Configure backup RPC URLs
- [ ] Set appropriate timeout values
- [ ] Enable comprehensive logging
- [ ] Set up monitoring alerts

### Security Audit
- [ ] Verify private key storage
- [ ] Check RPC endpoint authentication
- [ ] Validate input sanitization
- [ ] Test error handling
- [ ] Review access controls

## Troubleshooting

### Common Issues

**Connection Failures**:
```
Error: Failed to connect to ethereum blockchain at https://mainnet.infura.io/v3/...
```
- **Solution**: Check API key validity, network connectivity, RPC endpoint status

**Invalid Project ID**:
```
Error: invalid project id
```
- **Solution**: Verify Infura/Alchemy API keys in environment variables

**Transaction Not Found**:
```
Error: Transaction 0xabc123... not found on ethereum blockchain
```
- **Solution**: Verify transaction hash, check if transaction is confirmed, ensure correct network

**Insufficient Balance**:
```
Error: Insufficient CIRX balance. Required: 100.0, Available: 50.0
```
- **Solution**: Check wallet balance, ensure funds are available, verify wallet address

### Debug Commands

```bash
# Test blockchain connectivity
nix develop --command php -r "
require 'vendor/autoload.php';
use App\Blockchain\BlockchainClientFactory;
\$factory = new BlockchainClientFactory();
\$health = \$factory->healthCheck();
print_r(\$health);
"

# Check specific client
nix develop --command php -r "
require 'vendor/autoload.php';
use App\Blockchain\BlockchainClientFactory;
\$factory = new BlockchainClientFactory();
\$client = \$factory->getEthereumClient('mainnet');
echo 'Chain ID: ' . \$client->getChainId() . PHP_EOL;
echo 'Healthy: ' . (\$client->isHealthy() ? 'Yes' : 'No') . PHP_EOL;
"
```

## Migration Guide

### From Mock Implementation

The blockchain integration maintains full backward compatibility:

1. **Existing tests continue to work** - Test mode detection preserves mock behavior
2. **Service interfaces unchanged** - No breaking changes to public APIs
3. **Configuration additive** - New environment variables, existing ones preserved
4. **Indexer disabled** - Direct blockchain verification used for custodial architecture

### Production Migration Steps

1. **Stage 1**: Deploy with blockchain integration (fallback mode)
2. **Stage 2**: Monitor blockchain fallback usage and performance  
3. **Stage 3**: Gradually increase reliance on blockchain verification
4. **Stage 4**: Consider direct blockchain mode for critical operations

## Future Enhancements

### Planned Features
- **Multi-signature wallet support**
- **Hardware security module (HSM) integration**
- **Advanced gas optimization**
- **Cross-chain bridge support**
- **Real-time WebSocket connections**
- **Mempool monitoring**

### Performance Improvements
- **Request batching optimization**
- **Connection pooling enhancements**
- **Caching layer expansion**
- **Asynchronous processing**

## NAG API Usage Examples

### Available CEP Commands

The Circular Protocol NAG API uses CEP (Circular Endpoint Protocol) commands:

```bash
# Get available blockchains (no parameters needed)
curl -X POST "https://nag.circularlabs.io/NAG.php?cep=Circular_GetBlockchains_" \
  -H "Content-Type: application/json" \
  -d '{}'

# Get wallet balance (requires full blockchain address)
# Development/Testing - Sandbox blockchain
curl -X POST "https://nag.circularlabs.io/NAG.php?cep=Circular_GetWalletBalance_" \
  -H "Content-Type: application/json" \
  -d '{
    "Blockchain": "8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2",
    "Address": "your_wallet_address",
    "Asset": "CIRX",
    "Version": "1.0.8"
  }'

# Send transaction (requires full blockchain address)
# Development/Testing - Sandbox blockchain
curl -X POST "https://nag.circularlabs.io/NAG.php?cep=Circular_SendTransaction_" \
  -H "Content-Type: application/json" \
  -d '{
    "Blockchain": "8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2",
    "fromAddress": "sender_address",
    "recipientAddress": "recipient_address",
    "amount": "100.0",
    "asset": "CIRX",
    "nonce": 123,
    "Version": "1.0.8"
  }'
```

### Blockchain Address Mapping

The NAG API requires full blockchain addresses, not short names:

| Network | Blockchain Address | Usage |
|---------|-------------------|-------|
| **Circular Main Public** | `714d2ac07a826b66ac56752eebd7c77b58d2ee842e523d913fd0ef06e6bdfcae` | Production mainnet |
| **Circular Secondary Public** | `acb8a9b79f3c663aa01be852cd42725f9e0e497fd849b436df51c5e074ebeb28` | Secondary production network |
| **Circular Documark Public** | `e087257c48a949710b48bc725b8d90066871fa08f7bbe75d6b140d50119c481f` | Document verification network |
| **Circular SandBox** | `8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2` | **Development/Testing** ⭐ |

**Important**: For development and testing, use the **Sandbox** blockchain (`8a20baa...`). Test wallets and funded addresses are typically created on this network.

### Environment-Based Blockchain Selection

The backend automatically selects the appropriate blockchain based on environment:

```php
// Environment-based blockchain selection
function getBlockchainAddress($environment = null) {
    $env = $environment ?: ($_ENV['APP_ENV'] ?? 'development');
    
    switch ($env) {
        case 'production':
            return '714d2ac07a826b66ac56752eebd7c77b58d2ee842e523d913fd0ef06e6bdfcae'; // Main Public
        case 'staging':
            return 'acb8a9b79f3c663aa01be852cd42725f9e0e497fd849b436df51c5e074ebeb28'; // Secondary Public
        case 'development':
        case 'testing':
        default:
            return '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2'; // Sandbox
    }
}
```

**Configuration Examples:**

- **Development**: `APP_ENV=development` → Sandbox blockchain
- **Testing**: `APP_ENV=testing` → Sandbox blockchain  
- **Staging**: `APP_ENV=staging` → Secondary Public blockchain
- **Production**: `APP_ENV=production` → Main Public blockchain

### Common CEP Commands

| Command | Description | Required Parameters |
|---------|-------------|-------------------|
| `Circular_GetBlockchains_` | List available blockchains | None |
| `Circular_GetWalletBalance_` | Get wallet balance | `Blockchain`, `Address`, `asset`, `Version` |
| `Circular_GetWallet_` | Get wallet information | `Blockchain`, `Address`, `Version` |
| `Circular_SendTransaction_` | Send transaction | `Blockchain`, `fromAddress`, `recipientAddress`, `amount`, `asset`, `nonce`, `Version` |
| `Circular_GetWalletNonce_` | Get wallet nonce | `Blockchain`, `Address`, `Version` |

### Environment-Based Endpoint Selection

The backend automatically selects NAG endpoints based on environment:

```php
// Production environment
if (str_contains($rpcUrl, 'mainnet')) {
    $nagUrl = 'https://nag.circularlabs.io/NAG_Mainnet.php?cep=';
} else {
    // Development/Testing environment  
    $nagUrl = 'https://nag.circularlabs.io/NAG.php?cep=';
}
```

### Response Format

All NAG API responses follow this structure:

```json
{
  "Result": 200,
  "Response": {
    // Command-specific response data
  },
  "Node": "node_identifier"
}
```

### Troubleshooting NAG API Issues

**Common Error Codes and Solutions:**

| Error Code | Error Message | Solution |
|------------|---------------|----------|
| `119` | "Wrong Endpoint" | Use correct CEP command (e.g., `Circular_GetWalletBalance_`) |
| `111` | "Missing or invalid Blockchain" | Use full blockchain address, not short name |
| `126` | "Missing or Invalid Asset" | Use `"Asset": "CIRX"` (capital A), not `"asset": "CIRX"` |

**Debug Steps:**

1. **Test blockchain connectivity:**
   ```bash
   curl -X POST "https://nag.circularlabs.io/NAG.php?cep=Circular_GetBlockchains_" \
     -H "Content-Type: application/json" -d '{}'
   ```

2. **Verify wallet balance format:**
   ```bash
   # Use Sandbox blockchain for development/testing
   curl -X POST "https://nag.circularlabs.io/NAG.php?cep=Circular_GetWalletBalance_" \
     -H "Content-Type: application/json" \
     -d '{
       "Blockchain": "8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2",
       "Address": "test_address",
       "Asset": "CIRX",
       "Version": "1.0.8"
     }'
   ```

3. **Check backend debug endpoint:**
   - Visit: `http://localhost:8080/api/v1/debug/nag-config`
   - Test via: `http://localhost:3000/debug/circular-wallet`

**Important Notes:**

- Always use full blockchain addresses (64-character hex strings)
- Include `Version: "1.0.8"` in all requests
- Asset names are case-sensitive (use "CIRX", not "cirx")
- CEP commands must end with underscore (`_`)
- **SDK Bug Warning**: The official Circular Protocol PHP SDK v1.0.8 has a bug where `getWalletBalance()` uses lowercase `"asset"` parameter, but NAG API expects uppercase `"Asset"`. Use direct NAG calls with correct capitalization.

---

## Support

For questions or issues related to blockchain integration:

1. **Check logs** for detailed error messages
2. **Run health checks** to verify connectivity
3. **Review configuration** for correct RPC endpoints
4. **Test in development** before deploying to production

**Log Locations**:
- Application logs: `storage/logs/application.log`
- Blockchain logs: Search for `blockchain.` prefix
- Error logs: `storage/logs/errors.log`
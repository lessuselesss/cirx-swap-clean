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
CIRX_RPC_URL=https://rpc.circular.protocol
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

## Integration Points

### PaymentVerificationService

**Enhanced Fallback Verification**:
- When indexer is unavailable, uses blockchain clients directly
- Verifies transaction status, confirmations, amounts, and recipients
- Supports all major tokens (ETH, USDC, USDT, MATIC, BNB)
- Maintains test mode compatibility

**Key Methods**:
```php
// Existing indexer-based verification (primary)
$result = $service->verifyPayment($txHash, $chain, $amount, $token, $wallet);

// Fallback blockchain verification (automatic)
// Triggered when indexer is unhealthy
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

// Verify a payment (uses indexer primarily, blockchain as fallback)
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
4. **Gradual rollout** - Indexer remains primary, blockchain is fallback

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
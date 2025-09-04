# Proxy Configuration Documentation

## Overview

This document describes the proxy setup for the CIRX OTC Trading Platform, which enables secure communication between the frontend and external APIs (particularly Circular Protocol's NAG API) while avoiding CORS issues.

## Architecture

```
Frontend (Nuxt.js) → Backend Proxy (/api/v1/proxy/*) → External APIs (Circular Labs)
```

## Backend Proxy Endpoints

### 1. Circular Labs NAG API Proxy

**Endpoint**: `/api/v1/proxy/circular-labs`

**Methods**: GET, POST

**Purpose**: Proxies requests to Circular Labs NAG API for blockchain operations

**Query Parameters**:
- `cep` (required): The Circular endpoint method to call

**Whitelisted Methods**:
- `GetCirculatingSupply.php`
- `CProxy.php`
- `Circular_CheckWallet_`
- `Circular_GetWalletBalance_`

**Example Request**:
```bash
curl -X POST 'http://localhost:18423/api/v1/proxy/circular-labs?cep=Circular_CheckWallet_' \
  -H 'Content-Type: application/json' \
  -d '{
    "Blockchain": "8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2",
    "Address": "e184d1a551b4c0a5a21a90c72e238692c1bb84b5c06b832c37cc0f397ab28443",
    "Version": "1.0.8"
  }'
```

### 2. Circulating Supply Proxy

**Endpoint**: `/api/v1/proxy/circulating-supply`

**Method**: GET

**Purpose**: Fetches CIRX circulating supply data

**Example Request**:
```bash
curl 'http://localhost:18423/api/v1/proxy/circulating-supply'
```

## Configuration

### Backend Configuration (`backend/src/Controllers/ConfigController.php`)

The backend provides network-specific configuration through the `/api/v1/config/circular-network` endpoint:

```json
{
  "network": "testnet",
  "blockchain_id": "8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2",
  "nag_url": "/api/v1/proxy/circular-labs?cep=",
  "environment": "development",
  "chain_name": "Circular SandBox",
  "version": "1.0.8"
}
```

### Environment-Specific Settings

#### Development/Testing
```php
[
  'network' => 'testnet',
  'blockchain_id' => '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2',
  'nag_url' => '/api/v1/proxy/circular-labs?cep=',
  'chain_name' => 'Circular SandBox'
]
```

#### Staging
```php
[
  'network' => 'testnet',
  'blockchain_id' => 'acb8a9b79f3c663aa01be852cd42725f9e0e497fd849b436df51c5e074ebeb28',
  'nag_url' => '/api/v1/proxy/circular-labs?cep=',
  'chain_name' => 'Circular Secondary Public'
]
```

#### Production
```php
[
  'network' => 'mainnet',
  'blockchain_id' => '714d2ac07a826b66ac56752eebd7c77b58d2ee842e523d913fd0ef06e6bdfcae',
  'nag_url' => 'https://nag.circularlabs.io/NAG_Mainnet.php?cep=',
  'chain_name' => 'Circular Main Public'
]
```

## Frontend Integration

### 1. Environment Configuration

Configure your frontend environment variables in `ui/.env`:

```bash
# Backend API configuration
# Development: http://localhost:18423/api/v1
# Production: https://circularprotocol.io/buy/api/v1
NUXT_PUBLIC_API_BASE_URL=http://localhost:18423/api/v1
```

### 2. Fetching Configuration

The frontend should fetch configuration from the backend on initialization:

```javascript
// composables/utils/validators.js
const getCircularConfig = async () => {
  try {
    const config = useRuntimeConfig()
    const apiBaseUrl = config.public.apiBaseUrl || 'http://localhost:18423/api/v1'
    
    const response = await fetch(`${apiBaseUrl}/config/circular-network`)
    if (!response.ok) throw new Error('Failed to fetch config')
    return await response.json()
  } catch (error) {
    // Fallback configuration
    return {
      network: 'testnet',
      blockchain_id: '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2',
      nag_url: '/api/v1/proxy/circular-labs?cep=',  // IMPORTANT: Relative path format
      chain_name: 'Circular SandBox'
    }
  }
}
```

### 3. URL Construction

The frontend must properly construct the full proxy URL by combining the API base URL with the relative NAG URL:

```javascript
// composables/utils/validators.js - performAddressValidation function
const performAddressValidation = async (address, config) => {
  // Get the API base URL from runtime config
  const runtimeConfig = useRuntimeConfig()
  const apiBaseUrl = runtimeConfig.public.apiBaseUrl || 'http://localhost:18423/api/v1'
  
  // Build the full URL - handle different URL formats
  let nagUrl
  if (config.nag_url.startsWith('/api/v1')) {
    // Replace /api/v1 prefix with the actual base URL
    nagUrl = apiBaseUrl + config.nag_url.substring(7) // Remove '/api/v1' (7 chars)
  } else if (config.nag_url.startsWith('/')) {
    nagUrl = apiBaseUrl + config.nag_url
  } else {
    nagUrl = config.nag_url
  }
  
  // Now use the constructed URL for API calls
  const walletResponse = await fetch(nagUrl + 'Circular_CheckWallet_', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      Blockchain: config.blockchain_id,
      Address: address.replace('0x', ''), // Strip 0x prefix
      Version: '1.0.8'
    })
  })
  
  return await walletResponse.json()
}
```

### 4. Making Proxy Requests

#### Complete Example: Checking Wallet Existence
```javascript
const checkWallet = async (address) => {
  const config = await getCircularConfig()
  
  // Construct the full URL
  const runtimeConfig = useRuntimeConfig()
  const apiBaseUrl = runtimeConfig.public.apiBaseUrl || 'http://localhost:18423/api/v1'
  
  let nagUrl
  if (config.nag_url.startsWith('/api/v1')) {
    nagUrl = apiBaseUrl + config.nag_url.substring(7)
  } else if (config.nag_url.startsWith('/')) {
    nagUrl = apiBaseUrl + config.nag_url
  } else {
    nagUrl = config.nag_url
  }
  
  const response = await fetch(nagUrl + 'Circular_CheckWallet_', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      Blockchain: config.blockchain_id,
      Address: address.replace('0x', ''), // Strip 0x prefix
      Version: '1.0.8'
    })
  })
  
  return await response.json()
}
```

## Common Issues and Solutions

### Issue 1: "Server returned invalid response (expected JSON, got HTML/text)"

**Cause**: Incorrect NAG URL format in frontend configuration

**Solution**: Ensure the `nag_url` format matches exactly:
- ✅ Correct: `/api/v1/proxy/circular-labs?cep=`
- ❌ Wrong: `/api/v1/proxy/circular-labs?endpoint=NAG.php&cep=`

### Issue 2: "HTTP 404: Not Found" when validating addresses

**Cause**: Improper URL construction when combining API base URL with NAG URL path

**Solution**: 
1. Ensure `NUXT_PUBLIC_API_BASE_URL` is set correctly in `ui/.env`
2. Implement proper URL construction logic that handles the `/api/v1` prefix:
   - If `nag_url` starts with `/api/v1`, strip it and append to base URL
   - If `nag_url` starts with `/`, append to base URL
   - Otherwise, use as-is (for absolute URLs)

### Issue 3: "Invalid method" error from proxy

**Cause**: Attempting to use a non-whitelisted NAG API method

**Solution**: Only use whitelisted methods listed above, or add new methods to the whitelist in `backend/public/index.php`

### Issue 4: CORS errors when calling external APIs directly

**Cause**: Browser blocking cross-origin requests

**Solution**: Always use the backend proxy endpoints instead of calling external APIs directly from the frontend

## Security Considerations

1. **Method Whitelisting**: The proxy only allows specific NAG API methods to prevent abuse
2. **Input Validation**: Always validate and sanitize input data before forwarding to external APIs
3. **Rate Limiting**: Consider implementing rate limiting on proxy endpoints to prevent abuse
4. **HTTPS**: In production, ensure all communication uses HTTPS
5. **API Keys**: Store sensitive API keys in backend environment variables, never expose to frontend

## Testing

### Test Backend Proxy
```bash
# Test wallet check
curl -X POST 'http://localhost:18423/api/v1/proxy/circular-labs?cep=Circular_CheckWallet_' \
  -H 'Content-Type: application/json' \
  -d '{"Blockchain":"8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2","Address":"e184d1a551b4c0a5a21a90c72e238692c1bb84b5c06b832c37cc0f397ab28443","Version":"1.0.8"}'

# Test configuration endpoint
curl 'http://localhost:18423/api/v1/config/circular-network'

# Test circulating supply
curl 'http://localhost:18423/api/v1/proxy/circulating-supply'
```

### Monitor Logs
```bash
# Watch backend logs for proxy requests
tail -f backend/storage/logs/api.log | grep proxy/circular-labs
```

## Deployment Notes

1. **Development**: Uses local proxy to avoid CORS issues
2. **Staging**: Uses proxy with testnet blockchain
3. **Production**: May use direct API calls if CORS is configured, or proxy for consistency

## Contact

For issues or questions about the proxy configuration, please check:
- Backend logs in `backend/storage/logs/`
- Network tab in browser developer tools
- Backend health check: `http://localhost:18423/api/v1/health`
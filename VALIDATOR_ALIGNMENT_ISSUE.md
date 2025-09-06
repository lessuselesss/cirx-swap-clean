# Circular Address Validator Alignment Issue Report

## Issue Summary
The Circular address validation works in production but fails in the development environment due to a mismatch between the frontend's API base URL configuration and the backend's actual route structure.

## Root Cause Analysis - UPDATED

### ACTUAL Issue Found
The frontend is configured to use `/api/v1` as the base URL, but the backend serves routes directly under `/v1`. This causes all API calls to fail with 404 errors.

### Current Architecture
1. **Frontend** (`ui/composables/utils/validators.js`):
   - Makes direct NAG API calls from the browser
   - Fetches backend config to get the NAG URL endpoint
   - Constructs full URL and makes POST request to validate addresses

2. **Backend** (`backend/src/Controllers/ConfigController.php`):
   - Returns different NAG URLs based on environment:
     - Production: `https://nag.circularlabs.io/NAG_Mainnet.php?cep=`
     - Development: `/api/v1/proxy/circular-labs?cep=`

3. **Backend Proxy** (`backend/public/index.php`):
   - Provides a proxy endpoint at `/api/v1/proxy/circular-labs`
   - Forwards requests to the actual NAG API
   - Handles CORS and authentication

## The Problem

### Development Environment Issue
1. Backend returns: `nag_url: '/api/v1/proxy/circular-labs?cep='`
2. Frontend code appends method: `/api/v1/proxy/circular-labs?cep=Circular_CheckWallet_`
3. Frontend makes direct browser request to: `http://localhost:18423/api/v1/proxy/circular-labs?cep=Circular_CheckWallet_`
4. **PROBLEM**: The proxy expects the `cep` parameter value to be from a whitelist, but receives the full method name
5. **RESULT**: Validation fails with "Invalid method" error

### Production Environment (Working)
1. Backend returns: `https://nag.circularlabs.io/NAG_Mainnet.php?cep=`
2. Frontend appends method: `https://nag.circularlabs.io/NAG_Mainnet.php?cep=Circular_CheckWallet_`
3. Direct browser request succeeds (assuming CORS is configured on NAG API)

## Code Evidence

### Frontend Validation Code (`ui/composables/utils/validators.js:251-252`)
```javascript
// Step 1: Check wallet existence first
const checkUrl = nagUrl + 'Circular_CheckWallet_'
```

### Backend Proxy Whitelist (`backend/public/index.php:431-436`)
```php
$allowedMethods = [
    'GetCirculatingSupply.php',
    'CProxy.php', 
    'Circular_CheckWallet_',      // <-- This is in the whitelist
    'Circular_GetWalletBalance_'
];
```

### Backend Config (`backend/src/Controllers/ConfigController.php:94-95`)
```php
'nag_url' => '/api/v1/proxy/circular-labs?cep=',  // Development mode
```

## Solution Options

### Option 1: Fix Frontend to Use Proxy Correctly (Recommended)
Modify the frontend to detect when using a proxy URL and handle it differently:

```javascript
// In performAddressValidation function
if (nagUrl.includes('/proxy/')) {
    // Using backend proxy - send full request through proxy
    const response = await fetch(nagUrl + 'Circular_CheckWallet_', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestBody)
    });
} else {
    // Direct NAG API call (production)
    const checkUrl = nagUrl + 'Circular_CheckWallet_'
    // ... existing code
}
```

### Option 2: Update Backend Config
Change the development configuration to use the direct NAG URL:

```php
// In ConfigController.php
'nag_url' => 'https://nag.circularlabs.io/NAG.php?cep=',  // Use direct URL in dev
```

**Pros**: Simple fix
**Cons**: Loses proxy benefits (CORS handling, logging, rate limiting)

### Option 3: Enhance Proxy to Handle Full URLs
Modify the proxy to extract the method from the full CEP parameter:

```php
// In index.php proxy handler
$cep = $request->getQueryParams()['cep'] ?? '';
// Extract just the method name if full URL provided
if (strpos($cep, 'Circular_') === 0) {
    // Already just the method name, use as-is
} else {
    // Parse out the method from a full URL if needed
}
```

## Recommended Solution

**Use Option 1** - Fix the frontend to properly use the backend proxy. This maintains the benefits of the proxy architecture while ensuring consistent behavior across environments.

## Implementation Steps

1. Update `ui/composables/utils/validators.js` to detect proxy URLs
2. When using proxy, construct the request differently
3. Test in both development and production environments
4. Ensure proper error handling for both paths

## Testing Checklist

- [ ] Test address validation in development with proxy
- [ ] Test address validation in production with direct NAG API
- [ ] Verify CORS handling in both environments
- [ ] Check error messages are user-friendly
- [ ] Confirm validation state (yellow → green/red) works correctly

## Environment Variables to Verify

### Backend (.env)
```bash
CIRX_NAG_URL=https://nag.circularlabs.io/NAG.php?cep=
CIRX_NAG_URL_BACKUP=https://nag.circularlabs.io/NAG_Mainnet.php?cep=
TESTNET_MODE=true  # Controls which NAG endpoint is used
```

### Frontend (.env)
```bash
NUXT_PUBLIC_API_BASE_URL=http://localhost:18423/api/v1  # Must match backend
```

## Conclusion

The issue is a mismatch between how the frontend constructs NAG API calls and how the backend proxy expects to receive them. The frontend is treating the proxy URL like a direct NAG API endpoint, which causes validation to fail in development. The recommended solution is to update the frontend to detect when it's using a proxy and adjust its request construction accordingly.
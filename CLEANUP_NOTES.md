# Cleanup Notes

## Leftover Foundry Files (Not Currently Used)

The following files are remnants from an earlier smart contract development phase but are not currently used in the project:

### Files to Consider Removing:
- `foundry.toml` - Foundry configuration file
- `script/Counter.s.sol` - Deployment script for Counter contract
- `test/Counter.t.sol` - Test for Counter contract
- `test/CIRXToken.t.sol` - Test for CIRX token contract
- `test/OTCSwap.t.sol` - Test for OTC swap contract  
- `test/SimpleOTCSwap.t.sol` - Test for simple OTC swap
- `test/VestingContract.t.sol` - Test for vesting contract

### Keep These Files:
- `test/check-console.js` - Console debugging utilities (used for frontend)
- `test/console-capture.js` - Console capture utilities (used for frontend)
- `test/debug-console.js` - Debug console utilities (used for frontend)

## Current Architecture

This project now uses **API-first architecture**:
- Frontend (Nuxt.js) → Backend API (PHP) → Circular Protocol APIs
- No direct smart contract deployment or interaction
- All blockchain operations handled via Circular Protocol integration

## If You Want to Remove Foundry Files:

```bash
# Remove Foundry configuration
rm foundry.toml

# Remove Solidity contracts and tests (keep JS debug files)
rm script/Counter.s.sol
rm test/*.sol

# Or remove entire directories if empty after cleanup
# (but keep test/*.js files)
```

## Alternative: Keep for Future

If there are plans to add smart contract development in the future, these files can serve as a starting template. The decision depends on the project roadmap.
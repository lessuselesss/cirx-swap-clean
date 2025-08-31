# Code Redundancy Refactoring Checklist

**Generated from neural embeddings analysis of 217 functions**  
**Date: 2025-08-31**

## 📋 PHASE 1: Critical Duplicates (Week 1)

### Day 1: Create Core Utility Files

- [ ] **Create `ui/composables/useMathUtils.js`**
  - [ ] Implement `safeOperation(operation, a, b, fallback)` generic function
  - [ ] Create `safeDiv`, `safeMul`, `safePercentage` as wrappers
  - [ ] Add input validation and NaN/Infinity checks
  - [ ] Write unit tests for edge cases

- [ ] **Create `ui/composables/useFormattingUtils.js`**
  - [ ] Migrate advanced `formatNumber` (lines 758-803 from useFormattedNumbers.js)
  - [ ] Migrate `formatCurrency` (lines 857-871)
  - [ ] Migrate `formatTokenAmount` (lines 811-849)  
  - [ ] Migrate `formatPercentage` (lines 879-907)
  - [ ] Remove simple wrapper versions (lines 116-161)
  - [ ] Test all formatting edge cases

### Day 2: API Consolidation

- [ ] **Create `ui/composables/useApiClient.js`**
  - [ ] Extract common `getHeaders()` logic from useBackendAPIs.js
  - [ ] Create unified `createApiRequest(method, endpoint, data, options)`
  - [ ] Merge `handleApiResponse()` error handling
  - [ ] Add retry logic and timeout handling

- [ ] **Update useBackendAPIs.js**
  - [ ] Replace `initiateSwap` and `getTransactionStatus` with unified client
  - [ ] Merge `processTransactions` and `triggerManualProcess` (94.6% similarity)
  - [ ] Remove duplicate error handling code
  - [ ] Test all API endpoints still work

### Day 3-4: Quote System Consolidation

- [ ] **Create `ui/composables/useQuoteCalculator.js`**
  - [ ] Merge `getLiquidQuote` and `getOTCQuote` (97.3% similarity)
    - [ ] Create unified `getQuote(inputToken, inputAmount, { type: 'liquid'|'otc' })`
    - [ ] Preserve 0.3% vs 0.15% fee difference logic
    - [ ] Maintain OTC discount calculation
  - [ ] Merge `calculateQuote` and `calculateReverseQuote` (94.7% similarity)
    - [ ] Create `calculateQuote(input, { reverse: boolean })`
    - [ ] Preserve directional calculation logic
  - [ ] Update imports in `useSwapHandler.js`

- [ ] **Update useSwapHandler.js**
  - [ ] Replace `safeDiv`, `safeMul` with `useMathUtils`
  - [ ] Update quote functions to use new calculator
  - [ ] Test swap calculations remain accurate

### Day 5: Price Data Consolidation

- [ ] **Create `ui/composables/usePriceService.js`**
  - [ ] Create `fetchPriceWithFallbacks(sources, symbol, pair)` 
  - [ ] Define price source interfaces for different APIs
  - [ ] Merge `fetchCIRXFromAggregator` and `fetchCIRXFromCoinGecko` (96.1% similarity)
  - [ ] Create fallback chain: Aggregator → CoinGecko → DEXTools → Major tokens

- [ ] **Update usePriceData.js**
  - [ ] Replace individual fetch functions with unified service
  - [ ] Merge `useSingleExchangeDatafeed` and `createSingleExchangeDatafeed` (97.8% similarity)
  - [ ] Test price fetching still works with all sources
  - [ ] Verify TradingView integration remains functional

## 📋 PHASE 2: Component Integration (Week 2)

### Day 1-2: Update Component Imports

- [ ] **Find all components using redundant functions**
  - [ ] Search for imports of formatting functions
  - [ ] Search for direct usage of `safeDiv`, `safeMul`
  - [ ] Search for quote calculation calls
  - [ ] Update imports to use new consolidated utilities

- [ ] **Update component implementations**
  - [ ] Replace old function calls with new unified APIs
  - [ ] Update TypeScript types if applicable  
  - [ ] Test each component individually

### Day 3-4: Error Handling Consolidation

- [ ] **Create `ui/composables/useErrorService.js`**
  - [ ] Merge `clearError`/`clearAllErrors` from useErrorHandler.js (91.7% similarity)
  - [ ] Merge `shouldShowAsToast`/`shouldShowInline` (92.6% similarity)
  - [ ] Create unified error display strategy

- [ ] **Create `ui/composables/useTransactionService.js`**
  - [ ] Merge `fetchTransactionHistory`/`fetchVestingPositions`/`fetchUserStats` (93% similarity)
  - [ ] Create generic `fetchUserData(type, userAddress, options)` function
  - [ ] Maintain individual endpoint configurations

### Day 5: Testing & Validation

- [ ] **Run comprehensive tests**
  - [ ] Test all swap calculations with various inputs
  - [ ] Verify price data fetching from all sources
  - [ ] Test error handling scenarios
  - [ ] Check formatting edge cases
  - [ ] Validate API calls still work

- [ ] **Performance testing**
  - [ ] Measure bundle size reduction
  - [ ] Check for any performance regressions
  - [ ] Verify memory usage improvements

## 📋 PHASE 3: Architecture Cleanup (Week 3)

### Day 1-2: Directory Restructuring

- [ ] **Create new directory structure**
  ```
  ui/composables/
  ├── core/
  │   ├── useApiClient.js      ✅ Created
  │   ├── useFormattingUtils.js ✅ Created 
  │   ├── useMathUtils.js      ✅ Created
  │   └── useErrorService.js    ✅ Created
  ├── features/
  │   ├── useSwapLogic.js      (cleaned up)
  │   ├── usePriceService.js   ✅ Created
  │   └── useTransactionService.js ✅ Created
  └── utils/
      ├── validators.js        (address validation)
      └── constants.js         (shared constants)
  ```

- [ ] **Remove redundant code**
  - [ ] Delete duplicate functions from original files
  - [ ] Clean up imports and exports
  - [ ] Update internal cross-references

### Day 3-4: Final Integration

- [ ] **Update all remaining files**
  - [ ] Scan for any missed references to old functions
  - [ ] Update TypeScript definitions
  - [ ] Fix any circular dependency issues
  - [ ] Clean up unused imports

### Day 5: Documentation & Cleanup

- [ ] **Update documentation**
  - [ ] Document new utility APIs
  - [ ] Add migration guide for future developers
  - [ ] Update component examples

- [ ] **Final validation**
  - [ ] Run full test suite
  - [ ] Test complete user flows (swap, price checking, etc.)
  - [ ] Verify no console errors or warnings
  - [ ] Check bundle size reduction metrics

## ✅ SUCCESS CRITERIA

- [ ] **Code Reduction**: Achieve 30-40% reduction in functions (217 → ~130)
- [ ] **Eliminate Critical Duplicates**: All >90% similar functions consolidated  
- [ ] **Maintain Functionality**: All existing features work identically
- [ ] **Bundle Size**: Measurable reduction in JavaScript bundle size
- [ ] **Developer Experience**: Cleaner, more consistent APIs
- [ ] **Test Coverage**: Maintain or improve existing test coverage

## 🚨 CRITICAL DEPENDENCIES TO PRESERVE

- [ ] **External Dependencies**
  - [ ] Vue Composition API (`ref`, `computed`, `watch`)
  - [ ] Viem utilities for Web3 operations
  - [ ] TradingView charting library API compliance
  - [ ] Runtime config access patterns

- [ ] **Cross-File Dependencies**  
  - [ ] `usePriceData` import in `useSwapHandler` (line 57)
  - [ ] Environment variables and API configurations
  - [ ] Token address constants and contract ABIs
  - [ ] Backend API endpoint URL structure

## 📊 TRACKING PROGRESS

**Current Status**: ⏳ Ready to start Phase 1
- Total Functions: 217
- Critical Duplicates: 45+ functions >90% similarity  
- Target Functions: ~130 (40% reduction)
- Estimated Completion: 3 weeks

---

## 🎯 IMMEDIATE NEXT STEPS

1. **Start with Day 1 tasks** - Create `useMathUtils.js` and `useFormattingUtils.js`
2. **Focus on highest similarity functions first** (97%+ similarity)
3. **Test each consolidation incrementally** to avoid breaking functionality
4. **Preserve all existing APIs** during transition phase

*This checklist was generated from neural embeddings analysis showing 45+ functions with >90% semantic similarity that can be safely consolidated.*
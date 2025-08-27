# Testing & Code Quality

- [ ] **CRITICAL: Centralize testing and check test coverage for backend and frontend**
  - Backend PHPUnit tests are scattered and incomplete 
  - Need comprehensive test coverage analysis
  - Frontend testing strategy needs evaluation
  - Unit tests should have caught the NAG API decimal conversion bug (203.1 CIRX treated as wei)
  - Add integration tests for all blockchain interactions

- [ ] **CRITICAL: Fix status constant naming inconsistency**
  - Transaction status constants use `SCREAMING_SNAKE_CASE` names but store `lowercase_snake_case` values
  - Constants should be: `STATUS_PAYMENT_VERIFIED = 'PAYMENT_VERIFIED'` not `'payment_verified'`
  - Update `src/Models/Transaction.php` constants to match constant names
  - Create database migration to update existing status values to `SCREAMING_SNAKE_CASE`
  - Verify all code uses constants instead of hardcoded string values

# Frontend

- [ ] cookie page no longer matches the design of this project please update it.

- [ ] chart price in the top right takes a very very long time to load the price. the entire chart get's loaded many seconds before the price is rendered??
- [ ] cirx/usdt dropdown on the chart shouldn't be a dropdown. 
- [ ] icons for cirx/usdt pair in chart needed 
- [ ] chart should show standard chart by default
- [ ] chart should have the same background color as the swap form 

- [ ] cirx transaction hash on transaction page points to a bogus url.  backend

- [ ] ~~transactions are't processing e2e when everything appears to be setup and working fine. why isn't this working? are we removing the '0x' for circular transcations?~~ **RESOLVED: NAG API decimal conversion bug fixed**

ENVs

- [ ] we don't need to set the confirmations for each network. just the confirmations for the network that is provisioned, the rest should work dynamically
# **Circular CIRX OTC Purchase Form** **Product Requirements Document (PRD)**

### **Don’t need to connect their Circular wallet \- just paste the wallet address.**

Buy \- liquid token  
OTC \- vested token  (for 6 months with x discount).

Two different tabs  
Look at matcha/jupiter ui (with form \+ chart)

### **1\. Purpose**

The Circular OTC Purchase Form is designed to enable users to seamlessly purchase (swap) CIRX tokens for other cryptocurrencies using an over-the-counter (OTC) interface. The form will support wallet connections (Phantom, Ethereum, MetaMask) and facilitate secure, efficient, and transparent transactions for both novice and experienced users.

### **2\. Background & Rationale**

Circular aims to simplify the process of acquiring CIRX tokens outside of traditional exchanges, catering to users who prefer direct transactions or larger trades. The OTC form will reduce friction, improve user trust, and expand the CIRX ecosystem by supporting multiple wallets and token pairs.

### **3\. Goals & Objectives**

1. Enable users to connect popular wallets (Phantom, MetaMask, Ethereum-compatible wallets)  
2. Allow users to purchase CIRX tokens with a variety of supported cryptocurrencies  
3. Provide a secure, intuitive, and transparent user experience  
4. Ensure compliance with regulatory and KYC/AML requirements (where applicable)  
5. Support transaction tracking, confirmations, and error handling

### **4\. Scope**

#### **In Scope**

1. Wallet connection (Phantom, MetaMask, Ethereum wallets)  
2. User interface for OTC purchase (swap) of CIRX  
3. Support for major tokens (ETH, USDC, USDT, SOL, etc.)  
4. Real-time price quoting and slippage tolerance settings  
5. Transaction summary and confirmation  
6. Error handling and user feedback  
7. Transaction status and history (basic)  
8. Basic KYC/AML check integrations (if required)

#### **Out of Scope**

1. Integration with centralized exchanges  
2. Advanced trading features (e.g., limit orders, algorithmic trading)  
3. Fiat onramps/offramps (phase 2 via stripe)

### **5\. User Stories**

#### **As a user, I want to:**

1. Connect my preferred wallet (Phantom, MetaMask, or Ethereum-compatible) so I can access my crypto assets.  
2. Select the token I want to swap for CIRX, enter the amount, and view the quoted price.  
3. Review transaction details (exchange rate, fees, estimated completion time).  
4. Confirm the transaction and receive real-time feedback on its status.  
5. View a history of my OTC transactions for reference.

### **6\. Functional Requirements**

#### **6.1 Wallet Integration**

1. Support Phantom (Solana), MetaMask (Ethereum), and WalletConnect  
2. Detect wallet connection status and prompt users to connect/disconnect  
3. Display connected wallet address and balance for relevant tokens

#### **6.2 Token Selection & Quoting**

1. List supported tokens for swap (ETH, USDC, USDT, SOL, etc.)  
2. Fetch and display real-time exchange rates and slippage estimates  
3. Allow users to input amount in either source or destination token

#### **6.3 Transaction Flow**

1. Validate user input (amount, token selection, wallet connection)  
2. Display transaction summary (amount, rate, slippage, fees)  
3. Confirm transaction via wallet signature  
4. Display progress (pending, confirmed, failed) and provide transaction hash

#### **6.4 Error Handling & Feedback**

1. Display clear error messages for failed connections, insufficient balance, or transaction failures  
2. Provide actionable feedback for recovery (e.g., retry, reconnect wallet)

#### **6.5 Transaction History**

1. Display a list of recent OTC transactions (date, token, amount, status)  
2. Allow users to view transaction details and blockchain explorer link

#### **6.6 Security & Compliance**

1. Ensure secure handling of wallet credentials (never store private keys)  
2. Integrate basic KYC/AML checks if required by jurisdiction  
3. Log transactions for audit purposes

**6.7 Edge Case Handling**

**Wallet Disconnection During Transaction:**

1. If a wallet disconnects before transaction confirmation, the form will immediately halt the process and display a clear error message (“Wallet disconnected—please reconnect to continue”).  
2. Any partially entered data will be preserved locally to allow the user to resume after reconnecting.  
3. If a transaction was already signed but not broadcast, prompt the user to reconnect and attempt to resend, or provide guidance for manual broadcast if possible.  
4. All transaction attempts will be logged for audit and troubleshooting.

**Network Failure or Timeout:**

1. If network connectivity is lost or the transaction times out, notify the user and offer options to retry, check wallet status, or contact support.  
2. Ensure no funds are deducted unless the transaction is confirmed on-chain.

**Insufficient Gas/Fees:**

1. Detect and warn users before transaction submission if estimated gas/fees are insufficient.  
2. If fees become insufficient mid-process (e.g., due to network congestion), display a prompt to adjust and retry.

**Token Contract Issues:**

1. If a selected token contract is invalid or becomes unresponsive, display an error and prevent submission until resolved.

**Concurrent Transactions:**

1. Prevent multiple simultaneous submissions from the same wallet to avoid double-spending or nonce conflicts.

### **7\. Non-Functional Requirements**

**Performance:** Transactions should be processed with minimal latency; UI should be responsive.  
**Reliability:** 99.9% uptime for the form; robust error handling to prevent fund loss.  
**Security:** End-to-end encryption for all sensitive data; compliance with industry security standards.  
**Scalability:** Support for increased transaction volume and additional token pairs in the future.  
**Accessibility:** WCAG 2.1 compliance for users with disabilities.  
**Localization:** English (initially), with potential for additional languages.

### **8\. Success Metrics**

1. Time to complete a purchase (target: \<2 minutes)  
2. Conversion rate (wallet connect to completed swap)  
3. Error rate (failed transactions vs. total attempts)  
4. Number of unique wallets connected

### **9\. Dependencies**

1. Wallet SDKs/APIs (Phantom, MetaMask, WalletConnect)  
2. Price oracle providers for real-time rates  
3. Smart contract infrastructure for OTC swaps  
4. KYC/AML service providers (if applicable)

### **10\. Risks & Mitigations**

**Smart contract vulnerabilities:** Conduct third-party audits and continuous monitoring.  
**Regulatory changes:** Monitor compliance requirements and update KYC/AML processes as needed.  
**Wallet compatibility issues:** Regularly update support for new wallet versions.

### **11\. Appendix**

* Wireframes/mockups (to be developed)

* API documentation references

* Regulatory compliance guidelines


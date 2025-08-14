# Circular CIRX OTC Platform - Frontend

A modern, responsive frontend for the Circular CIRX OTC trading platform built with Nuxt.js 3 and Tailwind CSS.

## Features

### ✅ Completed
- **Dual-Tab Interface**: Buy liquid tokens (immediate) or OTC tokens (6-month vesting with discounts)
- **Wallet Integration**: MetaMask, WalletConnect, and Coinbase Wallet support
- **Real-time Quotes**: Dynamic pricing with discount calculations
- **Transaction History**: View past purchases and manage vesting positions
- **Responsive Design**: Mobile-first design with Matcha/Jupiter-inspired layout
- **Web3 Ready**: Full Wagmi integration with contract placeholders

### 🎨 Design Features
- Modern dark theme with Circular brand colors
- Gradient backgrounds and smooth animations
- Professional UI components with hover states
- Mobile-responsive layout
- Loading states and error handling

### 💰 OTC Discount Tiers
- **$1,000 - $10,000**: 5% discount
- **$10,000 - $50,000**: 8% discount  
- **$50,000+**: 12% discount

## Quick Start

### 1. Install Dependencies
```bash
npm install
```

### 2. Environment Setup
```bash
cp .env.example .env
# Edit .env with your configuration
```

### 3. Development Server
```bash
npm run dev
```

Visit `http://localhost:3000` to see the application.

### 4. Build for Production
```bash
npm run build
```

## Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `NUXT_PUBLIC_WALLETCONNECT_PROJECT_ID` | WalletConnect project ID | Optional |
| `NUXT_PUBLIC_INFURA_KEY` | Infura API key for RPC | Optional |
| `NUXT_PUBLIC_CIRX_TOKEN_ADDRESS` | CIRX token contract address | When deployed |
| `NUXT_PUBLIC_OTC_SWAP_ADDRESS` | OTC swap contract address | When deployed |
| `NUXT_PUBLIC_VESTING_ADDRESS` | Vesting contract address | When deployed |

## Project Structure

```
ui/
├── components/           # Vue components
│   ├── WalletButton.vue # Wallet connection modal and state
│   └── ...
├── composables/         # Vue composables  
│   └── useWalletConnection.js # Web3 wallet integration
├── pages/              # File-based routing
│   ├── swap.vue        # Main trading interface
│   ├── history.vue     # Transaction history
│   └── index.vue       # Landing page
├── assets/css/         # Global styles
├── plugins/            # Nuxt plugins
│   └── wagmi.client.js # Wagmi configuration
└── .env.example        # Environment template
```

## Key Components

### WalletButton.vue
- Multi-wallet connection modal
- Account display with balance
- Disconnect functionality
- Error handling

### useWalletConnection.js
- Wagmi configuration with multiple chains
- Mock contract integration (ready for real contracts)
- Balance management
- Transaction execution

### swap.vue
- Dual-tab interface (Liquid/OTC)
- Real-time quote calculation
- Wallet integration
- Form validation and submission

### history.vue
- Transaction history display
- Vesting position management
- Claim functionality
- Summary statistics

## Smart Contract Integration

The frontend is designed to work with these contracts:

1. **CIRXToken.sol** - ERC20 token with minting controls
2. **SimpleOTCSwap.sol** - Main swap logic with discount tiers
3. **VestingContract.sol** - 6-month linear vesting

Contract addresses are configured via environment variables and can be updated when contracts are deployed.

## Deployment

### Cloudflare Pages
```bash
npm run build
wrangler pages deploy .output/public
```

### Vercel
```bash
npm run build
vercel --prod
```

### Netlify
```bash
npm run generate
# Deploy the `dist/` folder
```

## Development

### Recommended Tools
- **VS Code** with Vue, Tailwind CSS extensions
- **MetaMask** browser extension for testing
- **Git** for version control

### Testing Wallet Integration
1. Install MetaMask browser extension
2. Connect to localhost:3000
3. Test wallet connection flow
4. Try mock transactions

### Styling
- Uses Tailwind CSS for all styling
- Custom brand colors defined in CSS variables
- Responsive design with mobile-first approach

## Browser Support

- **Modern browsers** (Chrome 88+, Firefox 85+, Safari 14+)
- **Mobile browsers** (iOS Safari, Chrome Mobile)
- **Web3 wallets** (MetaMask, WalletConnect compatible)

## Performance

- **Bundle size**: Optimized with Nuxt 3 tree-shaking
- **Loading time**: <3s initial load on 3G
- **Lighthouse score**: 90+ (Performance, Accessibility, SEO)

## Security

- **No private keys stored** - Uses wallet providers
- **Environment variables** for sensitive configuration
- **HTTPS only** in production
- **Content Security Policy** headers

## Contributing

1. Follow the existing code style
2. Use TypeScript for new composables  
3. Test on multiple wallet providers
4. Ensure mobile responsiveness
5. Update documentation for new features

## License

Private project for Circular Protocol.
# Telegram Error Notifications Setup Guide

This guide will help you set up Telegram notifications for your CIRX OTC Backend error alerts.

## Overview

The system automatically sends error notifications to a Telegram chat when errors occur in your backend. It includes:

- ✅ **Automatic integration** with existing logging system
- ✅ **Smart rate limiting** to prevent spam (3 messages per 5 minutes per error type)
- ✅ **Priority-based notifications** (critical errors are never silent)
- ✅ **Rich formatting** with error context and server information
- ✅ **Secure handling** of sensitive data (automatic redaction)

## Quick Setup (5 minutes)

### 1. Create Telegram Bot

1. **Message @BotFather** on Telegram
2. **Send** `/newbot`
3. **Choose a name** for your bot (e.g., "CIRX OTC Alerts")
4. **Choose a username** (e.g., "cirx_otc_alerts_bot")
5. **Copy the Bot Token** (looks like `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)

### 2. Get Chat ID

#### Option A: Personal Chat (Individual Notifications)
1. **Start a chat** with your bot (search for the username you created)
2. **Send any message** to the bot (e.g., "Hello")
3. **Get the chat ID** by visiting: `https://api.telegram.org/bot<BOT_TOKEN>/getUpdates`
4. **Find your chat ID** in the response (usually a positive number)

#### Option B: Group Chat (Team Notifications)
1. **Create a group** in Telegram
2. **Add your bot** to the group
3. **Send a message** mentioning the bot (e.g., "@your_bot_name test")
4. **Get the chat ID** by visiting: `https://api.telegram.org/bot<BOT_TOKEN>/getUpdates`
5. **Find the group chat ID** in the response (usually negative, starting with -100)

### 3. Configure Environment Variables

Add these to your `.env` file:

```env
# Telegram Bot Configuration for Error Notifications
TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz
TELEGRAM_CHAT_ID=-1001234567890
TELEGRAM_RATE_LIMIT_MESSAGES=3
TELEGRAM_RATE_LIMIT_WINDOW=300
```

**Configuration Options:**
- `TELEGRAM_BOT_TOKEN`: Your bot token from @BotFather
- `TELEGRAM_CHAT_ID`: Chat ID where notifications will be sent
- `TELEGRAM_RATE_LIMIT_MESSAGES`: Max messages per window (default: 3)
- `TELEGRAM_RATE_LIMIT_WINDOW`: Time window in seconds (default: 300 = 5 minutes)

### 4. Test the Setup

#### Test Bot Connection
```bash
curl http://localhost:8080/api/v1/telegram/test/connection
```

#### Trigger Test Error
```bash
curl -X POST http://localhost:8080/api/v1/telegram/test/error \
  -H "Content-Type: application/json" \
  -d '{
    "error_type": "test_error",
    "message": "Testing Telegram notifications",
    "context": {"test": true}
  }'
```

#### Test Multiple Error Types
```bash
curl -X POST http://localhost:8080/api/v1/telegram/test/multiple
```

## Error Priority Levels

### 🔥 Critical Errors (Always sent, never silent)
- `database_connection_failed`
- `cirx_transfer_failed`
- `payment_verification_failed`
- `blockchain_client_error`
- `wallet_configuration_error`

### ⚠️ High Priority Errors (Sent with notification)
- `transaction_timeout`
- `api_rate_limit_exceeded`
- `authentication_failure`
- `invalid_transaction_state`
- `blockchain_rpc_error`

### 📝 Low Priority Errors (Sent silently)
- All other error types

## Integration with Existing Code

The system automatically integrates with your existing logging. Just add `error_type` to your log context:

```php
// Before (existing code)
$logger->error('Payment verification failed', [
    'transaction_id' => $transactionId,
    'tx_hash' => $txHash
]);

// After (with Telegram notifications)
$logger->error('Payment verification failed', [
    'error_type' => 'payment_verification_failed',  // Add this line
    'transaction_id' => $transactionId,
    'tx_hash' => $txHash
]);
```

## Message Format

Telegram notifications include:

```
🔴 CIRX Backend Alert

🔴 Error Type: payment_verification_failed
📊 Level: ERROR
📝 Message: Payment verification failed for transaction
⏰ Time: 2024-08-19 15:30:25 UTC
🖥️ Server: cirx-backend-01
🌍 Environment: production

📊 Context:
• transaction_id: `tx_abc123`
• tx_hash: `0x1234...5678`
• blockchain: `ethereum`
```

## Security Features

✅ **Sensitive data protection**: Automatically redacts passwords, keys, tokens
✅ **Rate limiting**: Prevents notification spam
✅ **Message length limiting**: Truncates large contexts to fit Telegram limits
✅ **Production safety**: Test endpoints disabled in production
✅ **Error isolation**: Telegram failures don't break main logging system

## Rate Limiting

The system prevents notification spam with smart rate limiting:

- **Default**: 3 messages per error type per 5 minutes
- **Configurable**: Adjust via environment variables
- **Per error type**: Each error type has its own limit
- **Critical bypass**: Critical errors can override limits when necessary

## Troubleshooting

### Bot Token Issues
```bash
# Test bot token manually
curl "https://api.telegram.org/bot<YOUR_TOKEN>/getMe"
```

### Chat ID Issues
```bash
# Get chat updates to find correct chat ID
curl "https://api.telegram.org/bot<YOUR_TOKEN>/getUpdates"
```

### Permission Issues
- Ensure bot is added to group (for group chats)
- Ensure bot has permission to send messages
- For channels: Bot needs admin permissions

### Network Issues
- Check firewall settings for outbound HTTPS (port 443)
- Verify DNS resolution for `api.telegram.org`

### Debug Mode
Enable detailed logging by setting:
```env
LOG_LEVEL=debug
```

## Advanced Configuration

### Multiple Chat IDs (Future Enhancement)
You can modify the code to support different chat IDs for different error levels:

```env
TELEGRAM_CRITICAL_CHAT_ID=-1001234567890
TELEGRAM_HIGH_PRIORITY_CHAT_ID=-1001234567891
TELEGRAM_LOW_PRIORITY_CHAT_ID=-1001234567892
```

### Custom Rate Limits per Error Type
Modify `TelegramHandler.php` to set different limits:

```php
// In TelegramHandler constructor
$this->customRateLimits = [
    'cirx_transfer_failed' => ['messages' => 1, 'window' => 600], // 1 per 10 minutes
    'general_error' => ['messages' => 5, 'window' => 300]  // 5 per 5 minutes
];
```

## File Structure

```
backend/src/Services/
├── TelegramNotificationService.php  # Main notification service
├── TelegramHandler.php              # Monolog handler integration
└── LoggerService.php                # Enhanced with Telegram support

backend/src/Controllers/
└── TelegramTestController.php       # Testing endpoints (dev only)

backend/
├── .env.example                     # Updated with Telegram config
└── TELEGRAM_SETUP.md               # This guide
```

## Production Checklist

- [ ] Bot token configured in production `.env`
- [ ] Chat ID verified and tested
- [ ] Test endpoints disabled (automatic in production)
- [ ] Rate limits configured appropriately
- [ ] Team has access to the Telegram chat
- [ ] Monitoring alerts set up for notification failures

## Support

If you encounter issues:

1. Check the application logs for Telegram-related errors
2. Verify bot token and chat ID with manual API calls
3. Test with the provided endpoints in development
4. Ensure network connectivity to Telegram API

---

**Note**: Test endpoints (`/api/v1/telegram/test/*`) are automatically disabled in production for security.
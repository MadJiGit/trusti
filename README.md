# Trusti - Email Queue System

A robust, scalable email queue system built with PHP, featuring concurrent worker processing, idempotency guarantees, and delivery status tracking.

## Overview

Trusti is a production-ready email queue system designed to handle high-volume email processing with reliability and fault tolerance. The system implements industry-standard patterns including:

- **Queue-based email processing** with MySQL backend
- **Concurrent worker support** with row-level locking
- **Idempotency guarantees** for safe retries
- **Delivery status tracking** via provider callbacks
- **Automatic cleanup** of stuck/failed emails
- **Provider abstraction** supporting multiple email providers

## Features

### Core Functionality
- ✅ **Asynchronous Email Queue** - Add emails to queue and process them asynchronously
- ✅ **Multiple Workers** - Run multiple concurrent workers without conflicts
- ✅ **Row-Level Locking** - `FOR UPDATE SKIP LOCKED` prevents duplicate processing
- ✅ **Idempotency Keys** - Safe retries using unique transaction identifiers
- ✅ **Status Tracking** - Track emails through: queued → processing → sent → delivered
- ✅ **Delivery Verification** - Automatic checking of delivery status via provider API
- ✅ **Error Handling** - Automatic retries with configurable limits
- ✅ **Cleanup Jobs** - Automatic cleanup of stuck emails in processing/sent states

### Technical Implementation
- **Database transactions** for atomic operations
- **PSR-4 autoloading** with proper namespaces
- **Repository pattern** for clean data access
- **Service layer** for business logic
- **Provider abstraction** for easy integration of new email services
- **Environment-based configuration** via `.env` files

## Architecture

```
┌─────────────┐
│   Client    │
│ (push-notif)│
└──────┬──────┘
       │
       ▼
┌─────────────────┐
│  Email Queue    │
│   (MySQL DB)    │
└────────┬────────┘
         │
    ┌────┴────┐
    ▼         ▼
┌────────┐ ┌────────┐
│Worker 1│ │Worker 2│
└────┬───┘ └───┬────┘
     │         │
     └────┬────┘
          ▼
    ┌──────────────┐
    │   Provider   │
    │     API      │
    └──────────────┘
```

## Project Structure

```
trusti/
├── db/
│   ├── DBConnect.php               # Database connection
│   ├── emails_db.sql               # Database emails schema
│   └── provider_db.sql             # Database provider schema
├── EmailProvider/
│   ├── AbstractEmailProvider.php   # Base provider class
│   ├── BrevoProvider.php           # Brevo email provider
│   ├── MailtrapProvider.php        # Mailtrap email provider
│   ├── MailgunProvider.php         # Mailgun email provider
│   ├── MockProvider.php            # Mock email provider (for testing)
│   ├── EmailProviderInterface.php  # Provider interface
│   └── EmailProviderFactory.php    # Factory for providers
├── Enum/
│   └── StatusCode.php              # Email status enum
├── Repository/
│   ├── EmailRepository.php         # Email data access
│   └── StatusRepository.php        # Status data access
├── Service/
│   ├── EmailWorker.php             # Main worker logic
│   └── StatusCache.php             # In-memory status cache
├── scripts/
│   ├── process-queue.php           # Worker script
│   └── push-notif.php              # Add email to queue
├── provider/
│   └── api.php                     # Mock provider API
└── logs/                           # Worker logs
```

## Requirements

- PHP 8.1+
- MySQL 8.0+
- Composer
- Extensions: PDO, pdo_mysql, curl, json

## Installation

1. **Clone the repository**
```bash
git clone <repository-url>
cd trusti
```

2. **Install dependencies**
```bash
composer install
```

3. **Configure environment**
```bash
cp .env.example .env
```

Edit `.env` with your database credentials:
```env
DB_HOST=localhost
DB_NAME=trusti
DB_USER=root
DB_PASS=your_password

EMAIL_PROVIDER=mock
EMAIL_FROM=noreply@trusti.com
QUEUE_MAX_RETRIES=3
```

4. **Create database and tables**
```bash
mysql -u root -p < db/emails_db.sql
mysql -u root -p < db/provider_db.sql
```

## Usage

### 1. Start the Provider API (for testing)

```bash
php -S localhost:8001 provider/api.php
```

This starts a mock email provider that simulates email sending and delivery tracking.

### 2. Add Emails to Queue

```bash
php scripts/push-notif.php "user@example.com" "Test Subject" "Email body content"
```

### 3. Start Worker(s)

**Single worker:**
```bash
php scripts/process-queue.php
```

**Multiple workers (in separate terminals):**
```bash
# Terminal 1
php scripts/process-queue.php

# Terminal 2
php scripts/process-queue.php

# Terminal 3
php scripts/process-queue.php
```

Each worker will:
- Fetch emails from the queue (with row locking)
- Send via provider API
- Track delivery status
- Log all activity to `logs/worker_{PID}.log`

### 4. Monitor Logs

```bash
# Watch specific worker
tail -f logs/worker_12345.log

# Watch all workers
tail -f logs/worker_*.log
```

## Testing Concurrent Workers

To verify that row-level locking prevents duplicate processing:

1. **Add multiple emails:**
```bash
for i in {1..20}; do
    php scripts/push-notif.php "test$i@example.com" "Test $i" "Body $i"
done
```

2. **Start 2 workers simultaneously:**
```bash
# Terminal 1
php scripts/process-queue.php > worker1.log 2>&1 &

# Terminal 2
php scripts/process-queue.php > worker2.log 2>&1 &
```

3. **Verify no duplicates:**
```bash
# Each email should be processed by only ONE worker
grep "Processing email ID" worker*.log | sort
```

## Email Lifecycle

1. **Queued** - Email added to queue via `push-notif.php`
2. **Processing** - Worker picks up email (row locked)
3. **Sent** - Provider API accepts the email
4. **Delivered** - Provider confirms delivery (checked periodically)
5. **Failed** - Email failed to send or deliver (after max retries)

## Configuration

### Worker Settings

Edit `scripts/process-queue.php`:

```php
$insideSleep = 5;           // Sleep between email processing
$outsideSleep = 5;          // Sleep between queue checks
$processingOlderThan = 60;  // Cleanup stuck PROCESSING emails (seconds)
$sentOlderThan = 90;        // Cleanup stuck SENT emails (seconds)
```

### Email Providers

The system supports multiple providers. Configure in `.env`:

```env
# Options: brevo, mailtrap, mock
EMAIL_PROVIDER=brevo
```

Add your provider API key:
```env
BREVO_API_KEY=your_api_key_here
MAILTRAP_API_KEY=your_api_key_here
```

## Database Schema

### Tables

**`email_statuses`** - Status reference table
- `id` - Primary key
- `code` - Status code (queued, processing, sent, delivered, failed)
- `display_order` - Display order for UI

**`emails`** - Email queue
- `id` - Primary key
- `idempotency_key` - Unique transaction ID (for safe retries)
- `email` - Recipient email
- `subject` - Email subject
- `body` - Email body
- `status_id` - Foreign key to email_statuses
- `message_id` - Provider's message ID
- `provider` - Provider name (brevo, mailtrap, etc.)
- `retries` - Retry counter
- `error_message` - Error details if failed
- `created_at`, `updated_at` - Timestamps

**`provider_emails`** - Provider tracking (for mock API)
- `id` - Primary key
- `idempotency_key` - Client's transaction ID
- `message_id` - Provider's generated message ID
- `status_id` - Foreign key to email_statuses

## Key Concepts

### Idempotency

Every email has a unique `idempotency_key` (UUID). If the same email is sent multiple times (due to retries), the provider will return the same `message_id`, preventing duplicates.

### Row-Level Locking

Workers use MySQL's `FOR UPDATE SKIP LOCKED` to:
- Lock rows when fetching from queue
- Skip already-locked rows
- Prevent race conditions between workers

```sql
SELECT * FROM emails
WHERE status_id = 'queued'
LIMIT 10
FOR UPDATE SKIP LOCKED;
```

### Delivery Status Checking

Workers periodically check delivery status for emails in "SENT" state:
- Query provider API with `idempotency_key`
- Update status to "DELIVERED" when confirmed
- Mark as "FAILED" if delivery fails

## API Endpoints (Provider)

### POST / - Send Email
```json
{
  "idempotency_key": "uuid-v4-here",
  "sender": { "email": "from@example.com" },
  "to": [{ "email": "to@example.com" }],
  "subject": "Subject",
  "textContent": "Body"
}
```

**Response (201):**
```json
{
  "messageId": "<provider-timestamp-uuid@trusti.local>"
}
```

### GET /?idempotency_key=xxx - Check Status
**Response (200):**
```json
{
  "events": [
    {
      "event": "delivered",
      "reason": "Provider delivery successful"
    }
  ]
}
```

## Troubleshooting

### Workers not processing emails
- Check MySQL connection in `.env`
- Verify provider API is running: `curl http://localhost:8001`
- Check logs: `tail -f logs/worker_*.log`

### Emails stuck in PROCESSING
- Automatic cleanup runs every 2 cycles (default: 60 seconds)
- Manually reset: `UPDATE emails SET status_id = 1 WHERE status_id = 2`

### Duplicate emails sent
- Verify row locking is working: Check `getTenEmails()` uses `FOR UPDATE SKIP LOCKED`
- Ensure transactions are committed after setting PROCESSING status

## Production Deployment

For production use:

1. **Use a real email provider** (Brevo, Mailtrap, SendGrid, etc.)
2. **Run workers as system services** (systemd, supervisor)
3. **Configure proper logging** (rotate logs, send to monitoring)
4. **Set appropriate retry limits** based on your SLA
5. **Monitor queue depth** and worker performance
6. **Use connection pooling** for database
7. **Add rate limiting** if provider has limits

## License

MIT

## Contributing

Pull requests welcome! Please ensure:
- Code follows PSR-12 standards
- All tests pass
- Documentation is updated

## Author

Mladen Raykov

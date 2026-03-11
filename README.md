# Slot Booking API

A robust Laravel 12 API service for managing time slot bookings with capacity constraints, temporary holds, and idempotent operations. Designed for high-concurrency scenarios.

**Repository**: [github.com/SirAndrewGotham/LogisticsApiTest](github.com/SirAndrewGotham/LogisticsApiTest)   
**Contact**: [andreogotema@gmail.com](mailto:andreogotema@gmail.com)   
                [AndrewGotham.ru](https://andrewgotham.ru)   
                [Andrew Gotham on Telegram](https://t.me/SireAndrewGotham)

## 🚀 Features
- **Slot Management**: Create and manage slots with limited capacity
- **Temporary Holds**: Reserve slots temporarily with 5-minute expiration
- **Idempotent Operations**: Safe retry mechanism with Idempotency-Key
- **Cache Protection**: Redis-based caching with stampede prevention
- **Atomic Transactions**: Database-level consistency guarantees
- **Overbooking Protection**: Prevents capacity overflow in concurrent scenarios

## 🏗️ Architecture
- **Framework**: Laravel 12 (PHP 8.2+)
- **Database**: MySQL 8+
- **Cache**: Redis (recommended)
- **Queue**: Laravel Horizon (optional, for job processing)

## 📦 Installation

### Prerequisites
- PHP 8.2 or higher
- Composer 2.5+
- MySQL 8.0+
- Redis 6+ (recommended)

### Step 1: Clone and Setup
```bash
git clone https://github.com/SirAndrewGotham/LogisticsApiTest.git
cd LogisticsApiTest
cp .env.example .env
```

### Step 2: Configure Environment
Edit `.env` file:
```env
APP_NAME="Slot Booking API"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=https://LogisticsApiTest.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=slot_booking
DB_USERNAME=root
DB_PASSWORD=

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Step 3: Install and Setup
```bash
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

*Note for Laravel 12: This project uses the framework's lean default structure. An `AppServiceProvider` is not included by default. Service bindings, if needed later, can be registered in `bootstrap/providers.php` .*

## 🔧 API Endpoints & Examples

### 1. Get Available Slots (Cached)
```bash
curl -X GET https://LogisticsApiTest.test/api/slots/availability
```

### 2. Create a Hold (Idempotent)
```bash
curl -X POST https://LogisticsApiTest.test/api/slots/1/hold \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  -d '{"user_id": 123}'
```

### 3. Confirm a Hold
```bash
curl -X POST https://LogisticsApiTest.test/api/holds/45/confirm \
  -H "Content-Type: application/json"
```

### 4. Cancel a Hold
```bash
curl -X DELETE https://LogisticsApiTest.test/api/holds/45 \
  -H "Content-Type: application/json"
```

## 🧪 Testing
Run the test suite to verify core functionality including idempotency and capacity checks:
```bash
php artisan test

# Run tests in parallel for speed (requires brianium/paratest)
php artisan test --parallel

# Generate test coverage report
php artisan test --coverage
```
*Laravel's testing environment automatically handles test database creation and uses array drivers for session/cache .*

## 🔒 Idempotency & Cache Strategy
- **Idempotency Keys**: UUID v4 stored in Redis with 24-hour TTL. Repeated requests with the same key return the original response.
- **Cache Stampede Protection**: Slot availability is cached for 5-15 seconds (randomized TTL) using mutex locks (`Cache::lock()`) to prevent redundant calculations under load.
- **Cache Invalidation**: The availability cache is invalidated after any hold confirmation or cancellation.

## 🛠️ Development Notes (Laravel 12)
- **Service Classes**: Business logic is placed in `app/Services/` (e.g., `SlotService`) to keep controllers thin .
- **Validation**: Uses Form Request classes (`php artisan make:request`) for clean input validation .
- **Code Style**: The project uses Laravel Pint for automatic code formatting .
- **Performance**: Utilize Laravel's built-in optimizations: `php artisan config:cache` and `php artisan route:cache` for production .

## 📄 License
This project is proprietary software. All rights reserved.

## 📞 Support
For issues or questions regarding this implementation:
- Review the `context.md` file for architectural details.
- Contact: `andreogotema@gmail.com`   
            [Andrew Gotham on Telegram](https://t.me/SirAndrewGotham)

---

*Built with Laravel 12 following modern best practices for clean, maintainable, and testable code.*

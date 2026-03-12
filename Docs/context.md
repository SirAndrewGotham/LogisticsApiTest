# Context for the Laravel Slot Booking API

## 📋 Project Overview
**Project**: Slot Booking API Service
**Framework**: Laravel 12 (PHP 8.2+)
**Database**: MySQL 8+
**Repository**: `github.com/SirAndrewGotham/LogisticsApiTest`
**Contact**: `andreogotema@gmail.com`
**Website**: `AndrewGotham.ru`

## 🏗️ Laravel 12 Project Structure & Conventions
This project uses the lean application skeleton introduced in Laravel 12. Key points:
- **No Default AppServiceProvider**: Laravel 12 does not include an `AppServiceProvider` in its default structure. Service bindings are typically handled via the `bootstrap/providers.php` file or by creating specific providers only when needed .
- **Service Layer**: Business logic is encapsulated in dedicated Service classes (e.g., `SlotService`) to keep controllers thin and focused on HTTP handling, adhering to the Single Responsibility Principle .
- **Configuration**: Environment variables are accessed via `config()` helper after being defined in configuration files, not directly via `env()` .
- **Testing**: The project is configured for testing with PHPUnit/Pest. Laravel's testing environment automatically sets the configuration to `testing` and uses array drivers for sessions/cache .

## 🎯 Core Requirements
This API service manages slot bookings (warehouse windows, delivery slots, or client appointments) with:
- Limited capacity slots
- Temporary holds with expiration
- Capacity management to prevent overbooking
- Idempotent operations
- Cache protection (stampede prevention)

## 🔧 Key Implementation Details
### Database Schema
**slots table**:
- `id`, `capacity`, `remaining`, `created_at`, `updated_at`
- Check constraint: `remaining >= 0 AND remaining <= capacity`

**holds table**:
- `id`, `slot_id`, `user_id`, `status` (held/confirmed/cancelled/expired)
- `idempotency_key` (UNIQUE), `expires_at`, `created_at`, `updated_at`
- Indexes: `(slot_id, status)`, `(expires_at)`, `(idempotency_key)`

### API Endpoints
```
GET    /slots/availability      # Cached 5-15s with stampede protection
POST   /slots/{id}/hold         # Idempotent (Idempotency-Key header)
POST   /holds/{id}/confirm      # Atomic capacity decrement
DELETE /holds/{id}              # Cancel hold, return capacity
```

## 🚀 Laravel 12 Best Practices Emphasized
1.  **Service Classes for Business Logic**: Controllers delegate core operations to `SlotService` and `CacheService`, keeping them clean and testable .
2.  **Form Request Validation**: Use dedicated Request classes (e.g., `StoreHoldRequest`) for input validation, separating concerns from controllers .
3.  **Database Transactions**: Wrap operations that affect multiple records (like creating a hold and decrementing remaining capacity) in `DB::transaction` .
4.  **Efficient Eloquent**: Use Eloquent ORM features like model casting, accessors/mutators with closure syntax, and eager loading where applicable .
5.  **Artisan CLI**: Utilize Artisan commands for code generation (`make:controller`, `make:service`, `make:request`) and optimizations (`config:cache`, `route:cache`) .
6.  **Modern Testing**: Leverage Laravel's built-in testing tools, including database migrations per test and the ability to run tests in parallel for speed .

## 📧 Submission Requirements
- Repository link with complete code
- Video on Yandex.Disk with access to `serggit+phptest@yandex.ru`
- Email subject: "Видео-вакансия — результат - ФИО"
- Email sender: `andreogotema@gmail.com`
- README with setup instructions and curl examples
- Deadline: 10 days from assignment receipt

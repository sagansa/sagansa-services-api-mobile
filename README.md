# SAGANSA API Mobile - Laravel Backend

API Mobile Laravel untuk platform SAGANSA yang menyediakan layanan POS (Point of Sale), manajemen kehadiran, dan integrasi printer untuk bisnis multi-tenant.

## Fitur Utama

### 🔐 Authentication
- Login dengan Laravel Sanctum
- Token-based authentication
- Multi-role user support (owner, manager, cashier, staff)

### 🏪 Store Management
- Multi-tenant store management
- Store location with GPS coordinates
- Store radius configuration for attendance

### 📦 Product Management
- Product catalog with variants
- Product categories and units
- Store-specific product availability

### 🛒 Order Management
- Create and manage orders
- Order items with modifications
- Multiple payment types support
- Offline order support with sync

### 📍 Attendance (Presence)
- GPS-based check-in/check-out
- Photo attendance with location verification
- Leave request management
- Attendance history and reporting

### 🖨️ Printer Integration
- WiFi and Bluetooth printer support
- Multiple printer types (receipt, kitchen)
- Printer job queue management
- Real-time job status tracking

## Teknologi

- **Framework**: Laravel 11
- **PHP**: 8.2+
- **Database**: MySQL 8.0
- **Authentication**: Laravel Sanctum
- **Storage**: Laravel Storage (local/cloud)
- **Queue**: Laravel Queue for background jobs
- **Testing**: PHPUnit

## Instalasi

### Prerequisites
- PHP 8.2+
- MySQL 8.0+
- Composer
- Node.js & NPM

### Setup Database
```bash
# Copy environment file
cp .env.example .env

# Configure database in .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sagansa_api
DB_USERNAME=root
DB_PASSWORD=

# Run migrations
php artisan migrate

# Run seeders
php artisan db:seed
```

### Install Dependencies
```bash
# Install PHP dependencies
composer install

# Install NPM dependencies
npm install

# Generate application key
php artisan key:generate
```

### Storage Configuration
```bash
# Create storage link
php artisan storage:link

# Set proper permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

## Struktur Database

### Core Tables
- **tenants**: Multi-tenant organizations
- **users**: Application users with roles
- **stores**: Store locations
- **products**: Product catalog
- **orders**: Order transactions
- **attendances**: Attendance records
- **leave_requests**: Leave request management
- **printers**: Printer configuration
- **printer_jobs**: Print job queue

### Relationships
```
tenants -> users (one-to-many)
tenants -> stores (one-to-many)
tenants -> products (one-to-many)
stores -> orders (one-to-many)
users -> attendances (one-to-many)
stores -> attendances (one-to-many)
printers -> printer_jobs (one-to-many)
```

## API Endpoints

### Authentication
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `GET /api/auth/user` - Get current user

### Stores
- `GET /api/stores` - List stores
- `POST /api/stores` - Create store
- `GET /api/stores/{id}` - Get store details
- `PUT /api/stores/{id}` - Update store

### Products
- `GET /api/products` - List products
- `POST /api/products` - Create product
- `GET /api/products/{id}` - Get product details
- `PUT /api/products/{id}` - Update product

### Orders
- `GET /api/orders` - List orders
- `POST /api/orders` - Create order
- `GET /api/orders/{id}` - Get order details

### Attendance
- `GET /api/attendance` - List attendance
- `POST /api/attendance/checkin` - Check-in
- `POST /api/attendance/checkout` - Check-out
- `GET /api/attendance/history` - Attendance history

### Leave Requests
- `GET /api/leave-requests` - List leave requests
- `POST /api/leave-requests` - Submit leave request
- `GET /api/leave-requests/{id}` - Get leave request details

### Printers
- `GET /api/printers` - List printers
- `POST /api/printers` - Create printer
- `POST /api/printer-jobs` - Create print job
- `GET /api/printer-jobs/{id}` - Get job status

## Multi-Tenant Architecture

Aplikasi ini menggunakan pendekatan multi-tenant dimana:
- Setiap tenant memiliki data yang terisolasi
- Users hanya dapat mengakses data dalam tenant mereka
- Tenant context ditentukan otomatis dari user token

### Tenant Isolation
- Row-level security di database
- Automatic tenant scoping di models
- Middleware untuk validasi tenant

## Security Features

### Authentication
- Laravel Sanctum untuk API authentication
- Token-based authentication
- Token expiration dan refresh

### Authorization
- Role-based access control (RBAC)
- Tenant-based data isolation
- API rate limiting

### Data Protection
- Input validation dan sanitization
- SQL injection prevention via Eloquent ORM
- XSS protection
- File upload security

## Testing

### Run Tests
```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

### Test Categories
- **Unit Tests**: Model dan service testing
- **Feature Tests**: API endpoint testing
- **Integration Tests**: Database dan external service testing

## Deployment

### Production Setup
1. Configure environment variables
2. Setup database dengan proper indexing
3. Configure queue workers untuk background jobs
4. Setup monitoring dan logging
5. Configure backup strategy

### Environment Variables
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api-mobile.sagansa.id

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=sagansa_prod
DB_USERNAME=sagansa_user
DB_PASSWORD=secure_password

SANCTUM_STATEFUL_DOMAINS=api-mobile.sagansa.id
SESSION_DOMAIN=.sagansa.id
```

## Monitoring

### Application Monitoring
- Laravel Telescope untuk development
- Sentry untuk error tracking
- Laravel Log untuk application logs

### Performance Monitoring
- Query performance monitoring
- API response time tracking
- Database slow query log

## Contributing

1. Fork repository
2. Create feature branch
3. Commit perubahan
4. Push ke branch
5. Buat Pull Request

## License

Proprietary software - All rights reserved by SAGANSA Platform.

## Support

Untuk pertanyaan dan support, hubungi:
- Email: support@sagansa.id
- Documentation: [API Documentation](API_DOCUMENTATION.md)
- Issues: [GitHub Issues](https://github.com/sagansa/api-mobile/issues)
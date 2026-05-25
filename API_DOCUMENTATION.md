# SAGANSA API Mobile Documentation

## Authentication

### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password",
  "device_name": "iPhone 12"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "1|laravel_sanctum_token",
    "user": {
      "id": "uuid",
      "name": "John Doe",
      "email": "user@example.com",
      "tenant_id": "uuid",
      "role": "pos_staff"
    }
  }
}
```

### Logout
```http
POST /api/auth/logout
Authorization: Bearer {token}
```

### Get Current User
```http
GET /api/auth/user
Authorization: Bearer {token}
```

## Attendance (Presence)

### Check-in
```http
POST /api/attendance/checkin
Authorization: Bearer {token}
Content-Type: multipart/form-data

Parameters:
- store_id (required): UUID of the store
- latitude (required): Current latitude
- longitude (required): Current longitude
- accuracy (required): GPS accuracy in meters
- photo (required): Photo file for attendance
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "user_id": "uuid",
    "store_id": "uuid",
    "type": "regular",
    "latitude": -6.200000,
    "longitude": 106.816666,
    "accuracy": 5.2,
    "photo_path": "attendances/photo123.jpg",
    "status": "present",
    "check_in_at": "2024-01-15T08:00:00Z",
    "store": {
      "id": "uuid",
      "name": "Store Name",
      "nickname": "Store Nickname"
    }
  }
}
```

### Check-out
```http
POST /api/attendance/checkout
Authorization: Bearer {token}
Content-Type: multipart/form-data

Parameters:
- attendance_id (required): UUID of the attendance record
- latitude (required): Current latitude
- longitude (required): Current longitude
- accuracy (required): GPS accuracy in meters
- photo (optional): Photo file for check-out
```

### Get Attendance History
```http
GET /api/attendance/history
Authorization: Bearer {token}

Query Parameters:
- store_id (optional): Filter by store
- start_date (optional): Filter by start date (YYYY-MM-DD)
- end_date (optional): Filter by end date (YYYY-MM-DD)
- status (optional): Filter by status (present, late, absent, leave)
- per_page (optional): Items per page (default: 10)
```

## Leave Requests

### Submit Leave Request
```http
POST /api/leave-requests
Authorization: Bearer {token}
Content-Type: application/json

{
  "start_date": "2024-01-15",
  "end_date": "2024-01-17",
  "leave_type": "annual",
  "reason": "Family emergency"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "user_id": "uuid",
    "tenant_id": "uuid",
    "start_date": "2024-01-15",
    "end_date": "2024-01-17",
    "leave_type": "annual",
    "reason": "Family emergency",
    "status": "pending",
    "created_at": "2024-01-10T10:00:00Z"
  },
  "message": "Leave request submitted successfully"
}
```

### Get Leave Requests
```http
GET /api/leave-requests
Authorization: Bearer {token}

Query Parameters:
- status (optional): Filter by status (pending, approved, rejected)
- per_page (optional): Items per page (default: 10)
```

### Get Leave Request Details
```http
GET /api/leave-requests/{leaveRequestId}
Authorization: Bearer {token}
```

## Orders

### Create Order
```http
POST /api/orders
Authorization: Bearer {token}
Content-Type: application/json

{
  "store_id": "uuid",
  "items": [
    {
      "product_id": "uuid",
      "variant_id": "uuid",
      "quantity": 2,
      "modifications": [
        {
          "modification_id": "uuid",
          "quantity": 1
        }
      ]
    }
  ],
  "payment_type_id": "uuid",
  "is_offline": false,
  "device_identifier": "pos_device_001"
}
```

### Get Orders
```http
GET /api/orders
Authorization: Bearer {token}

Query Parameters:
- store_id (optional): Filter by store
- status (optional): Filter by status
- start_date (optional): Filter by start date
- end_date (optional): Filter by end date
- per_page (optional): Items per page (default: 10)
```

## Products

### Get Products
```http
GET /api/products
Authorization: Bearer {token}

Query Parameters:
- store_id (optional): Filter by store
- category_id (optional): Filter by category
- is_active (optional): Filter by active status
- search (optional): Search by name
- per_page (optional): Items per page (default: 10)
```

## Stores

### Get Stores
```http
GET /api/stores
Authorization: Bearer {token}
```

## Printers

### Get Printers
```http
GET /api/printers
Authorization: Bearer {token}

Query Parameters:
- store_id (optional): Filter by store
```

### Create Printer Job
```http
POST /api/printer-jobs
Authorization: Bearer {token}
Content-Type: application/json

{
  "printer_id": "uuid",
  "order_id": "uuid",
  "job_type": "receipt",
  "payload": {
    "order_number": "ORD-001",
    "items": [...],
    "total": 50000
  }
}
```

### Get Printer Job Status
```http
GET /api/printer-jobs/{jobId}
Authorization: Bearer {token}
```

## Error Responses

All error responses follow this format:
```json
{
  "success": false,
  "message": "Error message description",
  "errors": {
    "field_name": ["Error message for field"]
  }
}
```

Common HTTP status codes:
- `200`: Success
- `201`: Created
- `400`: Bad Request
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not Found
- `422`: Validation Error
- `500`: Internal Server Error

## Rate Limiting

API requests are rate-limited to:
- 60 requests per minute for authenticated users
- 10 requests per minute for unauthenticated endpoints

## Multi-Tenant Architecture

All API endpoints are tenant-scoped. Users can only access data within their tenant organization. The tenant context is automatically determined from the authenticated user's token.
# e-Disaster API Documentation

## 📚 Interactive API Documentation

The complete API documentation is available at: **http://localhost:8000/api/documentation**

This interactive Swagger UI allows you to:

-   ✅ View all available endpoints with detailed descriptions
-   ✅ Test API calls directly from the browser
-   ✅ See request/response examples with real data
-   ✅ Authenticate with your API token
-   ✅ Explore all parameters and filters

## 🚀 Quick Start

### 1. **Authentication**

```bash
# Login to get access token
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"fathur@edisaster.test","password":"password"}'
```

### 2. **Use the Token**

```bash
# Add token to all subsequent requests
curl -X GET http://localhost:8000/api/v1/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## 📋 Complete API Endpoints

### **System** (`/api/v1/`)

-   `GET /health` - Health check endpoint

### **Authentication** (`/api/v1/auth/`)

-   `POST /login` - User login with email/password
-   `POST /register` - User registration (volunteers only, requires admin approval)
-   `POST /logout` - User logout (invalidates token)
-   `GET /me` - Get current user profile with full details

### **Profile Management** (`/api/v1/`)

-   `PUT /profile` - Update user profile information
-   `PUT /profile/password` - Change user password
-   `POST /profile/picture` - Upload/update profile picture
-   `DELETE /profile/picture` - Delete profile picture

### **Dashboard** (`/api/v1/`)

-   `GET /dashboard` - Get dashboard statistics and recent data

### **Disasters** (`/api/v1/disasters/`)

-   `GET /` - List all disasters (with pagination, search, filters)
-   `POST /` - Create new disaster (auto-assigns creator as volunteer)
-   `GET /{id}` - Get disaster details with pictures and volunteers
-   `PUT /{id}` - Update disaster (assigned users only)
-   `PUT /{id}/cancel` - Cancel disaster with reason (assigned users only)

### **Disaster Volunteers** (`/api/v1/disasters/{id}/volunteers/`)

-   `GET /` - List disaster volunteers
-   `POST /` - Self-assign to disaster
-   `DELETE /{volunteerId}` - Remove volunteer from disaster

### **Disaster Reports** (`/api/v1/disasters/{id}/reports/`)

-   `GET /` - List disaster reports (assigned users only)
-   `POST /` - Create disaster report (assigned users only)
-   `GET /{reportId}` - Get report details (assigned users only)
-   `PUT /{reportId}` - Update report (assigned users only)
-   `DELETE /{reportId}` - Delete report (assigned users only)

### **Disaster Victims** (`/api/v1/disasters/{id}/victims/`)

-   `GET /` - List disaster victims (assigned users only)
-   `POST /` - Create victim record (assigned users only)
-   `GET /{victimId}` - Get victim details (assigned users only)
-   `PUT /{victimId}` - Update victim record (assigned users only)
-   `DELETE /{victimId}` - Delete victim record (assigned users only)

### **Disaster Aids** (`/api/v1/disasters/{id}/aids/`)

-   `GET /` - List disaster aids (assigned users only)
-   `POST /` - Create aid record (assigned users only)
-   `GET /{aidId}` - Get aid details (assigned users only)
-   `PUT /{aidId}` - Update aid record (assigned users only)
-   `DELETE /{aidId}` - Delete aid record (assigned users only)

### **Notifications** (`/api/v1/notifications/`)

-   `GET /` - List user notifications (with pagination, search, filters)
-   `GET /stats` - Get notification statistics
-   `PUT /read-all` - Mark all notifications as read
-   `DELETE /read-all` - Delete all read notifications
-   `GET /{id}` - Get specific notification details
-   `PUT /{id}/read` - Mark notification as read
-   `DELETE /{id}` - Delete specific notification

### **Pictures** (`/api/v1/pictures/`)

-   `POST /{modelType}/{modelId}` - Upload image for any model
-   `GET /{modelType}/{modelId}` - List images for a model
-   `GET /{modelType}/{modelId}/{imageId}` - Get specific image details
-   `PUT /{modelType}/{modelId}/{imageId}` - Update image metadata
-   `DELETE /{modelType}/{modelId}/{imageId}` - Delete image

**Model Types:** `disaster`, `disaster_report`, `disaster_victim`, `disaster_aid`

## 🔐 Authentication

All protected endpoints require a Bearer token in the Authorization header:

```
Authorization: Bearer YOUR_TOKEN_HERE
```

## 📝 Response Format

All API responses follow this format:

```json
{
  "message": "Success message",
  "data": { ... },
  "pagination": { ... } // For paginated responses
}
```

## 🚨 Error Handling

Errors are returned with appropriate HTTP status codes:

-   `200` - Success
-   `201` - Created
-   `400` - Bad Request
-   `401` - Unauthorized
-   `403` - Forbidden
-   `404` - Not Found
-   `422` - Validation Error
-   `500` - Server Error

## 🔍 Filtering & Search

Most list endpoints support:

-   **Pagination**: `page`, `per_page`
-   **Search**: `search` (searches relevant text fields)
-   **Filtering**: Various filters like `status`, `type`, `category`, `is_read`
-   **Sorting**: Default sorting by creation date (newest first)

## 🧪 Testing

Use the interactive Swagger UI at **http://localhost:8000/api/documentation** to test all endpoints directly from your browser.

## 📱 Mobile App Integration

This API is designed for mobile applications with:

-   Token-based authentication
-   JSON responses
-   File upload support
-   Pagination for large datasets
-   Real-time notification support
-   Comprehensive error handling

## 🎯 Key Features

-   **Role-based Access**: Different permissions for admins, officers, and volunteers
-   **Disaster Assignment**: Users must be assigned to disasters to manage them
-   **File Management**: Upload and manage images for all disaster-related entities
-   **Real-time Notifications**: Comprehensive notification system
-   **Audit Trail**: Track who made changes and when
-   **Data Integrity**: Soft deletes and status management

## 🎯 **Complete Coverage Achieved!**

✅ **All 44 API endpoints** are now fully documented with comprehensive Swagger annotations
✅ **Disaster Management** - Complete CRUD including update and cancel disaster
✅ **Disaster Reports** - Complete CRUD with detailed request/response examples
✅ **Disaster Victims** - Complete CRUD with status filtering and location data
✅ **Disaster Aids** - Complete CRUD with category filtering and quantity tracking
✅ **Notifications** - Complete notification system with statistics and bulk operations
✅ **Pictures** - Complete image management for all entities
✅ **Health Check** - System health monitoring endpoint
✅ **Interactive Testing** - All endpoints can be tested directly in Swagger UI
✅ **Professional Documentation** - Production-ready API documentation

### 🔧 **Fixed Missing Endpoints:**

-   ✅ **Disaster Update** (`PUT /disasters/{id}`) - Now documented
-   ✅ **Cancel Disaster** (`PUT /disasters/{id}/cancel`) - Now documented
-   ✅ **Health Check** (`GET /health`) - Now documented
-   ✅ **Complete Notifications** - All 7 notification endpoints documented
-   ✅ **Complete Pictures** - All 5 picture management endpoints documented

---

**Need Help?** Check the interactive documentation at http://localhost:8000/api/documentation

# Cheetah Uptime API Reference

RESTful API documentation for the Cheetah Uptime monitoring engine.

## Base URL
- **Local Dev:** `http://127.0.0.1:8000/api`
- **Docker:** `http://localhost:8000/api`

## Authentication
Authentication is powered by **Laravel Sanctum**. Protected endpoints require the `Authorization` header:

```http
Authorization: Bearer <your_api_token>
Accept: application/json
```

---

## 1. Authentication Endpoints

### Register User
Creates a new tenant user account and issues a personal access token.

- **Endpoint:** `POST /register`
- **Auth Required:** No
- **Request Body:**
  ```json
  {
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "secretPassword123"
  }
  ```
- **Response:** `201 Created`
  ```json
  {
    "message": "Register Success",
    "user": {
      "id": 1,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "created_at": "2026-10-31T12:00:00.000000Z"
    },
    "token": "1|qXy...secretToken"
  }
  ```

---

### Login User
Authenticates credentials and returns a personal access token.

- **Endpoint:** `POST /login`
- **Auth Required:** No
- **Request Body:**
  ```json
  {
    "email": "jane@example.com",
    "password": "secretPassword123"
  }
  ```
- **Response:** `200 OK`
  ```json
  {
    "message": "Login Success",
    "user": { "id": 1, "name": "Jane Doe", "email": "jane@example.com" },
    "token": "2|abZ...secretToken"
  }
  ```

---

### Get Profile
Returns details of the currently authenticated user.

- **Endpoint:** `GET /user`
- **Auth Required:** Yes
- **Response:** `200 OK`
  ```json
  {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com"
  }
  ```

---

### Logout
Revokes the current access token.

- **Endpoint:** `POST /logout`
- **Auth Required:** Yes
- **Response:** `200 OK`
  ```json
  {
    "message": "Logged Out"
  }
  ```

---

## 2. Monitor Management Endpoints

### List User Monitors
Returns all monitors scoped to the authenticated user, eager loading their latest ping check.

- **Endpoint:** `GET /monitors`
- **Auth Required:** Yes
- **Response:** `200 OK`
  ```json
  [
    {
      "id": 1,
      "name": "Production App",
      "url": "https://example.com",
      "check_interval": 1,
      "max_response_time_ms": 2000,
      "status": "up",
      "is_paused": false,
      "certificate_check_enabled": true,
      "certificate_status": "valid",
      "certificate_expiration_date": "2027-01-15T00:00:00.000000Z",
      "last_checked_at": "2026-10-31T12:05:00.000000Z",
      "latest_ping": {
        "id": 104,
        "monitor_id": 1,
        "status_code": 200,
        "response_time_ms": 84,
        "created_at": "2026-10-31T12:05:00.000000Z"
      }
    }
  ]
  ```

---

### Create Monitor
Creates a new URL monitor for the authenticated user.

- **Endpoint:** `POST /monitors`
- **Auth Required:** Yes
- **Request Body:**
  ```json
  {
    "name": "Main Storefront",
    "url": "https://example.com",
    "check_interval": 5,
    "keyword": "Under Maintenance",
    "certificate_check_enabled": true
  }
  ```
- **Response:** `201 Created`
  ```json
  {
    "message": "Monitor eklendi",
    "monitor": {
      "id": 2,
      "user_id": 1,
      "name": "Main Storefront",
      "url": "https://example.com",
      "check_interval": 5,
      "status": "pending"
    }
  }
  ```

---

### Update Monitor
Updates attributes of an existing monitor owned by the user.

- **Endpoint:** `PUT /monitors/{id}` (or `PATCH /monitors/{id}`)
- **Auth Required:** Yes
- **Request Body (all optional):**
  ```json
  {
    "name": "Updated Storefront",
    "url": "https://store.example.com",
    "check_interval": 1,
    "max_response_time_ms": 1500,
    "keyword": null
  }
  ```
- **Response:** `200 OK`
  ```json
  {
    "message": "Monitor updated successfully",
    "monitor": { "id": 2, "name": "Updated Storefront" }
  }
  ```

---

### Delete Monitor
Deletes a monitor owned by the authenticated user.

- **Endpoint:** `DELETE /monitors/{id}`
- **Auth Required:** Yes
- **Response:** `200 OK`
  ```json
  {
    "message": "Monitor Deleted"
  }
  ```

---

### Pause / Resume Monitor
Toggles monitoring execution without deleting historical statistics.

- **Pause:** `POST /monitors/{id}/pause`
- **Resume:** `POST /monitors/{id}/resume`
- **Auth Required:** Yes
- **Response:** `200 OK`
  ```json
  {
    "message": "Monitor paused successfully",
    "monitor": { "id": 2, "is_paused": true }
  }
  ```

---

### Monitor Stats & 24h History
Fetches 24-hour uptime percentage and recent response latency pings.

- **Endpoint:** `GET /monitors/{id}/stats`
- **Auth Required:** Yes
- **Response:** `200 OK`
  ```json
  {
    "uptime_24h": 99.82,
    "total_checks_24h": 1440,
    "recent": [
      {
        "status_code": 200,
        "response_time_ms": 112,
        "created_at": "2026-10-31T12:00:00.000000Z"
      }
    ]
  }
  ```

---

### Trigger Manual Check
Immediately dispatches check jobs to the queue.

- **Single Monitor:** `POST /monitors/{id}/check`
- **All User Monitors:** `POST /monitors/check`
- **Auth Required:** Yes
- **Response:** `202 Accepted`
  ```json
  {
    "message": "Check queued",
    "monitor_id": 2
  }
  ```

---

## 3. Activity Logs Endpoints

### List Recent Activity Logs
Fetches up to 100 recent check events across all monitors owned by the user.

- **Endpoint:** `GET /logs`
- **Auth Required:** Yes
- **Response:** `200 OK`
  ```json
  [
    {
      "id": 501,
      "monitor_id": 1,
      "status_code": 200,
      "response_time_ms": 94,
      "created_at": "2026-10-31T12:00:00.000000Z",
      "monitor": {
        "id": 1,
        "name": "Production App",
        "url": "https://example.com"
      }
    }
  ]
  ```

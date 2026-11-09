# Kiloneed Backend (Laravel API)

High-performance REST API backend for the **Kiloneed** geofenced African trade & services marketplace.

---

## 🚀 Quick Start

### 1. Start the Development Server
```bash
cd kiloneed-backend-laravel
php artisan serve
```
The API is served at `http://localhost:8000/api`.

### 2. Default Seeded Test Accounts
All pre-seeded test accounts share the default password `password123`.

| Email | Name | Role / Industry | Location |
|---|---|---|---|
| `user@kiloneed.com` | John Doe | Enterprise Buyer (Electronics) | Ikeja, Lagos |
| `adaeze@kiloneed.com` | Adaeze Okafor | Apex Digital Systems (Electronics) | Computer Village, Ikeja |
| `emeka@kiloneed.com` | Emeka Nwosu | Telecomms & Power (Electronics) | Alaba Int'l Market, Lagos |
| `halima@kiloneed.com` | Halima Sani | Aura Couture (Fashion) | Wuse II, Abuja |
| `bola@kiloneed.com` | Bola Tinubu Jr | Coastline Transit (Logistics) | Victoria Island, Lagos |

---

## 🗄️ Database & Environment Configuration

### SQLite (Default)
By default, the backend uses SQLite at `database/database.sqlite`. It requires zero daemon services and runs immediately out of the box.

### MySQL (Optional via Laragon / XAMPP)
To use MySQL, edit `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kiloneed
DB_USERNAME=root
DB_PASSWORD=
```
Then run:
```bash
php artisan migrate:fresh --seed
```

---

## 📡 API Endpoints Overview

### 🔐 Authentication (`/api/auth`)
- `POST /api/auth/register` - Create new user account (returns token & profile)
- `POST /api/auth/login` - Sign in with email & password
- `GET /api/auth/me` - Authenticated user info
- `PUT /api/auth/profile` - Update profile, bio, location, company, coords
- `POST /api/auth/logout` - Invalidate current session token

### 📦 Needs Feed & Marketplace (`/api/needs`)
- `GET /api/needs` - Paginated feed with geofence distance filter, category, privacy, search, and sort
- `POST /api/needs` - Create need with categories, privacy, expiry duration, and media
- `GET /api/needs/{id}` - Retrieve need with media, comments, and chats
- `PUT /api/needs/{id}` - Update need post
- `DELETE /api/needs/{id}` - Delete need post
- `POST /api/needs/{id}/click` - Track need clicks
- `POST /api/needs/{id}/share` - Increment share count
- `POST /api/needs/{id}/bookmark` - Toggle bookmark/save
- `POST /api/needs/{id}/repost` - Repost need
- `POST /api/needs/{id}/recommend` - Recommend need (reco)
- `POST /api/needs/{id}/mention-peers` - Recommend peers to attend to a need

### 💬 Need Discussions & In-App Direct Chats
- `GET /api/needs/{id}/comments` - Comments on a need
- `POST /api/needs/{id}/comments` - Add a comment
- `DELETE /api/comments/{id}` - Delete a comment
- `GET /api/needs/{id}/chats` - In-need buyer-supplier message thread
- `POST /api/needs/{id}/chats` - Send direct inquiry on a need

### 🤝 Trade Connections (`/api/connections`)
- `GET /api/connections` - List user connections
- `GET /api/connections/suggestions` - Network recommendations
- `GET /api/connections/pending` - Incoming requests
- `POST /api/connections/request` - Send connection request
- `POST /api/connections/{id}/accept` - Accept request
- `POST /api/connections/{id}/decline` - Decline request
- `DELETE /api/connections/{targetUserId}` - Disconnect

### 👥 Trade Groups (`/api/groups`)
- `GET /api/groups` - Explore trade groups
- `GET /api/groups/my` - User's joined groups
- `POST /api/groups` - Create new trade syndicate/group
- `GET /api/groups/{id}` - Group details and member count
- `POST /api/groups/{id}/join` - Join group
- `POST /api/groups/{id}/leave` - Leave group

### ✉️ Direct Messages (`/api/conversations`)
- `GET /api/conversations` - List active conversations
- `POST /api/conversations/start` - Start or open chat with user
- `GET /api/conversations/{id}` - Get message history
- `POST /api/conversations/{id}/messages` - Send direct message

### 🔔 Notifications & Reports (`/api/notifications`, `/api/reports`)
- `GET /api/notifications` - User notifications
- `POST /api/notifications/{id}/read` - Mark notification as read
- `POST /api/notifications/read-all` - Mark all notifications as read
- `POST /api/reports` - Submit report for safety & moderation

### 💳 Wallet & Sponsored Ads (`/api/transactions`, `/api/ads`)
- `GET /api/transactions` - Wallet balance and transaction history
- `POST /api/transactions/deposit` - Fund wallet
- `GET /api/ads` - Active sponsored listings
- `POST /api/ads` - Create sponsored ad campaign

### 📁 Media Upload (`/api/upload`)
- `POST /api/upload` - Multipart file upload (images, docs, videos) stored in public storage symlink.

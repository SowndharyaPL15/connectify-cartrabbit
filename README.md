# 💬 Connectify — Modern Messaging Platform

Connectify is a production-ready, real-time messaging platform inspired by WhatsApp Web. Built with **Laravel 10**, **Vanilla JS**, and **MySQL**, it features a glassmorphic UI, live interactions, Google OAuth, emoji reactions, and AI-powered chat translation. Fully containerized with Docker for isolated, seamless deployment. 

---

## ✨ Core Features
- **Real-time Messaging**: Instant delivery with read receipts and active polling.
- **Google OAuth**: Fast login and registration with standard `laravel/socialite`.
- **AI Tone Converter**: Rewrite messages in different tones — Formal, Friendly, Professional, and Funny — powered by Groq / OpenAI / Gemini.
- **AI-Powered Translations**: Real-time message translation using Groq (Llama-3).
- **Advanced Interactions**: Message reactions (👍, ❤️), forwarding, replies, and pinned chats.
- **Group Architecture**: Add members, exit groups, and block individual users.
- **Docker-Ready**: Instantly deployable isolated container stack (PHP-FPM + Nginx + MySQL).

---

## ⚙️ Environment Configuration

You must configure the environment variables correctly before running the application.

1. Copy the example file:
   ```bash
   cp .env.example .env
   ```
2. Generate the application key:
   ```bash
   php artisan key:generate
   ```
3. Update the `.env` with the following critical blocks:

**App Defaults:**
```env
APP_NAME=Connectify
APP_ENV=local
APP_URL=http://localhost:8000
```

**Database (Locally mapped):**
```env
DB_CONNECTION=sqlite
# OR if using MySQL locally:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=connectify
# DB_USERNAME=root
# DB_PASSWORD=
```

**Google OAuth (Important):**
```env
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URL=http://localhost:8000/auth/google/callback
```

**AI Translation (Groq):**
```env
AI_PROVIDER=groq
GROQ_API_KEY=your_groq_api_key_here
```

---

## 🗄️ Database Setup Instructions

The project uses Laravel's database migrations to automatically build the schema. All migration files are located safely in `database/migrations/`.

1. Depending on your `.env` choice above, ensure the database (SQLite file or MySQL database named `connectify`) actually exists.
   - For SQLite: `touch database/database.sqlite`
2. Run the migration to build all tables (including Users, Messages, Reactions, and Blocks):
   ```bash
   php artisan migrate
   ```
3. (Optional) Feed the database with sample users:
   ```bash
   php artisan db:seed
   ```

---

## 🚀 How to Run the Project Locally

There are **two entirely separate ways** to run this project depending on your needs.

### Option A: Standard Local Server (Artisan)
Ensure you have PHP 8.2+ and Composer installed.
1. Run `composer install`
2. Ensure directories have proper permissions (for file uploading):
   ```bash
   mkdir -p public/uploads/profiles public/uploads/messages
   chmod -R 777 public/uploads
   ```
3. Start the Laravel development server:
   ```bash
   php artisan serve
   ```
4. Access the app: **`http://localhost:8000`**

---

### Option B: Fully Isolated Docker Production Stack
If you have Docker Desktop running, you can spin up the full production cluster containing an isolated MySQL instance, Nginx router, and compiled backend—without touching anything locally.

1. Ensure the `docker/` folder and `docker-compose.yml` configs are present.
2. In the root terminal, run:
   ```bash
   docker-compose up -d --build
   ```
3. The entrypoint script will automatically migrate the `db` cluster and clear the cache.
4. Access the fully containerized app: **`http://localhost:8080`**

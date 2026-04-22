# 🎬 Entertainment Tadka Movie Bot

Telegram Movie Bot with Auto-Delete Feature

## ✨ Features

- 🔍 Smart Movie Search
- 📥 Movie Delivery with Attribution
- 📝 Movie Request System
- ⏰ Auto-Delete Messages (10s/60s/300s)
- 📊 Progress Bar UI
- 👑 Admin Commands
- 🎯 Multiple Channels Support

## 🚀 Deployment on Render

1. Push code to GitHub
2. Connect repository to Render
3. Add environment variables (from .env file)
4. Deploy!

## 📋 Commands

### User Commands
- `/start` - Welcome message
- `/help` - Show all commands
- `/search <movie>` - Search movie
- `/request <movie>` - Request a movie
- `/myrequests` - Your pending requests
- `/channels` - Show all channels
- `/requestgroup` - Get request group link

### Admin Commands
- `/pending_requests` - View all pending requests
- `/bulk_approve` - Approve all pending requests
- `/stats` - Bot statistics

## 📁 CSV Format

```csv
movie_name,message_id,channel_id
Movie Name,12345,-1001234567890

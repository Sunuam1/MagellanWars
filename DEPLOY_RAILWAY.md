# 🚀 Deploy MagellanWars to Railway

This guide will help you deploy MagellanWars to Railway in just a few minutes!

## Prerequisites
- GitHub account
- Railway account (free at [railway.app](https://railway.app))

## Step 1: Push to GitHub

1. Create a new repository on GitHub
2. Push your code:
```bash
git init
git add .
git commit -m "Initial MagellanWars commit"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/MagellanWars.git
git push -u origin main
```

## Step 2: Deploy on Railway

### Method 1: One-Click Deploy (Easiest)

1. Go to [railway.app](https://railway.app)
2. Click "Start a New Project"
3. Choose "Deploy from GitHub repo"
4. Select your MagellanWars repository
5. Railway will automatically detect the Dockerfile

### Method 2: Using Railway CLI

```bash
# Install Railway CLI
npm install -g @railway/cli

# Login to Railway
railway login

# Initialize project in your MagellanWars directory
railway init

# Link to GitHub
railway link

# Deploy
railway up
```

## Step 3: Add MySQL Database

1. In your Railway project dashboard:
   - Click "New Service"
   - Choose "Database" 
   - Select "MySQL"
   - Click "Deploy"

2. Railway will automatically:
   - Create the MySQL instance
   - Set environment variables
   - Connect it to your app

## Step 4: Initialize Database

1. Click on your MySQL service in Railway
2. Go to "Connect" tab
3. Copy the connection command
4. Run the SQL schema:

```bash
# Connect to Railway MySQL
mysql -h [host] -u [user] -p[password] [database] < src/apps/archspace/DB/all.sql
```

Or use Railway's query tool:
1. Click "Query" in your MySQL service
2. Paste contents of `src/apps/archspace/DB/all.sql`
3. Execute

## Step 5: Environment Variables

Railway automatically sets these for MySQL:
- `MYSQLHOST`
- `MYSQLPORT`
- `MYSQLUSER`
- `MYSQLPASSWORD`
- `MYSQLDATABASE`

Your app is configured to use these automatically!

## Step 6: Access Your Game

1. Railway provides a URL like: `magellanwars-production.up.railway.app`
2. Visit the URL
3. Create your account and start playing!

## Monitoring & Logs

- View logs: Click on your service → "Logs" tab
- Monitor usage: Check the "Metrics" tab
- Set up alerts: Configure in "Settings"

## Custom Domain (Optional)

1. Go to Settings → Domains
2. Add your custom domain
3. Update your DNS CNAME to point to Railway

## Troubleshooting

### Database Connection Issues
- Ensure MySQL service is running
- Check environment variables are set
- View logs for connection errors

### Build Failures
- Check Dockerfile syntax
- Ensure all source files are committed
- Review build logs in Railway

### Performance
- Railway's free tier includes:
  - 500 hours/month execution time
  - 100GB bandwidth
  - 1GB RAM
- Upgrade to Pro for more resources

## Quick Start Script

Save this as `deploy.sh`:

```bash
#!/bin/bash
echo "🚀 Deploying MagellanWars to Railway..."

# Check if railway CLI is installed
if ! command -v railway &> /dev/null; then
    echo "Installing Railway CLI..."
    npm install -g @railway/cli
fi

# Login and deploy
railway login
railway init
railway up

echo "✅ Deployment initiated! Check Railway dashboard for status."
```

## Support

- Railway Docs: [docs.railway.app](https://docs.railway.app)
- MagellanWars Issues: Create issue on GitHub
- Railway Discord: [discord.gg/railway](https://discord.gg/railway)

---

## One Command Deploy

For the ultimate lazy deployment:

```bash
npx @railway/cli up
```

This single command will:
1. Install Railway CLI if needed
2. Login to Railway
3. Deploy your app
4. Provide you with a URL

That's it! Your game is live! 🎮
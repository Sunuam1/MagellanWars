#!/bin/bash

echo "🚀 MagellanWars Railway Deployment Script"
echo "========================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if git is initialized
if [ ! -d .git ]; then
    echo -e "${YELLOW}Initializing git repository...${NC}"
    git init
    git add .
    git commit -m "Initial MagellanWars commit for Railway deployment"
fi

# Check if Railway CLI is installed
if ! command -v railway &> /dev/null; then
    echo -e "${YELLOW}Railway CLI not found. Installing...${NC}"
    
    # Check if npm is available
    if command -v npm &> /dev/null; then
        npm install -g @railway/cli
    else
        echo -e "${RED}Error: npm is not installed. Please install Node.js first.${NC}"
        echo "Visit: https://nodejs.org/"
        exit 1
    fi
fi

echo -e "${GREEN}✓ Railway CLI is installed${NC}"

# Login to Railway
echo ""
echo -e "${YELLOW}Logging into Railway...${NC}"
railway login

# Initialize Railway project
echo ""
echo -e "${YELLOW}Initializing Railway project...${NC}"
railway init

# Link to GitHub (optional but recommended)
echo ""
echo -e "${YELLOW}Would you like to link to GitHub for automatic deployments? (y/n)${NC}"
read -r link_github

if [ "$link_github" = "y" ] || [ "$link_github" = "Y" ]; then
    railway link
fi

# Deploy
echo ""
echo -e "${GREEN}Deploying to Railway...${NC}"
railway up

# Add MySQL
echo ""
echo -e "${YELLOW}Setting up MySQL database...${NC}"
echo "Please add MySQL service in your Railway dashboard:"
echo "1. Go to your Railway project dashboard"
echo "2. Click 'New Service' → 'Database' → 'MySQL'"
echo "3. Railway will automatically connect it to your app"
echo ""
echo -e "${GREEN}After adding MySQL, run this command to initialize the database:${NC}"
echo "railway run mysql -u root -p < src/apps/archspace/DB/all.sql"
echo ""

# Get deployment URL
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}✅ Deployment Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Your app will be available at your Railway URL once deployment finishes."
echo "Check your Railway dashboard for the URL and deployment status."
echo ""
echo "Next steps:"
echo "1. Add MySQL database in Railway dashboard"
echo "2. Initialize database with the schema"
echo "3. Visit your app URL and create an account!"
echo ""
echo -e "${YELLOW}View logs:${NC} railway logs"
echo -e "${YELLOW}Open dashboard:${NC} railway open"
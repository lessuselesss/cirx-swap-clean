#!/bin/bash

# =============================================================================
# STAGING DEPLOYMENT SCRIPT
# =============================================================================
# Deploys the application to staging.domain.com via FTP
# Triggered on push to development branch
# =============================================================================

set -e

# Configuration
STAGING_ENV="${STAGING_ENV:-staging}"
BUILD_DIR_UI="ui/.output/public"
BUILD_DIR_BACKEND="backend"
DEPLOY_DIR="/public_html/staging"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting staging deployment...${NC}"

# =============================================================================
# BUILD FRONTEND
# =============================================================================
echo -e "${YELLOW}Building frontend for staging...${NC}"
cd ui

# Copy staging environment file
cp .env.staging .env

# Install dependencies and build
npm ci
npm run build

cd ..

# =============================================================================
# PREPARE BACKEND
# =============================================================================
echo -e "${YELLOW}Preparing backend for staging...${NC}"
cd backend

# Copy staging environment file
cp .env.staging .env

# Install composer dependencies (production mode)
composer install --no-dev --optimize-autoloader

# Clear and cache config for performance
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache

cd ..

# =============================================================================
# CREATE DEPLOYMENT PACKAGE
# =============================================================================
echo -e "${YELLOW}Creating deployment package...${NC}"

# Create temporary deployment directory
TEMP_DEPLOY_DIR="staging-deploy-$(date +%Y%m%d-%H%M%S)"
mkdir -p $TEMP_DEPLOY_DIR

# Copy frontend build
cp -r $BUILD_DIR_UI/* $TEMP_DEPLOY_DIR/

# Copy backend to subdirectory
mkdir -p $TEMP_DEPLOY_DIR/backend
cp -r backend/app $TEMP_DEPLOY_DIR/backend/
cp -r backend/config $TEMP_DEPLOY_DIR/backend/
cp -r backend/database $TEMP_DEPLOY_DIR/backend/
cp -r backend/public $TEMP_DEPLOY_DIR/backend/
cp -r backend/resources $TEMP_DEPLOY_DIR/backend/
cp -r backend/routes $TEMP_DEPLOY_DIR/backend/
cp -r backend/storage $TEMP_DEPLOY_DIR/backend/
cp -r backend/vendor $TEMP_DEPLOY_DIR/backend/
cp backend/.env.staging $TEMP_DEPLOY_DIR/backend/.env
cp backend/composer.json $TEMP_DEPLOY_DIR/backend/
cp backend/composer.lock $TEMP_DEPLOY_DIR/backend/
cp backend/artisan $TEMP_DEPLOY_DIR/backend/

# Create .htaccess for proper routing
cat > $TEMP_DEPLOY_DIR/.htaccess << 'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Handle backend API requests
    RewriteCond %{REQUEST_URI} ^/backend/api
    RewriteRule ^backend/api/(.*)$ backend/public/index.php [L]
    
    # Handle frontend routes
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.html [L]
</IfModule>

# Security headers
Header set X-Frame-Options "SAMEORIGIN"
Header set X-Content-Type-Options "nosniff"
Header set X-XSS-Protection "1; mode=block"
EOF

# Create backend .htaccess
cat > $TEMP_DEPLOY_DIR/backend/public/.htaccess << 'EOF'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
EOF

# =============================================================================
# FTP DEPLOYMENT
# =============================================================================
echo -e "${YELLOW}Deploying to staging server via FTP...${NC}"

# Create FTP commands file
cat > ftp-commands.txt << EOF
binary
cd $DEPLOY_DIR
mdelete *
rmdir backend
lcd $TEMP_DEPLOY_DIR
mput -r *
quit
EOF

# Execute FTP deployment
# Note: Replace with your actual FTP credentials
# For security, use environment variables in CI/CD
if [ -n "$FTP_HOST" ] && [ -n "$FTP_USER" ] && [ -n "$FTP_PASS" ]; then
    lftp -u $FTP_USER,$FTP_PASS $FTP_HOST < ftp-commands.txt
else
    echo -e "${RED}FTP credentials not set. Please set FTP_HOST, FTP_USER, and FTP_PASS environment variables.${NC}"
    echo "Manual FTP deployment required:"
    echo "1. Connect to your FTP server"
    echo "2. Navigate to $DEPLOY_DIR"
    echo "3. Upload contents of $TEMP_DEPLOY_DIR"
fi

# Clean up
rm -f ftp-commands.txt
rm -rf $TEMP_DEPLOY_DIR

echo -e "${GREEN}Staging deployment complete!${NC}"
echo -e "Visit ${GREEN}https://staging.domain.com${NC} to view the staging site"
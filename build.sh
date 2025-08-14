#!/bin/bash

# Circular CIRX OTC Platform Build Script for Cloudflare Pages
echo "🚀 Building Circular CIRX OTC Platform..."

# Navigate to UI directory
cd ui

# Install dependencies
echo "📦 Installing dependencies..."
npm ci

# Build the application
echo "🔨 Building application..."
npm run generate

echo "✅ Build completed successfully!"
echo "📁 Output directory: .output/public"
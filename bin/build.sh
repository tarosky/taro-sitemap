#!/usr/bin/env bash

set -e

# Set variables.
PREFIX="refs/tags/"
VERSION=${1#"$PREFIX"}

echo "Building v${VERSION}..."

# Install composer.
composer install --no-dev --prefer-dist

# Install NPM.
npm install
npm run build

# Create README.txt
curl -L https://raw.githubusercontent.com/fumikito/wp-readme/master/wp-readme.php | php

# Change version string.
sed -i.bak "s/Version: .*/Version: ${VERSION}/g" ./taro-sitemap.php
sed -i.bak "s/^Stable Tag: .*/Stable Tag: ${VERSION}/g" ./readme.txt
